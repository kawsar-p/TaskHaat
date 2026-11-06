<?php
$pubRate=(float)get_setting($pdo,'publisher_fee_rate','8');
$workerRate=(float)get_setting($pdo,'worker_fee_rate','8');
$sampleGross=50.00;$sampleWorkerFee=round($sampleGross*$workerRate/100,2);$sampleNet=round($sampleGross-$sampleWorkerFee,2);$samplePublisherFee=round(50.00*$pubRate/100,2)*20;
?>
<section class="view active fees-page" data-publisher-fee="<?=e($pubRate)?>" data-worker-fee="<?=e($workerRate)?>">
  <div class="container page-top"><span class="eyebrow">Transparent pricing</span><h1>Fees &amp; reward calculation</h1><p>Know how campaign funding and worker rewards are calculated before you publish or claim a task. Rates are configurable by TaskHaat Admin.</p></div>
  <div class="container fees-content">
    <div class="fees-intro-grid">
      <article class="fees-intro-card"><span class="fees-intro-icon"><i data-lucide="wallet"></i></span><div><strong>Publisher fee</strong><p><?=e(number_format($pubRate,2))?>% is added to the campaign reward pool when a campaign is funded.</p></div></article>
      <article class="fees-intro-card"><span class="fees-intro-icon warm"><i data-lucide="badge-check"></i></span><div><strong>Worker fee</strong><p><?=e(number_format($workerRate,2))?>% is deducted from a reward after a publisher approves the proof.</p></div></article>
    </div>

    <div class="fees-calculator-grid">
      <section class="panel fees-estimator-panel">
        <div class="panel-head"><div><h3>Publisher campaign estimator</h3><p>Estimate the amount needed to fund a multi-worker campaign.</p></div><span class="pill green">Fee <?=e(number_format($pubRate,2))?>%</span></div>
        <div class="panel-body">
          <div class="form-grid fees-input-grid">
            <div class="field"><label for="feeReward">Reward per worker (BDT)</label><input id="feeReward" type="number" value="50" min="1" max="100000" step="0.01"></div>
            <div class="field"><label for="feeSlots">Worker slots</label><input id="feeSlots" type="number" value="20" min="1" max="100000" step="1"></div>
          </div>
          <div class="fees-breakdown">
            <div><span>Worker reward pool</span><strong id="feeBase"><?=money(1000)?></strong></div>
            <div><span>Publisher service fee (<?=e(number_format($pubRate,2))?>%)</span><strong id="feeCharge"><?=money($samplePublisherFee)?></strong></div>
            <div class="fees-total"><span>Total funding required</span><strong id="feeTotal"><?=money(1000+$samplePublisherFee)?></strong></div>
          </div>
          <p class="fees-formula"><strong>Formula:</strong> the per-worker publisher fee is rounded to 2 decimals, then added to each worker reward and multiplied by the number of slots.</p>
        </div>
      </section>

      <section class="panel worker-example-panel">
        <div class="panel-head"><div><h3>Worker reward example</h3><p>Illustration based on a <?=money($sampleGross)?> approved task.</p></div><span class="pill amber">Fee <?=e(number_format($workerRate,2))?>%</span></div>
        <div class="panel-body">
          <div class="worker-example-gross"><span>Gross task reward</span><strong><?=money($sampleGross)?></strong></div>
          <div class="worker-example-line"><span>Worker fee (<?=e(number_format($workerRate,2))?>%)</span><strong>− <?=money($sampleWorkerFee)?></strong></div>
          <div class="worker-example-net"><span>Estimated net reward</span><strong id="workerNetExample"><?=money($sampleNet)?></strong><small>Credited to the worker's available wallet after approval.</small></div>
          <p class="fees-formula">The worker fee is deducted from the reward shown on the task page. The task's gross reward does not change.</p>
        </div>
      </section>
    </div>

    <section class="panel fees-payment-panel">
      <div class="panel-head"><div><h3>Bangladesh payment methods</h3><p>Withdrawal requests are submitted from the Worker wallet and reviewed by Admin.</p></div></div>
      <div class="panel-body">
        <div class="payment-method-grid">
          <article class="payment-method"><img class="payment-method-logo bkash-logo" src="https://cdn.brandfetch.io/id_4D40okd/w/400/h/400/theme/dark/icon.jpeg?c=1bxid64Mup7aczewSAYMX&t=1789794450956" alt="bKash logo" loading="lazy"><div><strong>bKash</strong><small>Mobile financial service</small></div></article>
          <article class="payment-method"><img class="payment-method-logo nagad-logo" src="https://cdn.brandfetch.io/idPKXOsXfF/w/900/h/900/theme/dark/logo.png?c=1bxid64Mup7aczewSAYMX&t=1790152991630" alt="Nagad logo" loading="lazy"><div><strong>Nagad</strong><small>Mobile financial service</small></div></article>
          <article class="payment-method"><img class="payment-method-logo rocket-logo" src="https://www.dutchbanglabank.com/img/slides/mobile-Banking-Billboard.jpg" alt="Rocket by Dutch-Bangla Bank logo" loading="lazy"><div><strong>Rocket</strong><small>Mobile financial service</small></div></article>
          <article class="payment-method"><span class="payment-method-mark bank"><i data-lucide="landmark"></i></span><div><strong>Bank transfer</strong><small>Bank account payout</small></div></article>
        </div>
        <div class="form-note fees-manual-note"><i data-lucide="info"></i><span><strong>Manual processing:</strong> This build records withdrawal requests and lets Admin mark them paid or rejected. It does not connect to bKash, Nagad, Rocket or bank APIs, and it does not automatically transfer money. Any external payment must be verified and completed by an authorized operator.</span></div>
      </div>
    </section>

    <section class="fees-workflow-section">
      <div class="section-head"><div><span class="eyebrow">How settlement works</span><h2>From campaign funding to worker payout</h2><p>Every wallet movement is recorded in the platform ledger.</p></div></div>
      <div class="fees-workflow-grid">
        <article class="fees-workflow-step"><span>01</span><i data-lucide="wallet-cards"></i><h3>Publisher funds campaign</h3><p>The reward pool and publisher fee are reserved from the publisher's deposit wallet when the campaign is submitted.</p></article>
        <article class="fees-workflow-step"><span>02</span><i data-lucide="clipboard-check"></i><h3>Worker submits proof</h3><p>A worker claims one slot, completes the task and submits the required notes, URL or permitted evidence file.</p></article>
        <article class="fees-workflow-step"><span>03</span><i data-lucide="badge-check"></i><h3>Publisher approves</h3><p>After approval, the worker fee is deducted, the net reward is credited and platform commission is recorded.</p></article>
        <article class="fees-workflow-step"><span>04</span><i data-lucide="send"></i><h3>Worker requests withdrawal</h3><p>The worker requests a payout method and amount. Admin reviews the request and records its final status.</p></article>
      </div>
    </section>

    <section class="fees-note-grid">
      <div class="fees-note"><i data-lucide="shield-check"></i><div><strong>Clear records</strong><p>Wallet credits, debits, reserves and withdrawal holds are recorded as ledger transactions.</p></div></div>
      <div class="fees-note"><i data-lucide="sliders-horizontal"></i><div><strong>Configurable rates</strong><p>Admin can update publisher and worker fee rates. Campaigns retain the rates saved when they were created.</p></div></div>
    </section>
  </div>
</section>

<style>
.payment-method-logo{display:block;width:64px;height:44px;flex:0 0 64px;object-fit:contain;background:#fff;padding:3px;border:1px solid #EEF1EF}
.payment-method-logo.bkash-logo{width:44px;flex-basis:44px;height:44px;border:0}
.payment-method-logo.rocket-logo{width:76px;flex-basis:76px;object-fit:contain}
html[data-theme="dark"] .payment-method-logo{background:#fff;border-color:#DCE5E1}
</style>
