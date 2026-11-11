<?php
namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationForm;
use App\Services\AuthService;
use App\Services\ActivityLogService;

class ApplicationService
{
    private Application $model;
    private ApplicationForm $formModel;

    public function __construct()
    {
        $this->model = new Application();
        $this->formModel = new ApplicationForm();
    }

    public function findAll(array $filters = []): array
    {
        return $this->model->findAll($filters);
    }

    public function find(int $id): ?array
    {
        return $this->model->findById($id);
    }

    public function countByStatus(?int $formId = null): array
    {
        return $this->model->countByStatus($formId);
    }

    /**
     * Подача заявки пользователем
     * @return array{ok: bool, id?: int, errors?: array}
     */
    public function submit(int $formId, array $answers, int $userId): array
    {
        $form = $this->formModel->findById($formId);

        if (!$form) {
            return ['ok' => false, 'errors' => ['form' => 'Шаблон не найден']];
        }

        if ($form['status'] !== 'published' || empty($form['accepting'])) {
            return ['ok' => false, 'errors' => ['form' => 'Приём заявок закрыт']];
        }

        if (!$this->canSubmit($form, $userId)) {
            return ['ok' => false, 'errors' => ['form' => 'У вас нет прав на подачу этой заявки']];
        }

        if ($this->model->hasPending($formId, $userId)) {
            return ['ok' => false, 'errors' => ['form' => 'У вас уже есть активная заявка по этому шаблону']];
        }

        $errors = $this->validateAnswers($form['questions'] ?? [], $answers);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $snapshot = [
            'name'             => $form['name'],
            'slug'             => $form['slug'],
            'application_type' => $form['application_type'],
            'description'      => $form['description'],
            'questions'        => $form['questions'],
            'layout_id'        => $form['layout_id'],
        ];

        $id = $this->model->create([
            'form_id'       => $formId,
            'user_id'       => $userId,
            'answers'       => $answers,
            'form_snapshot' => $snapshot,
            'status'        => 'pending',
        ]);

        ActivityLogService::applicationSubmitted(
            $userId,
            $id,
            $formId,
            $form['name'] ?? ('Форма #' . $formId)
        );

        // TODO: уведомления notify_user_ids

        return ['ok' => true, 'id' => $id];
    }

    /**
     * Принятие / отклонение заявки
     * @return array{ok: bool, errors?: array}
     */
    public function review(int $id, string $status, ?string $comment = null): array
    {
        $app = $this->model->findById($id);
        if (!$app) {
            return ['ok' => false, 'errors' => ['id' => 'Заявка не найдена']];
        }

        if ($app['status'] !== 'pending') {
            return ['ok' => false, 'errors' => ['status' => 'Заявка уже рассмотрена']];
        }

        $allowed = ['accepted', 'rejected'];
        if (!in_array($status, $allowed, true)) {
            return ['ok' => false, 'errors' => ['status' => 'Недопустимый статус']];
        }

        $reviewerId = AuthService::user()['id'] ?? null;

        $this->model->updateStatus($id, [
            'status'        => $status,
            'reviewed_by'   => $reviewerId,
            'reviewed_at'   => date('Y-m-d H:i:s'),
            'admin_comment' => $comment !== null ? trim($comment) : null,
        ]);

        if ($reviewerId) {
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

    public function statusOptions(): array
    {
        return [
            'pending'   => 'На рассмотрении',
            'accepted'  => 'Принята',
            'rejected'  => 'Отклонена',
            'cancelled' => 'Отменена',
        ];
    }

    public function canSubmit(array $form, int $userId): bool
    {
        $allowed = $form['allowed_submitters'] ?? [];

        if (empty($allowed)) {
            return true;
        }

        $user = AuthService::user();
        if (!$user || (int)$user['id'] !== $userId) {
            return false;
        }

        $userRoleId = (int)($user['role_id'] ?? 0);
        return in_array($userRoleId, $allowed, true);
    }

    public function canView(array $form): bool
    {
        if (AuthService::hasRole('admin')) {
            return true;
        }

        if (AuthService::can('resolve_report')) {
            return true;
        }

        $visibility = $form['visibility'] ?? [];
        if (empty($visibility)) {
            return false;
        }

        $user = AuthService::user();
        if (!$user) {
            return false;
        }

        $userRoleId = (int)($user['role_id'] ?? 0);
        return in_array($userRoleId, $visibility, true);
    }

    private function validateAnswers(array $questions, array $answers): array
    {
        $errors = [];

        foreach ($questions as $q) {
            $qid = $q['id'] ?? null;
            if (!$qid) {
                continue;
            }

            $value = $answers[$qid] ?? null;
            $required = !empty($q['required']);

            if ($required) {
                $empty = $value === null
                    || $value === ''
                    || (is_array($value) && count($value) === 0);

                if ($empty) {
                    $errors[$qid] = 'Поле «' . ($q['label'] ?? $qid) . '» обязательно';
                    continue;
                }
            }

            $type = $q['type'] ?? 'text';
            if ($type === 'email' && $value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$qid] = 'Некорректный email';
            }
            if ($type === 'number' && $value !== null && $value !== '' && !is_numeric($value)) {
                $errors[$qid] = 'Должно быть числом';
            }
        }

        return $errors;
    }
}
