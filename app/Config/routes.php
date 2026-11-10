<?php
use Core\Route;

return [
    new Route('/', 'main', 'index'),

    new Route('/forums/:thread', 'thread', 'show'),



    new Route('/moderation', 'moderation', 'index'),
    new Route('/moderation/:tab', 'moderation', 'index'),


    // Sections
    new Route('/moderation/sections/create',     'section', 'create'),
    new Route('/moderation/sections/store',      'section', 'store'),
    new Route('/moderation/sections/edit/:id',   'section', 'edit'),
    new Route('/moderation/sections/update/:id', 'section', 'update'),
    new Route('/moderation/sections/delete/:id', 'section', 'delete'),

    // Applications
    new Route('/moderation/applications/create',     'application', 'create'),
    new Route('/moderation/applications/store',      'application', 'store'),
    new Route('/moderation/applications/edit/:id',   'application', 'edit'),
    new Route('/moderation/applications/update/:id', 'application', 'update'),
    new Route('/moderation/applications/delete/:id', 'application', 'delete'),
];