<?php
namespace App\Controllers;

use Core\Controller;
use App\Services\AuthService;
use App\Services\ContentModerationService;
use App\Services\AttachmentService;
use App\Models\DiscussionAdmin;
use App\Models\Post;
use App\Services\ActivityLogService;

class DiscussionController extends Controller
{
    private DiscussionAdmin $db;
    public function __construct(){ $this->db=new DiscussionAdmin(); }

    public function index(array $params=[]){
        $this->guard('discussions.view_mod');
        $user=AuthService::user(); $uid=(int)$user['id'];
        $seeDrafts=AuthService::can('discussions.view_drafts');
        $f=[
            'q'=>trim((string)($_GET['q']??'')),
            'category'=>(int)($_GET['category']??0),
            'status'=>in_array($_GET['status']??'', ['','draft','pending','published','rejected'],true)?(string)($_GET['status']??''):'',
            'locked'=>in_array($_GET['locked']??'', ['','0','1'],true)?(string)($_GET['locked']??''):'',
            'hidden'=>in_array($_GET['hidden']??'', ['','0','1'],true)?(string)($_GET['hidden']??''):'',
            'post_status'=>in_array($_GET['post_status']??'', ['','draft','pending','published','rejected','hidden'],true)?(string)($_GET['post_status']??''):'',
            'reported'=>($_GET['reported']??'')==='1'?'1':'',
        ];
        $page=max(1,(int)($_GET['page']??1));
        $queueMode=(($_GET['queue']??'')==='pending');
        $pendingCount=$this->db->pendingPostsCount();
        $threads=$queueMode?['items'=>[],'total'=>0,'page'=>1,'pages'=>1]:$this->db->threads($f,$page,12,$uid,$seeDrafts);
        $pendingQueue=$queueMode?$this->db->pendingPosts($f,$page,15,$uid,$seeDrafts):['items'=>[],'total'=>0,'page'=>1,'pages'=>1];
        $selectedId=max(0,(int)($_GET['id']??0));
        $selected=$selectedId?$this->db->thread($selectedId):null;
        if($selected && ($selected['status']??'')==='draft' && !$seeDrafts && (int)$selected['user_id']!==$uid) $selected=null;
        $posts=['items'=>[],'total'=>0,'page'=>1,'pages'=>1];
        $history=[]; $reportHistory=[];
        $hierarchy = new \App\Services\RoleHierarchyService();
        $actorId = (int)$uid;
        $isAdmin = AuthService::isAdmin();
        // Право видеть скрытые сообщения: админ или явное право, или любое право на удаление/модерацию
        $seeHidden = $isAdmin
            || AuthService::can('discussions.view_hidden')
            || AuthService::can('delete_any_post')
            || AuthService::can('discussions.moderate');

        if ($selected) {
            $posts = $this->db->posts($selectedId, $f, max(1, (int)($_GET['post_page'] ?? 1)), 10, $uid, $seeDrafts, $seeHidden);
            $history = (new \App\Models\ActivityLog())->getHistoryForTarget('post', isset($_GET['post_id']) ? (int)$_GET['post_id'] : null);
            if (!empty($_GET['post_id'])) {
                $reportHistory = $this->db->reportsForPost((int)$_GET['post_id']);
                $history = (new \App\Models\ActivityLog())->getHistoryForTarget('post', (int)$_GET['post_id']);
            }
        }
        foreach ($posts['items'] as &$postRow) {
            $isOwn = (int)$postRow['user_id'] === $actorId;
            $rankCheck = $hierarchy->canActOnUser($actorId, (int)$postRow['user_id']);
            $rankAllowed = $isOwn || !empty($rankCheck['ok']);
            $isHidden = ($postRow['status'] ?? '') === 'hidden';

            $postRow['can_edit_by_actor'] = $isOwn
                ? (AuthService::can('edit_own_post') || AuthService::can('discussions.edit_any'))
                : (AuthService::can('discussions.edit_any') && $rankAllowed);

            $postRow['can_delete_by_actor'] = !$isHidden && (
                $isOwn
                    ? AuthService::can('delete_own_post')
                    : (AuthService::can('delete_any_post') && $rankAllowed)
            );
            $postRow['can_restore_by_actor'] = $isHidden && (
                $isOwn
                    ? AuthService::can('delete_own_post')
                    : ((AuthService::can('delete_any_post') || AuthService::can('discussions.view_hidden')) && $rankAllowed)
            );

            $postRow['can_moderate_by_actor'] = !$isOwn && $rankAllowed;

            // Наказание: не своё сообщение + иерархия + (админ или право на бан/мут/restrict)
            // AuthService::can() для админа всегда true, поэтому отдельные проверки permissions сработают
            $postRow['can_punish_by_actor'] = !$isOwn && $rankAllowed && (
                AuthService::can('players.ban')
                || AuthService::can('players.mute')
                || AuthService::can('players.restrict')
            );
            $postRow['can_ban_author']      = AuthService::can('players.ban');
            $postRow['can_mute_author']     = AuthService::can('players.mute');
            $postRow['can_restrict_author'] = AuthService::can('players.restrict');
            $postRow['can_reject_with_punishment'] = AuthService::can('discussions.moderate');
        }
        unset($postRow);
        $threadRankAllowed=$selected ? $hierarchy->canActOnUser($actorId,(int)$selected['user_id'])['ok'] : false;
        $this->title='Обсуждения | Модерация';
        // Эта страница использует отдельный контроллер, поэтому сама выводит
        // общую навигацию модерации и подсвечивает активную вкладку.
        $allTabs = [
            ['id'=>'players','title'=>'Игроки','href'=>url('/moderation/players'),'permission'=>'players.view'],
            ['id'=>'roles','title'=>'Роли','href'=>url('/moderation/roles'),'permission'=>'manage_roles'],
            ['id'=>'complaints','title'=>'Жалобы','href'=>url('/moderation/complaints'),'permission'=>'resolve_report'],
            ['id'=>'reports','title'=>'Репорты','href'=>url('/moderation/reports'),'permission'=>'resolve_report'],
            ['id'=>'applications','title'=>'Заявки','href'=>url('/moderation/applications'),'permission'=>'applications.view'],
            ['id'=>'sections','title'=>'Разделы','href'=>url('/moderation/sections'),'permission'=>'manage_categories'],
            ['id'=>'topics','title'=>'Темы','href'=>url('/moderation/topics'),'permission'=>'threads.view_mod'],
            ['id'=>'discussions','title'=>'Обсуждения','href'=>url('/moderation/discussions'),'permission'=>'discussions.view_mod'],
            ['id'=>'logs','title'=>'Логи','href'=>url('/moderation/logs'),'permission'=>'view_logs'],
        ];
        $tabs = array_values(array_filter($allTabs, static function($tab) {
            return empty($tab['permission']) || AuthService::can($tab['permission']);
        }));
        return $this->render('moderation/discussions',[
            'threads'=>$threads,'selected'=>$selected,'posts'=>$posts,'filters'=>$f,'tabs'=>$tabs,'currentTab'=>'discussions',
            'queueMode'=>$queueMode,'pendingQueue'=>$pendingQueue,'pendingCount'=>$pendingCount,
            'categories'=>$this->db->categories(),'user'=>$user,'canDrafts'=>$seeDrafts,
            'canModerate'=>AuthService::can('discussions.moderate'),
            'canReply'=>AuthService::can('discussions.reply'),
            'canEdit'=>AuthService::can('discussions.edit_any'),
            'canAttach'=>AuthService::can('posts.attach'),
            'canEditThread'=>AuthService::can('threads.edit_any'),
            'canLock'=>AuthService::can('discussions.lock') && $threadRankAllowed,
            'canHide'=>AuthService::can('discussions.hide') && $threadRankAllowed,
            'canCreateThread'=>AuthService::can('create_thread'),
            'canResolve'=>AuthService::can('resolve_report'),
            'reports'=>$reportHistory,'history'=>$history,
        ]);
    }

    public function reply(array $params=[]){
        $this->guard('discussions.reply');
        if($_SERVER['REQUEST_METHOD']!=='POST') $this->back();
        $threadId=(int)($_POST['thread_id']??0); $thread=$this->db->thread($threadId);
        if(!$thread) $this->back($threadId);
        $text=trim((string)($_POST['content']??''));
        if($text==='') $this->back($threadId,'empty');
        if(!empty($thread['is_locked'])) $this->back($threadId,'locked');
        $check=(new ContentModerationService())->checkText($text);
        $bypass=AuthService::can('discussions.publish')||AuthService::hasRole('admin');
        $status=$bypass?'published':(!$check['ok']?'pending':(!empty($_POST['draft'])?'draft':'published'));
        if(!empty($_POST['draft'])) $status='draft';
        $uid=(int)AuthService::user()['id'];
        // Вложения отправляются тем же POST, поэтому нельзя полагаться только
        // на скрытие file input в шаблоне.
        if (!empty($_FILES['files']['name'][0]) && !AuthService::can('posts.attach')) {
            http_response_code(403);
            exit('Forbidden: attachment permission required');
        }
        $replyTo=max(0,(int)($_POST['reply_to_post_id']??0));
        if($replyTo>0){ if(!$this->db->postBelongsToThread($replyTo,$threadId)) $replyTo=0; }
        $blocks=[['type'=>'text','md'=>$text]];
        $postId=(new Post())->createPost($threadId,$uid,$text,$replyTo?:null,$blocks,nl2br(htmlspecialchars($text,ENT_QUOTES,'UTF-8')),$status,false);
        // Вложения сохраняются только после создания поста и сразу привязываются к нему.
        if(!empty($_FILES['files']['name'][0])){
            $svc=new AttachmentService();
            foreach($_FILES['files']['name'] as $i=>$name){
                $file=['name'=>$name,'type'=>$_FILES['files']['type'][$i]??'','tmp_name'=>$_FILES['files']['tmp_name'][$i]??'','error'=>$_FILES['files']['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$_FILES['files']['size'][$i]??0];
                $svc->store($file,['target_type'=>'post','target_id'=>$postId,'user_id'=>$uid,'accept'=>['png','jpg','jpeg','gif','webp','pdf','zip','rar','7z','doc','docx','txt'],'max_size'=>10*1024*1024]);
            }
        }
        ActivityLogService::log($uid,'created_discussion_post','success','post',$postId,mb_substr($text,0,180),['status'=>$status,'thread_id'=>$threadId,'auto_check'=>$check['reasons']]);
        $this->back($threadId,$status==='pending'?'pending':'saved');
    }

    public function postStatus(array $params=[]){
        $this->guard('discussions.moderate'); $id=(int)($params['id']??0); $this->guardTargetPost($id); $status=(string)($_POST['status']??'published');
        if($_SERVER['REQUEST_METHOD']==='POST' && in_array($status,['published','pending','rejected','draft','hidden'],true)){
            $this->db->setPostStatus($id,$status);
            ActivityLogService::log((int)AuthService::user()['id'],'moderated_post','success','post',$id,null,['status'=>$status]);
        }
        $this->back((int)($_POST['thread_id']??0));
    }
    public function editPost(array $params=[]){
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !AuthService::check()) { $this->back(); }
        $id=(int)($params['id']??0);
        $owner=$this->db->postAuthorId($id);
        $actor=(int)AuthService::user()['id'];
        $own=$owner===$actor;
        $allowed=$own
            ? (AuthService::can('edit_own_post') || AuthService::can('discussions.edit_any'))
            : (AuthService::can('discussions.edit_any') && (new \App\Services\RoleHierarchyService())->canActOnUser($actor,$owner)['ok']);
        if($id<=0 || !$allowed){http_response_code(403);exit('Forbidden: edit permission required');}
        $text=trim((string)($_POST['content']??''));
        if($text!=='') {
            $check=(new ContentModerationService())->checkText($text);
            $status=(AuthService::can('discussions.publish')||AuthService::hasRole('admin')||$check['ok'])?'published':'pending';
            $this->db->setPostContent($id,$text,$status);
            ActivityLogService::log($actor,'edited_post','success','post',$id,mb_substr($text,0,180),['status'=>$status,'own'=>$own]);
        }
        $this->back((int)($_POST['thread_id']??0));
    }

    public function deletePost(array $params=[]){
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !AuthService::check()) { $this->back(); }
        $id = (int)($params['id'] ?? 0);
        $owner = $this->db->postAuthorId($id);
        $actor = (int)AuthService::user()['id'];
        $own = $owner === $actor;
        $hierarchy = new \App\Services\RoleHierarchyService();
        $isAdmin = $hierarchy->isAdminUser($actor) || AuthService::hasRole('admin');
        $allowed = $own
            ? AuthService::can('delete_own_post')
            : ((AuthService::can('delete_any_post') || $isAdmin) && $hierarchy->canActOnUser($actor, $owner)['ok']);
        if ($id <= 0 || !$allowed) {
            http_response_code(403);
            exit('Forbidden: delete permission required');
        }
        $restore = !empty($_POST['restore']);
        if ($restore) {
            $this->db->restorePost($id);
            ActivityLogService::log($actor, 'restored_post', 'success', 'post', $id, null, ['own' => $own]);
            $this->back((int)($_POST['thread_id'] ?? 0), 'Сообщение восстановлено');
        } else {
            $this->db->markPostDeleted($id);
            ActivityLogService::log($actor, 'hidden_post', 'success', 'post', $id, null, ['own' => $own]);
            $this->back((int)($_POST['thread_id'] ?? 0), 'Сообщение скрыто');
        }
    }
    public function threadAction(array $params=[]){
        $id=(int)($params['id']??0); $action=(string)($params['action']??'');
        if($action==='lock'){$this->guard('discussions.lock');$this->guardTargetThread($id);$locked=!empty($_POST['locked']);$this->db->setThreadLock($id,$locked);ActivityLogService::log((int)AuthService::user()['id'],$locked?'locked_thread':'unlocked_thread','success','thread',$id,null,['is_locked'=>$locked]);}
        elseif($action==='hide'){$this->guard('discussions.hide');$this->guardTargetThread($id);$hidden=!empty($_POST['hidden']);$this->db->setThreadHidden($id,$hidden);ActivityLogService::log((int)AuthService::user()['id'],$hidden?'hidden_thread':'restored_thread','success','thread',$id,null,['is_hidden'=>$hidden]);}
        elseif($action==='status'){$this->guard('discussions.moderate');$this->guardTargetThread($id);$s=(string)($_POST['status']??'published');if(in_array($s,['draft','pending','published','rejected'],true)){$this->db->setThreadStatus($id,$s);ActivityLogService::log((int)AuthService::user()['id'],'moderated_thread','success','thread',$id,null,['status'=>$s]);}}
        $this->back($id);
    }
    /** Выдать наказание автору сообщения; при наличии права можно одновременно отклонить пост. */
    public function punishPostAuthor(array $params=[]){
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !AuthService::check()) {
            http_response_code(405); exit('Method not allowed');
        }
        $postId=(int)($params['id']??0);
        $threadId=(int)($_POST['thread_id']??0);
        $owner=$this->db->postAuthorId($postId);
        $actorId=(int)(AuthService::user()['id']??0);
        $returnPage=max(1,(int)($_POST['page']??1));
        $queue=(($_POST['queue']??'')==='pending');
        $willReject=!empty($_POST['reject_post']);
        $redirect=url('/moderation/discussions').'?'.http_build_query(array_filter([
            'id'=>$threadId,'page'=>$returnPage>1?$returnPage:null,
            'queue'=>$queue?'pending':null,'post_status'=>($queue&&!$willReject)?'pending':null,
            'focus_post'=>$postId,'notice'=>null
        ],static fn($v)=>$v!==null&&$v!==''));
        $fail=static function(string $message) use ($redirect,$postId): void {
            $sep=str_contains($redirect,'?')?'&':'?';
            header('Location: '.$redirect.$sep.'error='.rawurlencode($message).'#post-'.$postId);
            exit;
        };
        if($postId<=0 || $threadId<=0 || $owner<=0 || !$this->db->postBelongsToThread($postId,$threadId)){
            http_response_code(404); exit('Post not found');
        }
        $type=(string)($_POST['type']??'');
        $permission=match($type){'ban'=>'players.ban','mute'=>'players.mute','restrict'=>'players.restrict',default=>''};
        if($permission==='' || (!AuthService::can($permission)&&!AuthService::hasRole('admin'))){
            http_response_code(403); exit('Forbidden: sanction permission required');
        }
        // Hierarchy service запрещает наказывать себя, равные и вышестоящие роли.
        $rank=(new \App\Services\RoleHierarchyService())->canActOnUser($actorId,$owner);
        if(empty($rank['ok'])) { $fail($rank['error']??'Недостаточно прав по иерархии ролей'); }
        $reason=trim((string)($_POST['reason']??''));
        if($reason==='') { $fail('Укажите причину наказания'); }
        $expires=trim((string)($_POST['expires_at']??''))?:null;
        if($expires!==null){
            $date=str_replace('T',' ',$expires);
            if(strlen($date)===16) $date.=':00';
            $parsed=\DateTime::createFromFormat('Y-m-d H:i:s',$date);
            if(!$parsed || $parsed->format('Y-m-d H:i:s')!==$date){ $fail('Некорректная дата окончания наказания'); }
            $expires=$date;
        }
        if(!empty($_POST['reject_post']) && !AuthService::can('discussions.moderate') && !AuthService::hasRole('admin')){
            http_response_code(403); exit('Forbidden: moderation permission required');
        }
        $result=(new \App\Services\PlayerService())->punish($owner,$type,$reason,$expires,$actorId);
        if(empty($result['ok'])) { $fail($result['errors']['rank']??$result['errors']['reason']??'Не удалось выдать наказание'); }
        if(!empty($_POST['reject_post'])){
            $this->guardTargetPost($postId);
            $this->db->setPostStatus($postId,'rejected');
            ActivityLogService::log($actorId,'moderated_post','success','post',$postId,null,[
                'status'=>'rejected','reason'=>'Отклонено одновременно с наказанием автора','sanction_type'=>$type
            ]);
        }
        $sep=str_contains($redirect,'?')?'&':'?';
        header('Location: '.$redirect.$sep.'notice='.rawurlencode('Наказание выдано'.(!empty($_POST['reject_post'])?' · сообщение отклонено':'' )).'#post-'.$postId);
        exit;
    }

    public function resolveReport(array $params=[]){
        $this->guard('resolve_report'); $id=(int)($params['id']??0);$status=(string)($_POST['status']??'resolved');
        if($_SERVER['REQUEST_METHOD']==='POST' && in_array($status,['resolved','rejected'],true)) {
            $this->db->resolveReport($id,(int)AuthService::user()['id'],$status);
            ActivityLogService::log((int)AuthService::user()['id'],'resolved_report','success','report',$id,null,['status'=>$status]);
        }
        $this->back((int)($_POST['thread_id']??0));
    }
    private function guardTargetPost(int $postId): void {
        $owner=$this->db->postAuthorId($postId);
        if($owner<=0 || !(new \App\Services\RoleHierarchyService())->canActOnUser((int)AuthService::user()['id'],$owner)['ok']) {
            http_response_code(403); exit('Forbidden: insufficient role hierarchy');
        }
    }
    private function guardTargetThread(int $threadId): void {
        $owner=$this->db->threadAuthorId($threadId);
        if($owner<=0 || !(new \App\Services\RoleHierarchyService())->canActOnUser((int)AuthService::user()['id'],$owner)['ok']) {
            http_response_code(403); exit('Forbidden: insufficient role hierarchy');
        }
    }
    private function guard(string $permission):void{
        if(!AuthService::check()||(!AuthService::can($permission)&&!AuthService::hasRole('admin'))){http_response_code(403);exit('Forbidden');}
    }
    private function back(int $id=0,string $msg=''):void{
        $url=url('/moderation/discussions').($id?'?id='.$id:'');
        if($msg!=='')$url.=($id?'&':'?').'notice='.rawurlencode($msg);
        header('Location: '.$url);exit;
    }
}
