<?php
define('LE_CHAT_HTML_MODE', true);
try {
	require_once dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.'dms-trigger.php';
} catch (Throwable $e){
	http_response_code(503);
	header('Content-Type: text/plain; charset=UTF-8');
	echo 'DMS is unavailable: '.htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	exit;
}

load_lang();
if(session_status()===PHP_SESSION_NONE){
	ini_set('session.use_strict_mode', '1');
	session_name('LECHATDMS');
	$session_path=dirname($_SERVER['SCRIPT_NAME'] ?? '/');
	if($session_path==='.' || $session_path==='\\'){
		$session_path='/';
	}
	session_set_cookie_params([
		'lifetime'=>0,
		'path'=>$session_path,
		'secure'=>!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS'])!=='off',
		'httponly'=>true,
		'samesite'=>'Strict'
	]);
	session_start();
}
if(!isset($_SESSION['dms_csrf']) || !is_string($_SESSION['dms_csrf']) || $_SESSION['dms_csrf']===''){
	$_SESSION['dms_csrf']=bin2hex(random_bytes(32));
}
$csrf=$_SESSION['dms_csrf'];
$notice='';
if(isset($_SESSION['dms_flash']) && is_string($_SESSION['dms_flash'])){
	$notice=$_SESSION['dms_flash'];
	unset($_SESSION['dms_flash']);
}
$state=null;
try {
	$state=dms_open_state();
} catch (Throwable $e){
	send_fatal_error(_('DMS settings could not be authenticated. Check ENCRYPTKEY_PASS and database integrity.').' '.$e->getMessage());
}

if(!empty($session)){
	parse_sessions();
}
$is_superadmin=isset($U['status']) && (int) $U['status']===8;

if(($_SERVER['REQUEST_METHOD'] ?? '')==='POST'){
	$submitted_csrf=$_POST['csrf'] ?? '';
	if(!is_string($submitted_csrf) || !hash_equals($csrf, $submitted_csrf)){
		send_error(_('Invalid form security token. Please reload the page and try again.'));
	}
	$operation=$_POST['do'] ?? '';
	if(!is_string($operation)){
		send_error(_('Invalid DMS operation.'));
	}

	if($state===null){
		if(!$is_superadmin || $operation!=='setup'){
			send_access_denied();
		}
		$bootstrap_password=$_POST['bootstrap_password'] ?? '';
		$checkin_password=$_POST['checkin_password'] ?? '';
		$checkin_password_confirm=$_POST['checkin_password_confirm'] ?? '';
		$encryption_password=$_POST['encryption_password'] ?? '';
		$encryption_password_confirm=$_POST['encryption_password_confirm'] ?? '';
		$interval=filter_var($_POST['interval_hours'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>8760]]);
		$replacement_code=$_POST['replacement_code'] ?? '';
		if(!is_string($bootstrap_password) || !is_string($checkin_password) || !is_string($checkin_password_confirm) ||
			!is_string($encryption_password) || !is_string($encryption_password_confirm) || !is_string($replacement_code) || $interval===false){
			send_error(_('Please provide valid DMS setup values.'));
		}
		if(strlen($checkin_password)<12 || $checkin_password!==$checkin_password_confirm){
			send_error(_('The check-in password must be at least 12 characters and both password fields must match.'));
		}
		if(strlen($encryption_password)<16 || $encryption_password!==$encryption_password_confirm){
			send_error(_('The separate DMS encryption password must be at least 16 characters and both password fields must match.'));
		}
		if(hash_equals($checkin_password, $encryption_password)){
			send_error(_('The check-in password and DMS encryption password must be different.'));
		}
		if(!defined('PASSWORD_ARGON2ID') || !in_array('argon2id', password_algos(), true)){
			send_fatal_error(_('PHP must support Argon2id password hashing to set up the DMS.'));
		}
		try {
			define('LE_CHAT_DMS_AUTH', true);
			$bootstrap_hash=require dirname(__DIR__).DIRECTORY_SEPARATOR.'auth.php';
			if(!is_string($bootstrap_hash) || !password_verify($bootstrap_password, $bootstrap_hash)){
				send_error(_('The one-time DMS bootstrap password is incorrect.'));
			}
			if(hash_equals($bootstrap_password, $checkin_password) || hash_equals($bootstrap_password, $encryption_password)){
				send_error(_('The bootstrap password must be different from both new DMS passwords.'));
			}
			dms_validate_replacement_code($replacement_code);
			$now=time();
			$config=[
				'password_hash'=>password_hash($checkin_password, PASSWORD_ARGON2ID),
				'enabled'=>isset($_POST['enabled']),
				'interval_hours'=>(int) $interval,
				'replacement_code'=>$replacement_code,
				'destroy_database'=>isset($_POST['destroy_database']),
				'last_checkin_at'=>$now,
				'deadline_at'=>$now+((int) $interval*3600),
				'failure_at'=>0,
				'triggered_at'=>0
			];
			dms_clear_trigger_marker();
			$db->beginTransaction();
			dms_create_state($encryption_password, $config);
			$db->commit();
			$_SESSION['dms_flash']=_('Dead man\'s switch configured. The first check-in is due after the selected interval.');
		} catch (Throwable $e){
			if($db->inTransaction()){
				$db->rollBack();
			}
			send_error(_('DMS setup failed: ').$e->getMessage());
		}
	}elseif($operation==='checkin'){
		if(!$state['config']['enabled'] || $state['config']['triggered_at']>0){
			send_error(_('The dead man\'s switch is not enabled.'));
		}
		$now=time();
		if($state['lock_until']>$now){
			send_error(sprintf(_('Too many incorrect attempts. Try again after %s.'), date('Y-m-d H:i:s', $state['lock_until'])));
		}
		$password=$_POST['checkin_password'] ?? '';
		if(!is_string($password)){
			send_error(_('Invalid check-in password.'));
		}
		if(!password_verify($password, $state['config']['password_hash'])){
			$lock_until=$now+900;
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'dms SET lock_until=CASE WHEN failed_attempts>=4 THEN ? ELSE lock_until END, failed_attempts=failed_attempts+1 WHERE id=1;');
			$stmt->execute([$lock_until]);
			send_error(_('Incorrect check-in password.'));
		}
		try {
			$config=$state['config'];
			$config['last_checkin_at']=$now;
			$config['deadline_at']=$now+($config['interval_hours']*3600);
			$config['failure_at']=0;
			dms_clear_trigger_marker();
			$db->beginTransaction();
			dms_save_state($state, $config);
			$db->exec('UPDATE ' . PREFIX . 'dms SET failed_attempts=0, lock_until=0 WHERE id=1;');
			$db->commit();
			$_SESSION['dms_flash']=_('Check-in successful. The next deadline has been reset.');
		} catch (Throwable $e){
			if($db->inTransaction()){
				$db->rollBack();
			}
			send_error(_('Check-in failed: ').$e->getMessage());
		}
	}elseif($operation==='save' && $is_superadmin){
		$interval=filter_var($_POST['interval_hours'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1, 'max_range'=>8760]]);
		$replacement_code=$_POST['replacement_code'] ?? '';
		$new_checkin_password=$_POST['new_checkin_password'] ?? '';
		$new_checkin_password_confirm=$_POST['new_checkin_password_confirm'] ?? '';
		$new_encryption_password=$_POST['new_encryption_password'] ?? '';
		$new_encryption_password_confirm=$_POST['new_encryption_password_confirm'] ?? '';
		if($interval===false || !is_string($replacement_code) || !is_string($new_checkin_password) ||
			!is_string($new_checkin_password_confirm) || !is_string($new_encryption_password) || !is_string($new_encryption_password_confirm)){
			send_error(_('Please provide valid DMS settings.'));
		}
		if($new_checkin_password!=='' && (strlen($new_checkin_password)<12 || $new_checkin_password!==$new_checkin_password_confirm)){
			send_error(_('A new check-in password must be at least 12 characters and both password fields must match.'));
		}
		if($new_encryption_password!=='' && (strlen($new_encryption_password)<16 || $new_encryption_password!==$new_encryption_password_confirm)){
			send_error(_('A new DMS encryption password must be at least 16 characters and both password fields must match.'));
		}
		if($new_encryption_password!=='' && $new_checkin_password===''){
			send_error(_('Set a new check-in password when rotating the DMS encryption password.'));
		}
		if($new_checkin_password!=='' && (!defined('PASSWORD_ARGON2ID') || !in_array('argon2id', password_algos(), true))){
			send_fatal_error(_('PHP must support Argon2id password hashing to change the DMS check-in password.'));
		}
		if($new_checkin_password!=='' && hash_equals($new_checkin_password, $state['encryption_password'])){
			send_error(_('The check-in password and DMS encryption password must be different.'));
		}
		if($new_encryption_password!=='' && $new_checkin_password!=='' && hash_equals($new_checkin_password, $new_encryption_password)){
			send_error(_('The check-in password and DMS encryption password must be different.'));
		}
		try {
			dms_validate_replacement_code($replacement_code);
			$config=$state['config'];
			$was_enabled=$config['enabled'];
			$config['enabled']=isset($_POST['enabled']);
			$config['interval_hours']=(int) $interval;
			$config['replacement_code']=$replacement_code;
			$config['destroy_database']=isset($_POST['destroy_database']);
			if($new_checkin_password!==''){
				$config['password_hash']=password_hash($new_checkin_password, PASSWORD_ARGON2ID);
			}
			$now=time();
			if($config['enabled'] && (!$was_enabled || $config['triggered_at']>0)){
				$config['last_checkin_at']=$now;
				$config['deadline_at']=$now+($config['interval_hours']*3600);
				$config['failure_at']=0;
				$config['triggered_at']=0;
			}elseif($config['enabled']){
				$config['deadline_at']=$config['last_checkin_at']+($config['interval_hours']*3600);
			}
			if($config['enabled']){
				dms_clear_trigger_marker();
			}
			$db->beginTransaction();
			dms_save_state($state, $config, $new_encryption_password!=='' ? $new_encryption_password : null);
			$db->commit();
			$_SESSION['dms_flash']=_('DMS settings saved.');
		} catch (Throwable $e){
			if($db->inTransaction()){
				$db->rollBack();
			}
			send_error(_('Saving DMS settings failed: ').$e->getMessage());
		}
	}else{
		send_access_denied();
	}

	$_SESSION['dms_csrf']=bin2hex(random_bytes(32));
	session_write_close();
	$redirect=$_SERVER['SCRIPT_NAME'] ?? 'checkin.php';
	if(empty($_COOKIE[COOKIENAME]) && !empty($session)){
		$redirect.='?session='.rawurlencode($session);
	}
	header('Location: '.$redirect, true, 303);
	exit;
}

session_write_close();
header('Cache-Control: no-store');
print_start('admin');
echo '<h1>'._('Dead man\'s switch').'</h1>';
if($notice!==''){
	echo '<p role="status">'.htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>';
}
if($state===null){
	if($is_superadmin){
		echo '<p>'._('Initial setup requires the one-time Argon2id bootstrap password from admin/auth.php. Choose separate passwords for check-in and DMS encryption.').'</p>';
		echo form('dms_checkin', 'setup').'<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8').'">';
		echo '<p><label>'._('Bootstrap password').': <input type="password" name="bootstrap_password" autocomplete="current-password" required></label></p>';
		echo '<p><label>'._('New check-in password (12+ characters)').': <input type="password" name="checkin_password" autocomplete="new-password" required></label></p>';
		echo '<p><label>'._('Confirm check-in password').': <input type="password" name="checkin_password_confirm" autocomplete="new-password" required></label></p>';
		echo '<p><label>'._('Separate DMS encryption password (16+ characters)').': <input type="password" name="encryption_password" autocomplete="new-password" required></label></p>';
		echo '<p><label>'._('Confirm DMS encryption password').': <input type="password" name="encryption_password_confirm" autocomplete="new-password" required></label></p>';
		echo '<p><label>'._('Check-in interval in hours').': <input type="number" name="interval_hours" min="1" max="8760" value="1" required></label></p>';
		echo '<p><label><input type="checkbox" name="enabled" value="1" checked> '._('Enable the dead man\'s switch').'</label></p>';
		echo '<p><label><input type="checkbox" name="destroy_database" value="1"> '._('Destroy chat database tables after a missed check-in (irreversible)').'</label></p>';
		echo '<p><label>'._('Complete PHP source to replace chat.php after a missed check-in').':<br><textarea name="replacement_code" rows="16" cols="80" required></textarea></label></p>';
		echo submit(_('Set up DMS')).'</form>';
	}else{
		echo '<p>'._('The dead man\'s switch has not been configured. Only a Super-Admin can set it up.').'</p>';
	}
}else{
	$config=$state['config'];
	if($config['enabled']){
		echo '<p>'._('DMS is enabled.').'</p>';
		if($config['deadline_at']>0){
			echo '<p>'.sprintf(_('Next check-in deadline: %s'), date('Y-m-d H:i:s', $config['deadline_at'])).'</p>';
		}
		if($config['failure_at']>0){
			echo '<p>'.sprintf(_('Last missed deadline: %s'), date('Y-m-d H:i:s', $config['failure_at'])).'</p>';
		}
		echo form('dms_checkin', 'checkin').'<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8').'">';
		echo '<p><label>'._('DMS check-in password').': <input type="password" name="checkin_password" autocomplete="current-password" required></label></p>';
		echo submit(_('Check in')).'</form>';
	}else{
		echo '<p>'._('DMS is disabled.').'</p>';
	}
	if($is_superadmin){
		echo '<h2>'._('DMS settings').'</h2>';
		echo '<p>'.sprintf(_('Last successful check-in: %s'), date('Y-m-d H:i:s', $config['last_checkin_at'])).'</p>';
		echo form('dms_checkin', 'save').'<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8').'">';
		echo '<p><label>'._('Check-in interval in hours').': <input type="number" name="interval_hours" min="1" max="8760" value="'.(int) $config['interval_hours'].'" required></label></p>';
		echo '<p><label><input type="checkbox" name="enabled" value="1"'.($config['enabled'] ? ' checked' : '').'> '._('Enable the dead man\'s switch').'</label></p>';
		echo '<p><label><input type="checkbox" name="destroy_database" value="1"'.($config['destroy_database'] ? ' checked' : '').'> '._('Destroy chat database tables after a missed check-in (irreversible)').'</label></p>';
		echo '<p><label>'._('Replacement PHP source').':<br><textarea name="replacement_code" rows="16" cols="80" required>'.htmlspecialchars($config['replacement_code'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</textarea></label></p>';
		echo '<p><label>'._('New check-in password (leave blank to keep current)').': <input type="password" name="new_checkin_password" autocomplete="new-password"></label></p>';
		echo '<p><label>'._('Confirm new check-in password').': <input type="password" name="new_checkin_password_confirm" autocomplete="new-password"></label></p>';
		echo '<p><label>'._('New DMS encryption password (leave blank to keep current)').': <input type="password" name="new_encryption_password" autocomplete="new-password"></label></p>';
		echo '<p><label>'._('Confirm new DMS encryption password').': <input type="password" name="new_encryption_password_confirm" autocomplete="new-password"></label></p>';
		echo submit(_('Save DMS settings')).'</form>';
	}
}
if($is_superadmin){
	echo '<p><a href="../../chat.php?session='.rawurlencode((string) $U['session']).'">'._('Return to chat').'</a></p>';
}
print_end();