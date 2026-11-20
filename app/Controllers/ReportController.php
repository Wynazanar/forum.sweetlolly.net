<?php
namespace App\Controllers;

use Core\Controller;
use App\Services\AuthService;
use App\Services\ActivityLogService;
use App\Models\Report;
use App\Models\ReportMessage;

class ReportController extends Controller
{
    private Report $reports;
    private ReportMessage $messages;

    public function __construct()
    {
        $this->reports  = new Report();
        $this->messages = new ReportMessage();
        $this->ensureSchema();
    }

    public function resolve(array $params = []): void
    {
        $this->guard();
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            $this->back($id);
        }

        $report = $this->reports->findById($id);
        if (!$report) {
            $this->back(0);
        }

        $actorId = (int)(AuthService::user()['id'] ?? 0);
        $note = trim((string)($_POST['comment'] ?? ''));

        $this->reports->setStatus($id, 'resolved', $actorId);
        if ($note !== '') {
            $this->messages->create([
                'report_id'   => $id,
                'user_id'     => $actorId,
                'body'        => $note,
                'is_internal' => 1,
                'kind'        => 'resolve',
            ]);
        }

        ActivityLogService::log(
            $actorId,
            'resolved_report',
            'success',
            'report',
            $id,
            $report['title'] ?? ('R-' . $id),
            [
                'target_type' => $report['target_type'] ?? null,
                'target_id'   => $report['target_id'] ?? null,
                'comment'     => $note !== '' ? $note : null,
            ],
            $note !== '' ? $note : null
        );

        $this->back($id);
    }

    public function reject(array $params = []): void
    {
        $this->guard();
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            $this->back($id);
        }

        $report = $this->reports->findById($id);
        if (!$report) {
            $this->back(0);
        }

        $actorId = (int)(AuthService::user()['id'] ?? 0);
        $note = trim((string)($_POST['comment'] ?? ''));

        $this->reports->setStatus($id, 'rejected', $actorId);
        if ($note !== '') {
            $this->messages->create([
                'report_id'   => $id,
                'user_id'     => $actorId,
                'body'        => $note,
                'is_internal' => 1,
                'kind'        => 'reject',
            ]);
        }

        ActivityLogService::log(
            $actorId,
            'rejected_report',
            'success',
            'report',
            $id,
            $report['title'] ?? ('R-' . $id),
            [
                'target_type' => $report['target_type'] ?? null,
                'target_id'   => $report['target_id'] ?? null,
                'comment'     => $note !== '' ? $note : null,
            ],
            $note !== '' ? $note : null
        );

        $this->back($id);
    }

    /** Запрос уточнения у репортёра */
    public function requestInfo(array $params = []): void
    {
        $this->guard();
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            $this->back($id);
        }

        $report = $this->reports->findById($id);
        if (!$report) {
            $this->back(0);
        }

        $body = trim((string)($_POST['body'] ?? ''));
        if ($body === '') {
            header('Location: ' . url('/moderation/reports?id=' . $id . '&error=' . rawurlencode('Напишите текст уточнения')));
            exit;
        }

        $actorId = (int)(AuthService::user()['id'] ?? 0);
        $this->reports->setStatus($id, 'needs_info', null);
        $this->messages->create([
            'report_id'   => $id,
            'user_id'     => $actorId,
            'body'        => $body,
            'is_internal' => 0,
            'kind'        => 'request_info',
        ]);

        ActivityLogService::log(
            $actorId,
            'requested_report_info',
            'success',
            'report',
            $id,
            $report['title'] ?? ('R-' . $id),
            ['message' => $body],
            $body
        );

        $this->back($id);
    }

    /** Ответ / комментарий в диалоге (модератор) */
    public function reply(array $params = []): void
    {
        $this->guard();
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            $this->back($id);
        }

        $report = $this->reports->findById($id);
        if (!$report) {
            $this->back(0);
        }

        $body = trim((string)($_POST['body'] ?? ''));
        if ($body === '') {
            $this->back($id);
        }

        $actorId = (int)(AuthService::user()['id'] ?? 0);
        $internal = !empty($_POST['is_internal']);

        $this->messages->create([
            'report_id'   => $id,
            'user_id'     => $actorId,
            'body'        => $body,
            'is_internal' => $internal ? 1 : 0,
            'kind'        => 'message',
        ]);

        // Если был needs_info и пишем не внутреннее — оставляем статус, ждём ответа репортёра
        ActivityLogService::log(
            $actorId,
            'commented_report',
            'success',
            'report',
            $id,
            $report['title'] ?? ('R-' . $id),
            ['message' => $body, 'internal' => $internal],
            $body
        );

        $this->back($id);
    }

    /** Вернуть в очередь */
    public function reopen(array $params = []): void
    {
        $this->guard();
        $id = (int)($params['id'] ?? 0);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            $this->back($id);
        }

        $report = $this->reports->findById($id);
        if (!$report) {
            $this->back(0);
        }

        $actorId = (int)(AuthService::user()['id'] ?? 0);
        $this->reports->setStatus($id, 'pending', null);

        ActivityLogService::log(
            $actorId,
            'opened_report',
            'success',
            'report',
            $id,
            $report['title'] ?? ('R-' . $id),
            null
        );

        $this->back($id);
    }

    private function guard(): void
    {
        if (!AuthService::check() || !AuthService::can('resolve_report')) {
            header('Location: ' . url('/'));
            exit;
        }
    }

    private function back(int $id): void
    {
        $q = $id > 0 ? ('?id=' . $id) : '';
        header('Location: ' . url('/moderation/reports') . $q);
        exit;
    }

    /** Одноразово расширяем схему (needs_info + report_messages). */
    private function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        try {
            $pdo = \Core\Model::getPdo(); // may not exist
        } catch (\Throwable $e) {
            $pdo = null;
        }

        // Используем прямой mysqli через временный Report execute
        try {
            $r = new Report();
            // status enum
            $r->execute(
                "ALTER TABLE reports
                 MODIFY COLUMN status ENUM('pending','needs_info','resolved','rejected')
                 NOT NULL DEFAULT 'pending'"
            );
        } catch (\Throwable $e) {
            // уже применено или нет прав — ок
        }

        try {
            $r = new Report();
            $r->execute(
                "CREATE TABLE IF NOT EXISTS report_messages (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    report_id BIGINT UNSIGNED NOT NULL,
                    user_id BIGINT UNSIGNED NOT NULL,
                    body TEXT NOT NULL,
                    is_internal TINYINT(1) NOT NULL DEFAULT 0,
                    kind VARCHAR(32) NOT NULL DEFAULT 'message',
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX (report_id, created_at),
                    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (\Throwable $e) {
            // ignore
        }
    }

    // ------------------------------------------------------------------
    //  Личный кабинет: мои репорты
    // ------------------------------------------------------------------

    public function myList(array $params = [])
    {
        if (!AuthService::check()) {
            header('Location: ' . url('/login'));
            exit;
        }
        $userId = (int)(AuthService::user()['id'] ?? 0);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $list = $this->reports->findByReporter($userId, $page, 20);

        $this->title = 'Мои репорты';
        return $this->render('report/my_list', [
            'items'         => $list['items'],
            'total'         => $list['total'],
            'page'          => $list['page'],
            'pages'         => $list['pages'],
            'statusOptions' => $this->reports->getStatusOptions(),
        ]);
    }

    public function myShow(array $params = [])
    {
        if (!AuthService::check()) {
            header('Location: ' . url('/login'));
            exit;
        }
        $userId = (int)(AuthService::user()['id'] ?? 0);
        $id = (int)($params['id'] ?? 0);
        $report = $this->reports->findById($id);

        if (!$report || (int)($report['reporter_id'] ?? 0) !== $userId) {
            header('Location: ' . url('/my/reports'));
            exit;
        }

        $messages = [];
        try {
            // игроку — только не внутренние
            $messages = $this->messages->findByReport($id, false);
        } catch (\Throwable $e) {
            $messages = [];
        }

        $this->title = 'Репорт R-' . str_pad((string)$id, 3, '0', STR_PAD_LEFT);
        return $this->render('report/my_show', [
            'report'        => $report,
            'messages'      => $messages,
            'statusOptions' => $this->reports->getStatusOptions(),
            'errors'        => [],
        ]);
    }

    public function myReply(array $params = [])
    {
        if (!AuthService::check()) {
            header('Location: ' . url('/login'));
            exit;
        }
        $userId = (int)(AuthService::user()['id'] ?? 0);
        $id = (int)($params['id'] ?? 0);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $id <= 0) {
            header('Location: ' . url('/my/reports'));
            exit;
        }

        $report = $this->reports->findById($id);
        if (!$report || (int)($report['reporter_id'] ?? 0) !== $userId) {
            header('Location: ' . url('/my/reports'));
            exit;
        }

        $status = $report['status'] ?? '';
        if (!in_array($status, ['pending', 'needs_info'], true)) {
            header('Location: ' . url('/my/reports/' . $id));
            exit;
        }

        $body = trim((string)($_POST['body'] ?? ''));
        if ($body === '') {
            $messages = $this->messages->findByReport($id, false);
            $this->title = 'Репорт R-' . $id;
            return $this->render('report/my_show', [
                'report'        => $report,
                'messages'      => $messages,
                'statusOptions' => $this->reports->getStatusOptions(),
                'errors'        => ['body' => 'Напишите сообщение'],
            ]);
        }

        $this->messages->create([
            'report_id'   => $id,
            'user_id'     => $userId,
            'body'        => $body,
            'is_internal' => 0,
            'kind'        => 'message',
        ]);

        // После ответа на уточнение — снова в очередь
        if ($status === 'needs_info') {
            $this->reports->setStatus($id, 'pending', null);
        }

        ActivityLogService::log(
            $userId,
            'commented_report',
            'success',
            'report',
            $id,
            $report['title'] ?? ('R-' . $id),
            ['message' => $body, 'from' => 'reporter'],
            $body
        );

        header('Location: ' . url('/my/reports/' . $id));
        exit;
    }

}
