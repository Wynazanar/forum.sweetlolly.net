<?php
namespace App\Controllers;

use Core\Controller;
use App\Services\AuthService;
use App\Models\ActivityLog;
use App\Models\Section;
use App\Models\Report;


class ModerationController extends Controller
{
    public function index(array $params = [])
    {
        // ===== ВРЕМЕННО: переключение аккаунта для тестов =====
        AuthService::loginAs(1); // Admin
        // AuthService::loginAs(2); // Moderator
        // AuthService::loginAs(3); // Обычный пользователь
        // ======================================================

        // Не авторизован или обычный пользователь → на главную
        if (!AuthService::check()) {
            $this->deny();
        }

        if (!AuthService::can('resolve_report') && !AuthService::hasRole('admin')) {
            $this->deny();
        }

        $tab = $params['tab'] ?? 'overview';

        // Все возможные табы + нужное право
        $allTabs = [
            ['id' => 'overview',     'title' => 'Обзор',      'href' => url('/moderation/overview'),     'permission' => null],
            ['id' => 'players',      'title' => 'Игроки',     'href' => url('/moderation/players'),      'permission' => 'ban_user'],
            ['id' => 'complaints',   'title' => 'Жалобы',     'href' => url('/moderation/complaints'),   'permission' => 'resolve_report'],
            ['id' => 'reports',      'title' => 'Репорты',    'href' => url('/moderation/reports'),      'permission' => 'resolve_report'],
            ['id' => 'applications', 'title' => 'Заявки',     'href' => url('/moderation/applications'), 'permission' => 'resolve_report'],
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
            'sections' => $this->handleSections($data),
            'reports'  => $this->handleReports($data),
            'logs'     => $this->handleLogs($data),
            default    => null,
        };

        return $this->render('moderation/index', $data);
    }

    // =========================================================
    //  РАЗДЕЛЫ
    // =========================================================
    private function handleSections(array &$data): void
    {
        $sectionModel = new Section();

        $filters = [
            'q'           => trim((string)($_GET['q'] ?? '')),
            'category_id' => ($_GET['category_id'] ?? '') !== '' ? (int)$_GET['category_id'] : null,
            'access'      => trim((string)($_GET['access'] ?? '')),
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
        $data['accessOptions']   = [
            'public'  => 'Публичный',
            'closed'  => 'Закрытый',
        ];
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

    private function deny(): void
    {
        header('Location: ' . url('/'));
        exit;
    }
}