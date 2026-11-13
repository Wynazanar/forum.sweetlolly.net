<?php
namespace App\Controllers;

use Core\Controller;
use App\Services\AuthService;
use App\Services\AttachmentService;
use App\Services\ApplicationService;

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

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string)filesize($abs));
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $name) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($abs);
        exit;
    }

    private function canDownload(array $row): bool
    {
        if (!AuthService::check()) {
            return false;
        }
        $user = AuthService::user();
        $uid = (int)($user['id'] ?? 0);

        // владелец файла
        if ((int)($row['user_id'] ?? 0) === $uid) {
            return true;
        }

        // staff
        if (AuthService::hasRole('admin') || AuthService::can('applications.view') || AuthService::can('resolve_report')) {
            return true;
        }

        // автор заявки (если target application)
        if (($row['target_type'] ?? '') === 'application') {
            $app = (new ApplicationService())->find((int)$row['target_id']);
            if ($app && (int)($app['user_id'] ?? 0) === $uid) {
                return true;
            }
        }

        return false;
    }
}
