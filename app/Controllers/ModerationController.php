<?php
namespace App\Controllers;

use Core\Controller;
use App\Services\AuthService;
use App\Models\ActivityLog;
use App\Models\Section;
use App\Models\Report;
use App\Models\Role;

class ModerationController extends Controller
{
    public function index(array $params = [])
    {
        // Тестовый loginAs убран — входите через /login

        // Не авторизован или обычный пользователь → на главную
        if (!AuthService::check()) {
            $this->deny();
        }

        if (!AuthService::can('resolve_report') && !AuthService::can('applications.view') && !AuthService::can('manage_roles') && !AuthService::can('lock_thread') && !AuthService::can('players.view') && !AuthService::hasRole('admin')) {
            $this->deny();
        }

        $tab = $params['tab'] ?? 'overview';

        // Все возможные табы + нужное право
        $allTabs = [
            // ['id' => 'overview',     'title' => 'Обзор',      'href' => url('/moderation/overview'),     'permission' => null],
            ['id' => 'players',      'title' => 'Игроки',     'href' => url('/moderation/players'),      'permission' => 'players.view'],
            ['id' => 'roles',        'title' => 'Роли',       'href' => url('/moderation/roles'),        'permission' => 'manage_roles'],
            ['id' => 'complaints',   'title' => 'Жалобы',     'href' => url('/moderation/complaints'),   'permission' => 'resolve_report'],
            ['id' => 'reports',      'title' => 'Репорты',    'href' => url('/moderation/reports'),      'permission' => 'resolve_report'],
            ['id' => 'applications', 'title' => 'Заявки',     'href' => url('/moderation/applications'), 'permission' => 'applications.view'],
            ['id' => 'sections',     'title' => 'Разделы',    'href' => url('/moderation/sections'),     'permission' => 'manage_categories'],
            ['id' => 'topics',       'title' => 'Темы',       'href' => url('/moderation/topics'),       'permission' => 'lock_thread'],
            ['id' => 'discussions',  'title' => 'Обсуждения', 'href' => url('/moderation/discussions'),  'permission' => 'edit_any_post'],
            ['id' => 'logs',         'title' => 'Логи',       'href' => url('/moderation/logs'),         'permission' => 'view_logs'],
        ];

        // Оставляем только доступные табы
        $tabs = array_values(array_filter($allTabs, function ($item) {
            return empty($item['permission']) || AuthService::can($item['permission']);
        }));

        // Если после фильтрации текущий таб недоступен — редирект на первый доступный
        $allowedIds = array_column($tabs, 'id');
        if (!in_array($tab, $allowedIds, true)) {
            $first = $tabs[0]['id'] ?? 'overview';
            header('Location: ' . url('/moderation/' . $first));
            exit;
        }

        $this->title = 'Модерация | SweetLolly Форум';

        $data = [
            'currentTab' => $tab,
            'tabs'       => $tabs,
            'user'       => AuthService::user(),
        ];

        // Данные конкретного таба
        match ($tab) {
            'sections'     => $this->handleSections($data),
            'reports'      => $this->handleReports($data),
            'logs'         => $this->handleLogs($data),
            'applications' => $this->handleApplications($data),
            'roles'        => $this->handleRoles($data),
            'players'      => $this->handlePlayers($data),
            'topics'       => $this->handleTopics($data),
            default        => null,
        };

        return $this->render('moderation/index', $data);
    }

    // =========================================================
    //  РАЗДЕЛЫ
    // =========================================================
    private function handleSections(array &$data): void
    {
        $sectionModel = new Section();
        $roleModel    = new Role();

        $filters = [
            'q'            => trim((string)($_GET['q'] ?? '')),
            'category_id'  => ($_GET['category_id'] ?? '') !== '' ? (int)$_GET['category_id'] : null,
            'view_role_id' => trim((string)($_GET['view_role_id'] ?? '')),
        ];
        $filters = array_filter($filters, static fn($v) => $v !== null && $v !== '');

        $categories = $sectionModel->getTree($filters);

        // Выбранный элемент
        $selectedId = (int)($_GET['id'] ?? 0);
        $selected   = $selectedId > 0 ? $sectionModel->findById($selectedId) : null;

        $data['categories']      = $categories;
        $data['selected']        = $selected;
        $data['filters']         = $filters;
        $data['categoryOptions'] = $sectionModel->getCategoryOptions();

        // '' = Публичный + роли по id
        $data['accessOptions'] = ['' => 'Публичный'] + $roleModel->getOptions();
        $data['roleOptions']   = $roleModel->getOptions(); // id => label
    }

    // =========================================================
    //  РЕПОРТЫ
    // =========================================================
    private function handleReports(array &$data): void
    {
        $reportModel = new Report();

        $filters = [
            'q'           => trim((string)($_GET['q'] ?? '')),
            'status'      => trim((string)($_GET['status'] ?? '')),
            'target_type' => trim((string)($_GET['target_type'] ?? '')),
            'date'        => trim((string)($_GET['date'] ?? '')),
        ];
        $filters = array_filter($filters, static fn($v) => $v !== null && $v !== '');

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 10;

        $list = $reportModel->getList($page, $perPage, $filters);

        $selectedId = (int)($_GET['id'] ?? 0);
        $selected   = $selectedId > 0 ? $reportModel->findById($selectedId) : null;

        $data['items']             = $list['items'];
        $data['total']             = $list['total'];
        $data['page']              = $list['page'];
        $data['pages']             = $list['pages'];
        $data['selected']          = $selected;
        $data['filters']           = $filters;
        $data['statusOptions']     = $reportModel->getStatusOptions();
        $data['targetTypeOptions'] = $reportModel->getTargetTypeOptions();
    }

    // =========================================================
    //  ЛОГИ
    // =========================================================
    private function handleLogs(array &$data): void
    {
        $logModel = new ActivityLog();

        $filters = [
            'q'        => trim((string)($_GET['q'] ?? '')),
            'date'     => trim((string)($_GET['date'] ?? '')),
            'admin_id' => ($_GET['admin_id'] ?? '') !== '' ? (int)$_GET['admin_id'] : null,
            'action'   => trim((string)($_GET['action'] ?? '')),
            'result'   => trim((string)($_GET['result'] ?? '')),
            'role'     => trim((string)($_GET['role'] ?? '')),
        ];
        $filters = array_filter($filters, static fn($v) => $v !== null && $v !== '');

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 7;

        $list = $logModel->getList($page, $perPage, $filters);

        $selectedId  = (int)($_GET['id'] ?? 0);
        $selectedLog = $selectedId > 0 ? $logModel->findById($selectedId) : null;

        $logHistory = [];
        if ($selectedLog) {
            $logHistory = $logModel->getHistoryForTarget(
                $selectedLog['target_type'] ?? null,
                isset($selectedLog['target_id']) ? (int)$selectedLog['target_id'] : null
            );
        }

        $data['logs']          = $list['items'];
        $data['logsTotal']     = $list['total'];
        $data['logsPage']      = $list['page'];
        $data['logsPages']     = $list['pages'];
        $data['selectedLog']   = $selectedLog;
        $data['logHistory']    = $logHistory;
        $data['filters']       = $filters;
        $data['filterOptions'] = [
            'admins'  => $logModel->getActorOptions(),
            'actions' => $logModel->getActionOptions(),
            'roles'   => $logModel->getRoleOptions(),
            'results' => [
                'success' => 'Успешно',
                'denied'  => 'Отказано',
                'error'   => 'Ошибка',
            ],
        ];
    }


    // =========================================================
    //  ЗАЯВКИ
    // =========================================================
    private function handleApplications(array &$data): void
    {
        $formService = new \App\Services\ApplicationFormService();
        $appService  = new \App\Services\ApplicationService();

        $sub = $_GET['sub'] ?? 'submissions';
        if (!in_array($sub, ['submissions', 'forms'], true)) {
            $sub = 'submissions';
        }

        $data['appTab']        = $sub;
        $data['statusOptions'] = $formService->statusOptions();
        $data['typeOptions']   = $formService->typeOptions();
        $data['appStatusOpts'] = $appService->statusOptions();
        $data['counts']        = $appService->countByStatus();

        if ($sub === 'forms') {
            $filters = [
                'status' => $_GET['status'] ?? '',
                'q'      => trim($_GET['q'] ?? ''),
                'sub'    => 'forms',
            ];
            $data['forms']   = $formService->findAll([
                'status' => $filters['status'],
                'q'      => $filters['q'],
            ]);
            $data['filters'] = $filters;

            if (!empty($_GET['id'])) {
                $data['selected'] = $formService->find((int)$_GET['id']);
            }
        } else {
            $filters = [
                'status'  => $_GET['status'] ?? 'pending',
                'form_id' => $_GET['form_id'] ?? '',
                'q'       => trim($_GET['q'] ?? ''),
                'sub'     => 'submissions',
            ];
            $data['applications'] = $appService->findAll([
                'status'  => $filters['status'],
                'form_id' => $filters['form_id'],
                'q'       => $filters['q'],
            ]);
            $data['formsList'] = $formService->findAll(['status' => 'published']);
            $data['filters']   = $filters;

            if (!empty($_GET['id'])) {
                $data['selected'] = $appService->find((int)$_GET['id']);
            }
        }
    }


    private function handleRoles(array &$data): void
    {
        $service = new \App\Services\RoleService();
        $data['roles'] = $service->findAll();
    }


    private function handlePlayers(array &$data): void
    {
        $service = new \App\Services\PlayerService();

        $filters = [
            'q'       => trim((string)($_GET['q'] ?? '')),
            'role_id' => $_GET['role_id'] ?? '',
            'banned'  => $_GET['banned'] ?? '',
            'active'  => $_GET['active'] ?? '',
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));
        $list = $service->list([
            'q'       => $filters['q'],
            'role_id' => $filters['role_id'] !== '' ? (int)$filters['role_id'] : null,
            'banned'  => $filters['banned'] !== '' ? $filters['banned'] : null,
            'active'  => $filters['active'] !== '' ? $filters['active'] : null,
        ], $page, 20);

        $data['players']     = $list['items'];
        $data['total']       = $list['total'];
        $data['page']        = $list['page'];
        $data['pages']       = $list['pages'];
        $data['filters']     = array_filter($filters, static fn($v) => $v !== '' && $v !== null);
        $data['roleOptions'] = $service->roleOptions();

        $selectedId = (int)($_GET['id'] ?? 0);
        $data['selected'] = $selectedId > 0 ? $service->find($selectedId) : null;
    }

    private function handleTopics(array &$data): void
    {
        $service = new \App\Services\ThreadService();

        $filters = [
            'q'           => trim((string)($_GET['q'] ?? '')),
            'category_id' => $_GET['category_id'] ?? '',
            'locked'      => $_GET['locked'] ?? '',
            'sticky'      => $_GET['sticky'] ?? '',
            'hidden'      => $_GET['hidden'] ?? '',
        ];
        $page = max(1, (int)($_GET['page'] ?? 1));

        $viewerId = (int)(\App\Services\AuthService::user()['id'] ?? 0);

        $list = $service->list([
            'q'           => $filters['q'],
            'category_id' => $filters['category_id'] !== '' ? (int)$filters['category_id'] : null,
            'locked'      => $filters['locked'] !== '' ? $filters['locked'] : null,
            'sticky'      => $filters['sticky'] !== '' ? $filters['sticky'] : null,
            'hidden'      => $filters['hidden'] !== '' ? $filters['hidden'] : null,
            'show_hidden' => $filters['hidden'] !== '',
            'viewer_id'   => $viewerId,
            'status'      => $_GET['status'] ?? null,
        ], $page, 20);

        $data['topics']          = $list['items'];
        $data['total']           = $list['total'];
        $data['page']            = $list['page'];
        $data['pages']           = $list['pages'];
        $data['filters']         = array_filter($filters, static fn($v) => $v !== '' && $v !== null);
        $data['categoryOptions'] = $service->categoryOptions();

        $selectedId = (int)($_GET['id'] ?? 0);
        $data['selected'] = $selectedId > 0 ? $service->find($selectedId) : null;
    }

    private function deny(): void
    {
        header('Location: ' . url('/'));
        exit;
    }
}