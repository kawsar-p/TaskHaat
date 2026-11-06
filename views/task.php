<?php
$left=max(0,(int)$task['worker_slots']-(int)$task['claimed_slots']);
$fillPercent=(int)$task['worker_slots']>0?min(100,(int)round(((int)$task['claimed_slots']/(int)$task['worker_slots'])*100)):0;
$mine=$claim??null;$submission=null;$files=[];
$isPublisherOwner=$user&&(int)$user['id']===(int)$task['publisher_id'];
$isAdmin=$user&&has_role($user,'admin');
$canClaim=$task['status']==='approved'&&$left>0&&(!$task['deadline']||strtotime($task['deadline'])>=time())&&(!$isPublisherOwner)&&(!$isAdmin);
$coverImage=!empty($task['cover_image'])?'uploads/campaigns/'.rawurlencode((string)$task['cover_image']):null;
$gross=(float)$task['reward_per_worker'];$workerFee=(float)$task['worker_fee_rate'];$net=max(0,$gross*(1-$workerFee/100));
if($mine){
  $st=$pdo->prepare('SELECT * FROM submissions WHERE claim_id=?');$st->execute([(int)$mine['id']]);$submission=$st->fetch();
  if($submission){$st=$pdo->prepare('SELECT * FROM submission_files WHERE submission_id=?');$st->execute([(int)$submission['id']]);$files=$st->fetchAll();}
}
$statusLabel=ucfirst(str_replace('_',' ',$task['status']));
?>
<section class="view active task-page">
  <div class="container page-top task-page-top">
    <a class="section-title-link" href="<?=e(url('browse'))?>"><i data-lucide="arrow-left"></i> Back to task board</a>
    <div class="task-heading-block"><div class="task-heading-meta"><span class="eyebrow"><?=e($task['category_name'])?></span><?=status_pill($task['status'])?></div><h1><?=e($task['title'])?></h1><p>Published by <strong><?=e($task['publisher_name'])?></strong> · Created <?=e(display_datetime($task['created_at']))?></p></div>
  </div>

  <div class="container task-detail-layout task-detail-page-layout">
    <div class="task-detail-main">
      <article class="panel task-overview-panel">
        <?php if($coverImage): ?><div class="task-detail-cover"><img src="<?=e($coverImage)?>" alt="Campaign cover for <?=e($task['title'])?>"></div><?php endif; ?>
        <div class="panel-body">
          <div class="task-detail-top"><div><span class="pill blue"><?=e($task['category_name'])?></span><h2>About this task</h2><div class="task-description"><?=nl2br(e($task['description']))?></div></div><div class="task-detail-reward"><small>Reward per approved worker</small><strong><?=money($gross)?></strong><span>Estimated net: <?=money($net)?></span></div></div>
          <div class="task-detail-stats">
            <div><small>Available slots</small><strong><?=$left?> / <?=number_format((int)$task['worker_slots'])?></strong></div>
            <div><small>Estimated time</small><strong><?=e($task['estimated_time']?:'Flexible')?></strong></div>
            <div><small>Deadline</small><strong><?=e(display_datetime($task['deadline']))?></strong></div>
            <div><small>Campaign status</small><strong><?=e($statusLabel)?></strong></div>
          </div>
          <div class="task-progress-label"><span>Worker slots filled</span><strong><?=number_format((int)$task['claimed_slots'])?> of <?=number_format((int)$task['worker_slots'])?></strong></div>
          <div class="progress task-detail-progress"><span style="width:<?=$fillPercent?>%"></span></div>
        </div>
      </article>

      <section class="panel task-proof-panel">
        <div class="panel-head"><div><h3>Proof requirements</h3><p>Review the acceptance requirements before you claim this task.</p></div><span class="pill green"><?=e($task['proof_type'])?></span></div>
        <div class="panel-body"><div class="task-instructions"><?=nl2br(e($task['proof_instructions']))?></div><div class="form-note task-safety-note"><i data-lucide="shield-check"></i><span>Only submit evidence needed to verify the task. Never share passwords, OTPs, banking PINs or private account credentials.</span></div></div>
      </section>

      <?php if($mine): ?>
        <section class="panel task-progress-panel">
          <div class="panel-head"><div><h3>Your task progress</h3><p>Your claim and review status for this campaign.</p></div><?=status_pill($mine['status'])?></div>
          <div class="panel-body">
            <div class="task-status-steps"><div class="task-status-step is-done"><span>1</span><strong>Slot claimed</strong></div><div class="task-status-step <?=$mine['status']!=='claimed'?'is-done':''?>"><span>2</span><strong>Proof submitted</strong></div><div class="task-status-step <?=$mine['status']==='approved'?'is-done':''?>"><span>3</span><strong>Reviewed</strong></div></div>
            <?php if($mine['status']==='claimed'): ?>
              <form method="post" enctype="multipart/form-data" class="stack-form task-proof-form"><?=csrf_field()?><input type="hidden" name="action" value="submit_proof"><input type="hidden" name="claim_id" value="<?=$mine['id']?>"><input type="hidden" name="_return" value="task"><input type="hidden" name="_return_id" value="<?=$task['id']?>">
                <div class="field"><label>Proof notes <span class="req">*</span></label><textarea name="notes" required minlength="10" maxlength="10000" placeholder="Describe what you completed and include the requested references…"></textarea><small>Write enough detail for the publisher to verify your work.</small></div>
                <div class="field"><label>Evidence URL (optional)</label><input name="evidence_url" type="url" maxlength="1000" placeholder="https://…"></div>
                <div class="field"><label>Screenshot / evidence file (optional)</label><input name="evidence_file" type="file" accept="image/jpeg,image/png,image/webp,application/pdf,text/plain"><small>JPG, PNG, WEBP, PDF or TXT · maximum 5 MB. Do not upload passwords, OTPs or private information.</small></div>
                <button class="btn red" type="submit">Submit proof <i data-lucide="send"></i></button>
              </form>
            <?php elseif($mine['status']==='submitted'): ?><div class="form-note">Your proof has been submitted and is waiting for review.</div>
            <?php elseif($mine['status']==='approved'): ?><div class="form-note">Your submission was approved. Net reward credited: <strong><?=money($submission['net_reward']??$net)?></strong>.</div>
            <?php elseif($mine['status']==='rejected'): ?><div class="form-error">Your submission was rejected. Review the feedback below before taking further action.</div>
            <?php else: ?><div class="form-note">Current claim status: <?=e($mine['status'])?>.</div><?php endif; ?>
            <?php if($submission): ?><div class="submission-card"><div class="submission-top"><strong>Your latest proof</strong><?=status_pill($submission['status'])?></div><p><?=nl2br(e($submission['notes']))?></p><?php if($submission['evidence_url']): ?><p><a href="<?=e($submission['evidence_url'])?>" target="_blank" rel="noopener noreferrer">Open evidence URL <i data-lucide="external-link"></i></a></p><?php endif; ?><?php foreach($files as $f): ?><p><a href="<?=e(url('download_submission',['id'=>$f['id']]))?>"><i data-lucide="paperclip"></i> <?=e($f['original_name'])?> · <?=number_format($f['file_size']/1024,1)?> KB</a></p><?php endforeach; ?><?php if($submission['feedback']): ?><div class="review-feedback"><strong>Reviewer feedback</strong><p><?=nl2br(e($submission['feedback']))?></p></div><?php endif; ?></div><?php endif; ?>
          </div>
        </section>
      <?php elseif($isPublisherOwner): ?>
        <div class="form-note task-owner-note"><i data-lucide="info"></i> This is your campaign. You can manage it from Publisher → My campaigns.</div>
      <?php elseif($isAdmin): ?>
        <div class="form-note task-owner-note"><i data-lucide="shield-check"></i> Admin preview: this campaign is visible for moderation and support purposes.</div>
      <?php elseif($canClaim): ?>
        <section class="panel task-claim-panel"><div class="panel-body task-claim-body"><div><h3>Ready to complete this task?</h3><p>Claim one available worker slot, complete the instructions and submit your proof for review.</p></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="claim_task"><input type="hidden" name="campaign_id" value="<?=$task['id']?>"><input type="hidden" name="_return" value="task"><input type="hidden" name="_return_id" value="<?=$task['id']?>"><button class="btn red" type="submit">Claim a worker slot <i data-lucide="arrow-right"></i></button></form></div></section>
      <?php elseif(!$user): ?>
        <section class="panel task-claim-panel"><div class="panel-body"><h3>Sign in to claim this task</h3><p>TaskHaat workers can claim one available slot per campaign.</p><div class="task-claim-actions"><a class="btn red" href="<?=e(url('login'))?>">Log in to claim</a><a class="btn secondary" href="<?=e(url('register'))?>">Create account</a></div></div></section>
      <?php else: ?>
        <div class="form-error task-owner-note">This campaign is not currently accepting worker claims. It may be full, paused or past its deadline.</div>
      <?php endif; ?>
    </div>

    <aside class="task-detail-aside">
      <div class="balance-card task-reward-card"><small>Worker reward after fee</small><strong><?=money($net)?></strong><p>Gross reward: <?=money($gross)?><br>Worker fee: <?=e($workerFee)?>%</p><a class="btn white sm" href="<?=e(url('fees'))?>">See fee calculation</a></div>
      <div class="panel"><div class="panel-head"><h3>Campaign publisher</h3></div><div class="panel-body"><strong><?=e($task['publisher_name'])?></strong><p class="muted task-publisher-note">Campaigns are reviewed by TaskHaat Admin before publication. Contact details are not exposed on public task pages.</p></div></div>
      <div class="panel"><div class="panel-head"><h3>Before you claim</h3></div><div class="panel-body task-checklist"><p><i data-lucide="check-circle"></i> Read all task instructions.</p><p><i data-lucide="check-circle"></i> Confirm the reward and deadline.</p><p><i data-lucide="check-circle"></i> Prepare the requested proof.</p><p><i data-lucide="shield-check"></i> Keep private credentials out of evidence.</p></div></div>
    </aside>
  </div>

  <?php if(!empty($relatedTasks)): ?><section class="section related-task-section"><div class="container"><div class="section-head"><div><span class="eyebrow">More opportunities</span><h2>Related tasks</h2><p>Other live campaigns in <?=e($task['category_name'])?>.</p></div><a class="section-title-link" href="<?=e(url('browse',['category'=>$task['category_id']]))?>">Browse category <i data-lucide="arrow-right"></i></a></div><div class="task-grid"><?php foreach($relatedTasks as $related)echo task_card($related); ?></div></div></section><?php endif; ?>
</section>
