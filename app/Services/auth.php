<?php

use App\Services\AuthService;

function auth(): ?array
{
    return AuthService::user();
}

function can(string $permission): bool
{
    return AuthService::can($permission);
}

function guest(): bool
{
    return !AuthService::check();
}