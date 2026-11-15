<?php
$threads=$threads??['items'=>[],'total'=>0,'page'=>1,'pages'=>1];
$selected=$selected??null;$posts=$posts??['items'=>[],'total'=>0,'page'=>1,'pages'=>1];
$filters=$filters??[];$categories=$categories??[];$reports=$reports??[];$pendingQueue=$pendingQueue??['items'=>[],'total'=>0,'page'=>1,'pages'=>1];
$queueMode=$queueMode??false;$pendingCount=(int)($pendingCount??0);
$h=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
$badge=static function($s){$m=['draft'=>'Черновик','pending'=>'На проверке','published'=>'Опубликовано','rejected'=>'Отклонено'];return $m[$s]??$s;};
$threadStateLabels=static function($thread){$labels=[];if(!empty($thread['is_hidden']))$labels[]='Скрыта';if(!empty($thread['is_locked']))$labels[]='Закрыта';if(!empty($thread['is_sticky']))$labels[]='Закреплена';return $labels;};
$base=url('/moderation/discussions');
$clean=array_filter($filters,static fn($v)=>$v!==''&&$v!==null&&$v!==0);
$currentPage=max(1,(int)($_GET['page']??1));
$makeUrl=static function(array $changes=[])use($base,$clean,$selected,$queueMode,$currentPage){$p=array_merge($clean,['id'=>(int)($selected['id']??0)],$queueMode?['queue'=>'pending']:[],$currentPage>1?['page'=>$currentPage]:[],$changes);$p=array_filter($p,static fn($v)=>$v!==''&&$v!==null&&$v!==0);return $base.($p?'?'.http_build_query($p):'');};
?>
<main class="disc-wrap">
    <div class="container">
        <div class="page-header flex">
            <div>
                <h2>Обсуждения</h2>
                <p>Темы, ответы и очередь модерации сообщений</p>
            </div>
        </div>
    </div>
 <nav class="disc-toolbar" aria-label="Обсуждения">
  <a class="disc-mode <?= !$queueMode?'active':'' ?>" href="<?= $base ?>">Все темы</a>
  <a class="disc-mode disc-mode-pending <?= $queueMode?'active':'' ?>" href="<?= $base ?>?queue=pending"><span>На проверке</span><b><?= $pendingCount ?></b></a>
 </nav>
 <form class="disc-filters" method="get" action="<?= $base ?>">
  <?php if($queueMode): ?><input type="hidden" name="queue" value="pending"><?php endif ?>
  <label class="disc-search"><span>⌕</span><input name="q" placeholder="Поиск по тексту, автору или теме" value="<?= $h($filters['q']??'') ?>"></label>
  <?php if(!$queueMode): ?>
   <select name="category"><option value="0">Все разделы</option><?php foreach($categories as $id=>$name): ?><option value="<?= (int)$id ?>" <?= (int)($filters['category']??0)===(int)$id?'selected':'' ?>><?= $h($name) ?></option><?php endforeach ?></select>
   <select name="status"><option value="">Все статусы тем</option><?php foreach(['draft','pending','published','rejected'] as $s): ?><option value="<?= $s ?>" <?= ($filters['status']??'')===$s?'selected':'' ?>><?= $badge($s) ?></option><?php endforeach ?></select>
   <select name="locked"><option value="">Любое состояние</option><option value="0" <?= ($filters['locked']??'')==='0'?'selected':'' ?>>Открытые</option><option value="1" <?= ($filters['locked']??'')==='1'?'selected':'' ?>>Закрытые</option></select>
   <select name="hidden"><option value="">Видимость: любая</option><option value="0" <?= ($filters['hidden']??'')==='0'?'selected':'' ?>>Видимые</option><option value="1" <?= ($filters['hidden']??'')==='1'?'selected':'' ?>>Скрытые</option></select>
  <?php endif ?>
  <button class="admin-btn admin-btn-primary" type="submit">Найти</button><a class="admin-btn" href="<?= $queueMode?$base.'?queue=pending':$base ?>">Сбросить</a>
 </form>
 <div class="disc-layout <?= $selected?'has-selection':'' ?>">
  <aside class="disc-panel disc-sidebar">
   <div class="disc-panel-head"><strong><?= $queueMode?'Сообщения на проверке':'Темы обсуждений' ?></strong><span><?= (int)($queueMode?$pendingQueue['total']:$threads['total']) ?></span></div>
   <div class="disc-list">
    <?php if($queueMode): foreach($pendingQueue['items'] as $item): ?>
     <a class="disc-list-item" href="<?= $makeUrl(['id'=>(int)$item['thread_id'],'post_status'=>'pending','focus_post'=>(int)$item['id']]) ?>">
      <div class="disc-list-line"><strong><?= $h($item['username']) ?></strong><em class="disc-pill pending">На проверке</em></div>
      <span class="disc-list-title"><?= $h(mb_substr($item['content'],0,100)) ?></span>
      <small><?= $h($item['thread_title']) ?> · #<?= (int)$item['id'] ?> · <?= $h($item['created_at']) ?></small>
     </a>
    <?php endforeach; if(!$pendingQueue['items']): ?><p class="disc-empty">Новых сообщений на проверке нет.</p><?php endif; else: foreach($threads['items'] as $t): ?>
     <a class="disc-list-item <?= $selected&&(int)$selected['id']===(int)$t['id']?'active':'' ?>" href="<?= $makeUrl(['id'=>(int)$t['id']]) ?>">
      <div class="disc-list-line"><strong><?= $h($t['title']) ?></strong><em class="disc-pill <?= $h($t['status']) ?>"><?= $h($badge($t['status'])) ?></em></div>
      <?php if($stateLabels=$threadStateLabels($t)): ?><div class="disc-state-labels"><?php foreach($stateLabels as $stateLabel): ?><em class="disc-state-label"><?= $h($stateLabel) ?></em><?php endforeach ?></div><?php endif ?>
      <small><?= $h($t['category_title']??'Без раздела') ?> · <?= $h($t['author_name']) ?></small><small><?= (int)$t['posts_count'] ?> сообщений · <?= (int)$t['reports_count'] ?> жалоб</small>
     </a>
    <?php endforeach; if(!$threads['items']): ?><p class="disc-empty">Темы не найдены.</p><?php endif; endif ?>
   </div>
   <?php $pager=$queueMode?$pendingQueue:$threads; if($pager['pages']>1): ?><div class="disc-pagination"><?php for($i=1;$i<=$pager['pages'];$i++): ?><a class="<?= $i===$pager['page']?'active':'' ?>" href="<?= $makeUrl(['page'=>$i]) ?>"><?= $i ?></a><?php endfor ?></div><?php endif ?>
  </aside>
  <section class="disc-conversation">
   <?php if(!$selected): ?><div class="disc-panel disc-placeholder"><strong><?= $queueMode?'Выберите сообщение':'Выберите тему' ?></strong><p><?= $queueMode?'Откройте сообщение из общей очереди проверки.':'Сообщения загружаются только для выбранной темы.' ?></p></div>
   <?php else: ?>
    <div class="disc-panel disc-topic-head"><div class="disc-topic-title"><div><h2><?= $h($selected['title']) ?></h2><p>Тема #<?= (int)$selected['id'] ?> · <?= $h($selected['category_title']??'') ?> · <?= $h($selected['author_name']) ?></p></div><div class="disc-topic-badges"><em class="disc-pill <?= $h($selected['status']) ?>"><?= $h($badge($selected['status'])) ?></em><?php foreach($threadStateLabels($selected) as $stateLabel): ?><em class="disc-state-label"><?= $h($stateLabel) ?></em><?php endforeach ?></div></div>
     <div class="disc-actions">
      <?php if($canEditThread??false): ?><a class="admin-btn" href="<?= url('/moderation/topics/edit/'.(int)$selected['id']) ?>">Редактировать тему</a><?php endif ?>
      <?php if($canLock??false): ?><form method="post" action="<?= url('/moderation/discussions/thread/lock/'.(int)$selected['id']) ?>"><input type="hidden" name="locked" value="<?= empty($selected['is_locked'])?'1':'0' ?>"><button class="admin-btn" type="submit"><?= empty($selected['is_locked'])?'Закрыть':'Открыть' ?> тему</button></form><?php endif ?>
      <?php if($canHide??false): ?><form method="post" action="<?= url('/moderation/discussions/thread/hide/'.(int)$selected['id']) ?>"><input type="hidden" name="hidden" value="<?= empty($selected['is_hidden'])?'1':'0' ?>"><button class="admin-btn" type="submit"><?= empty($selected['is_hidden'])?'Скрыть':'Восстановить' ?> тему</button></form><?php endif ?>
     </div>
    </div>
    <div class="disc-panel disc-posts-panel"><div class="disc-panel-head"><strong>Сообщения</strong><span><?= (int)$posts['total'] ?> · <?= (int)$posts['page'] ?>/<?= (int)$posts['pages'] ?></span></div>
     <form class="disc-post-filter" method="get" action="<?= $base ?>"><input type="hidden" name="id" value="<?= (int)$selected['id'] ?>"><?php if($queueMode): ?><input type="hidden" name="queue" value="pending"><?php endif ?><select name="post_status"><option value="">Все статусы сообщений</option><?php foreach(['draft','pending','published','rejected'] as $s): ?><option value="<?= $s ?>" <?= ($filters['post_status']??'')===$s?'selected':'' ?>><?= $badge($s) ?></option><?php endforeach ?></select><label><input type="checkbox" name="reported" value="1" <?= ($filters['reported']??'')==='1'?'checked':'' ?>> С жалобами</label><button class="admin-btn" type="submit">Применить</button></form>
     <?php foreach($posts['items'] as $p): ?>
      <article class="disc-post" id="post-<?= (int)$p['id'] ?>">
       <div class="disc-post-meta"><div><strong><?= $h($p['username']) ?></strong><small><?= $h($p['created_at']) ?> · #<?= (int)$p['id'] ?></small></div><em class="disc-pill <?= $h($p['status']) ?>"><?= $h($badge($p['status'])) ?></em></div>
       <?php if(!empty($p['reply_to_content'])): ?><blockquote class="disc-quote"><b>Ответ <?= $h($p['reply_to_username']) ?></b><span><?= $h(mb_substr(strip_tags($p['reply_to_content']),0,220)) ?></span></blockquote><?php endif ?>
       <p class="disc-message"><?= $h($p['content']??'') ?></p>
       <?php if(!empty($p['attachments'])): ?><div class="disc-attachments"><b>Вложения:</b><?php foreach($p['attachments'] as $a): ?><a href="<?= url('/attachments/'.(int)$a['id'].'/download') ?>"><?= $h($a['original_name']) ?></a><?php endforeach ?></div><?php endif ?>
       <div class="disc-actions">
        <?php if($canReply??false): ?><button type="button" class="admin-btn disc-reply-trigger" data-post-id="<?= (int)$p['id'] ?>" data-author="<?= $h($p['username']) ?>" data-content="<?= $h(mb_substr(strip_tags($p['content']),0,220)) ?>">Ответить</button><?php endif ?>
        <?php if(!empty($p['can_edit_by_actor'])): ?>
         <details class="disc-edit-form"><summary class="admin-btn">Редактировать сообщение</summary>
          <form method="post" action="<?= url('/moderation/discussions/post-edit/'.(int)$p['id']) ?>">
           <input type="hidden" name="thread_id" value="<?= (int)$selected['id'] ?>">
           <textarea name="content" required><?= $h($p['content']??'') ?></textarea>
           <button class="admin-btn admin-btn-primary" type="submit">Сохранить изменения</button>
          </form>
         </details>
        <?php endif ?>
        <?php if(($canModerate??false) && !empty($p['can_moderate_by_actor'])): ?><form method="post" action="<?= url('/moderation/discussions/post-status/'.(int)$p['id']) ?>"><input type="hidden" name="thread_id" value="<?= (int)$selected['id'] ?>"><select name="status"><?php foreach(['published','pending','rejected','draft'] as $s): ?><option value="<?= $s ?>" <?= $p['status']===$s?'selected':'' ?>><?= $badge($s) ?></option><?php endforeach ?></select><button class="admin-btn" type="submit">Сохранить статус</button></form><?php endif ?>
        <?php if(!empty($p['can_delete_by_actor'])): ?>
         <form method="post" action="<?= url('/moderation/discussions/post-delete/'.(int)$p['id']) ?>" onsubmit="return confirm('Удалить это сообщение?')">
          <input type="hidden" name="thread_id" value="<?= (int)$selected['id'] ?>">
          <button class="admin-btn admin-btn-danger" type="submit">Удалить сообщение</button>
         </form>
        <?php endif ?>
        <?php if(!empty($p['can_punish_by_actor'])): ?>
         <details class="disc-punish-details">
          <summary class="admin-btn admin-btn-danger">Выдать наказание автору</summary>
          <form class="disc-punish-form" method="post" action="<?= url('/moderation/discussions/punish-post/'.(int)$p['id']) ?>">
           <input type="hidden" name="thread_id" value="<?= (int)$selected['id'] ?>">
           <input type="hidden" name="page" value="<?= (int)($_GET['page']??1) ?>">
           <?php if($queueMode): ?><input type="hidden" name="queue" value="pending"><?php endif ?>
           <label>Тип наказания
            <select name="type" required>
             <?php if(!empty($p['can_ban_author'])): ?><option value="ban">Бан</option><?php endif ?>
             <?php if(!empty($p['can_mute_author'])): ?><option value="mute">Мут</option><?php endif ?>
             <?php if(!empty($p['can_restrict_author'])): ?><option value="restrict">Ограничение</option><?php endif ?>
            </select>
           </label>
           <label>Причина <input type="text" name="reason" required maxlength="500" placeholder="Причина наказания"></label>
           <label>Действует до <input type="datetime-local" name="expires_at"></label>
           <?php if(!empty($p['can_reject_with_punishment'])): ?>
            <label class="disc-punish-reject"><input type="checkbox" name="reject_post" value="1" <?= ($p['status']??'')==='pending'?'checked':'' ?>> Одновременно отклонить сообщение</label>
           <?php endif ?>
           <button class="admin-btn admin-btn-danger" type="submit" onclick="return confirm('Выдать наказание автору сообщения?')">Применить наказание</button>
          </form>
         </details>
        <?php endif ?>
        <?php if(($canResolve??false)&&(int)$p['pending_reports']>0): ?><a class="admin-btn" href="<?= $makeUrl(['post_id'=>(int)$p['id']]) ?>">Жалобы (<?= (int)$p['pending_reports'] ?>)</a><?php endif ?>
       </div>
       <?php if(!empty($_GET['post_id'])&&(int)$_GET['post_id']===(int)$p['id']): foreach($reports as $r): ?><div class="disc-report"><p>Жалоба #<?= (int)$r['id'] ?> · <?= $h($r['reporter_name']) ?> · <?= $h($r['reason']) ?> · <?= $h($r['description']) ?></p><?php if($canResolve??false): ?><form method="post" action="<?= url('/moderation/discussions/report/'.(int)$r['id']) ?>"><input type="hidden" name="thread_id" value="<?= (int)$selected['id'] ?>"><button class="admin-btn admin-btn-primary" name="status" value="resolved">Рассмотрено</button><button class="admin-btn" name="status" value="rejected">Отклонить</button></form><?php endif ?></div><?php endforeach; if($history): ?><div class="disc-history"><b>История действий</b><?php foreach($history as $log): ?><p><?= $h($log['created_at']) ?> · <?= $h($log['actor_name']??'Система') ?> · <?= $h($log['action_label']??$log['action']) ?></p><?php endforeach ?></div><?php endif; endif ?>
      </article>
     <?php endforeach ?>
     <?php if(!$posts['items']): ?><p class="disc-empty">Сообщений по выбранным фильтрам нет.</p><?php endif ?>
     <?php if($posts['pages']>1): ?><div class="disc-pagination"><?php for($i=1;$i<=$posts['pages'];$i++): ?><a class="<?= $i===$posts['page']?'active':'' ?>" href="<?= $makeUrl(['post_page'=>$i]) ?>"><?= $i ?></a><?php endfor ?></div><?php endif ?>
    </div>
   <?php endif ?>
  </section>
  <?php if($selected && ($canReply??false)): ?>
  <aside class="disc-panel disc-compose-panel" id="disc-composer"><div class="disc-panel-head"><strong>Добавить ответ</strong><span>Новая публикация</span></div>
   <form method="post" enctype="multipart/form-data" action="<?= url('/moderation/discussions/reply') ?>" class="disc-compose-form">
    <input type="hidden" name="thread_id" value="<?= (int)$selected['id'] ?>"><input type="hidden" name="reply_to_post_id" id="disc-reply-to" value="">
    <div class="disc-reply-context" id="disc-reply-context" hidden><div><b id="disc-reply-author"></b><p id="disc-reply-text"></p></div><button type="button" id="disc-reply-clear" aria-label="Убрать цитирование">×</button></div>
    <textarea name="content" id="disc-reply-content" placeholder="Напишите ответ..." required></textarea>
    <div class="disc-compose-hint">Сообщение проходит серверную автопроверку ссылок и стоп-слов.</div>
    <?php if($canAttach??false): ?><label class="disc-file-picker">📎 <span>Прикрепить файлы</span><input type="file" name="files[]" multiple accept=".png,.jpg,.jpeg,.gif,.webp,.pdf,.zip,.rar,.7z,.doc,.docx,.txt"></label><?php endif ?>
    <label class="disc-draft-check"><input type="checkbox" name="draft" value="1"> Сохранить как черновик</label>
    <button class="admin-btn admin-btn-primary disc-send" type="submit">Отправить ответ</button>
   </form>
  </aside>
  <?php endif ?>
 </div>
</main>
<script>
(()=>{const id=document.getElementById('disc-reply-to'),ctx=document.getElementById('disc-reply-context'),author=document.getElementById('disc-reply-author'),quote=document.getElementById('disc-reply-text'),editor=document.getElementById('disc-reply-content');if(!id)return;document.querySelectorAll('.disc-reply-trigger').forEach(btn=>btn.addEventListener('click',()=>{id.value=btn.dataset.postId||'';author.textContent='Ответ пользователю '+(btn.dataset.author||'');quote.textContent=btn.dataset.content||'';ctx.hidden=false;editor.focus();}));document.getElementById('disc-reply-clear')?.addEventListener('click',()=>{id.value='';ctx.hidden=true;});const focus=new URLSearchParams(location.search).get('focus_post');if(focus){document.getElementById('post-'+focus)?.scrollIntoView({block:'center'});}
})();
</script>
