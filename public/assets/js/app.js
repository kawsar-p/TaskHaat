(() => {
  const $ = (s, root=document) => root.querySelector(s);
  const $$ = (s, root=document) => [...root.querySelectorAll(s)];
  const iconify = () => { if (window.lucide) window.lucide.createIcons(); };
  iconify();

  const html = document.documentElement;

  // Keep the reader at the same position after a manual browser refresh.
  // Normal link navigation and Back/Forward keep their native browser behaviour.
  const scrollStorageKey = 'taskhaat-scroll:' + location.pathname + location.search;
  const navInfo = performance.getEntriesByType?.('navigation')?.[0];
  let refreshScrollY = null;
  try {
    if (navInfo?.type === 'reload') {
      const storedPosition = sessionStorage.getItem(scrollStorageKey);
      if (storedPosition !== null && Number.isFinite(Number(storedPosition))) {
        refreshScrollY = Math.max(0, Number(storedPosition));
        history.scrollRestoration = 'manual';
      }
    }
  } catch (_) { /* Storage can be unavailable in restricted browser contexts. */ }

  window.addEventListener('pagehide', () => {
    try { sessionStorage.setItem(scrollStorageKey, String(window.scrollY || 0)); }
    catch (_) { /* Keep page navigation working when storage is disabled. */ }
  });

  const restoreRefreshPosition = () => {
    if (refreshScrollY === null) return;
    const restore = () => {
      const maximum = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
      window.scrollTo(0, Math.min(refreshScrollY, maximum));
    };
    requestAnimationFrame(() => requestAnimationFrame(restore));
    // A second instant correction covers late font/image layout without animated jumping.
    window.setTimeout(restore, 120);
    try { sessionStorage.removeItem(scrollStorageKey); } catch (_) {}
    refreshScrollY = null;
    history.scrollRestoration = 'auto';
  };
  if (document.readyState === 'complete') restoreRefreshPosition();
  else window.addEventListener('load', restoreRefreshPosition, {once:true});

  const savedTheme = localStorage.getItem('taskhaat-theme');
  if (savedTheme === 'dark' || savedTheme === 'light') html.dataset.theme = savedTheme;
  const themeButton = $('#themeToggle');
  const updateThemeIcon = () => {
    if (!themeButton) return;
    themeButton.innerHTML = `<i data-lucide="${html.dataset.theme === 'dark' ? 'sun' : 'moon'}"></i>`;
    iconify();
  };
  updateThemeIcon();
  themeButton?.addEventListener('click', () => {
    html.dataset.theme = html.dataset.theme === 'dark' ? 'light' : 'dark';
    localStorage.setItem('taskhaat-theme', html.dataset.theme);
    updateThemeIcon();
  });

  // Smooth page navigation; respect the visitor's reduced-motion preference.
  const scrollTopButton = $('#scrollToTop');
  const scrollBottomButton = $('#scrollToBottom');
  const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;
  const updateScrollControls = () => {
    const maxScroll = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
    const current = window.scrollY || document.documentElement.scrollTop || 0;
    if (scrollTopButton) scrollTopButton.hidden = current < 220;
    if (scrollBottomButton) scrollBottomButton.hidden = maxScroll < 220 || current >= maxScroll - 140;
  };
  const requestScrollControlUpdate = () => {
    if (requestScrollControlUpdate.pending) return;
    requestScrollControlUpdate.pending = true;
    requestAnimationFrame(() => {
      requestScrollControlUpdate.pending = false;
      updateScrollControls();
    });
  };
  scrollTopButton?.addEventListener('click', () => {
    window.scrollTo({top:0,left:0,behavior:reducedMotion?'auto':'smooth'});
  });
  scrollBottomButton?.addEventListener('click', () => {
    window.scrollTo({top:document.documentElement.scrollHeight,left:0,behavior:reducedMotion?'auto':'smooth'});
  });
  window.addEventListener('scroll', requestScrollControlUpdate, {passive:true});
  window.addEventListener('resize', requestScrollControlUpdate, {passive:true});
  window.addEventListener('load', requestScrollControlUpdate, {once:true});
  updateScrollControls();

  const mobileMenu = $('#mobileMenu');
  const navLinks = $('#navLinks');
  const setNavOpen = open => {
    navLinks?.classList.toggle('open', open);
    mobileMenu?.setAttribute('aria-expanded', String(open));
    mobileMenu?.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    if (mobileMenu) {
      mobileMenu.innerHTML = `<i data-lucide="${open ? 'x' : 'menu'}"></i>`;
      iconify();
    }
  };
  mobileMenu?.addEventListener('click', () => setNavOpen(!navLinks?.classList.contains('open')));
  navLinks?.querySelectorAll('a').forEach(link => link.addEventListener('click', () => setNavOpen(false)));
  document.addEventListener('keydown', event => { if (event.key === 'Escape') setNavOpen(false); });
  document.addEventListener('click', event => {
    if (navLinks?.classList.contains('open') && !event.target.closest('.nav-inner')) setNavOpen(false);
  });

  // Search controls: reveal the clear affordance only while a query exists.
  $$('input[type="search"]').forEach(input => {
    const clear = input.parentElement?.querySelector(`[data-clear="${input.id}"]`) ||
      input.parentElement?.querySelector('.clear-search');
    const sync = () => clear?.classList.toggle('visible', Boolean(input.value));
    input.addEventListener('input', sync);
    clear?.addEventListener('click', () => { input.value = ''; sync(); input.dispatchEvent(new Event('input', {bubbles:true})); input.focus(); });
    input.form?.addEventListener('reset', () => setTimeout(sync, 0));
    sync();
  });

  // Login password visibility control, matching the supplied reference auth UI.
  const passwordToggle = $('#togglePassword');
  const loginPassword = $('#loginPassword');
  passwordToggle?.addEventListener('click', () => {
    if (!loginPassword) return;
    const show = loginPassword.type === 'password';
    loginPassword.type = show ? 'text' : 'password';
    passwordToggle.innerHTML = `<i data-lucide="${show ? 'eye-off' : 'eye'}"></i>`;
    passwordToggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    passwordToggle.setAttribute('aria-pressed', String(show));
    iconify();
  });

  // Local help assistant: deterministic built-in answers, not an AI or live agent.
  const chatPanel = $('#chatPanel');
  const chatLaunch = $('#chatLaunch');
  const chatClose = $('#chatClose');
  const chatForm = $('#chatForm');
  const chatBody = $('#chatBody');
  const chatInput = $('#chatInput');
  const setChatOpen = open => {
    chatPanel?.classList.toggle('open', open);
    chatPanel?.setAttribute('aria-hidden', String(!open));
    chatLaunch?.setAttribute('aria-expanded', String(open));
    if (open) chatInput?.focus();
  };
  const appendChatMessage = (message, isUser=false) => {
    if (!chatBody) return;
    const node = document.createElement('div');
    node.className = `chat-msg${isUser ? ' user' : ''}`;
    node.textContent = message;
    chatBody.appendChild(node);
    chatBody.scrollTop = chatBody.scrollHeight;
  };
  const answerHelp = question => {
    const q = String(question || '').toLowerCase();
    if (q.includes('proof') || q.includes('evidence') || q.includes('screenshot')) {
      return 'Each campaign states its accepted proof format. Submit only relevant notes, URLs or permitted files. Hide unrelated personal information and never share passwords, OTPs, PINs or private account credentials.';
    }
    if (q.includes('withdraw') || q.includes('payout') || q.includes('bkash') || q.includes('nagad') || q.includes('rocket')) {
      return 'Submit a withdrawal request from Worker → Wallet & withdrawals. Admin reviews the request and records the result. This build does not automatically send bKash, Nagad, Rocket or bank payments.';
    }
    if (q.includes('publisher') || q.includes('campaign') || q.includes('budget') || q.includes('slot')) {
      return 'A Publisher sets reward per worker and total worker slots. The reward pool is reward × slots; the publisher service fee is added. Funds are reserved from the deposit wallet when the campaign is submitted.';
    }
    if (q.includes('claim') || q.includes('worker') || q.includes('task')) {
      return 'Open Find Tasks, compare reward, deadline, remaining slots and proof rules, then claim one available slot. A worker account can claim each campaign only once.';
    }
    if (q.includes('fee') || q.includes('commission') || q.includes('reward')) {
      return 'Publisher and worker rates are shown on Fees & rewards. Publisher fee is added to the campaign budget; worker fee is deducted before an approved net reward is credited.';
    }
    if (q.includes('contact') || q.includes('support') || q.includes('help')) {
      return 'Use Contact Support to send a request. It is stored in the Admin Support Inbox. This local build does not send an external email or connect to a live support agent.';
    }
    if (q.includes('password') || q.includes('login') || q.includes('account')) {
      return 'Use the email address registered with TaskHaat. Keep your password private. Automated email verification and password reset delivery are not connected in this build.';
    }
    return 'I can answer common questions about claiming tasks, proof requirements, campaign budgets, fees and withdrawals. For an issue that needs Admin review, use the Contact Support form.';
  };
  const askQuickHelp = question => {
    const cleaned = String(question || '').trim();
    if (!cleaned) return;
    appendChatMessage(cleaned, true);
    appendChatMessage(answerHelp(cleaned));
  };
  chatLaunch?.addEventListener('click', () => setChatOpen(!chatPanel?.classList.contains('open')));
  chatClose?.addEventListener('click', () => setChatOpen(false));
  chatForm?.addEventListener('submit', event => {
    event.preventDefault();
    const question = chatInput?.value.trim();
    if (!question) return;
    askQuickHelp(question);
    chatInput.value = '';
  });
  chatBody?.addEventListener('click', event => {
    const suggestion = event.target.closest('[data-chat-question]');
    if (!suggestion) return;
    askQuickHelp(suggestion.dataset.chatQuestion || '');
  });

  // FAQ search supports both the Homepage FAQ and the dedicated Help Center.
  const faqSearchInputs = ['#homeFaqSearch', '#helpFaqSearch'].map(selector => $(selector)).filter(Boolean);
  faqSearchInputs.forEach(input => {
    const updateFaqResults = () => {
      const query = input.value.trim().toLocaleLowerCase();
      const page = input.closest('main') || document;
      const lists = $$('[data-faq-list]', page);
      lists.forEach(list => {
        const items = $$('[data-faq-item]', list);
        let visible = 0;
        items.forEach(item => {
          const text = item.textContent.toLocaleLowerCase();
          const match = query === '' || text.includes(query);
          item.hidden = !match;
          if (match) visible++;
        });
        const empty = $('[data-faq-empty]', list);
        if (empty) empty.hidden = visible > 0;
        const topic = list.closest('.help-topic');
        if (topic) topic.hidden = visible === 0;
      });
    };
    input.addEventListener('input', updateFaqResults);
    updateFaqResults();
  });
  $('#workspaceSwitch')?.addEventListener('change', e => {
    const params = new URLSearchParams(location.search);
    params.set('route', 'dashboard'); params.set('as', e.target.value); params.set('tab', 'overview');
    location.href = `index.php?${params.toString()}`;
  });
  $$('.faq-q').forEach(button => button.addEventListener('click', () => {
    const item = button.closest('.faq-item');
    item?.classList.toggle('open');
    button.setAttribute('aria-expanded', String(item?.classList.contains('open')));
  }));

  const feeReward = $('#feeReward'), feeSlots = $('#feeSlots');
  const feeBase = $('#feeBase'), feeCharge = $('#feeCharge'), feeTotal = $('#feeTotal');
  const workerNet = $('#workerNetExample');
  const publisherRate = Number(document.body.dataset.publisherFee || document.querySelector('[data-publisher-fee]')?.dataset.publisherFee || 8);
  const workerRate = Number(document.body.dataset.workerFee || document.querySelector('[data-worker-fee]')?.dataset.workerFee || 8);
  const money = value => '৳' + Number(value || 0).toLocaleString('en-BD', {minimumFractionDigits:2, maximumFractionDigits:2});
  const calculateFees = () => {
    if (!feeReward || !feeSlots) return;
    const reward = Math.max(0, Number(feeReward.value || 0));
    const slots = Math.max(0, Math.floor(Number(feeSlots.value || 0)));
    const base = reward * slots;
    if (feeBase) feeBase.textContent = money(base);
    const publisherFeePerWorker = Math.round((reward * publisherRate / 100 + Number.EPSILON) * 100) / 100;
    const workerFeePerWorker = Math.round((reward * workerRate / 100 + Number.EPSILON) * 100) / 100;
    if (feeCharge) feeCharge.textContent = money(publisherFeePerWorker * slots);
    if (feeTotal) feeTotal.textContent = money((reward + publisherFeePerWorker) * slots);
    if (workerNet) workerNet.textContent = money(reward - workerFeePerWorker);
  };
  feeReward?.addEventListener('input', calculateFees); feeSlots?.addEventListener('input', calculateFees);
  if (feeReward && feeSlots) calculateFees();

  // Publisher campaign estimator — mirrors the server's base reward + publisher fee formula.
  const campaignBudget = $('#campaignBudgetCalculator');
  const campaignReward = $('#campaignReward');
  const campaignSlots = $('#campaignSlots');
  if (campaignBudget && campaignReward && campaignSlots) {
    const feeRate = Math.max(0, Number(campaignBudget.dataset.feeRate || 0));
    const available = Math.max(0, Number(campaignBudget.dataset.available || 0));
    const baseEl = $('#campaignBase');
    const feeEl = $('#campaignFee');
    const totalEl = $('#campaignTotal');
    const availableEl = $('#campaignAvailable');
    const meter = $('#campaignBudgetMeter');
    const hint = $('#campaignBudgetHint');
    const money = amount => '৳' + Number(amount || 0).toLocaleString('en-BD', {
      minimumFractionDigits: 2, maximumFractionDigits: 2
    });
    const updateCampaignBudget = () => {
      const reward = Math.max(0, Number(campaignReward.value || 0));
      const slots = Math.max(0, Math.floor(Number(campaignSlots.value || 0)));
      const base = reward * slots;
      const feePerWorker = Math.round((reward * feeRate / 100 + Number.EPSILON) * 100) / 100;
      const fee = feePerWorker * slots;
      const total = base + fee;
      const valid = reward > 0 && slots > 0;
      if (baseEl) baseEl.textContent = money(base);
      if (feeEl) feeEl.textContent = money(fee);
      if (totalEl) totalEl.textContent = money(total);
      if (availableEl) availableEl.textContent = money(available);
      if (meter) meter.style.width = (available > 0 ? Math.min(100, total / available * 100) : 0) + '%';
      if (!hint) return;
      hint.classList.remove('is-short', 'is-enough');
      if (!valid) {
        hint.textContent = 'Enter a reward and at least one worker slot to calculate the campaign budget.';
      } else if (total > available) {
        hint.textContent = 'Insufficient deposit balance. You need ' + money(total - available) + ' more before submitting this campaign.';
        hint.classList.add('is-short');
      } else {
        hint.textContent = 'Your deposit wallet covers this estimate. Funds will be reserved after you submit the campaign.';
        hint.classList.add('is-enough');
      }
    };
    campaignReward.addEventListener('input', updateCampaignBudget);
    campaignSlots.addEventListener('input', updateCampaignBudget);
    updateCampaignBudget();
  }

  $$('form').forEach(form => {
    form.addEventListener('submit', event => {
      const submitter = event.submitter;
      if (submitter?.dataset.confirm && !confirm(submitter.dataset.confirm)) event.preventDefault();
    });
  });

  const toast = $('#toast');
  $$('.flash').forEach(f => {
    if (toast) { toast.textContent = f.textContent.trim(); toast.classList.add('show'); setTimeout(() => toast.classList.remove('show'), 3600); }
  });
})();
