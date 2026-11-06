USE taskhaat;

-- Phase 10 website management tables (additive).
CREATE TABLE IF NOT EXISTS site_content (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  content_key VARCHAR(120) NOT NULL,
  label VARCHAR(160) NOT NULL,
  content_group ENUM('branding','homepage','navigation','footer','general') NOT NULL DEFAULT 'homepage',
  content_type ENUM('text','textarea','url','boolean') NOT NULL DEFAULT 'text',
  content_value LONGTEXT NOT NULL,
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  updated_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_site_content_key (content_key),
  KEY idx_site_content_group_order (content_group,is_public,sort_order),
  CONSTRAINT fk_site_content_admin FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_site_content_public CHECK (is_public IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_faqs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  question VARCHAR(255) NOT NULL,
  faq_group ENUM('home','help') NOT NULL DEFAULT 'home',
  category VARCHAR(80) NOT NULL DEFAULT 'General',
  answer TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  updated_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_site_faq_group_question (faq_group,question),
  KEY idx_site_faq_active_order (faq_group,is_active,category,sort_order,id),
  CONSTRAINT fk_site_faq_admin FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_site_faq_active CHECK (is_active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_announcements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(160) NOT NULL,
  message VARCHAR(1200) NOT NULL,
  cta_label VARCHAR(80) NULL,
  cta_url VARCHAR(500) NULL,
  style ENUM('info','success','warning') NOT NULL DEFAULT 'info',
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  updated_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_site_announcement_schedule (is_active,starts_at,ends_at,sort_order),
  CONSTRAINT fk_site_announcement_admin FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT chk_site_announcement_active CHECK (is_active IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO site_content
(content_key,label,content_group,content_type,content_value,is_public,sort_order)
VALUES
('site_brand_name','Website brand name','branding','text','TaskHaat',1,1),
('topbar_message','Topbar message','navigation','text','Legitimate tasks. Clear proof. Fair rewards.',1,2),
('footer_description','Footer description','footer','textarea','A practical microtask marketplace for legitimate short work, transparent proof review and clear rewards.',1,1),
('home_hero_visible','Show hero section','homepage','boolean','1',1,1),
('home_hero_eyebrow','Hero eyebrow','homepage','text','A smarter way to get small work done',1,10),
('home_hero_title_primary','Hero title — first line','homepage','text','Micro tasks.',1,11),
('home_hero_title_accent','Hero title — accent line','homepage','text','Real progress.',1,12),
('home_hero_description','Hero description','homepage','textarea','Find flexible online tasks, submit clear proof, and earn rewards when your work is approved. Or publish a campaign and reach many workers at once.',1,13),
('home_stats_visible','Show live statistics','homepage','boolean','1',1,20),
('home_categories_visible','Show categories section','homepage','boolean','1',1,30),
('home_categories_eyebrow','Categories eyebrow','homepage','text','Explore by category',1,31),
('home_categories_title','Categories heading','homepage','text','Find a task that fits your skills',1,32),
('home_categories_description','Categories description','homepage','textarea','Short, clearly explained work across research, testing, data, content and more.',1,33),
('home_features_visible','Show features section','homepage','boolean','1',1,40),
('home_features_eyebrow','Features eyebrow','homepage','text','Built for everyday work',1,41),
('home_features_title','Features heading','homepage','text','A simple system, from start to finish',1,42),
('home_features_description','Features description','homepage','textarea','Keep task instructions, proof, decisions and rewards visible in one place.',1,43),
('home_how_visible','Show how-it-works section','homepage','boolean','1',1,50),
('home_how_eyebrow','How-it-works eyebrow','homepage','text','How TaskHaat works',1,51),
('home_how_title','How-it-works heading','homepage','text','Two sides. One clear workflow.',1,52),
('home_how_description','How-it-works description','homepage','textarea','Workers complete tasks; publishers distribute small jobs to multiple people and review the evidence.',1,53),
('home_tasks_visible','Show fresh tasks section','homepage','boolean','1',1,60),
('home_tasks_eyebrow','Fresh tasks eyebrow','homepage','text','Fresh opportunities',1,61),
('home_tasks_title','Fresh tasks heading','homepage','text','Tasks ready to explore',1,62),
('home_tasks_description','Fresh tasks description','homepage','textarea','Each campaign has a fixed reward per approved worker and a visible number of available slots.',1,63),
('home_mobile_visible','Show mobile showcase section','homepage','boolean','1',1,70),
('home_stories_visible','Show community stories section','homepage','boolean','1',1,80),
('home_stories_eyebrow','Stories eyebrow','homepage','text','Community stories',1,81),
('home_stories_title','Stories heading','homepage','text','Small tasks can make useful progress',1,82),
('home_stories_description','Stories description','homepage','textarea','Illustrative preview testimonials. Real reviews will come from verified completed tasks.',1,83),
('home_guides_visible','Show guides section','homepage','boolean','1',1,90),
('home_guides_eyebrow','Guides eyebrow','homepage','text','Guides & resources',1,91),
('home_guides_title','Guides heading','homepage','text','Know the rules before you begin',1,92),
('home_guides_description','Guides description','homepage','textarea','Short guides to help workers and publishers use the marketplace responsibly.',1,93),
('home_safety_visible','Show trust and safety section','homepage','boolean','1',1,100),
('home_safety_eyebrow','Safety eyebrow','homepage','text','Trust and safety',1,101),
('home_safety_title','Safety heading','homepage','text','Clear work. Responsible participation.',1,102),
('home_safety_description','Safety description','homepage','textarea','TaskHaat is intended for legitimate surveys, usability testing, research, data entry and similar tasks that respect privacy and platform rules.',1,103),
('home_faq_visible','Show FAQ section','homepage','boolean','1',1,110),
('home_faq_eyebrow','FAQ eyebrow','homepage','text','Need a little clarity?',1,111),
('home_faq_title','FAQ heading','homepage','text','Frequently asked questions',1,112),
('home_faq_description','FAQ description','homepage','textarea','Quick answers about task slots, proof review, rewards and publishing campaigns.',1,113),
('home_cta_visible','Show final call-to-action section','homepage','boolean','1',1,120),
('home_cta_title','Final CTA heading','homepage','text','Ready to make progress, one task at a time?',1,121),
('home_cta_description','Final CTA description','homepage','textarea','Join as a worker or bring a small campaign to a wider group.',1,122);

-- Upgrade an earlier Phase 10 CMS FAQ table in place when the first migration is already installed.
SET @faq_group_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_faqs' AND COLUMN_NAME='faq_group');
SET @faq_group_sql = IF(@faq_group_exists=0, 'ALTER TABLE site_faqs ADD COLUMN faq_group ENUM(''home'',''help'') NOT NULL DEFAULT ''home'' AFTER question', 'SELECT 1');
PREPARE faq_group_stmt FROM @faq_group_sql; EXECUTE faq_group_stmt; DEALLOCATE PREPARE faq_group_stmt;
SET @faq_category_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_faqs' AND COLUMN_NAME='category');
SET @faq_category_sql = IF(@faq_category_exists=0, 'ALTER TABLE site_faqs ADD COLUMN category VARCHAR(80) NOT NULL DEFAULT ''General'' AFTER faq_group', 'SELECT 1');
PREPARE faq_category_stmt FROM @faq_category_sql; EXECUTE faq_category_stmt; DEALLOCATE PREPARE faq_category_stmt;
SET @faq_unique_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='site_faqs' AND INDEX_NAME='uq_site_faq_group_question');
SET @faq_unique_sql = IF(@faq_unique_exists=0, 'ALTER TABLE site_faqs ADD UNIQUE KEY uq_site_faq_group_question (faq_group,question)', 'SELECT 1');
PREPARE faq_unique_stmt FROM @faq_unique_sql; EXECUTE faq_unique_stmt; DEALLOCATE PREPARE faq_unique_stmt;

INSERT IGNORE INTO site_content (content_key,label,content_group,content_type,content_value,is_public,sort_order) VALUES
('page_title_home','Homepage SEO title','general','text','TaskHaat — Microtasks. Real progress.',1,200),
('page_description_home','Homepage SEO description','general','textarea','Find legitimate microtasks in Bangladesh. Complete tasks, submit proof and earn rewards after approval, or publish a campaign for multiple workers.',1,201),
('page_title_browse','Browse page SEO title','general','text','Find tasks — TaskHaat',1,210),
('page_description_browse','Browse page SEO description','general','textarea','Browse approved TaskHaat microtasks by reward, category, deadline and available worker slots.',1,211),
('browse_page_eyebrow','Browse page eyebrow','general','text','Task marketplace',1,212),
('browse_page_heading','Browse page heading','general','text','Find your next microtask',1,213),
('browse_page_intro','Browse page introduction','general','textarea','Compare reward, effort, deadline and available worker slots before claiming.',1,214),
('page_title_fees','Fees page SEO title','general','text','Fees & rewards — TaskHaat',1,220),
('page_description_fees','Fees page SEO description','general','textarea','Understand TaskHaat publisher campaign fees, worker rewards and manual withdrawal steps.',1,221),
('fees_page_eyebrow','Fees page eyebrow','general','text','Transparent pricing',1,222),
('fees_page_heading','Fees page heading','general','text','Fees & reward calculation',1,223),
('fees_page_intro','Fees page introduction','general','textarea','Know how campaign funding and worker rewards are calculated before you publish or claim a task. Rates are configurable by TaskHaat Admin.',1,224),
('page_title_help','Help page SEO title','general','text','Help Center — TaskHaat',1,230),
('page_description_help','Help page SEO description','general','textarea','Find answers about TaskHaat accounts, task claims, proof reviews, wallets, payments and safety.',1,231),
('help_page_eyebrow','Help page eyebrow','general','text','TaskHaat support',1,232),
('help_page_heading','Help page heading','general','text','Help Center',1,233),
('help_page_intro','Help page introduction','general','textarea','Practical answers for Workers and Publishers, plus clear information about reviews, rewards and safety.',1,234),
('page_title_guide_worker','Worker guide SEO title','general','text','Worker guide — TaskHaat',1,240),
('page_description_guide_worker','Worker guide SEO description','general','textarea','Learn how to choose legitimate TaskHaat tasks, submit proof and track approved rewards.',1,241),
('page_title_guide_publisher','Publisher guide SEO title','general','text','Publisher guide — TaskHaat',1,242),
('page_description_guide_publisher','Publisher guide SEO description','general','textarea','Learn how to plan, fund and manage a multi-worker TaskHaat campaign.',1,243),
('page_title_safety','Safety page SEO title','general','text','Trust & safety — TaskHaat',1,250),
('page_description_safety','Safety page SEO description','general','textarea','Read TaskHaat rules for legitimate tasks, privacy, responsible evidence and fair reviews.',1,251),
('safety_page_eyebrow','Safety page eyebrow','general','text','Trust & safety',1,252),
('safety_page_heading','Safety page heading','general','text','Clear work. Responsible participation.',1,253),
('safety_page_intro','Safety page introduction','general','textarea','TaskHaat is intended for legitimate surveys, usability testing, research, data entry, transcription and similar work that respects privacy and platform rules.',1,254),
('page_title_contact','Contact page SEO title','general','text','Contact support — TaskHaat',1,260),
('page_description_contact','Contact page SEO description','general','textarea','Contact TaskHaat support about accounts, campaigns, submissions, wallets or safety concerns.',1,261),
('contact_page_eyebrow','Contact page eyebrow','general','text','Contact TaskHaat',1,262),
('contact_page_heading','Contact page heading','general','text','How can we help?',1,263),
('contact_page_intro','Contact page introduction','general','textarea','Send a support request about an account, campaign, submission, wallet or safety concern.',1,264),
('page_title_privacy','Privacy page SEO title','general','text','Privacy notice — TaskHaat',1,270),
('page_description_privacy','Privacy page SEO description','general','textarea','Read how the TaskHaat development build stores account, campaign, submission, wallet and support information.',1,271),
('privacy_page_heading','Privacy page heading','general','text','Privacy notice',1,272),
('privacy_page_intro','Privacy page introduction','general','textarea','Plain-language product privacy summary for this TaskHaat project build.',1,273),
('page_title_terms','Terms page SEO title','general','text','Terms of use — TaskHaat',1,280),
('page_description_terms','Terms page SEO description','general','textarea','Read the TaskHaat worker, publisher, fee, wallet and prohibited-task rules.',1,281),
('terms_page_heading','Terms page heading','general','text','Terms of use',1,282),
('terms_page_intro','Terms page introduction','general','textarea','Plain-language product terms summary for this TaskHaat project build.',1,283);
INSERT IGNORE INTO site_faqs (faq_group,category,question,answer,sort_order,is_active) VALUES
('home','General','Can multiple workers complete the same task?','Yes. A publisher sets the number of worker slots. Each worker claims one available slot and submits a separate proof. The same worker cannot claim the same campaign more than once.',10,1),
('home','General','When do workers receive rewards?','After a submission is approved, the configured platform fee is deducted and the net reward is credited to the worker''s Earned Wallet. The live platform will show the fee before work is claimed.',20,1),
('home','General','What proof can a worker submit?','Depending on the campaign: written answers, a public URL, a screenshot or an allowed attachment. Never submit passwords, OTPs, private account credentials or sensitive personal information.',30,1),
('home','General','How does a publisher set a campaign budget?','Budget is calculated from reward per worker multiplied by the total worker slots, plus the platform service fee. The amount should be reserved before the campaign becomes available.',40,1),
('home','General','Which tasks are prohibited?','TaskHaat does not allow fake views, likes, follows, reviews, bulk Gmail/account creation, credential handover, OTP collection, spam or deceptive activity that manipulates third-party platforms.',50,1),
('help','Getting started','How do I create a TaskHaat account?','Choose Get started, enter your personal details, select Worker, Publisher or Both, accept the platform rules and create your account. Use an email address you can access.',110,1),
('help','Getting started','Can I use one account as both a Worker and Publisher?','Yes. Select Both during registration. You can switch between the Worker and Publisher workspaces from the workspace selector.',120,1),
('help','Workers','How do I claim a task?','Open Find Tasks, review the reward, deadline, available slots and proof instructions, then open the task and claim one slot. A worker can claim each campaign only once.',210,1),
('help','Workers','What happens after I submit proof?','Your submission appears in My Submissions with Pending status. The publisher reviews it and either approves it or rejects it with feedback.',220,1),
('help','Workers','When is my reward available?','After approval, the worker fee saved for that campaign is deducted and the net reward is credited to your available wallet balance.',230,1),
('help','Workers','How do I request a withdrawal?','Open Worker → Wallet & withdrawals, choose a supported method, enter your payout details and submit a request. Admin reviews and manually processes it.',240,1),
('help','Publishers','How is a campaign budget calculated?','Reward per worker multiplied by worker slots gives the reward pool. The publisher service fee is added to that pool. The total is reserved from your deposit wallet when you submit the campaign.',310,1),
('help','Publishers','Can one campaign have multiple workers?','Yes. The publisher chooses the number of slots. Each unique worker can claim one slot and submit separate evidence.',320,1),
('help','Publishers','Can I edit a campaign after workers claim it?','Campaign requirements are locked once workers have claimed slots, so existing workers are not asked to complete changed work. Pending or rejected campaigns with no claims may be edited.',330,1),
('help','Payments','Are bKash, Nagad or bank payments automatic?','No. This build records wallet balances and withdrawal requests. An authorized Admin must verify and complete external payments manually; no payment gateway is connected.',410,1),
('help','Safety','What tasks are prohibited?','Tasks involving credential or OTP collection, fake engagement, account trading, spam, deceptive reviews, bulk account creation or other manipulation of third-party platforms are prohibited.',510,1),
('help','Safety','What should I avoid sharing in proof?','Never include passwords, OTPs, bank PINs, full payment credentials or unrelated personal information. Crop or blur private details before uploading evidence.',520,1);

-- Safe to run once on an existing installation. All statements are additive.
