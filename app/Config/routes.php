<?php
use Core\Route;

return [
    new Route('/', 'main', 'index'),

    new Route('/forums/:thread', 'thread', 'show'),



    new Route('/moderation', 'moderation', 'index'),
    new Route('/moderation/:tab', 'moderation', 'index'),


    new Route('/moderation/sections/create',     'section', 'create'),
    new Route('/moderation/sections/store',      'section', 'store'),
    new Route('/moderation/sections/edit/:id',   'section', 'edit'),
    new Route('/moderation/sections/update/:id', 'section', 'update'),
    new Route('/moderation/sections/delete/:id', 'section', 'delete'),
];