<?php
namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationMessage;
use App\Services\AuthService;
use App\Services\ActivityLogService;
use App\Services\ApplicationFormService;

class ApplicationService
{
    private Application $model;
    private ApplicationMessage $messages;
    private ApplicationFormService $formService;

    public function __construct()
    {
        $this->model = new Application();
        $this->messages = new ApplicationMessage();
        $this->formService = new ApplicationFormService();
    }

    public function findAll(array $filters = []): array
    {
        return $this->model->findAll($filters);
    }

    public function find(int $id): ?array
    {
        return $this->model->findById($id);
    }

    public function findForUser(int $userId): array
    {
        return $this->model->findByUser($userId);
    }

    public function countByStatus(?int $formId = null): array
    {
        return $this->model->countByStatus($formId);
    }

    public function messages(int $applicationId, bool $staff = false): array
    {
        return $this->messages->findByApplication($applicationId, $staff);
    }

    /**
     * @return array{ok: bool, id?: int, errors?: array}
     */
    public function submit(int $formId, array $answers, int $userId, array $files = []): array
    {
        $form = $this->formService->find($formId);
        if (!$form) {
            return ['ok' => false, 'errors' => ['form' => 'Шаблон не найден']];
        }

        if (($form['status'] ?? '') !== 'published' || empty($form['accepting'])) {
            return ['ok' => false, 'errors' => ['form' => 'Приём заявок закрыт']];
        }

        if (!$this->canSubmit($form, $userId)) {
            return ['ok' => false, 'errors' => ['form' => 'Нет прав на подачу']];
        }

        if ($this->model->hasPending($formId, $userId)) {
            return ['ok' => false, 'errors' => ['form' => 'У вас уже есть активная заявка']];
        }

        $questions = $form['questions'] ?? [];
        $errors = $this->validateAnswers($questions, $answers, $files);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $snapshot = [
            'name'        => $form['name'],
            'description' => $form['description'] ?? null,
            'questions'   => $questions,
            'type'        => $form['application_type'] ?? null,
        ];

        $id = $this->model->create([
            'form_id'       => $formId,
            'user_id'       => $userId,
            'answers'       => $answers,
            'form_snapshot' => $snapshot,
            'status'        => 'pending',
        ]);

        // Файлы из $_FILES['answers']
        $fileResult = $this->processAnswerFiles($id, $userId, $questions, $files, $answers);
        if (!$fileResult['ok']) {
            // откатить заявку при ошибке файла
            $this->model->setStatus($id, 'withdrawn', ['admin_comment' => 'Ошибка загрузки файла']);
            return ['ok' => false, 'errors' => $fileResult['errors'] ?? ['form' => 'Ошибка файла']];
        }
        $answers = $fileResult['answers'];
        $this->updateAnswers($id, $answers);

        $this->messages->create([
            'application_id' => $id,
            'user_id'        => $userId,
            'body'           => 'Заявка отправлена',
            'is_internal'    => 0,
            'kind'           => 'system',
        ]);

        ActivityLogService::applicationSubmitted(
            $userId,
            $id,
            $formId,
            $form['name'] ?? ('Форма #' . $formId)
        );

        return ['ok' => true, 'id' => $id];
    }

    /**
     * Принять / отклонить
     * @return array{ok: bool, errors?: array}
     */
    public function review(int $id, string $status, ?string $comment = null): array
    {
        $app = $this->model->findById($id);
        if (!$app) {
            return ['ok' => false, 'errors' => ['id' => 'Заявка не найдена']];
        }

        if (!in_array($app['status'], ['pending', 'needs_info'], true)) {
            return ['ok' => false, 'errors' => ['status' => 'Заявка уже закрыта']];
        }

        if (!in_array($status, ['accepted', 'rejected'], true)) {
            return ['ok' => false, 'errors' => ['status' => 'Недопустимый статус']];
        }

        $reviewerId = AuthService::user()['id'] ?? null;

        $this->model->setStatus($id, $status, [
            'reviewed_by'   => $reviewerId,
            'reviewed_at'   => date('Y-m-d H:i:s'),
            'admin_comment' => $comment !== null ? trim($comment) : null,
        ]);

        if ($reviewerId) {
            $label = $status === 'accepted' ? 'Заявка одобрена' : 'Заявка отклонена';
            if ($comment) {
                $label .= ': ' . trim($comment);
            }
            $this->messages->create([
                'application_id' => $id,
                'user_id'        => $reviewerId,
                'body'           => $label,
                'is_internal'    => 0,
                'kind'           => 'system',
            ]);

            ActivityLogService::applicationReviewed(
                (int)$reviewerId,
                $id,
                $app['form_name'] ?? ('Заявка #' . $id),
                $status,
                $comment
            );
        }

        return ['ok' => true];
    }

    /**
     * Запросить уточнение у игрока
     */
    public function requestClarification(int $id, string $body, int $staffId): array
    {
        $app = $this->model->findById($id);
        if (!$app) {
            return ['ok' => false, 'errors' => ['id' => 'Заявка не найдена']];
        }

        if (!in_array($app['status'], ['pending', 'needs_info'], true)) {
            return ['ok' => false, 'errors' => ['status' => 'Нельзя запросить уточнение']];
        }

        $body = trim($body);
        if ($body === '') {
            return ['ok' => false, 'errors' => ['body' => 'Напишите, что нужно уточнить']];
        }

        $this->model->setStatus($id, 'needs_info', [
            'reviewed_by' => $staffId,
        ]);

        $this->messages->create([
            'application_id' => $id,
            'user_id'        => $staffId,
            'body'           => $body,
            'is_internal'    => 0,
            'kind'           => 'clarification',
        ]);

        ActivityLogService::log(
            $staffId,
            'requested_application_info',
            'success',
            'application',
            $id,
            $app['form_name'] ?? ('#' . $id),
            null,
            $body
        );

        return ['ok' => true];
    }

    /**
     * Сообщение staff игроку (не меняет статус)
     */
    public function staffReply(int $id, string $body, int $staffId, bool $internal = false): array
    {
        $app = $this->model->findById($id);
        if (!$app) {
            return ['ok' => false, 'errors' => ['id' => 'Заявка не найдена']];
        }

        $body = trim($body);
        if ($body === '') {
            return ['ok' => false, 'errors' => ['body' => 'Пустое сообщение']];
        }

        $this->messages->create([
            'application_id' => $id,
            'user_id'        => $staffId,
            'body'           => $body,
            'is_internal'    => $internal ? 1 : 0,
            'kind'           => 'message',
        ]);

        return ['ok' => true];
    }

    /**
     * Ответ игрока на уточнение → снова pending
     */
    public function playerReply(int $id, string $body, int $userId): array
    {
        $app = $this->model->findById($id);
        if (!$app) {
            return ['ok' => false, 'errors' => ['id' => 'Заявка не найдена']];
        }

        if ((int)$app['user_id'] !== $userId) {
            return ['ok' => false, 'errors' => ['id' => 'Нет доступа']];
        }

        if (!in_array($app['status'], ['pending', 'needs_info'], true)) {
            return ['ok' => false, 'errors' => ['status' => 'Заявка закрыта']];
        }

        $body = trim($body);
        if ($body === '') {
            return ['ok' => false, 'errors' => ['body' => 'Введите ответ']];
        }

        $this->messages->create([
            'application_id' => $id,
            'user_id'        => $userId,
            'body'           => $body,
            'is_internal'    => 0,
            'kind'           => 'message',
        ]);

        if ($app['status'] === 'needs_info') {
            $this->model->setStatus($id, 'pending');
            $this->messages->create([
                'application_id' => $id,
                'user_id'        => $userId,
                'body'           => 'Игрок ответил на уточнение — заявка снова на рассмотрении',
                'is_internal'    => 0,
                'kind'           => 'system',
            ]);
        }

        ActivityLogService::log(
            $userId,
            'replied_application',
            'success',
            'application',
            $id,
            $app['form_name'] ?? ('#' . $id)
        );

        return ['ok' => true];
    }

    /**
     * Игрок отзывает заявку
     */
    public function withdraw(int $id, int $userId): array
    {
        $app = $this->model->findById($id);
        if (!$app) {
            return ['ok' => false, 'errors' => ['id' => 'Заявка не найдена']];
        }

        if ((int)$app['user_id'] !== $userId) {
            return ['ok' => false, 'errors' => ['id' => 'Нет доступа']];
        }

        if (!in_array($app['status'], ['pending', 'needs_info'], true)) {
            return ['ok' => false, 'errors' => ['status' => 'Нельзя отозвать']];
        }

        $this->model->setStatus($id, 'withdrawn', [
            'reviewed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->messages->create([
            'application_id' => $id,
            'user_id'        => $userId,
            'body'           => 'Заявка отозвана игроком',
            'is_internal'    => 0,
            'kind'           => 'system',
        ]);

        return ['ok' => true];
    }

    public function statusOptions(): array
    {
        return [
            'pending'    => 'На рассмотрении',
            'needs_info' => 'Нужно уточнение',
            'accepted'   => 'Принята',
            'rejected'   => 'Отклонена',
            'withdrawn'  => 'Отозвана',
        ];
    }

    public function statusBadgeClass(string $status): string
    {
        return match ($status) {
            'pending'    => 'badge-info',
            'needs_info' => 'badge-warning',
            'accepted'   => 'badge-success',
            'rejected'   => 'badge-danger',
            'withdrawn'  => 'badge-secondary',
            default      => 'badge-secondary',
        };
    }

    public function canSubmit(array $form, int $userId): bool
    {
        $allowed = $form['allowed_submitters'] ?? [];
        if (!is_array($allowed) || $allowed === []) {
            return true;
        }

        $user = AuthService::user();
        if (!$user) {
            return false;
        }

        return in_array((int)($user['role_id'] ?? 0), array_map('intval', $allowed), true);
    }

    public function canView(array $form): bool
    {
        if (AuthService::hasRole('admin') || AuthService::can('applications.view')) {
            return true;
        }
        return false;
    }

    private function validateAnswers(array $questions, array $answers, array $files = []): array
    {
        $errors = [];
        $fileMap = $this->normalizeFilesBag($files);

        foreach ($questions as $q) {
            if (empty($q['required'])) {
                continue;
            }
            $qid = $q['id'] ?? null;
            if (!$qid) {
                continue;
            }

            // Файлы приходят в $_FILES, не в POST answers
            if (($q['type'] ?? '') === 'file') {
                $file = $fileMap[$qid] ?? null;
                $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
                if (!$file || $err === UPLOAD_ERR_NO_FILE) {
                    $errors[$qid] = 'Прикрепите файл';
                } elseif ($err !== UPLOAD_ERR_OK) {
                    $errors[$qid] = 'Ошибка загрузки файла';
                }
                continue;
            }

            $val = $answers[$qid] ?? null;
            $empty = $val === null || $val === '' || (is_array($val) && $val === []);
            if ($empty) {
                $errors[$qid] = 'Обязательное поле';
            }
        }
        return $errors;
    }

    private function updateAnswers(int $id, array $answers): void
    {
        $this->model->updateAnswers($id, $answers);
    }

    /**
     * @return array{ok:bool, answers?:array, errors?:array}
     */
    private function processAnswerFiles(
        int $applicationId,
        int $userId,
        array $questions,
        array $files,
        array $answers
    ): array {
        $attach = new AttachmentService();
        $errors = [];
        $normalized = $this->normalizeFilesBag($files);

        foreach ($questions as $q) {
            if (($q['type'] ?? '') !== 'file') {
                continue;
            }
            $qid = $q['id'] ?? null;
            if (!$qid) {
                continue;
            }

            $file = $normalized[$qid] ?? null;
            $required = !empty($q['required']);

            if (!$file || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                if ($required) {
                    $errors[$qid] = 'Прикрепите файл';
                }
                continue;
            }

            if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $errors[$qid] = 'Ошибка загрузки файла';
                continue;
            }

            $result = $attach->store($file, [
                'target_type' => 'application',
                'target_id'   => $applicationId,
                'user_id'     => $userId,
                'field_key'   => $qid,
                'accept'      => $q['accept'] ?? [],
                'max_size'    => $q['max_size'] ?? 10485760,
            ]);

            if (!$result['ok']) {
                $errors[$qid] = $result['error'] ?? 'Ошибка файла';
                continue;
            }

            $answers[$qid] = $result['meta'];
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        return ['ok' => true, 'answers' => $answers];
    }

    /**
     * $_FILES['answers'] → [question_id => fileRow]
     */
    private function normalizeFilesBag(array $files): array
    {
        if ($files === []) {
            return [];
        }

        // PHP: name[q_id], type[q_id], tmp_name[q_id], error[q_id], size[q_id]
        if (isset($files['name']) && is_array($files['name'])) {
            $out = [];
            foreach ($files['name'] as $qid => $name) {
                if (is_array($name)) {
                    $name = (string)(reset($name) ?: '');
                    $type = $files['type'][$qid] ?? '';
                    $tmp  = $files['tmp_name'][$qid] ?? '';
                    $err  = $files['error'][$qid] ?? UPLOAD_ERR_NO_FILE;
                    $size = $files['size'][$qid] ?? 0;
                    $out[$qid] = [
                        'name'     => $name,
                        'type'     => is_array($type) ? (string)(reset($type) ?: '') : (string)$type,
                        'tmp_name' => is_array($tmp) ? (string)(reset($tmp) ?: '') : (string)$tmp,
                        'error'    => is_array($err) ? (int)reset($err) : (int)$err,
                        'size'     => is_array($size) ? (int)reset($size) : (int)$size,
                    ];
                } else {
                    $out[$qid] = [
                        'name'     => (string)$name,
                        'type'     => (string)($files['type'][$qid] ?? ''),
                        'tmp_name' => (string)($files['tmp_name'][$qid] ?? ''),
                        'error'    => (int)($files['error'][$qid] ?? UPLOAD_ERR_NO_FILE),
                        'size'     => (int)($files['size'][$qid] ?? 0),
                    ];
                }
            }
            return $out;
        }

        $first = reset($files);
        if (is_array($first) && array_key_exists('tmp_name', $first)) {
            return $files;
        }

        return [];
    }

}
