<?php
namespace App\Controllers;

use Core\Controller;
use App\Models\Post;
use App\Services\ContentBlocksService;
use App\Services\ContentModerationService;
use App\Services\AuthService;


class ThreadController extends Controller
{
    public function show($params)
    {
        $postModel = new Post();

        $slug = trim(urldecode($params['thread'] ?? ''));

        if ($slug === '') {
            return $this->error(404);
        }

        $thread = $postModel->getThreadBySlug($slug);

        if (!$thread) {
            return $this->error(404);
        }

        $viewerId = AuthService::check() ? (int)(AuthService::user()['id'] ?? 0) : 0;
        $isMod = AuthService::check() && (
            AuthService::can('lock_thread') || AuthService::can('threads.moderate') || AuthService::hasRole('admin')
        );

        $tStatus = $thread['status'] ?? 'published';
        if ($tStatus === 'draft' && $viewerId !== (int)$thread['user_id']) {
            return $this->error(404);
        }
        if ($tStatus === 'pending' && $viewerId !== (int)$thread['user_id'] && !$isMod) {
            return $this->error(404);
        }
        if ($tStatus === 'rejected' && $viewerId !== (int)$thread['user_id'] && !$isMod) {
            return $this->error(404);
        }

        $posts = $postModel->findByThread($slug);
        $blocksSvc = new ContentBlocksService();

        // Публично — только published; свои pending/draft видно автору; модер видит pending
        $posts = array_values(array_filter($posts, function ($p) use ($viewerId, $isMod) {
            $st = $p['status'] ?? 'published';
            if ($st === 'published') return true;
            if ($isMod && $st === 'pending') return true;
            if ($viewerId > 0 && (int)($p['user_id'] ?? 0) === $viewerId) return true;
            return false;
        }));

        // Готовим HTML для каждого поста
        foreach ($posts as &$post) {
            $html = $post['content_html'] ?? '';
            if ($html === null || $html === '') {
                $blocks = $post['content_blocks'] ?? null;
                if (is_string($blocks)) {
                    $blocks = json_decode($blocks, true) ?: [];
                }
                if (is_array($blocks) && $blocks !== []) {
                    $html = $blocksSvc->toHtml($blocks);
                } else {
                    // fallback: простой текст без сырого markdown-отображения как есть
                    $html = $blocksSvc->toHtml($blocksSvc->fromPlainText((string)($post['content'] ?? '')));
                }
            }
            $post['body_html'] = $html;
        }
        unset($post);

        $tree = $postModel->buildTree($posts);

        $this->title = $thread['title'] . ' | SweetLolly Форум';

        return $this->render('post/index', [
            'thread' => $thread,
            'posts'  => $tree,
        ]);
    }

    public function reply(array $params = [])
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . url('/'));
            exit;
        }

        if (!AuthService::check()) {
            header('Location: ' . url('/login'));
            exit;
        }

        $slug = trim(urldecode($params['thread'] ?? ''));
        $postModel = new Post();
        $thread = $postModel->getThreadBySlug($slug);

        if (!$thread) {
            return $this->error(404);
        }

        if (!empty($thread['is_locked'])) {
            header('Location: ' . url('/forums/' . rawurlencode($slug)) . '?error=locked');
            exit;
        }

        // draft themes: only author
        $status = $thread['status'] ?? 'published';
        $uid = (int)(AuthService::user()['id'] ?? 0);
        if ($status === 'draft' && (int)$thread['user_id'] !== $uid) {
            return $this->error(404);
        }
        if ($status === 'pending' && (int)$thread['user_id'] !== $uid && !AuthService::can('lock_thread')) {
            return $this->error(404);
        }

        $blocksSvc = new ContentBlocksService();
        $blocks = $blocksSvc->normalize($_POST['content_blocks'] ?? null);
        if ($blocks === []) {
            $plain = trim((string)($_POST['content'] ?? ''));
            if ($plain !== '') {
                $blocks = $blocksSvc->fromPlainText($plain);
            }
        }

        if ($blocks === []) {
            header('Location: ' . url('/forums/' . rawurlencode($slug)) . '?error=empty');
            exit;
        }

        $plain = '';
        foreach ($blocks as $b) {
            if (($b['type'] ?? '') === 'text') {
                $plain .= ($b['md'] ?? '') . "\n\n";
            }
        }
        $plain = trim($plain) ?: '(вложение)';
        $html = $blocksSvc->toHtml($blocks);

        $replyTo = !empty($_POST['reply_to_post_id']) ? (int)$_POST['reply_to_post_id'] : null;

        $canBypass = AuthService::can('threads.publish')
            || AuthService::can('lock_thread')
            || AuthService::hasRole('admin');
        $mod = new ContentModerationService();
        $check = $mod->checkBlocks($blocks);
        $postStatus = ($canBypass || $check['ok']) ? 'published' : 'pending';

        $postModel->createPost(
            (int)$thread['id'],
            $uid,
            $plain,
            $replyTo,
            $blocks,
            $html,
            $postStatus,
            false
        );

        $redir = url('/forums/' . rawurlencode($slug));
        if ($postStatus === 'pending') {
            $redir .= '?mod=pending';
        }
        header('Location: ' . $redir);
        exit;
    }
}
