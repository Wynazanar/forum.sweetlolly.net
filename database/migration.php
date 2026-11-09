<?php

/**
 * Миграция + сидер БД форума.
 * Запуск: php database/migration.php
 * или через браузер: /database/migration.php
 *
 * Создаёт все таблицы и заполняет тестовыми данными.
 */

$basePath = dirname(__DIR__);

require $basePath . '/vendor/autoload.php';

// .env
$dotenv = Dotenv\Dotenv::createImmutable($basePath);
$dotenv->safeLoad();

// Конфиг БД → константы
$db = require $basePath . '/app/Config/database.php';
if (!defined('DB_HOST')) define('DB_HOST', $db['host']);
if (!defined('DB_USER')) define('DB_USER', $db['username']);
if (!defined('DB_PASS')) define('DB_PASS', $db['password']);
if (!defined('DB_NAME')) define('DB_NAME', $db['database']);

if (!function_exists('db')) {
    require_once __DIR__ . '/app/db.php';
}

try {
    // Подключаемся без указания базы
    $server = new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    $dbName = str_replace('`', '``', DB_NAME);
    $server->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    require_once __DIR__ . '/app/db.php';
    $pdo = db();

    // =====================================================
    // СОЗДАНИЕ ТАБЛИЦ
    // =====================================================

    $sql = [];

    $sql[] = "CREATE TABLE IF NOT EXISTS roles (
        id          TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name        VARCHAR(75) NOT NULL UNIQUE,
        permissions JSON NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sql[] = "CREATE TABLE IF NOT EXISTS users (
        id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        username        VARCHAR(100) NOT NULL UNIQUE,
        email           VARCHAR(255) NOT NULL UNIQUE,
        password_hash   VARCHAR(255) NOT NULL,
        avatar          VARCHAR(255) NULL,
        role_id         TINYINT UNSIGNED NOT NULL DEFAULT 1,
        posts_count     INT UNSIGNED NOT NULL DEFAULT 0,
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_seen       TIMESTAMP NULL,
        is_banned       TINYINT(1) NOT NULL DEFAULT 0,
        ban_reason      VARCHAR(255) NULL,
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sql[] = "CREATE TABLE IF NOT EXISTS categories (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        parent_id   INT UNSIGNED NULL,
        title       VARCHAR(150) NOT NULL,
        description TEXT NULL,
        position    SMALLINT NOT NULL DEFAULT 0,
        is_locked   TINYINT(1) NOT NULL DEFAULT 0,
        FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sql[] = "CREATE TABLE IF NOT EXISTS threads (
        id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        category_id     INT UNSIGNED NOT NULL,
        user_id         BIGINT UNSIGNED NOT NULL,
        title           VARCHAR(255) NOT NULL,
        slug            VARCHAR(255) NOT NULL,
        is_sticky       TINYINT(1) NOT NULL DEFAULT 0,
        is_locked       TINYINT(1) NOT NULL DEFAULT 0,
        views           INT UNSIGNED NOT NULL DEFAULT 0,
        posts_count     INT UNSIGNED NOT NULL DEFAULT 1,
        last_post_id    BIGINT UNSIGNED NULL,
        last_post_at    TIMESTAMP NULL,
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (category_id, is_sticky, last_post_at),
        INDEX (user_id),
        INDEX (slug)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sql[] = "CREATE TABLE IF NOT EXISTS posts (
        id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        thread_id       BIGINT UNSIGNED NOT NULL,
        user_id         BIGINT UNSIGNED NOT NULL,
        reply_to_post_id BIGINT UNSIGNED NULL,
        content         MEDIUMTEXT NOT NULL,
        content_html    MEDIUMTEXT NULL,
        is_first        TINYINT(1) NOT NULL DEFAULT 0,
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP NULL,
        edited_by       BIGINT UNSIGNED NULL,
        FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (reply_to_post_id) REFERENCES posts(id) ON DELETE SET NULL,
        FOREIGN KEY (edited_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX (thread_id, created_at),
        INDEX (user_id),
        INDEX (reply_to_post_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sql[] = "CREATE TABLE IF NOT EXISTS reactions (
        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        post_id     BIGINT UNSIGNED NOT NULL,
        user_id     BIGINT UNSIGNED NOT NULL,
        type        VARCHAR(30) NOT NULL,
        created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_reaction (post_id, user_id, type),
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sql[] = "CREATE TABLE IF NOT EXISTS notifications (
        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id     BIGINT UNSIGNED NOT NULL,
        type        VARCHAR(50) NOT NULL,
        data        JSON NOT NULL,
        is_read     TINYINT(1) NOT NULL DEFAULT 0,
        created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (user_id, is_read, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sql[] = "CREATE TABLE IF NOT EXISTS activity_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         BIGINT UNSIGNED NULL,                    -- кто совершил действие
    action          VARCHAR(100) NOT NULL,                   -- например: assign_complaint, change_access
    result          ENUM('success', 'denied', 'error') NOT NULL DEFAULT 'success',
    target_type     VARCHAR(50) NULL,                        -- complaint, report, user, category...
    target_id       BIGINT UNSIGNED NULL,
    object_title    VARCHAR(255) NULL,                        -- Жалоба C-104, Команда форума
    ip              VARCHAR(45) NULL,
    user_agent      VARCHAR(255) NULL,
    data            JSON NULL,                               -- before / after / extra
    comment         TEXT NULL,                               -- Передано на проверку
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX (user_id, created_at),
    INDEX (action, created_at),
    INDEX (result, created_at),
    INDEX (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $sql[] = "CREATE TABLE IF NOT EXISTS reports (
        id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        reporter_id     BIGINT UNSIGNED NOT NULL,
        target_type     ENUM('post', 'thread', 'user') NOT NULL,
        target_id       BIGINT UNSIGNED NOT NULL,
        reason          VARCHAR(255) NOT NULL,
        description     TEXT NULL,
        status          ENUM('pending', 'resolved', 'rejected') NOT NULL DEFAULT 'pending',
        resolved_by     BIGINT UNSIGNED NULL,
        resolved_at     TIMESTAMP NULL,
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX (status, created_at),
        INDEX (target_type, target_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sql[] = "CREATE TABLE IF NOT EXISTS bans (
        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id     BIGINT UNSIGNED NOT NULL,
        banned_by   BIGINT UNSIGNED NULL,
        reason      VARCHAR(255) NOT NULL,
        expires_at  TIMESTAMP NULL,
        created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        is_active   TINYINT(1) NOT NULL DEFAULT 1,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (banned_by) REFERENCES users(id) ON DELETE SET NULL,
        INDEX (user_id, is_active),
        INDEX (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sql[] = "CREATE TABLE IF NOT EXISTS attachments (
        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        post_id     BIGINT UNSIGNED NULL,
        user_id     BIGINT UNSIGNED NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        stored_name VARCHAR(255) NOT NULL,
        path        VARCHAR(500) NOT NULL,
        size        INT UNSIGNED NOT NULL,
        mime_type   VARCHAR(100) NOT NULL,
        created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (post_id),
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sql[] = "CREATE TABLE IF NOT EXISTS thread_reads (
        user_id         BIGINT UNSIGNED NOT NULL,
        thread_id       BIGINT UNSIGNED NOT NULL,
        last_read_post_id BIGINT UNSIGNED NULL,
        last_read_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, thread_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sql[] = "CREATE TABLE IF NOT EXISTS thread_subscriptions (
        user_id     BIGINT UNSIGNED NOT NULL,
        thread_id   BIGINT UNSIGNED NOT NULL,
        created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, thread_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (thread_id) REFERENCES threads(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sql[] = "CREATE TABLE IF NOT EXISTS friendships (
        id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id     BIGINT UNSIGNED NOT NULL,
        friend_id   BIGINT UNSIGNED NOT NULL,
        status      ENUM('pending', 'accepted', 'rejected', 'blocked') NOT NULL DEFAULT 'pending',
        created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at  TIMESTAMP NULL,
        UNIQUE KEY unique_friendship (user_id, friend_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (friend_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (friend_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $sql[] = "CREATE TABLE IF NOT EXISTS private_messages (
        id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sender_id       BIGINT UNSIGNED NOT NULL,
        recipient_id    BIGINT UNSIGNED NOT NULL,
        content         MEDIUMTEXT NOT NULL,
        is_read         TINYINT(1) NOT NULL DEFAULT 0,
        created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX (recipient_id, is_read, created_at),
        INDEX (sender_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    foreach ($sql as $query) {
        $pdo->exec($query);
    }

    // =====================================================
    // СИДЫ (тестовые данные)
    // =====================================================

    // --- Роли ---
    $roleCount = (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();
    if ($roleCount === 0) {
        $roles = [
            ['user', json_encode([
                'create_thread' => true, 'create_post' => true, 'edit_own_post' => true,
                'delete_own_post' => true, 'react' => true, 'report' => true, 'send_pm' => true,
            ])],
            ['moderator', json_encode([
                'create_thread' => true, 'create_post' => true, 'edit_own_post' => true,
                'edit_any_post' => true, 'delete_own_post' => true, 'delete_any_post' => true,
                'lock_thread' => true, 'move_thread' => true, 'react' => true, 'report' => true,
                'resolve_report' => true, 'ban_user' => true, 'send_pm' => true,
            ])],
            ['admin', json_encode([
                'create_thread' => true, 'create_post' => true, 'edit_own_post' => true,
                'edit_any_post' => true, 'delete_own_post' => true, 'delete_any_post' => true,
                'lock_thread' => true, 'move_thread' => true, 'delete_thread' => true,
                'react' => true, 'report' => true, 'resolve_report' => true, 'ban_user' => true,
                'manage_categories' => true, 'manage_roles' => true, 'send_pm' => true, 'view_logs' => true,
            ])],
        ];
        $stmt = $pdo->prepare('INSERT INTO roles (name, permissions) VALUES (?, ?)');
        foreach ($roles as $r) {
            $stmt->execute($r);
        }
    }

    // --- Пользователи ---
    $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($userCount === 0) {
        $passwordHash = password_hash('password', PASSWORD_DEFAULT); // пароль у всех: password

        $users = [
            // id 1 — админ
            ['Admin',       'admin@forum.local',    $passwordHash, null, 3, 12, date('Y-m-d H:i:s', strtotime('-60 days')), date('Y-m-d H:i:s')],
            // id 2 — модератор
            ['Moderator',   'mod@forum.local',      $passwordHash, null, 2, 8,  date('Y-m-d H:i:s', strtotime('-45 days')), date('Y-m-d H:i:s', strtotime('-1 hour'))],
            // обычные пользователи
            ['Alex',        'alex@forum.local',     $passwordHash, null, 1, 15, date('Y-m-d H:i:s', strtotime('-30 days')), date('Y-m-d H:i:s', strtotime('-10 minutes'))],
            ['Maria',       'maria@forum.local',    $passwordHash, null, 1, 9,  date('Y-m-d H:i:s', strtotime('-25 days')), date('Y-m-d H:i:s', strtotime('-2 hours'))],
            ['Dmitry',      'dmitry@forum.local',   $passwordHash, null, 1, 6,  date('Y-m-d H:i:s', strtotime('-20 days')), date('Y-m-d H:i:s', strtotime('-1 day'))],
            ['Elena',       'elena@forum.local',    $passwordHash, null, 1, 4,  date('Y-m-d H:i:s', strtotime('-15 days')), date('Y-m-d H:i:s', strtotime('-3 hours'))],
            ['Ivan',        'ivan@forum.local',     $passwordHash, null, 1, 3,  date('Y-m-d H:i:s', strtotime('-10 days')), date('Y-m-d H:i:s', strtotime('-5 hours'))],
            ['Olga',        'olga@forum.local',     $passwordHash, null, 1, 2,  date('Y-m-d H:i:s', strtotime('-7 days')),  date('Y-m-d H:i:s', strtotime('-1 day'))],
            ['BannedUser',  'banned@forum.local',   $passwordHash, null, 1, 1,  date('Y-m-d H:i:s', strtotime('-5 days')),  null],
        ];

        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, avatar, role_id, posts_count, created_at, last_seen) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($users as $u) {
            $stmt->execute($u);
        }

        // Помечаем забаненного
        $pdo->exec("UPDATE users SET is_banned = 1, ban_reason = 'Спам и оскорбления' WHERE username = 'BannedUser'");
    }

    // --- Категории ---
    $catCount = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    if ($catCount === 0) {
        $categories = [
            [null, 'Новости и объявления', 'Официальные новости сервера и форума', 1, 0],
            [null, 'Общение', 'Свободное общение на любые темы', 2, 0],
            [null, 'Помощь', 'Вопросы новичков и техническая поддержка', 3, 0],
            [null, 'Творчество', 'Скриншоты, постройки, видео, истории', 4, 0],
            [null, 'Предложения', 'Идеи по улучшению сервера и форума', 5, 0],
            // подкатегории
            [2, 'Оффтоп', 'Всё что не относится к серверу', 1, 0],
            [2, 'Знакомства', 'Расскажите о себе', 2, 0],
            [3, 'Технические вопросы', 'Лаги, баги, ошибки', 1, 0],
            [3, 'Как начать играть', 'Гайды для новичков', 2, 0],
        ];

        $stmt = $pdo->prepare('INSERT INTO categories (parent_id, title, description, position, is_locked) VALUES (?, ?, ?, ?, ?)');
        foreach ($categories as $c) {
            $stmt->execute($c);
        }
    }

    // --- Темы и посты ---
    $threadCount = (int) $pdo->query('SELECT COUNT(*) FROM threads')->fetchColumn();
    if ($threadCount === 0) {
        // Темы
        $threads = [
            // category_id, user_id, title, slug, is_sticky, is_locked, views, posts_count, created_at
            [1, 1, 'Добро пожаловать на форум!', 'dobro-pozhalovat-na-forum', 1, 0, 342, 5, date('Y-m-d H:i:s', strtotime('-20 days'))],
            [1, 1, 'Правила форума и сервера', 'pravila-foruma-i-servera', 1, 1, 289, 3, date('Y-m-d H:i:s', strtotime('-19 days'))],
            [2, 3, 'Кто онлайн вечером?', 'kto-onlayn-vecherom', 0, 0, 156, 8, date('Y-m-d H:i:s', strtotime('-5 days'))],
            [2, 4, 'Любимые моды и датапаки', 'lyubimye-mody-i-datapaki', 0, 0, 98, 6, date('Y-m-d H:i:s', strtotime('-4 days'))],
            [3, 5, 'Не могу зайти на сервер — помогает кто-нибудь?', 'ne-mogu-zayti-na-server', 0, 0, 67, 4, date('Y-m-d H:i:s', strtotime('-2 days'))],
            [4, 6, 'Моя первая большая постройка', 'moya-pervaya-bolshaya-postroyka', 0, 0, 124, 7, date('Y-m-d H:i:s', strtotime('-3 days'))],
            [5, 3, 'Идея: система достижений', 'ideya-sistema-dostizheniy', 0, 0, 45, 3, date('Y-m-d H:i:s', strtotime('-1 day'))],
            [6, 7, 'Что смотрите сейчас?', 'chto-smotrite-seychas', 0, 0, 88, 5, date('Y-m-d H:i:s', strtotime('-6 hours'))],
            [8, 5, 'Ошибка при подключении (таймаут)', 'oshibka-pri-podklyuchenii-taymaut', 0, 0, 33, 2, date('Y-m-d H:i:s', strtotime('-12 hours'))],
        ];

        $stmtThread = $pdo->prepare('INSERT INTO threads (category_id, user_id, title, slug, is_sticky, is_locked, views, posts_count, created_at, last_post_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($threads as $t) {
            $stmtThread->execute([$t[0], $t[1], $t[2], $t[3], $t[4], $t[5], $t[6], $t[7], $t[8], $t[8]]);
        }

        // Посты
        $posts = [
            // thread 1
            [1, 1, "Приветствуем всех на официальном форуме!\n\nЗдесь можно общаться, задавать вопросы, предлагать идеи и делиться творчеством.\nСоблюдайте правила и будьте дружелюбны.", 1, date('Y-m-d H:i:s', strtotime('-20 days'))],
            [1, 3, "Отлично, наконец-то нормальный форум появился!", 0, date('Y-m-d H:i:s', strtotime('-19 days'))],
            [1, 4, "Поддерживаю, очень ждали.", 0, date('Y-m-d H:i:s', strtotime('-18 days'))],
            [1, 2, "Модерация будет следить за порядком. Приятного общения!", 0, date('Y-m-d H:i:s', strtotime('-17 days'))],
            [1, 6, "Зарегистрировалась, буду тут чаще появляться.", 0, date('Y-m-d H:i:s', strtotime('-10 days'))],

            // thread 2
            [2, 1, "Основные правила:\n1. Уважайте других участников\n2. Запрещён спам и флуд\n3. Запрещены оскорбления и провокации\n4. Реклама только с разрешения администрации\n\nНарушения наказываются варном или баном.", 1, date('Y-m-d H:i:s', strtotime('-19 days'))],
            [2, 3, "Всё понятно, спасибо.", 0, date('Y-m-d H:i:s', strtotime('-18 days'))],
            [2, 5, "А можно уточнить про рекламу ютуб-каналов?", 0, date('Y-m-d H:i:s', strtotime('-15 days'))],

            // thread 3
            [3, 3, "Кто будет сегодня вечером после 20:00? Хочу компанию для выживания.", 1, date('Y-m-d H:i:s', strtotime('-5 days'))],
            [3, 4, "Я буду, пиши в лс.", 0, date('Y-m-d H:i:s', strtotime('-5 days +1 hour'))],
            [3, 6, "Тоже планирую зайти часов в 21.", 0, date('Y-m-d H:i:s', strtotime('-4 days'))],
            [3, 7, "Я пас, работа.", 0, date('Y-m-d H:i:s', strtotime('-4 days +2 hours'))],
            [3, 3, "Отлично, жду в 21:00 у спавна.", 0, date('Y-m-d H:i:s', strtotime('-3 days'))],
            [3, 5, "Можно и мне присоединиться?", 0, date('Y-m-d H:i:s', strtotime('-2 days'))],
            [3, 3, "Конечно!", 0, date('Y-m-d H:i:s', strtotime('-2 days +30 minutes'))],
            [3, 4, "Вчера было круто, давайте ещё.", 0, date('Y-m-d H:i:s', strtotime('-1 day'))],

            // thread 4
            [4, 4, "Какие моды/датапаки вам больше всего нравятся на сервере? Или что бы хотели добавить?", 1, date('Y-m-d H:i:s', strtotime('-4 days'))],
            [4, 3, "Мне нравится всё что связано с экономикой и квестами.", 0, date('Y-m-d H:i:s', strtotime('-4 days +2 hours'))],
            [4, 6, "Хотела бы больше декоративных блоков.", 0, date('Y-m-d H:i:s', strtotime('-3 days'))],
            [4, 5, "Поддерживаю про декоратив.", 0, date('Y-m-d H:i:s', strtotime('-3 days +1 hour'))],
            [4, 7, "А мне бы PvP-арену нормальную.", 0, date('Y-m-d H:i:s', strtotime('-2 days'))],
            [4, 2, "Идеи записываем, спасибо.", 0, date('Y-m-d H:i:s', strtotime('-1 day'))],

            // thread 5
            [5, 5, "При попытке зайти пишет Connection timed out. Что делать?", 1, date('Y-m-d H:i:s', strtotime('-2 days'))],
            [5, 2, "Проверь, не заблокирован ли IP. Также попробуй другой клиент / версию.", 0, date('Y-m-d H:i:s', strtotime('-2 days +30 minutes'))],
            [5, 3, "У меня такая же проблема была — помог VPN.", 0, date('Y-m-d H:i:s', strtotime('-1 day'))],
            [5, 5, "Спасибо, через VPN зашёл!", 0, date('Y-m-d H:i:s', strtotime('-1 day +2 hours'))],

            // thread 6
            [6, 6, "Построила замок в стиле средневековья. Координаты примерно X: 1200 Z: -800. Буду рада фидбеку!", 1, date('Y-m-d H:i:s', strtotime('-3 days'))],
            [6, 3, "Ого, выглядит круто! Особенно башни.", 0, date('Y-m-d H:i:s', strtotime('-3 days +1 hour'))],
            [6, 4, "Очень атмосферно получилось.", 0, date('Y-m-d H:i:s', strtotime('-2 days'))],
            [6, 7, "Завидую белой завистью 🔥", 0, date('Y-m-d H:i:s', strtotime('-2 days +3 hours'))],
            [6, 1, "Красивая работа, можно в новости сервера взять?", 0, date('Y-m-d H:i:s', strtotime('-1 day'))],
            [6, 6, "Конечно, будет приятно!", 0, date('Y-m-d H:i:s', strtotime('-1 day +1 hour'))],
            [6, 5, "Скинь скрины ещё ракурсов, пожалуйста.", 0, date('Y-m-d H:i:s', strtotime('-12 hours'))],

            // thread 7
            [7, 3, "Предлагаю добавить систему достижений с наградами. Например за первый алмаз, за убийство дракона и т.д.", 1, date('Y-m-d H:i:s', strtotime('-1 day'))],
            [7, 4, "Поддерживаю, будет мотивация.", 0, date('Y-m-d H:i:s', strtotime('-20 hours'))],
            [7, 2, "Идея хорошая, передадим разработчикам.", 0, date('Y-m-d H:i:s', strtotime('-10 hours'))],

            // thread 8
            [8, 7, "Что сейчас смотрите / слушаете?", 1, date('Y-m-d H:i:s', strtotime('-6 hours'))],
            [8, 3, "Пересматриваю старые сезоны Игры престолов.", 0, date('Y-m-d H:i:s', strtotime('-5 hours'))],
            [8, 4, "Подкасты про разработку.", 0, date('Y-m-d H:i:s', strtotime('-4 hours'))],
            [8, 6, "Музыку из Minecraft OST 😌", 0, date('Y-m-d H:i:s', strtotime('-3 hours'))],
            [8, 5, "Аниме сезон новый ловлю.", 0, date('Y-m-d H:i:s', strtotime('-2 hours'))],

            // thread 9
            [9, 5, "Постоянно выбивает с таймаутом. Пинг нормальный.", 1, date('Y-m-d H:i:s', strtotime('-12 hours'))],
            [9, 2, "Напишите версию клиента и есть ли моды. Также приложите скрин ошибки.", 0, date('Y-m-d H:i:s', strtotime('-11 hours'))],
        ];

        $stmtPost = $pdo->prepare('INSERT INTO posts (thread_id, user_id, content, is_first, created_at) VALUES (?, ?, ?, ?, ?)');
        foreach ($posts as $p) {
            $stmtPost->execute($p);
        }

        // Обновляем last_post_id и last_post_at у тем
        $pdo->exec("
            UPDATE threads t
            SET last_post_id = (SELECT MAX(id) FROM posts p WHERE p.thread_id = t.id),
                last_post_at = (SELECT MAX(created_at) FROM posts p WHERE p.thread_id = t.id)
        ");
    }

    // --- Реакции ---
    $reactCount = (int) $pdo->query('SELECT COUNT(*) FROM reactions')->fetchColumn();
    if ($reactCount === 0) {
        $reactions = [
            // post_id, user_id, type
            [1, 3, 'like'], [1, 4, 'like'], [1, 6, 'love'], [1, 2, 'like'],
            [2, 1, 'like'], [2, 4, 'like'],
            [6, 3, 'like'], [6, 4, 'love'], [6, 7, 'like'], [6, 5, 'like'],
            [9, 4, 'like'], [9, 6, 'like'],
            [14, 3, 'like'], [14, 7, 'love'],
            [20, 4, 'like'], [20, 5, 'like'], [20, 1, 'love'],
            [25, 3, 'like'], [25, 6, 'like'],
            [28, 4, 'like'], [28, 2, 'like'],
            [30, 3, 'like'], [30, 5, 'like'], [30, 6, 'love'],
        ];
        $stmt = $pdo->prepare('INSERT INTO reactions (post_id, user_id, type) VALUES (?, ?, ?)');
        foreach ($reactions as $r) {
            $stmt->execute($r);
        }
    }

    // --- Уведомления ---
    $notifCount = (int) $pdo->query('SELECT COUNT(*) FROM notifications')->fetchColumn();
    if ($notifCount === 0) {
        $notifications = [
            [3, 'reply', json_encode(['thread_id' => 3, 'post_id' => 14, 'from_user_id' => 5, 'message' => 'ответил в теме «Кто онлайн вечером?»']), 0],
            [3, 'reaction', json_encode(['post_id' => 9, 'from_user_id' => 4, 'type' => 'like']), 0],
            [4, 'reply', json_encode(['thread_id' => 4, 'post_id' => 20, 'from_user_id' => 2, 'message' => 'ответил в теме «Любимые моды»']), 1],
            [6, 'reply', json_encode(['thread_id' => 6, 'post_id' => 25, 'from_user_id' => 1, 'message' => 'Админ предложил взять постройку в новости']), 0],
            [5, 'reply', json_encode(['thread_id' => 5, 'post_id' => 18, 'from_user_id' => 2, 'message' => 'Модератор ответил на ваш вопрос']), 1],
            [1, 'report', json_encode(['report_id' => 1, 'message' => 'Новая жалоба на рассмотрении']), 0],
            [7, 'friend_request', json_encode(['from_user_id' => 3, 'message' => 'Alex хочет добавить вас в друзья']), 0],
        ];
        $stmt = $pdo->prepare('INSERT INTO notifications (user_id, type, data, is_read) VALUES (?, ?, ?, ?)');
        foreach ($notifications as $n) {
            $stmt->execute($n);
        }
    }

    // --- Логи ---
    $logCount = (int) $pdo->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn();
    if ($logCount === 0) {
        // Каждый элемент: user_id, action, result, target_type, target_id, object_title, ip, ua, data, comment
        $logs = [
            // Темы
            [1, 'created_thread', 'success', 'thread', 1, 'Добро пожаловать на форум!', '127.0.0.1', 'Mozilla/5.0',
                json_encode(['title' => 'Добро пожаловать на форум!', 'slug' => 'dobro-pozhalovat'], JSON_UNESCAPED_UNICODE), null],
            [1, 'created_thread', 'success', 'thread', 2, 'Правила форума и сервера', '127.0.0.1', 'Mozilla/5.0',
                json_encode(['title' => 'Правила форума и сервера', 'slug' => 'pravila-foruma'], JSON_UNESCAPED_UNICODE), null],
            [3, 'created_thread', 'success', 'thread', 3, 'Тема пользователя #3', '192.168.1.10', 'Mozilla/5.0', null, null],
            [2, 'locked_thread', 'success', 'thread', 2, 'Правила форума и сервера', '127.0.0.1', 'Mozilla/5.0',
                json_encode(['before' => ['is_locked' => 0], 'after' => ['is_locked' => 1]], JSON_UNESCAPED_UNICODE), 'Закрыто по правилам'],
            [6, 'created_thread', 'success', 'thread', 6, 'Тема пользователя #6', '10.0.0.5', 'Mozilla/5.0', null, null],

            // Бан
            [2, 'banned_user', 'success', 'user', 9, 'Пользователь #9', '127.0.0.1', 'Mozilla/5.0',
                json_encode(['reason' => 'Спам и оскорбления', 'before' => ['is_banned' => 0], 'after' => ['is_banned' => 1]], JSON_UNESCAPED_UNICODE), null],

            // Пост
            [3, 'created_post', 'success', 'post', 14, 'Пост #14', '192.168.1.10', 'Mozilla/5.0',
                json_encode(['thread_id' => 3], JSON_UNESCAPED_UNICODE), null],

            // История одного репорта (target_type=report, target_id=1) — несколько логов
            [4, 'created_report', 'success', 'report', 1, 'Репорт #1 (пост)', '10.0.0.8', 'Mozilla/5.0',
                json_encode(['reason' => 'Флуд', 'target_type' => 'post', 'target_id' => 12], JSON_UNESCAPED_UNICODE), null],
            [2, 'opened_report', 'success', 'report', 1, 'Репорт #1 (пост)', '127.0.0.1', 'Mozilla/5.0',
                json_encode(['before' => ['status' => 'pending'], 'after' => ['status' => 'in_progress']], JSON_UNESCAPED_UNICODE), 'Взял в работу'],
            [2, 'resolved_report', 'success', 'report', 1, 'Репорт #1 (пост)', '127.0.0.1', 'Mozilla/5.0',
                json_encode(['before' => ['status' => 'in_progress'], 'after' => ['status' => 'resolved']], JSON_UNESCAPED_UNICODE), 'Пост удалён, нарушитель предупреждён'],
        ];
        $stmt = $pdo->prepare(
            'INSERT INTO activity_logs
                (user_id, action, result, target_type, target_id, object_title, ip, user_agent, data, comment)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($logs as $l) {
            $stmt->execute($l);
        }
    }

    // --- Репорты ---
    $reportCount = (int) $pdo->query('SELECT COUNT(*) FROM reports')->fetchColumn();
    if ($reportCount === 0) {
        $reports = [
            [4, 'post', 12, 'Флуд', 'Сообщение не по теме и повторяется', 'resolved', 2, date('Y-m-d H:i:s', strtotime('-1 day'))],
            [3, 'user', 9, 'Оскорбления', 'Пользователь матерился в нескольких темах', 'resolved', 2, date('Y-m-d H:i:s', strtotime('-3 days'))],
            [6, 'post', 30, 'Спам', 'Подозрительная ссылка', 'pending', null, null],
        ];
        $stmt = $pdo->prepare('INSERT INTO reports (reporter_id, target_type, target_id, reason, description, status, resolved_by, resolved_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($reports as $r) {
            $stmt->execute($r);
        }
    }

    // --- Баны ---
    $banCount = (int) $pdo->query('SELECT COUNT(*) FROM bans')->fetchColumn();
    if ($banCount === 0) {
        $pdo->prepare('INSERT INTO bans (user_id, banned_by, reason, expires_at, is_active) VALUES (?, ?, ?, ?, ?)')
            ->execute([9, 2, 'Спам и оскорбления', null, 1]); // перманентный
    }

    // --- Вложения (тестовые записи) ---
    $attachCount = (int) $pdo->query('SELECT COUNT(*) FROM attachments')->fetchColumn();
    if ($attachCount === 0) {
        $attachments = [
            [22, 6, 'castle_main.png', 'castle_main_6f3a2b.png', '/uploads/2025/11/castle_main_6f3a2b.png', 2457600, 'image/png'],
            [22, 6, 'castle_side.jpg', 'castle_side_9c1e4d.jpg', '/uploads/2025/11/castle_side_9c1e4d.jpg', 1894400, 'image/jpeg'],
            [1, 1, 'welcome_banner.png', 'welcome_banner_a1b2c3.png', '/uploads/2025/10/welcome_banner_a1b2c3.png', 512000, 'image/png'],
        ];
        $stmt = $pdo->prepare('INSERT INTO attachments (post_id, user_id, original_name, stored_name, path, size, mime_type) VALUES (?, ?, ?, ?, ?, ?, ?)');
        foreach ($attachments as $a) {
            $stmt->execute($a);
        }
    }

    // --- Прочитанные темы ---
    $readCount = (int) $pdo->query('SELECT COUNT(*) FROM thread_reads')->fetchColumn();
    if ($readCount === 0) {
        $reads = [
            [3, 1, 5], [3, 2, 8], [3, 3, 16],
            [4, 1, 5], [4, 3, 16], [4, 4, 21],
            [5, 5, 19], [6, 6, 28],
            [1, 1, 5], [1, 2, 8], [2, 1, 5], [2, 2, 8],
        ];
        $stmt = $pdo->prepare('INSERT INTO thread_reads (user_id, thread_id, last_read_post_id) VALUES (?, ?, ?)');
        foreach ($reads as $r) {
            $stmt->execute($r);
        }
    }

    // --- Подписки ---
    $subCount = (int) $pdo->query('SELECT COUNT(*) FROM thread_subscriptions')->fetchColumn();
    if ($subCount === 0) {
        $subs = [
            [3, 3], [3, 7], [4, 4], [4, 6],
            [5, 5], [5, 9], [6, 6], [1, 1], [1, 2], [2, 5],
        ];
        $stmt = $pdo->prepare('INSERT INTO thread_subscriptions (user_id, thread_id) VALUES (?, ?)');
        foreach ($subs as $s) {
            $stmt->execute($s);
        }
    }

    // --- Друзья ---
    $friendCount = (int) $pdo->query('SELECT COUNT(*) FROM friendships')->fetchColumn();
    if ($friendCount === 0) {
        $friends = [
            [3, 4, 'accepted'],
            [3, 6, 'accepted'],
            [4, 6, 'accepted'],
            [3, 5, 'accepted'],
            [5, 7, 'pending'],
            [7, 3, 'pending'],
            [4, 7, 'accepted'],
            [6, 7, 'accepted'],
            [3, 9, 'blocked'],
        ];
        $stmt = $pdo->prepare('INSERT INTO friendships (user_id, friend_id, status) VALUES (?, ?, ?)');
        foreach ($friends as $f) {
            $stmt->execute($f);
        }
    }

    // --- Личные сообщения ---
    $pmCount = (int) $pdo->query('SELECT COUNT(*) FROM private_messages')->fetchColumn();
    if ($pmCount === 0) {
        $messages = [
            [3, 4, 'Привет! Ты сегодня вечером будешь?', 1, date('Y-m-d H:i:s', strtotime('-2 days'))],
            [4, 3, 'Да, примерно после 20:30', 1, date('Y-m-d H:i:s', strtotime('-2 days +10 minutes'))],
            [3, 4, 'Отлично, встретимся у спавна', 1, date('Y-m-d H:i:s', strtotime('-2 days +15 minutes'))],
            [6, 3, 'Спасибо за отзыв о замке! 😊', 0, date('Y-m-d H:i:s', strtotime('-1 day'))],
            [2, 5, 'По вопросу с подключением — если снова не получится, напиши сюда лог.', 1, date('Y-m-d H:i:s', strtotime('-1 day'))],
            [5, 2, 'Хорошо, спасибо большое!', 1, date('Y-m-d H:i:s', strtotime('-1 day +5 minutes'))],
            [4, 6, 'Можешь кинуть координаты замка ещё раз?', 0, date('Y-m-d H:i:s', strtotime('-5 hours'))],
            [1, 2, 'Нужно будет завтра проверить жалобы', 0, date('Y-m-d H:i:s', strtotime('-3 hours'))],
        ];
        $stmt = $pdo->prepare('INSERT INTO private_messages (sender_id, recipient_id, content, is_read, created_at) VALUES (?, ?, ?, ?, ?)');
        foreach ($messages as $m) {
            $stmt->execute($m);
        }
    }

    // =====================================================
    // ВЫВОД
    // =====================================================

    echo '<meta charset="utf-8">';
    echo '<style>
        body{font-family:system-ui,Arial,sans-serif;background:#151322;color:#eee;padding:40px;line-height:1.5}
        a{color:#ff65ad} code{background:#2a2438;padding:2px 6px;border-radius:4px}
        .ok{color:#7dffa0} .box{background:#1e1a2e;padding:16px 20px;border-radius:10px;margin:16px 0}
        ul{margin:8px 0} li{margin:4px 0}
    </style>';

    echo '<h1 class="ok">Готово 🍭</h1>';
    echo '<p>База <b>' . htmlspecialchars(DB_NAME) . '</b> создана, таблицы и тестовые данные заполнены.</p>';

    echo '<div class="box"><b>Тестовые аккаунты</b> (пароль у всех: <code>password</code>):<ul>';
    echo '<li><code>Admin</code> — администратор</li>';
    echo '<li><code>Moderator</code> — модератор</li>';
    echo '<li><code>Alex</code>, <code>Maria</code>, <code>Dmitry</code>, <code>Elena</code>, <code>Ivan</code>, <code>Olga</code> — обычные пользователи</li>';
    echo '<li><code>BannedUser</code> — забанен</li>';
    echo '</ul></div>';

    echo '<div class="box"><b>Что создано:</b><ul>';
    echo '<li>9 пользователей, 3 роли</li>';
    echo '<li>9 категорий (включая подкатегории)</li>';
    echo '<li>9 тем и ~40 постов</li>';
    echo '<li>Реакции, уведомления, логи</li>';
    echo '<li>Репорты, бан, вложения</li>';
    echo '<li>Прочитанные темы, подписки</li>';
    echo '<li>Друзья и личные сообщения</li>';
    echo '</ul></div>';

    echo '<p><a href="/lollipopmc/register/">Регистрация сайта</a> · <a href="/lollipopmc/forum/register/">Регистрация форума</a> · <a href="/lollipopmc/forum/">Форум</a></p>';

} catch (Throwable $e) {
    http_response_code(500);
    echo '<meta charset="utf-8"><pre>Ошибка установки: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    echo '<p>Проверь .env: DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD.</p>';
}