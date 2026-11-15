<?php
namespace App\Services;

use App\Models\Thread;
use App\Models\Post;
use App\Models\Section;
use App\Services\AuthService;
use App\Services\ActivityLogService;
use App\Services\ContentBlocksService;
use App\Services\ContentModerationService;

class ThreadService
{
    private Thread $threads;
    private Section $sections;

    public function __construct()
    {
        $this->threads = new Thread();
        $this->sections = new Section();
    }

    public function list(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        return $this->threads->findAdminList($filters, $page, $perPage);
    }

    public function find(int $id): ?array
    {
        $thread = $this->threads->findById($id);
        if (!$thread) {
            return null;
        }

        $first = (new Post())->findFirstByThreadId($id);
        if ($first) {
            $blocks = $first['content_blocks'] ?? null;
            if (is_string($blocks)) {
                $blocks = json_decode($blocks, true) ?: [];
            }
            if (!is_array($blocks) || $blocks === []) {
                $plain = trim((string)($first['content'] ?? ''));
                if ($plain !== '') {
                    $blocks = (new ContentBlocksService())->fromPlainText($plain);
                }
            }
            $thread['content_blocks'] = $blocks;
            $thread['content'] = $first['content'] ?? '';
            $thread['content_html'] = $first['content_html'] ?? '';
            $thread['first_post_id'] = (int)$first['id'];
        } else {
            $thread['content_blocks'] = [];
            $thread['content'] = '';
            $thread['content_html'] = '';
            $thread['first_post_id'] = null;
        }

        return $thread;
    }

    public function categoryOptions(): array
    {
        return $this->sections->getCategoryOptions();
    }

    /**
     * @return array{ok:bool, id?:int, errors?:array}
     */
    public function create(array $input): array
    {
        $data = $this->prepare($input);
        $errors = $this->validate($data);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $userId = (int)(AuthService::user()['id'] ?? 0);
        $data['user_id'] = $userId;
        if ($data['slug'] === '') {
            $data['slug'] = $this->threads->makeSlug($data['title']);
        }

        $blocksSvc = new ContentBlocksService();
        $blocks = $blocksSvc->normalize($input['content_blocks'] ?? null);
        if ($blocks === []) {
            $plain = trim($input['content'] ?? '');
            if ($plain === '') {
                $plain = $data['title'];
            }
            $blocks = $blocksSvc->fromPlainText($plain);
        }
        $plainJoined = $this->blocksToPlain($blocks);
        $html = $blocksSvc->toHtml($blocks);

        // draft | published | pending (автомодерация)
        $action = $input['action'] ?? 'publish';
        if ($action === 'draft') {
            $status = 'draft';
            $modReasons = [];
        } else {
            $canBypass = AuthService::can('threads.publish')
                || AuthService::can('lock_thread')
                || AuthService::hasRole('admin');
            $mod = new ContentModerationService();
            $check = $mod->checkBlocks($blocks);
            $modReasons = $check['reasons'];
            $status = ($canBypass || $check['ok']) ? 'published' : 'pending';
        }
        $data['status'] = $status;

        $id = $this->threads->create($data);

        (new Post())->createPost(
            $id,
            $userId,
            $plainJoined,
            null,
            $blocks,
            $html,
            $status === 'draft' ? 'draft' : 'published',
            true
        );

        ActivityLogService::log(
            $userId,
            'created_thread',
            'success',
            'thread',
            $id,
            $data['title']
        );

        return [
            'ok' => true,
            'id' => $id,
            'status' => $status,
            'moderation_reasons' => $modReasons ?? [],
        ];
    }

    public function update(int $id, array $input): array
    {
        $old = $this->threads->findById($id);
        if (!$old) {
            return ['ok' => false, 'errors' => ['id' => 'Тема не найдена']];
        }

        $data = $this->prepare($input, $id);
        $errors = $this->validate($data, $id);
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        if ($data['slug'] === '') {
            $data['slug'] = $old['slug'];
        }

        $blocksSvc = new ContentBlocksService();
        $blocks = $blocksSvc->normalize($input['content_blocks'] ?? null);
        if ($blocks === []) {
            $plain = trim($input['content'] ?? '');
            if ($plain !== '') {
                $blocks = $blocksSvc->fromPlainText($plain);
            }
        }

        $modReasons = [];
        if (!empty($input['action']) && $input['action'] === 'draft') {
            $data['status'] = 'draft';
        } elseif (!empty($input['action']) && $input['action'] === 'publish') {
            $canBypass = AuthService::can('threads.publish')
                || AuthService::can('lock_thread')
                || AuthService::hasRole('admin');
            $mod = new ContentModerationService();
            $check = $mod->checkBlocks($blocks);
            $modReasons = $check['reasons'];
            $data['status'] = ($canBypass || $check['ok']) ? 'published' : 'pending';
        } elseif (!empty($input['status'])) {
            $data['status'] = $input['status'];
        } else {
            $data['status'] = $old['status'] ?? 'published';
        }

        $this->threads->updateThread($id, $data);

        if ($blocks !== []) {
            $plainJoined = $this->blocksToPlain($blocks);
            $html = $blocksSvc->toHtml($blocks);
            $postModel = new Post();
            $first = $postModel->findFirstByThreadId($id);
            $postStatus = match ($data['status'] ?? 'published') {
                'draft' => 'draft',
                'pending' => 'pending',
                default => 'published',
            };
            if ($first) {
                $postModel->updateContent((int)$first['id'], $plainJoined, $blocks, $html, $postStatus);
            } else {
                $userId = (int)(AuthService::user()['id'] ?? $old['user_id'] ?? 0);
                $postModel->createPost($id, $userId, $plainJoined, null, $blocks, $html, $postStatus, true);
            }
        }

        $actor = AuthService::user()['id'] ?? null;
        if ($actor) {
            ActivityLogService::log(
                (int)$actor,
                'updated_thread',
                'success',
                'thread',
                $id,
                $data['title']
            );
        }

        return ['ok' => true, 'status' => $data['status'] ?? null, 'moderation_reasons' => $modReasons];
    }

    public function setStatus(int $id, string $status, int $actorId): array
    {
        if (!in_array($status, ['published', 'pending', 'rejected', 'draft'], true)) {
            return ['ok' => false, 'errors' => ['status' => 'Неверный статус']];
        }
        $t = $this->threads->findById($id);
        if (!$t) {
            return ['ok' => false, 'errors' => ['id' => 'Не найдено']];
        }
        $this->threads->updateThread($id, [
            'category_id' => $t['category_id'],
            'title'       => $t['title'],
            'slug'        => $t['slug'],
            'is_sticky'   => !empty($t['is_sticky']),
            'is_locked'   => !empty($t['is_locked']),
            'is_hidden'   => !empty($t['is_hidden']),
            'status'      => $status,
        ]);
        $first = (new Post())->findFirstByThreadId($id);
        if ($first) {
            $postStatus = $status === 'draft' ? 'draft' : ($status === 'pending' ? 'pending' : ($status === 'rejected' ? 'rejected' : 'published'));
            (new Post())->updateContent(
                (int)$first['id'],
                (string)($first['content'] ?? ''),
                is_string($first['content_blocks'] ?? null)
                    ? (json_decode($first['content_blocks'], true) ?: [])
                    : (array)($first['content_blocks'] ?? []),
                (string)($first['content_html'] ?? ''),
                $postStatus
            );
        }
        ActivityLogService::log($actorId, 'moderated_thread', 'success', 'thread', $id, $t['title'], ['status' => $status]);
        return ['ok' => true];
    }

    public function toggleLock(int $id, bool $locked): array
    {
        $t = $this->threads->findById($id);
        if (!$t) {
            return ['ok' => false, 'errors' => ['id' => 'Не найдено']];
        }
        $this->threads->setFlags($id, ['is_locked' => $locked]);
        $actor = AuthService::user()['id'] ?? null;
        if ($actor) {
            ActivityLogService::log(
                (int)$actor,
                $locked ? 'locked_thread' : 'unlocked_thread',
                'success',
                'thread',
                $id,
                $t['title']
            );
        }
        return ['ok' => true];
    }

    public function toggleSticky(int $id, bool $sticky): array
    {
        $t = $this->threads->findById($id);
        if (!$t) {
            return ['ok' => false, 'errors' => ['id' => 'Не найдено']];
        }
        $this->threads->setFlags($id, ['is_sticky' => $sticky]);
        $actor = AuthService::user()['id'] ?? null;
        if ($actor) {
            ActivityLogService::log(
                (int)$actor,
                $sticky ? 'pinned_thread' : 'unpinned_thread',
                'success',
                'thread',
                $id,
                $t['title']
            );
        }
        return ['ok' => true];
    }

    public function hide(int $id, bool $hidden = true): array
    {
        $t = $this->threads->findById($id);
        if (!$t) {
            return ['ok' => false, 'errors' => ['id' => 'Не найдено']];
        }
        $this->threads->setFlags($id, ['is_hidden' => $hidden]);
        return ['ok' => true];
    }

    public function move(int $id, int $categoryId): array
    {
        $t = $this->threads->findById($id);
        if (!$t) {
            return ['ok' => false, 'errors' => ['id' => 'Не найдено']];
        }
        $this->threads->move($id, $categoryId);
        $actor = AuthService::user()['id'] ?? null;
        if ($actor) {
            ActivityLogService::log(
                (int)$actor,
                'moved_thread',
                'success',
                'thread',
                $id,
                $t['title'],
                ['from' => $t['category_id'], 'to' => $categoryId]
            );
        }
        return ['ok' => true];
    }


    /**
     * Можно ли модерировать тему (проверка иерархии относительно автора темы).
     * Для своих тем — разрешено; для чужих — только если актор выше автора по роли.
     */
    public function canActOn(int $actorId, int $threadId): bool
    {
        if ($actorId <= 0 || $threadId <= 0) {
            return false;
        }

        $thread = $this->threads->findById($threadId);
        if (!$thread) {
            return false;
        }

        $authorId = (int)($thread['user_id'] ?? 0);
        if ($authorId <= 0) {
            return true; // нет автора — не блокируем
        }
        if ($actorId === $authorId) {
            return true; // своя тема
        }

        return (new RoleHierarchyService())->canActOnUser($actorId, $authorId)['ok'] === true;
    }

    private function blocksToPlain(array $blocks): string
    {
        $parts = [];
        foreach ($blocks as $b) {
            if (($b['type'] ?? '') === 'text') {
                $parts[] = $b['md'] ?? '';
            }
        }
        return trim(implode("

", $parts));
    }

    private function prepare(array $input, ?int $id = null): array
    {
        return [
            'title'       => trim($input['title'] ?? ''),
            'slug'        => trim($input['slug'] ?? ''),
            'category_id' => (int)($input['category_id'] ?? 0),
            'is_sticky'   => !empty($input['is_sticky']),
            'is_locked'   => !empty($input['is_locked']),
            'is_hidden'   => !empty($input['is_hidden']),
            'status'      => $input['status'] ?? null,
        ];
    }

    private function validate(array $data, ?int $excludeId = null): array
    {
        $errors = [];
        if ($data['title'] === '') {
            $errors['title'] = 'Укажите название';
        }
        if ($data['category_id'] <= 0) {
            $errors['category_id'] = 'Выберите раздел';
        }
        if ($data['slug'] !== '') {
            $existing = $this->threads->findBySlug($data['slug']);
            if ($existing && (int)$existing['id'] !== (int)$excludeId) {
                $errors['slug'] = 'Такой slug уже занят';
            }
        }
        return $errors;
    }
}
