<?php
use Core\Route;

return [
    new Route('/', 'main', 'index'),

    new Route('/forums/:thread', 'thread', 'show'),



    new Route('/moderation', 'moderation', 'index'),
    new Route('/moderation/:tab', 'moderation', 'index'),
];