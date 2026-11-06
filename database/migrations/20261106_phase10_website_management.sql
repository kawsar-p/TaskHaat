
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
  answer TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  updated_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_site_faq_active_order (is_active,sort_order,id),
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

INSERT IGNORE INTO site_faqs (question,answer,sort_order,is_active) VALUES
('Can multiple workers complete the same task?','Yes. A publisher sets the number of worker slots. Each worker claims one available slot and submits a separate proof. The same worker cannot claim the same campaign more than once.',10,1),
('When do workers receive rewards?','After a submission is approved, the configured platform fee is deducted and the net reward is credited to the worker''s Earned Wallet. The live platform will show the fee before work is claimed.',20,1),
('What proof can a worker submit?','Depending on the campaign: written answers, a public URL, a screenshot or an allowed attachment. Never submit passwords, OTPs, private account credentials or sensitive personal information.',30,1),
('How does a publisher set a campaign budget?','Budget is calculated from reward per worker multiplied by the total worker slots, plus the platform service fee. The amount should be reserved before the campaign becomes available.',40,1),
('Which tasks are prohibited?','TaskHaat does not allow fake views, likes, follows, reviews, bulk Gmail/account creation, credential handover, OTP collection, spam or deceptive activity that manipulates third-party platforms.',50,1);

-- Safe to run once on an existing installation. All statements are additive.
