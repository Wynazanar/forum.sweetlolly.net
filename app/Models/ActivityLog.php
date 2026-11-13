<?php
namespace App\Models;

use Core\Model;

/**
 * Модель журнала активности.
 * Только доступ к БД. Запись событий — через ActivityLogService.
 */
class ActivityLog extends Model
{
    /**
     * Вставка записи. Вызывается только из ActivityLogService.
     *
     * @return int|null ID созданной записи
     */
    public function insert(
        ?int $userId,
        string $action,
        string $result = 'success',
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $objectTitle = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?array $data = null,
        ?string $comment = null
    ): ?int {
        $json = $data !== null ? json_encode($data, JSON_UNESCAPED_UNICODE) : null;

        $sql = "INSERT INTO activity_logs
                    (user_id, action, result, target_type, target_id, object_title, ip, user_agent, data, comment)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $userId !== null ? (string)$userId : null,
            $action,
            $result,
            $targetType,
            $targetId !== null ? (string)$targetId : null,
            $objectTitle,
            $ip,
            $userAgent,
            $json,
            $comment,
        ];

        try {
            $stmt = $this->executeInsert($sql, $params);
            return $stmt ? (int) self::$link->insert_id : null;
        } catch (\Throwable $e) {
            error_log('ActivityLog::insert failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Базовый SELECT с пользователем и ролью.
     */
    private function selectWithUser(): string
    {
        return "SELECT
                al.*,
                u.username AS actor_name,
                u.username AS admin_name,
                r.name AS role_name,
                r.id AS role_id
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             LEFT JOIN roles r ON r.id = u.role_id";
    }

    /**
     * Список логов с пагинацией и фильтрами.
     *
     * @param int   $page
     * @param int   $perPage
     * @param array $filters  q, date (Y-m-d), admin_id, action, result, role
     * @return array{items: array, total: int, page: int, pages: int}
     */
    public function getList(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $page    = max(1, $page);
        $perPage = max(1, $perPage);
        $offset  = ($page - 1) * $perPage;

        $where  = ['1=1'];
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . $filters['q'] . '%';
            $where[] = '(al.action LIKE ?
                        OR al.object_title LIKE ?
                        OR al.target_type LIKE ?
                        OR u.username LIKE ?
                        OR CAST(al.id AS CHAR) = ?)';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $filters['q'];
        }

        if (!empty($filters['date'])) {
            $where[]  = 'DATE(al.created_at) = ?';
            $params[] = $filters['date'];
        }

        if (!empty($filters['admin_id'])) {
            $where[]  = 'al.user_id = ?';
            $params[] = (string)(int)$filters['admin_id'];
        }

        if (!empty($filters['action'])) {
            $where[]  = 'al.action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['result'])) {
            $where[]  = 'al.result = ?';
            $params[] = $filters['result'];
        }

        if (!empty($filters['role'])) {
            $where[]  = 'r.name = ?';
            $params[] = $filters['role'];
        }

        $whereSql = implode(' AND ', $where);

        $totalRow = $this->findOne(
            "SELECT COUNT(*) AS cnt
             FROM activity_logs al
             LEFT JOIN users u ON u.id = al.user_id
             LEFT JOIN roles r ON r.id = u.role_id
             WHERE {$whereSql}",
            $params
        );
        $total = (int)($totalRow['cnt'] ?? 0);
        $pages = max(1, (int)ceil($total / $perPage));

        if ($page > $pages) {
            $page   = $pages;
            $offset = ($page - 1) * $perPage;
        }

        $items = $this->findMany(
            $this->selectWithUser() . "
             WHERE {$whereSql}
             ORDER BY al.created_at DESC, al.id DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [(string)$perPage, (string)$offset])
        );

        foreach ($items as &$item) {
            $item['action_label'] = $this->actionLabel($item['action'] ?? '');
            $item['role_label']   = $this->roleLabel($item['role_name'] ?? null);
        }
        unset($item);

        return [
            'items' => $items ?: [],
            'total' => $total,
            'page'  => $page,
            'pages' => $pages,
        ];
    }

    /**
     * Один лог по ID (для правой панели)
     */
    public function findById(int $id): ?array
    {
        $row = $this->findOne(
            $this->selectWithUser() . "
             WHERE al.id = ?
             LIMIT 1",
            [(string)$id]
        );

        if ($row) {
            $row['action_label'] = $this->actionLabel($row['action'] ?? '');
            $row['role_label']   = $this->roleLabel($row['role_name'] ?? null);
            $row['object_url']   = $this->buildObjectUrl($row);
        }

        return $row;
    }

    /**
     * Вся история изменений одного объекта (target_type + target_id).
     *
     * @return array<int, array>
     */
    public function getHistoryForTarget(?string $targetType, ?int $targetId): array
    {
        if ($targetType === null || $targetType === '' || $targetId === null || $targetId <= 0) {
            return [];
        }

        $rows = $this->findMany(
            $this->selectWithUser() . "
             WHERE al.target_type = ? AND al.target_id = ?
             ORDER BY al.created_at ASC, al.id ASC",
            [$targetType, (string)$targetId]
        );

        foreach ($rows as &$row) {
            $row['action_label'] = $this->actionLabel($row['action'] ?? '');
            $row['role_label']   = $this->roleLabel($row['role_name'] ?? null);
            $row['object_url']   = $this->buildObjectUrl($row);
        }
        unset($row);

        return $rows ?: [];
    }

    /**
     * Участники, которые есть в логах — для селекта фильтра
     * @return array<int, array{id: int, name: string, role_name: string|null, role_label: string}>
     */
    public function getActorOptions(): array
    {
        $rows = $this->findMany(
            "SELECT DISTINCT u.id, u.username AS name, r.name AS role_name
             FROM activity_logs al
             INNER JOIN users u ON u.id = al.user_id
             LEFT JOIN roles r ON r.id = u.role_id
             ORDER BY u.username"
        );

        if (!$rows) {
            return [];
        }

        foreach ($rows as &$row) {
            $row['role_label'] = $this->roleLabel($row['role_name'] ?? null);
        }
        unset($row);

        return $rows;
    }

    /** @deprecated use getActorOptions() */
    public function getAdminOptions(): array
    {
        return $this->getActorOptions();
    }

    /**
     * Роли, которые встречаются в логах
     * @return array<string, string> role_name => label
     */
    public function getRoleOptions(): array
    {
        $rows = $this->findMany(
            "SELECT DISTINCT r.name
             FROM activity_logs al
             INNER JOIN users u ON u.id = al.user_id
             INNER JOIN roles r ON r.id = u.role_id
             WHERE r.name IS NOT NULL AND r.name != ''
             ORDER BY r.name"
        );

        if (!$rows) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            $name = $row['name'];
            $options[$name] = $this->roleLabel($name);
        }

        return $options;
    }

    /**
     * Типы действий из логов
     * @return array<string, string>
     */
    public function getActionOptions(): array
    {
        $rows = $this->findMany(
            "SELECT DISTINCT action
             FROM activity_logs
             WHERE action IS NOT NULL AND action != ''
             ORDER BY action"
        );

        if (!$rows) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            $action = $row['action'];
            $options[$action] = $this->actionLabel($action);
        }

        return $options;
    }

    public function actionLabel(string $action): string
    {
        $map = [
            'created_thread'   => 'Создание темы',
            'locked_thread'    => 'Закрытие темы',
            'unlocked_thread'  => 'Открытие темы',
            'lock_thread'      => 'Закрытие темы',
            'unlock_thread'    => 'Открытие темы',
            'pinned_thread'    => 'Закрепление темы',
            'unpinned_thread'  => 'Открепление темы',
            'deleted_thread'   => 'Удаление темы',
            'moved_thread'     => 'Перемещение темы',
            'edited_thread'    => 'Редактирование темы',

            'created_post'     => 'Создание поста',
            'edited_post'      => 'Редактирование поста',
            'edit_post'        => 'Редактирование поста',
            'deleted_post'     => 'Удаление поста',
            'delete_post'      => 'Удаление поста',

            'banned_user'      => 'Блокировка пользователя',
            'ban_user'         => 'Блокировка пользователя',
            'unbanned_user'    => 'Разблокировка пользователя',
            'unban_user'       => 'Разблокировка пользователя',
            'changed_role'     => 'Смена роли',
            'warned_user'      => 'Предупреждение',

            'created_report'   => 'Создание репорта',
            'opened_report'    => 'Открытие репорта',
            'resolved_report'  => 'Закрытие репорта',
            'resolve_report'   => 'Рассмотрение жалобы',
            'rejected_report'  => 'Отклонение репорта',
            'assigned_report'  => 'Назначение репорта',
            'commented_report' => 'Комментарий к репорту',

            'manage_category'  => 'Управление разделом',
            'created_category' => 'Создание раздела',
            'created_section'   => 'Создание раздела',
            'updated_section'   => 'Редактирование раздела',
            'deleted_section'   => 'Удаление раздела',
            'edited_category'  => 'Редактирование раздела',
            'deleted_category' => 'Удаление раздела',

            'created_application_form'   => 'Создание шаблона заявки',
            'updated_application_form'   => 'Изменение шаблона заявки',
            'deleted_application_form'   => 'Удаление шаблона заявки',
            'archived_application_form'  => 'Архивация шаблона заявки',
            'submitted_application'      => 'Подача заявки',
            'accepted_application'       => 'Принятие заявки',
            'rejected_application'       => 'Отклонение заявки',
            'created_application_layout' => 'Создание макета заявки',
            'updated_application_layout' => 'Изменение макета заявки',
            'deleted_application_layout' => 'Удаление макета заявки',
            'created_role'               => 'Создание роли',
            'updated_role'               => 'Изменение роли',
            'deleted_role'               => 'Удаление роли',
            'requested_application_info'  => 'Запрос уточнения по заявке',
            'replied_application'         => 'Ответ по заявке',
            'created_player'              => 'Создание игрока',
            'updated_player'              => 'Изменение игрока',
            'deactivated_player'          => 'Деактивация игрока',
            'activated_player'            => 'Активация игрока',
            'muted_user'                  => 'Мут игрока',
            'unmuted_user'                => 'Снятие мута',
            'restricted_user'             => 'Ограничение игрока',
            'unrestricted_user'           => 'Снятие ограничения',
        ];

        return $map[$action] ?? $action;
    }

    /**
     * Человекочитаемая роль.
     */
    public function roleLabel(?string $roleName): string
    {
        if ($roleName === null || $roleName === '') {
            return 'Система';
        }

        $map = [
            'user'      => 'Игрок',
            'moderator' => 'Модератор',
            'admin'     => 'Администратор',
        ];

        return $map[$roleName] ?? $roleName;
    }

    public function buildObjectUrl(array $log): ?string
    {
        $type = $log['target_type'] ?? null;
        $id   = isset($log['target_id']) ? (int)$log['target_id'] : 0;

        if (!$type || $id <= 0) {
            return null;
        }

        $data = [];
        if (!empty($log['data'])) {
            $decoded = is_string($log['data']) ? json_decode($log['data'], true) : $log['data'];
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        switch ($type) {
            case 'thread':
                $slug = $data['slug'] ?? null;
                if ($slug) {
                    return url('/forums/' . rawurlencode($slug));
                }
                return url('/forums/' . $id);

            case 'post':
                $threadId = $data['thread_id'] ?? $data['thread'] ?? null;
                if ($threadId) {
                    return url('/forums/' . (int)$threadId . '#post-' . $id);
                }
                return null;

            case 'user':
                return url('/user/' . $id);

            case 'report':
            case 'complaint':
                return url('/moderation/reports?id=' . $id);

            case 'category':
                return url('/moderation/sections?id=' . $id);

            default:
                return null;
        }
    }

    private function executeInsert(string $sql, array $params): ?\mysqli_stmt
    {
        $stmt = self::$link->prepare($sql);
        if (!$stmt) {
            throw new \RuntimeException(mysqli_error(self::$link));
        }

        $refs = [];
        $types = '';
        foreach ($params as $i => $value) {
            $types .= 's';
            $refs[$i] = $value === null ? null : (string)$value;
        }

        $bind = [$types];
        foreach ($refs as $k => $v) {
            $bind[] = &$refs[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new \RuntimeException($err);
        }

        return $stmt;
    }
}