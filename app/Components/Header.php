<?php
namespace App\Components;

use App\Services\AuthService;
use App\Models\Role;

class Header
{
    public function render(): string
    {
        $user = AuthService::user();
        $isAdminBtnVisible = false; 
        if ($user != null) {
            $nickname = $user['username'];
            $roleModel = new Role();
            $role = $roleModel->label($user['role_name']);
            if (AuthService::can('resolve_report') || 
                AuthService::can('applications.view') ||
                AuthService::can('manage_roles') ||
                AuthService::hasRole('admin')) {
                $isAdminBtnVisible = true;
            }
        }
        $loggedIn = isset($user);

        // var_dump($isAdminBtnVisible);

        ob_start();
        ?>
        <link rel="stylesheet" href="<?= asset('styles/header.css'); ?>">
        <header>
            <div class="container">
                <div class="header">
                    <a href="<?= url('/') ?>" class="logo">
                        <svg width="30" height="30" viewBox="0 0 26 26" fill="currentColor"
                             xmlns="http://www.w3.org/2000/svg">
                            <path d="M25.0956 8.23612C26.6282 12.138 26.2493 16.708 23.68 20.4108C21.1107 24.1137 16.9622 26.0683 12.7707 25.9982L25.0956 8.23612Z"/>
                            <path d="M15.8284 0.309587C17.426 0.665061 18.983 1.32942 20.4108 2.32014C21.8384 3.31078 23.0054 4.5367 23.8975 5.90877L10.1714 25.6904C8.57391 25.3349 7.01698 24.6708 5.58931 23.6801C4.16151 22.6894 2.99446 21.4632 2.10229 20.0909L15.8284 0.309587Z"/>
                            <path d="M2.32007 5.58943C4.88929 1.88673 9.03749 -0.0681167 13.2288 0.00181279L0.904213 17.7639C-0.628283 13.8621 -0.249101 9.29214 2.32007 5.58943Z"/>
                        </svg>
                        <div class="header-title">
                            <h2>SWEETLOLLY</h2>
                            <h3 id="textBlock">Форум</h3>
                        </div>
                    </a>

                    <div class="user-theme">
                        <div class="theme-toggle" data-theme-toggle></div>

                        <?php if ($isAdminBtnVisible): ?>
                            <a href="<?= url('/moderation/') ?>">
                                <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24" >
                                    <path d="M5 3a2 2 0 1 0 0 4 2 2 0 1 0 0-4m7 0a2 2 0 1 0 0 4 2 2 0 1 0 0-4m7 0a2 2 0 1 0 0 4 2 2 0 1 0 0-4M5 10a2 2 0 1 0 0 4 2 2 0 1 0 0-4m7 0a2 2 0 1 0 0 4 2 2 0 1 0 0-4m7 0a2 2 0 1 0 0 4 2 2 0 1 0 0-4M5 17a2 2 0 1 0 0 4 2 2 0 1 0 0-4m7 0a2 2 0 1 0 0 4 2 2 0 1 0 0-4m7.33 0a2 2 0 1 0 0 4 2 2 0 1 0 0-4"></path>
                                </svg>
                            </a>
                        <?php endif; ?>

                        <div class="header-separator"></div>
                        
                        <?php if ($loggedIn): ?>
                            <!-- ?= Notify::make() ?> -->
                            
                            <div class="user user-logged">
                                <a class="user-logg-a"
                                   href="<?= url('/profile/'.htmlspecialchars($nickname)) ?>">
                                    <img src="https://nmsr.nickac.dev/bust/<?= htmlspecialchars($nickname) ?>"
                                         alt="avatar">
                                    <div>
                                        <h6><?= htmlspecialchars($nickname !== '' ? $nickname : 'Игрок') ?></h6>
                                        <p><?= htmlspecialchars($role !== '' ? $role : 'Игрок') ?></p>
                                    </div>
                                </a>
                                <a href="<?= url('logout/') ?>" class="logout" title="Выйти">
                                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 24 24"
                                         transform="scale(-1,1)">
                                        <path d="M15 11H8v2h7v4l6-5-6-5z"/>
                                        <path d="M5 21h7v-2H5V5h7V3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2"/>
                                    </svg>
                                </a>
                            </div>
                        <?php else: ?>
                            <a class="user" href="<?= url('login') ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor"
                                     viewBox="0 0 24 24">
                                    <path d="M12 2a5 5 0 1 0 0 10 5 5 0 1 0 0-10M4 22h16c.55 0 1-.45 1-1v-1c0-3.86-3.14-7-7-7h-4c-3.86 0-7 3.14-7 7v1c0 .55.45 1 1 1"/>
                                </svg>
                                <p>Войти</p>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>
        <script src="<?= asset('scripts/theme.js') ?>"></script>
        <?php
        return ob_get_clean();
    }

    public function __toString(): string
    {
        return $this->render();
    }

    public static function make(...$args): self
    {
        return new self(...$args);
    }
}