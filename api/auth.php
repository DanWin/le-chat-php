<?php

if(!defined('LE_CHAT_LIBRARY_ONLY')){
	define('LE_CHAT_LIBRARY_ONLY', true);
}
if(!defined('LE_CHAT_HTML_MODE')){
	define('LE_CHAT_JSON_MODE', true);
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
foreach(['date', 'mbstring', 'pcre'] as $extension){
	if(!extension_loaded($extension)){
		send_fatal_error(sprintf(_('The %s extension of PHP is required, please install it first.'), $extension));
	}
}
mb_internal_encoding('UTF-8');
$session=$_REQUEST['session'] ?? ($_COOKIE[COOKIENAME] ?? '');
$session=preg_replace('/[^0-9a-zA-Z]/', '', (string) $session);
$original_action=$_REQUEST['action'] ?? null;
$_REQUEST['action']='bot_api';
check_db();
if($original_action===null){
	unset($_REQUEST['action']);
}else{
	$_REQUEST['action']=$original_action;
}

function api_json_response(array $payload, int $status=200): void
{
	http_response_code($status);
	header('Content-Type: application/json; charset=UTF-8');
	header('Cache-Control: no-store');
	header('X-Content-Type-Options: nosniff');
	echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
	exit;
}

if(defined('LE_CHAT_JSON_MODE')){
	set_exception_handler(function(Throwable $e): void {
		error_log('Bot API request failed: '.$e->getMessage());
		api_json_response(['error'=>'Internal server error.'], 500);
	});
}

function api_require_ready(): void
{
	global $db;
	if(!check_init()){
		api_json_response(['error'=>'Chat database is not initialized.'], 503);
	}
	if((int) get_setting('dbversion')<DBVERSION){
		api_json_response(['error'=>'Visit the chat Setup page to apply database updates before using the bot API.'], 503);
	}
}

function api_require_method(string $method): void
{
	if(($_SERVER['REQUEST_METHOD'] ?? '')!==$method){
		header('Allow: '.$method);
		api_json_response(['error'=>'Method not allowed.'], 405);
	}
}

function api_authenticate(): array
{
	global $db;
	$authorization=$_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
	if($authorization==='' && function_exists('getallheaders')){
		foreach(getallheaders() as $name=>$value){
			if(strcasecmp($name, 'Authorization')===0){
				$authorization=$value;
				break;
			}
		}
	}
	if(!is_string($authorization) || !preg_match('/^Bearer\s+([a-f0-9]{64})$/i', trim($authorization), $matches)){
		api_json_response(['error'=>'A valid Bearer token is required.'], 401);
	}
	$token=strtolower($matches[1]);
	$token_hash=hash('sha256', $token);
	$stmt=$db->prepare('SELECT id, name, token_hash FROM ' . PREFIX . 'bots WHERE token_hash=? AND revoked_at=0;');
	$stmt->execute([$token_hash]);
	$bot=$stmt->fetch(PDO::FETCH_ASSOC);
	if(!$bot || !hash_equals((string) $bot['token_hash'], $token_hash)){
		api_json_response(['error'=>'Invalid or revoked bot token.'], 401);
	}
	try {
		$name=decrypt_database_value((string) $bot['name']);
	} catch (Throwable $e){
		error_log('Bot API could not decrypt a bot name: '.$e->getMessage());
		api_json_response(['error'=>'Unable to decrypt bot identity.'], 500);
	}
	return ['id'=>(int) $bot['id'], 'name'=>$name, 'token_hash'=>$token_hash];
}

function api_set_bot_user(array $bot): void
{
	global $U;
	$U=[
		'nickname'=>'LECHATBOT:'.$bot['id'],
		'status'=>1,
		'style'=>'color:#FFFFFF;',
		'session'=>'',
		'entry'=>0
	];
}

function api_json_body(): array
{
	$content_type=$_SERVER['CONTENT_TYPE'] ?? '';
	if(!preg_match('/^application\/json(?:\s*;|$)/i', trim((string) $content_type))){
		api_json_response(['error'=>'Content-Type must be application/json.'], 415);
	}
	$input=fopen('php://input', 'rb');
	if($input===false){
		api_json_response(['error'=>'Unable to read request body.'], 400);
	}
	$body=stream_get_contents($input, 1048577);
	fclose($input);
	if($body===false){
		api_json_response(['error'=>'Unable to read request body.'], 400);
	}
	if(strlen($body)>1048576){
		api_json_response(['error'=>'Request body is too large.'], 413);
	}
	$decoded=json_decode($body);
	if(json_last_error()!==JSON_ERROR_NONE || !is_object($decoded)){
		api_json_response(['error'=>'Request body must be a valid JSON object.'], 400);
	}
	return (array) $decoded;
}

function api_int_parameter($value, string $name, int $minimum, int $maximum): int
{
	if(!is_int($value) && !(is_string($value) && preg_match('/^[0-9]+$/', $value))){
		api_json_response(['error'=>$name.' must be an integer.'], 422);
	}
	$value=(int) $value;
	if($value<$minimum || $value>$maximum){
		api_json_response(['error'=>$name.' is out of range.'], 422);
	}
	return $value;
}

function api_take_read_slot(array $bot): void
{
	global $db;
	$now=time();
	$stmt=$db->prepare('UPDATE ' . PREFIX . 'bots SET last_read_at=? WHERE id=? AND token_hash=? AND revoked_at=0 AND last_read_at<=?;');
	$stmt->execute([$now, $bot['id'], $bot['token_hash'], $now-10]);
	if($stmt->rowCount()>0){
		return;
	}
	$check=$db->prepare('SELECT last_read_at FROM ' . PREFIX . 'bots WHERE id=? AND token_hash=? AND revoked_at=0;');
	$check->execute([$bot['id'], $bot['token_hash']]);
	$last_read=$check->fetchColumn();
	if($last_read===false){
		api_json_response(['error'=>'Invalid or revoked bot token.'], 401);
	}
	$retry_after=max(1, 10-($now-(int) $last_read));
	header('Retry-After: '.$retry_after);
	api_json_response(['error'=>'Room reads are limited to once every 10 seconds.'], 429);
}

function api_try_take_send_slot(array $bot): array
{
	global $db;
	$now=time();
	$stmt=$db->prepare('UPDATE ' . PREFIX . 'bots SET send1_at=send2_at, send2_at=send3_at, send3_at=send4_at, send4_at=send5_at, send5_at=? WHERE id=? AND token_hash=? AND revoked_at=0 AND send1_at<=?;');
	$stmt->execute([$now, $bot['id'], $bot['token_hash'], $now-10]);
	if($stmt->rowCount()>0){
		return ['valid'=>true, 'allowed'=>true, 'retry_after'=>0];
	}
	$check=$db->prepare('SELECT send1_at FROM ' . PREFIX . 'bots WHERE id=? AND token_hash=? AND revoked_at=0;');
	$check->execute([$bot['id'], $bot['token_hash']]);
	$oldest=$check->fetchColumn();
	if($oldest===false){
		return ['valid'=>false, 'allowed'=>false, 'retry_after'=>0];
	}
	return ['valid'=>true, 'allowed'=>false, 'retry_after'=>max(1, 10-($now-(int) $oldest))];
}

if(basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''))==='auth.php'){
	api_require_method('GET');
	api_require_ready();
	$bot=api_authenticate();
	api_json_response(['authenticated'=>true, 'bot'=>['id'=>$bot['id'], 'name'=>$bot['name']]]);
}