<?php
require_once __DIR__ . '/../app/application.php';if(isset($_GET['lang'])&&in_array($_GET['lang'],supportedLanguages(),true))$_SESSION['lang']=$_GET['lang'];if(isAuthenticated())safeRedirect('../portal/');$error=null;
if(requestMethod()==='POST'){verifyCsrfToken();try{$name=trim((string)($_POST['fullname']??''));$email=normalizedEmail($_POST['email']??'');$phone=trim((string)($_POST['phone']??''));$password=(string)($_POST['password']??'');$confirmation=(string)($_POST['password_confirmation']??'');if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException(t('auth.signup_name_email_required'));if($password!==$confirmation)throw new InvalidArgumentException(t('auth.password_confirmation_mismatch'));$errors=passwordValidationErrors($password);if($errors)throw new InvalidArgumentException(implode(' ',$errors));$agency=dbFetchOne("SELECT id FROM agencies WHERE status='active' AND archived_at IS NULL ORDER BY id LIMIT 1");if(!$agency)throw new DomainException(t('auth.signup_unavailable'));$id=withTransaction(function()use($name,$email,$phone,$password,$agency){dbExecute("INSERT INTO users(fullname,email,email_normalized,phone,password_hash,role,status,password_changed_at)VALUES(:name,:email,:normalized,:phone,:hash,'CUSTOMER','active',NOW())",['name'=>$name,'email'=>$email,'normalized'=>$email,'phone'=>$phone,'hash'=>password_hash($password,PASSWORD_DEFAULT)]);$userId=(int)db()->lastInsertId();$parts=preg_split('/\s+/',trim($name),2);dbExecute("INSERT INTO customers(agency_id,user_id,customer_type,first_name,last_name,phone,phone_normalized,email,email_normalized,status)VALUES(:agency,:user,'individual',:first,:last,:phone_raw,:phone,:email,:normalized,'new')",['agency'=>$agency['id'],'user'=>$userId,'first'=>$parts[0],'last'=>$parts[1]??'Customer','phone_raw'=>$phone,'phone'=>normalizedPhone($phone),'email'=>$email,'normalized'=>$email]);return$userId;});auditLog('customer.self_registered','user',$id,null,['email'=>$email],$agency['id']);$user=dbFetchOne('SELECT * FROM users WHERE id=:id',['id'=>$id]);$user['iduser']=$id;$user['agency_ids']=[];loginUser($user);safeRedirect('../portal/');}catch(InvalidArgumentException|DomainException $exception){$error=$exception->getMessage();}catch(Throwable $exception){reportDatabaseError($exception,'Customer signup failed');$error=t('auth.registration_failed');}}
?><!doctype html><html lang="<?=e(language())?>" dir="<?=language()==='ar'?'rtl':'ltr'?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e(appConfig('name'))?> — <?=e(t('auth.signup_title'))?></title><link rel="icon" href="../backoffice/assets/img/favicon.png"><link rel="stylesheet" href="../backoffice/assets/app.css?v=<?= e(assetVersion('backoffice/assets/app.css')) ?>"></head><body>
<div class="auth-shell">
<aside class="auth-brand-panel" aria-hidden="true">
<div class="auth-brand-mark"><img class="auth-brand-logo" src="../backoffice/assets/img/aurevo-logo.png" alt="<?=e(appConfig('name'))?>"></div>
<div class="auth-brand-copy"><h2><?=e(t('auth.brand_headline'))?></h2><p><?=e(t('auth.brand_subheadline'))?></p></div>
<div class="auth-brand-footer">© <?=e(date('Y'))?> <?=e(appConfig('name'))?></div>
</aside>
<main class="auth-form-panel">
<div class="auth-card">
<h1><?=e(t('auth.signup_heading'))?></h1>
<?php if($error):?><div class="alert danger"><?=e($error)?></div><?php endif;?>
<form method="post"><?=csrfField()?>
<label class="auth-field"><?=e(t('field.full_name'))?><input name="fullname" required></label>
<label class="auth-field"><?=e(t('auth.email'))?><input type="email" name="email" required></label>
<label class="auth-field"><?=e(t('field.phone'))?><input name="phone"></label>
<label class="auth-field"><?=e(t('auth.password'))?><input type="password" name="password" required autocomplete="new-password"></label>
<label class="auth-field"><?=e(t('auth.confirm_password'))?><input type="password" name="password_confirmation" required autocomplete="new-password"><small class="field-hint"><?=e(t('auth.password_requirements'))?></small></label>
<button class="btn primary auth-submit"><?=e(t('auth.create_account_button'))?></button></form>
<div class="auth-links"><a href="login.php?lang=<?=e(language())?>"><?=e(t('auth.already_registered'))?></a><a href="../<?=e(language())?>/"><?=e(t('auth.public_website'))?></a></div>
</div>
</main>
</div>
</body></html>

