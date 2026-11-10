<?php
namespace App\Controllers;

use Core\Controller;
use App\Services\AuthService;
use App\Services\ApplicationService;

class ApplicationController extends Controller
{
    private ApplicationService $service;

    public function __construct()
    {
        $this->service = new ApplicationService();
    }

    public function create(array $params = [])
    {
        $this->guard();

        $parentId = isset($_GET['parent']) ? (int)$_GET['parent'] : null;

        $data = [
            'mode'     => 'create',
            'parentId' => $parentId,
        ];

        $this->title = 'Создать заявку | Модерация';
        return $this->render('moderation/application_form', $data);
    }

    private function guard(): void
    {
        if (!AuthService::check() || !AuthService::can('manage_categories')) {
            header('Location: ' . url('/'));
            exit;
        }
    }
}