<?php
$guideType=in_array(($guideType??'worker'),['worker','publisher'],true)?$guideType:'worker';
if($guideType==='publisher'){
  $eyebrow='Publisher guide';$heading='Plan, fund and manage a multi-worker campaign';$intro='A campaign lets multiple unique workers complete the same defined task. Clear instructions and fair proof criteria make reviews easier.';
  $steps=[
   ['Define one clear outcome','Describe exactly what the worker should do, who can participate, the expected result and any exclusions. Avoid vague or subjective requirements.'],
   ['Choose reward and slots','Set the reward per approved worker and the number of unique worker slots. The reward pool is reward × slots.'],
   ['Review the full budget','The publisher fee is added to the reward pool. Check the total against your deposit wallet before submitting the campaign.'],
   ['Write verifiable proof rules','State the exact answer, public URL, screenshot or permitted file workers must submit. Do not request passwords, OTPs or private account access.'],
   ['Submit for moderation','New campaigns enter the Admin review queue. A campaign becomes visible to workers only after approval and funding checks.'],
   ['Review each submission fairly','Use the stated criteria. Approve valid proof; reject with a specific reason that explains what requirement was not met.'],
  ];
}else{
  $eyebrow='Worker guide';$heading='Choose tasks carefully and submit clear proof';$intro='Check the full task details before claiming a slot. The reward is credited only after the publisher approves your submission.';
  $steps=[
   ['Complete your profile','Use accurate contact details and add skills or interests so you can identify suitable tasks.'],
   ['Compare task details','Review reward, time estimate, deadline, remaining slots and the publisher’s proof instructions. Do not claim a task you cannot complete.'],
   ['Claim one available slot','A worker account can claim a given campaign only once. After claiming, the task appears in My Tasks.'],
   ['Complete the stated work','Follow the campaign instructions. Do not create fake engagement, spam, accounts or reviews, and do not share sensitive credentials.'],
   ['Submit verifiable evidence','Write clear notes and attach only the required URL or permitted evidence file. Hide unrelated personal information in screenshots.'],
   ['Follow the review and wallet status','Track Pending, Approved or Rejected in My Submissions. A net reward is credited to your available wallet after approval.'],
  ];
}
$eyebrow=site_content_value($pdo,'guide_'.$guideType.'_eyebrow',$eyebrow);
$heading=site_content_value($pdo,'guide_'.$guideType.'_heading',$heading);
$intro=site_content_value($pdo,'guide_'.$guideType.'_intro',$intro);

?>
<section class="view active guide-page">
  <div class="container page-top"><span class="eyebrow"><?=e($eyebrow)?></span><h1><?=e($heading)?></h1><p><?=e($intro)?></p></div>
  <div class="container guide-content"><div class="guide-step-grid"><?php foreach($steps as $i=>[$title,$copy]): ?><article class="guide-step-card"><span class="guide-step-number"><?=str_pad((string)($i+1),2,'0',STR_PAD_LEFT)?></span><div><h2><?=e($title)?></h2><p><?=e($copy)?></p></div></article><?php endforeach; ?></div>
    <?php if($guideType==='publisher'): ?><div class="guide-callout"><i data-lucide="calculator"></i><div><strong>Use the Fees &amp; Rewards calculator</strong><p>Try different reward and slot amounts before funding a campaign.</p></div><a class="btn sm" href="<?=e(url('fees'))?>">Open calculator</a></div><a class="btn" href="<?=e(url('dashboard',['tab'=>'create','as'=>'publisher']))?>">Create a campaign <i data-lucide="arrow-right"></i></a>
    <?php else: ?><div class="guide-callout"><i data-lucide="shield-check"></i><div><strong>Protect your account and privacy</strong><p>Never share passwords, OTPs, PINs or private account credentials in task proof.</p></div><a class="btn sm" href="<?=e(url('safety'))?>">Read safety rules</a></div><a class="btn" href="<?=e(url('browse'))?>">Find tasks <i data-lucide="arrow-right"></i></a><?php endif; ?>
  </div>
</section>
