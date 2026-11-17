<?php
define('LE_CHAT_HTML_MODE', true);
require_once __DIR__.DIRECTORY_SEPARATOR.'api'.DIRECTORY_SEPARATOR.'auth.php';

load_lang();
if(!check_init()){
	send_fatal_error(_('Chat database is not initialized. Visit the Setup page first.'));
}
if((int) get_setting('dbversion')<DBVERSION){
	send_fatal_error(_('Visit the Setup page to apply database updates before managing bots.'));
}
check_session();
if(!has_staff_permission('manage_bots')){
	send_access_denied();
}

if(session_status()===PHP_SESSION_NONE){
	ini_set('session.use_strict_mode', '1');
	session_name('LECHATBOT');
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
if(!isset($_SESSION['bots_csrf']) || !is_string($_SESSION['bots_csrf']) || $_SESSION['bots_csrf']===''){
	$_SESSION['bots_csrf']=bin2hex(random_bytes(32));
}
$csrf=$_SESSION['bots_csrf'];
$notice='';
$one_time_token='';

if(($_SERVER['REQUEST_METHOD'] ?? '')==='POST'){
	$submitted_csrf=$_POST['csrf'] ?? '';
	if(!is_string($submitted_csrf) || !hash_equals($csrf, $submitted_csrf)){
		send_error(_('Invalid form security token. Please reload the page and try again.'));
	}
	$operation=$_POST['do'] ?? '';
	if(!is_string($operation)){
		send_error(_('Invalid bot operation.'));
	}

	if($operation==='create'){
		$name_input=$_POST['bot_name'] ?? '';
		$name=is_string($name_input) ? trim($name_input) : '';
		if(!valid_nick($name)){
			send_error(sprintf(_('Invalid nickname (%1$d characters maximum and has to match the regular expression "%2$s")'), get_setting('maxname'), get_setting('nickregex')));
		}
		try {
			foreach(['members', 'sessions'] as $table){
				$stmt=$db->prepare('SELECT nickname FROM ' . PREFIX . $table . ' WHERE LOWER(nickname)=LOWER(?);');
				$stmt->execute([$name]);
				if($stmt->fetchColumn()!==false){
					send_error(_('A user or bot already has that name.'));
				}
			}
			$stmt=$db->query('SELECT name FROM ' . PREFIX . 'bots;');
			while($existing=$stmt->fetchColumn()){
				if(mb_strtolower(decrypt_database_value((string) $existing))===mb_strtolower($name)){
					send_error(_('A bot with that name already exists.'));
				}
			}
			$one_time_token=bin2hex(random_bytes(32));
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'bots (name, token_cipher, token_hash, created_by, created_at) VALUES (?, ?, ?, ?, ?);');
			$stmt->execute([
				encrypt_database_value($name),
				encrypt_database_value($one_time_token),
				hash('sha256', $one_time_token),
				$U['nickname'],
				time()
			]);
			write_staff_log('bot_created', $name, 'Created by '.$U['nickname']);
			$_SESSION['bots_flash']=['notice'=>_('Bot created. Copy the token now; it will not be shown again.'), 'token'=>$one_time_token];
		} catch (Throwable $e){
			send_error($e->getMessage());
		}
	}elseif($operation==='rotate'){
		$bot_id=filter_var($_POST['bot_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
		if($bot_id===false){
			send_error(_('Invalid bot identifier.'));
		}
		$stmt=$db->prepare('SELECT name, token_hash FROM ' . PREFIX . 'bots WHERE id=? AND revoked_at=0;');
		$stmt->execute([$bot_id]);
		$stored_bot=$stmt->fetch(PDO::FETCH_ASSOC);
		if(!$stored_bot){
			send_error(_('The selected bot does not exist or has been revoked.'));
		}
		try {
			$name=decrypt_database_value((string) $stored_bot['name']);
			$one_time_token=bin2hex(random_bytes(32));
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'bots SET token_cipher=?, token_hash=?, last_read_at=0, send1_at=0, send2_at=0, send3_at=0, send4_at=0, send5_at=0 WHERE id=? AND token_hash=? AND revoked_at=0;');
			$stmt->execute([encrypt_database_value($one_time_token), hash('sha256', $one_time_token), $bot_id, $stored_bot['token_hash']]);
			if($stmt->rowCount()!==1){
				send_error(_('The bot token changed concurrently. Reload the page and try again.'));
			}
			write_staff_log('bot_token_rotated', $name, 'Rotated token by '.$U['nickname']);
			$_SESSION['bots_flash']=['notice'=>_('Token rotated. Copy the new token now; the old token no longer works.'), 'token'=>$one_time_token];
		} catch (Throwable $e){
			send_error($e->getMessage());
		}
	}elseif($operation==='revoke'){
		$bot_id=filter_var($_POST['bot_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
		if($bot_id===false){
			send_error(_('Invalid bot identifier.'));
		}
		$stmt=$db->prepare('SELECT name FROM ' . PREFIX . 'bots WHERE id=? AND revoked_at=0;');
		$stmt->execute([$bot_id]);
		$stored_name=$stmt->fetchColumn();
		if($stored_name===false){
			send_error(_('The selected bot does not exist or has already been revoked.'));
		}
		try {
			$name=decrypt_database_value((string) $stored_name);
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'bots SET revoked_at=? WHERE id=? AND revoked_at=0;');
			$stmt->execute([time(), $bot_id]);
			write_staff_log('bot_revoked', $name, 'Revoked bot #'.$bot_id);
			$_SESSION['bots_flash']=['notice'=>_('Bot token revoked.')];
		} catch (Throwable $e){
			send_error($e->getMessage());
		}
	}else{
		send_error(_('Invalid bot operation.'));
	}

	$_SESSION['bots_csrf']=bin2hex(random_bytes(32));
	session_write_close();
	$redirect=$_SERVER['SCRIPT_NAME'] ?? 'bots.php';
	if(empty($_COOKIE[COOKIENAME]) && !empty($session)){
		$redirect.='?session='.rawurlencode($session);
	}
	header('Location: '.$redirect, true, 303);
	exit;
}

if(isset($_SESSION['bots_flash']) && is_array($_SESSION['bots_flash'])){
	$notice=(string) ($_SESSION['bots_flash']['notice'] ?? '');
	$one_time_token=(string) ($_SESSION['bots_flash']['token'] ?? '');
	unset($_SESSION['bots_flash']);
}
session_write_close();
header('Cache-Control: no-store');

$bots=[];
$stmt=$db->query('SELECT id, name, created_by, created_at, revoked_at FROM ' . PREFIX . 'bots ORDER BY created_at DESC, id DESC;');
while($bot=$stmt->fetch(PDO::FETCH_ASSOC)){
	try {
		$bot['name']=decrypt_database_value((string) $bot['name']);
	} catch (Throwable $e){
		send_error($e->getMessage());
	}
	$bots[]=$bot;
}

print_start('admin');
echo '<h1>'._('Manage bots').'</h1>';
echo '<p>'._('Create bots for the API, or revoke and rotate their tokens. A token is only displayed once after creation or rotation.').'</p>';
echo '<p><a href="api/index.php">'._('Bot API guide').'</a></p>';
if($notice!==''){
	echo '<p role="status">'.htmlspecialchars($notice, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</p>';
}
if($one_time_token!==''){
	echo '<h2>'._('New bot token').'</h2><p>'._('Copy this token now. It cannot be recovered; rotate it if you lose it.').'</p>';
	echo '<pre><code>'.htmlspecialchars($one_time_token, ENT_QUOTES, 'UTF-8').'</code></pre>';
}

echo '<h2>'._('Create a bot').'</h2>'.form('bots', 'create');
echo '<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8').'">';
echo '<label>'._('Bot name').': <input type="text" name="bot_name" maxlength="'.(int) get_setting('maxname').'" required></label> ';
echo submit(_('Create bot')).'</form>';

echo '<h2>'._('Registered bots').'</h2>';
if(!$bots){
	echo '<p>'._('No bots have been created.').'</p>';
}else{
	echo '<table><thead><tr><th>'._('Name').'</th><th>'._('Created by').'</th><th>'._('Created').'</th><th>'._('Status').'</th><th>'._('Actions').'</th></tr></thead><tbody>';
	foreach($bots as $bot){
		$bot_id=(int) $bot['id'];
		echo '<tr><td>'.htmlspecialchars((string) $bot['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</td>';
		echo '<td>'.htmlspecialchars((string) $bot['created_by'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</td>';
		echo '<td>'.htmlspecialchars(date(get_setting('dateformat'), (int) $bot['created_at']), ENT_QUOTES, 'UTF-8').'</td>';
		echo '<td>'.((int) $bot['revoked_at']===0 ? _('Active') : _('Revoked')).'</td><td>';
		if((int) $bot['revoked_at']===0){
			echo form('bots', 'rotate').'<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8').'">'.hidden('bot_id', (string) $bot_id).submit(_('Rotate token')).'</form> ';
			echo form('bots', 'revoke').'<input type="hidden" name="csrf" value="'.htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8').'">'.hidden('bot_id', (string) $bot_id).submit(_('Revoke'), 'class="delbutton"').'</form>';
		}else{
			echo '&mdash;';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}
echo '<p><a href="chat.php?action=view&amp;session='.rawurlencode((string) $U['session']).'">'._('Return to chat').'</a></p>';
print_end();