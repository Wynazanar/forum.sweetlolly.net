<?php
namespace App\Controllers;

use Core\Controller;
use App\Models\Log;

class ModerationController extends Controller
{
    public function index(array $params = [])
    {
        $tab = $params['tab'] ?? 'overview';

        $allowedTabs = [
            'overview', 'players', 'complaints', 'reports',
            'applications', 'sections', 'topics', 'discussions', 'logs'
        ];

        if (!in_array($tab, $allowedTabs, true)) {
            $tab = 'overview';
        }

        $this->title = $tab . ' | Модерация | SweetLolly Форум';

        $data = [
            'currentTab' => $tab,
        ];

        switch ($tab) {
            // case 'players':
            //     $userModel = new User();
            //     $data['players'] = $userModel->getForModeration();
            //     break;

            // case 'reports':
            //     $reportModel = new Report();
            //     $data['reports'] = $reportModel->getPending();
            //     break;

            // case 'complaints':
            //     $complaintModel = new Complaint();
            //     $data['complaints'] = $complaintModel->getAll();
            //     break;

            case 'logs':
                $logModel = new Log();
                
                $page = max(1, (int)($_GET['page'] ?? 1));
                $list = $logModel->getList($page, 20);
                
                $selectedId = (int)($_GET['id'] ?? 0);
                $selectedLog = $selectedId > 0 ? $logModel->findById($selectedId) : null;

                $data['logs']        = $list['items'];
                $data['logsTotal']   = $list['total'];
                $data['logsPage']    = $list['page'];
                $data['logsPages']   = $list['pages'];
                $data['selectedLog'] = $selectedLog;
                break;

            // остальные табы...
        }

        return $this->render('moderation/index', $data);
    }
}