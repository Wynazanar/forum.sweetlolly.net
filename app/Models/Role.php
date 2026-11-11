<?php
namespace App\Models;

use Core\Model;

class Role extends Model
{
    public function getAll(): array
    {
        $rows = $this->findMany(
            "SELECT id, name, permissions FROM roles ORDER BY id"
        );

        foreach ($rows as &$row) {
            $row['permissions'] = $this->decodePermissions($row['permissions'] ?? []);
            $row['users_count'] = 0;
        }
        unset($row);

        $counts = $this->findMany(
            "SELECT role_id, COUNT(*) AS cnt FROM users GROUP BY role_id"
        );
        $map = [];
        foreach ($counts as $c) {
            $map[(int)$c['role_id']] = (int)$c['cnt'];
        }
        foreach ($rows as &$row) {
            $row['users_count'] = $map[(int)$row['id']] ?? 0;
        }
        unset($row);

        return $rows;
    }

    public function findById(int $id): ?array
    {
        $row = $this->findOne(
            "SELECT id, name, permissions FROM roles WHERE id = ?",
            [$id]
        );
        if (!$row) {
            return null;
        }
        $row['permissions'] = $this->decodePermissions($row['permissions'] ?? []);
        $cnt = $this->findOne(
            "SELECT COUNT(*) AS cnt FROM users WHERE role_id = ?",
            [$id]
        );
        $row['users_count'] = (int)($cnt['cnt'] ?? 0);
        return $row;
    }

    public function findByName(string $name): ?array
    {
        $row = $this->findOne(
            "SELECT id, name, permissions FROM roles WHERE name = ?",
            [$name]
        );
        if (!$row) {
            return null;
        }
        $row['permissions'] = $this->decodePermissions($row['permissions'] ?? []);
        return $row;
    }

    public function getOptions(): array
    {
        $rows = $this->findMany("SELECT id, name FROM roles ORDER BY id");
        $options = [];
        foreach ($rows as $row) {
            $options[(string)$row['id']] = $this->label($row['name']);
        }
        return $options;
    }

    public function create(string $name, array $permissions): int
    {
        $this->execute(
            "INSERT INTO roles (name, permissions) VALUES (?, ?)",
            [$name, json_encode($permissions, JSON_UNESCAPED_UNICODE)]
        );
        return (int)$this->lastInsertId();
    }

    public function updateRole(int $id, string $name, array $permissions): bool
    {
        $this->execute(
            "UPDATE roles SET name = ?, permissions = ? WHERE id = ?",
            [$name, json_encode($permissions, JSON_UNESCAPED_UNICODE), $id]
        );
        return $this->affectedRows() >= 0;
    }

    public function delete(int $id): array
    {
        $role = $this->findById($id);
        if (!$role) {
            return ['ok' => false, 'error' => 'Роль не найдена'];
        }

        if (in_array($role['name'], ['user', 'moderator', 'admin'], true)) {
            return ['ok' => false, 'error' => 'Системную роль нельзя удалить'];
        }

        if (($role['users_count'] ?? 0) > 0) {
            return ['ok' => false, 'error' => 'На роли есть пользователи — сначала смените им роль'];
        }

        $this->execute("DELETE FROM roles WHERE id = ?", [$id]);
        return ['ok' => true];
    }

    public function label(string $name): string
    {
        return match ($name) {
            'user'       => 'Игрок',
            'moderator'  => 'Модератор',
            'admin'      => 'Администратор',
            'helper'     => 'Хелпер',
            'curator'    => 'Куратор',
            default      => mb_convert_case(str_replace(['_', '-'], ' ', $name), MB_CASE_TITLE, 'UTF-8'),
        };
    }

    private function decodePermissions($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
