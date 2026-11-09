<?php
namespace App\Services;

use App\Models\ActivityLog;

/**
 * Сервис аудита действий.
 *
 * Контроллеры и другие сервисы вызывают только его.
 * Модель ActivityLog используется только для доступа к БД.
 */
class ActivityLogService
{
    /**
     * Универсальная запись события.
     *
     * @param int|null    $userId      Кто совершил (null = система)
     * @param string      $action      Код: created_thread, locked_thread, banned_user…
     * @param string      $result      success|denied|error
     * @param string|null $targetType  thread|post|user|report|category…
     * @param int|null    $targetId
     * @param string|null $objectTitle Человекочитаемое название
     * @param array|null  $data        before/after и любые доп. поля
     * @param string|null $comment
     * @return int|null ID записи
     */
    public static function log(
        ?int $userId,
        string $action,
        string $result = 'success',
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $objectTitle = null,
        ?array $data = null,
        ?string $comment = null
    ): ?int {
        $ip = self::clientIp();
        $ua = self::userAgent();

        $model = new ActivityLog();
        return $model->insert(
            $userId,
            $action,
            $result,
            $targetType,
            $targetId,
            $objectTitle,
            $ip,
            $ua,
            $data,
            $comment
        );
    }

    // -------------------------------------------------------------------------
    // Удобные обёртки под типичные действия форума
    // -------------------------------------------------------------------------

    public static function threadCreated(int $userId, int $threadId, string $title, ?string $slug = null): ?int
    {
        return self::log(
            $userId,
            'created_thread',
            'success',
            'thread',
            $threadId,
            $title,
            array_filter(['title' => $title, 'slug' => $slug])
        );
    }

    public static function threadLocked(int $adminId, int $threadId, string $title, ?string $comment = null): ?int
    {
        return self::log(
            $adminId,
            'locked_thread',
            'success',
            'thread',
            $threadId,
            $title,
            [
                'before' => ['is_locked' => 0],
                'after'  => ['is_locked' => 1],
            ],
            $comment
        );
    }

    public static function threadUnlocked(int $adminId, int $threadId, string $title, ?string $comment = null): ?int
    {
        return self::log(
            $adminId,
            'unlocked_thread',
            'success',
            'thread',
            $threadId,
            $title,
            [
                'before' => ['is_locked' => 1],
                'after'  => ['is_locked' => 0],
            ],
            $comment
        );
    }

    public static function postCreated(int $userId, int $postId, int $threadId, ?string $title = null): ?int
    {
        return self::log(
            $userId,
            'created_post',
            'success',
            'post',
            $postId,
            $title ?? ('Пост #' . $postId),
            ['thread_id' => $threadId]
        );
    }

    public static function postEdited(int $userId, int $postId, int $threadId, ?string $comment = null): ?int
    {
        return self::log(
            $userId,
            'edited_post',
            'success',
            'post',
            $postId,
            'Пост #' . $postId,
            ['thread_id' => $threadId],
            $comment
        );
    }

    public static function postDeleted(int $adminId, int $postId, int $threadId, ?string $comment = null): ?int
    {
        return self::log(
            $adminId,
            'deleted_post',
            'success',
            'post',
            $postId,
            'Пост #' . $postId,
            ['thread_id' => $threadId],
            $comment
        );
    }

    public static function userBanned(
        int $adminId,
        int $userId,
        string $username,
        string $reason,
        ?string $comment = null
    ): ?int {
        return self::log(
            $adminId,
            'banned_user',
            'success',
            'user',
            $userId,
            $username,
            [
                'reason' => $reason,
                'before' => ['is_banned' => 0],
                'after'  => ['is_banned' => 1],
            ],
            $comment
        );
    }

    public static function userUnbanned(int $adminId, int $userId, string $username, ?string $comment = null): ?int
    {
        return self::log(
            $adminId,
            'unbanned_user',
            'success',
            'user',
            $userId,
            $username,
            [
                'before' => ['is_banned' => 1],
                'after'  => ['is_banned' => 0],
            ],
            $comment
        );
    }

    public static function reportCreated(
        int $reporterId,
        int $reportId,
        string $targetType,
        int $targetId,
        string $reason
    ): ?int {
        return self::log(
            $reporterId,
            'created_report',
            'success',
            'report',
            $reportId,
            'Репорт #' . $reportId,
            [
                'reason'      => $reason,
                'target_type' => $targetType,
                'target_id'   => $targetId,
            ]
        );
    }

    public static function reportOpened(int $adminId, int $reportId, ?string $comment = null): ?int
    {
        return self::log(
            $adminId,
            'opened_report',
            'success',
            'report',
            $reportId,
            'Репорт #' . $reportId,
            [
                'before' => ['status' => 'pending'],
                'after'  => ['status' => 'in_progress'],
            ],
            $comment
        );
    }

    public static function reportResolved(int $adminId, int $reportId, ?string $comment = null): ?int
    {
        return self::log(
            $adminId,
            'resolved_report',
            'success',
            'report',
            $reportId,
            'Репорт #' . $reportId,
            [
                'before' => ['status' => 'in_progress'],
                'after'  => ['status' => 'resolved'],
            ],
            $comment
        );
    }

    public static function reportRejected(int $adminId, int $reportId, ?string $comment = null): ?int
    {
        return self::log(
            $adminId,
            'rejected_report',
            'success',
            'report',
            $reportId,
            'Репорт #' . $reportId,
            [
                'before' => ['status' => 'in_progress'],
                'after'  => ['status' => 'rejected'],
            ],
            $comment
        );
    }

    /**
     * Отказ в действии (нет прав / rate limit и т.п.)
     */
    public static function denied(
        ?int $userId,
        string $action,
        ?string $targetType = null,
        ?int $targetId = null,
        ?string $objectTitle = null,
        ?string $comment = null
    ): ?int {
        return self::log(
            $userId,
            $action,
            'denied',
            $targetType,
            $targetId,
            $objectTitle,
            null,
            $comment
        );
    }

    // -------------------------------------------------------------------------
    // Вспомогательные
    // -------------------------------------------------------------------------

    private static function clientIp(): ?string
    {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        if ($ip && str_contains($ip, ',')) {
            $ip = trim(explode(',', $ip)[0]);
        }
        return $ip ? mb_substr($ip, 0, 45) : null;
    }

    private static function userAgent(): ?string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        return $ua ? mb_substr($ua, 0, 255) : null;
    }
}