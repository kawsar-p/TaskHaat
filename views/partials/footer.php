<footer class="footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand"><a class="brand" href="<?=e(url('home'))?>"><span class="brand-mark"><i data-lucide="layers-3"></i></span> <?=e(site_content_value($pdo,'site_brand_name','TaskHaat'))?></a><p><?=e(site_content_value($pdo,'footer_description','A practical microtask marketplace for legitimate short work, transparent proof review and clear rewards.'))?></p><a class="footer-support-link" href="<?=e(url('contact'))?>"><i data-lucide="mail"></i> Contact support</a></div>
      <div class="footer-col"><h4>Discover</h4><a href="<?=e(url('browse'))?>">Find tasks</a><a href="<?=e(url('home'))?>#how">How it works</a><a href="<?=e(url('fees'))?>">Fees &amp; rewards</a><a href="<?=e(url('guide',['type'=>'worker']))?>">Worker guide</a></div>
      <div class="footer-col"><h4>Help &amp; safety</h4><a href="<?=e(url('help'))?>">Help Center &amp; FAQ</a><a href="<?=e(url('guide',['type'=>'publisher']))?>">Publisher guide</a><a href="<?=e(url('safety'))?>">Trust &amp; safety</a><a href="<?=e(url('contact'))?>">Contact support</a></div>
      <div class="footer-col"><h4>Account &amp; policies</h4><?php if($user): ?><a href="<?=e(url('dashboard'))?>">My workspace</a><?php else: ?><a href="<?=e(url('register'))?>">Create account</a><a href="<?=e(url('login'))?>">Log in</a><?php endif; ?><a href="<?=e(url('privacy'))?>">Privacy notice</a><a href="<?=e(url('terms'))?>">Terms of use</a></div>
    </div>
    <div class="footer-bottom"><span>© <?=date('Y')?> TaskHaat. All rights reserved.</span><span>Bangladesh · BDT · Light / Dark</span></div>
  </div>
</footer>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<button class="chat-launch" id="chatLaunch" type="button" aria-label="Open TaskHaat quick help" aria-expanded="false"><i data-lucide="message-circle"></i></button>
<section class="chat-panel" id="chatPanel" aria-label="TaskHaat quick help" aria-hidden="true">
  <div class="chat-head"><span class="mini-icon"><i data-lucide="messages-square"></i></span><div><strong>TaskHaat quick help</strong><small>Built-in answers · not a live agent</small></div><button id="chatClose" type="button" aria-label="Close help assistant"><i data-lucide="x"></i></button></div>
  <div class="chat-body" id="chatBody" aria-live="polite"><div class="chat-msg">Hi! I can explain task claims, proof requirements, campaign budgets, fees and withdrawals. What would you like to know?</div><div class="chat-suggestions"><button type="button" data-chat-question="How do I claim a task?">Claim a task</button><button type="button" data-chat-question="What proof can I submit?">Proof rules</button><button type="button" data-chat-question="How do withdrawals work?">Withdrawals</button></div><a class="chat-help-link" href="<?=e(url('help'))?>">Open Help Center <i data-lucide="arrow-up-right"></i></a></div>
  <form class="chat-form" id="chatForm"><input id="chatInput" type="text" maxlength="300" placeholder="Ask a question…" aria-label="Ask a question" required><button type="submit" aria-label="Send question"><i data-lucide="send"></i></button></form>
</section>
