<?php
if(!defined('LE_CHAT_LIBRARY_ONLY')){
	define('LE_CHAT_LIBRARY_ONLY', true);
}
require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'chat.php';

if(!defined('VERSION')){
	load_config();
}
$U=[];
$db=null;
$memcached=null;
$language=LANG;
$locale=LANGUAGES[LANG]['locale'];
$dir=LANGUAGES[LANG]['dir'];
$styles=[];
$session=$_REQUEST['session'] ?? ($_COOKIE[COOKIENAME] ?? '');
$session=preg_replace('/[^0-9a-zA-Z]/', '', (string) $session);
$original_action=$_REQUEST['action'] ?? null;
$_REQUEST['action']='dms';
check_db();
if($original_action===null){
	unset($_REQUEST['action']);
}else{
	$_REQUEST['action']=$original_action;
}
if(!check_init()){
	throw new RuntimeException('The chat database is not initialized.');
}
if((int) get_setting('dbversion')<DBVERSION){
	throw new RuntimeException('Visit the Setup page to apply database updates before using the DMS.');
}
if(!extension_loaded('sodium')){
	throw new RuntimeException('The sodium extension is required for the dead man\'s switch.');
}
mb_internal_encoding('UTF-8');

function dms_derive_key(string $password, string $salt): string
{
	if(!preg_match('/^[a-f0-9]{32}$/', $salt)){
		throw new RuntimeException('Invalid DMS encryption salt.');
	}
	$raw_salt=hex2bin($salt);
	$key=hash_hkdf('sha256', $password, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES, 'le-chat-php:dms:v1', $raw_salt);
	if(strlen($key)!==SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES){
		throw new RuntimeException('Unable to derive the DMS encryption key.');
	}
	return $key;
}

function dms_encrypt_config(array $config, string $password, string $salt): string
{
	$key=dms_derive_key($password, $salt);
	$nonce=random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
	$plaintext=json_encode($config, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
	$cipher=sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plaintext, 'le-chat-php:dms:v1', $nonce, $key);
	return 'DMSENC1:'.base64_encode($nonce.$cipher);
}

function dms_decrypt_config(string $payload, string $password, string $salt): array
{
	if(strpos($payload, 'DMSENC1:')!==0){
		throw new RuntimeException('Unknown DMS encrypted data format.');
	}
	$decoded=base64_decode(substr($payload, 8), true);
	$nonce_length=SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
	$minimum_length=$nonce_length+SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES;
	if($decoded===false || strlen($decoded)<$minimum_length){
		throw new RuntimeException('Invalid DMS encrypted data.');
	}
	$nonce=substr($decoded, 0, $nonce_length);
	$cipher=substr($decoded, $nonce_length);
	$plaintext=sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher, 'le-chat-php:dms:v1', $nonce, dms_derive_key($password, $salt));
	if($plaintext===false){
		throw new RuntimeException('Could not authenticate DMS encrypted data.');
	}
	try {
		$config=json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
	} catch (JsonException $e){
		throw new RuntimeException('Invalid DMS settings data.', 0, $e);
	}
	if(!is_array($config)){
		throw new RuntimeException('Invalid DMS settings data.');
	}
	dms_validate_config($config);
	return $config;
}

function dms_validate_config(array $config): void
{
	foreach(['password_hash', 'replacement_code'] as $field){
		if(!isset($config[$field]) || !is_string($config[$field])){
			throw new RuntimeException('The DMS settings are incomplete.');
		}
	}
	if(!isset($config['enabled'], $config['destroy_database']) || !is_bool($config['enabled']) || !is_bool($config['destroy_database'])){
		throw new RuntimeException('The DMS options are invalid.');
	}
	foreach(['interval_hours', 'last_checkin_at', 'deadline_at', 'failure_at', 'triggered_at'] as $field){
		if(!isset($config[$field]) || !is_int($config[$field])){
			throw new RuntimeException('The DMS timing settings are invalid.');
		}
	}
	if($config['interval_hours']<1 || $config['interval_hours']>8760 || strpos($config['password_hash'], '$argon2id$')!==0){
		throw new RuntimeException('The DMS settings are invalid.');
	}
}

function dms_open_state(): ?array
{
	global $db;
	$stmt=$db->query('SELECT key_cipher, salt, config_cipher, updated_at, failed_attempts, lock_until FROM ' . PREFIX . 'dms WHERE id=1;');
	$row=$stmt->fetch(PDO::FETCH_ASSOC);
	if(!$row){
		return null;
	}
	$encryption_password=decrypt_database_value((string) $row['key_cipher']);
	$config=dms_decrypt_config((string) $row['config_cipher'], $encryption_password, (string) $row['salt']);
	return [
		'encryption_password'=>$encryption_password,
		'key_cipher'=>(string) $row['key_cipher'],
		'salt'=>(string) $row['salt'],
		'config_cipher'=>(string) $row['config_cipher'],
		'updated_at'=>(int) $row['updated_at'],
		'failed_attempts'=>(int) $row['failed_attempts'],
		'lock_until'=>(int) $row['lock_until'],
		'config'=>$config
	];
}

function dms_save_state(array $state, array $config, ?string $new_encryption_password=null): array
{
	global $db;
	dms_validate_config($config);
	$encryption_password=$new_encryption_password ?? $state['encryption_password'];
	$salt=$new_encryption_password===null ? $state['salt'] : bin2hex(random_bytes(16));
	$key_cipher=$new_encryption_password===null ? $state['key_cipher'] : encrypt_database_value($encryption_password);
	$config_cipher=dms_encrypt_config($config, $encryption_password, $salt);
	$stmt=$db->prepare('UPDATE ' . PREFIX . 'dms SET key_cipher=?, salt=?, config_cipher=?, updated_at=? WHERE id=1 AND config_cipher=?;');
	$stmt->execute([$key_cipher, $salt, $config_cipher, time(), $state['config_cipher']]);
	if($stmt->rowCount()!==1){
		throw new RuntimeException('DMS settings changed concurrently. Reload and try again.');
	}
	$state['encryption_password']=$encryption_password;
	$state['key_cipher']=$key_cipher;
	$state['salt']=$salt;
	$state['config_cipher']=$config_cipher;
	$state['updated_at']=time();
	$state['config']=$config;
	return $state;
}

function dms_create_state(string $encryption_password, array $config): array
{
	global $db;
	dms_validate_config($config);
	$salt=bin2hex(random_bytes(16));
	$key_cipher=encrypt_database_value($encryption_password);
	$config_cipher=dms_encrypt_config($config, $encryption_password, $salt);
	$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'dms (id, key_cipher, salt, config_cipher, updated_at, failed_attempts, lock_until) VALUES (1, ?, ?, ?, ?, 0, 0);');
	$stmt->execute([$key_cipher, $salt, $config_cipher, time()]);
	return [
		'encryption_password'=>$encryption_password,
		'key_cipher'=>$key_cipher,
		'salt'=>$salt,
		'config_cipher'=>$config_cipher,
		'updated_at'=>time(),
		'failed_attempts'=>0,
		'lock_until'=>0,
		'config'=>$config
	];
}

function dms_validate_replacement_code(string $code): void
{
	if(strlen($code)>1048576 || strpos($code, '<?php')!==0){
		throw new InvalidArgumentException('Replacement code must be a complete PHP file beginning with <?php and no larger than 1 MiB.');
	}
	try {
		token_get_all($code, TOKEN_PARSE);
	} catch (ParseError $e){
		throw new InvalidArgumentException('Replacement code has a PHP syntax error.', 0, $e);
	}
}

function dms_trigger_marker_path(): string
{
	return dirname(__DIR__).DIRECTORY_SEPARATOR.'cron'.DIRECTORY_SEPARATOR.'dms-triggered';
}

function dms_clear_trigger_marker(): void
{
	$marker=dms_trigger_marker_path();
	if(is_file($marker) && !unlink($marker)){
		throw new RuntimeException('Unable to clear the DMS trigger marker.');
	}
}

function dms_write_trigger_marker(): void
{
	$marker=dms_trigger_marker_path();
	$handle=fopen($marker, 'x');
	if($handle===false){
		throw new RuntimeException('Unable to write the DMS trigger marker.');
	}
	$contents=(string) time();
	$written=fwrite($handle, $contents);
	fclose($handle);
	if($written===false || $written!==strlen($contents)){
		if(!unlink($marker)){
			error_log('DMS could not remove an incomplete trigger marker: '.$marker);
		}
		throw new RuntimeException('Unable to write the DMS trigger marker.');
	}
}

function dms_replace_chat_file(string $code): void
{
	$target=dirname(__DIR__).DIRECTORY_SEPARATOR.'chat.php';
	if(!is_file($target) || is_link($target)){
		throw new RuntimeException('The chat.php target is not a regular file.');
	}
	$temp=$target.'.dms-'.bin2hex(random_bytes(8)).'.tmp';
	$permissions=fileperms($target);
	if($permissions===false){
		throw new RuntimeException('Unable to read chat.php file permissions.');
	}
	$written=file_put_contents($temp, $code, LOCK_EX);
	if($written===false || $written!==strlen($code)){
		if(is_file($temp) && !unlink($temp)){
			error_log('DMS could not remove an incomplete replacement file: '.$temp);
		}
		throw new RuntimeException('Unable to write the DMS replacement file.');
	}
	if(!chmod($temp, $permissions & 0777)){
		if(!unlink($temp)){
			error_log('DMS could not remove the replacement file with invalid permissions: '.$temp);
		}
		throw new RuntimeException('Unable to preserve chat.php file permissions.');
	}
	if(!@rename($temp, $target)){
		if(!unlink($temp)){
			error_log('DMS could not remove the failed replacement file: '.$temp);
		}
		throw new RuntimeException('Unable to replace chat.php.');
	}
}

function dms_destroy_tables(): void
{
	global $db, $memcached;
	if(MEMCACHED && is_object($memcached) && method_exists($memcached, 'delete')){
		$settings=$db->query('SELECT setting FROM ' . PREFIX . 'settings;');
		while($setting=$settings->fetchColumn()){
			$memcached->delete(DBNAME . '-' . PREFIX . 'settings-'.$setting);
		}
		$memcached->delete(DBNAME . '-' . PREFIX . 'filter');
		$memcached->delete(DBNAME . '-' . PREFIX . 'linkfilter');
	}
	$tables=[
		'captcha', 'files', 'filter', 'ignored', 'inbox', 'linkfilter', 'likes',
		'member_roles', 'messages', 'notes', 'reports', 'room_roles', 'sessions',
		'staff_log', 'bots', 'dms', 'members', 'roles', 'rooms', 'settings'
	];
	foreach($tables as $table){
		$db->exec('DROP TABLE IF EXISTS ' . PREFIX . $table . ';');
	}
}

function dms_check_deadline(): bool
{
	try {
		$state=dms_open_state();
		if($state===null){
			return false;
		}
		$config=$state['config'];
		$now=time();
		if(!$config['enabled'] || $config['triggered_at']>0 || $now<$config['deadline_at']){
			return false;
		}
		$config['failure_at']=$now;
		$state=dms_save_state($state, $config);
		error_log('LE CHAT DMS: a check-in deadline was missed at '.gmdate('c', $now).'.');
		dms_validate_replacement_code($config['replacement_code']);
		$config['enabled']=false;
		$config['triggered_at']=$now;
		$claimed=dms_save_state($state, $config);
		try {
			dms_write_trigger_marker();
			dms_replace_chat_file($config['replacement_code']);
		} catch (Throwable $e){
			try {
				dms_clear_trigger_marker();
			} catch (Throwable $marker_error){
				error_log('LE CHAT DMS: could not clear the trigger marker after replacement failed: '.$marker_error->getMessage());
			}
			$config['enabled']=true;
			$config['triggered_at']=0;
			try {
				dms_save_state($claimed, $config);
			} catch (Throwable $save_error){
				error_log('LE CHAT DMS: could not restore the pending state after replacement failed: '.$save_error->getMessage());
			}
			throw $e;
		}
		if($config['destroy_database']){
			error_log('LE CHAT DMS: destroying chat database tables as configured.');
			dms_destroy_tables();
		}
		return true;
	} catch (Throwable $e){
		error_log('LE CHAT DMS trigger failed: '.$e->getMessage());
		throw $e;
	}
}