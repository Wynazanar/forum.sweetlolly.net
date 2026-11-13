<?php
use Core\Route;

return [
    new Route('/', 'main', 'index'),

    new Route('/login',        'auth', 'loginForm'),
    new Route('/login/submit', 'auth', 'login'),
    new Route('/logout',       'auth', 'logout'),

    new Route('/attachments/:id/download', 'attachment', 'download'),

    new Route('/forums/:thread', 'thread', 'show'),

    // ===== Публичные заявки (для игроков) =====
    new Route('/applications',              'application', 'publicList'),
    new Route('/application/:id',           'application', 'publicShow'),
    new Route('/application/:id/submit',    'application', 'publicSubmit'),

    // Кабинет игрока — заявки
    new Route('/my/applications',                    'application', 'myList'),
    new Route('/my/applications/:id',                'application', 'myShow'),
    new Route('/my/applications/:id/reply',          'application', 'myReply'),
    new Route('/my/applications/:id/withdraw',       'application', 'myWithdraw'),





    // ===== Applications (конкретные роуты ДО общего /moderation/:tab) =====
    // список заявок → /moderation/:tab (ModerationController), чтобы была навигация
    new Route('/moderation/applications/create',             'application', 'create'),
    new Route('/moderation/applications/store',              'application', 'store'),
    new Route('/moderation/applications/edit/:id',           'application', 'edit'),
    new Route('/moderation/applications/update/:id',         'application', 'update'),
    new Route('/moderation/applications/delete/:id',         'application', 'delete'),
    new Route('/moderation/applications/show/:id',           'application', 'show'),
    new Route('/moderation/applications/review/:id',         'application', 'review'),
    new Route('/moderation/applications/layouts',            'application', 'layouts'),
    new Route('/moderation/applications/layouts/create',     'application', 'layoutCreate'),
    new Route('/moderation/applications/layouts/store',      'application', 'layoutStore'),
    new Route('/moderation/applications/layouts/edit/:id',   'application', 'layoutEdit'),
    new Route('/moderation/applications/layouts/update/:id', 'application', 'layoutUpdate'),
    new Route('/moderation/applications/layouts/delete/:id', 'application', 'layoutDelete'),

    // Players
    new Route('/moderation/players/create',          'player', 'create'),
    new Route('/moderation/players/store',           'player', 'store'),
    new Route('/moderation/players/edit/:id',        'player', 'edit'),
    new Route('/moderation/players/update/:id',      'player', 'update'),
    new Route('/moderation/players/punish/:id',      'player', 'punish'),
    new Route('/moderation/players/revoke/:id',      'player', 'revoke'),
    new Route('/moderation/players/deactivate/:id',  'player', 'deactivate'),


    // Roles
    new Route('/moderation/roles/create',     'role', 'create'),
    new Route('/moderation/roles/store',      'role', 'store'),
    new Route('/moderation/roles/edit/:id',   'role', 'edit'),
    new Route('/moderation/roles/update/:id', 'role', 'update'),
    new Route('/moderation/roles/delete/:id', 'role', 'delete'),

    // Sections
    new Route('/moderation/sections/create',     'section', 'create'),
    new Route('/moderation/sections/store',      'section', 'store'),
    new Route('/moderation/sections/edit/:id',   'section', 'edit'),
    new Route('/moderation/sections/update/:id', 'section', 'update'),
    new Route('/moderation/sections/delete/:id', 'section', 'delete'),

    // Общий moderation (должен быть после конкретных)
    new Route('/moderation', 'moderation', 'index'),
    new Route('/moderation/:tab', 'moderation', 'index'),
];
