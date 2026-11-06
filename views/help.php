<?php
$helpFaqs=site_faq_rows($pdo,'help');
if(!$helpFaqs){$helpFaqs=[
 ['category'=>'Getting started','question'=>'How do I create a TaskHaat account?','answer'=>'Choose Get started, enter your personal details, select Worker, Publisher or Both, accept the platform rules and create your account. Use an email address you can access.'],
 ['category'=>'Getting started','question'=>'Can I use one account as both a Worker and Publisher?','answer'=>'Yes. Select Both during registration. You can switch between the Worker and Publisher workspaces from the workspace selector.'],
 ['category'=>'Workers','question'=>'How do I claim a task?','answer'=>'Open Find Tasks, review the reward, deadline, available slots and proof instructions, then open the task and claim one slot. A worker can claim each campaign only once.'],
 ['category'=>'Workers','question'=>'What happens after I submit proof?','answer'=>'Your submission appears in My Submissions with Pending status. The publisher reviews it and either approves it or rejects it with feedback.'],
 ['category'=>'Workers','question'=>'When is my reward available?','answer'=>'After approval, the worker fee saved for that campaign is deducted and the net reward is credited to your available wallet balance.'],
 ['category'=>'Workers','question'=>'How do I request a withdrawal?','answer'=>'Open Worker → Wallet & withdrawals, choose a supported method, enter your payout details and submit a request. Admin reviews and manually processes it.'],
 ['category'=>'Publishers','question'=>'How is a campaign budget calculated?','answer'=>'Reward per worker multiplied by worker slots gives the reward pool. The publisher service fee is added to that pool. The total is reserved from your deposit wallet when you submit the campaign.'],
 ['category'=>'Publishers','question'=>'Can one campaign have multiple workers?','answer'=>'Yes. The publisher chooses the number of slots. Each unique worker can claim one slot and submit separate evidence.'],
 ['category'=>'Publishers','question'=>'Can I edit a campaign after workers claim it?','answer'=>'Campaign requirements are locked once workers have claimed slots, so existing workers are not asked to complete changed work. Pending or rejected campaigns with no claims may be edited.'],
 ['category'=>'Payments','question'=>'Are bKash, Nagad or bank payments automatic?','answer'=>'No. This build records wallet balances and withdrawal requests. An authorized Admin must verify and complete external payments manually; no payment gateway is connected.'],
 ['category'=>'Safety','question'=>'What tasks are prohibited?','answer'=>'Tasks involving credential or OTP collection, fake engagement, account trading, spam, deceptive reviews, bulk account creation or other manipulation of third-party platforms are prohibited.'],
 ['category'=>'Safety','question'=>'What should I avoid sharing in proof?','answer'=>'Never include passwords, OTPs, bank PINs, full payment credentials or unrelated personal information. Crop or blur private details before uploading evidence.']
];}
$groups=[];foreach($helpFaqs as $faq)$groups[$faq['category']][]=[$faq['category'],$faq['question'],$faq['answer']];

?>
<section class="view active help-page">
  <div class="container page-top"><span class="eyebrow"><?=e(site_content_value($pdo,'help_page_eyebrow','TaskHaat support'))?></span><h1><?=e(site_content_value($pdo,'help_page_heading','Help Center'))?></h1><p><?=e(site_content_value($pdo,'help_page_intro','Practical answers for Workers and Publishers, plus clear information about reviews, rewards and safety.'))?></p></div>
  <div class="container help-content">
    <div class="help-hero-grid">
      <article class="help-hero-card"><span class="help-hero-icon"><i data-lucide="search"></i></span><div><h2>Find an answer</h2><p>Search common questions about tasks, campaigns, wallets and account safety.</p></div><a class="btn sm" href="#helpQuestions">Browse answers</a></article>
      <article class="help-hero-card warm"><span class="help-hero-icon"><i data-lucide="message-square-text"></i></span><div><h2>Need more help?</h2><p>Send a support request. Your message will be saved in the Admin support inbox.</p></div><a class="btn sm secondary" href="<?=e(url('contact'))?>">Contact support</a></article>
    </div>
    <div class="help-search-wrap" id="helpQuestions"><label class="faq-search"><i data-lucide="search"></i><input type="search" id="helpFaqSearch" placeholder="Search questions or keywords…" aria-label="Search help questions"><button type="button" data-clear="helpFaqSearch" class="clear-search" aria-label="Clear search"><i data-lucide="x"></i></button></label><div class="help-category-links"><a href="#help-Getting-started">Getting started</a><a href="#help-Workers">Workers</a><a href="#help-Publishers">Publishers</a><a href="#help-Payments">Payments</a><a href="#help-Safety">Safety</a></div></div>
    <?php foreach($groups as $group=>$items): $groupId='help-'.str_replace(' ','-', $group); ?>
      <section class="help-topic" id="<?=e($groupId)?>"><div class="section-head"><div><span class="eyebrow">Help topic</span><h2><?=e($group)?></h2></div></div><div class="faq-list" data-faq-list><?php foreach($items as [$topic,$question,$answer]): ?><article class="faq-item" data-faq-item><button class="faq-q" type="button" aria-expanded="false"><span><?=e($question)?></span><i data-lucide="chevron-down"></i></button><div class="faq-a"><?=e($answer)?></div></article><?php endforeach; ?><div class="faq-no-results" data-faq-empty hidden>No matching questions in this topic.</div></div></section>
    <?php endforeach; ?>
    <div class="help-bottom-cta"><div><h2>Still need help?</h2><p>Send us the topic, your account email and enough detail to understand the issue. Never include your password or OTP.</p></div><a class="btn" href="<?=e(url('contact'))?>">Send a support request <i data-lucide="arrow-right"></i></a></div>
  </div>
</section>
