<?php
namespace App\Controllers;

use Core\Controller;
use App\Services\AuthService;
use App\Services\ApplicationFormService;
use App\Services\ApplicationService;
use App\Services\ApplicationLayoutService;

class ApplicationController extends Controller
{
    private ApplicationFormService $formService;
    private ApplicationService $appService;
    private ApplicationLayoutService $layoutService;

    public function __construct()
    {
        $this->formService   = new ApplicationFormService();
        $this->appService    = new ApplicationService();
        $this->layoutService = new ApplicationLayoutService();
    }

    // =========================================================
    //  СПИСОК (шаблоны + поступившие заявки)
    // =========================================================

    /**
     * GET /moderation/applications
     * Можно передать ?tab=forms|submissions
     */
    public function index(array $params = [])
    {
        $this->guard();

        $tab = $_GET['tab'] ?? 'forms';
        if (!in_array($tab, ['forms', 'submissions'], true)) {
            $tab = 'forms';
        }

        $data = [
            'tab'            => $tab,
            'statusOptions'  => $this->formService->statusOptions(),
            'typeOptions'    => $this->formService->typeOptions(),
            'appStatusOpts'  => $this->appService->statusOptions(),
            'counts'         => $this->appService->countByStatus(),
        ];

        if ($tab === 'forms') {
            $filters = [
                'status' => $_GET['status'] ?? '',
                'q'      => trim($_GET['q'] ?? ''),
            ];
            $data['forms']   = $this->formService->findAll($filters);
            $data['filters'] = $filters;
        } else {
            $filters = [
                'status'  => $_GET['status'] ?? 'pending',
                'form_id' => $_GET['form_id'] ?? '',
                'q'       => trim($_GET['q'] ?? ''),
            ];
            $data['applications'] = $this->appService->findAll($filters);
            $data['formsList']    = $this->formService->findAll(['status' => 'published']);
            $data['filters']      = $filters;
        }

        $this->title = 'Заявки | Модерация';
        return $this->render('moderation/applications', $data);
    }

    // =========================================================
    //  ШАБЛОНЫ ФОРМ (CRUD)
    // =========================================================

    public function create(array $params = [])
    {
        $this->guardForms();

        $data = [
            'mode'           => 'create',
            'item'           => $this->emptyFormItem(),
            'layoutOptions'  => $this->formService->layoutOptions(),
            'layoutsMeta'     => $this->layoutsWithSections(),
            'typeOptions'    => $this->formService->typeOptions(),
            'statusOptions'  => $this->formService->statusOptions(),
            'roleOptions'    => $this->formService->roleOptions(),
            'errors'         => [],
        ];

        $this->title = 'Создать шаблон заявки | Модерация';
        return $this->render('moderation/application_form', $data);
    }

    public function store(array $params = [])
    {
        $this->guardForms();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('/moderation/applications'));
            exit;
        }

        $result = $this->formService->create($_POST);

        if (!$result['ok']) {
            $data = [
                'mode'           => 'create',
                'item'           => $this->itemFromPost($_POST),
                'layoutOptions'  => $this->formService->layoutOptions(),
            'layoutsMeta'     => $this->layoutsWithSections(),
                'typeOptions'    => $this->formService->typeOptions(),
                'statusOptions'  => $this->formService->statusOptions(),
                'roleOptions'    => $this->formService->roleOptions(),
                'errors'         => $result['errors'] ?? [],
            ];

            $this->title = 'Создать шаблон заявки | Модерация';
            return $this->render('moderation/application_form', $data);
        }

        header('Location: ' . url('/moderation/applications?tab=forms'));
        exit;
    }

    public function edit(array $params = [])
    {
        $this->guardForms();

        $id   = (int)($params['id'] ?? 0);
        $item = $this->formService->find($id);

        if (!$item) {
            header('Location: ' . url('/moderation/applications?tab=forms'));
            exit;
        }

        $data = [
            'mode'           => 'edit',
            'item'           => $item,
            'layoutOptions'  => $this->formService->layoutOptions(),
            'layoutsMeta'     => $this->layoutsWithSections(),
            'typeOptions'    => $this->formService->typeOptions(),
            'statusOptions'  => $this->formService->statusOptions(),
            'roleOptions'    => $this->formService->roleOptions(),
            'errors'         => [],
        ];

        $this->title = 'Редактировать шаблон | Модерация';
        return $this->render('moderation/application_form', $data);
    }

    public function update(array $params = [])
    {
        $this->guardForms();

        $id = (int)($params['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . url('/moderation/applications?tab=forms'));
            exit;
        }

        $result = $this->formService->update($id, $_POST);

        if (!$result['ok']) {
            $item = $this->formService->find($id) ?? [];
            $item = array_merge($item, $this->itemFromPost($_POST));

            $data = [
                'mode'           => 'edit',
                'item'           => $item,
                'layoutOptions'  => $this->formService->layoutOptions(),
            'layoutsMeta'     => $this->layoutsWithSections(),
                'typeOptions'    => $this->formService->typeOptions(),
                'statusOptions'  => $this->formService->statusOptions(),
                'roleOptions'    => $this->formService->roleOptions(),
                'errors'         => $result['errors'] ?? [],
            ];

            $this->title = 'Редактировать шаблон | Модерация';
            return $this->render('moderation/application_form', $data);
        }

        header('Location: ' . url('/moderation/applications?tab=forms'));
        exit;
    }

    public function delete(array $params = [])
    {
        $this->guardForms();

        $id = (int)($params['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . url('/moderation/applications?tab=forms'));
            exit;
        }

        $this->formService->delete($id);

        header('Location: ' . url('/moderation/applications?tab=forms'));
        exit;
    }

    // =========================================================
    //  ПОСТУПИВШИЕ ЗАЯВКИ (просмотр + review)
    // =========================================================

    public function show(array $params = [])
    {
        $this->guardReview();

        $id  = (int)($params['id'] ?? 0);
        $app = $this->appService->find($id);

        if (!$app) {
            header('Location: ' . url('/moderation/applications?tab=submissions'));
            exit;
        }

        $data = [
            'application'   => $app,
            'statusOptions' => $this->appService->statusOptions(),
        ];

        $this->title = 'Заявка #' . $id . ' | Модерация';
        return $this->render('moderation/application_show', $data);
    }

    public function review(array $params = [])
    {
        $this->guardReview();

        $id = (int)($params['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . url('/moderation/applications?tab=submissions'));
            exit;
        }

        $status  = $_POST['status'] ?? '';
        $comment = $_POST['admin_comment'] ?? null;

        $result = $this->appService->review($id, $status, $comment);

        if (!$result['ok']) {
            // Можно flash-сообщение, пока просто редирект
            header('Location: ' . url('/moderation/applications/show/' . $id));
            exit;
        }

        header('Location: ' . url('/moderation/applications?tab=submissions'));
        exit;
    }

    // =========================================================
    //  МАКЕТЫ (базовый CRUD)
    // =========================================================

    public function layouts(array $params = [])
    {
        $this->guardLayouts();

        $data = [
            'layouts' => $this->layoutService->findAll(),
        ];

        $this->title = 'Макеты заявок | Модерация';
        return $this->render('moderation/application_layouts', $data);
    }

    public function layoutCreate(array $params = [])
    {
        $this->guardLayouts();

        $data = [
            'mode'   => 'create',
            'item'   => [
                'name'          => '',
                'slug'          => '',
                'description'   => '',
                'columns_count' => 12,
                'gap'           => 16,
                'settings'      => [],
                'is_system'     => 0,
            ],
            'errors' => [],
        ];

        $this->title = 'Создать макет | Модерация';
        return $this->render('moderation/application_layout_form', $data);
    }

    public function layoutStore(array $params = [])
    {
        $this->guardLayouts();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('/moderation/applications/layouts'));
            exit;
        }

        $result = $this->layoutService->create($_POST);

        if (!$result['ok']) {
            $data = [
                'mode'   => 'create',
                'item'   => [
                    'name'          => trim($_POST['name'] ?? ''),
                    'slug'          => trim($_POST['slug'] ?? ''),
                    'description'   => trim($_POST['description'] ?? ''),
                    'columns_count' => (int)($_POST['columns_count'] ?? 12),
                    'gap'           => (int)($_POST['gap'] ?? 16),
                    'settings'      => [],
                    'is_system'     => !empty($_POST['is_system']) ? 1 : 0,
                ],
                'errors' => $result['errors'] ?? [],
            ];

            $this->title = 'Создать макет | Модерация';
            return $this->render('moderation/application_layout_form', $data);
        }

        header('Location: ' . url('/moderation/applications/layouts'));
        exit;
    }

    public function layoutEdit(array $params = [])
    {
        $this->guardLayouts();

        $id   = (int)($params['id'] ?? 0);
        $item = $this->layoutService->find($id);

        if (!$item) {
            header('Location: ' . url('/moderation/applications/layouts'));
            exit;
        }

        $data = [
            'mode'   => 'edit',
            'item'   => $item,
            'errors' => [],
        ];

        $this->title = 'Редактировать макет | Модерация';
        return $this->render('moderation/application_layout_form', $data);
    }

    public function layoutUpdate(array $params = [])
    {
        $this->guardLayouts();

        $id = (int)($params['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . url('/moderation/applications/layouts'));
            exit;
        }

        $result = $this->layoutService->update($id, $_POST);

        if (!$result['ok']) {
            $item = $this->layoutService->find($id) ?? [];
            $item = array_merge($item, [
                'name'          => trim($_POST['name'] ?? ''),
                'slug'          => trim($_POST['slug'] ?? ''),
                'description'   => trim($_POST['description'] ?? ''),
                'columns_count' => (int)($_POST['columns_count'] ?? 12),
                'gap'           => (int)($_POST['gap'] ?? 16),
                'is_system'     => !empty($_POST['is_system']) ? 1 : 0,
            ]);

            $data = [
                'mode'   => 'edit',
                'item'   => $item,
                'errors' => $result['errors'] ?? [],
            ];

            $this->title = 'Редактировать макет | Модерация';
            return $this->render('moderation/application_layout_form', $data);
        }

        header('Location: ' . url('/moderation/applications/layouts'));
        exit;
    }

    public function layoutDelete(array $params = [])
    {
        $this->guardLayouts();

        $id = (int)($params['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . url('/moderation/applications/layouts'));
            exit;
        }

        $this->layoutService->delete($id);

        header('Location: ' . url('/moderation/applications/layouts'));
        exit;
    }

    // =========================================================
    //  HELPERS
    // =========================================================


    // =========================================================
    //  ПУБЛИЧНАЯ ЧАСТЬ (для игроков)
    // =========================================================

    /**
     * GET /application/:id — анкета для игрока
     */
    public function publicShow(array $params = [])
    {
        $id = (int)($params['id'] ?? 0);
        $form = $this->formService->find($id);

        if (!$form || ($form['status'] ?? '') !== 'published') {
            $this->title = 'Заявка не найдена';
            return $this->render('error/notFound', []);
        }

        $user = AuthService::user();
        $userId = $user['id'] ?? null;

        $canSubmit = false;
        $alreadyPending = false;
        $closed = empty($form['accepting']);

        if ($userId) {
            $canSubmit = !$closed && $this->appService->canSubmit($form, (int)$userId);
            if ($canSubmit) {
                $appModel = new \App\Models\Application();
                $alreadyPending = $appModel->hasPending($id, (int)$userId);
            }
        }

        $layout = null;
        if (!empty($form['layout_id'])) {
            $layout = $this->layoutService->find((int)$form['layout_id']);
        }

        $data = [
            'form'            => $form,
            'layout'          => $layout,
            'user'            => $user,
            'canSubmit'       => $canSubmit,
            'alreadyPending'  => $alreadyPending,
            'closed'          => $closed,
            'errors'          => [],
            'old'             => [],
            'success'         => false,
        ];

        $this->title = ($form['name'] ?? 'Заявка') . ' | SweetLolly Форум';
        return $this->render('application/show', $data);
    }

    /**
     * POST /application/:id — отправка заявки
     */
    public function publicSubmit(array $params = [])
    {
        $id = (int)($params['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('/application/' . $id));
            exit;
        }

        if (!AuthService::check()) {
            header('Location: ' . url('/application/' . $id));
            exit;
        }

        $form = $this->formService->find($id);
        if (!$form || ($form['status'] ?? '') !== 'published') {
            $this->title = 'Заявка не найдена';
            return $this->render('error/notFound', []);
        }

        $user = AuthService::user();
        $userId = (int)$user['id'];

        // answers: name="answers[q_id]"
        $answers = $_POST['answers'] ?? [];
        if (!is_array($answers)) {
            $answers = [];
        }

        $result = $this->appService->submit($id, $answers, $userId);

        $layout = null;
        if (!empty($form['layout_id'])) {
            $layout = $this->layoutService->find((int)$form['layout_id']);
        }

        if (!$result['ok']) {
            $data = [
                'form'            => $form,
                'layout'          => $layout,
                'user'            => $user,
                'canSubmit'       => true,
                'alreadyPending'  => false,
                'closed'          => empty($form['accepting']),
                'errors'          => $result['errors'] ?? [],
                'old'             => $answers,
                'success'         => false,
            ];
            $this->title = ($form['name'] ?? 'Заявка') . ' | SweetLolly Форум';
            return $this->render('application/show', $data);
        }

        $data = [
            'form'            => $form,
            'layout'          => $layout,
            'user'            => $user,
            'canSubmit'       => false,
            'alreadyPending'  => true,
            'closed'          => empty($form['accepting']),
            'errors'          => [],
            'old'             => [],
            'success'         => true,
            'applicationId'   => $result['id'] ?? null,
        ];

        $this->title = 'Заявка отправлена | SweetLolly Форум';
        return $this->render('application/show', $data);
    }

    /**
     * GET /applications — список открытых анкет
     */
    public function publicList(array $params = [])
    {
        $forms = $this->formService->findPublishedAccepting();

        $data = [
            'forms' => $forms,
            'user'  => AuthService::user(),
        ];

        $this->title = 'Заявки | SweetLolly Форум';
        return $this->render('application/list', $data);
    }


    /**
     * Макеты с разделами для JS/селектов в форме шаблона
     * @return array<int, array{id:int,name:string,columns:int,gap:int,sections:array}>
     */
    private function layoutsWithSections(): array
    {
        $all = $this->layoutService->findAll();
        $result = [];
        foreach ($all as $layout) {
            $full = $this->layoutService->find((int)$layout['id']);
            if (!$full) continue;
            $settings = $full['settings'] ?? [];
            if (is_string($settings)) {
                $settings = json_decode($settings, true) ?: [];
            }
            $result[(int)$full['id']] = [
                'id'       => (int)$full['id'],
                'name'     => $full['name'],
                'columns'  => (int)($full['columns_count'] ?? 12),
                'gap'      => (int)($full['gap'] ?? 16),
                'sections' => $settings['sections'] ?? [],
            ];
        }
        return $result;
    }

    private function emptyFormItem(): array
    {
        return [
            'name'               => '',
            'slug'               => '',
            'application_type'   => '',
            'description'        => '',
            'layout_id'          => null,
            'allowed_submitters' => [],
            'notify_user_ids'    => [],
            'visibility'         => [],
            'questions'          => [],
            'accepting'          => 0,
            'status'             => 'draft',
        ];
    }

    private function itemFromPost(array $post): array
    {
        $allowed = $post['allowed_submitters'] ?? [];
        if (!is_array($allowed)) {
            $allowed = [];
        }

        $visibility = $post['visibility'] ?? [];
        if (!is_array($visibility)) {
            $visibility = [];
        }

        $notify = $post['notify_user_ids'] ?? [];
        if (is_string($notify)) {
            // можно передавать через запятую
            $notify = array_filter(array_map('intval', explode(',', $notify)));
        }
        if (!is_array($notify)) {
            $notify = [];
        }

        $questions = $post['questions'] ?? [];
        if (is_string($questions)) {
            $questions = json_decode($questions, true) ?: [];
        }

        return [
            'name'               => trim($post['name'] ?? ''),
            'slug'               => trim($post['slug'] ?? ''),
            'application_type'   => $post['application_type'] ?? '',
            'description'        => trim($post['description'] ?? ''),
            'layout_id'          => ($post['layout_id'] ?? '') !== '' ? (int)$post['layout_id'] : null,
            'allowed_submitters' => array_map('intval', $allowed),
            'notify_user_ids'    => array_map('intval', $notify),
            'visibility'         => array_map('intval', $visibility),
            'questions'          => is_array($questions) ? $questions : [],
            'accepting'          => !empty($post['accepting']) ? 1 : 0,
            'status'             => $post['status'] ?? 'draft',
        ];
    }

    private function guard(): void
    {
        if (!AuthService::check() || (
            !AuthService::can('applications.view')
            && !AuthService::can('applications.forms.manage')
            && !AuthService::can('applications.review')
            && !AuthService::hasRole('admin')
        )) {
            header('Location: ' . url('/'));
            exit;
        }
    }

    private function guardForms(): void
    {
        if (!AuthService::check() || (
            !AuthService::can('applications.forms.manage')
            && !AuthService::hasRole('admin')
        )) {
            header('Location: ' . url('/moderation/applications'));
            exit;
        }
    }

    private function guardReview(): void
    {
        if (!AuthService::check() || (
            !AuthService::can('applications.review')
            && !AuthService::hasRole('admin')
        )) {
            header('Location: ' . url('/moderation/applications'));
            exit;
        }
    }

    private function guardLayouts(): void
    {
        if (!AuthService::check() || (
            !AuthService::can('applications.layouts.manage')
            && !AuthService::hasRole('admin')
        )) {
            header('Location: ' . url('/moderation/applications'));
            exit;
        }
    }

}