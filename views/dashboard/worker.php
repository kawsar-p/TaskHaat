<?php
$workerId = (int)$user['id'];
$filterUrl = static fn(string $filter, int $page = 1, string $query = ''): string => url('dashboard', [
    'tab' => 'mytasks', 'as' => 'worker', 'filter' => $filter, 'page' => $page, 'q' => $query,
]);
$submissionUrl = static fn(string $filter, int $page = 1): string => url('dashboard', [
    'tab' => 'submissions', 'as' => 'worker', 'filter' => $filter, 'page' => $page,
]);
$pagination = static function (int $page, int $pages, callable $makeUrl): void {
    if ($pages <= 1) return;
    $start = max(1, $page - 2);
    $end = min($pages, $page + 2);
    ?>
    <nav class="worker-pagination" aria-label="Pagination">
      <?php if ($page > 1): ?><a class="btn secondary sm" href="<?=e($makeUrl($page - 1))?>"><i data-lucide="chevron-left"></i> Previous</a><?php endif; ?>
      <span>Page <?=number_format($page)?> of <?=number_format($pages)?></span>
      <?php if ($page < $pages): ?><a class="btn secondary sm" href="<?=e($makeUrl($page + 1))?>">Next <i data-lucide="chevron-right"></i></a><?php endif; ?>
    </nav>
    <?php
};
?>
<?php if ($tab === 'overview'):
    $statusCounts = ['claimed'=>0,'submitted'=>0,'approved'=>0,'rejected'=>0,'cancelled'=>0];
    $st = $pdo->prepare('SELECT status, COUNT(*) total FROM claims WHERE worker_id=? GROUP BY status');
    $st->execute([$workerId]);
    foreach ($st->fetchAll() as $row) if (array_key_exists($row['status'], $statusCounts)) $statusCounts[$row['status']] = (int)$row['total'];
    $st = $pdo->prepare("SELECT COALESCE(SUM(GREATEST(0,c.reward_per_worker*(1-c.worker_fee_rate/100))),0) FROM submissions s JOIN claims cl ON cl.id=s.claim_id JOIN campaigns c ON c.id=cl.campaign_id WHERE cl.worker_id=? AND s.status='pending'");
    $st->execute([$workerId]);
    $pendingNet = (float)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COALESCE(SUM(net_reward),0) FROM submissions s JOIN claims cl ON cl.id=s.claim_id WHERE cl.worker_id=? AND s.status='approved'");
    $st->execute([$workerId]);
    $lifetimeNet = (float)$st->fetchColumn();
    $st = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $st->execute([$workerId]);
    $unreadCount = (int)$st->fetchColumn();
    $st = $pdo->prepare("SELECT c.*,cat.name category_name,CONCAT(u.first_name,' ',u.last_name) publisher_name FROM campaigns c JOIN categories cat ON cat.id=c.category_id JOIN users u ON u.id=c.publisher_id WHERE c.status='approved' AND c.publisher_id<>? AND c.claimed_slots<c.worker_slots AND (c.deadline IS NULL OR c.deadline>=NOW()) AND NOT EXISTS (SELECT 1 FROM claims own WHERE own.campaign_id=c.id AND own.worker_id=?) ORDER BY c.created_at DESC LIMIT 4");
    $st->execute([$workerId,$workerId]);
    $recommended = $st->fetchAll();
    $st = $pdo->prepare("SELECT s.id,s.status,s.created_at,s.feedback,c.id campaign_id,c.title campaign_title,s.net_reward FROM submissions s JOIN claims cl ON cl.id=s.claim_id JOIN campaigns c ON c.id=cl.campaign_id WHERE cl.worker_id=? ORDER BY s.created_at DESC LIMIT 4");
    $st->execute([$workerId]);
    $recentSubmissions = $st->fetchAll();
    $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE user_id=? AND status='paid'");
    $st->execute([$workerId]);
    $paidWithdrawals = (float)$st->fetchColumn();
?>
<div class="worker-welcome welcome-card">
  <div><span class="worker-kicker">YOUR WORKSPACE</span><h2>Welcome back, <?=e($user['first_name'])?>.</h2><p>Keep your tasks moving, submit clear evidence and track every approved reward from one place.</p></div>
  <div class="worker-welcome-actions"><span class="welcome-tag"><i data-lucide="shield-check"></i> Verified task workflow</span><a class="btn red sm" href="<?=e(url('browse'))?>"><i data-lucide="search"></i> Find tasks</a></div>
</div>
<div class="worker-kpi-grid">
  <article class="worker-kpi"><span class="worker-kpi-icon"><i data-lucide="list-checks"></i></span><small>In progress</small><strong><?=number_format($statusCounts['claimed'])?></strong><a href="<?=e($filterUrl('claimed'))?>">Continue tasks <i data-lucide="arrow-up-right"></i></a></article>
  <article class="worker-kpi"><span class="worker-kpi-icon is-cream"><i data-lucide="clock-3"></i></span><small>Awaiting review</small><strong><?=number_format($statusCounts['submitted'])?></strong><a href="<?=e($submissionUrl('pending'))?>">Track submissions <i data-lucide="arrow-up-right"></i></a></article>
  <article class="worker-kpi"><span class="worker-kpi-icon is-orange"><i data-lucide="badge-check"></i></span><small>Approved tasks</small><strong><?=number_format($statusCounts['approved'])?></strong><a href="<?=e($submissionUrl('approved'))?>">View approvals <i data-lucide="arrow-up-right"></i></a></article>
  <article class="worker-kpi"><span class="worker-kpi-icon is-red"><i data-lucide="wallet"></i></span><small>Available balance</small><strong><?=money($wallet['available_balance'])?></strong><a href="<?=e($sideUrl('wallet'))?>">Open wallet <i data-lucide="arrow-up-right"></i></a></article>
</div>
<div class="worker-summary-strip">
  <div><span>Estimated pending rewards</span><strong><?=money($pendingNet)?></strong><small>Only credited after approval</small></div>
  <div><span>Lifetime approved earnings</span><strong><?=money($lifetimeNet)?></strong><small>Net of worker fees</small></div>
  <div><span>Withdrawals marked paid</span><strong><?=money($paidWithdrawals)?></strong><small>Manual payout records</small></div>
  <div><span>Unread notifications</span><strong><?=number_format($unreadCount)?></strong><small><a href="<?=e($sideUrl('notifications'))?>">Review alerts</a></small></div>
</div>
<div class="worker-content-grid">
  <section class="panel worker-panel">
    <div class="panel-head"><div><span class="eyebrow">Recommended for you</span><h3>Available tasks</h3><p>Live campaigns you have not claimed yet.</p></div><a class="section-title-link" href="<?=e(url('browse'))?>">Browse all <i data-lucide="arrow-right"></i></a></div>
    <div class="panel-body worker-recommended-list">
      <?php foreach ($recommended as $task) echo task_card($task); ?>
      <?php if (!$recommended): ?><div class="empty-state"><i data-lucide="search-check"></i><strong>No new campaigns right now</strong><p>Check the task board again later. New approved campaigns will appear here.</p><a class="btn secondary sm" href="<?=e(url('browse'))?>">Open task board</a></div><?php endif; ?>
    </div>
  </section>
  <section class="panel worker-panel">
    <div class="panel-head"><div><span class="eyebrow">Your activity</span><h3>Recent submissions</h3><p>Latest proof review updates.</p></div><a class="section-title-link" href="<?=e($sideUrl('submissions'))?>">View all</a></div>
    <div class="panel-body worker-activity-list">
      <?php foreach ($recentSubmissions as $activity): ?>
        <article class="worker-activity-item"><span class="worker-activity-icon"><i data-lucide="file-check-2"></i></span><div><strong><?=e($activity['campaign_title'])?></strong><small><?=e(display_datetime($activity['created_at']))?></small><p><?=e($activity['status']==='approved'?'Your proof was approved.':($activity['status']==='rejected'?'Your proof was rejected. Review the feedback.':'Your proof is waiting for review.'))?></p></div><div><?=status_pill($activity['status'])?><?php if($activity['status']==='approved'): ?><strong class="worker-activity-reward">+<?=money($activity['net_reward'])?></strong><?php endif; ?></div></article>
      <?php endforeach; ?>
      <?php if (!$recentSubmissions): ?><div class="empty-state"><i data-lucide="file-clock"></i><strong>No submissions yet</strong><p>Claim a task and submit its required proof. Your review history will appear here.</p></div><?php endif; ?>
    </div>
  </section>
</div>
<?php elseif ($tab === 'mytasks'):
    $taskFilter = (string)($_GET['filter'] ?? 'all');
    $validTaskFilters = ['all','claimed','submitted','approved','rejected','cancelled'];
    if (!in_array($taskFilter,$validTaskFilters,true)) $taskFilter='all';
    $taskSearch = trim((string)($_GET['q'] ?? ''));
    $taskPage = max(1,(int)($_GET['page'] ?? 1));
    $taskPerPage = 10;
    $taskWhere = ' WHERE cl.worker_id=?';
    $taskParams = [$workerId];
    if ($taskFilter !== 'all') { $taskWhere .= ' AND cl.status=?'; $taskParams[]=$taskFilter; }
    if ($taskSearch !== '') { $taskWhere .= ' AND (c.title LIKE ? OR cat.name LIKE ?)'; $taskParams[]='%'.$taskSearch.'%'; $taskParams[]='%'.$taskSearch.'%'; }
    $st=$pdo->prepare('SELECT COUNT(*) FROM claims cl JOIN campaigns c ON c.id=cl.campaign_id JOIN categories cat ON cat.id=c.category_id'.$taskWhere);
    $st->execute($taskParams); $taskTotal=(int)$st->fetchColumn();
    $taskPages=max(1,(int)ceil($taskTotal/$taskPerPage)); if($taskPage>$taskPages)$taskPage=$taskPages;
    $taskOffset=($taskPage-1)*$taskPerPage;
    $sql='SELECT cl.*,c.id campaign_id,c.title,c.reward_per_worker,c.deadline,c.status campaign_status,c.worker_slots,c.claimed_slots,cat.name category_name,s.id submission_id,s.status submission_status,s.feedback FROM claims cl JOIN campaigns c ON c.id=cl.campaign_id JOIN categories cat ON cat.id=c.category_id LEFT JOIN submissions s ON s.claim_id=cl.id'.$taskWhere.' ORDER BY cl.claimed_at DESC LIMIT '.$taskPerPage.' OFFSET '.$taskOffset;
    $st=$pdo->prepare($sql);$st->execute($taskParams);$taskRows=$st->fetchAll();
    $st=$pdo->prepare('SELECT COUNT(*) FROM claims WHERE worker_id=?');$st->execute([$workerId]);
    $filterCounts=['all'=>(int)$st->fetchColumn()];
    $st=$pdo->prepare('SELECT cl.status,COUNT(*) total FROM claims cl WHERE cl.worker_id=? GROUP BY cl.status');$st->execute([$workerId]);
    foreach($st->fetchAll() as $r)$filterCounts[$r['status']]=(int)$r['total'];
?>
<div class="panel worker-panel">
  <div class="panel-head worker-section-head"><div><span class="eyebrow">WORK HISTORY</span><h3>My tasks</h3><p>Continue active tasks or review the status of completed work.</p></div><a class="btn red sm" href="<?=e(url('browse'))?>"><i data-lucide="search"></i> Find tasks</a></div>
  <div class="panel-body">
    <form method="get" class="worker-filter-form"><input type="hidden" name="route" value="dashboard"><input type="hidden" name="tab" value="mytasks"><input type="hidden" name="as" value="worker"><input type="hidden" name="filter" value="<?=e($taskFilter)?>"><label class="worker-search"><i data-lucide="search"></i><input type="search" name="q" value="<?=e($taskSearch)?>" placeholder="Search your claimed tasks..."></label><button class="btn sm" type="submit">Search</button><?php if($taskSearch!==''): ?><a class="btn secondary sm" href="<?=e($filterUrl($taskFilter))?>">Clear</a><?php endif; ?></form>
    <div class="worker-filter-tabs" aria-label="Filter tasks">
      <?php foreach(['all'=>'All tasks','claimed'=>'In progress','submitted'=>'Submitted','approved'=>'Approved','rejected'=>'Rejected','cancelled'=>'Cancelled'] as $key=>$label): ?><a class="filter-tab <?=$taskFilter===$key?'active':''?>" href="<?=e($filterUrl($key,1,$taskSearch))?>"><?=e($label)?> <span><?=number_format((int)($filterCounts[$key]??0))?></span></a><?php endforeach; ?>
    </div>
    <div class="worker-record-list">
      <?php foreach($taskRows as $r):
        $deadlinePassed=!empty($r['deadline'])&&strtotime($r['deadline'])<time();
        $ctaLabel=$r['status']==='claimed'?'Continue task':($r['status']==='submitted'?'View submission':($r['status']==='rejected'?'Review feedback':'View task details'));
      ?>
      <article class="worker-task-record">
        <div class="worker-task-main"><div class="worker-record-top"><span class="pill blue"><?=e($r['category_name'])?></span><?=status_pill($r['status'])?></div><h4><a href="<?=e(url('task',['id'=>$r['campaign_id']]))?>"><?=e($r['title'])?></a></h4><p>Claimed <?=e(display_datetime($r['claimed_at']))?> · Deadline: <?=e(display_datetime($r['deadline']))?></p><?php if($r['status']==='rejected'&&!empty($r['feedback'])): ?><div class="worker-feedback-preview"><strong>Review feedback:</strong> <?=e(short_text((string)$r['feedback'],180))?></div><?php endif; ?></div>
        <div class="worker-task-reward"><small>Reward per worker</small><strong><?=money($r['reward_per_worker'])?></strong><small><?=number_format((int)$r['claimed_slots'])?> / <?=number_format((int)$r['worker_slots'])?> slots claimed</small></div>
        <div class="worker-task-actions"><a class="btn sm <?=$r['status']==='claimed'?'red':'secondary'?>" href="<?=e(url('task',['id'=>$r['campaign_id']]))?>"><?=e($ctaLabel)?></a><?php if($deadlinePassed&&$r['status']==='claimed'): ?><small class="worker-deadline-note">Deadline passed; check publisher instructions.</small><?php endif; ?></div>
      </article>
      <?php endforeach; ?>
      <?php if(!$taskRows): ?><div class="empty-state"><i data-lucide="clipboard-list"></i><strong>No tasks found</strong><p><?=($taskSearch!==''||$taskFilter!=='all')?'Try another search or clear the current filter.':'Claim a campaign from the task board to start your work history.'?></p><a class="btn sm" href="<?=e(url('browse'))?>">Browse tasks</a></div><?php endif; ?>
    </div>
    <?php $pagination($taskPage,$taskPages,fn(int $p)=>$filterUrl($taskFilter,$p,$taskSearch)); ?>
  </div>
</div>
<?php elseif ($tab === 'submissions'):
    $submissionFilter=(string)($_GET['filter']??'all');
    if(!in_array($submissionFilter,['all','pending','approved','rejected'],true))$submissionFilter='all';
    $submissionPage=max(1,(int)($_GET['page']??1));$submissionPerPage=10;
    $submissionWhere=' WHERE cl.worker_id=?';$submissionParams=[$workerId];
    if($submissionFilter!=='all'){$submissionWhere.=' AND s.status=?';$submissionParams[]=$submissionFilter;}
    $st=$pdo->prepare('SELECT COUNT(*) FROM submissions s JOIN claims cl ON cl.id=s.claim_id'.$submissionWhere);$st->execute($submissionParams);$submissionTotal=(int)$st->fetchColumn();
    $submissionPages=max(1,(int)ceil($submissionTotal/$submissionPerPage));if($submissionPage>$submissionPages)$submissionPage=$submissionPages;
    $submissionOffset=($submissionPage-1)*$submissionPerPage;
    $st=$pdo->prepare('SELECT s.*,c.title campaign_title,cl.campaign_id,c.reward_per_worker,c.worker_fee_rate FROM submissions s JOIN claims cl ON cl.id=s.claim_id JOIN campaigns c ON c.id=cl.campaign_id'.$submissionWhere.' ORDER BY s.created_at DESC LIMIT '.$submissionPerPage.' OFFSET '.$submissionOffset);
    $st->execute($submissionParams);$submissionRows=$st->fetchAll();
    $st=$pdo->prepare('SELECT COUNT(*) FROM submissions s JOIN claims cl ON cl.id=s.claim_id WHERE cl.worker_id=?');$st->execute([$workerId]);
    $submissionCounts=['all'=>(int)$st->fetchColumn()];
    $st=$pdo->prepare('SELECT s.status,COUNT(*) total FROM submissions s JOIN claims cl ON cl.id=s.claim_id WHERE cl.worker_id=? GROUP BY s.status');$st->execute([$workerId]);foreach($st->fetchAll() as $r)$submissionCounts[$r['status']]=(int)$r['total'];
?>
<div class="panel worker-panel"><div class="panel-head"><div><span class="eyebrow">PROOF & REVIEW</span><h3>My submissions</h3><p>See what is waiting, approved or needs your attention.</p></div><span class="pill blue"><?=number_format($submissionTotal)?> results</span></div><div class="panel-body">
  <div class="worker-filter-tabs" aria-label="Filter submissions"><?php foreach(['all'=>'All','pending'=>'Pending review','approved'=>'Approved','rejected'=>'Rejected'] as $key=>$label): ?><a class="filter-tab <?=$submissionFilter===$key?'active':''?>" href="<?=e($submissionUrl($key))?>"><?=e($label)?> <span><?=number_format((int)($submissionCounts[$key]??0))?></span></a><?php endforeach; ?></div>
  <?php foreach($submissionRows as $s): $renderSubmission($s,false); ?>
    <?php if($s['status']==='pending'): ?><div class="worker-review-meta"><span class="pill amber">Review pending</span><span>Estimated net reward: <?=money((float)$s['reward_per_worker']*(1-(float)$s['worker_fee_rate']/100))?></span></div><?php elseif($s['status']==='approved'): ?><div class="worker-review-meta"><span class="pill green">Reward approved</span><strong>Credited: <?=money($s['net_reward'])?></strong></div><?php elseif($s['status']==='rejected'): ?><div class="worker-review-meta is-rejected"><span class="pill rose">Changes required</span><a href="<?=e(url('task',['id'=>$s['campaign_id']]))?>">Open task & review feedback</a></div><?php endif; ?>
  <?php endforeach; ?>
  <?php if(!$submissionRows): ?><div class="empty-state"><i data-lucide="file-check-2"></i><strong>No submissions in this view</strong><p>Submit proof for a claimed task to start your review history.</p><a class="btn secondary sm" href="<?=e($sideUrl('mytasks'))?>">Open my tasks</a></div><?php endif; ?>
  <?php $pagination($submissionPage,$submissionPages,fn(int $p)=>$submissionUrl($submissionFilter,$p)); ?>
</div></div>
<?php elseif ($tab === 'wallet'):
    $st=$pdo->prepare("SELECT COALESCE(SUM(GREATEST(0,c.reward_per_worker*(1-c.worker_fee_rate/100))),0) FROM submissions s JOIN claims cl ON cl.id=s.claim_id JOIN campaigns c ON c.id=cl.campaign_id WHERE cl.worker_id=? AND s.status='pending'");$st->execute([$workerId]);$pendingReward=(float)$st->fetchColumn();
    $st=$pdo->prepare("SELECT COALESCE(SUM(net_reward),0) FROM submissions s JOIN claims cl ON cl.id=s.claim_id WHERE cl.worker_id=? AND s.status='approved'");$st->execute([$workerId]);$lifetimeEarnings=(float)$st->fetchColumn();
    $st=$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE user_id=? AND status='paid'");$st->execute([$workerId]);$totalPaid=(float)$st->fetchColumn();
    $withdrawalPage=max(1,(int)($_GET['withdrawal_page']??1));$withdrawalPerPage=8;
    $st=$pdo->prepare('SELECT COUNT(*) FROM withdrawals WHERE user_id=?');$st->execute([$workerId]);$withdrawalTotal=(int)$st->fetchColumn();
    $withdrawalPages=max(1,(int)ceil($withdrawalTotal/$withdrawalPerPage));if($withdrawalPage>$withdrawalPages)$withdrawalPage=$withdrawalPages;
    $st=$pdo->prepare('SELECT * FROM withdrawals WHERE user_id=? ORDER BY created_at DESC LIMIT '.$withdrawalPerPage.' OFFSET '.(($withdrawalPage-1)*$withdrawalPerPage));$st->execute([$workerId]);$withdrawals=$st->fetchAll();
    $ledgerPage=max(1,(int)($_GET['ledger_page']??1));$ledgerPerPage=12;
    $st=$pdo->prepare('SELECT COUNT(*) FROM wallet_transactions WHERE user_id=?');$st->execute([$workerId]);$ledgerTotal=(int)$st->fetchColumn();
    $ledgerPages=max(1,(int)ceil($ledgerTotal/$ledgerPerPage));if($ledgerPage>$ledgerPages)$ledgerPage=$ledgerPages;
    $st=$pdo->prepare('SELECT * FROM wallet_transactions WHERE user_id=? ORDER BY created_at DESC,id DESC LIMIT '.$ledgerPerPage.' OFFSET '.(($ledgerPage-1)*$ledgerPerPage));$st->execute([$workerId]);$ledger=$st->fetchAll();
    $walletUrl=static fn(string $key,int $page):string=>url('dashboard',['tab'=>'wallet','as'=>'worker',$key=>$page]);
?>
<div class="worker-wallet-kpis">
  <article class="worker-wallet-primary"><span>AVAILABLE TO WITHDRAW</span><strong><?=money($wallet['available_balance'])?></strong><small>Approved rewards available in your wallet.</small></article>
  <article><small>Pending review estimate</small><strong><?=money($pendingReward)?></strong><span>Not available until proof approval</span></article>
  <article><small>Withdrawal on hold</small><strong><?=money($wallet['withdrawal_hold_balance'])?></strong><span>Requests awaiting Admin settlement</span></article>
  <article><small>Lifetime approved earnings</small><strong><?=money($lifetimeEarnings)?></strong><span>Paid withdrawals: <?=money($totalPaid)?></span></article>
</div>
<div class="worker-content-grid worker-wallet-grid">
  <section class="panel worker-panel"><div class="panel-head"><div><span class="eyebrow">PAYOUT REQUEST</span><h3>Request a withdrawal</h3><p>Use an account that belongs to you.</p></div><i data-lucide="landmark"></i></div><div class="panel-body">
    <div class="form-note"><strong>Manual processing:</strong> Admin reviews your request and marks it paid after completing the external transfer. No automatic bKash, Nagad, Rocket or bank payout is connected.</div>
    <form method="post" class="stack-form worker-withdraw-form"><?=csrf_field()?><input type="hidden" name="action" value="request_withdrawal">
      <div class="field"><label>Payment method <span class="req">*</span></label><select name="method" required><option value="bKash">bKash</option><option value="Nagad">Nagad</option><option value="Rocket">Rocket</option><option value="Bank transfer">Bank transfer</option></select></div>
      <div class="field"><label>Account number / bank reference <span class="req">*</span></label><input name="account_reference" required maxlength="120" autocomplete="off" placeholder="Your own wallet number or bank reference"></div>
      <div class="field"><label>Amount (BDT) <span class="req">*</span></label><input type="number" name="amount" required min="<?=e(get_setting($pdo,'min_withdrawal','200'))?>" max="<?=e(get_setting($pdo,'max_withdrawal','25000'))?>" step="0.01" inputmode="decimal"></div>
      <p class="worker-limit-note">Minimum <?=money(get_setting($pdo,'min_withdrawal','200'))?> · Maximum <?=money(get_setting($pdo,'max_withdrawal','25000'))?></p>
      <button class="btn red" type="submit" <?=((float)$wallet['available_balance']<(float)get_setting($pdo,'min_withdrawal','200'))?'disabled':''?>>Submit withdrawal request <i data-lucide="arrow-right"></i></button>
    </form>
  </div></section>
  <section class="panel worker-panel"><div class="panel-head"><div><span class="eyebrow">PAYOUT HISTORY</span><h3>Withdrawal requests</h3><p><?=number_format($withdrawalTotal)?> total requests</p></div></div><div class="panel-body table-wrap"><table class="data-table worker-data-table"><thead><tr><th>Date</th><th>Method</th><th>Amount</th><th>Status / Admin note</th></tr></thead><tbody>
    <?php foreach($withdrawals as $wd): ?><tr><td><?=e(display_datetime($wd['created_at']))?></td><td><?=e($wd['method'])?><br><small><?=e(mask_account_reference(decrypt_account_reference((string)$wd['account_reference'])))?></small></td><td><strong><?=money($wd['amount'])?></strong></td><td><?=status_pill($wd['status'])?><?php if(!empty($wd['payment_reference'])): ?><small class="worker-admin-note">Payment reference: <?=e($wd['payment_reference'])?></small><?php endif; ?><?php if(!empty($wd['admin_note'])): ?><small class="worker-admin-note"><?=e($wd['admin_note'])?></small><?php endif; ?></td></tr><?php endforeach; ?>
    <?php if(!$withdrawals): ?><tr><td colspan="4">No withdrawal requests yet.</td></tr><?php endif; ?>
  </tbody></table><?php $pagination($withdrawalPage,$withdrawalPages,fn(int $p)=>$walletUrl('withdrawal_page',$p)); ?></div></section>
</div>
<section class="panel worker-panel worker-ledger-panel"><div class="panel-head"><div><span class="eyebrow">ACCOUNTING HISTORY</span><h3>Wallet activity</h3><p>Every credit and debit recorded for your account.</p></div><span class="pill blue"><?=number_format($ledgerTotal)?> transactions</span></div><div class="panel-body table-wrap"><table class="data-table worker-data-table"><thead><tr><th>Date</th><th>Transaction</th><th>Wallet bucket</th><th>Amount</th><th>Description</th></tr></thead><tbody>
  <?php foreach($ledger as $tx): ?><tr><td><?=e(display_datetime($tx['created_at']))?></td><td><?=e(ucwords(str_replace('_',' ',$tx['transaction_type'])))?></td><td><?=e(ucwords(str_replace('_',' ',$tx['bucket'])))?></td><td class="<?=$tx['direction']==='credit'?'amount-positive':'amount-negative'?>"><?=$tx['direction']==='credit'?'+':'−'?><?=money($tx['amount'])?></td><td><?=e($tx['description'])?></td></tr><?php endforeach; ?>
  <?php if(!$ledger): ?><tr><td colspan="5">No wallet activity has been recorded yet.</td></tr><?php endif; ?>
</tbody></table><?php $pagination($ledgerPage,$ledgerPages,fn(int $p)=>$walletUrl('ledger_page',$p)); ?></div></section>
<?php elseif ($tab === 'notifications'):
    $notificationFilter=(string)($_GET['filter']??'all');if(!in_array($notificationFilter,['all','unread'],true))$notificationFilter='all';
    $notificationPage=max(1,(int)($_GET['page']??1));$notificationPerPage=15;
    $notificationWhere=' WHERE user_id=?';$notificationParams=[$workerId];if($notificationFilter==='unread')$notificationWhere.=' AND is_read=0';
    $st=$pdo->prepare('SELECT COUNT(*) FROM notifications'.$notificationWhere);$st->execute($notificationParams);$notificationTotal=(int)$st->fetchColumn();
    $notificationPages=max(1,(int)ceil($notificationTotal/$notificationPerPage));if($notificationPage>$notificationPages)$notificationPage=$notificationPages;
    $st=$pdo->prepare('SELECT * FROM notifications'.$notificationWhere.' ORDER BY created_at DESC,id DESC LIMIT '.$notificationPerPage.' OFFSET '.(($notificationPage-1)*$notificationPerPage));$st->execute($notificationParams);$notificationRows=$st->fetchAll();
    $st=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0');$st->execute([$workerId]);$notificationUnread=(int)$st->fetchColumn();
?>
<div class="panel worker-panel"><div class="panel-head"><div><span class="eyebrow">ACCOUNT UPDATES</span><h3>Notifications</h3><p><?=number_format($notificationUnread)?> unread notifications</p></div><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="mark_notifications_read"><button class="btn secondary sm" type="submit" <?= $notificationUnread===0?'disabled':''?>>Mark all as read</button></form></div><div class="panel-body">
  <div class="worker-filter-tabs"><a class="filter-tab <?=$notificationFilter==='all'?'active':''?>" href="<?=e(url('dashboard',['tab'=>'notifications','as'=>'worker']))?>">All notifications</a><a class="filter-tab <?=$notificationFilter==='unread'?'active':''?>" href="<?=e(url('dashboard',['tab'=>'notifications','as'=>'worker','filter'=>'unread']))?>">Unread <span><?=number_format($notificationUnread)?></span></a></div>
  <div class="worker-notification-list">
  <?php foreach($notificationRows as $n): ?><article class="worker-notification <?=$n['is_read']?'is-read':'is-unread'?>"><span class="worker-notification-icon"><i data-lucide="<?=e($n['type']==='wallet'?'wallet':($n['type']==='submission'?'file-check-2':($n['type']==='campaign'?'layers':'bell')))?>"></i></span><div class="worker-notification-copy"><div class="worker-notification-title"><strong><?=e($n['title'])?></strong><?=$n['is_read']?'':'<span class="pill blue">NEW</span>'?></div><p><?=e($n['body'])?></p><time><?=e(display_datetime($n['created_at']))?></time><?php if(!empty($n['link'])): ?><a class="worker-notification-link" href="<?=e($n['link'])?>">Open related page <i data-lucide="arrow-up-right"></i></a><?php endif; ?></div><?php if(!$n['is_read']): ?><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="mark_notification_read"><input type="hidden" name="notification_id" value="<?=$n['id']?>"><button class="btn secondary sm" type="submit">Mark read</button></form><?php endif; ?></article><?php endforeach; ?>
  <?php if(!$notificationRows): ?><div class="empty-state"><i data-lucide="bell-ring"></i><strong>No notifications here</strong><p><?=($notificationFilter==='unread'?'You have no unread updates.':'Your TaskHaat activity updates will appear here.')?></p></div><?php endif; ?>
  </div>
  <?php $pagination($notificationPage,$notificationPages,fn(int $p)=>url('dashboard',['tab'=>'notifications','as'=>'worker','filter'=>$notificationFilter,'page'=>$p])); ?>
</div></div>
<?php elseif ($tab === 'profile'):
?>
<div class="worker-profile-layout">
  <section class="panel worker-panel"><div class="panel-head"><div><span class="eyebrow">PERSONAL DETAILS</span><h3>My profile</h3><p>Keep your account details accurate for task participation and payout review.</p></div></div><div class="panel-body">
    <form method="post" class="worker-profile-form"><?=csrf_field()?><input type="hidden" name="action" value="update_profile">
      <div class="form-grid"><div class="field"><label>First name <span class="req">*</span></label><input name="first_name" required maxlength="80" autocomplete="given-name" value="<?=e($user['first_name']??'')?>"></div><div class="field"><label>Last name <span class="req">*</span></label><input name="last_name" required maxlength="80" autocomplete="family-name" value="<?=e($user['last_name']??'')?>"></div>
      <div class="field"><label>Email address</label><input value="<?=e($user['email']??'')?>" disabled><small>Email changes require a separate verification flow.</small></div><div class="field"><label>Mobile number</label><input name="phone" type="tel" maxlength="16" value="<?=e($user['phone']??'')?>" placeholder="01XXXXXXXXX"></div>
      <div class="field"><label>Country <span class="req">*</span></label><input name="country" required maxlength="100" value="<?=e($user['country']??'Bangladesh')?>"></div><div class="field"><label>District</label><input name="district" maxlength="100" value="<?=e($user['district']??'')?>" placeholder="e.g. Dhaka"></div>
      <div class="field"><label>City / Upazila</label><input name="city" maxlength="120" value="<?=e($user['city']??'')?>" placeholder="e.g. Uttara"></div><div class="field"><label>Occupation</label><input name="occupation" maxlength="120" value="<?=e($user['occupation']??'')?>" placeholder="Student, freelancer, professional..."></div>
      <div class="field full"><label>Skills and interests</label><textarea name="skills" rows="3" maxlength="2000" placeholder="e.g. Survey research, data entry, website testing"><?=e($user['skills']??'')?></textarea></div>
      <div class="field"><label>Organization (optional)</label><input name="organization" maxlength="180" value="<?=e($user['organization']??'')?>"></div><div class="field"><label>Website / portfolio (optional)</label><input name="website" type="url" maxlength="255" value="<?=e($user['website']??'')?>" placeholder="https://example.com"></div>
      </div>
      <div class="worker-profile-footer"><p><i data-lucide="lock-keyhole"></i> Your profile is visible according to TaskHaat privacy and account rules. Your email address is not publicly displayed.</p><button class="btn red" type="submit">Save profile changes <i data-lucide="arrow-right"></i></button></div>
    </form>
  </div></section>
  <aside class="panel worker-profile-aside"><div class="panel-body"><span class="worker-profile-avatar"><?=e(strtoupper(substr((string)$user['first_name'],0,1).substr((string)$user['last_name'],0,1)))?></span><h3><?=e($user['first_name'].' '.$user['last_name'])?></h3><p><?=e($user['email'])?></p><span class="pill green">Active account</span><div class="worker-profile-facts"><div><small>Member since</small><strong><?=e(date('d M Y',strtotime((string)$user['created_at'])))?></strong></div><div><small>Account type</small><strong><?=e(ucfirst((string)$user['user_type']))?></strong></div><div><small>Country</small><strong><?=e($user['country']??'Bangladesh')?></strong></div></div></div></aside>
</div>
<?php endif; ?>
