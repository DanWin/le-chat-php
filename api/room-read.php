<?php
require_once __DIR__.DIRECTORY_SEPARATOR.'auth.php';

api_require_method('GET');
api_require_ready();
$bot=api_authenticate();
api_set_bot_user($bot);

$room_id=api_int_parameter($_GET['room_id'] ?? 1, 'room_id', 1, PHP_INT_MAX);
$since_id=api_int_parameter($_GET['since_id'] ?? 0, 'since_id', 0, PHP_INT_MAX);
$limit=api_int_parameter($_GET['limit'] ?? 50, 'limit', 1, 100);
if(!can_access_room($room_id)){
	api_json_response(['error'=>'This bot cannot access the requested room.'], 403);
}
api_take_read_slot($bot);

$expiry=time()-60*(int) get_setting('messageexpire');
$stmt=$db->prepare('SELECT id, postdate, poststatus, poster, text, reply_to FROM ' . PREFIX . 'messages WHERE room_id=? AND id>? AND postdate>=? AND (poststatus<=? OR poststatus=4) AND poster NOT IN (SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=?) ORDER BY id ASC LIMIT '.$limit.';');
$stmt->execute([$room_id, $since_id, $expiry, $U['status'], $U['nickname']]);
$messages=[];
$next_since_id=$since_id;
while($message=$stmt->fetch(PDO::FETCH_ASSOC)){
	prepare_message_print($message, true);
	$id=(int) $message['id'];
	$messages[]=[
		'id'=>$id,
		'created_at'=>(int) $message['postdate'],
		'poster'=>(string) $message['poster'],
		'text'=>(string) $message['text'],
		'reply_to'=>(int) $message['reply_to']
	];
	$next_since_id=$id;
}
api_json_response([
	'room'=>['id'=>$room_id, 'name'=>get_room_name($room_id)],
	'messages'=>$messages,
	'next_since_id'=>$next_since_id
]);