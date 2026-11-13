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
            'lock_thread'     => 'Закрывать / открывать темы',
            'move_thread'     => 'Перемещать темы',
            'delete_thread'   => 'Удалять темы',
            'react'           => 'Ставить реакции',
            'report'          => 'Жаловаться на контент',
            'send_pm'         => 'Личные сообщения',
        ],
    ],
    'moderation' => [
        'label' => 'Модерация',
        'permissions' => [
            'resolve_report'    => 'Разбирать репорты / жалобы',
            'ban_user'          => 'Банить игроков (legacy)',
            'view_logs'         => 'Смотреть логи',
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
            'manage_roles' => 'Управлять ролями и правами',
        ],
    ],
];
