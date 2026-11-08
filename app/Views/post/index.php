<?php
/**
 * @var array $thread
 * @var array $posts   // уже дерево
 */

/**
 * Рекурсивный вывод поста и его ответов
 */
function renderPost(array $post, int $depth = 0): void
{
    $indent = $depth * 28; // отступ в пикселях
    ?>
    <div class="post" id="post-<?= $post['id'] ?>"
         style="margin-top: 18px; margin-left: <?= $indent ?>px;
                <?= $depth > 0 ? 'border-left: 3px solid #c5d0e0; padding-left: 16px;' : '' ?>">

        <div class="post-header">
            <b><?= htmlspecialchars($post['username']) ?></b>
            <small><?= $post['created_at'] ?></small>
        </div>

        <?php if (!empty($post['reply_to_post_id']) && $depth === 0): ?>
            <!-- Показываем цитату только если пост оказался на верхнем уровне (на всякий случай) -->
            <div class="reply-to">
                В ответ <b><?= htmlspecialchars($post['reply_to_username'] ?? '') ?></b>:
                <div class="quoted">
                    <?= nl2br(htmlspecialchars(mb_strimwidth($post['reply_to_content'] ?? '', 0, 180, '...'))) ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="post-content">
            <?= nl2br(htmlspecialchars($post['content'])) ?>
        </div>

        <div class="post-actions">
            <a href="?reply_to=<?= $post['id'] ?>">Ответить</a>
        </div>

        <?php if (!empty($post['children'])): ?>
            <?php foreach ($post['children'] as $child): ?>
                <?php renderPost($child, $depth + 1); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
}
?>

<h1><?= htmlspecialchars($thread['title']) ?></h1>

<p>
    Автор: <b><?= htmlspecialchars($thread['author_name']) ?></b> · 
    Категория: <?= htmlspecialchars($thread['category_title']) ?> · 
    Просмотров: <?= (int)$thread['views'] ?>
</p>

<hr>

<?php foreach ($posts as $post): ?>
    <?php renderPost($post); ?>
<?php endforeach; ?>