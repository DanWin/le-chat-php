<?php
require_once __DIR__.DIRECTORY_SEPARATOR.'auth.php';

api_require_method('POST');
api_require_ready();
$bot=api_authenticate();
$body=api_json_body();
$message_input=$body['message'] ?? null;
if(!is_string($message_input)){
	api_json_response(['error'=>'message must be a string.'], 422);
}
$message_input=trim($message_input);
if($message_input==='' || mb_strlen($message_input)> (int) get_setting('maxmessage')){
	api_json_response(['error'=>'message must be non-empty and no longer than the configured maximum.'], 422);
}
$room_id=api_int_parameter($body['room_id'] ?? 1, 'room_id', 1, PHP_INT_MAX);
api_set_bot_user($bot);
if(!can_access_room($room_id)){
	api_json_response(['error'=>'This bot cannot send messages to the requested room.'], 403);
}

$message=htmlspecialchars($message_input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$message=preg_replace("/(\r?\n|\r\n?)/u", '<br>', $message);
$message=apply_filter($message, 1, $bot['name']);
$message=format_message_markup($message);
$message=create_hotlinks($message);
$message=apply_linkfilter($message);
$displaysend=sprintf(get_setting('msgsendall'), '<span class="lechat-bot-name"></span>');

try {
	$db->beginTransaction();
	$rate=api_try_take_send_slot($bot);
	if(!$rate['valid']){
		$db->rollBack();
		api_json_response(['error'=>'Invalid or revoked bot token.'], 401);
	}
	if(!$rate['allowed']){
		$db->rollBack();
		header('Retry-After: '.$rate['retry_after']);
		api_json_response(['error'=>'Message sending is limited to 5 messages every 10 seconds.'], 429);
	}
	if(!add_message($message, 's *', 'LECHATBOT:'.$bot['id'], 1, 1, $displaysend, $U['style'], 0, $room_id)){
		$db->rollBack();
		api_json_response(['error'=>'The message was not sent.'], 409);
	}
	$db->commit();
} catch (Throwable $e){
	if($db->inTransaction()){
		$db->rollBack();
	}
	error_log('Bot API could not send a message: '.$e->getMessage());
	api_json_response(['error'=>'Unable to send message.'], 500);
}

api_json_response(['sent'=>true, 'room_id'=>$room_id], 201);