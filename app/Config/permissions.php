<?php
/**
 * Каталог прав форума.
 * Ключ = то, что проверяет AuthService::can('...').
 * Группы используются в UI редактирования роли.
 */
return [
    'forum' => [
        'label' => 'Форум',
        'permissions' => [
            'create_thread'   => 'Создавать темы',
            'create_post'     => 'Отвечать в темах',
            'edit_own_post'   => 'Редактировать свои сообщения',
            'edit_any_post'   => 'Редактировать чужие сообщения',
            'delete_own_post' => 'Удалять свои сообщения',
            'delete_any_post' => 'Удалять чужие сообщения',
            'react'           => 'Ставить реакции',
            'report'          => 'Жаловаться на контент',
            'send_pm'         => 'Личные сообщения',
            'posts.attach'    => 'Прикреплять файлы к постам',
        ],
    ],
    'threads' => [
        'label' => 'Темы',
        'permissions' => [
            'threads.view_mod'    => 'Список тем в админке',
            'threads.publish'     => 'Публиковать без автопроверки',
            'threads.moderate'    => 'Одобрять / отклонять pending',
            'threads.pin'         => 'Закреплять темы',
            'threads.lock'        => 'Закрывать / открывать темы',
            'threads.move'        => 'Переносить темы между разделами',
            'threads.hide'        => 'Скрывать темы',
            'threads.edit_any'    => 'Редактировать любую тему',
            // legacy-алиасы остаются в AuthService
            'lock_thread'         => 'Закрывать темы (legacy)',
            'move_thread'         => 'Переносить темы (legacy)',
            'delete_thread'       => 'Удалять темы (legacy)',
        ],
    ],
    'discussions' => [
        'label' => 'Обсуждения',
        'permissions' => [
            'discussions.view_mod'    => 'Открывать раздел обсуждений в модерации',
            'discussions.reply'       => 'Отвечать в обсуждениях от имени модерации',
            'discussions.publish'     => 'Публиковать обсуждения без автопроверки',
            'discussions.moderate'    => 'Одобрять / отклонять pending обсуждения',
            'discussions.lock'        => 'Закрывать / открывать обсуждения',
            'discussions.hide'        => 'Скрывать обсуждения',
            'discussions.edit_any'    => 'Редактировать сообщения других пользователей',
            'discussions.view_hidden' => 'Просматривать скрытые сообщения',
        ],
    ],
    'moderation' => [
        'label' => 'Модерация',
        'permissions' => [
            'resolve_report'    => 'Разбирать репорты / жалобы',
            'ban_user'          => 'Банить игроков (legacy)',
            'manage_categories' => 'Управлять разделами форума',
        ],
    ],
    'players' => [
        'label' => 'Игроки',
        'permissions' => [
            'players.view'       => 'Смотреть список и карточки',
            'players.create'     => 'Создавать аккаунты',
            'players.edit'       => 'Редактировать профиль (ник, email)',
            'players.edit_role'  => 'Менять роль',
            'players.deactivate' => 'Деактивировать / активировать аккаунт',
            'players.ban'        => 'Бан / снять бан',
            'players.mute'       => 'Мут / снять мут',
            'players.restrict'   => 'Прочие ограничения',
        ],
    ],
    'applications' => [
        'label' => 'Заявки',
        'permissions' => [
            'applications.view'           => 'Смотреть заявки',
            'applications.review'         => 'Принимать / отклонять заявки',
            'applications.forms.manage'   => 'Управлять шаблонами заявок',
            'applications.layouts.manage' => 'Управлять макетами заявок',
            'applications.submit'         => 'Подавать заявки (игроки)',
        ],
    ],
    'admin' => [
        'label' => 'Администрирование',
        'permissions' => [
            'view_logs'           => 'Смотреть логи',
            'manage_roles'        => 'Управлять ролями и правами',
            'threads.view_drafts' => 'Видеть все черновики тем',
            'discussions.view_drafts' => 'Видеть все черновики обсуждений',
        ],
    ],
];
