<?php
declare(strict_types=1);

function process_post(PDO $pdo): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    verify_csrf();
    $action=(string)($_POST['action']??'');
    $createdUploads = [];
    try {
        switch ($action) {
            case 'contact_support': {
                $sender=current_user($pdo);
                $honeypot=trim((string)($_POST['company_website']??''));
                if($honeypot!==''){flash('success','Your support request has been received.');redirect('contact');}
                $name=trim((string)($_POST['name']??''));
                $email=lower_text(trim((string)($_POST['email']??'')));
                $subject=trim((string)($_POST['subject']??''));
                $message=trim((string)($_POST['message']??''));
                if($sender){$name=trim($sender['first_name'].' '.$sender['last_name']);$email=lower_text((string)$sender['email']);}
                if(text_length($name)<2||text_length($name)>120)throw new RuntimeException('Enter a name between 2 and 120 characters.');
                if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>190)throw new RuntimeException('Enter a valid email address.');
                if(text_length($subject)<4||text_length($subject)>150)throw new RuntimeException('Choose a support topic.');
                if(text_length($message)<20||text_length($message)>4000)throw new RuntimeException('Your message must contain 20–4,000 characters.');
                if(!empty($_SESSION['support_last_sent_at']) && (time()-(int)$_SESSION['support_last_sent_at'])<20)throw new RuntimeException('Please wait a few seconds before sending another support request.');
                $rate=$pdo->prepare('SELECT COUNT(*) FROM support_messages WHERE sender_email=? AND created_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)');
                $rate->execute([$email]);
                if((int)$rate->fetchColumn()>=5)throw new RuntimeException('This email has reached the support request limit. Please try again later.');
                $pdo->beginTransaction();
                $st=$pdo->prepare("INSERT INTO support_messages(user_id,sender_name,sender_email,subject,message,status) VALUES(?,?,?,?,?,'new')");
                $st->execute([$sender?(int)$sender['id']:null,$name,$email,$subject,$message]);
                $messageId=(int)$pdo->lastInsertId();
                foreach($pdo->query("SELECT id FROM users WHERE user_type='admin' AND status='active'")->fetchAll() as $adminRow){
                    notify($pdo,(int)$adminRow['id'],'New support request',$subject.' · from '.$name,'support',url('dashboard',['tab'=>'support','as'=>'admin']));
                }
                audit($pdo,$sender?(int)$sender['id']:null,'support.message_created','support_message',$messageId,['subject'=>$subject,'signed_in'=>(bool)$sender]);
                $pdo->commit();
                $_SESSION['support_last_sent_at']=time();
                flash('success','Your support request has been saved. TaskHaat Admin can now review it from the Support Inbox.');
                redirect('contact');
            }
            case 'admin_update_support_message': {
                $admin=require_role($pdo,'admin');
                $id=(int)($_POST['message_id']??0);
                $status=(string)($_POST['status']??'');
                $note=trim((string)($_POST['admin_note']??''));
                if($id<1||!in_array($status,['new','in_progress','resolved','closed'],true))throw new RuntimeException('Select a valid support request and status.');
                if(text_length($note)>2000)throw new RuntimeException('Admin note must be 2,000 characters or fewer.');
                $pdo->beginTransaction();
                $st=$pdo->prepare('SELECT id,user_id,sender_email,subject,status FROM support_messages WHERE id=? FOR UPDATE');
                $st->execute([$id]);$ticket=$st->fetch();
                if(!$ticket)throw new RuntimeException('Support request not found.');
                $handled=in_array($status,['resolved','closed'],true);
                $pdo->prepare('UPDATE support_messages SET status=?,admin_note=?,handled_by=?,handled_at=? WHERE id=?')->execute([$status,$note!==''?$note:null,(int)$admin['id'],$handled?date('Y-m-d H:i:s'):null,$id]);
                if(!empty($ticket['user_id']))notify($pdo,(int)$ticket['user_id'],'Support request updated','Your support request “'.$ticket['subject'].'” is now marked '.str_replace('_',' ',$status).'.','support',url('contact'));
                audit($pdo,(int)$admin['id'],'support.message_updated','support_message',$id,['previous_status'=>$ticket['status'],'status'=>$status,'has_internal_note'=>$note!=='']);
                $pdo->commit();
                flash('success','Support request status updated. Admin notes are stored internally; no email reply is sent by this build.');
                redirect('dashboard',['tab'=>'support','as'=>'admin','status'=>$status]);
            }
            case 'login': {
                $email=lower_text(trim((string)($_POST['email']??'')));
                $password=(string)($_POST['password']??'');
                $ip=filter_var((string)($_SERVER['REMOTE_ADDR']??''),FILTER_VALIDATE_IP) ?: 'unknown';
                $emailHash=hash_hmac('sha256',$email,APP_KEY);
                $ipHash=hash_hmac('sha256',$ip,APP_KEY);
                $rate=$pdo->prepare("SELECT COALESCE(SUM(email_hash=?),0) email_failures,COALESCE(SUM(ip_hash=?),0) ip_failures FROM login_attempts WHERE was_successful=0 AND attempted_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE) AND (email_hash=? OR ip_hash=?)");
                $rate->execute([$emailHash,$ipHash,$emailHash,$ipHash]);
                $counts=$rate->fetch() ?: ['email_failures'=>0,'ip_failures'=>0];
                if((int)$counts['email_failures']>=8 || (int)$counts['ip_failures']>=30) {
                    throw new RuntimeException('Too many unsuccessful login attempts. Please wait 15 minutes before trying again.');
                }
                $authenticated=auth_login($pdo,$email,$password);
                $pdo->prepare('INSERT INTO login_attempts(email_hash,ip_hash,was_successful) VALUES(?,?,?)')->execute([$emailHash,$ipHash,$authenticated?1:0]);
                // Opportunistic cleanup keeps the security log bounded without a scheduled job.
                if(random_int(1,100)===1) $pdo->exec('DELETE FROM login_attempts WHERE attempted_at<DATE_SUB(NOW(),INTERVAL 30 DAY)');
                if(!$authenticated) throw new RuntimeException('Email or password is incorrect, or the account is inactive.');
                flash('success','Welcome back to TaskHaat.'); redirect('dashboard');
            }
            case 'register': {
                $first=trim((string)($_POST['first_name']??'')); $last=trim((string)($_POST['last_name']??''));
                $email=lower_text(trim((string)($_POST['email']??''))); $phone=trim((string)($_POST['phone']??''));
                $country=trim((string)($_POST['country']??'Bangladesh')); $district=trim((string)($_POST['district']??''));
                $city=trim((string)($_POST['city']??'')); $occupation=trim((string)($_POST['occupation']??''));
                $skills=trim((string)($_POST['skills']??'')); $organization=trim((string)($_POST['organization']??''));
                $website=trim((string)($_POST['website']??'')); $referral=trim((string)($_POST['referral_code']??''));
                $termsAccepted=isset($_POST['terms_accepted']);
                $type=(string)($_POST['user_type']??'worker'); $password=(string)($_POST['password']??''); $confirm=(string)($_POST['confirm_password']??'');
                if($first===''||$last===''||!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter your first name, last name and a valid email address.');
                if($phone===''||!preg_match('/^[0-9+ -]{8,16}$/',$phone)) throw new RuntimeException('Enter a valid mobile number (8–16 digits, spaces, + or hyphens only).');
                if($country===''||$district==='') throw new RuntimeException('Select your country and enter your district.');
                if($website!==''&&!filter_var($website,FILTER_VALIDATE_URL)) throw new RuntimeException('Enter a valid website or portfolio URL.');
                if(!$termsAccepted) throw new RuntimeException('Please accept the TaskHaat terms and prohibited-task rules.');
                if(!in_array($type,['worker','publisher','both'],true)) throw new RuntimeException('Select a valid account type.');
                if(strlen($password)<10) throw new RuntimeException('Use a password with at least 10 characters.');
                if($password!==$confirm) throw new RuntimeException('The password confirmation does not match.');
                $pdo->beginTransaction();
                $st=$pdo->prepare('INSERT INTO users(first_name,last_name,email,phone,country,district,city,occupation,skills,organization,website,referral_code,terms_accepted_at,password_hash,user_type) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?)');
                $st->execute([$first,$last,$email,$phone,$country,$district!==''?$district:null,$city!==''?$city:null,$occupation!==''?$occupation:null,$skills!==''?$skills:null,$organization!==''?$organization:null,$website!==''?$website:null,$referral!==''?$referral:null,password_hash($password,PASSWORD_DEFAULT),$type]);
                $id=(int)$pdo->lastInsertId(); $pdo->prepare('INSERT INTO wallets(user_id) VALUES(?)')->execute([$id]);
                notify($pdo,$id,'Welcome to TaskHaat','Your account is ready. Complete your profile and explore the task marketplace.','welcome',url('dashboard'));
                audit($pdo,$id,'account.registered','user',$id,['user_type'=>$type]);
                $pdo->commit(); session_regenerate_id(true); unset($_SESSION['_csrf']); $_SESSION['user_id']=$id;
                flash('success','Your TaskHaat account has been created.'); redirect('dashboard');
            }
            case 'logout': { auth_logout(); header('Location: index.php'); exit; }
            case 'create_campaign': {
                $user=require_role($pdo,'publisher');
                $title=trim((string)($_POST['title']??'')); $description=trim((string)($_POST['description']??''));
                $category=(int)($_POST['category_id']??0); $reward=round((float)($_POST['reward_per_worker']??0),2); $slots=(int)($_POST['worker_slots']??0);
                $time=trim((string)($_POST['estimated_time']??'')); $deadlineRaw=trim((string)($_POST['deadline']??''));
                $proofType=trim((string)($_POST['proof_type']??'Text')); $proof=trim((string)($_POST['proof_instructions']??''));
                if(strlen($title)<8||strlen($title)>180||strlen($description)<30||$proof==='') throw new RuntimeException('Add a clear title, a description (at least 30 characters) and proof instructions.');
                if($reward<1||$reward>100000||$slots<1||$slots>100000) throw new RuntimeException('Reward must be at least ৳1 and worker slots must be between 1 and 100,000.');
                $st=$pdo->prepare('SELECT id FROM categories WHERE id=? AND is_active=1');$st->execute([$category]);if(!$st->fetch())throw new RuntimeException('Select an active task category.');
                $deadline=null;if($deadlineRaw!==''){try{$d=new DateTime($deadlineRaw);if($d<=new DateTime())throw new RuntimeException('Deadline must be in the future.');$deadline=$d->format('Y-m-d H:i:s');}catch(Throwable $ex){if($ex instanceof RuntimeException)throw $ex;throw new RuntimeException('Enter a valid deadline.');}}
                $publisherRate=(float)get_setting($pdo,'publisher_fee_rate','8');$workerRate=(float)get_setting($pdo,'worker_fee_rate','8');
                $publisherFeePerWorker=round($reward*$publisherRate/100,2);
                $budget=round(($reward+$publisherFeePerWorker)*$slots,2);
                $pdo->beginTransaction();
                lock_active_account($pdo,(int)$user['id']);
                $image=null;
                if(isset($_FILES['cover_image'])) {
                    $image=upload_campaign_image($_FILES['cover_image']);
                    if($image) $createdUploads[]=['path'=>CAMPAIGN_UPLOAD_DIR.'/'.$image];
                }
                $st=$pdo->prepare('SELECT deposit_balance FROM wallets WHERE user_id=? FOR UPDATE');$st->execute([(int)$user['id']]);$wallet=$st->fetch();
                if(!$wallet || (float)$wallet['deposit_balance']<$budget) throw new RuntimeException('Insufficient deposit wallet balance. You need '.money($budget).' including the publisher fee.');
                $st=$pdo->prepare('INSERT INTO campaigns(publisher_id,category_id,title,description,reward_per_worker,worker_slots,estimated_time,deadline,proof_type,proof_instructions,cover_image,status,publisher_fee_rate,worker_fee_rate,budget_total,budget_remaining) VALUES(?,?,?,?,?,?,?,?,?,?,? ,\'pending\',?,?,?,?)');
                $st->execute([(int)$user['id'],$category,$title,$description,$reward,$slots,$time!==''?$time:null,$deadline,$proofType,$proof,$image,$publisherRate,$workerRate,$budget,$budget]);
                $campaignId=(int)$pdo->lastInsertId();
                $pdo->prepare('UPDATE wallets SET deposit_balance=deposit_balance-?,reserved_balance=reserved_balance+? WHERE user_id=?')->execute([$budget,$budget,(int)$user['id']]);
                record_wallet($pdo,(int)$user['id'],'deposit','debit',$budget,'campaign_reserve','Campaign budget reserved: '.$title,'campaign',$campaignId);
                record_wallet($pdo,(int)$user['id'],'reserved','credit',$budget,'campaign_reserve','Campaign budget reserved: '.$title,'campaign',$campaignId);
                notify($pdo,(int)$user['id'],'Campaign submitted for review','Your campaign has been submitted to the Admin moderation queue.','campaign',url('dashboard',['tab'=>'campaigns']));
                notify_admins($pdo,'Campaign awaiting review',$title.' has been submitted by '.$user['first_name'].' '.$user['last_name'].'.');
                audit($pdo,(int)$user['id'],'campaign.created','campaign',$campaignId,['budget'=>$budget,'slots'=>$slots]);
                $pdo->commit(); flash('success','Campaign created and sent to Admin review.'); redirect('dashboard',['tab'=>'campaigns']);
            }
            case 'update_campaign': {
                $user=require_role($pdo,'publisher');
                $campaignId=(int)($_POST['campaign_id']??0);
                $title=trim((string)($_POST['title']??''));
                $description=trim((string)($_POST['description']??''));
                $category=(int)($_POST['category_id']??0);
                $reward=round((float)($_POST['reward_per_worker']??0),2);
                $slots=(int)($_POST['worker_slots']??0);
                $time=trim((string)($_POST['estimated_time']??''));
                $deadlineRaw=trim((string)($_POST['deadline']??''));
                $proofType=trim((string)($_POST['proof_type']??'Text'));
                $proof=trim((string)($_POST['proof_instructions']??''));
                if(strlen($title)<8||strlen($title)>180||strlen($description)<30||$proof==='') throw new RuntimeException('Add a clear title, a description (at least 30 characters) and proof instructions.');
                if($reward<1||$reward>100000||$slots<1||$slots>100000) throw new RuntimeException('Reward must be at least ৳1 and worker slots must be between 1 and 100,000.');
                $st=$pdo->prepare('SELECT id FROM categories WHERE id=? AND is_active=1');$st->execute([$category]);if(!$st->fetch())throw new RuntimeException('Select an active task category.');
                $deadline=null;
                if($deadlineRaw!==''){
                    try{$d=new DateTime($deadlineRaw);if($d<=new DateTime())throw new RuntimeException('Deadline must be in the future.');$deadline=$d->format('Y-m-d H:i:s');}
                    catch(Throwable $ex){if($ex instanceof RuntimeException)throw $ex;throw new RuntimeException('Enter a valid deadline.');}
                }
                $newImage=null;
                $pdo->beginTransaction();
                lock_active_account($pdo,(int)$user['id']);
                $st=$pdo->prepare('SELECT * FROM campaigns WHERE id=? AND publisher_id=? FOR UPDATE');
                $st->execute([$campaignId,(int)$user['id']]);$campaign=$st->fetch();
                if(!$campaign)throw new RuntimeException('Campaign not found or you do not own it.');
                if(!in_array($campaign['status'],['pending','rejected'],true))throw new RuntimeException('Only pending or rejected campaigns can be edited. Live campaigns cannot be changed after workers may have seen the instructions.');
                $st=$pdo->prepare('SELECT COUNT(*) FROM claims WHERE campaign_id=?');$st->execute([$campaignId]);
                if((int)$campaign['claimed_slots']!==0||(int)$st->fetchColumn()!==0)throw new RuntimeException('This campaign already has worker activity and can no longer be edited.');
                $publisherRate=(float)$campaign['publisher_fee_rate'];
                $publisherFeePerWorker=round($reward*$publisherRate/100,2);
                $newBudget=round(($reward+$publisherFeePerWorker)*$slots,2);
                $currentReserved=$campaign['status']==='pending'?(float)$campaign['budget_remaining']:0.0;
                $delta=round($newBudget-$currentReserved,2);
                $st=$pdo->prepare('SELECT deposit_balance,reserved_balance FROM wallets WHERE user_id=? FOR UPDATE');$st->execute([(int)$user['id']]);$wallet=$st->fetch();
                if(!$wallet)throw new RuntimeException('Publisher wallet not found.');
                if((float)$wallet['reserved_balance']+0.001<$currentReserved)throw new RuntimeException('The reserved wallet balance does not match this campaign. Contact an Admin before editing.');
                if($delta>0 && (float)$wallet['deposit_balance']+0.001<$delta)throw new RuntimeException('Insufficient deposit balance. You need '.money($delta).' more to update and resubmit this campaign.');
                if(isset($_FILES['cover_image']) && ($_FILES['cover_image']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
                    $newImage=upload_campaign_image($_FILES['cover_image']);
                    if($newImage) $createdUploads[]=['path'=>CAMPAIGN_UPLOAD_DIR.'/'.$newImage];
                }
                if($delta>0){
                    $pdo->prepare('UPDATE wallets SET deposit_balance=deposit_balance-?,reserved_balance=reserved_balance+? WHERE user_id=?')->execute([$delta,$delta,(int)$user['id']]);
                    record_wallet($pdo,(int)$user['id'],'deposit','debit',$delta,'campaign_edit_reserve','Additional budget reserved for campaign edit','campaign',$campaignId);
                    record_wallet($pdo,(int)$user['id'],'reserved','credit',$delta,'campaign_edit_reserve','Additional budget reserved for campaign edit','campaign',$campaignId);
                }elseif($delta<0){
                    $release=abs($delta);
                    $pdo->prepare('UPDATE wallets SET reserved_balance=reserved_balance-?,deposit_balance=deposit_balance+? WHERE user_id=?')->execute([$release,$release,(int)$user['id']]);
                    record_wallet($pdo,(int)$user['id'],'reserved','debit',$release,'campaign_edit_release','Unused campaign reserve released after edit','campaign',$campaignId);
                    record_wallet($pdo,(int)$user['id'],'deposit','credit',$release,'campaign_edit_release','Unused campaign reserve returned after edit','campaign',$campaignId);
                }
                $imageToSave=$newImage?:$campaign['cover_image'];
                $pdo->prepare("UPDATE campaigns SET category_id=?,title=?,description=?,reward_per_worker=?,worker_slots=?,estimated_time=?,deadline=?,proof_type=?,proof_instructions=?,cover_image=?,status='pending',budget_total=?,budget_remaining=?,review_note=NULL,reviewed_by=NULL,reviewed_at=NULL WHERE id=?")
                    ->execute([$category,$title,$description,$reward,$slots,$time!==''?$time:null,$deadline,$proofType,$proof,$imageToSave,$newBudget,$newBudget,$campaignId]);
                notify($pdo,(int)$user['id'],'Campaign resubmitted for review','Your updated campaign “'.$title.'” has been sent to Admin moderation.','campaign',url('dashboard',['tab'=>'campaign_view','id'=>$campaignId,'as'=>'publisher']));
                notify_admins($pdo,'Updated campaign awaiting review',$title.' was edited and resubmitted by '.$user['first_name'].' '.$user['last_name'].'.');
                audit($pdo,(int)$user['id'],'campaign.updated','campaign',$campaignId,['budget'=>$newBudget,'previous_status'=>$campaign['status']]);
                $pdo->commit();
                if($newImage && !empty($campaign['cover_image'])){$oldPath=CAMPAIGN_UPLOAD_DIR.'/'.$campaign['cover_image'];if(is_file($oldPath))@unlink($oldPath);}
                flash('success','Campaign updated. Its budget was reconciled and it is waiting for Admin review again.');
                redirect('dashboard',['tab'=>'campaign_view','id'=>$campaignId,'as'=>'publisher']);
            }
            case 'claim_task': {
                $user=require_role($pdo,'worker');$campaignId=(int)($_POST['campaign_id']??0);
                $pdo->beginTransaction();
                lock_active_account($pdo,(int)$user['id']);
                $meta=$pdo->prepare('SELECT publisher_id FROM campaigns WHERE id=?');$meta->execute([$campaignId]);$publisherId=(int)$meta->fetchColumn();
                if($publisherId<1)throw new RuntimeException('This campaign is not currently available.');
                if($publisherId===(int)$user['id'])throw new RuntimeException('You cannot claim a campaign published by your own account.');
                $publisherAccount=lock_active_account($pdo,$publisherId);
                $st=$pdo->prepare('SELECT * FROM campaigns WHERE id=? AND publisher_id=? FOR UPDATE');$st->execute([$campaignId,$publisherId]);$c=$st->fetch();
                if(!$c||$c['status']!=='approved')throw new RuntimeException('This campaign is not currently available.');
                if($c['deadline'] && strtotime($c['deadline'])<time())throw new RuntimeException('The deadline for this campaign has passed.');
                if((int)$c['claimed_slots'] >= (int)$c['worker_slots'])throw new RuntimeException('All worker slots have already been claimed.');
                $publisherFeePerWorker=round((float)$c['reward_per_worker']*(float)$c['publisher_fee_rate']/100,2);
                $minimumReserve=round((float)$c['reward_per_worker']+$publisherFeePerWorker,2);
                if((float)$c['budget_remaining']+0.001<$minimumReserve)throw new RuntimeException('This campaign does not have enough reserved budget for another worker slot. Please contact the Publisher.');
                $st=$pdo->prepare('SELECT id FROM claims WHERE campaign_id=? AND worker_id=?');$st->execute([$campaignId,(int)$user['id']]);if($st->fetch())throw new RuntimeException('You have already claimed a slot in this campaign.');
                $pdo->prepare("INSERT INTO claims(campaign_id,worker_id,status) VALUES(?,?,'claimed')")->execute([$campaignId,(int)$user['id']]);$claimId=(int)$pdo->lastInsertId();
                $increment=$pdo->prepare('UPDATE campaigns SET claimed_slots=claimed_slots+1 WHERE id=? AND claimed_slots<worker_slots');
                $increment->execute([$campaignId]);
                if($increment->rowCount()!==1)throw new RuntimeException('All worker slots have already been claimed.');
                notify($pdo,$publisherId,'A worker claimed your campaign',$user['first_name'].' '.$user['last_name'].' claimed a slot in “'.$c['title'].'”.','claim',url('dashboard',['tab'=>'review']));
                audit($pdo,(int)$user['id'],'campaign.claimed','claim',$claimId,['campaign_id'=>$campaignId]);$pdo->commit();
                flash('success','Task slot claimed. Complete the work and submit your proof.');redirect('task',['id'=>$campaignId]);
            }
            case 'submit_proof': {
                $user=require_role($pdo,'worker');$claimId=(int)($_POST['claim_id']??0);$notes=trim((string)($_POST['notes']??''));$evidence=trim((string)($_POST['evidence_url']??''));
                if(text_length($notes)<10||text_length($notes)>10000)throw new RuntimeException('Please provide 10–10,000 characters describing your completed work.');
                if($evidence!==''&&(!filter_var($evidence,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($evidence,PHP_URL_SCHEME)),['http','https'],true)))throw new RuntimeException('Enter a valid evidence URL beginning with https:// or http://.');
                $pdo->beginTransaction();
                lock_active_account($pdo,(int)$user['id']);
                $st=$pdo->prepare("SELECT cl.*,c.title,c.publisher_id FROM claims cl JOIN campaigns c ON c.id=cl.campaign_id WHERE cl.id=? AND cl.worker_id=? FOR UPDATE");
                $st->execute([$claimId,(int)$user['id']]);$claim=$st->fetch();
                if(!$claim||$claim['status']!=='claimed')throw new RuntimeException('This claim cannot accept a new submission.');
                $fileName=null;$fileOriginal=null;$fileMime=null;$fileSize=0;
                if(isset($_FILES['evidence_file'])){
                    $fileName=upload_submission_file($_FILES['evidence_file']);
                    if($fileName){
                        $createdUploads[]=['path'=>UPLOAD_DIR.'/'.$fileName];
                        $fileOriginal=substr(basename((string)$_FILES['evidence_file']['name']),0,255);
                        $fileMime=(new finfo(FILEINFO_MIME_TYPE))->file(UPLOAD_DIR.'/'.$fileName)?:'application/octet-stream';
                        $fileSize=(int)$_FILES['evidence_file']['size'];
                    }
                }
                $pdo->prepare("INSERT INTO submissions(claim_id,notes,evidence_url,status) VALUES(?,?,?,'pending')")->execute([$claimId,$notes,$evidence!==''?$evidence:null]);$submissionId=(int)$pdo->lastInsertId();
                if($fileName){$pdo->prepare('INSERT INTO submission_files(submission_id,storage_name,original_name,mime_type,file_size) VALUES(?,?,?,?,?)')->execute([$submissionId,$fileName,$fileOriginal,$fileMime,$fileSize]);}
                $claimUpdate=$pdo->prepare("UPDATE claims SET status='submitted' WHERE id=? AND worker_id=? AND status='claimed'");$claimUpdate->execute([$claimId,(int)$user['id']]);
                if($claimUpdate->rowCount()!==1)throw new RuntimeException('This claim has already been submitted. Refresh the page and check My Submissions.');
                notify($pdo,(int)$claim['publisher_id'],'New proof submission received',$user['first_name'].' '.$user['last_name'].' submitted proof for “'.$claim['title'].'”.','submission',url('dashboard',['tab'=>'review']));
                notify_admins($pdo,'New task proof submitted','A worker submitted proof for “'.$claim['title'].'”.','submission');
                audit($pdo,(int)$user['id'],'submission.created','submission',$submissionId,['claim_id'=>$claimId]);$pdo->commit();
                flash('success','Proof submitted. It is now waiting for review.');redirect('dashboard',['tab'=>'submissions']);
            }
            case 'review_campaign': {
                $admin=require_role($pdo,'admin');$campaignId=(int)($_POST['campaign_id']??0);$decision=(string)($_POST['decision']??'');$note=trim((string)($_POST['review_note']??''));
                if(!in_array($decision,['approve','reject'],true))throw new RuntimeException('Invalid moderation decision.');
                $pdo->beginTransaction();lock_active_account($pdo,(int)$admin['id']);$st=$pdo->prepare('SELECT * FROM campaigns WHERE id=? FOR UPDATE');$st->execute([$campaignId]);$c=$st->fetch();
                if(!$c||$c['status']!=='pending')throw new RuntimeException('This campaign is no longer waiting for moderation.');
                if($decision==='approve'){
                    $categorySt=$pdo->prepare('SELECT is_active FROM categories WHERE id=?');$categorySt->execute([(int)$c['category_id']]);
                    if(!(int)$categorySt->fetchColumn())throw new RuntimeException('This campaign uses an inactive category. Activate the category or ask the publisher to choose an active one before approval.');
                    $pdo->prepare("UPDATE campaigns SET status='approved',review_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$note!==''?$note:null,(int)$admin['id'],$campaignId]);notify($pdo,(int)$c['publisher_id'],'Campaign approved','Your campaign “'.$c['title'].'” is now visible to workers.','campaign',url('dashboard',['tab'=>'campaigns']));
                }
                else {
                    $amount=(float)$c['budget_remaining'];
                    $walletSt=$pdo->prepare('SELECT reserved_balance FROM wallets WHERE user_id=? FOR UPDATE');$walletSt->execute([(int)$c['publisher_id']]);$publisherWallet=$walletSt->fetch();
                    if(!$publisherWallet||(float)$publisherWallet['reserved_balance']+0.001<$amount)throw new RuntimeException('Publisher reserved balance does not match this campaign. Reconcile the wallet before rejecting it.');
                    if($amount>0){
                        $pdo->prepare('UPDATE wallets SET reserved_balance=reserved_balance-?,deposit_balance=deposit_balance+? WHERE user_id=?')->execute([$amount,$amount,(int)$c['publisher_id']]);
                        record_wallet($pdo,(int)$c['publisher_id'],'reserved','debit',$amount,'campaign_refund','Campaign moderation refund','campaign',$campaignId);
                        record_wallet($pdo,(int)$c['publisher_id'],'deposit','credit',$amount,'campaign_refund','Campaign moderation refund','campaign',$campaignId);
                    }
                    $pdo->prepare("UPDATE campaigns SET status='rejected',budget_remaining=0,review_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$note!==''?$note:'Please revise the task details and proof requirements.',(int)$admin['id'],$campaignId]);
                    notify($pdo,(int)$c['publisher_id'],'Campaign not approved',$note!==''?$note:'Please revise your campaign and resubmit.','campaign',url('dashboard',['tab'=>'campaigns']));
                }
                audit($pdo,(int)$admin['id'],'campaign.'.$decision,'campaign',$campaignId,['note'=>$note]);$pdo->commit();flash('success',$decision==='approve'?'Campaign approved and published.':'Campaign rejected and its remaining budget refunded.');redirect('dashboard',['tab'=>'moderation']);
            }
            case 'review_submission': {
                $reviewer=require_login($pdo);$submissionId=(int)($_POST['submission_id']??0);$decision=(string)($_POST['decision']??'');$feedback=trim((string)($_POST['feedback']??''));
                if(!in_array($decision,['approve','reject'],true))throw new RuntimeException('Invalid review decision.');if($decision==='reject'&&strlen($feedback)<5)throw new RuntimeException('A clear rejection reason is required.');
                $pdo->beginTransaction();lock_active_account($pdo,(int)$reviewer['id']);$st=$pdo->prepare('SELECT s.*,cl.id claim_id,cl.campaign_id,cl.worker_id,cl.status claim_status,c.publisher_id,c.title,c.reward_per_worker,c.publisher_fee_rate,c.worker_fee_rate,c.budget_remaining,c.status campaign_status FROM submissions s JOIN claims cl ON cl.id=s.claim_id JOIN campaigns c ON c.id=cl.campaign_id WHERE s.id=? FOR UPDATE');$st->execute([$submissionId]);$s=$st->fetch();
                if(!$s||$s['status']!=='pending')throw new RuntimeException('This submission is no longer waiting for review.');
                if($reviewer['user_type']!=='admin' && ((int)$s['publisher_id']!==(int)$reviewer['id'] || !has_role($reviewer,'publisher')))throw new RuntimeException('Only the campaign publisher or an Admin can review this proof.');
                $reward=(float)$s['reward_per_worker'];$publisherFee=round($reward*(float)$s['publisher_fee_rate']/100,2);$workerFee=round($reward*(float)$s['worker_fee_rate']/100,2);$publisherCost=round($reward+$publisherFee,2);$net=round($reward-$workerFee,2);
                if((float)$s['budget_remaining']+0.001<$publisherCost)throw new RuntimeException('Campaign reserve is insufficient to settle this task. Contact an Admin.');
                $publisherWalletSt=$pdo->prepare('SELECT reserved_balance FROM wallets WHERE user_id=? FOR UPDATE');$publisherWalletSt->execute([(int)$s['publisher_id']]);$publisherWallet=$publisherWalletSt->fetch();
                if(!$publisherWallet||(float)$publisherWallet['reserved_balance']+0.001<$publisherCost)throw new RuntimeException('Publisher reserved wallet does not cover this submission. Stop and reconcile the campaign ledger before review.');
                if($decision==='approve'){
                    $workerWalletSt=$pdo->prepare('SELECT id FROM wallets WHERE user_id=? FOR UPDATE');$workerWalletSt->execute([(int)$s['worker_id']]);
                    if(!$workerWalletSt->fetch())throw new RuntimeException('Worker wallet not found. Cannot credit this reward.');
                    $pdo->prepare('UPDATE wallets SET reserved_balance=reserved_balance-? WHERE user_id=?')->execute([$publisherCost,(int)$s['publisher_id']]);
                    $pdo->prepare('UPDATE wallets SET available_balance=available_balance+? WHERE user_id=?')->execute([$net,(int)$s['worker_id']]);
                    record_wallet($pdo,(int)$s['publisher_id'],'reserved','debit',$publisherCost,'campaign_settlement','Approved task settlement: '.$s['title'],'submission',$submissionId);
                    record_wallet($pdo,(int)$s['worker_id'],'available','credit',$net,'worker_reward','Approved reward for: '.$s['title'],'submission',$submissionId);
                    $platformRevenue=round($publisherFee+$workerFee,2);$pdo->prepare('INSERT INTO platform_transactions(amount,transaction_type,description,reference_type,reference_id) VALUES(?,?,?,?,?)')->execute([$platformRevenue,'commission','Publisher + worker commission for '.$s['title'],'submission',$submissionId]);
                    $pdo->prepare("UPDATE submissions SET status='approved',feedback=?,reviewed_by=?,reviewed_at=NOW(),gross_reward=?,worker_fee=?,net_reward=? WHERE id=?")->execute([$feedback!==''?$feedback:null,(int)$reviewer['id'],$reward,$workerFee,$net,$submissionId]);
                    $pdo->prepare("UPDATE claims SET status='approved' WHERE id=?")->execute([(int)$s['claim_id']]);
                    $pdo->prepare('UPDATE campaigns SET budget_remaining=GREATEST(0,budget_remaining-?) WHERE id=?')->execute([$publisherCost,(int)$s['campaign_id']]);
                    $st=$pdo->prepare("SELECT COUNT(*) FROM claims WHERE campaign_id=? AND status IN ('claimed','submitted')");$st->execute([(int)$s['campaign_id']]);$openClaims=(int)$st->fetchColumn();
                    $st=$pdo->prepare('SELECT budget_remaining FROM campaigns WHERE id=?');$st->execute([(int)$s['campaign_id']]);$remaining=(float)$st->fetchColumn();
                    if($remaining<0.01 && $openClaims===0)$pdo->prepare("UPDATE campaigns SET status='completed' WHERE id=?")->execute([(int)$s['campaign_id']]);
                    notify($pdo,(int)$s['worker_id'],'Proof approved — reward credited','Your net reward of '.money($net).' has been added to your wallet.','wallet',url('dashboard',['tab'=>'wallet']));
                    notify($pdo,(int)$s['publisher_id'],'Submission approved','A worker submission for “'.$s['title'].'” was approved.','submission',url('dashboard',['tab'=>'review']));
                } else {
                    $pdo->prepare('UPDATE wallets SET reserved_balance=reserved_balance-?,deposit_balance=deposit_balance+? WHERE user_id=?')->execute([$publisherCost,$publisherCost,(int)$s['publisher_id']]);
                    record_wallet($pdo,(int)$s['publisher_id'],'reserved','debit',$publisherCost,'submission_refund','Rejected proof refund: '.$s['title'],'submission',$submissionId);
                    record_wallet($pdo,(int)$s['publisher_id'],'deposit','credit',$publisherCost,'submission_refund','Rejected proof refund: '.$s['title'],'submission',$submissionId);
                    $pdo->prepare("UPDATE submissions SET status='rejected',feedback=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$feedback,(int)$reviewer['id'],$submissionId]);
                    $pdo->prepare("UPDATE claims SET status='rejected' WHERE id=?")->execute([(int)$s['claim_id']]);
                    $pdo->prepare('UPDATE campaigns SET budget_remaining=GREATEST(0,budget_remaining-?) WHERE id=?')->execute([$publisherCost,(int)$s['campaign_id']]);
                    $st=$pdo->prepare("SELECT COUNT(*) FROM claims WHERE campaign_id=? AND status IN ('claimed','submitted')");$st->execute([(int)$s['campaign_id']]);$openClaims=(int)$st->fetchColumn();
                    $st=$pdo->prepare('SELECT worker_slots,claimed_slots,budget_remaining FROM campaigns WHERE id=?');$st->execute([(int)$s['campaign_id']]);$campaignState=$st->fetch();
                    if($openClaims===0 && (int)$campaignState['claimed_slots'] >= (int)$campaignState['worker_slots'] && (float)$campaignState['budget_remaining'] < 0.01) $pdo->prepare("UPDATE campaigns SET status='completed' WHERE id=?")->execute([(int)$s['campaign_id']]);
                    notify($pdo,(int)$s['worker_id'],'Submission needs attention',$feedback,'submission',url('dashboard',['tab'=>'submissions']));
                    notify($pdo,(int)$s['publisher_id'],'Submission rejected','A rejected slot has been returned to your deposit balance.','submission',url('dashboard',['tab'=>'campaigns']));
                }
                audit($pdo,(int)$reviewer['id'],'submission.'.$decision,'submission',$submissionId,['feedback'=>$feedback]);$pdo->commit();
                flash('success',$decision==='approve'?'Submission approved and net reward credited.':'Submission rejected with feedback; publisher reserve refunded.');redirect('dashboard',['tab'=>'review']);
            }
            case 'request_withdrawal': {
                $user=require_role($pdo,'worker');$amount=round((float)($_POST['amount']??0),2);$method=(string)($_POST['method']??'');$account=trim((string)($_POST['account_reference']??''));
                $min=(float)get_setting($pdo,'min_withdrawal','200');$max=(float)get_setting($pdo,'max_withdrawal','25000');
                if(!in_array($method,['bKash','Nagad','Rocket','Bank transfer'],true)||$account===''||strlen($account)>120)throw new RuntimeException('Enter a valid payout method and account reference.');
                if(in_array($method,['bKash','Nagad','Rocket'],true)&&!preg_match('/^[0-9+() -]{8,24}$/',$account))throw new RuntimeException('Enter a valid mobile wallet number for the selected payout method.');
                if($amount<$min||$amount>$max)throw new RuntimeException('Withdrawal amount must be between '.money($min).' and '.money($max).'.');
                $pdo->beginTransaction();lock_active_account($pdo,(int)$user['id']);
                $st=$pdo->prepare('SELECT available_balance FROM wallets WHERE user_id=? FOR UPDATE');$st->execute([(int)$user['id']]);$w=$st->fetch();if(!$w||(float)$w['available_balance']<$amount)throw new RuntimeException('Your available wallet balance is insufficient.');
                $pdo->prepare('UPDATE wallets SET available_balance=available_balance-?,withdrawal_hold_balance=withdrawal_hold_balance+? WHERE user_id=?')->execute([$amount,$amount,(int)$user['id']]);
                $encryptedAccount=encrypt_account_reference($account);$last4=payout_reference_last4($account);
                $pdo->prepare("INSERT INTO withdrawals(user_id,amount,method,account_reference,account_reference_last4,status) VALUES(?,?,?,?,?,'pending')")->execute([(int)$user['id'],$amount,$method,$encryptedAccount,$last4]);$id=(int)$pdo->lastInsertId();
                record_wallet($pdo,(int)$user['id'],'available','debit',$amount,'withdrawal_request','Withdrawal request submitted','withdrawal',$id);record_wallet($pdo,(int)$user['id'],'withdrawal_hold','credit',$amount,'withdrawal_hold','Amount held for withdrawal','withdrawal',$id);
                notify_admins($pdo,'Withdrawal request received',$user['first_name'].' requested '.money($amount).' via '.$method.'.');audit($pdo,(int)$user['id'],'withdrawal.requested','withdrawal',$id,['amount'=>$amount,'method'=>$method]);$pdo->commit();
                flash('success','Withdrawal request submitted for manual review. No payment has been sent yet.');redirect('dashboard',['tab'=>'wallet']);
            }
            case 'submit_deposit_request': {
                $publisher=require_role($pdo,'publisher');
                $amount=round((float)($_POST['amount']??0),2);
                $method=(string)($_POST['method']??'');
                $reference=trim((string)($_POST['sender_reference']??''));
                $minDeposit=(float)get_setting($pdo,'min_deposit','100');
                $maxDeposit=(float)get_setting($pdo,'max_deposit','1000000');
                $methods=manual_deposit_methods($pdo);
                if(!isset($methods[$method]))throw new RuntimeException('This deposit method is not configured yet. Please contact TaskHaat Admin.');
                if($amount<$minDeposit||$amount>$maxDeposit)throw new RuntimeException('Deposit amount must be between '.money($minDeposit).' and '.money($maxDeposit).'.');
                if(text_length($reference)<4||text_length($reference)>120)throw new RuntimeException('Enter the transaction/reference ID from your payment receipt (4–120 characters).');
                $proof=upload_deposit_proof($_FILES['deposit_proof']??[]);
                $createdUploads[]=['path'=>$proof['path']];
                $fingerprint=manual_deposit_reference_fingerprint($method,$reference);
                $pdo->beginTransaction();
                lock_active_account($pdo,(int)$publisher['id']);
                $duplicate=$pdo->prepare('SELECT id,status FROM manual_deposit_requests WHERE reference_fingerprint=? LIMIT 1 FOR UPDATE');
                $duplicate->execute([$fingerprint]);
                if($duplicate->fetch())throw new RuntimeException('This transaction reference has already been submitted. Check your deposit history.');
                $st=$pdo->prepare("INSERT INTO manual_deposit_requests(publisher_id,payment_method,amount,sender_reference,reference_fingerprint,proof_storage_name,proof_original_name,proof_mime_type,proof_file_size,status) VALUES(?,?,?,?,?,?,?,?,?,'pending')");
                $st->execute([(int)$publisher['id'],$method,$amount,$reference,$fingerprint,$proof['storage_name'],$proof['original_name'],$proof['mime_type'],$proof['file_size']]);
                $requestId=(int)$pdo->lastInsertId();
                notify_admins($pdo,'Publisher deposit request received',$publisher['first_name'].' '.$publisher['last_name'].' submitted '.money($amount).' via '.$method.'.','wallet');
                audit($pdo,(int)$publisher['id'],'deposit.requested','deposit_request',$requestId,['amount'=>$amount,'method'=>$method,'reference_fingerprint'=>$fingerprint,'proof_uploaded'=>true]);
                $pdo->commit();
                $createdUploads=[];
                flash('success','Deposit request submitted. Your wallet will be credited only after Admin verifies the external payment.');
                redirect('dashboard',['tab'=>'wallet','as'=>'publisher']);
            }
            case 'cancel_deposit_request': {
                $publisher=require_role($pdo,'publisher');$id=(int)($_POST['deposit_request_id']??0);
                if($id<1)throw new RuntimeException('Select a valid deposit request.');
                $pdo->beginTransaction();lock_active_account($pdo,(int)$publisher['id']);
                $st=$pdo->prepare('SELECT * FROM manual_deposit_requests WHERE id=? AND publisher_id=? FOR UPDATE');$st->execute([$id,(int)$publisher['id']]);$request=$st->fetch();
                if(!$request||$request['status']!=='pending')throw new RuntimeException('Only pending deposit requests can be cancelled.');
                $pdo->prepare("UPDATE manual_deposit_requests SET status='cancelled',admin_note='Cancelled by publisher' WHERE id=?")->execute([$id]);
                audit($pdo,(int)$publisher['id'],'deposit.cancelled','deposit_request',$id,['amount'=>(float)$request['amount']]);
                $pdo->commit();flash('success','Pending deposit request cancelled. No wallet balance was changed.');redirect('dashboard',['tab'=>'wallet','as'=>'publisher']);
            }
            case 'admin_deposit_request': {
                $admin=require_role($pdo,'admin');$id=(int)($_POST['deposit_request_id']??0);$decision=(string)($_POST['decision']??'');$note=trim((string)($_POST['admin_note']??''));
                if($id<1||!in_array($decision,['approve','reject'],true))throw new RuntimeException('Select a valid deposit request action.');
                if($decision==='reject'&&text_length($note)<5)throw new RuntimeException('Enter a clear reason before rejecting a deposit request.');
                if(text_length($note)>500)throw new RuntimeException('Admin note must be 500 characters or fewer.');
                $pdo->beginTransaction();lock_active_account($pdo,(int)$admin['id']);
                $st=$pdo->prepare('SELECT * FROM manual_deposit_requests WHERE id=? FOR UPDATE');$st->execute([$id]);$request=$st->fetch();
                if(!$request||$request['status']!=='pending')throw new RuntimeException('This deposit request has already been reviewed or cannot be changed.');
                $publisherId=(int)$request['publisher_id'];
                $publisher=lock_active_account($pdo,$publisherId);
                if(!in_array($publisher['user_type'],['publisher','both'],true))throw new RuntimeException('The deposit request owner is not a publisher account.');
                if($decision==='approve'){
                    if(empty($request['proof_storage_name']) || empty($request['proof_mime_type']) || empty($request['proof_file_size']))throw new RuntimeException('This request has no stored payment proof. Ask the Publisher to submit a new request with a receipt, or reject this record.');
                    $walletSt=$pdo->prepare('SELECT deposit_balance FROM wallets WHERE user_id=? FOR UPDATE');$walletSt->execute([$publisherId]);$wallet=$walletSt->fetch();
                    if(!$wallet)throw new RuntimeException('Publisher wallet not found.');
                    $idempotencyKey=hash_hmac('sha256','manual-deposit-approved:'.$id,APP_KEY);
                    $already=$pdo->prepare('SELECT id FROM wallet_transactions WHERE idempotency_key=?');$already->execute([$idempotencyKey]);
                    if($already->fetch())throw new RuntimeException('This deposit request has already generated a wallet credit.');
                    $pdo->prepare('UPDATE wallets SET deposit_balance=deposit_balance+? WHERE user_id=?')->execute([$request['amount'],$publisherId]);
                    record_wallet($pdo,$publisherId,'deposit','credit',(float)$request['amount'],'manual_deposit','Manual deposit approved · '.$request['payment_method'].' · '.$request['sender_reference'],'deposit_request',$id,$idempotencyKey);
                    $walletTransactionId=(int)$pdo->lastInsertId();
                    $pdo->prepare("UPDATE manual_deposit_requests SET status='approved',admin_note=?,reviewed_by=?,reviewed_at=NOW(),wallet_transaction_id=? WHERE id=?")->execute([$note!==''?$note:null,(int)$admin['id'],$walletTransactionId,$id]);
                    notify($pdo,$publisherId,'Deposit approved',money($request['amount']).' has been credited to your Publisher Deposit Wallet. Reference: '.$request['sender_reference'],'wallet',url('dashboard',['tab'=>'wallet','as'=>'publisher']));
                    audit($pdo,(int)$admin['id'],'deposit.approved','deposit_request',$id,['publisher_id'=>$publisherId,'amount'=>(float)$request['amount'],'wallet_transaction_id'=>$walletTransactionId,'payment_method'=>$request['payment_method']]);
                    $success='Deposit approved and publisher wallet credited.';
                }else{
                    $pdo->prepare("UPDATE manual_deposit_requests SET status='rejected',admin_note=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$note,(int)$admin['id'],$id]);
                    notify($pdo,$publisherId,'Deposit request rejected','Your '.money($request['amount']).' deposit request was rejected. Reason: '.$note,'wallet',url('dashboard',['tab'=>'wallet','as'=>'publisher']));
                    audit($pdo,(int)$admin['id'],'deposit.rejected','deposit_request',$id,['publisher_id'=>$publisherId,'amount'=>(float)$request['amount'],'reason'=>$note]);
                    $success='Deposit request rejected. No wallet balance was changed.';
                }
                $pdo->commit();flash('success',$success);redirect('dashboard',['tab'=>'finance','as'=>'admin','deposit_filter'=>$decision==='approve'?'approved':'rejected']);
            }
            case 'admin_withdrawal': {
                $admin=require_role($pdo,'admin');
                $id=(int)($_POST['withdrawal_id']??0);$decision=(string)($_POST['decision']??'');$note=trim((string)($_POST['admin_note']??''));$paymentReference=trim((string)($_POST['payment_reference']??''));
                if(!in_array($decision,['processing','paid','rejected'],true))throw new RuntimeException('Select a valid withdrawal action.');
                if($decision==='paid'&&(text_length($paymentReference)<4||text_length($paymentReference)>120))throw new RuntimeException('Enter the external payment reference before marking a withdrawal as paid.');
                if($decision==='rejected'&&text_length($note)<5)throw new RuntimeException('Enter a clear reason before rejecting a withdrawal.');
                if(text_length($note)>500)throw new RuntimeException('Admin note must be 500 characters or fewer.');
                $pdo->beginTransaction();
                lock_active_account($pdo,(int)$admin['id']);
                $st=$pdo->prepare('SELECT * FROM withdrawals WHERE id=? FOR UPDATE');$st->execute([$id]);$w=$st->fetch();
                if(!$w||!in_array($w['status'],['pending','approved','processing'],true))throw new RuntimeException('This withdrawal request can no longer be changed.');
                if($decision==='processing'){
                    if($w['status']!=='pending')throw new RuntimeException('Only pending withdrawals can move to processing.');
                    $newStatus='processing';
                    $message='Your withdrawal request is now being processed manually.';
                    $title='Withdrawal processing started';
                }elseif($decision==='rejected'){
                    $walletSt=$pdo->prepare('SELECT withdrawal_hold_balance FROM wallets WHERE user_id=? FOR UPDATE');$walletSt->execute([(int)$w['user_id']]);$wallet=$walletSt->fetch();
                    if(!$wallet||(float)$wallet['withdrawal_hold_balance']+0.001<(float)$w['amount'])throw new RuntimeException('The held withdrawal balance is inconsistent. Please reconcile the wallet before rejecting.');
                    $pdo->prepare('UPDATE wallets SET withdrawal_hold_balance=withdrawal_hold_balance-?,available_balance=available_balance+? WHERE user_id=?')->execute([$w['amount'],$w['amount'],(int)$w['user_id']]);
                    record_wallet($pdo,(int)$w['user_id'],'withdrawal_hold','debit',(float)$w['amount'],'withdrawal_reversal','Rejected withdrawal returned','withdrawal',$id);
                    record_wallet($pdo,(int)$w['user_id'],'available','credit',(float)$w['amount'],'withdrawal_reversal','Rejected withdrawal returned','withdrawal',$id);
                    $newStatus='rejected';$title='Withdrawal request rejected';$message='Your withdrawal request was rejected. The held amount has been returned to your available wallet. Reason: '.$note;
                }else{
                    $walletSt=$pdo->prepare('SELECT withdrawal_hold_balance FROM wallets WHERE user_id=? FOR UPDATE');$walletSt->execute([(int)$w['user_id']]);$wallet=$walletSt->fetch();
                    if(!$wallet||(float)$wallet['withdrawal_hold_balance']+0.001<(float)$w['amount'])throw new RuntimeException('The held withdrawal balance is inconsistent. Please reconcile the wallet before marking paid.');
                    $pdo->prepare('UPDATE wallets SET withdrawal_hold_balance=withdrawal_hold_balance-? WHERE user_id=?')->execute([$w['amount'],(int)$w['user_id']]);
                    record_wallet($pdo,(int)$w['user_id'],'withdrawal_hold','debit',(float)$w['amount'],'withdrawal_paid','Withdrawal marked paid by Admin; reference: '.$paymentReference,'withdrawal',$id);
                    $newStatus='paid';$title='Withdrawal marked paid';$message='Your withdrawal has been marked paid. Payment reference: '.$paymentReference;
                }
                $pdo->prepare('UPDATE withdrawals SET status=?,admin_note=?,payment_reference=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?')->execute([$newStatus,$note!==''?$note:null,$decision==='paid'?$paymentReference:null,(int)$admin['id'],$id]);
                notify($pdo,(int)$w['user_id'],$title,$message,'wallet',url('dashboard',['tab'=>'wallet']));
                audit($pdo,(int)$admin['id'],'withdrawal.'.$newStatus,'withdrawal',$id,['previous_status'=>$w['status'],'admin_note'=>$note,'payment_reference'=>$decision==='paid'?$paymentReference:null]);
                $pdo->commit();
                $success=$newStatus==='processing'?'Withdrawal moved to processing.':($newStatus==='paid'?'Withdrawal marked paid with a payment reference.':'Withdrawal rejected and amount returned to the worker.');
                flash('success',$success);redirect('dashboard',['tab'=>'finance','filter'=>$newStatus]);
            }
            case 'admin_add_funds': {
                $admin=require_role($pdo,'admin');$email=lower_text(trim((string)($_POST['email']??'')));$amount=round((float)($_POST['amount']??0),2);$note=trim((string)($_POST['description']??''));
                if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$amount<=0||$amount>10000000||strlen($note)<4||strlen($note)>255)throw new RuntimeException('Enter a valid publisher email, amount and external deposit reference.');
                $st=$pdo->prepare('SELECT id,status FROM users WHERE email=? AND user_type IN (\'publisher\',\'both\')');$st->execute([$email]);$target=$st->fetch();if(!$target)throw new RuntimeException('Publisher account not found.');if($target['status']!=='active')throw new RuntimeException('A suspended publisher wallet cannot be credited. Reactivate the account first.');
                $pdo->beginTransaction();$activeTarget=lock_active_account($pdo,(int)$target['id']);if(!in_array($activeTarget['user_type'],['publisher','both'],true))throw new RuntimeException('This account is no longer eligible for publisher deposits.');$walletSt=$pdo->prepare('SELECT id FROM wallets WHERE user_id=? FOR UPDATE');$walletSt->execute([(int)$target['id']]);if(!$walletSt->fetch())throw new RuntimeException('Publisher wallet was not found.');
                $ledgerDescription='Manual deposit credit · '.$note;
                $idempotencyKey=hash_hmac('sha256','admin-deposit-reference:'.lower_text(trim($note)),APP_KEY);
                $duplicate=$pdo->prepare('SELECT id FROM wallet_transactions WHERE idempotency_key=? LIMIT 1 FOR UPDATE');$duplicate->execute([$idempotencyKey]);if($duplicate->fetch())throw new RuntimeException('This external deposit reference has already been credited. Use a unique payment reference for each deposit.');
                $pdo->prepare('UPDATE wallets SET deposit_balance=deposit_balance+? WHERE user_id=?')->execute([$amount,(int)$target['id']]);
                record_wallet($pdo,(int)$target['id'],'deposit','credit',$amount,'admin_credit',$ledgerDescription,'admin_credit',(int)$admin['id'],$idempotencyKey);
                notify($pdo,(int)$target['id'],'Deposit wallet credited',money($amount).' has been added by TaskHaat Admin. Reference: '.$note,'wallet',url('dashboard',['tab'=>'wallet']));
                audit($pdo,(int)$admin['id'],'wallet.admin_credit','wallet',(int)$target['id'],['amount'=>$amount,'reference'=>$note]);
                $pdo->commit();flash('success','Publisher deposit wallet credited and ledger entry recorded.');redirect('dashboard',['tab'=>'finance']);
            }
            case 'update_settings': {
                $admin=require_role($pdo,'admin');$pub=(float)($_POST['publisher_fee_rate']??8);$worker=(float)($_POST['worker_fee_rate']??8);$min=(float)($_POST['min_withdrawal']??200);$max=(float)($_POST['max_withdrawal']??25000);$minDeposit=(float)($_POST['min_deposit']??100);$maxDeposit=(float)($_POST['max_deposit']??1000000);
                if($pub<0||$pub>30||$worker<0||$worker>30||$min<1||$max<$min||$max>1000000||$minDeposit<1||$maxDeposit<$minDeposit||$maxDeposit>10000000)throw new RuntimeException('Review commission rates and deposit/withdrawal limits. Commission rates must be 0–30%.');
                $manualFields=['manual_bkash_account_name'=>120,'manual_bkash_number'=>40,'manual_nagad_account_name'=>120,'manual_nagad_number'=>40,'manual_rocket_account_name'=>120,'manual_rocket_number'=>40,'manual_bank_name'=>120,'manual_bank_account_name'=>120,'manual_bank_account_number'=>80,'manual_bank_branch'=>120,'manual_deposit_instructions'=>1000];$manualValues=[];foreach($manualFields as $key=>$limit){$value=trim((string)($_POST[$key]??''));if(text_length($value)>$limit)throw new RuntimeException('One or more manual deposit details are too long.');$manualValues[$key]=$value;}foreach(['manual_bkash_number','manual_nagad_number','manual_rocket_number'] as $key){if($manualValues[$key]!==''&&!preg_match('/^[0-9+() -]{8,24}$/',$manualValues[$key]))throw new RuntimeException('Enter valid mobile receiving numbers for bKash, Nagad or Rocket.');}
                $pdo->beginTransaction();set_setting($pdo,'publisher_fee_rate',number_format($pub,2,'.',''));set_setting($pdo,'worker_fee_rate',number_format($worker,2,'.',''));set_setting($pdo,'min_withdrawal',number_format($min,2,'.',''));set_setting($pdo,'max_withdrawal',number_format($max,2,'.',''));set_setting($pdo,'min_deposit',number_format($minDeposit,2,'.',''));set_setting($pdo,'max_deposit',number_format($maxDeposit,2,'.',''));foreach($manualValues as $key=>$value)set_setting($pdo,$key,$value);audit($pdo,(int)$admin['id'],'settings.updated','settings',null,['publisher_fee_rate'=>$pub,'worker_fee_rate'=>$worker,'min_withdrawal'=>$min,'max_withdrawal'=>$max,'min_deposit'=>$minDeposit,'max_deposit'=>$maxDeposit,'manual_methods_configured'=>array_keys(array_filter($manualValues))]);$pdo->commit();flash('success','Platform settings saved, including offline deposit instructions.');redirect('dashboard',['tab'=>'settings']);
            }
            case 'update_profile': {
                $user=require_login($pdo);
                $first=trim((string)($_POST['first_name']??''));
                $last=trim((string)($_POST['last_name']??''));
                $phone=trim((string)($_POST['phone']??($user['phone']??'')));
                $country=trim((string)($_POST['country']??($user['country']??'Bangladesh')));
                $district=trim((string)($_POST['district']??($user['district']??'')));
                $city=trim((string)($_POST['city']??($user['city']??'')));
                $occupation=trim((string)($_POST['occupation']??($user['occupation']??'')));
                $skills=trim((string)($_POST['skills']??($user['skills']??'')));
                $organization=trim((string)($_POST['organization']??($user['organization']??'')));
                $website=trim((string)($_POST['website']??($user['website']??'')));
                if($first===''||$last==='')throw new RuntimeException('First and last name are required.');
                if(strlen($first)>80||strlen($last)>80)throw new RuntimeException('Names must be 80 characters or fewer.');
                if($phone!==''&&!preg_match('/^[0-9+ -]{8,16}$/',$phone))throw new RuntimeException('Enter a valid mobile number (8–16 digits, spaces, + or hyphens only).');
                if($country===''||strlen($country)>100)throw new RuntimeException('Enter a valid country.');
                if(strlen($district)>100||strlen($city)>120||strlen($occupation)>120||strlen($organization)>180||strlen($skills)>2000)throw new RuntimeException('One or more profile fields exceed the allowed length.');
                if($website!==''&&(!filter_var($website,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($website,PHP_URL_SCHEME)),['http','https'],true)))throw new RuntimeException('Enter a valid website or portfolio URL beginning with https:// or http://.');
                $pdo->beginTransaction();
                lock_active_account($pdo,(int)$user['id']);
                $pdo->prepare('UPDATE users SET first_name=?,last_name=?,phone=?,country=?,district=?,city=?,occupation=?,skills=?,organization=?,website=? WHERE id=?')->execute([
                    $first,$last,$phone!==''?$phone:null,$country,$district!==''?$district:null,$city!==''?$city:null,$occupation!==''?$occupation:null,$skills!==''?$skills:null,$organization!==''?$organization:null,$website!==''?$website:null,(int)$user['id']
                ]);
                audit($pdo,(int)$user['id'],'profile.updated','user',(int)$user['id'],['fields'=>['name','phone','country','district','city','occupation','skills','organization','website']]);
                $pdo->commit();
                flash('success','Your profile details have been updated.');redirect('dashboard',['tab'=>'profile']);
            }
            case 'mark_notification_read': {
                $user=require_login($pdo);$id=(int)($_POST['notification_id']??0);
                if($id<1)throw new RuntimeException('Select a valid notification.');
                $st=$pdo->prepare('UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?');$st->execute([$id,(int)$user['id']]);
                if(!$st->rowCount()){$check=$pdo->prepare('SELECT id FROM notifications WHERE id=? AND user_id=? AND is_read=1');$check->execute([$id,(int)$user['id']]);if(!$check->fetch())throw new RuntimeException('Notification not found.');}
                flash('success','Notification marked as read.');redirect('dashboard',['tab'=>'notifications']);
            }
            case 'mark_notifications_read': {
                $user=require_login($pdo);$pdo->prepare('UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0')->execute([(int)$user['id']]);flash('success','Notifications marked as read.');redirect('dashboard',['tab'=>'notifications']);
            }
            case 'admin_add_category': {
                $admin=require_role($pdo,'admin');$name=trim((string)($_POST['name']??''));$desc=trim((string)($_POST['description']??''));
                if(strlen($name)<3||strlen($name)>100||strlen($desc)>255)throw new RuntimeException('Category name must contain 3–100 characters and description must be 255 characters or fewer.');
                $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$name),'-'));if($slug==='')throw new RuntimeException('The category name must include letters or numbers.');
                $baseSlug=$slug;$suffix=2;$check=$pdo->prepare('SELECT id FROM categories WHERE slug=?');while(true){$check->execute([$slug]);if(!$check->fetch())break;$slug=$baseSlug.'-'.$suffix++;if($suffix>1000)throw new RuntimeException('Could not generate a unique category slug.');}
                $pdo->beginTransaction();$pdo->prepare('INSERT INTO categories(name,slug,description,is_active) VALUES(?,?,?,1)')->execute([$name,$slug,$desc!==''?$desc:null]);$id=(int)$pdo->lastInsertId();audit($pdo,(int)$admin['id'],'category.created','category',$id,['name'=>$name,'slug'=>$slug]);$pdo->commit();flash('success','Category added.');redirect('dashboard',['tab'=>'categories']);
            }
            case 'admin_update_category': {
                $admin=require_role($pdo,'admin');$id=(int)($_POST['category_id']??0);$name=trim((string)($_POST['name']??''));$slug=strtolower(trim((string)($_POST['slug']??'')));$desc=trim((string)($_POST['description']??''));
                if($id<1||strlen($name)<3||strlen($name)>100||strlen($desc)>255||!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/',$slug)||strlen($slug)>120)throw new RuntimeException('Enter a valid category name, slug and description.');
                $st=$pdo->prepare('SELECT id FROM categories WHERE id=?');$st->execute([$id]);if(!$st->fetch())throw new RuntimeException('Category not found.');
                $st=$pdo->prepare('SELECT id FROM categories WHERE slug=? AND id<>?');$st->execute([$slug,$id]);if($st->fetch())throw new RuntimeException('That category slug is already in use.');
                $pdo->beginTransaction();$pdo->prepare('UPDATE categories SET name=?,slug=?,description=? WHERE id=?')->execute([$name,$slug,$desc!==''?$desc:null,$id]);audit($pdo,(int)$admin['id'],'category.updated','category',$id,['name'=>$name,'slug'=>$slug]);$pdo->commit();flash('success','Category updated.');redirect('dashboard',['tab'=>'categories']);
            }
            case 'admin_delete_category': {
                $admin=require_role($pdo,'admin');$id=(int)($_POST['category_id']??0);
                if($id<1)throw new RuntimeException('Select a valid category.');
                $pdo->beginTransaction();
                $st=$pdo->prepare('SELECT id,name,slug FROM categories WHERE id=? FOR UPDATE');$st->execute([$id]);$category=$st->fetch();
                if(!$category)throw new RuntimeException('Category not found.');
                $st=$pdo->prepare('SELECT COUNT(*) FROM campaigns WHERE category_id=?');$st->execute([$id]);$campaignCount=(int)$st->fetchColumn();
                if($campaignCount>0)throw new RuntimeException('This category is linked to '.$campaignCount.' campaign(s). Deactivate it instead of deleting it.');
                $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$id]);
                audit($pdo,(int)$admin['id'],'category.deleted','category',$id,['name'=>$category['name'],'slug'=>$category['slug']]);
                $pdo->commit();flash('success','Category permanently deleted.');redirect('dashboard',['tab'=>'categories']);
            }
            case 'admin_toggle_category': {
                $admin=require_role($pdo,'admin');$id=(int)($_POST['category_id']??0);$active=(int)($_POST['is_active']??-1);if($id<1||!in_array($active,[0,1],true))throw new RuntimeException('Invalid category status.');
                $st=$pdo->prepare('SELECT name,is_active FROM categories WHERE id=?');$st->execute([$id]);$category=$st->fetch();if(!$category)throw new RuntimeException('Category not found.');
                $pdo->beginTransaction();$pdo->prepare('UPDATE categories SET is_active=? WHERE id=?')->execute([$active,$id]);audit($pdo,(int)$admin['id'],$active?'category.activated':'category.deactivated','category',$id,['name'=>$category['name']]);$pdo->commit();flash('success',$active?'Category activated.':'Category deactivated. Existing campaigns are retained.');redirect('dashboard',['tab'=>'categories']);
            }
            case 'admin_toggle_user': {
                $admin=require_role($pdo,'admin');$id=(int)($_POST['user_id']??0);$status=(string)($_POST['status']??'');
                if(!in_array($status,['active','suspended'],true)||$id<1||$id===(int)$admin['id'])throw new RuntimeException('Invalid account status change.');
                $pdo->beginTransaction();$st=$pdo->prepare('SELECT id,first_name,last_name,user_type,status FROM users WHERE id=? FOR UPDATE');$st->execute([$id]);$target=$st->fetch();if(!$target||$target['user_type']==='admin')throw new RuntimeException('This account does not exist or is protected.');if($target['status']===$status)throw new RuntimeException('The account already has this status.');
                $pdo->prepare('UPDATE users SET status=? WHERE id=?')->execute([$status,$id]);$paused=0;
                if($status==='suspended'&&in_array($target['user_type'],['publisher','both'],true)){$st=$pdo->prepare("UPDATE campaigns SET status='paused' WHERE publisher_id=? AND status='approved'");$st->execute([$id]);$paused=$st->rowCount();}
                notify($pdo,$id,'Account status updated',$status==='active'?'Your account has been reactivated. Paused campaigns remain paused until you resume them.':'Your account has been suspended.'.($paused?' '.$paused.' live campaigns were paused.':''),'account');
                audit($pdo,(int)$admin['id'],'user.'.$status,'user',$id,['previous_status'=>$target['status'],'user_type'=>$target['user_type'],'campaigns_paused'=>$paused]);$pdo->commit();flash('success',$status==='suspended'?'User suspended. Any live publisher campaigns were paused.':'User reactivated. Campaigns remain paused until the publisher resumes them.');redirect('dashboard',['tab'=>'users']);
            }
            case 'resume_campaign': {
                $user=require_role($pdo,'publisher');$id=(int)($_POST['campaign_id']??0);$pdo->beginTransaction();
                lock_active_account($pdo,(int)$user['id']);
                $st=$pdo->prepare("UPDATE campaigns SET status='approved' WHERE id=? AND publisher_id=? AND status='paused' AND claimed_slots<worker_slots AND budget_remaining>0 AND (deadline IS NULL OR deadline>NOW())");$st->execute([$id,(int)$user['id']]);
                if(!$st->rowCount())throw new RuntimeException('This campaign cannot be resumed. Check its deadline, remaining slots and budget.');
                audit($pdo,(int)$user['id'],'campaign.resumed','campaign',$id);$pdo->commit();flash('success','Campaign resumed and visible to workers.');redirect('dashboard',['tab'=>'campaigns']);
            }
            case 'cancel_campaign': {
                $user=require_role($pdo,'publisher');$id=(int)($_POST['campaign_id']??0);$pdo->beginTransaction();
                lock_active_account($pdo,(int)$user['id']);
                $st=$pdo->prepare('SELECT * FROM campaigns WHERE id=? AND publisher_id=? FOR UPDATE');$st->execute([$id,(int)$user['id']]);$c=$st->fetch();
                if(!$c||!in_array($c['status'],['pending','approved','paused'],true)||(int)$c['claimed_slots']!==0)throw new RuntimeException('Only a campaign with no claimed worker slots can be cancelled.');
                $st=$pdo->prepare('SELECT COUNT(*) FROM claims WHERE campaign_id=?');$st->execute([$id]);if((int)$st->fetchColumn()>0)throw new RuntimeException('This campaign already has claims and cannot be cancelled from this screen.');
                $amount=(float)$c['budget_remaining'];
                if($amount>0){
                    $walletCheck=$pdo->prepare('SELECT reserved_balance FROM wallets WHERE user_id=? FOR UPDATE');$walletCheck->execute([(int)$user['id']]);$wallet=$walletCheck->fetch();
                    if(!$wallet||(float)$wallet['reserved_balance']+0.001<$amount)throw new RuntimeException('The publisher reserved balance does not cover this campaign refund. Contact an Admin to reconcile the wallet.');
                    $walletUpdate=$pdo->prepare('UPDATE wallets SET reserved_balance=reserved_balance-?,deposit_balance=deposit_balance+? WHERE user_id=? AND reserved_balance>=?');$walletUpdate->execute([$amount,$amount,(int)$user['id'],$amount]);
                    if($walletUpdate->rowCount()!==1)throw new RuntimeException('The campaign refund could not be reconciled safely.');
                    record_wallet($pdo,(int)$user['id'],'reserved','debit',$amount,'campaign_cancel_refund','Campaign cancelled: '.$c['title'],'campaign',$id);record_wallet($pdo,(int)$user['id'],'deposit','credit',$amount,'campaign_cancel_refund','Campaign cancellation refund: '.$c['title'],'campaign',$id);
                }
                $pdo->prepare("UPDATE campaigns SET status='cancelled',budget_remaining=0 WHERE id=?")->execute([$id]);notify($pdo,(int)$user['id'],'Campaign cancelled','Your campaign “'.$c['title'].'” was cancelled. Remaining reserved funds were returned to your deposit wallet.','campaign',url('dashboard',['tab'=>'campaigns']));audit($pdo,(int)$user['id'],'campaign.cancelled','campaign',$id,['refund'=>$amount]);$pdo->commit();flash('success','Campaign cancelled and remaining funds returned.');redirect('dashboard',['tab'=>'campaigns']);
            }
            case 'pause_campaign': {
                $user=require_role($pdo,'publisher');$id=(int)($_POST['campaign_id']??0);$pdo->beginTransaction();lock_active_account($pdo,(int)$user['id']);$st=$pdo->prepare("UPDATE campaigns SET status='paused' WHERE id=? AND publisher_id=? AND status='approved'");$st->execute([$id,(int)$user['id']]);if(!$st->rowCount())throw new RuntimeException('Only your approved campaigns can be paused.');audit($pdo,(int)$user['id'],'campaign.paused','campaign',$id);$pdo->commit();flash('success','Campaign paused. Existing claims can still be reviewed.');redirect('dashboard',['tab'=>'campaigns']);
            }
            default: throw new RuntimeException('Unknown form action.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach ($createdUploads as $upload) {
            $path=(string)($upload['path']??'');
            if ($path!=='' && is_file($path)) @unlink($path);
        }
        if (APP_ENV==='local' && !($e instanceof RuntimeException) && !($e instanceof PDOException)) error_log((string)$e);
        $message=$e instanceof RuntimeException ? $e->getMessage() : ($e instanceof PDOException && str_contains($e->getMessage(),'Duplicate') ? ($action==='submit_deposit_request'?'This payment reference was already submitted. Check your deposit history.':'That email, phone, category or payment reference already exists.') : 'A system or database error occurred. Please try again.');
        if(isset($_FILES['cover_image']['tmp_name']) && is_uploaded_file($_FILES['cover_image']['tmp_name'])) { /* PHP cleans the temp upload automatically. */ }
        flash('error',$message);
        $fallback=(string)($_POST['_return']??$_GET['route']??'home');$allowed=['home','browse','dashboard','login','register','task','contact','help','guide','safety','privacy','terms'];$fallback=in_array($fallback,$allowed,true)?$fallback:'home';$params=[];if(isset($_POST['_return_id']))$params['id']=(int)$_POST['_return_id'];if($fallback==='dashboard'){if(isset($_GET['tab']))$params['tab']=(string)$_GET['tab'];if(isset($_GET['as']))$params['as']=(string)$_GET['as'];}redirect($fallback,$params);
    }
}
