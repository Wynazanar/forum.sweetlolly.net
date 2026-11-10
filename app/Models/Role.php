<?php
namespace App\Models;

use Core\Model;

class Role extends Model
{
    /**
     * @return array<int, array{id: int, name: string}>
     */
    public function getAll(): array
    {
        return $this->findMany(
            "SELECT id, name FROM roles ORDER BY id"
        );
    }

    /**
     * [id => label] для селектов
     */
    public function getOptions(): array
    {
        $rows = $this->getAll();
        $options = [];

        foreach ($rows as $row) {
            $options[(string)$row['id']] = $this->label($row['name']);
        }

        return $options;
    }

    private function label(string $name): string
    {
        return match ($name) {
            'user'       => 'Игроки',
            'moderator'  => 'Модераторы',
            'admin'      => 'Администраторы',
            default      => mb_convert_case(str_replace('_', ' ', $name), MB_CASE_TITLE, 'UTF-8'),
        };
    }
}