<?php
namespace App\Models;

use Core\Model;

class Post extends Model
{
    /**
     * Все посты темы по slug (+ информация о цитируемом посте)
     */
    public function findByThread(string $slug): array
    {
        return $this->findMany(
            "SELECT 
                p.*,
                u.username,
                u.avatar,
                u.role_id,
                rp.content AS reply_to_content,
                ru.username AS reply_to_username
             FROM posts p
             JOIN users u ON u.id = p.user_id
             JOIN threads t ON t.id = p.thread_id
             LEFT JOIN posts rp ON rp.id = p.reply_to_post_id
             LEFT JOIN users ru ON ru.id = rp.user_id
             WHERE t.slug = ?
             ORDER BY p.created_at ASC",
            [$slug]
        );
    }

    /**
     * Строим дерево постов (родитель → дети)
     */
    public function buildTree(array $posts): array
    {
        $indexed = [];
        $tree = [];

        // Сначала индексируем все посты
        foreach ($posts as $post) {
            $post['children'] = [];
            $indexed[$post['id']] = $post;
        }

        // Раскладываем по родителям
        foreach ($indexed as $id => &$post) {
            if (!empty($post['reply_to_post_id']) && isset($indexed[$post['reply_to_post_id']])) {
                $indexed[$post['reply_to_post_id']]['children'][] = &$post;
            } else {
                $tree[] = &$post;
            }
        }
        unset($post);

        return $tree;
    }

    /**
     * Информация о теме по slug
     */
    public function getThreadBySlug(string $slug): ?array
    {
        return $this->findOne(
            "SELECT t.*, 
                    u.username AS author_name,
                    c.title AS category_title
             FROM threads t
             JOIN users u ON u.id = t.user_id
             JOIN categories c ON c.id = t.category_id
             WHERE t.slug = ?
             LIMIT 1",
            [$slug]
        );
    }

    /**
     * Создание нового поста (обычного или ответа на другой пост)
     */
    public function createPost(int $threadId, int $userId, string $content, ?int $replyToPostId = null): int
    {
        $this->execute(
            "INSERT INTO posts (thread_id, user_id, reply_to_post_id, content, is_first, created_at)
             VALUES (?, ?, ?, ?, 0, NOW())",
            [$threadId, $userId, $replyToPostId, $content]
        );

        $postId = (int) $this->lastInsertId();

        // Обновляем счётчики темы
        $this->execute(
            "UPDATE threads 
             SET posts_count = posts_count + 1,
                 last_post_id = ?,
                 last_post_at = NOW(),
                 updated_at = NOW()
             WHERE id = ?",
            [$postId, $threadId]
        );

        // Увеличиваем счётчик постов пользователя
        $this->execute(
            "UPDATE users SET posts_count = posts_count + 1 WHERE id = ?",
            [$userId]
        );

        return $postId;
    }
}