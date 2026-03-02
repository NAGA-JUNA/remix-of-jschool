<?php
$pageTitle='Settings';require_once __DIR__.'/../includes/auth.php';requireAdmin();require_once __DIR__.'/../includes/file-handler.php';$db=getDB();

// === Brand Color Extraction Functions ===
function extractDominantColors($imagePath, $count = 3) {
    $data = @file_get_contents($imagePath);
    if (!$data) return [];
    $img = @imagecreatefromstring($data);
    if (!$img) return [];
    $w = imagesx($img); $h = imagesy($img);
    $sample = imagecreatetruecolor(50, 50);
    imagealphablending($sample, false); imagesavealpha($sample, true);
    imagecopyresampled($sample, $img, 0,0,0,0, 50,50, $w,$h);
    imagedestroy($img);
    $colors = [];
    for ($y=0; $y<50; $y++) {
        for ($x=0; $x<50; $x++) {
            $rgb = imagecolorat($sample, $x, $y);
            $r = ($rgb>>16)&0xFF; $g = ($rgb>>8)&0xFF; $b = $rgb&0xFF;
            $a = ($rgb>>24)&0x7F;
            if ($a > 60) continue;
            $brightness = ($r*299 + $g*587 + $b*114) / 1000;
            if ($brightness > 240 || $brightness < 15) continue;
            $qr = round($r/32)*32; $qg = round($g/32)*32; $qb = round($b/32)*32;
            $key = "$qr,$qg,$qb";
            $colors[$key] = ($colors[$key] ?? 0) + 1;
        }
    }
    imagedestroy($sample);
    arsort($colors);
    $result = [];
    foreach (array_slice(array_keys($colors), 0, $count) as $c) {
        list($r,$g,$b) = explode(',', $c);
        $result[] = sprintf('#%02x%02x%02x', (int)$r, (int)$g, (int)$b);
    }
    if (count($result) === 1) {
        $result[] = adjustBrandHue($result[0], 30);
        $result[] = adjustBrandHue($result[0], 180);
    } elseif (count($result) === 2) {
        $result[] = adjustBrandHue($result[0], 180);
    }
    if (empty($result)) {
        $result = ['#1e40af', '#6366f1', '#f59e0b'];
    }
    return $result;
}

function adjustBrandHue($hex, $shift) {
    $r = hexdec(substr($hex,1,2)); $g = hexdec(substr($hex,3,2)); $b = hexdec(substr($hex,5,2));
    $r /= 255; $g /= 255; $b /= 255;
    $max = max($r,$g,$b); $min = min($r,$g,$b); $d = $max - $min;
    $l = ($max + $min) / 2; $s = 0; $h = 0;
    if ($d > 0) {
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);
        if ($max == $r) $h = fmod(($g - $b) / $d + 6, 6) * 60;
        elseif ($max == $g) $h = (($b - $r) / $d + 2) * 60;
        else $h = (($r - $g) / $d + 4) * 60;
    }
    $h = fmod($h + $shift, 360); if ($h < 0) $h += 360;
    // Normalize brightness
    if ($l < 0.12) $l = 0.3;
    if ($l > 0.88) $l = 0.6;
    return hslToHex($h, $s, $l);
}

function hslToHex($h, $s, $l) {
    $c = (1 - abs(2*$l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h/60, 2) - 1));
    $m = $l - $c/2;
    if ($h < 60) { $r=$c; $g=$x; $b=0; }
    elseif ($h < 120) { $r=$x; $g=$c; $b=0; }
    elseif ($h < 180) { $r=0; $g=$c; $b=$x; }
    elseif ($h < 240) { $r=0; $g=$x; $b=$c; }
    elseif ($h < 300) { $r=$x; $g=0; $b=$c; }
    else { $r=$c; $g=0; $b=$x; }
    return sprintf('#%02x%02x%02x', round(($r+$m)*255), round(($g+$m)*255), round(($b+$m)*255));
}

function saveBrandColors($db, $colors, $auto = true) {
    $keys = ['brand_primary', 'brand_secondary', 'brand_accent'];
    foreach ($keys as $i => $key) {
        $val = $colors[$i] ?? $colors[0];
        $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$key, $val, $val]);
    }
    $autoVal = $auto ? '1' : '0';
    $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('brand_colors_auto',?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$autoVal, $autoVal]);
}

if($_SERVER['REQUEST_METHOD']==='POST'&&verifyCsrf()){$action=$_POST['form_action']??'settings';

if($action==='settings'){$keys=['school_name','school_short_name','school_tagline','school_email','school_phone','school_address','primary_color','academic_year','admission_open'];foreach($keys as $k){$v=trim($_POST[$k]??'');$db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$k,$v,$v]);}$mmVal=isset($_POST['maintenance_mode'])?'1':'0';$db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('maintenance_mode',?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$mmVal,$mmVal]);auditLog('update_settings','settings');setFlash('success','Settings updated.');}

if($action==='logo_upload'){
  if(!isSuperAdmin()){setFlash('error','Only Super Admin can change the logo.');
  }elseif(!empty($_FILES['school_logo']['name'])&&$_FILES['school_logo']['error']===UPLOAD_ERR_OK){
    $ext=strtolower(pathinfo($_FILES['school_logo']['name'],PATHINFO_EXTENSION));
    if(in_array($ext,['jpg','jpeg','png','webp','svg'])){
      FileHandler::ensureDir(__DIR__.'/../uploads/branding');
      $fname='school_logo.'.$ext;FileHandler::saveUploadedFile($_FILES['school_logo']['tmp_name'],__DIR__.'/../uploads/branding/'.$fname);
      $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('school_logo',?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$fname,$fname]);
      $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('logo_updated_at',?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([time(),time()]);
      // Auto-generate favicon 32x32
      $srcPath=__DIR__.'/../uploads/branding/'.$fname;
      if(in_array($ext,['jpg','jpeg','png','webp'])){
        $srcImg=imagecreatefromstring(file_get_contents($srcPath));
        if($srcImg){$fav=imagecreatetruecolor(32,32);imagealphablending($fav,false);imagesavealpha($fav,true);$transparent=imagecolorallocatealpha($fav,0,0,0,127);imagefill($fav,0,0,$transparent);imagecopyresampled($fav,$srcImg,0,0,0,0,32,32,imagesx($srcImg),imagesy($srcImg));imagepng($fav,__DIR__.'/../uploads/branding/favicon.png');imagedestroy($fav);imagedestroy($srcImg);
        $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('school_favicon',?) ON DUPLICATE KEY UPDATE setting_value=?")->execute(['favicon.png','favicon.png']);
        $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('favicon_updated_at',?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([time(),time()]);
        }
      }
      // Auto-extract brand colors from logo
      if(in_array($ext,['jpg','jpeg','png','webp'])){
        $extracted = extractDominantColors($srcPath);
        if(!empty($extracted)) saveBrandColors($db, $extracted, true);
      }
      auditLog('update_logo','settings');setFlash('success','Logo updated. Brand colors auto-extracted.');
    }else setFlash('error','Logo must be JPG, PNG, WebP or SVG.');
  }
}

// Re-extract brand colors from current logo
if($action==='reextract_colors'){
  if(!isSuperAdmin()){setFlash('error','Only Super Admin can change brand colors.');
  }else{
    $logoFile = $db->query("SELECT setting_value FROM settings WHERE setting_key='school_logo'")->fetchColumn();
    $logoPath = $logoFile ? __DIR__.'/../uploads/branding/'.$logoFile : '';
    if($logoPath && file_exists($logoPath)){
      $extracted = extractDominantColors($logoPath);
      if(!empty($extracted)){ saveBrandColors($db, $extracted, true); setFlash('success','Brand colors re-extracted from logo.'); }
      else setFlash('error','Could not extract colors from logo.');
    }else setFlash('error','No logo found. Upload a logo first.');
  }
}

// Manual brand color override
if($action==='brand_colors_manual'){
  if(!isSuperAdmin()){setFlash('error','Only Super Admin can change brand colors.');
  }else{
    $bp = trim($_POST['brand_primary'] ?? '');
    $bs = trim($_POST['brand_secondary'] ?? '');
    $ba = trim($_POST['brand_accent'] ?? '');
    if(preg_match('/^#[0-9a-fA-F]{6}$/', $bp) && preg_match('/^#[0-9a-fA-F]{6}$/', $bs) && preg_match('/^#[0-9a-fA-F]{6}$/', $ba)){
      saveBrandColors($db, [$bp, $bs, $ba], false);
      setFlash('success','Brand colors updated manually.');
    }else setFlash('error','Invalid color format. Use #RRGGBB.');
  }
}

// Page Colors (Public + Admin) manual override
if($action==='page_colors_manual'){
  if(!isSuperAdmin()){setFlash('error','Only Super Admin can change page colors.');
  }else{
    $colorKeys = [
      'color_navbar_bg','color_navbar_text','color_topbar_bg',
      'color_footer_bg','color_footer_cta_bg','color_footer_cta_end',
      'color_sidebar_bg','color_sidebar_bg_dark','color_body_bg','color_body_bg_dark'
    ];
    $valid = true;
    foreach($colorKeys as $ck){
      $val = trim($_POST[$ck] ?? '');
      if($val !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $val)){
        $valid = false; break;
      }
    }
    if($valid){
      foreach($colorKeys as $ck){
        $val = trim($_POST[$ck] ?? '');
        if($val !== ''){
          $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$ck,$val,$val]);
        }
      }
      auditLog('update_page_colors','settings');setFlash('success','Page colors updated successfully.');
    }else setFlash('error','Invalid color format. Use #RRGGBB.');
  }
}

// Reset page colors to defaults
if($action==='reset_page_colors'){
  if(!isSuperAdmin()){setFlash('error','Only Super Admin can reset page colors.');
  }else{
    $resetKeys = ['color_navbar_bg','color_navbar_text','color_topbar_bg','color_footer_bg','color_footer_cta_bg','color_footer_cta_end','color_sidebar_bg','color_sidebar_bg_dark','color_body_bg','color_body_bg_dark'];
    foreach($resetKeys as $rk){
      $db->prepare("DELETE FROM settings WHERE setting_key=?")->execute([$rk]);
    }
    auditLog('reset_page_colors','settings');setFlash('success','Page colors reset to defaults.');
  }
}

if($action==='favicon_upload'){
  if(!isSuperAdmin()){setFlash('error','Only Super Admin can change the favicon.');
  }elseif(!empty($_FILES['school_favicon']['name'])&&$_FILES['school_favicon']['error']===UPLOAD_ERR_OK){
    $ext=strtolower(pathinfo($_FILES['school_favicon']['name'],PATHINFO_EXTENSION));
    if(in_array($ext,['ico','png','svg','jpg','jpeg'])){
      FileHandler::ensureDir(__DIR__.'/../uploads/branding');
      $fname='favicon.'.$ext;FileHandler::saveUploadedFile($_FILES['school_favicon']['tmp_name'],__DIR__.'/../uploads/branding/'.$fname);
      $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('school_favicon',?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$fname,$fname]);
      $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('favicon_updated_at',?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([time(),time()]);
      auditLog('update_favicon','settings');setFlash('success','Favicon updated.');
    }else setFlash('error','Favicon must be ICO, PNG, SVG or JPG.');
  }
}

if($action==='admin_logo_upload'){
  if(!isSuperAdmin()){setFlash('error','Only Super Admin can change the admin logo.');
  }elseif(!empty($_FILES['admin_logo']['name'])&&$_FILES['admin_logo']['error']===UPLOAD_ERR_OK){
    $ext=strtolower(pathinfo($_FILES['admin_logo']['name'],PATHINFO_EXTENSION));
    if(in_array($ext,['jpg','jpeg','png','webp','svg'])){
      FileHandler::ensureDir(__DIR__.'/../uploads/branding');
      // Delete old admin logo
      $oldAdminLogo=$db->query("SELECT setting_value FROM settings WHERE setting_key='admin_logo'")->fetchColumn();
      if($oldAdminLogo){$oldPath=__DIR__.'/../uploads/branding/'.basename($oldAdminLogo);if(file_exists($oldPath))unlink($oldPath);}
      $fname='admin_logo_'.time().'.'.$ext;
      FileHandler::saveUploadedFile($_FILES['admin_logo']['tmp_name'],__DIR__.'/../uploads/branding/'.$fname);
      $logoPath='/uploads/branding/'.$fname;
      $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('admin_logo',?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$logoPath,$logoPath]);
      $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('admin_logo_updated_at',?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([time(),time()]);
      auditLog('update_admin_logo','settings');setFlash('success','Admin dashboard logo updated.');
    }else setFlash('error','Admin logo must be JPG, PNG, WebP or SVG.');
  }
}

if($action==='admin_logo_delete'){
  if(!isSuperAdmin()){setFlash('error','Only Super Admin can remove the admin logo.');
  }else{
    $curLogo=$db->query("SELECT setting_value FROM settings WHERE setting_key='admin_logo'")->fetchColumn();
    if($curLogo){$fPath=__DIR__.'/../uploads/branding/'.basename($curLogo);if(file_exists($fPath))unlink($fPath);}
    $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('admin_logo',?) ON DUPLICATE KEY UPDATE setting_value=?")->execute(['','']);
    $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('admin_logo_updated_at',?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([time(),time()]);
    auditLog('delete_admin_logo','settings');setFlash('success','Admin logo removed. School logo will be used as fallback.');
  }
}

if($action==='social_links'){
  foreach(['social_facebook','social_twitter','social_instagram','social_youtube','social_linkedin'] as $k){
    $v=trim($_POST[$k]??'');$db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$k,$v,$v]);
  }auditLog('update_social','settings');setFlash('success','Social links updated.');
}

if($action==='about_content'){
  foreach(['about_history','about_vision','about_mission'] as $k){
    $v=trim($_POST[$k]??'');$db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$k,$v,$v]);
  }auditLog('update_about','settings');setFlash('success','About page content updated.');
}

if($action==='core_values'){
  for($i=1;$i<=4;$i++){
    foreach(['title','desc'] as $f){
      $k="core_value_{$i}_{$f}";$v=trim($_POST[$k]??'');
      $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$k,$v,$v]);
    }
  }auditLog('update_core_values','settings');setFlash('success','Core values updated.');
}

if($action==='sms_whatsapp'){
  foreach(['whatsapp_api_number','sms_gateway_key'] as $k){
    $v=trim($_POST[$k]??'');$db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$k,$v,$v]);
  }auditLog('update_sms_config','settings');setFlash('success','SMS/WhatsApp config updated.');
}


if($action==='smtp_settings'&&isSuperAdmin()){
  foreach(['smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from_name','smtp_encryption'] as $k){
    $v=trim($_POST[$k]??'');
    $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$k,$v,$v]);
  }
  auditLog('update_smtp','settings');setFlash('success','SMTP email settings updated.');
}

if($action==='test_email'&&isSuperAdmin()){
  require_once __DIR__.'/../config/mail.php';
  $customEmail=trim($_POST['test_email_recipient']??'');
  if($customEmail!==''){
    if(!filter_var($customEmail,FILTER_VALIDATE_EMAIL)){setFlash('error','Invalid email address provided.');header('Location:settings.php');exit;}
    $recipient=$customEmail;
  }else{
    $recipient=$_SESSION['user_email']??'';
  }
  if($recipient){
    $ok=sendMail($recipient,'Test Email from JNV School','<h2>SMTP Test</h2><p>If you received this email, your SMTP configuration is working correctly.</p><p>Sent at: '.date('d M Y, h:i:s A').'</p>');
    if($ok) setFlash('success','Test email sent to '.$recipient.'.');
    else setFlash('error','Failed to send test email. Check SMTP credentials.');
  }else setFlash('error','No email address provided.');
}

if($action==='feature_access'&&isSuperAdmin()){
  $features=['feature_admissions','feature_gallery','feature_events','feature_slider','feature_notifications','feature_reports','feature_audit_logs','feature_hr','feature_recruitment','feature_fee_structure','feature_certificates','feature_feature_cards','feature_core_team'];
  foreach($features as $k){
    $v=isset($_POST[$k])?'1':'0';
    $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$k,$v,$v]);
  }auditLog('update_feature_access','settings');setFlash('success','Feature access updated.');
}

if($action==='create_user'){$name=trim($_POST['user_name']??'');$email=trim($_POST['user_email']??'');$pass=$_POST['user_password']??'';$role=$_POST['user_role']??'teacher';if($name&&$email&&strlen($pass)>=6){try{$db->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)")->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT),$role]);auditLog('create_user','user',(int)$db->lastInsertId(),$email);setFlash('success',"User created.");}catch(PDOException $e){if($e->getCode()==23000)setFlash('error','Email exists.');else setFlash('error',$e->getMessage());}}else setFlash('error','All fields required, password min 6.');}

if($action==='edit_user'){
  $uid=(int)($_POST['edit_user_id']??0);$uname=trim($_POST['edit_user_name']??'');$urole=$_POST['edit_user_role']??'';$uactive=(int)($_POST['edit_user_active']??1);
  if($uid&&$uname&&$urole){$db->prepare("UPDATE users SET name=?,role=?,is_active=? WHERE id=?")->execute([$uname,$urole,$uactive,$uid]);auditLog('edit_user','user',$uid);setFlash('success','User updated.');}
}

if($action==='reset_user_pass'){
  $uid=(int)($_POST['reset_user_id']??0);
  if($uid){$db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash('Reset@123',PASSWORD_DEFAULT),$uid]);auditLog('reset_password','user',$uid);setFlash('success','Password reset to Reset@123.');}
}

if($action==='delete_user'&&isSuperAdmin()){$uid=(int)($_POST['delete_user_id']??0);if($uid&&$uid!==currentUserId()){$db->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);auditLog('delete_user','user',$uid);setFlash('success','Deleted.');}}

if($action==='clear_audit_logs'&&isSuperAdmin()){$db->exec("DELETE FROM audit_logs");auditLog('clear_audit_logs','system');setFlash('success','Audit logs cleared.');}

if($action==='test_db_connection'&&isSuperAdmin()){
  $connChecks=['server'=>false,'database'=>false,'privileges'=>false];$connDetails=[];
  try{
    $testDsn="mysql:host=".DB_HOST.";charset=".DB_CHARSET;
    $testPdo=new PDO($testDsn,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $connChecks['server']=true;$connDetails[]='Connected to MySQL server at '.DB_HOST;
    // Check database exists
    $testPdo->exec("USE `".DB_NAME."`");
    $connChecks['database']=true;$connDetails[]='Database "'.DB_NAME.'" accessible';
    // Check privileges
    $grants=$testPdo->query("SHOW GRANTS FOR CURRENT_USER()")->fetchAll(PDO::FETCH_COLUMN);
    $grantStr=implode(' ',$grants);
    if(stripos($grantStr,'ALL PRIVILEGES')!==false||stripos($grantStr,'ALL')!==false){
      $connChecks['privileges']=true;$connDetails[]='User has ALL PRIVILEGES';
    }elseif(preg_match('/CREATE|DROP|ALTER|INSERT|UPDATE|DELETE|SELECT/i',$grantStr)){
      $connChecks['privileges']=true;$connDetails[]='User has sufficient privileges';
    }else{
      $connDetails[]='User may lack CREATE/DROP privileges';
    }
    $_SESSION['db_conn_checks']=$connChecks;$_SESSION['db_conn_details']=$connDetails;
    if($connChecks['server']&&$connChecks['database']&&$connChecks['privileges']){
      setFlash('success','Database connection verified successfully!');
    }else{
      setFlash('warning','Connection partial: '.implode('; ',$connDetails));
    }
  }catch(PDOException $e){
    $connDetails[]='Error: '.$e->getMessage();
    $_SESSION['db_conn_checks']=$connChecks;$_SESSION['db_conn_details']=$connDetails;
    setFlash('error','Database connection failed: '.$e->getMessage());
  }
}

if($action==='import_schema'&&isSuperAdmin()){
  $confirmWord=trim($_POST['confirm_word']??'');
  if($confirmWord!=='CONFIRM'){setFlash('error','You must type CONFIRM to proceed.');}
  else{
    $schemaFile=__DIR__.'/../schema.sql';
    if(!file_exists($schemaFile)){setFlash('error','schema.sql not found on server.');}
    else{
      $sql=file_get_contents($schemaFile);
      // Remove comments and empty lines
      $sql=preg_replace('/--.*$/m','',$sql);
      $sql=preg_replace('/\/\*.*?\*\//s','',$sql);
      $statements=array_filter(array_map('trim',explode(';',$sql)));
      $executed=0;$failed='';
      try{
        foreach($statements as $stmt){
          if(empty($stmt)||$stmt==='COMMIT') continue;
          $db->exec($stmt);
          $executed++;
        }
        $db->exec('COMMIT');
        auditLog('import_schema','system',null,"Executed $executed SQL statements");
        setFlash('success',"Schema imported successfully. $executed statements executed.");
      }catch(PDOException $e){
        setFlash('error','Schema import failed: '.$e->getMessage().'<br><small>Failed statement: '.htmlspecialchars(substr($stmt,0,200)).'</small>');
      }
    }
  }
}

if($action==='certificate_settings'){
  foreach(['home_certificates_show','certificates_page_enabled'] as $k){
    $v=isset($_POST[$k])?'1':'0';
    $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$k,$v,$v]);
  }
  $maxVal=max(1,min(12,(int)($_POST['home_certificates_max']??6)));
  $db->prepare("INSERT INTO settings (setting_key,setting_value) VALUES ('home_certificates_max',?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$maxVal,$maxVal]);
  auditLog('update_certificate_settings','settings');setFlash('success','Certificate settings updated.');
}

header('Location: /admin/settings.php');exit;}

$settings=[];$stmt=$db->query("SELECT setting_key,setting_value FROM settings");while($r=$stmt->fetch())$settings[$r['setting_key']]=$r['setting_value'];
$users=$db->query("SELECT id,name,email,role,is_active,last_login FROM users ORDER BY created_at DESC")->fetchAll();
try{$totalStudents=$db->query("SELECT COUNT(*) FROM students")->fetchColumn();}catch(Exception $e){$totalStudents=0;}
try{$activeStudents=$db->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn();}catch(Exception $e){$activeStudents=0;}
try{$totalTeachers=$db->query("SELECT COUNT(*) FROM teachers")->fetchColumn();}catch(Exception $e){$totalTeachers=0;}
try{$activeTeachers=$db->query("SELECT COUNT(*) FROM teachers WHERE is_active=1")->fetchColumn();}catch(Exception $e){$activeTeachers=0;}
$totalUsers=count($users);
try{$totalNotifications=$db->query("SELECT COUNT(*) FROM notifications")->fetchColumn();}catch(Exception $e){$totalNotifications=0;}
try{$totalEvents=$db->query("SELECT COUNT(*) FROM events")->fetchColumn();}catch(Exception $e){$totalEvents=0;}
try{$mysqlVersion=$db->query("SELECT VERSION()")->fetchColumn();}catch(Exception $e){$mysqlVersion='N/A';}
try{$dbTablesCount=$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchColumn();}catch(Exception $e){$dbTablesCount='N/A';}
try{$dbSize=$db->query("SELECT ROUND(SUM(data_length + index_length)/1024/1024, 2) FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchColumn();}catch(Exception $e){$dbSize='N/A';}

// Expected tables for Database Setup card
$expectedTables=['users','students','teachers','admissions','notifications','notification_reads','notification_versions','notification_attachments','gallery_items','gallery_categories','gallery_albums','events','attendance','exam_results','audit_logs','settings','home_slider','site_quotes','leadership_profiles','nav_menu_items','certificates','feature_cards','fee_structures','fee_components','popup_ads','popup_analytics','enquiries','core_team'];
$existingTables=[];
try{$tblStmt=$db->query("SHOW TABLES");while($t=$tblStmt->fetch(PDO::FETCH_NUM))$existingTables[]=$t[0];}catch(Exception $e){}
$missingTables=array_diff($expectedTables,$existingTables);
$tableCount=count(array_intersect($expectedTables,$existingTables));
$totalExpected=count($expectedTables);

// Database Explorer data (Super Admin only)
$dbExplorerTables=[];
$dbExplorerColumns=[];
if(isSuperAdmin()){
  try{
    $exStmt=$db->query("SELECT TABLE_NAME, ENGINE, TABLE_ROWS, ROUND((DATA_LENGTH + INDEX_LENGTH)/1024, 2) as size_kb FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");
    $dbExplorerTables=$exStmt->fetchAll(PDO::FETCH_ASSOC);
  }catch(Exception $e){$dbExplorerTables=[];}
  try{
    $colStmt=$db->query("SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, COLUMN_DEFAULT, EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, ORDINAL_POSITION");
    while($col=$colStmt->fetch(PDO::FETCH_ASSOC)){
      $dbExplorerColumns[$col['TABLE_NAME']][]=$col;
    }
  }catch(Exception $e){$dbExplorerColumns=[];}
}

require_once __DIR__.'/../includes/header.php';$s=$settings;?>

<!-- Tab Navigation -->
<ul class="nav nav-pills mb-4 flex-nowrap overflow-auto" id="settingsTabs" role="tablist" style="gap:.5rem;">
  <li class="nav-item" role="presentation">
    <button class="nav-link active d-flex align-items-center gap-2 rounded-pill px-3 py-2" id="general-tab" data-bs-toggle="pill" data-bs-target="#tab-general" type="button" role="tab">
      <i class="bi bi-building"></i><span class="d-none d-md-inline">General</span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link d-flex align-items-center gap-2 rounded-pill px-3 py-2" id="appearance-tab" data-bs-toggle="pill" data-bs-target="#tab-appearance" type="button" role="tab">
      <i class="bi bi-palette"></i><span class="d-none d-md-inline">Appearance</span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link d-flex align-items-center gap-2 rounded-pill px-3 py-2" id="content-tab" data-bs-toggle="pill" data-bs-target="#tab-content" type="button" role="tab">
      <i class="bi bi-file-text"></i><span class="d-none d-md-inline">Content</span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link d-flex align-items-center gap-2 rounded-pill px-3 py-2" id="social-tab" data-bs-toggle="pill" data-bs-target="#tab-social" type="button" role="tab">
      <i class="bi bi-share"></i><span class="d-none d-md-inline">Social & SMS</span>
    </button>
  </li>
  <?php if(isSuperAdmin()):?>
  <li class="nav-item" role="presentation">
    <button class="nav-link d-flex align-items-center gap-2 rounded-pill px-3 py-2" id="email-tab" data-bs-toggle="pill" data-bs-target="#tab-email" type="button" role="tab">
      <i class="bi bi-envelope"></i><span class="d-none d-md-inline">Email</span>
    </button>
  </li>
  <?php endif;?>
  <li class="nav-item" role="presentation">
    <button class="nav-link d-flex align-items-center gap-2 rounded-pill px-3 py-2" id="users-tab" data-bs-toggle="pill" data-bs-target="#tab-users" type="button" role="tab">
      <i class="bi bi-people"></i><span class="d-none d-md-inline">Users</span>
    </button>
  </li>
  <?php if(isSuperAdmin()):?>
  <li class="nav-item" role="presentation">
    <button class="nav-link d-flex align-items-center gap-2 rounded-pill px-3 py-2" id="access-tab" data-bs-toggle="pill" data-bs-target="#tab-access" type="button" role="tab">
      <i class="bi bi-shield-lock"></i><span class="d-none d-md-inline">Access Control</span>
    </button>
  </li>
  <?php endif;?>
  <li class="nav-item" role="presentation">
    <button class="nav-link d-flex align-items-center gap-2 rounded-pill px-3 py-2" id="system-tab" data-bs-toggle="pill" data-bs-target="#tab-system" type="button" role="tab">
      <i class="bi bi-cpu"></i><span class="d-none d-md-inline">System</span>
    </button>
  </li>
</ul>

<!-- Tab Content -->
<div class="tab-content" id="settingsTabContent">

<!-- ========== GENERAL TAB ========== -->
<div class="tab-pane fade show active" id="tab-general" role="tabpanel">
  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card border-0 rounded-3"><div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0"><i class="bi bi-building me-2"></i>School Information</h6></div><div class="card-body">
        <form method="POST"><?=csrfField()?><input type="hidden" name="form_action" value="settings">
        <input type="hidden" name="primary_color" value="<?=e($s['primary_color']??'#1e40af')?>">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">School Name</label><input type="text" name="school_name" class="form-control" value="<?=e($s['school_name']??'')?>"></div>
          <div class="col-md-6"><label class="form-label">Short Name</label><input type="text" name="school_short_name" class="form-control" value="<?=e($s['school_short_name']??'')?>"></div>
          <div class="col-12"><label class="form-label">Tagline</label><input type="text" name="school_tagline" class="form-control" value="<?=e($s['school_tagline']??'')?>"></div>
          <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="school_email" class="form-control" value="<?=e($s['school_email']??'')?>"></div>
          <div class="col-md-6"><label class="form-label">Phone</label><input type="tel" name="school_phone" class="form-control" value="<?=e($s['school_phone']??'')?>"></div>
          <div class="col-12"><label class="form-label">Address</label><textarea name="school_address" class="form-control" rows="2"><?=e($s['school_address']??'')?></textarea></div>
          <div class="col-md-6"><label class="form-label">Academic Year</label><input type="text" name="academic_year" class="form-control" value="<?=e($s['academic_year']??'')?>"></div>
          <div class="col-md-6"><label class="form-label">Admissions</label><select name="admission_open" class="form-select"><option value="1" <?=($s['admission_open']??'1')==='1'?'selected':''?>>Open</option><option value="0" <?=($s['admission_open']??'1')==='0'?'selected':''?>>Closed</option></select></div>
          <div class="col-12">
            <div class="card border-0 rounded-3 mb-3" style="background:<?=($s['maintenance_mode']??'0')==='1'?'#fef2f2':'#f0fdf4'?>;border:1px solid <?=($s['maintenance_mode']??'0')==='1'?'#fecaca':'#bbf7d0'?> !important;">
              <div class="card-body py-3 d-flex align-items-center justify-content-between">
                <div>
                  <h6 class="fw-semibold mb-1"><i class="bi bi-tools me-2"></i>Maintenance Mode</h6>
                  <small class="text-muted">When enabled, public visitors will see a maintenance page. Admins remain unaffected.</small>
                </div>
                <div class="form-check form-switch ms-3">
                  <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintenanceMode" value="1" <?=($s['maintenance_mode']??'0')==='1'?'checked':''?> style="width:3rem;height:1.5rem;cursor:pointer;">
                </div>
              </div>
            </div>
          </div>
          <div class="col-12"><button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Settings</button></div>
        </div></form>
      </div></div>
    </div>
    <div class="col-lg-4">
      <?php
        // Build logo URL with cache-busting
        $_logoVer = $s['logo_updated_at'] ?? '0';
        $_logoFile = $s['school_logo'] ?? '';
        $_logoUrl = $_logoFile ? ((strpos($_logoFile, '/uploads/') === 0 ? $_logoFile : '/uploads/branding/' . $_logoFile) . '?v=' . $_logoVer) : '';
        // Also check old path for backward compat
        if ($_logoFile && !file_exists(__DIR__ . '/../uploads/branding/' . $_logoFile) && file_exists(__DIR__ . '/../uploads/logo/' . $_logoFile)) {
            $_logoUrl = '/uploads/logo/' . $_logoFile . '?v=' . $_logoVer;
        }
        $_favFile = $s['school_favicon'] ?? '';
        $_favVer = $s['favicon_updated_at'] ?? '0';
        $_favUrl = $_favFile ? ((strpos($_favFile, '/uploads/') === 0 ? $_favFile : '/uploads/branding/' . $_favFile) . '?v=' . $_favVer) : '';
        if ($_favFile && !file_exists(__DIR__ . '/../uploads/branding/' . $_favFile) && file_exists(__DIR__ . '/../uploads/logo/' . $_favFile)) {
            $_favUrl = '/uploads/logo/' . $_favFile . '?v=' . $_favVer;
        }
      ?>
      <div class="card border-0 rounded-3 mb-3"><div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0"><i class="bi bi-image me-2"></i>School Logo</h6></div><div class="card-body">
        <?php if($_logoUrl):?>
        <div class="mb-3 text-center p-3 rounded-3" style="background:#f1f5f9;">
          <img src="<?=e($_logoUrl)?>" alt="School Logo" style="max-width:200px;max-height:200px;object-fit:contain;" class="rounded">
        </div>
        <!-- Logo Preview at All Sizes -->
        <div class="mb-3 p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0;">
          <small class="fw-semibold text-muted d-block mb-2">Preview at different sizes:</small>
          <div class="d-flex align-items-end gap-4 justify-content-center flex-wrap">
            <div class="text-center">
              <div class="d-inline-flex align-items-center justify-content-center rounded-2" style="background:#0f172a;padding:6px;">
                <img src="<?=e($_logoUrl)?>" alt="" style="height:42px;width:auto;object-fit:contain;border-radius:6px;">
              </div>
              <small class="d-block text-muted mt-1" style="font-size:.65rem">Navbar (42px h)</small>
            </div>
            <div class="text-center">
              <div class="d-inline-flex align-items-center justify-content-center rounded-2" style="background:#1e293b;padding:4px;">
                <img src="<?=e($_logoUrl)?>" alt="" style="width:64px;height:64px;object-fit:contain;border-radius:6px;">
              </div>
              <small class="d-block text-muted mt-1" style="font-size:.65rem">Sidebar (64px)</small>
            </div>
            <div class="text-center">
              <div class="d-inline-flex align-items-center justify-content-center rounded-2" style="width:32px;height:32px;background:#fff;border:1px solid #e2e8f0;">
                <img src="<?=e($_favUrl ?: $_logoUrl)?>" alt="" style="width:28px;height:28px;object-fit:contain;">
              </div>
              <small class="d-block text-muted mt-1" style="font-size:.65rem">Favicon (32px)</small>
            </div>
            <div class="text-center">
              <div class="d-inline-flex align-items-center justify-content-center rounded-2" style="background:#1a1a2e;padding:4px;">
                <img src="<?=e($_logoUrl)?>" alt="" style="max-width:120px;height:auto;object-fit:contain;border-radius:8px;">
              </div>
              <small class="d-block text-muted mt-1" style="font-size:.65rem">Footer (120px)</small>
            </div>
          </div>
        </div>
        <?php if($_logoVer && $_logoVer !== '0'): ?>
        <small class="text-muted d-block mb-2"><i class="bi bi-clock me-1"></i>Last updated: <?= date('d M Y, h:i A', (int)$_logoVer) ?></small>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-secondary btn-sm mb-2 w-100" onclick="location.reload(true);"><i class="bi bi-arrow-clockwise me-1"></i>Clear Cache & Refresh Logo</button>
        <?php endif;?>
        <form method="POST" enctype="multipart/form-data" id="logoUploadForm"><?=csrfField()?><input type="hidden" name="form_action" value="logo_upload">
        <input type="file" name="school_logo" id="logoFileInput" class="form-control form-control-sm mb-2" accept=".jpg,.jpeg,.png,.webp,.svg">
        <small class="text-muted d-block mb-2" style="font-size:.7rem">Recommended: Wide/rectangular format supported. Min 160px wide. JPG, PNG, WebP, SVG.</small>
        <!-- Crop Preview -->
        <div id="logoCropArea" class="mb-2" style="display:none;">
          <small class="fw-semibold text-muted d-block mb-1">Crop Preview:</small>
          <div class="position-relative d-inline-block">
            <canvas id="logoCropCanvas" style="max-width:100%;border-radius:8px;border:2px solid #e2e8f0;cursor:crosshair;"></canvas>
          </div>
          <div class="d-flex gap-2 mt-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetCrop()"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</button>
          </div>
        </div>
        <button type="submit" class="btn btn-success btn-sm w-100"><i class="bi bi-upload me-1"></i>Upload Logo</button>
        </form>
      </div></div>

      <script>
      (function(){
        const fileInput = document.getElementById('logoFileInput');
        const cropArea = document.getElementById('logoCropArea');
        const canvas = document.getElementById('logoCropCanvas');
        const ctx = canvas.getContext('2d');
        let img = null, cropBox = {x:0,y:0,w:0,h:0}, dragging = false, dragStart={x:0,y:0};

        fileInput.addEventListener('change', function(e){
          const file = e.target.files[0];
          if(!file) { cropArea.style.display='none'; return; }
          const reader = new FileReader();
          reader.onload = function(ev){
            img = new Image();
            img.onload = function(){
              const maxW = 300;
              const scale = Math.min(maxW / img.width, maxW / img.height, 1);
              canvas.width = img.width * scale;
              canvas.height = img.height * scale;
              cropBox.w = canvas.width;
              cropBox.h = canvas.height;
              cropBox.x = 0;
              cropBox.y = 0;
              drawCrop();
              cropArea.style.display = 'block';
            };
            img.src = ev.target.result;
          };
          reader.readAsDataURL(file);
        });

        function drawCrop(){
          if(!img) return;
          ctx.clearRect(0,0,canvas.width,canvas.height);
          ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
          // Dim outside crop
          ctx.fillStyle = 'rgba(0,0,0,0.5)';
          ctx.fillRect(0, 0, canvas.width, cropBox.y);
          ctx.fillRect(0, cropBox.y + cropBox.h, canvas.width, canvas.height - cropBox.y - cropBox.h);
          ctx.fillRect(0, cropBox.y, cropBox.x, cropBox.h);
          ctx.fillRect(cropBox.x + cropBox.w, cropBox.y, canvas.width - cropBox.x - cropBox.w, cropBox.h);
          // Crop border
          ctx.strokeStyle = '#3b82f6'; ctx.lineWidth = 2;
          ctx.strokeRect(cropBox.x, cropBox.y, cropBox.w, cropBox.h);
          // Corner handles
          const hs = 6;
          ctx.fillStyle = '#3b82f6';
          [[cropBox.x,cropBox.y],[cropBox.x+cropBox.w,cropBox.y],[cropBox.x,cropBox.y+cropBox.h],[cropBox.x+cropBox.w,cropBox.y+cropBox.h]].forEach(([cx,cy])=>{
            ctx.fillRect(cx-hs/2, cy-hs/2, hs, hs);
          });
        }

        canvas.addEventListener('mousedown', function(e){
          const rect = canvas.getBoundingClientRect();
          const mx = e.clientX - rect.left, my = e.clientY - rect.top;
          if(mx >= cropBox.x && mx <= cropBox.x+cropBox.w && my >= cropBox.y && my <= cropBox.y+cropBox.h){
            dragging = true; dragStart = {x: mx - cropBox.x, y: my - cropBox.y};
          }
        });
        canvas.addEventListener('mousemove', function(e){
          if(!dragging) return;
          const rect = canvas.getBoundingClientRect();
          cropBox.x = Math.max(0, Math.min(canvas.width - cropBox.w, e.clientX - rect.left - dragStart.x));
          cropBox.y = Math.max(0, Math.min(canvas.height - cropBox.h, e.clientY - rect.top - dragStart.y));
          drawCrop();
        });
        canvas.addEventListener('mouseup', ()=>{ dragging=false; });
        canvas.addEventListener('mouseleave', ()=>{ dragging=false; });

        // Touch support
        canvas.addEventListener('touchstart', function(e){
          e.preventDefault(); const t=e.touches[0]; const rect=canvas.getBoundingClientRect();
          const mx=t.clientX-rect.left, my=t.clientY-rect.top;
          if(mx>=cropBox.x&&mx<=cropBox.x+cropBox.w&&my>=cropBox.y&&my<=cropBox.y+cropBox.h){
            dragging=true; dragStart={x:mx-cropBox.x,y:my-cropBox.y};
          }
        },{passive:false});
        canvas.addEventListener('touchmove', function(e){
          if(!dragging) return; e.preventDefault(); const t=e.touches[0]; const rect=canvas.getBoundingClientRect();
          cropBox.x=Math.max(0,Math.min(canvas.width-cropBox.w,t.clientX-rect.left-dragStart.x));
          cropBox.y=Math.max(0,Math.min(canvas.height-cropBox.h,t.clientY-rect.top-dragStart.y));
          drawCrop();
        },{passive:false});
        canvas.addEventListener('touchend',()=>{dragging=false;});

        window.resetCrop = function(){
          if(!img) return;
          cropBox.w = canvas.width;
          cropBox.h = canvas.height;
          cropBox.x = 0;
          cropBox.y = 0;
          drawCrop();
        };

        // On form submit, crop and replace file
        document.getElementById('logoUploadForm').addEventListener('submit', function(e){
          if(!img || !cropArea.style.display || cropArea.style.display==='none') return;
          e.preventDefault();
          const scaleX = img.width / canvas.width, scaleY = img.height / canvas.height;
          const cropW = cropBox.w * scaleX, cropH = cropBox.h * scaleY;
          const maxDim = 400;
          const outScale = Math.min(maxDim / cropW, maxDim / cropH, 1);
          const outCanvas = document.createElement('canvas');
          outCanvas.width = Math.round(cropW * outScale);
          outCanvas.height = Math.round(cropH * outScale);
          const outCtx = outCanvas.getContext('2d');
          outCtx.drawImage(img, cropBox.x*scaleX, cropBox.y*scaleY, cropW, cropH, 0, 0, outCanvas.width, outCanvas.height);
          outCanvas.toBlob(function(blob){
            const fd = new FormData(e.target);
            fd.delete('school_logo');
            fd.append('school_logo', blob, 'cropped_logo.png');
            fetch('/admin/settings.php', {method:'POST', body: fd}).then(()=>{ window.location.href='/admin/settings.php'; });
          }, 'image/png');
        });
      })();
      </script>

      <div class="card border-0 rounded-3"><div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0"><i class="bi bi-window-desktop me-2"></i>Favicon</h6></div><div class="card-body">
        <form method="POST" enctype="multipart/form-data"><?=csrfField()?><input type="hidden" name="form_action" value="favicon_upload">
        <?php if(!empty($s['school_favicon'])):?><div class="mb-3 text-center"><img src="/uploads/logo/<?=e($s['school_favicon'])?>" alt="Favicon" style="max-height:48px" class="rounded"></div><?php endif;?>
        <input type="file" name="school_favicon" class="form-control form-control-sm mb-2" accept=".ico,.png,.svg,.jpg,.jpeg">
        <small class="text-muted d-block mb-2" style="font-size:.7rem">Recommended: 32×32 or 64×64px. ICO, PNG, SVG, JPG.</small>
        <button class="btn btn-success btn-sm w-100"><i class="bi bi-upload me-1"></i>Upload Favicon</button>
        </form>
      </div></div>

      <!-- Admin Dashboard Logo -->
      <div class="card border-0 rounded-3 mt-3">
        <div class="card-header bg-white border-0">
          <h6 class="fw-semibold mb-0"><i class="bi bi-layout-sidebar-inset me-2"></i>Admin Dashboard Logo</h6>
          <small class="text-muted" style="font-size:.72rem">Appears in the admin sidebar. If not set, the School Logo is used.</small>
        </div>
        <div class="card-body">
          <?php
          $adminLogoVal = $s['admin_logo'] ?? '';
          $adminLogoVer = $s['admin_logo_updated_at'] ?? time();
          ?>
          <?php if($adminLogoVal): ?>
          <div class="mb-3 d-flex align-items-center gap-3">
            <div style="width:56px;height:56px;background:#1e293b;border-radius:10px;display:flex;align-items:center;justify-content:center;padding:6px;">
              <img src="<?=e($adminLogoVal)?>?v=<?=e($adminLogoVer)?>" alt="Admin Logo" style="max-width:100%;max-height:100%;object-fit:contain;">
            </div>
            <div style="width:36px;height:36px;background:#1e293b;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:4px;">
              <img src="<?=e($adminLogoVal)?>?v=<?=e($adminLogoVer)?>" alt="Admin Logo" style="max-width:100%;max-height:100%;object-fit:contain;">
            </div>
            <form method="POST" class="ms-auto" onsubmit="return confirm('Remove admin logo?')">
              <?=csrfField()?><input type="hidden" name="form_action" value="admin_logo_delete">
              <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Remove</button>
            </form>
          </div>
          <?php else: ?>
          <div class="mb-3 d-flex align-items-center gap-3">
            <div style="width:56px;height:56px;background:#e2e8f0;border-radius:10px;display:flex;align-items:center;justify-content:center;">
              <i class="bi bi-image text-muted" style="font-size:1.3rem"></i>
            </div>
            <small class="text-muted">No admin logo set — using school logo as fallback.</small>
          </div>
          <?php endif; ?>
          <form method="POST" enctype="multipart/form-data">
            <?=csrfField()?><input type="hidden" name="form_action" value="admin_logo_upload">
            <input type="file" name="admin_logo" class="form-control form-control-sm mb-2" accept=".jpg,.jpeg,.png,.svg,.webp">
            <small class="text-muted d-block mb-2" style="font-size:.7rem">Square format recommended, min 100×100px. JPG, PNG, SVG, WEBP. Max 5MB.</small>
            <button class="btn btn-success btn-sm w-100"><i class="bi bi-cloud-upload me-1"></i>Upload Admin Logo</button>
          </form>
        </div>
      </div>

    </div>
  </div>

  <!-- Brand Colors Card -->
  <div class="row g-3 mt-2">
    <div class="col-12">
      <div class="card border-0 rounded-3"><div class="card-header bg-white border-0 d-flex align-items-center justify-content-between">
        <h6 class="fw-semibold mb-0"><i class="bi bi-droplet-fill me-2"></i>Brand Colors <span class="badge <?=($s['brand_colors_auto']??'1')==='1'?'bg-success':'bg-warning text-dark'?> rounded-pill ms-2" style="font-size:.65rem"><?=($s['brand_colors_auto']??'1')==='1'?'Auto':'Custom'?></span></h6>
        <?php if(isSuperAdmin()):?>
        <form method="POST" class="d-inline"><?=csrfField()?><input type="hidden" name="form_action" value="reextract_colors">
          <button class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-repeat me-1"></i>Re-extract from Logo</button>
        </form>
        <?php endif;?>
      </div><div class="card-body">
        <p class="text-muted mb-3" style="font-size:.85rem">These colors are auto-extracted from your school logo and applied across the admin dashboard (sidebar, buttons, badges, highlights). Super Admins can manually override them.</p>

        <!-- Color Swatches -->
        <div class="d-flex gap-3 mb-4 flex-wrap">
          <?php
          $brandColors = [
            ['key'=>'brand_primary','label'=>'Primary','default'=>'#1e40af'],
            ['key'=>'brand_secondary','label'=>'Secondary','default'=>'#6366f1'],
            ['key'=>'brand_accent','label'=>'Accent','default'=>'#f59e0b'],
          ];
          foreach($brandColors as $bc):
            $colorVal = $s[$bc['key']] ?? $bc['default'];
          ?>
          <div class="text-center">
            <div style="width:64px;height:64px;border-radius:12px;background:<?=e($colorVal)?>;border:3px solid var(--border-color);box-shadow:0 2px 8px rgba(0,0,0,0.15);"></div>
            <small class="d-block mt-1 fw-semibold" style="font-size:.75rem"><?=$bc['label']?></small>
            <code style="font-size:.65rem"><?=e($colorVal)?></code>
          </div>
          <?php endforeach;?>
        </div>

        <!-- Preview of brand colors on UI elements -->
        <div class="p-3 rounded-3 mb-4" style="background:var(--bg-body);border:1px solid var(--border-color);">
          <small class="fw-semibold text-muted d-block mb-2">Preview on UI elements:</small>
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <button class="btn btn-sm text-white" style="background:<?=e($s['brand_primary']??'#1e40af')?>">Primary Button</button>
            <span class="badge rounded-pill text-white" style="background:<?=e($s['brand_secondary']??'#6366f1')?>">Badge</span>
            <span class="badge rounded-pill text-white" style="background:<?=e($s['brand_accent']??'#f59e0b')?>">Accent</span>
            <div style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,<?=e($s['brand_primary']??'#1e40af')?>,<?=e($s['brand_secondary']??'#6366f1')?>);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.7rem">AB</div>
            <div style="width:10px;height:10px;border-radius:50%;background:<?=e($s['brand_accent']??'#f59e0b')?>"></div>
          </div>
        </div>

        <?php if(isSuperAdmin()):?>
        <!-- Manual Override -->
        <form method="POST"><?=csrfField()?><input type="hidden" name="form_action" value="brand_colors_manual">
          <h6 class="fw-semibold mb-2" style="font-size:.85rem"><i class="bi bi-sliders me-1"></i>Manual Override</h6>
          <div class="row g-2 mb-3">
            <div class="col-md-4">
              <label class="form-label" style="font-size:.8rem">Primary</label>
              <div class="d-flex gap-2 align-items-center">
                <input type="color" name="brand_primary" class="form-control form-control-color" value="<?=e($s['brand_primary']??'#1e40af')?>" style="width:48px;height:38px;">
                <input type="text" class="form-control form-control-sm" value="<?=e($s['brand_primary']??'#1e40af')?>" readonly style="font-size:.75rem;font-family:monospace;">
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label" style="font-size:.8rem">Secondary</label>
              <div class="d-flex gap-2 align-items-center">
                <input type="color" name="brand_secondary" class="form-control form-control-color" value="<?=e($s['brand_secondary']??'#6366f1')?>" style="width:48px;height:38px;">
                <input type="text" class="form-control form-control-sm" value="<?=e($s['brand_secondary']??'#6366f1')?>" readonly style="font-size:.75rem;font-family:monospace;">
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label" style="font-size:.8rem">Accent</label>
              <div class="d-flex gap-2 align-items-center">
                <input type="color" name="brand_accent" class="form-control form-control-color" value="<?=e($s['brand_accent']??'#f59e0b')?>" style="width:48px;height:38px;">
                <input type="text" class="form-control form-control-sm" value="<?=e($s['brand_accent']??'#f59e0b')?>" readonly style="font-size:.75rem;font-family:monospace;">
              </div>
            </div>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save Custom Colors</button>
          </div>
        </form>
        <script>
        // Sync color pickers with text fields
        document.querySelectorAll('input[type="color"][name^="brand_"]').forEach(function(picker){
          picker.addEventListener('input', function(){
            this.closest('.d-flex').querySelector('input[type="text"]').value = this.value;
          });
        });
        </script>
        <?php endif;?>
      </div></div>
    </div>
  </div>
</div>

<!-- ========== APPEARANCE TAB ========== -->
<div class="tab-pane fade" id="tab-appearance" role="tabpanel">
  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card border-0 rounded-3"><div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0"><i class="bi bi-palette me-2"></i>Theme Color</h6></div><div class="card-body">
        <form method="POST" id="colorForm"><?=csrfField()?><input type="hidden" name="form_action" value="settings">
        <!-- Hidden fields to preserve other settings -->
        <input type="hidden" name="school_name" value="<?=e($s['school_name']??'')?>">
        <input type="hidden" name="school_short_name" value="<?=e($s['school_short_name']??'')?>">
        <input type="hidden" name="school_tagline" value="<?=e($s['school_tagline']??'')?>">
        <input type="hidden" name="school_email" value="<?=e($s['school_email']??'')?>">
        <input type="hidden" name="school_phone" value="<?=e($s['school_phone']??'')?>">
        <input type="hidden" name="school_address" value="<?=e($s['school_address']??'')?>">
        <input type="hidden" name="academic_year" value="<?=e($s['academic_year']??'')?>">
        <input type="hidden" name="admission_open" value="<?=e($s['admission_open']??'1')?>">

        <p class="text-muted mb-3" style="font-size:.85rem">Choose a primary color for your school's website theme. This color is applied to the navbar, buttons, links, and accents across all pages.</p>

        <div class="d-flex align-items-center gap-3 mb-4">
          <input type="color" name="primary_color" id="primaryColorPicker" class="form-control form-control-color border-2" value="<?=e($s['primary_color']??'#1e40af')?>" style="width:64px;height:64px;cursor:pointer;">
          <div>
            <div class="fw-semibold" style="font-size:.9rem">Selected Color</div>
            <code id="colorHexDisplay" class="text-muted"><?=e($s['primary_color']??'#1e40af')?></code>
          </div>
        </div>

        <label class="form-label fw-semibold mb-2">Preset Swatches</label>
        <div class="d-flex flex-wrap gap-2 mb-4">
          <?php
          $presets = [
            'Navy' => '#1e40af', 'Emerald' => '#059669', 'Purple' => '#7c3aed', 'Rose' => '#e11d48',
            'Amber' => '#d97706', 'Slate' => '#334155', 'Teal' => '#0d9488', 'Indigo' => '#4f46e5'
          ];
          foreach ($presets as $label => $hex): ?>
          <button type="button" class="btn p-0 border-2 rounded-3 theme-swatch d-flex align-items-center justify-content-center" style="width:48px;height:48px;background:<?=$hex?>;min-width:48px;" onclick="selectColor('<?=$hex?>')" title="<?=$label?>">
            <span class="text-white fw-bold" style="font-size:.55rem;text-shadow:0 1px 2px rgba(0,0,0,.5)"><?=$label?></span>
          </button>
          <?php endforeach; ?>
        </div>

        <button class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Theme Color</button>
        </form>
      </div></div>
    </div>

    <!-- Live Preview -->
    <div class="col-lg-6">
      <div class="card border-0 rounded-3"><div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0"><i class="bi bi-eye me-2"></i>Live Preview</h6></div><div class="card-body">
        <p class="text-muted mb-3" style="font-size:.8rem">See how the selected color looks on your website elements in real-time.</p>

        <div id="colorPreview" class="border rounded-3 overflow-hidden">
          <!-- Preview Navbar -->
          <div class="preview-navbar d-flex align-items-center px-3 py-2" style="background:<?=e($s['primary_color']??'#1e40af')?>">
            <i class="bi bi-mortarboard-fill text-white me-2"></i>
            <span class="text-white fw-semibold" style="font-size:.85rem"><?=e($s['school_name']??'School Name')?></span>
            <div class="ms-auto d-flex gap-2">
              <span class="text-white-50" style="font-size:.75rem">Home</span>
              <span class="text-white-50" style="font-size:.75rem">About</span>
              <span class="text-white-50" style="font-size:.75rem">Contact</span>
  </div>

  <?php if(isSuperAdmin()):?>
  <!-- Page Colors Section -->
  <div class="row g-3 mt-2">
    <div class="col-12">
      <div class="card border-0 rounded-3">
        <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
          <h6 class="fw-semibold mb-0"><i class="bi bi-brush me-2"></i>Page Colors — Public Site & Admin Backend</h6>
          <form method="POST" class="d-inline"><?=csrfField()?><input type="hidden" name="form_action" value="reset_page_colors">
            <button class="btn btn-outline-secondary btn-sm" onclick="return confirm('Reset all page colors to defaults?')"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset Defaults</button>
          </form>
        </div>
        <div class="card-body">

          <!-- Preset Schemes -->
          <h6 class="fw-semibold mb-2" style="font-size:.85rem"><i class="bi bi-lightning me-1"></i>Quick Apply — Preset Schemes</h6>
          <p class="text-muted mb-3" style="font-size:.8rem">Click a scheme to fill all color fields. Fine-tune individual colors, then save.</p>
          <div class="d-flex flex-wrap gap-2 mb-4">
            <?php
            $schemes = [
              ['Classic Navy','#0f172a','#ffffff','#060a12','#1a1a2e','#0f2557','#1a3a7a','#faf8f5','#1a1a1a','#f4f2ee','#111111'],
              ['Ocean Blue','#1e3a5f','#ffffff','#0c2340','#0c2340','#1a4a7a','#2563eb','#f0f7ff','#1a2332','#f0f4f8','#0f1a24'],
              ['Forest Green','#1a3c34','#ffffff','#0d2818','#0d2818','#14532d','#166534','#f0fdf4','#1a2e1a','#f0f8f0','#0f1a0f'],
              ['Royal Purple','#2d1b69','#ffffff','#1a1040','#1a1040','#3b1f8e','#6d28d9','#faf5ff','#201530','#f5f0fa','#130f1a'],
              ['Warm Earth','#3d2b1f','#ffffff','#2c1810','#2c1810','#5c3d2e','#92400e','#fef7ed','#2a1f18','#faf5f0','#1a1410'],
              ['Minimal Light','#ffffff','#1a1a1a','#f8fafc','#f8fafc','#1e40af','#3b82f6','#ffffff','#1a1a1a','#f9fafb','#111111'],
            ];
            $schemeNames = ['Classic Navy','Ocean Blue','Forest Green','Royal Purple','Warm Earth','Minimal Light'];
            $schemeColors = ['#0f172a','#1e3a5f','#1a3c34','#2d1b69','#3d2b1f','#ffffff'];
            foreach($schemes as $i => $sc): ?>
            <button type="button" class="btn p-0 border-2 rounded-3 d-flex flex-column align-items-center justify-content-center page-scheme-btn"
              style="width:90px;height:60px;background:<?=$sc[1]?>;border:2px solid <?=$sc[4]?>;"
              data-scheme='<?=json_encode($sc)?>'
              title="<?=$schemeNames[$i]?>">
              <div style="width:70px;height:20px;border-radius:4px;background:<?=$sc[1]?>;border:1px solid <?=$sc[4]?>;margin-bottom:2px;"></div>
              <span style="font-size:.55rem;font-weight:600;color:<?=$sc[1]==='#ffffff'?'#333':'#fff'?>;text-shadow:0 1px 2px rgba(0,0,0,.4)"><?=$schemeNames[$i]?></span>
            </button>
            <?php endforeach; ?>
          </div>

          <form method="POST" id="pageColorsForm"><?=csrfField()?><input type="hidden" name="form_action" value="page_colors_manual">

          <!-- Public Site Colors -->
          <h6 class="fw-semibold mb-2 mt-3" style="font-size:.85rem"><i class="bi bi-globe me-1"></i>Public Site Colors</h6>
          <div class="row g-3 mb-4">
            <?php
            $pubColors = [
              ['color_navbar_bg','Navbar Background','#0f172a'],
              ['color_navbar_text','Navbar Text','#ffffff'],
              ['color_topbar_bg','Top Bar Background','#060a12'],
              ['color_footer_bg','Footer Background','#1a1a2e'],
              ['color_footer_cta_bg','Footer CTA Start','#0f2557'],
              ['color_footer_cta_end','Footer CTA End','#1a3a7a'],
            ];
            foreach($pubColors as $pc): ?>
            <div class="col-md-4 col-6">
              <label class="form-label" style="font-size:.8rem"><?=$pc[1]?></label>
              <div class="d-flex gap-2 align-items-center">
                <input type="color" name="<?=$pc[0]?>" class="form-control form-control-color pc-picker" value="<?=e($s[$pc[0]]??$pc[2])?>" data-default="<?=$pc[2]?>" style="width:48px;height:38px;">
                <input type="text" class="form-control form-control-sm pc-hex" value="<?=e($s[$pc[0]]??$pc[2])?>" readonly style="font-size:.75rem;font-family:monospace;">
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Admin Backend Colors -->
          <h6 class="fw-semibold mb-2" style="font-size:.85rem"><i class="bi bi-gear me-1"></i>Admin Backend Colors</h6>
          <div class="row g-3 mb-4">
            <?php
            $adminColors = [
              ['color_sidebar_bg','Sidebar BG (Light)','#faf8f5'],
              ['color_sidebar_bg_dark','Sidebar BG (Dark)','#1a1a1a'],
              ['color_body_bg','Body BG (Light)','#f4f2ee'],
              ['color_body_bg_dark','Body BG (Dark)','#111111'],
            ];
            foreach($adminColors as $ac): ?>
            <div class="col-md-3 col-6">
              <label class="form-label" style="font-size:.8rem"><?=$ac[1]?></label>
              <div class="d-flex gap-2 align-items-center">
                <input type="color" name="<?=$ac[0]?>" class="form-control form-control-color pc-picker" value="<?=e($s[$ac[0]]??$ac[2])?>" data-default="<?=$ac[2]?>" style="width:48px;height:38px;">
                <input type="text" class="form-control form-control-sm pc-hex" value="<?=e($s[$ac[0]]??$ac[2])?>" readonly style="font-size:.75rem;font-family:monospace;">
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Live Mini Preview -->
          <h6 class="fw-semibold mb-2" style="font-size:.85rem"><i class="bi bi-eye me-1"></i>Live Preview</h6>
          <div class="border rounded-3 overflow-hidden mb-3" id="pcPreview">
            <div id="pcPrevTopbar" style="background:#060a12;padding:4px 12px;font-size:.65rem;color:rgba(255,255,255,0.7);">🎓 Welcome to School</div>
            <div id="pcPrevNavbar" style="background:#0f172aee;padding:8px 12px;display:flex;align-items:center;gap:12px;">
              <span style="color:#fff;font-weight:600;font-size:.8rem">School</span>
              <span id="pcPrevNavText" style="color:rgba(255,255,255,0.75);font-size:.75rem">Home</span>
              <span id="pcPrevNavText2" style="color:rgba(255,255,255,0.75);font-size:.75rem">About</span>
            </div>
            <div style="background:#fff;padding:12px;font-size:.75rem;color:#666;">Page content area...</div>
            <div id="pcPrevFooterCta" style="background:linear-gradient(135deg,#0f2557,#1a3a7a);padding:10px 12px;text-align:center;">
              <span style="color:#fff;font-size:.75rem;font-weight:600;">Join Our School</span>
            </div>
            <div id="pcPrevFooter" style="background:#1a1a2e;padding:10px 12px;display:flex;justify-content:space-between;">
              <span style="color:rgba(255,255,255,0.6);font-size:.7rem">© 2025 School</span>
              <span style="color:rgba(255,255,255,0.4);font-size:.7rem">Contact Info</span>
            </div>
          </div>
          <div class="d-flex gap-2 mb-2" id="pcPrevAdmin">
            <div id="pcPrevSidebar" style="width:80px;height:60px;border-radius:8px;background:#faf8f5;border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;font-size:.6rem;color:#999;">Sidebar</div>
            <div id="pcPrevBody" style="flex:1;height:60px;border-radius:8px;background:#f4f2ee;border:1px solid #e5e7eb;display:flex;align-items:center;justify-content:center;font-size:.6rem;color:#999;">Body</div>
          </div>

          <button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save Page Colors</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script>
  // Sync color pickers with hex fields and live preview
  document.querySelectorAll('.pc-picker').forEach(function(p){
    p.addEventListener('input', function(){
      this.closest('.d-flex').querySelector('.pc-hex').value = this.value;
      updatePcPreview();
    });
  });

  function getPcVal(name){ return document.querySelector('input[name="'+name+'"]').value; }

  function updatePcPreview(){
    var topbar = document.getElementById('pcPrevTopbar');
    var navbar = document.getElementById('pcPrevNavbar');
    var navText = document.getElementById('pcPrevNavText');
    var navText2 = document.getElementById('pcPrevNavText2');
    var footerCta = document.getElementById('pcPrevFooterCta');
    var footer = document.getElementById('pcPrevFooter');
    var sidebar = document.getElementById('pcPrevSidebar');
    var body = document.getElementById('pcPrevBody');
    topbar.style.background = getPcVal('color_topbar_bg');
    navbar.style.background = getPcVal('color_navbar_bg') + 'ee';
    var ntc = getPcVal('color_navbar_text');
    navText.style.color = ntc + 'cc';
    navText2.style.color = ntc + 'cc';
    footerCta.style.background = 'linear-gradient(135deg,' + getPcVal('color_footer_cta_bg') + ',' + getPcVal('color_footer_cta_end') + ')';
    footer.style.background = getPcVal('color_footer_bg');
    sidebar.style.background = getPcVal('color_sidebar_bg');
    body.style.background = getPcVal('color_body_bg');
  }

  // Preset scheme click handler
  document.querySelectorAll('.page-scheme-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var sc = JSON.parse(this.dataset.scheme);
      var keys = ['color_navbar_bg','color_navbar_text','color_topbar_bg','color_footer_bg','color_footer_cta_bg','color_footer_cta_end','color_sidebar_bg','color_sidebar_bg_dark','color_body_bg','color_body_bg_dark'];
      keys.forEach(function(k, i){
        var inp = document.querySelector('input[name="'+k+'"]');
        if(inp){ inp.value = sc[i+1]; inp.closest('.d-flex').querySelector('.pc-hex').value = sc[i+1]; }
      });
      updatePcPreview();
    });
  });

  updatePcPreview();
  </script>
  <?php endif;?>
</div>

          <!-- Preview Body -->
          <div class="p-3 bg-white">
            <h6 class="preview-heading fw-bold mb-2" style="font-size:.9rem;color:<?=e($s['primary_color']??'#1e40af')?>">Welcome to Our School</h6>
            <p class="text-muted mb-3" style="font-size:.75rem">This is a sample paragraph to show how body text looks alongside the theme color elements.</p>
            <div class="d-flex gap-2 mb-3">
              <button class="btn btn-sm preview-btn text-white" style="background:<?=e($s['primary_color']??'#1e40af')?>;border:none;font-size:.75rem">Primary Button</button>
              <button class="btn btn-sm btn-outline-primary preview-btn-outline" style="color:<?=e($s['primary_color']??'#1e40af')?>;border-color:<?=e($s['primary_color']??'#1e40af')?>;font-size:.75rem">Outline Button</button>
            </div>
            <div class="d-flex gap-3" style="font-size:.75rem">
              <a href="#" class="preview-link text-decoration-none" style="color:<?=e($s['primary_color']??'#1e40af')?>"><i class="bi bi-link-45deg me-1"></i>Sample Link</a>
              <a href="#" class="preview-link text-decoration-none" style="color:<?=e($s['primary_color']??'#1e40af')?>"><i class="bi bi-arrow-right me-1"></i>Learn More</a>
            </div>
          </div>

          <!-- Preview Footer -->
          <div class="preview-footer px-3 py-2" style="background:<?=e($s['primary_color']??'#1e40af')?>22">
            <div class="d-flex justify-content-between align-items-center">
              <span style="font-size:.7rem;color:<?=e($s['primary_color']??'#1e40af')?>">© 2025 <?=e($s['school_short_name']??'School')?></span>
              <div class="d-flex gap-2">
                <i class="bi bi-facebook preview-social-icon" style="font-size:.75rem;color:<?=e($s['primary_color']??'#1e40af')?>"></i>
                <i class="bi bi-instagram preview-social-icon" style="font-size:.75rem;color:<?=e($s['primary_color']??'#1e40af')?>"></i>
                <i class="bi bi-youtube preview-social-icon" style="font-size:.75rem;color:<?=e($s['primary_color']??'#1e40af')?>"></i>
              </div>
            </div>
          </div>
        </div>
      </div></div>
    </div>
  </div>
</div>

<!-- ========== CONTENT TAB ========== -->
<div class="tab-pane fade" id="tab-content" role="tabpanel">
  <div class="card border-0 rounded-3 mb-3"><div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0"><i class="bi bi-file-text me-2"></i>About Page Content</h6></div><div class="card-body">
    <p class="text-muted mb-3" style="font-size:.8rem">This content appears on the public About Us page. Leave empty to use default placeholder text.</p>
    <form method="POST"><?=csrfField()?><input type="hidden" name="form_action" value="about_content"><div class="row g-3">
    <div class="col-12"><label class="form-label fw-semibold">School History</label><textarea name="about_history" class="form-control" rows="4" placeholder="Tell the story of your school..."><?=e($s['about_history']??'')?></textarea></div>
    <div class="col-md-6"><label class="form-label fw-semibold">Vision Statement</label><textarea name="about_vision" class="form-control" rows="3" placeholder="Your school's vision..."><?=e($s['about_vision']??'')?></textarea></div>
    <div class="col-md-6"><label class="form-label fw-semibold">Mission Statement</label><textarea name="about_mission" class="form-control" rows="3" placeholder="Your school's mission..."><?=e($s['about_mission']??'')?></textarea></div>
    <div class="col-12"><button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save About Content</button></div>
    </div></form>
  </div></div>

  <!-- Core Values -->
  <div class="card border-0 rounded-3"><div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0"><i class="bi bi-trophy me-2"></i>Core Values</h6></div><div class="card-body">
    <p class="text-muted mb-3" style="font-size:.8rem">Edit the 4 core values displayed on the About Us page. Leave empty to use defaults.</p>
    <form method="POST"><?=csrfField()?><input type="hidden" name="form_action" value="core_values"><div class="row g-3">
    <?php
    $defaultValues = [
      1 => ['Excellence', 'We strive for the highest standards in academics, character, and personal growth.'],
      2 => ['Integrity', 'We foster honesty, transparency, and ethical behavior in all our actions.'],
      3 => ['Innovation', 'We embrace creativity and modern teaching methods to inspire learning.'],
      4 => ['Community', 'We build a supportive, inclusive environment where everyone belongs.'],
    ];
    for ($i = 1; $i <= 4; $i++):
      $defTitle = $defaultValues[$i][0];
      $defDesc = $defaultValues[$i][1];
    ?>
    <div class="col-md-6">
      <div class="bg-light rounded-3 p-3">
        <label class="form-label fw-semibold mb-1">Value <?=$i?> — Title</label>
        <input type="text" name="core_value_<?=$i?>_title" class="form-control form-control-sm mb-2" value="<?=e($s['core_value_'.$i.'_title']??$defTitle)?>" placeholder="<?=$defTitle?>">
        <label class="form-label fw-semibold mb-1">Description</label>
        <textarea name="core_value_<?=$i?>_desc" class="form-control form-control-sm" rows="2" placeholder="<?=$defDesc?>"><?=e($s['core_value_'.$i.'_desc']??$defDesc)?></textarea>
      </div>
    </div>
    <?php endfor; ?>
    <div class="col-12"><button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save Core Values</button></div>
    </div></form>
  </div></div>

  <!-- Certificates Settings -->
  <div class="card border-0 rounded-3 mt-3"><div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0"><i class="bi bi-award me-2"></i>Certificates & Accreditations</h6></div><div class="card-body">
    <p class="text-muted mb-3" style="font-size:.8rem">Control how certificates appear on the Home page and public site.</p>
    <form method="POST"><?=csrfField()?><input type="hidden" name="form_action" value="certificate_settings">
    <div class="row g-3">
      <div class="col-md-4">
        <div class="d-flex align-items-center justify-content-between bg-light rounded-3 p-3">
          <div>
            <div class="fw-semibold" style="font-size:.85rem">Show on Home Page</div>
            <small class="text-muted" style="font-size:.7rem">Featured certificates section</small>
          </div>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" name="home_certificates_show" <?=($s['home_certificates_show']??'1')==='1'?'checked':''?> style="width:2.5em;height:1.25em;">
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="d-flex align-items-center justify-content-between bg-light rounded-3 p-3">
          <div>
            <div class="fw-semibold" style="font-size:.85rem">Public Certificates Page</div>
            <small class="text-muted" style="font-size:.7rem">Enable /certificates.php</small>
          </div>
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" name="certificates_page_enabled" <?=($s['certificates_page_enabled']??'1')==='1'?'checked':''?> style="width:2.5em;height:1.25em;">
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold" style="font-size:.85rem">Max on Home Page</label>
        <input type="number" name="home_certificates_max" class="form-control form-control-sm" value="<?=e($s['home_certificates_max']??'6')?>" min="1" max="12">
      </div>
      <div class="col-12"><button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save Certificate Settings</button></div>
    </div>
    </form>
  </div></div>
</div>

<!-- ========== SOCIAL & SMS TAB ========== -->
<div class="tab-pane fade" id="tab-social" role="tabpanel">
  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card border-0 rounded-3"><div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0"><i class="bi bi-share me-2"></i>Social Media Links</h6></div><div class="card-body">
        <form method="POST"><?=csrfField()?><input type="hidden" name="form_action" value="social_links"><div class="row g-2">
        <div class="col-12"><div class="input-group input-group-sm"><span class="input-group-text"><i class="bi bi-facebook"></i></span><input type="url" name="social_facebook" class="form-control" placeholder="Facebook URL" value="<?=e($s['social_facebook']??'')?>"></div></div>
        <div class="col-12"><div class="input-group input-group-sm"><span class="input-group-text"><i class="bi bi-twitter-x"></i></span><input type="url" name="social_twitter" class="form-control" placeholder="Twitter/X URL" value="<?=e($s['social_twitter']??'')?>"></div></div>
        <div class="col-12"><div class="input-group input-group-sm"><span class="input-group-text"><i class="bi bi-instagram"></i></span><input type="url" name="social_instagram" class="form-control" placeholder="Instagram URL" value="<?=e($s['social_instagram']??'')?>"></div></div>
        <div class="col-12"><div class="input-group input-group-sm"><span class="input-group-text"><i class="bi bi-youtube"></i></span><input type="url" name="social_youtube" class="form-control" placeholder="YouTube URL" value="<?=e($s['social_youtube']??'')?>"></div></div>
        <div class="col-12"><div class="input-group input-group-sm"><span class="input-group-text"><i class="bi bi-linkedin"></i></span><input type="url" name="social_linkedin" class="form-control" placeholder="LinkedIn URL" value="<?=e($s['social_linkedin']??'')?>"></div></div>
        <div class="col-12"><button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save Links</button></div>
        </div></form>
      </div></div>
    </div>

    <div class="col-lg-6">
      <div class="card border-0 rounded-3"><div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0"><i class="bi bi-whatsapp me-2"></i>SMS / WhatsApp</h6></div><div class="card-body">
        <form method="POST"><?=csrfField()?><input type="hidden" name="form_action" value="sms_whatsapp"><div class="row g-3">
        <div class="col-12"><label class="form-label">WhatsApp API Number</label><input type="text" name="whatsapp_api_number" class="form-control" placeholder="+91 9876543210" value="<?=e($s['whatsapp_api_number']??'')?>"></div>
        <div class="col-12"><label class="form-label">SMS Gateway API Key</label><input type="text" name="sms_gateway_key" class="form-control" placeholder="API key from your SMS provider" value="<?=e($s['sms_gateway_key']??'')?>"></div>
        <div class="col-12"><button class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i>Save Config</button></div>
        </div></form>
      </div></div>
    </div>
  </div>
</div>


<!-- ========== EMAIL / SMTP TAB ========== -->
<?php if(isSuperAdmin()):?>
<div class="tab-pane fade" id="tab-email" role="tabpanel">
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card border-0 rounded-3">
        <div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0"><i class="bi bi-envelope me-2"></i>SMTP Email Configuration <span class="badge bg-warning text-dark ms-2" style="font-size:.6rem">Super Admin</span></h6></div>
        <div class="card-body">
          <form method="POST"><?=csrfField()?><input type="hidden" name="form_action" value="smtp_settings">
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label fw-semibold" style="font-size:.8rem">SMTP Host</label>
                <input type="text" name="smtp_host" class="form-control form-control-sm" value="<?=e($s['smtp_host']??'mail.awayindia.com')?>" placeholder="mail.example.com">
              </div>
              <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.8rem">SMTP Port</label>
                <input type="number" name="smtp_port" class="form-control form-control-sm" value="<?=e($s['smtp_port']??'465')?>" placeholder="465">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold" style="font-size:.8rem">SMTP Username</label>
                <input type="text" name="smtp_user" class="form-control form-control-sm" value="<?=e($s['smtp_user']??'noreply@jnvschool.awayindia.com')?>" placeholder="noreply@example.com">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold" style="font-size:.8rem">SMTP Password</label>
                <div class="input-group input-group-sm">
                  <input type="password" name="smtp_pass" id="smtpPassInput" class="form-control form-control-sm" value="<?=e($s['smtp_pass']??'')?>" placeholder="Enter SMTP password">
                  <button type="button" class="btn btn-outline-secondary" onclick="var i=document.getElementById('smtpPassInput');i.type=i.type==='password'?'text':'password';this.innerHTML=i.type==='password'?'<i class=\'bi bi-eye\'></i>':'<i class=\'bi bi-eye-slash\'></i>';"><i class="bi bi-eye"></i></button>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold" style="font-size:.8rem">Sender Name</label>
                <input type="text" name="smtp_from_name" class="form-control form-control-sm" value="<?=e($s['smtp_from_name']??'JNV School')?>" placeholder="School Name">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold" style="font-size:.8rem">Encryption</label>
                <select name="smtp_encryption" class="form-select form-select-sm">
                  <option value="ssl" <?=($s['smtp_encryption']??'ssl')==='ssl'?'selected':''?>>SSL</option>
                  <option value="tls" <?=($s['smtp_encryption']??'')==='tls'?'selected':''?>>TLS</option>
                  <option value="" <?=($s['smtp_encryption']??'ssl')===''?'selected':''?>>None</option>
                </select>
              </div>
            </div>
            <button class="btn btn-primary btn-sm mt-3 w-100"><i class="bi bi-save me-1"></i>Save Email Settings</button>
          </form>
        </div>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card border-0 rounded-3">
        <div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0"><i class="bi bi-send me-2"></i>Test Email</h6></div>
        <div class="card-body">
          <p class="text-muted" style="font-size:.8rem">Send a test email to verify the SMTP configuration works correctly.</p>
          <form method="POST" id="testEmailForm"><?=csrfField()?><input type="hidden" name="form_action" value="test_email">
            <div class="mb-2">
              <label class="form-label" style="font-size:.8rem;font-weight:600">Test Email Recipient</label>
              <input type="email" name="test_email_recipient" class="form-control form-control-sm" placeholder="<?=e($_SESSION['user_email']??'Enter email address')?>" value="">
            </div>
            <button class="btn btn-outline-success btn-sm w-100" onclick="var em=document.querySelector('[name=test_email_recipient]').value||'<?=e($_SESSION['user_email']??'')?>'; return confirm('Send test email to '+em+'?')"><i class="bi bi-envelope-check me-1"></i>Send Test Email</button>
          </form>
          <div class="mt-3 p-2 bg-light rounded-3">
            <small class="text-muted" style="font-size:.7rem">
              <i class="bi bi-info-circle me-1"></i><strong>Tips:</strong><br>
              • For cPanel hosting, use <code>mail.yourdomain.com</code> as SMTP host<br>
              • Port 465 uses SSL, Port 587 uses TLS<br>
              • The username is usually the full email address<br>
              • Make sure the email account exists in cPanel → Email Accounts
            </small>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif;?>

<!-- ========== USERS TAB ========== -->
<div class="tab-pane fade" id="tab-users" role="tabpanel">
  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card border-0 rounded-3"><div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0"><i class="bi bi-person-plus me-2"></i>Create User</h6></div><div class="card-body"><form method="POST"><?=csrfField()?><input type="hidden" name="form_action" value="create_user">
      <div class="mb-2"><input type="text" name="user_name" class="form-control form-control-sm" placeholder="Name" required></div>
      <div class="mb-2"><input type="email" name="user_email" class="form-control form-control-sm" placeholder="Email" required></div>
      <div class="mb-2"><input type="password" name="user_password" class="form-control form-control-sm" placeholder="Password" required minlength="6"></div>
      <div class="mb-2"><select name="user_role" class="form-select form-select-sm"><option value="teacher">Teacher</option><option value="office">Office</option><option value="admin">Admin</option><?php if(isSuperAdmin()):?><option value="super_admin">Super Admin</option><?php endif;?></select></div>
      <button class="btn btn-success btn-sm w-100"><i class="bi bi-person-plus me-1"></i>Create User</button></form></div></div>
    </div>

    <div class="col-lg-7">
      <div class="card border-0 rounded-3"><div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0">Users (<?=count($users)?>)</h6></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Name</th><th>Role</th><th>Status</th><th>Last Login</th><th></th></tr></thead><tbody>
      <?php foreach($users as $u):?><tr>
        <td style="font-size:.8rem"><strong><?=e($u['name'])?></strong><br><small class="text-muted"><?=e($u['email'])?></small></td>
        <td><span class="badge bg-primary-subtle text-primary"><?=e($u['role'])?></span></td>
        <td><span class="badge bg-<?=$u['is_active']?'success':'danger'?>-subtle text-<?=$u['is_active']?'success':'danger'?>"><?=$u['is_active']?'Active':'Inactive'?></span></td>
        <td style="font-size:.75rem"><?=$u['last_login']?date('M d, H:i',strtotime($u['last_login'])):'Never'?></td>
        <td class="text-nowrap">
          <?php if($u['id']!==currentUserId()):?>
          <button class="btn btn-sm btn-outline-primary py-0 px-1 btn-edit-user" data-bs-toggle="modal" data-bs-target="#editUserModal" data-id="<?=$u['id']?>" data-name="<?=e($u['name'])?>" data-role="<?=e($u['role'])?>" data-active="<?=$u['is_active']?>"><i class="bi bi-pencil" style="font-size:.7rem"></i></button>
          <form method="POST" class="d-inline"><input type="hidden" name="form_action" value="reset_user_pass"><input type="hidden" name="reset_user_id" value="<?=$u['id']?>"><?=csrfField()?><button class="btn btn-sm btn-outline-warning py-0 px-1" onclick="return confirm('Reset password to Reset@123?')" title="Reset Password"><i class="bi bi-key" style="font-size:.7rem"></i></button></form>
          <?php if(isSuperAdmin()):?><form method="POST" class="d-inline"><input type="hidden" name="form_action" value="delete_user"><input type="hidden" name="delete_user_id" value="<?=$u['id']?>"><?=csrfField()?><button class="btn btn-sm btn-outline-danger py-0 px-1" onclick="return confirm('Delete?')"><i class="bi bi-trash" style="font-size:.7rem"></i></button></form><?php endif;?>
          <?php endif;?>
        </td>
      </tr><?php endforeach;?></tbody></table></div></div></div>
    </div>
  </div>
</div>

<!-- ========== ACCESS CONTROL TAB ========== -->
<?php if(isSuperAdmin()):?>
<div class="tab-pane fade" id="tab-access" role="tabpanel">
  <div class="card border-0 rounded-3">
    <div class="card-header bg-white border-0"><h6 class="fw-semibold mb-0"><i class="bi bi-shield-lock me-2"></i>Feature Access Control <span class="badge bg-warning text-dark ms-2" style="font-size:.6rem">Super Admin</span></h6></div>
    <div class="card-body">
      <p class="text-muted mb-3" style="font-size:.8rem">Toggle modules ON/OFF for non-super-admin users. Disabled modules will be hidden from the sidebar and inaccessible.</p>
      <form method="POST"><?=csrfField()?><input type="hidden" name="form_action" value="feature_access">
      <div class="row g-3">
        <?php
        $featureList = [
          'feature_admissions' => ['Admissions', 'bi-file-earmark-plus-fill', 'Manage admission applications'],
          'feature_gallery' => ['Gallery', 'bi-images', 'Photo gallery management'],
          'feature_events' => ['Events', 'bi-calendar-event-fill', 'School events calendar'],
          'feature_slider' => ['Home Slider', 'bi-collection-play-fill', 'Homepage slider management'],
          'feature_notifications' => ['Notifications', 'bi-bell-fill', 'Notification management'],
          'feature_reports' => ['Reports', 'bi-file-earmark-bar-graph-fill', 'Reports & exports'],
          'feature_audit_logs' => ['Audit Logs', 'bi-clock-history', 'System activity logs'],
          'feature_hr' => ['HR & Payroll', 'bi-person-vcard-fill', 'Employee management, letters & payslips'],
          'feature_recruitment' => ['Recruitment', 'bi-people-fill', 'Teacher applications & recruitment'],
          'feature_fee_structure' => ['Fee Structure', 'bi-cash-stack', 'Fee management'],
          'feature_certificates' => ['Certificates', 'bi-award-fill', 'Certificates & accreditations'],
          'feature_feature_cards' => ['Feature Cards', 'bi-grid-1x2-fill', 'Homepage feature cards'],
          'feature_core_team' => ['Core Team', 'bi-people-fill', 'Core team management'],
        ];
        foreach ($featureList as $key => [$label, $icon, $desc]):
          $checked = getSetting($key, '1') === '1';
        ?>
        <div class="col-md-6">
          <div class="d-flex align-items-center justify-content-between bg-light rounded-3 p-3">
            <div class="d-flex align-items-center gap-2">
              <i class="bi <?=$icon?> text-primary"></i>
              <div>
                <div class="fw-semibold" style="font-size:.85rem"><?=$label?></div>
                <small class="text-muted" style="font-size:.7rem"><?=$desc?></small>
              </div>
            </div>
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" name="<?=$key?>" id="<?=$key?>" <?=$checked?'checked':''?> style="width:2.5em;height:1.25em;">
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <div class="col-12"><button class="btn btn-primary btn-sm"><i class="bi bi-shield-check me-1"></i>Save Feature Access</button></div>
      </div>
      </form>
    </div>
  </div>
</div>
<?php endif;?>

<!-- ========== SYSTEM TAB ========== -->
<div class="tab-pane fade" id="tab-system" role="tabpanel">

  <!-- Section 1: System Information (open by default) -->
  <div class="card border-0 rounded-3 mb-3 shadow-sm">
    <div class="card-header bg-white border-0 d-flex align-items-center justify-content-between" style="cursor:pointer" onclick="toggleSection('sysInfoBody',this)">
      <h6 class="fw-semibold mb-0"><i class="bi bi-cpu me-2 text-primary"></i>System Information</h6>
      <i class="bi bi-chevron-up section-chevron text-muted" style="transition:transform .2s"></i>
    </div>
    <div class="card-body section-body" id="sysInfoBody">
      <!-- Server Info -->
      <h6 class="text-muted fw-semibold" style="font-size:.7rem;text-transform:uppercase;letter-spacing:1px;"><i class="bi bi-hdd-rack me-1"></i>Server</h6>
      <div class="row g-2 mb-3">
        <div class="col-6"><div class="bg-light rounded-3 p-2 text-center"><small class="text-muted d-block" style="font-size:.65rem">PHP</small><strong style="font-size:.8rem"><?=phpversion()?></strong></div></div>
        <div class="col-6"><div class="bg-light rounded-3 p-2 text-center"><small class="text-muted d-block" style="font-size:.65rem">MySQL</small><strong style="font-size:.8rem"><?=e($mysqlVersion)?></strong></div></div>
        <div class="col-12"><div class="bg-light rounded-3 p-2 text-center"><small class="text-muted d-block" style="font-size:.65rem">Server Software</small><strong style="font-size:.75rem"><?=e(explode(' ', $_SERVER['SERVER_SOFTWARE']??'N/A')[0])?></strong></div></div>
      </div>

      <!-- Database -->
      <h6 class="text-muted fw-semibold" style="font-size:.7rem;text-transform:uppercase;letter-spacing:1px;"><i class="bi bi-database me-1"></i>Database</h6>
      <div class="row g-2 mb-3">
        <div class="col-4"><div class="bg-light rounded-3 p-2 text-center"><small class="text-muted d-block" style="font-size:.65rem">Tables</small><strong style="font-size:.85rem"><?=$dbTablesCount?></strong></div></div>
        <div class="col-4"><div class="bg-light rounded-3 p-2 text-center"><small class="text-muted d-block" style="font-size:.65rem">Size</small><strong style="font-size:.85rem"><?=$dbSize?> MB</strong></div></div>
        <div class="col-4"><div class="bg-light rounded-3 p-2 text-center"><small class="text-muted d-block" style="font-size:.65rem">DB Name</small><strong style="font-size:.7rem"><?=e(DB_NAME)?></strong></div></div>
      </div>

      <!-- Application Stats -->
      <h6 class="text-muted fw-semibold" style="font-size:.7rem;text-transform:uppercase;letter-spacing:1px;"><i class="bi bi-bar-chart me-1"></i>Application</h6>
      <div class="mb-2">
        <div class="d-flex justify-content-between" style="font-size:.75rem"><span>Students</span><span class="fw-semibold"><?=$activeStudents?> / <?=$totalStudents?> active</span></div>
        <div class="progress" style="height:6px"><div class="progress-bar bg-primary" style="width:<?=$totalStudents?round($activeStudents/$totalStudents*100,0):0?>%"></div></div>
      </div>
      <div class="mb-2">
        <div class="d-flex justify-content-between" style="font-size:.75rem"><span>Teachers</span><span class="fw-semibold"><?=$activeTeachers?> / <?=$totalTeachers?> active</span></div>
        <div class="progress" style="height:6px"><div class="progress-bar bg-success" style="width:<?=$totalTeachers?round($activeTeachers/$totalTeachers*100,0):0?>%"></div></div>
      </div>
      <div class="row g-2 mt-1">
        <div class="col-4"><div class="bg-light rounded-3 p-2 text-center"><small class="text-muted d-block" style="font-size:.65rem">Users</small><strong style="font-size:.85rem"><?=$totalUsers?></strong></div></div>
        <div class="col-4"><div class="bg-light rounded-3 p-2 text-center"><small class="text-muted d-block" style="font-size:.65rem">Notices</small><strong style="font-size:.85rem"><?=$totalNotifications?></strong></div></div>
        <div class="col-4"><div class="bg-light rounded-3 p-2 text-center"><small class="text-muted d-block" style="font-size:.65rem">Events</small><strong style="font-size:.85rem"><?=$totalEvents?></strong></div></div>
      </div>

      <!-- Server Time -->
      <div class="mt-3 bg-light rounded-3 p-2 text-center">
        <small class="text-muted d-block" style="font-size:.65rem">Server Time</small>
        <strong style="font-size:.8rem"><i class="bi bi-clock me-1"></i><?=date('d M Y, h:i A T')?></strong>
      </div>
    </div>
  </div>

  <?php if(isSuperAdmin()):?>

  <!-- Section 2: Database Setup (collapsed by default) -->
  <div class="card border-0 rounded-3 mb-3 shadow-sm" style="border:1px solid <?=$tableCount===$totalExpected?'#16a34a':'#eab308'?>!important">
    <div class="card-header border-0 d-flex align-items-center justify-content-between" style="background:<?=$tableCount===$totalExpected?'#f0fdf4':'#fefce8'?>;cursor:pointer" onclick="toggleSection('dbSetupBody',this)">
      <h6 class="fw-semibold mb-0" style="color:<?=$tableCount===$totalExpected?'#16a34a':'#ca8a04'?>">
        <i class="bi bi-database-gear me-2"></i>Database Setup
        <span class="badge bg-warning text-dark ms-2" style="font-size:.6rem">Super Admin</span>
        <?php $liveTableCount = is_numeric($dbTablesCount) ? (int)$dbTablesCount : count($dbExplorerTables ?? []); ?>
        <span class="badge rounded-pill bg-info text-white ms-2" style="font-size:.6rem"><?=$liveTableCount?> live tables</span>
      </h6>
      <i class="bi bi-chevron-down section-chevron" style="transition:transform .2s;color:<?=$tableCount===$totalExpected?'#16a34a':'#ca8a04'?>"></i>
    </div>
    <div class="card-body section-body collapsed" id="dbSetupBody">
      <!-- Pre-Setup Checklist -->
      <?php
      $connChecks=$_SESSION['db_conn_checks']??null;
      $connDetails=$_SESSION['db_conn_details']??[];
      unset($_SESSION['db_conn_checks'],$_SESSION['db_conn_details']);
      $checkItems=[
        ['key'=>'server','label'=>'Server: '.DB_HOST,'desc'=>'MySQL server reachable'],
        ['key'=>'database','label'=>'Database: '.DB_NAME,'desc'=>'Database exists & accessible'],
        ['key'=>'privileges','label'=>'User: '.DB_USER,'desc'=>'Has required privileges'],
      ];
      ?>
      <div class="mb-3 p-3 rounded-3" style="background:#f8fafc;border:1px solid #e2e8f0">
        <h6 class="fw-semibold mb-2" style="font-size:.8rem"><i class="bi bi-clipboard-check me-1"></i>Pre-Setup Checklist (cPanel)</h6>
        <div class="mb-2">
          <?php foreach($checkItems as $ci):
            $status=$connChecks[$ci['key']]??null;
            if($status===true){$icon='bi-check-circle-fill text-success';$bg='#f0fdf4';}
            elseif($status===false){$icon='bi-x-circle-fill text-danger';$bg='#fef2f2';}
            else{$icon='bi-dash-circle text-muted';$bg='#f8fafc';}
          ?>
          <div class="d-flex align-items-center gap-2 py-1 px-2 mb-1 rounded" style="font-size:.8rem;background:<?=$bg?>">
            <i class="bi <?=$icon?>"></i>
            <span class="fw-semibold"><?=e($ci['label'])?></span>
            <small class="text-muted ms-auto"><?=e($ci['desc'])?></small>
          </div>
          <?php endforeach;?>
        </div>
        <?php if($connChecks!==null&&!empty($connDetails)):?>
        <div class="mt-2 p-2 rounded" style="font-size:.72rem;background:<?=($connChecks['server']&&$connChecks['database']&&$connChecks['privileges'])?'#f0fdf4':'#fef2f2'?>">
          <?php foreach($connDetails as $d):?><div><i class="bi bi-info-circle me-1"></i><?=e($d)?></div><?php endforeach;?>
        </div>
        <?php endif;?>
        <form method="POST" class="mt-2"><?=csrfField()?><input type="hidden" name="form_action" value="test_db_connection">
          <button class="btn btn-outline-primary btn-sm"><i class="bi bi-plug me-1"></i>Test Connection</button>
        </form>
      </div>

      <hr class="my-3">
      <?php if(!empty($missingTables)):?>
      <div class="bg-light rounded-3 p-2 mb-3">
        <small class="fw-semibold text-muted d-block mb-1" style="font-size:.7rem">Missing tables:</small>
        <?php foreach($missingTables as $mt):?>
          <span class="badge bg-danger-subtle text-danger me-1 mb-1" style="font-size:.7rem"><i class="bi bi-x-circle me-1"></i><?=e($mt)?></span>
        <?php endforeach;?>
      </div>
      <?php endif;?>

      <details class="mb-3">
        <summary class="fw-semibold" style="font-size:.8rem;cursor:pointer"><i class="bi bi-list-check me-1"></i>Check Required App Tables (<?=$tableCount?>/<?=$totalExpected?>)</summary>
        <div class="mt-2" style="max-height:200px;overflow-y:auto;">
          <?php foreach($expectedTables as $et):?>
          <div class="d-flex align-items-center gap-2 py-1" style="font-size:.75rem;border-bottom:1px solid #f1f5f9">
            <?php if(in_array($et,$existingTables)):?>
              <i class="bi bi-check-circle-fill text-success"></i>
            <?php else:?>
              <i class="bi bi-x-circle-fill text-danger"></i>
            <?php endif;?>
            <code><?=e($et)?></code>
          </div>
          <?php endforeach;?>
        </div>
      </details>

      <?php if(isSuperAdmin() && !empty($dbExplorerTables)): ?>
      <!-- Database Explorer -->
      <div class="border rounded-3 mb-3" style="border-color:#6366f1!important">
        <div class="d-flex align-items-center justify-content-between p-2" style="background:#eef2ff;border-radius:.375rem .375rem 0 0;cursor:pointer" onclick="document.getElementById('dbExplorerBody').classList.toggle('d-none');this.querySelector('.bi-chevron-down')&&this.querySelector('.bi-chevron-down').classList.replace('bi-chevron-down','bi-chevron-up')||this.querySelector('.bi-chevron-up')&&this.querySelector('.bi-chevron-up').classList.replace('bi-chevron-up','bi-chevron-down');">
          <h6 class="fw-semibold mb-0" style="font-size:.8rem;color:#4f46e5">
            <i class="bi bi-database-gear me-1"></i>Database Explorer
            <span class="badge bg-warning text-dark ms-2" style="font-size:.55rem">Super Admin</span>
          </h6>
          <small class="text-muted d-block" style="font-size:.6rem">Shows all tables in database, not just application tables</small>
          <i class="bi bi-chevron-down" style="font-size:.7rem;color:#4f46e5"></i>
        </div>
        <div id="dbExplorerBody" class="d-none p-2">
          <?php
            $exTotalRows=0;$exTotalSize=0;
            foreach($dbExplorerTables as $t){$exTotalRows+=(int)$t['TABLE_ROWS'];$exTotalSize+=(float)$t['size_kb'];}
          ?>
          <div class="d-flex gap-2 mb-2 flex-wrap">
            <span class="badge bg-primary-subtle text-primary" style="font-size:.7rem"><i class="bi bi-table me-1"></i><?=count($dbExplorerTables)?> Tables</span>
            <span class="badge bg-success-subtle text-success" style="font-size:.7rem"><i class="bi bi-list-ol me-1"></i><?=number_format($exTotalRows)?> Rows</span>
            <span class="badge bg-info-subtle text-info" style="font-size:.7rem"><i class="bi bi-hdd me-1"></i><?=$exTotalSize>1024?round($exTotalSize/1024,2).' MB':round($exTotalSize,2).' KB'?></span>
          </div>
          <input type="text" class="form-control form-control-sm mb-2" placeholder="Search tables..." id="dbExplorerSearch" onkeyup="filterDbExplorer()" style="font-size:.75rem">
          <div style="max-height:400px;overflow-y:auto" id="dbExplorerList">
            <?php foreach($dbExplorerTables as $i=>$tbl): $tn=$tbl['TABLE_NAME']; ?>
            <div class="db-explorer-item" data-table="<?=strtolower($tn)?>">
              <div class="d-flex align-items-center gap-2 py-1 px-2" style="font-size:.75rem;border-bottom:1px solid #f1f5f9;cursor:pointer;background:#fafbfc" onclick="document.getElementById('dbCols_<?=$i?>').classList.toggle('d-none')">
                <i class="bi bi-table text-primary" style="font-size:.65rem"></i>
                <code class="fw-semibold"><?=e($tn)?></code>
                <span class="badge bg-secondary-subtle text-secondary ms-auto" style="font-size:.6rem"><?=e($tbl['ENGINE'])?></span>
                <span class="badge bg-light text-dark" style="font-size:.6rem"><?=number_format((int)$tbl['TABLE_ROWS'])?> rows</span>
                <span class="badge bg-light text-dark" style="font-size:.6rem"><?=$tbl['size_kb']>1024?round($tbl['size_kb']/1024,2).' MB':$tbl['size_kb'].' KB'?></span>
                <i class="bi bi-chevron-expand" style="font-size:.6rem"></i>
              </div>
              <div id="dbCols_<?=$i?>" class="d-none" style="background:#f8fafc;border-bottom:2px solid #e2e8f0">
                <table class="table table-sm table-borderless mb-0" style="font-size:.7rem">
                  <thead><tr style="background:#eef2ff"><th>Column</th><th>Type</th><th>Nullable</th><th>Key</th><th>Default</th><th>Extra</th></tr></thead>
                  <tbody>
                  <?php if(isset($dbExplorerColumns[$tn])): foreach($dbExplorerColumns[$tn] as $col): ?>
                    <tr>
                      <td><code><?=e($col['COLUMN_NAME'])?></code></td>
                      <td><span class="text-muted"><?=e($col['COLUMN_TYPE'])?></span></td>
                      <td><?=$col['IS_NULLABLE']==='YES'?'<span class="badge bg-warning-subtle text-warning" style="font-size:.6rem">YES</span>':'<span style="font-size:.6rem" class="text-muted">NO</span>'?></td>
                      <td>
                        <?php if($col['COLUMN_KEY']==='PRI'):?><span class="badge bg-primary" style="font-size:.6rem">PRI</span>
                        <?php elseif($col['COLUMN_KEY']==='UNI'):?><span class="badge" style="font-size:.6rem;background:#7c3aed;color:#fff">UNI</span>
                        <?php elseif($col['COLUMN_KEY']==='MUL'):?><span class="badge bg-secondary" style="font-size:.6rem">MUL</span>
                        <?php elseif($col['COLUMN_KEY']):?><span class="badge bg-light text-dark" style="font-size:.6rem"><?=e($col['COLUMN_KEY'])?></span>
                        <?php else:?><span class="text-muted" style="font-size:.6rem">—</span><?php endif;?>
                      </td>
                      <td><span class="text-muted"><?=$col['COLUMN_DEFAULT']!==null?e($col['COLUMN_DEFAULT']):'<em>NULL</em>'?></span></td>
                      <td><?=$col['EXTRA']?'<span class="badge bg-info-subtle text-info" style="font-size:.6rem">'.e($col['EXTRA']).'</span>':''?></td>
                    </tr>
                  <?php endforeach; endif;?>
                  </tbody>
                </table>
              </div>
            </div>
            <?php endforeach;?>
          </div>
        </div>
      </div>
      <script>
      function filterDbExplorer(){
        const q=document.getElementById('dbExplorerSearch').value.toLowerCase();
        document.querySelectorAll('.db-explorer-item').forEach(el=>{
          el.style.display=el.dataset.table.includes(q)?'':'none';
        });
      }
      </script>
      <?php endif; ?>

      <form method="POST" id="schemaImportForm" onsubmit="return validateSchemaImport()">
        <?=csrfField()?>
        <input type="hidden" name="form_action" value="import_schema">
        <div class="alert alert-danger py-2 mb-2" style="font-size:.75rem">
          <i class="bi bi-exclamation-octagon-fill me-1"></i>
          <strong>WARNING:</strong> Importing schema will <strong>DROP ALL existing tables</strong> and recreate them. <strong>ALL DATA WILL BE LOST.</strong> Back up your database first!
        </div>
        <div class="mb-2">
          <label class="form-label" style="font-size:.75rem">Type <strong>CONFIRM</strong> to proceed:</label>
          <input type="text" name="confirm_word" id="schemaConfirmInput" class="form-control form-control-sm" placeholder="Type CONFIRM" autocomplete="off">
        </div>
        <button type="submit" class="btn btn-danger btn-sm w-100" id="schemaImportBtn" disabled>
          <i class="bi bi-database-down me-1"></i>Import / Reset Schema
        </button>
      </form>
    </div>
  </div>

  <!-- Section 3: Backup & Migration (collapsed by default) -->
  <?php
  $sitePath=realpath(__DIR__.'/..');
  $siteSizeMB=0;
  if($sitePath&&is_dir($sitePath)){
    $iter=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sitePath,RecursiveDirectoryIterator::SKIP_DOTS));
    $totalBytes=0;foreach($iter as $f){if($f->isFile())$totalBytes+=$f->getSize();}
    $siteSizeMB=round($totalBytes/1024/1024,1);
  }
  $totalRows=0;
  try{
    $tbs=$db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    foreach($tbs as $tb){try{$totalRows+=(int)$db->query("SELECT COUNT(*) FROM `$tb`")->fetchColumn();}catch(Exception $e){}}
  }catch(Exception $e){}
  $backupToken=$_SESSION['csrf_token']??'';
  ?>
  <div class="card border-0 rounded-3 mb-3 shadow-sm" style="border:1px solid #6366f1!important">
    <div class="card-header border-0 d-flex align-items-center justify-content-between" style="background:#eef2ff;cursor:pointer" onclick="toggleSection('backupBody',this)">
      <h6 class="fw-semibold mb-0" style="color:#4f46e5">
        <i class="bi bi-cloud-download me-2"></i>Backup & Migration
        <span class="badge bg-warning text-dark ms-2" style="font-size:.6rem">Super Admin</span>
      </h6>
      <i class="bi bi-chevron-down section-chevron" style="transition:transform .2s;color:#4f46e5"></i>
    </div>
    <div class="card-body section-body collapsed" id="backupBody">
      <p class="text-muted mb-3" style="font-size:.8rem">Download backups for migrating to a new cPanel or for safekeeping.</p>

      <!-- Database Backup -->
      <div class="d-flex justify-content-between align-items-center p-2 rounded mb-2" style="background:#f8fafc;border:1px solid #e2e8f0">
        <div>
          <div class="fw-semibold" style="font-size:.8rem"><i class="bi bi-database me-1"></i>Database Backup</div>
          <small class="text-muted" style="font-size:.7rem">All <?=count($tbs??[])?> tables with <?=$totalRows?> rows as .sql file</small>
        </div>
        <a href="/admin/ajax/backup-download.php?type=database&token=<?=urlencode($backupToken)?>" class="btn btn-sm btn-outline-primary">
          <i class="bi bi-download me-1"></i>SQL
        </a>
      </div>

      <!-- Files Backup -->
      <div class="d-flex justify-content-between align-items-center p-2 rounded mb-2" style="background:#f8fafc;border:1px solid #e2e8f0">
        <div>
          <div class="fw-semibold" style="font-size:.8rem"><i class="bi bi-folder-symlink me-1"></i>Files Backup</div>
          <small class="text-muted" style="font-size:.7rem">All site files — PHP, config, uploads, everything (~<?=$siteSizeMB?> MB)</small>
        </div>
        <a href="/admin/ajax/backup-download.php?type=files&token=<?=urlencode($backupToken)?>" class="btn btn-sm btn-outline-success">
          <i class="bi bi-download me-1"></i>ZIP
        </a>
      </div>

      <!-- Full Backup -->
      <div class="d-flex justify-content-between align-items-center p-2 rounded mb-2" style="background:#eef2ff;border:1px solid #c7d2fe">
        <div>
          <div class="fw-semibold" style="font-size:.8rem"><i class="bi bi-box-seam me-1"></i>Full Backup (DB + Files)</div>
          <small class="text-muted" style="font-size:.7rem">Everything in one ZIP for complete migration</small>
        </div>
        <a href="/admin/ajax/backup-download.php?type=full&token=<?=urlencode($backupToken)?>" class="btn btn-sm btn-primary">
          <i class="bi bi-download me-1"></i>Full
        </a>
      </div>

      <div class="alert alert-info py-2 mt-3 mb-0" style="font-size:.72rem">
        <i class="bi bi-info-circle me-1"></i>
        <strong>Migration steps:</strong> Download full backup → Create new DB on new cPanel → Import .sql via phpMyAdmin → Extract site files to public_html → Update db.php credentials → Done!
      </div>
    </div>
  </div>

  <!-- Section 4: Danger Zone (collapsed by default) -->
  <div class="card border-0 rounded-3 mb-3 shadow-sm" style="border:1px solid #dc3545!important">
    <div class="card-header border-0 d-flex align-items-center justify-content-between" style="background:rgba(220,53,69,.08);cursor:pointer" onclick="toggleSection('dangerBody',this)">
      <h6 class="fw-semibold mb-0 text-danger">
        <i class="bi bi-exclamation-triangle me-2"></i>Danger Zone
        <span class="badge bg-warning text-dark ms-2" style="font-size:.6rem">Super Admin</span>
      </h6>
      <i class="bi bi-chevron-down section-chevron text-danger" style="transition:transform .2s"></i>
    </div>
    <div class="card-body section-body collapsed" id="dangerBody">
      <div class="d-flex flex-wrap gap-2">
        <form method="POST"><?=csrfField()?><input type="hidden" name="form_action" value="clear_audit_logs"><button class="btn btn-outline-danger btn-sm" onclick="return confirm('Clear ALL audit logs? This cannot be undone.')"><i class="bi bi-trash me-1"></i>Clear Audit Logs</button></form>
      </div>
    </div>
  </div>

  <?php endif;?>

</div>

<script>
function toggleSection(bodyId, headerEl) {
  const body = document.getElementById(bodyId);
  const chevron = headerEl.querySelector('.section-chevron');
  body.classList.toggle('collapsed');
  if (body.classList.contains('collapsed')) {
    chevron.classList.replace('bi-chevron-up', 'bi-chevron-down');
  } else {
    chevron.classList.replace('bi-chevron-down', 'bi-chevron-up');
  }
}
</script>

</div><!-- end tab-content -->

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content border-0 rounded-3">
  <div class="modal-header"><h6 class="modal-title fw-semibold">Edit User</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <form method="POST" id="editUserForm"><?=csrfField()?><input type="hidden" name="form_action" value="edit_user"><input type="hidden" name="edit_user_id" id="eu-id">
    <div class="mb-3"><label class="form-label">Name</label><input type="text" name="edit_user_name" id="eu-name" class="form-control" required></div>
    <div class="mb-3"><label class="form-label">Role</label><select name="edit_user_role" id="eu-role" class="form-select"><option value="teacher">Teacher</option><option value="office">Office</option><option value="admin">Admin</option><?php if(isSuperAdmin()):?><option value="super_admin">Super Admin</option><?php endif;?></select></div>
    <div class="mb-3"><label class="form-label">Status</label><select name="edit_user_active" id="eu-active" class="form-select"><option value="1">Active</option><option value="0">Inactive</option></select></div>
    <button class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i>Update User</button>
    </form>
  </div>
</div></div></div>

<style>
.section-body{max-height:2000px;overflow:hidden;transition:max-height .4s ease,opacity .3s ease,padding .3s ease;opacity:1}
.section-body.collapsed{max-height:0;opacity:0;padding-top:0!important;padding-bottom:0!important;border-top:none}
.theme-swatch{cursor:pointer;transition:transform .15s,box-shadow .15s}
.theme-swatch:hover{transform:scale(1.1);box-shadow:0 0 0 3px rgba(0,0,0,.2)}
.nav-pills .nav-link{color:#6c757d;font-size:.8rem;font-weight:500;white-space:nowrap;transition:all .2s}
.nav-pills .nav-link.active{background:var(--theme-primary, #1e40af);color:#fff}
.nav-pills .nav-link:not(.active):hover{background:#f8f9fa;color:#333}
#settingsTabs{scrollbar-width:none;-ms-overflow-style:none}
#settingsTabs::-webkit-scrollbar{display:none}
</style>

<script>
// Color picker live preview
const colorPicker = document.getElementById('primaryColorPicker');
const hexDisplay = document.getElementById('colorHexDisplay');
const preview = document.getElementById('colorPreview');

function updatePreview(color) {
  if (!preview) return;
  // Navbar
  const navbar = preview.querySelector('.preview-navbar');
  if (navbar) navbar.style.background = color;
  // Heading
  const heading = preview.querySelector('.preview-heading');
  if (heading) heading.style.color = color;
  // Filled button
  const btn = preview.querySelector('.preview-btn');
  if (btn) btn.style.background = color;
  // Outline button
  const btnOutline = preview.querySelector('.preview-btn-outline');
  if (btnOutline) { btnOutline.style.color = color; btnOutline.style.borderColor = color; }
  // Links
  preview.querySelectorAll('.preview-link').forEach(l => l.style.color = color);
  // Footer
  const footer = preview.querySelector('.preview-footer');
  if (footer) footer.style.background = color + '22';
  footer?.querySelectorAll('span, i').forEach(el => el.style.color = color);
  // Hex display
  if (hexDisplay) hexDisplay.textContent = color;
}

if (colorPicker) {
  colorPicker.addEventListener('input', function() { updatePreview(this.value); });
}

function selectColor(hex) {
  if (colorPicker) { colorPicker.value = hex; updatePreview(hex); }
}

// Tab persistence via URL hash
document.querySelectorAll('#settingsTabs button[data-bs-toggle="pill"]').forEach(tab => {
  tab.addEventListener('shown.bs.tab', function(e) {
    window.location.hash = e.target.getAttribute('data-bs-target').replace('#tab-', '');
  });
});

// Activate tab from hash on page load
(function() {
  const hash = window.location.hash.replace('#', '');
  if (hash) {
    const tabBtn = document.querySelector('#settingsTabs button[data-bs-target="#tab-' + hash + '"]');
    if (tabBtn) {
      const tab = new bootstrap.Tab(tabBtn);
      tab.show();
    }
  }
})();

// Set hash before form submit for tab persistence
document.querySelectorAll('.tab-pane form').forEach(form => {
  form.addEventListener('submit', function() {
    const pane = this.closest('.tab-pane');
    if (pane) {
      const tabId = pane.id.replace('tab-', '');
      window.location.hash = tabId;
    }
  });
});

// Edit user modal
document.querySelectorAll('.btn-edit-user').forEach(btn => {
  btn.addEventListener('click', function() {
    document.getElementById('eu-id').value = this.dataset.id;
    document.getElementById('eu-name').value = this.dataset.name;
    document.getElementById('eu-role').value = this.dataset.role;
    document.getElementById('eu-active').value = this.dataset.active;
  });
});

// Schema import confirm validation
const schemaInput = document.getElementById('schemaConfirmInput');
const schemaBtn = document.getElementById('schemaImportBtn');
if (schemaInput && schemaBtn) {
  schemaInput.addEventListener('input', function() {
    schemaBtn.disabled = this.value !== 'CONFIRM';
  });
}
function validateSchemaImport() {
  if (document.getElementById('schemaConfirmInput').value !== 'CONFIRM') return false;
  return confirm('FINAL WARNING: This will DELETE ALL DATA and recreate tables. Are you absolutely sure?');
}
</script>
<?php require_once __DIR__.'/../includes/footer.php';?>