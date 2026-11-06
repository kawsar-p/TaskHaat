<?php
$defaultType=(string)($_GET['type']??'worker');
if(!in_array($defaultType,['worker','publisher','both'],true)) $defaultType='worker';
?>
<section class="auth-layout auth-signup" aria-label="Create a TaskHaat account">
  <aside class="auth-aside">
    <a class="auth-brand" href="<?=e(url('home'))?>"><span class="auth-brand-mark"><i data-lucide="layers-3"></i></span>taskhaat</a>
    <span class="auth-kicker">GET STARTED</span>
    <h1>Make every task count.</h1>
    <p>Build your profile once, then access clear tasks, transparent proof reviews and reward tracking.</p>
    <div class="auth-benefits">
      <span><i data-lucide="shield-check"></i> Safer, moderated tasks</span>
      <span><i data-lucide="wallet"></i> Clear BDT rewards</span>
      <span><i data-lucide="list-checks"></i> Track every submission</span>
    </div>
    <div class="auth-aside-foot">Bangladesh-focused · BDT payments</div>
  </aside>

  <div class="auth-main">
    <a class="auth-back" href="<?=e(url('home'))?>"><i data-lucide="arrow-left"></i> Back to TaskHaat</a>
    <div class="auth-intro">
      <span class="auth-step">CREATE ACCOUNT</span>
      <h2>Your details</h2>
      <p>Enter accurate information to set up your account.</p>
    </div>

    <form method="post" class="auth-form" autocomplete="on">
      <?=csrf_field()?>
      <input type="hidden" name="action" value="register">
      <div class="auth-section-title">Personal information</div>
      <div class="auth-grid">
        <div class="field"><label for="firstName">First name <span class="req">*</span></label><input id="firstName" name="first_name" required maxlength="80" autocomplete="given-name" placeholder="First name"></div>
        <div class="field"><label for="lastName">Last name <span class="req">*</span></label><input id="lastName" name="last_name" required maxlength="80" autocomplete="family-name" placeholder="Last name"></div>
        <div class="field"><label for="registerEmail">Email address <span class="req">*</span></label><input id="registerEmail" name="email" type="email" required maxlength="190" autocomplete="email" placeholder="you@example.com"></div>
        <div class="field"><label for="registerPhone">Mobile number <span class="req">*</span></label><input id="registerPhone" name="phone" type="tel" inputmode="tel" pattern="[0-9+ -]{8,16}" required maxlength="16" autocomplete="tel" placeholder="01XXXXXXXXX"></div>
        <div class="field"><label for="country">Country <span class="req">*</span></label><select id="country" name="country" required><option value="Bangladesh" selected>Bangladesh</option><option value="Other">Other</option></select></div>
        <div class="field"><label for="district">District <span class="req">*</span></label><input id="district" name="district" required maxlength="100" placeholder="e.g. Dhaka"></div>
        <div class="field"><label for="city">City / Upazila</label><input id="city" name="city" maxlength="120" placeholder="e.g. Uttara"></div>
        <div class="field"><label for="occupation">Occupation</label><input id="occupation" name="occupation" maxlength="120" placeholder="Student, freelancer, business…"></div>
      </div>

      <div class="auth-section-title">TaskHaat profile</div>
      <div class="auth-grid">
        <div class="field full"><label for="userType">Account type <span class="req">*</span></label><select id="userType" name="user_type" required><option value="worker" <?=$defaultType==='worker'?'selected':''?>>Worker — find tasks and earn</option><option value="publisher" <?=$defaultType==='publisher'?'selected':''?>>Publisher — create task campaigns</option><option value="both" <?=$defaultType==='both'?'selected':''?>>Both — worker and publisher</option></select></div>
        <div class="field full"><label for="skills">Skills or interests</label><input id="skills" name="skills" maxlength="1000" placeholder="e.g. Survey, data entry, transcription, testing"><small>Separate multiple skills with commas.</small></div>
        <div class="field"><label for="organization">Organization / business</label><input id="organization" name="organization" maxlength="180" placeholder="Optional"></div>
        <div class="field"><label for="website">Website or portfolio</label><input id="website" name="website" type="url" maxlength="255" placeholder="https://example.com"></div>
        <div class="field"><label for="registerPassword">Password <span class="req">*</span></label><input id="registerPassword" name="password" type="password" minlength="10" required autocomplete="new-password" placeholder="At least 10 characters"></div>
        <div class="field"><label for="confirmPassword">Confirm password <span class="req">*</span></label><input id="confirmPassword" name="confirm_password" type="password" minlength="10" required autocomplete="new-password" placeholder="Enter password again"></div>
        <div class="field full"><label for="referralCode">Referral code</label><input id="referralCode" name="referral_code" maxlength="50" placeholder="Optional referral code"></div>
      </div>

      <label class="auth-consent"><input type="checkbox" name="terms_accepted" value="1" required><span>I agree to the <a href="<?=e(url('home'))?>#faq">TaskHaat terms</a>, Privacy Policy and prohibited-task rules.</span></label>
      <p class="auth-note">Your profile details are stored with your TaskHaat account. Never enter banking PINs, OTPs or account passwords in your profile.</p>
      <div class="auth-form-actions"><button type="submit" class="btn auth-submit">Create account <i data-lucide="arrow-right"></i></button></div>
    </form>
    <div class="auth-switch">Already have an account? <a href="<?=e(url('login'))?>">Log in</a></div>
  </div>
</section>
