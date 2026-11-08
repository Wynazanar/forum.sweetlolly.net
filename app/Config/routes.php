<?php
use Core\Route;

return [
    new Route('/', 'main', 'index'),

    new Route('/forums/:thread', 'thread', 'show'),
];