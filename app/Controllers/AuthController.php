<?php
namespace App\Controllers;

use Core\Controller;
use App\Services\AuthService;

class AuthController extends Controller
{
    public function loginForm(array $params = [])
    {
        if (AuthService::check()) {
            header('Location: ' . url('/'));
            exit;
        }

        $this->title = 'Вход | SweetLolly Форум';
        return $this->render('auth/login', [
            'error' => null,
            'login' => '',
        ]);
    }

    public function login(array $params = [])
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('/login'));
            exit;
        }

        if (AuthService::check()) {
            header('Location: ' . url('/'));
            exit;
        }

        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        $result = AuthService::attempt($login, $password);

        if (!$result['ok']) {
            $this->title = 'Вход | SweetLolly Форум';
            return $this->render('auth/login', [
                'error' => $result['error'] ?? 'Ошибка входа',
                'login' => $login,
            ]);
        }

        $redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? '/';
        if (!is_string($redirect) || $redirect === '' || $redirect[0] !== '/') {
            $redirect = '/';
        }

        header('Location: ' . url($redirect));
        exit;
    }

    public function logout(array $params = [])
    {
        AuthService::logout();
        header('Location: ' . url('/'));
        exit;
    }
}
