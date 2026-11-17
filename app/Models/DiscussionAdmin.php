<?php
namespace App\Models;

use Core\Model;

/** SQL-слой страницы модерации обсуждений. Все большие выборки ограничены LIMIT/OFFSET. */
class DiscussionAdmin extends Model
{
    public function threads(array $f, int $page, int $perPage, int $viewerId, bool $seeAllDrafts): array
    {
        $page = max(1, $page); $perPage = max(5, min(50, $perPage)); $offset = ($page - 1) * $perPage;
        $where = ["1=1"]; $params = [];
        if ($f['q'] !== '') {
            $where[] = "(t.title LIKE ? OR u.username LIKE ? OR CAST(t.id AS CHAR)=? OR EXISTS (SELECT 1 FROM posts sp JOIN users su ON su.id=sp.user_id WHERE sp.thread_id=t.id AND (sp.content LIKE ? OR su.username LIKE ?)))";
            $like = '%'.$f['q'].'%'; array_push($params, $like, $like, $f['q'], $like, $like);
        }
        if ($f['category'] > 0) { $where[] = 't.category_id=?'; $params[] = $f['category']; }
        if ($f['status'] !== '') { $where[] = "COALESCE(t.status,'published')=?"; $params[] = $f['status']; }
        if (!$seeAllDrafts) { $where[] = "(COALESCE(t.status,'published') <> 'draft' OR t.user_id=?)"; $params[] = $viewerId; }
        if ($f['locked'] !== '') { $where[] = 't.is_locked=?'; $params[] = (int)$f['locked']; }
        if ($f['hidden'] !== '') { $where[] = 't.is_hidden=?'; $params[] = (int)$f['hidden']; }
        $sqlWhere = implode(' AND ', $where);
        $cnt = $this->findOne("SELECT COUNT(*) cnt FROM threads t JOIN users u ON u.id=t.user_id WHERE $sqlWhere", $params);
        $total = (int)($cnt['cnt'] ?? 0); $pages = max(1,(int)ceil($total/$perPage));
        $items = $this->findMany(
            "SELECT t.id,t.title,t.slug,t.category_id,t.user_id,t.status,t.is_locked,t.is_hidden,t.is_sticky,t.posts_count,t.last_post_at,t.created_at,
                    u.username author_name,c.title category_title,
                    (SELECT COUNT(*) FROM reports r JOIN posts rp ON r.target_type='post' AND r.target_id=rp.id WHERE rp.thread_id=t.id AND r.status='pending') reports_count
             FROM threads t JOIN users u ON u.id=t.user_id LEFT JOIN categories c ON c.id=t.category_id
             WHERE $sqlWhere ORDER BY t.last_post_at DESC,t.id DESC LIMIT $perPage OFFSET $offset", $params
        );
        return compact('items','total','page','pages');
    }

    /** Глобальная очередь сообщений на проверке, не привязанная к выбранной теме. */
    public function pendingPosts(array $f, int $page, int $perPage, int $viewerId, bool $seeAllDrafts): array
    {
        $page=max(1,$page); $perPage=max(5,min(50,$perPage)); $offset=($page-1)*$perPage;
        $where=["COALESCE(p.status,'published')='pending'"]; $params=[];
        if ($f['q'] !== '') { $like='%'.$f['q'].'%'; $where[]='(p.content LIKE ? OR u.username LIKE ? OR t.title LIKE ?)'; array_push($params,$like,$like,$like); }
        if (!$seeAllDrafts) { $where[]="(COALESCE(t.status,'published') <> 'draft' OR t.user_id=?)"; $params[]=$viewerId; }
        $w=implode(' AND ',$where);
        $c=$this->findOne("SELECT COUNT(*) cnt FROM posts p JOIN users u ON u.id=p.user_id JOIN threads t ON t.id=p.thread_id WHERE $w",$params);
        $total=(int)($c['cnt']??0); $pages=max(1,(int)ceil($total/$perPage));
        $items=$this->findMany("SELECT p.id,p.thread_id,p.user_id,p.content,p.status,p.created_at,u.username,t.title thread_title,
            (SELECT COUNT(*) FROM reports r WHERE r.target_type='post' AND r.target_id=p.id AND r.status='pending') pending_reports
            FROM posts p JOIN users u ON u.id=p.user_id JOIN threads t ON t.id=p.thread_id
            WHERE $w ORDER BY p.created_at ASC,p.id ASC LIMIT $perPage OFFSET $offset",$params);
        return compact('items','total','page','pages');
    }

    public function postAuthorId(int $postId): int {
        $row=$this->findOne("SELECT user_id FROM posts WHERE id=? LIMIT 1",[$postId]);
        return (int)($row['user_id']??0);
    }

    public function threadAuthorId(int $threadId): int {
        $row=$this->findOne("SELECT user_id FROM threads WHERE id=? LIMIT 1",[$threadId]);
        return (int)($row['user_id']??0);
    }

    public function postBelongsToThread(int $postId, int $threadId): bool {
        return (bool)$this->findOne('SELECT id FROM posts WHERE id=? AND thread_id=? LIMIT 1',[$postId,$threadId]);
    }

    public function pendingPostsCount(): int {
        $r=$this->findOne("SELECT COUNT(*) cnt FROM posts WHERE COALESCE(status,'published')='pending'");
        return (int)($r['cnt']??0);
    }

    public function thread(int $id): ?array {
        return $this->findOne("SELECT t.*,u.username author_name,c.title category_title FROM threads t JOIN users u ON u.id=t.user_id LEFT JOIN categories c ON c.id=t.category_id WHERE t.id=? LIMIT 1",[$id]);
    }

    public function posts(int $threadId, array $f, int $page, int $perPage, int $viewerId, bool $seeAllDrafts, bool $seeHidden = false): array {
        $page=max(1,$page); $perPage=max(5,min(50,$perPage)); $offset=($page-1)*$perPage;
        $where=['p.thread_id=?']; $params=[$threadId];
        if ($f['post_status']!=='') {
            $where[]="COALESCE(p.status, 'published')=?";
            $params[]=$f['post_status'];
        } elseif (!$seeHidden) {
            // По умолчанию скрытые сообщения не показываем, пока нет права view_hidden
            $where[]="COALESCE(p.status,'published') <> 'hidden'";
        }
        if (!$seeAllDrafts) { $where[]="(COALESCE(p.status,'published') <> 'draft' OR p.user_id=?)"; $params[]=$viewerId; }
        if ($f['q'] !== '') { $where[]='(p.content LIKE ? OR p.user_id IN (SELECT id FROM users WHERE username LIKE ?))'; $like='%'.$f['q'].'%'; $params[]=$like; $params[]=$like; }
        if ($f['reported']==='1') $where[]="EXISTS(SELECT 1 FROM reports r WHERE r.target_type='post' AND r.target_id=p.id AND r.status='pending')";
        $w=implode(' AND ',$where);
        $c=$this->findOne("SELECT COUNT(*) cnt FROM posts p WHERE $w",$params);
        $total=(int)($c['cnt']??0); $pages=max(1,(int)ceil($total/$perPage));
        $items=$this->findMany(
            "SELECT p.*,u.username,u.avatar,u.role_id,
                    rp.content reply_to_content,ru.username reply_to_username,
                    (SELECT COUNT(*) FROM reports r WHERE r.target_type='post' AND r.target_id=p.id AND r.status='pending') pending_reports
             FROM posts p JOIN users u ON u.id=p.user_id
             LEFT JOIN posts rp ON rp.id=p.reply_to_post_id LEFT JOIN users ru ON ru.id=rp.user_id
             WHERE $w ORDER BY p.created_at ASC,p.id ASC LIMIT $perPage OFFSET $offset",$params
        );
        foreach($items as &$p) {
            $p['attachments']=$this->findMany("SELECT id,original_name,path,size,mime_type FROM attachments WHERE target_type='post' AND target_id=? ORDER BY id ASC",[(int)$p['id']]);
        } unset($p);
        return compact('items','total','page','pages');
    }

    public function categories(): array {
        $rows=$this->findMany("SELECT id,title FROM categories ORDER BY title");
        $out=[]; foreach($rows as $r)$out[(int)$r['id']]=$r['title']; return $out;
    }
    public function setPostStatus(int $id,string $status): void {
        $this->execute("UPDATE posts SET status=?,updated_at=NOW() WHERE id=?",[$status,$id]);
    }
    public function setThreadStatus(int $id,string $status): void {
        $this->execute("UPDATE threads SET status=?,updated_at=NOW() WHERE id=?",[$status,$id]);
    }
    public function setPostHidden(int $id, bool $hidden): void {
        // Скрытие через статус 'hidden' — контент сохраняется, миграция не нужна.
        $this->setPostStatus($id, $hidden ? 'hidden' : 'published');
    }
    public function reportsForPost(int $postId): array {
        return $this->findMany("SELECT r.*,u.username reporter_name FROM reports r JOIN users u ON u.id=r.reporter_id WHERE r.target_type='post' AND r.target_id=? ORDER BY r.created_at DESC LIMIT 20",[$postId]);
    }
    public function resolveReport(int $id,int $actor,string $status): void {
        $this->execute("UPDATE reports SET status=?,resolved_by=?,resolved_at=NOW() WHERE id=?",[$status,$actor,$id]);
    }
    public function setPostContent(int $id,string $content,string $status='published'): void {
        $this->execute("UPDATE posts SET content=?,content_html=?,content_blocks=?,status=?,updated_at=NOW() WHERE id=?",[$content,nl2br(htmlspecialchars($content,ENT_QUOTES,'UTF-8')),json_encode([['type'=>'text','md'=>$content]],JSON_UNESCAPED_UNICODE),$status,$id]);
    }
    /**
     * Скрытие сообщения (не физическое удаление).
     * Контент остаётся в БД. Статус = 'hidden'.
     * Модераторы с правом discussions.view_hidden видят такие сообщения и могут восстановить.
     */
    public function markPostDeleted(int $id): void {
        $this->execute("UPDATE posts SET status='hidden', updated_at=NOW() WHERE id=?", [$id]);
    }

    /** Восстановить скрытое сообщение. */
    public function restorePost(int $id): void {
        $this->execute("UPDATE posts SET status='published', updated_at=NOW() WHERE id=? AND status='hidden'", [$id]);
    }
    public function setThreadLock(int $id,bool $locked): void {
        $this->execute("UPDATE threads SET is_locked=?,updated_at=NOW() WHERE id=?",[$locked?1:0,$id]);
    }
    public function setThreadHidden(int $id,bool $hidden): void {
        $this->execute("UPDATE threads SET is_hidden=?,updated_at=NOW() WHERE id=?",[$hidden?1:0,$id]);
    }
}
