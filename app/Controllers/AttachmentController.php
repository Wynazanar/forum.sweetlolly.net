<?php
namespace App\Controllers;

use Core\Controller;
use App\Services\AuthService;
use App\Services\AttachmentService;
use App\Services\ApplicationService;
use App\Models\Post;
use Core\Model;

class AttachmentController extends Controller
{
    public function download(array $params = [])
    {
        $id = (int)($params['id'] ?? 0);
        $service = new AttachmentService();
        $row = $service->find($id);

        if (!$row) {
            http_response_code(404);
            echo 'Not found';
            exit;
        }

        if (!$this->canDownload($row)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }

        $abs = $service->absolutePath($row);
        if (!is_file($abs)) {
            http_response_code(404);
            echo 'File missing';
            exit;
        }

        $name = $row['original_name'] ?? 'file';
        $mime = $row['mime_type'] ?? 'application/octet-stream';
        $isImage = str_starts_with($mime, 'image/');

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string)filesize($abs));
        // картинки — inline (для <img>), остальное — attachment
        $disp = $isImage ? 'inline' : 'attachment';
        header('Content-Disposition: ' . $disp . '; filename="' . str_replace('"', '', $name) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($abs);
        exit;
    }

    /**
     * AJAX upload для редактора
     */
    public function upload(array $params = [])
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!AuthService::check()) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Нужен вход']);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'POST only']);
            exit;
        }

        $file = $_FILES['file'] ?? null;
        if (!$file) {
            echo json_encode(['ok' => false, 'error' => 'Файл не передан']);
            exit;
        }

        $user = AuthService::user();
        $userId = (int)($user['id'] ?? 0);
        $context = preg_replace('~[^a-z_]+~', '', strtolower((string)($_POST['context'] ?? 'editor'))) ?: 'editor';

        $service = new AttachmentService();
        $result = $service->store($file, [
            'target_type' => $context,
            'target_id'   => $userId,
            'user_id'     => $userId,
            'field_key'   => $_POST['field_key'] ?? null,
            'accept'      => ['png', 'jpg', 'jpeg', 'gif', 'webp'],
            'max_size'    => 8 * 1024 * 1024,
        ]);

        if (!$result['ok']) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => $result['error'] ?? 'Ошибка загрузки']);
            exit;
        }

        $meta = $result['meta'] ?? [];
        $path = $meta['path'] ?? '';
        $url = $path;
        if ($path !== '' && function_exists('url')) {
            $url = url(ltrim($path, '/'));
        }

        echo json_encode([
            'ok'            => true,
            'attachment_id' => (int)($result['id'] ?? $meta['attachment_id'] ?? 0),
            'path'          => $path,
            'url'           => $url,
            'name'          => $meta['name'] ?? '',
            'size'          => $meta['size'] ?? 0,
            'mime'          => $meta['mime'] ?? '',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function canDownload(array $row): bool
    {
        $uid = AuthService::check() ? (int)(AuthService::user()['id'] ?? 0) : 0;

        // владелец
        if ($uid > 0 && (int)($row['user_id'] ?? 0) === $uid) {
            return true;
        }

        // staff
        if (AuthService::check() && (
            AuthService::hasRole('admin')
            || AuthService::can('applications.view')
            || AuthService::can('resolve_report')
            || AuthService::can('lock_thread')
        )) {
            return true;
        }

        // заявка — автор заявки
        if (($row['target_type'] ?? '') === 'application') {
            if (!AuthService::check()) {
                return false;
            }
            $app = (new ApplicationService())->find((int)$row['target_id']);
            if ($app && (int)($app['user_id'] ?? 0) === $uid) {
                return true;
            }
            return false;
        }

        // Картинки/файлы в опубликованных темах форума — можно всем (в т.ч. гостям)
        if ($this->isPublicForumAttachment($row)) {
            return true;
        }

        return false;
    }

    /**
     * Вложение видно, если оно в content_blocks опубликованного поста
     * или привязано к published post/thread.
     */
    private function isPublicForumAttachment(array $row): bool
    {
        $id = (int)($row['id'] ?? 0);
        if ($id <= 0) {
            return false;
        }

        $type = $row['target_type'] ?? '';
        $targetId = (int)($row['target_id'] ?? 0);

        // Прямая привязка к посту
        if ($type === 'post' && $targetId > 0) {
            return $this->postIsPubliclyVisible($targetId);
        }

        // Привязка к теме
        if ($type === 'thread' && $targetId > 0) {
            return $this->threadIsPubliclyVisible($targetId);
        }

        // editor / user temp — ищем id в content_blocks опубликованных постов
        // LIKE по JSON; для небольших форумов ок
        $model = new class extends Model {
            public function probe(int $attachmentId): bool
            {
                // JSON может хранить "attachment_id": 12 или "attachment_id":12
                $like1 = '%"attachment_id":' . $attachmentId . '%';
                $like2 = '%"attachment_id": ' . $attachmentId . '%';

                $row = $this->findOne(
                    "SELECT p.id
                     FROM posts p
                     JOIN threads t ON t.id = p.thread_id
                     WHERE (p.content_blocks LIKE ? OR p.content_blocks LIKE ?)
                       AND COALESCE(p.status, 'published') = 'published'
                       AND COALESCE(t.status, 'published') = 'published'
                       AND COALESCE(t.is_hidden, 0) = 0
                     LIMIT 1",
                    [$like1, $like2]
                );
                return $row !== null;
            }
        };

        return $model->probe($id);
    }

    private function postIsPubliclyVisible(int $postId): bool
    {
        $model = new class extends Model {
            public function check(int $postId): bool
            {
                $row = $this->findOne(
                    "SELECT p.id
                     FROM posts p
                     JOIN threads t ON t.id = p.thread_id
                     WHERE p.id = ?
                       AND COALESCE(p.status, 'published') = 'published'
                       AND COALESCE(t.status, 'published') = 'published'
                       AND COALESCE(t.is_hidden, 0) = 0
                     LIMIT 1",
                    [$postId]
                );
                return $row !== null;
            }
        };
        return $model->check($postId);
    }

    private function threadIsPubliclyVisible(int $threadId): bool
    {
        $model = new class extends Model {
            public function check(int $threadId): bool
            {
                $row = $this->findOne(
                    "SELECT id FROM threads
                     WHERE id = ?
                       AND COALESCE(status, 'published') = 'published'
                       AND COALESCE(is_hidden, 0) = 0
                     LIMIT 1",
                    [$threadId]
                );
                return $row !== null;
            }
        };
        return $model->check($threadId);
    }
}
