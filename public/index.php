<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD']==='POST') process_post($pdo);
$route=(string)($_GET['route']??'home');
$user=current_user($pdo);
$flashes=pull_flashes();

if($route==='download_submission'){
    $user=require_login($pdo);$fileId=(int)($_GET['id']??0);
    $st=$pdo->prepare('SELECT sf.*,s.claim_id,cl.worker_id,c.publisher_id FROM submission_files sf JOIN submissions s ON s.id=sf.submission_id JOIN claims cl ON cl.id=s.claim_id JOIN campaigns c ON c.id=cl.campaign_id WHERE sf.id=?');$st->execute([$fileId]);$f=$st->fetch();
    if(!$f || ((int)$f['worker_id']!==(int)$user['id'] && (int)$f['publisher_id']!==(int)$user['id'] && $user['user_type']!=='admin')){http_response_code(404);exit('File not found.');}
    $path=UPLOAD_DIR.'/'.$f['storage_name'];if(!is_file($path)){http_response_code(404);exit('File not found.');}
    $downloadMimes=['image/jpeg','image/png','image/webp','application/pdf','text/plain'];
    $mime=in_array((string)$f['mime_type'],$downloadMimes,true)?(string)$f['mime_type']:'application/octet-stream';
    $safeName=preg_replace('/[^A-Za-z0-9._ -]/','_',basename((string)$f['original_name'])) ?: 'evidence-file';
    header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store, max-age=0');header('Pragma: no-cache');
    header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('Content-Disposition: attachment; filename="'.$safeName.'"');readfile($path);exit;
}
if($route==='deposit_proof'){
    $user=require_login($pdo);$requestId=(int)($_GET['id']??0);
    $st=$pdo->prepare('SELECT id,publisher_id,proof_storage_name,proof_original_name,proof_mime_type,proof_file_size FROM manual_deposit_requests WHERE id=?');$st->execute([$requestId]);$proof=$st->fetch();
    if(!$proof||((int)$proof['publisher_id']!==(int)$user['id']&&$user['user_type']!=='admin')){http_response_code(404);exit('File not found.');}
    if(empty($proof['proof_storage_name'])){http_response_code(404);exit('No proof file is attached to this request.');}
    if(!preg_match('/^[a-f0-9]{36}\.(?:jpg|png|webp|pdf)$/',(string)$proof['proof_storage_name'])){http_response_code(404);exit('File not found.');}
    $path=DEPOSIT_UPLOAD_DIR.'/'.$proof['proof_storage_name'];if(!is_file($path)){http_response_code(404);exit('File not found.');}
    $allowed=['image/jpeg','image/png','image/webp','application/pdf'];$mime=in_array((string)$proof['proof_mime_type'],$allowed,true)?(string)$proof['proof_mime_type']:'application/octet-stream';
    $safeName=preg_replace('/[^A-Za-z0-9._ -]/','_',basename((string)$proof['proof_original_name']))?:'deposit-proof';
    header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store, max-age=0');header('Pragma: no-cache');header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('Content-Disposition: inline; filename="'.$safeName.'"');readfile($path);exit;
}
if($route==='admin_export'){
    $admin=require_role($pdo,'admin');
    $type=(string)($_GET['type']??'');
    if(!in_array($type,['campaigns','users','finance'],true)){http_response_code(400);exit('Invalid export type.');}
    $from=trim((string)($_GET['from']??''));$to=trim((string)($_GET['to']??''));
    if($from!==''&&(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$from,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1])))$from='';
    if($to!==''&&(!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$to,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1])))$to='';
    if($from!==''&&$to!==''&&$from>$to){$swap=$from;$from=$to;$to=$swap;}
    $dateSql='';$params=[];
    if($from!==''){$dateSql.=' AND x.created_at>=?';$params[]=$from.' 00:00:00';}
    if($to!==''){$dateSql.=' AND x.created_at<DATE_ADD(?, INTERVAL 1 DAY)';$params[]=$to.' 00:00:00';}
    $filename='taskhaat-'.$type.'-'.date('Ymd-His').'.csv';
    header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.$filename.'"');header('Cache-Control: no-store, no-cache, must-revalidate');
    $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
    $cell=static function(mixed $value):string {$v=(string)($value??'');if($v!==''&&preg_match('/^[\s]*[=+@\-]/',$v))$v="'".$v;return $v;};
    if($type==='campaigns'){
        $sql='SELECT x.id,x.title,x.status,x.reward_per_worker,x.worker_slots,x.claimed_slots,x.budget_total,x.budget_remaining,x.created_at,x.reviewed_at,u.email publisher_email,cat.name category_name FROM campaigns x JOIN users u ON u.id=x.publisher_id JOIN categories cat ON cat.id=x.category_id WHERE 1=1'.$dateSql.' ORDER BY x.created_at DESC';
        $st=$pdo->prepare($sql);$st->execute($params);fputcsv($out,['Campaign ID','Title','Status','Reward per worker BDT','Worker slots','Claimed slots','Budget total BDT','Budget remaining BDT','Created at','Reviewed at','Publisher email','Category']);
    }elseif($type==='users'){
        $sql='SELECT x.id,x.first_name,x.last_name,x.email,x.phone,x.user_type,x.status,x.country,x.district,x.city,x.created_at FROM users x WHERE x.user_type<>\'admin\''.$dateSql.' ORDER BY x.created_at DESC';
        $st=$pdo->prepare($sql);$st->execute($params);fputcsv($out,['User ID','First name','Last name','Email','Phone','Account type','Status','Country','District','City','Joined at']);
    }else{
        $sql="SELECT x.created_at,'platform_fee' entry_type,x.amount,'credit' direction,x.transaction_type,x.description,x.reference_type,x.reference_id,NULL user_email FROM platform_transactions x WHERE 1=1".$dateSql." UNION ALL SELECT x.created_at,'wallet_entry',x.amount,x.direction,x.transaction_type,x.description,x.reference_type,x.reference_id,u.email FROM wallet_transactions x LEFT JOIN users u ON u.id=x.user_id WHERE x.transaction_type IN ('worker_reward','admin_credit','manual_deposit')".str_replace('x.created_at','x.created_at',$dateSql).' ORDER BY created_at DESC';
        $st=$pdo->prepare($sql);$st->execute(array_merge($params,$params));fputcsv($out,['Date','Entry type','Amount BDT','Direction','Transaction type','Description','Reference type','Reference ID','User email']);
    }
    while($row=$st->fetch(PDO::FETCH_NUM)){foreach($row as $i=>$value){if(!is_numeric($value)||in_array($i,[0,1,2,3,4,5,6,7,8,9,10,11],true))$row[$i]=$cell($value);}fputcsv($out,$row);}
    fclose($out);exit;
}


$title='TaskHaat — Microtasks. Real progress.';
$view='home';
$categories=$pdo->query('SELECT id,name,slug,description FROM categories WHERE is_active=1 ORDER BY name')->fetchAll();

switch($route){
    case 'home': {
        $view='home';$tasks=$pdo->query("SELECT c.*,cat.name category_name,CONCAT(u.first_name,' ',u.last_name) publisher_name FROM campaigns c JOIN categories cat ON cat.id=c.category_id JOIN users u ON u.id=c.publisher_id WHERE c.status='approved' AND (c.deadline IS NULL OR c.deadline>=NOW()) ORDER BY c.created_at DESC LIMIT 8")->fetchAll();
        $stats=[];$stats['slots']=(int)$pdo->query("SELECT COALESCE(SUM(GREATEST(worker_slots-claimed_slots,0)),0) FROM campaigns WHERE status='approved' AND (deadline IS NULL OR deadline>=NOW())")->fetchColumn();$stats['members']=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='active' AND user_type<>'admin'")->fetchColumn();$stats['approved']=(int)$pdo->query("SELECT COUNT(*) FROM submissions WHERE status='approved'")->fetchColumn();$stats['paid']=(float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE transaction_type='worker_reward' AND direction='credit'")->fetchColumn();
        break;
    }
    case 'browse': {
        $view='browse';
        $q=trim((string)($_GET['q']??''));
        $category=max(0,(int)($_GET['category']??0));
        $min=max(0,(float)($_GET['min_reward']??0));
        $sort=(string)($_GET['sort']??'newest');
        if(!in_array($sort,['newest','reward','slots','deadline'],true))$sort='newest';
        $perPage=12;
        $page=max(1,(int)($_GET['page']??1));
        $where=" FROM campaigns c JOIN categories cat ON cat.id=c.category_id JOIN users u ON u.id=c.publisher_id WHERE c.status='approved' AND (c.deadline IS NULL OR c.deadline>=NOW()) AND c.claimed_slots<c.worker_slots";
        $params=[];
        if($q!==''){$where.=' AND (c.title LIKE ? OR c.description LIKE ?)';$params[]='%'.$q.'%';$params[]='%'.$q.'%';}
        if($category>0){$where.=' AND c.category_id=?';$params[]=$category;}
        if($min>0){$where.=' AND c.reward_per_worker>=?';$params[]=$min;}
        $count=$pdo->prepare('SELECT COUNT(*)'.$where);$count->execute($params);$totalTasks=(int)$count->fetchColumn();
        $totalPages=max(1,(int)ceil($totalTasks/$perPage));
        if($page>$totalPages)$page=$totalPages;
        $offset=($page-1)*$perPage;
        $order=match($sort){
            'reward'=>' ORDER BY c.reward_per_worker DESC,c.created_at DESC',
            'slots'=>' ORDER BY (c.worker_slots-c.claimed_slots) DESC,c.created_at DESC',
            'deadline'=>' ORDER BY (c.deadline IS NULL) ASC,c.deadline ASC,c.created_at DESC',
            default=>' ORDER BY c.created_at DESC'
        };
        $sql="SELECT c.*,cat.name category_name,CONCAT(u.first_name,' ',u.last_name) publisher_name".$where.$order.' LIMIT '.$perPage.' OFFSET '.$offset;
        $st=$pdo->prepare($sql);$st->execute($params);$tasks=$st->fetchAll();
        $title='Find tasks — TaskHaat';break;
    }
    case 'task': {
        $view='task';$id=(int)($_GET['id']??0);
        $st=$pdo->prepare("SELECT c.*,cat.name category_name,CONCAT(u.first_name,' ',u.last_name) publisher_name FROM campaigns c JOIN categories cat ON cat.id=c.category_id JOIN users u ON u.id=c.publisher_id WHERE c.id=?");
        $st->execute([$id]);$task=$st->fetch();
        $claim=null;
        if($task&&$user&&has_role($user,'worker')){$claimSt=$pdo->prepare('SELECT * FROM claims WHERE campaign_id=? AND worker_id=? LIMIT 1');$claimSt->execute([$id,(int)$user['id']]);$claim=$claimSt->fetch()?:null;}
        $canViewUnpublished=$task&&$user&&((int)$task['publisher_id']===(int)$user['id']||has_role($user,'admin')||$claim!==null);
        if(!$task||($task['status']!=='approved'&&!$canViewUnpublished)){
            http_response_code(404);$view='404';$title='Task not found — TaskHaat';break;
        }
        $st=$pdo->prepare("SELECT c.*,cat.name category_name,CONCAT(u.first_name,' ',u.last_name) publisher_name FROM campaigns c JOIN categories cat ON cat.id=c.category_id JOIN users u ON u.id=c.publisher_id WHERE c.status='approved' AND c.id<>? AND c.category_id=? AND c.claimed_slots<c.worker_slots AND (c.deadline IS NULL OR c.deadline>=NOW()) ORDER BY c.created_at DESC LIMIT 3");
        $st->execute([$id,(int)$task['category_id']]);$relatedTasks=$st->fetchAll();
        $title=$task['title'].' — TaskHaat';break;
    }
    case 'fees': {$view='fees';$title='Fees & rewards — TaskHaat';break;}
    case 'help': {$view='help';$title='Help Center — TaskHaat';break;}
    case 'guide': {$guideType=(string)($_GET['type']??'worker');if(!in_array($guideType,['worker','publisher'],true))$guideType='worker';$view='guide';$title=ucfirst($guideType).' guide — TaskHaat';break;}
    case 'safety': {$view='safety';$title='Trust & safety — TaskHaat';break;}
    case 'contact': {$view='contact';$title='Contact support — TaskHaat';break;}
    case 'privacy': case 'terms': {$legalType=$route;$view='legal';$title=($route==='privacy'?'Privacy notice':'Terms of use').' — TaskHaat';break;}
    case 'login': case 'register': {if($user)redirect('dashboard');$view='auth/'.($route==='login'?'login':'register');$title=($route==='login'?'Log in':'Create account').' — TaskHaat';break;}
    case 'dashboard': {
        $user=require_login($pdo);$view='dashboard';$tab=(string)($_GET['tab']??'overview');
        if($user['user_type']==='admin')$workspace='admin';else{$requested=(string)($_GET['as']??'');$workspace=in_array($requested,['worker','publisher'],true)&&has_role($user,$requested)?$requested:(has_role($user,'worker')?'worker':'publisher');}
        $allowedTabs=['worker'=>['overview','mytasks','submissions','wallet','notifications','profile'],'publisher'=>['overview','campaigns','create','edit','campaign_view','review','wallet','notifications','profile'],'admin'=>['overview','moderation','submissions','users','categories','finance','reports','support','settings','notifications','audit']];
        if(!in_array($tab,$allowedTabs[$workspace],true))$tab='overview';
        $title=ucfirst($workspace).' workspace — TaskHaat';break;
    }
    default: {http_response_code(404);$view='404';$title='Page not found — TaskHaat';}
}

require dirname(__DIR__).'/views/layout.php';
