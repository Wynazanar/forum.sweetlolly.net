<?php
namespace App\Controllers;

use Core\Controller;
use App\Services\AuthService;
use App\Models\ActivityLog;

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
            [
                'id'         => 'overview',
                'title'      => 'Обзор',
                'href'       => url('/moderation/overview'),
                'permission' => null,
            ],
            [
                'id'         => 'players',
                'title'      => 'Игроки',
                'href'       => url('/moderation/players'),
                'permission' => 'ban_user',
            ],
            [
                'id'         => 'complaints',
                'title'      => 'Жалобы',
                'href'       => url('/moderation/complaints'),
                'permission' => 'resolve_report',
            ],
            [
                'id'         => 'reports',
                'title'      => 'Репорты',
                'href'       => url('/moderation/reports'),
                'permission' => 'resolve_report',
            ],
            [
                'id'         => 'applications',
                'title'      => 'Заявки',
                'href'       => url('/moderation/applications'),
                'permission' => 'resolve_report',
            ],
            [
                'id'         => 'sections',
                'title'      => 'Разделы',
                'href'       => url('/moderation/sections'),
                'permission' => 'manage_categories',
            ],
            [
                'id'         => 'topics',
                'title'      => 'Темы',
                'href'       => url('/moderation/topics'),
                'permission' => 'lock_thread',
            ],
            [
                'id'         => 'discussions',
                'title'      => 'Обсуждения',
                'href'       => url('/moderation/discussions'),
                'permission' => 'edit_any_post',
            ],
            [
                'id'         => 'logs',
                'title'      => 'Логи',
                'href'       => url('/moderation/logs'),
                'permission' => 'view_logs', // только admin
            ],
        ];

        // Оставляем только доступные табы
        $tabs = array_values(array_filter($allTabs, function ($item) {
            if (empty($item['permission'])) {
                return true;
            }
            return AuthService::can($item['permission']);
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
        switch ($tab) {
            case 'logs':
                $logModel = new ActivityLog();

                $page = max(1, (int)($_GET['page'] ?? 1));
                $list = $logModel->getList($page, 20);

                $selectedId  = (int)($_GET['id'] ?? 0);
                $selectedLog = $selectedId > 0 ? $logModel->findById($selectedId) : null;

                $data['logs']        = $list['items'];
                $data['logsTotal']   = $list['total'];
                $data['logsPage']    = $list['page'];
                $data['logsPages']   = $list['pages'];
                $data['selectedLog'] = $selectedLog;
                break;

            case 'players':
                // позже
                break;

            case 'reports':
                // позже
                break;

            // остальные табы...
        }

        return $this->render('moderation/index', $data);
    }

    private function deny(): void
    {
        header('Location: ' . url('/'));
        exit;
    }
}