<section class="auth-layout auth-login" aria-label="TaskHaat login">
  <aside class="auth-aside">
    <a class="auth-brand" href="<?=e(url('home'))?>"><span class="auth-brand-mark"><i data-lucide="layers-3"></i></span>taskhaat</a>
    <span class="auth-kicker">YOUR WORKSPACE</span>
    <h1>Good to see you again.</h1>
    <p>Sign in to continue managing tasks, proof submissions and your wallet activity.</p>
    <div class="auth-login-quote"><i data-lucide="quote"></i><strong>Small tasks. Real progress.</strong><small>One clear place for workers and publishers.</small></div>
    <div class="auth-aside-foot">Bangladesh-focused · BDT rewards</div>
  </aside>

  <div class="auth-main auth-login-main">
    <a class="auth-back" href="<?=e(url('home'))?>"><i data-lucide="arrow-left"></i> Back to TaskHaat</a>
    <div class="auth-intro">
      <span class="auth-step">ACCOUNT ACCESS</span>
      <h2>Log in</h2>
      <p>Use the email address linked to your TaskHaat account.</p>
    </div>
    <form method="post" class="auth-form auth-login-form" autocomplete="on">
      <?=csrf_field()?>
      <input type="hidden" name="action" value="login">
      <div class="field">
        <label for="loginEmail">Email address <span class="req">*</span></label>
        <input id="loginEmail" type="email" name="email" required autocomplete="username" placeholder="you@example.com">
      </div>
      <div class="field">
        <label for="loginPassword">Password <span class="req">*</span></label>
        <div class="auth-password-wrap">
          <input id="loginPassword" name="password" type="password" required autocomplete="current-password" placeholder="Enter your password">
          <button type="button" class="auth-eye" id="togglePassword" aria-label="Show password" aria-pressed="false"><i data-lucide="eye"></i></button>
        </div>
      </div>
      <div class="auth-login-options">
        <label><input type="checkbox" name="remember" value="1"> Remember me</label>
        <a href="mailto:help@taskhaat.local?subject=TaskHaat%20password%20recovery">Forgot password?</a>
      </div>
      <div class="auth-login-security"><i data-lucide="lock-keyhole"></i><span>Never share your TaskHaat password, banking PIN or OTP with anyone.</span></div>
      <div class="auth-form-actions"><button type="submit" class="btn red auth-submit">Log in <i data-lucide="arrow-right"></i></button></div>
    </form>
    <div class="auth-switch">New to TaskHaat? <a href="<?=e(url('register'))?>">Create an account</a></div>
  </div>
</section>
