<?php
namespace App\Models;

use Core\Model;

class Moderation extends Model
{
    public function allCategories(): array
    {
        return $this->findMany("
            SELECT * FROM minigames_categories ORDER BY id
        ");
    }
}