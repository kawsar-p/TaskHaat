<?php $legalType=($legalType??'terms')==='privacy'?'privacy':'terms'; ?>
<section class="view active legal-page">
  <div class="container page-top"><span class="eyebrow">TaskHaat policies</span><h1><?=$legalType==='privacy'?'Privacy notice':'Terms of use'?></h1><p>Plain-language product policy summary for this TaskHaat project build.</p></div>
  <div class="container legal-content">
    <?php if($legalType==='privacy'): ?>
      <article class="legal-section"><h2>1. Information used to operate the service</h2><p>TaskHaat stores account details such as name, email, optional phone and profile information. It also stores campaign, claim, submission, wallet, withdrawal, notification and support-request records needed to operate the marketplace.</p></article>
      <article class="legal-section"><h2>2. Evidence and file uploads</h2><p>Workers may submit notes, URLs and permitted evidence files when a campaign requests them. Files are stored by the application and are visible only to the worker, the relevant publisher and authorized Admin users through the protected download route.</p></article>
      <article class="legal-section"><h2>3. Payment information</h2><p>This build records wallet balances and withdrawal requests. It does not connect to bKash, Nagad, Rocket or bank APIs. Avoid submitting full payment credentials or any PIN, password or OTP through TaskHaat forms.</p></article>
      <article class="legal-section"><h2>4. Notifications and support</h2><p>TaskHaat creates in-app notifications for account, campaign, submission and wallet events. Contact form submissions are stored in the Admin support inbox. External email delivery is not configured in this build.</p></article>
      <article class="legal-section"><h2>5. Data requests</h2><p>For a question about account information or a support request, contact TaskHaat through the <a href="<?=e(url('contact'))?>">support form</a>. Do not include your password or OTP.</p></article>
    <?php else: ?>
      <article class="legal-section"><h2>1. Use accurate account information</h2><p>Provide accurate contact details and keep your login credentials private. Accounts may be suspended where activity violates platform rules or creates a safety risk.</p></article>
      <article class="legal-section"><h2>2. Worker responsibilities</h2><p>Review campaign instructions before claiming a slot. Submit truthful, relevant evidence. Do not submit fabricated proof, automate prohibited actions or share another person’s private information.</p></article>
      <article class="legal-section"><h2>3. Publisher responsibilities</h2><p>Describe the task accurately, set a fair reward and slot count, publish clear proof requirements and review submissions against those requirements. Do not change a campaign’s requirements after workers have claimed it.</p></article>
      <article class="legal-section"><h2>4. Fees and wallet records</h2><p>Publisher and worker fee rates are configurable and are displayed in the Fees &amp; Rewards page. Rewards are credited after proof approval. Wallet records in this build are internal ledger entries and do not themselves represent an external bank or mobile-money transfer.</p></article>
      <article class="legal-section"><h2>5. Prohibited campaigns</h2><p>Credential collection, OTP or PIN requests, fake engagement, bulk account creation, spam, fraud, deceptive reviews and other prohibited activities are not allowed. See the <a href="<?=e(url('safety'))?>">Trust &amp; Safety rules</a>.</p></article>
      <article class="legal-section"><h2>6. Platform status</h2><p>This project build is under development. External payment gateways and email delivery are not connected. Production launch requires operational, security and legal review.</p></article>
    <?php endif; ?>
    <div class="legal-disclaimer">This page is a product-policy draft for the development build, not a substitute for legal advice or a final jurisdiction-specific policy review.</div>
    <a class="btn secondary" href="<?=e(url('contact'))?>">Ask a policy question</a>
  </div>
</section>
