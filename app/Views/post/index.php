<?php
use App\Components\Editor;
use App\Services\AuthService;

$thread = $thread ?? [];
$posts = $posts ?? [];
$slug = $thread['slug'] ?? '';
$error = $_GET['error'] ?? '';
$canReply = AuthService::check() && empty($thread['is_locked']);
$avatar = AuthService::user()['avatar'] ?? null;
$letter = mb_strtoupper(mb_substr(AuthService::user()['username'] ?? '?', 0, 1));
?>
<main>
<div class="container" style="max-width:920px;margin:0 auto;padding:20px;">
    <h1 style="margin-bottom:8px;"><?= htmlspecialchars($thread['title'] ?? '') ?></h1>
    <p class="text-muted" style="font-size:13px;margin-bottom:20px;">
        Автор: <strong><?= htmlspecialchars($thread['author_name'] ?? '') ?></strong>
        · <?= htmlspecialchars($thread['category_title'] ?? '') ?>
        · Просмотров: <?= (int)($thread['views'] ?? 0) ?>
    </p>

    <?php if ($error === 'empty'): ?>
        <div class="alert alert-danger">Напишите текст или прикрепите фото.</div>
    <?php elseif ($error === 'locked'): ?>
        <div class="alert alert-danger">Тема закрыта.</div>
    <?php endif; ?>

    <?php
    $renderPost = function (array $post, int $depth = 0) use (&$renderPost) {
        $pad = min($depth, 4) * 20;
        ?>
        <article class="thread-post" id="post-<?= (int)$post['id'] ?>"
                 style="margin:14px 0;padding:16px;border-radius:16px;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.04);margin-left:<?= $pad ?>px;">
            <header style="display:flex;justify-content:space-between;margin-bottom:10px;font-size:13px;">
                <div>
                    <strong><?= htmlspecialchars($post['username'] ?? '') ?></strong>
                    <span class="text-muted"> · <?= htmlspecialchars($post['created_at'] ?? '') ?></span>
                </div>
            </header>
            <div class="post-body">
                <?= $post['body_html'] ?? nl2br(htmlspecialchars($post['content'] ?? '')) ?>
            </div>
            <?php foreach ($post['children'] ?? [] as $child): ?>
                <?php $renderPost($child, $depth + 1); ?>
            <?php endforeach; ?>
        </article>
        <?php
    };
    foreach ($posts as $post) {
        $renderPost($post, 0);
    }
    ?>

    <?php if ($canReply): ?>
        <section class="reply-box">
            <h3 class="reply-box__title">
                <?php if ($avatar): ?>
                    <img src="<?= htmlspecialchars(url(ltrim($avatar, '/'))) ?>" alt=""
                         style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                <?php else: ?>
                    <span style="width:36px;height:36px;border-radius:50%;background:#7c5cfc;color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;"><?= htmlspecialchars($letter) ?></span>
                <?php endif; ?>
                Присоединиться к обсуждению
            </h3>
            <form method="post" action="<?= url('/forums/' . rawurlencode($slug) . '/reply') ?>">
                <?= Editor::make([
                    'name'        => 'content_blocks',
                    'mode'        => 'compact',
                    'placeholder' => 'Напиши ответ или процитируй сообщение выше…',
                ]) ?>
                <div class="reply-box__actions">
                    <button type="submit" class="reply-box__submit">Отправить ответ</button>
                </div>
            </form>
        </section>
    <?php elseif (!AuthService::check()): ?>
        <p class="text-muted" style="margin-top:24px;"><a href="<?= url('/login') ?>">Войдите</a>, чтобы ответить.</p>
    <?php else: ?>
        <p class="text-muted" style="margin-top:24px;">Тема закрыта для ответов.</p>
    <?php endif; ?>
</div>
</main>
<style>
.post-body .content-block--text { margin-bottom: 10px; line-height: 1.55; }
.post-body .content-block--images { display: grid; gap: 10px; margin: 12px 0; }
.post-body .content-block--images.layout-2 { grid-template-columns: 1fr 1fr; }
.post-body .content-block--images.layout-3 { grid-template-columns: 1fr 1fr 1fr; }
.post-body .content-block__img { max-width: 100%; border-radius: 12px; }
</style>
