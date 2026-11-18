<?php
if(PHP_SAPI!=='cli'){
	http_response_code(404);
	exit;
}

$marker=__DIR__.DIRECTORY_SEPARATOR.'dms-triggered';
if(is_file($marker)){
	exit(0);
}
$lock=fopen(__DIR__.DIRECTORY_SEPARATOR.'dms-check.lock', 'c');
if($lock===false){
	error_log('LE CHAT DMS: unable to open the checker lock file.');
	exit(1);
}
if(!flock($lock, LOCK_EX | LOCK_NB)){
	fclose($lock);
	exit(0);
}
try {
	require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'includes'.DIRECTORY_SEPARATOR.'dms-trigger.php';
	dms_check_deadline();
} catch (Throwable $e){
	error_log('LE CHAT DMS checker failed: '.$e->getMessage());
	exit(1);
} finally {
	flock($lock, LOCK_UN);
	fclose($lock);
}