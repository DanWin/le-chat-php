<?php
/*
* LE CHAT-PHP - a PHP Chat based on LE CHAT - Main program
*
* Copyright (C) 2015-2018 Daniel Winzen <d@winzen4.de>
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation, either version 3 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

/*
* status codes
* 0 - Kicked/Banned
* 1 - Guest
* 2 - Applicant
* 3 - Member
* 4 - System message
* 5 - Moderator
* 6 - Super-Moderator
* 7 - Admin
* 8 - Super-Admin
* 9 - Private messages
*/

send_headers();
// initialize and load variables/configuration
$I=[];// Translations
$L=[];// Languages
$U=[];// This user data
$db;// Database connection
$memcached;// Memcached connection
$language;// user selected language
load_config();
// set session variable to cookie if cookies are enabled
if(!isset($_REQUEST['session']) && isset($_COOKIE[COOKIENAME])){
	$_REQUEST['session']=$_COOKIE[COOKIENAME];
}
$_REQUEST['session'] = preg_replace('/[^0-9a-zA-Z]/', '', $_REQUEST['session']);
load_lang();
check_db();
cron();
route();

//  main program: decide what to do based on queries
function route(){
	global $U;
	if(!isset($_REQUEST['action'])){
		send_login();
	}elseif($_REQUEST['action']==='view'){
		check_session();
		send_messages();
	}elseif($_REQUEST['action']==='redirect' && !empty($_REQUEST['url'])){
		send_redirect($_REQUEST['url']);
	}elseif($_REQUEST['action']==='wait'){
		parse_sessions();
		send_waiting_room();
	}elseif($_REQUEST['action']==='post'){
		check_session();
		if(isset($_REQUEST['kick']) && isset($_REQUEST['sendto']) && $_REQUEST['sendto']!=='s &'){
			if($U['status']>=5 || ($U['status']>=3 && get_count_mods()==0 && get_setting('memkick'))){
				if(isset($_REQUEST['what']) && $_REQUEST['what']==='purge'){
					kick_chatter([$_REQUEST['sendto']], $_REQUEST['message'], true);
				}else{
					kick_chatter([$_REQUEST['sendto']], $_REQUEST['message'], false);
				}
			}
		}elseif(isset($_REQUEST['message']) && isset($_REQUEST['sendto'])){
			send_post(validate_input());
		}
		send_post();
	}elseif($_REQUEST['action']==='login'){
		check_login();
		send_frameset();
	}elseif($_REQUEST['action']==='controls'){
		check_session();
		send_controls();
	}elseif($_REQUEST['action']==='greeting'){
		check_session();
		send_greeting();
	}elseif($_REQUEST['action']==='delete'){
		check_session();
		if($_REQUEST['what']==='all'){
			if(isset($_REQUEST['confirm'])){
				del_all_messages($U['nickname'], $U['status']==1 ? $U['entry'] : 0);
			}else{
				send_del_confirm();
			}
		}elseif($_REQUEST['what']==='last'){
			del_last_message();
		}
		send_post();
	}elseif($_REQUEST['action']==='profile'){
		check_session();
		$arg='';
		if(!isset($_REQUEST['do'])){
		}elseif($_REQUEST['do']==='save'){
			$arg=save_profile();
		}elseif($_REQUEST['do']==='delete'){
			if(isset($_REQUEST['confirm'])){
				delete_account();
			}else{
				send_delete_account();
			}
		}
		send_profile($arg);
	}elseif($_REQUEST['action']==='logout'){
		kill_session();
		send_logout();
	}elseif($_REQUEST['action']==='colours'){
		check_session();
		send_colours();
	}elseif($_REQUEST['action']==='notes'){
		check_session();
		if(isset($_REQUEST['do']) && $_REQUEST['do']==='admin' && $U['status']>6){
			send_notes(0);
		}elseif(isset($_REQUEST['do']) && $_REQUEST['do']==='staff' && $U['status']>=5){
			send_notes(1);
		}
		if($U['status']<3 || !get_setting('personalnotes')){
			send_access_denied();
		}
		send_notes(2);
	}elseif($_REQUEST['action']==='help'){
		check_session();
		send_help();
	}elseif($_REQUEST['action']==='inbox'){
		check_session();
		if(isset($_REQUEST['do'])){
			clean_inbox_selected();
		}
		send_inbox();
	}elseif($_REQUEST['action']==='download'){
		send_download();
	}elseif($_REQUEST['action']==='admin'){
		check_session();
		send_admin(route_admin());
	}elseif($_REQUEST['action']==='setup'){
		route_setup();
	}else{
		send_login();
	}
}

function route_admin(){
	global $U, $db;
	if($U['status']<5){
		send_access_denied();
	}
	if(!isset($_REQUEST['do'])){
	}elseif($_REQUEST['do']==='clean'){
		if($_REQUEST['what']==='choose'){
			send_choose_messages();
		}elseif($_REQUEST['what']==='selected'){
			clean_selected($U['status'], $U['nickname']);
		}elseif($_REQUEST['what']==='room'){
			clean_room();
		}elseif($_REQUEST['what']==='nick'){
			$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'members WHERE nickname=? AND status>=?;');
			$stmt->execute([$_REQUEST['nickname'], $U['status']]);
			if(!$stmt->fetch(PDO::FETCH_ASSOC)){
				del_all_messages($_REQUEST['nickname'], 0);
			}
		}
	}elseif($_REQUEST['do']==='kick'){
		if(isset($_REQUEST['name'])){
			if(isset($_REQUEST['what']) && $_REQUEST['what']==='purge'){
				kick_chatter($_REQUEST['name'], $_REQUEST['kickmessage'], true);
			}else{
				kick_chatter($_REQUEST['name'], $_REQUEST['kickmessage'], false);
			}
		}
	}elseif($_REQUEST['do']==='logout'){
		if(isset($_REQUEST['name'])){
			logout_chatter($_REQUEST['name']);
		}
	}elseif($_REQUEST['do']==='sessions'){
		if(isset($_REQUEST['kick']) && isset($_REQUEST['nick'])){
			kick_chatter([$_REQUEST['nick']], '', false);
		}elseif(isset($_REQUEST['logout']) && isset($_REQUEST['nick'])){
			logout_chatter([$_REQUEST['nick']], '', false);
		}
		send_sessions();
	}elseif($_REQUEST['do']==='register'){
		return register_guest(3, $_REQUEST['name']);
	}elseif($_REQUEST['do']==='superguest'){
		return register_guest(2, $_REQUEST['name']);
	}elseif($_REQUEST['do']==='status'){
		return change_status($_REQUEST['name'], $_REQUEST['set']);
	}elseif($_REQUEST['do']==='regnew'){
		return register_new($_REQUEST['name'], $_REQUEST['pass']);
	}elseif($_REQUEST['do']==='approve'){
		approve_session();
		send_approve_waiting();
	}elseif($_REQUEST['do']==='guestaccess'){
		if(isset($_REQUEST['guestaccess']) && preg_match('/^[0123]$/', $_REQUEST['guestaccess'])){
			update_setting('guestaccess', $_REQUEST['guestaccess']);
		}
	}elseif($_REQUEST['do']==='filter'){
		send_filter(manage_filter());
	}elseif($_REQUEST['do']==='linkfilter'){
		send_linkfilter(manage_linkfilter());
	}elseif($_REQUEST['do']==='topic'){
		if(isset($_REQUEST['topic'])){
			update_setting('topic', htmlspecialchars($_REQUEST['topic']));
		}
	}elseif($_REQUEST['do']==='passreset'){
		return passreset($_REQUEST['name'], $_REQUEST['pass']);
	}
}

function route_setup(){
	global $U;
	if(!valid_admin()){
		send_alogin();
	}
	$C['bool_settings']=['suguests', 'imgembed', 'timestamps', 'trackip', 'memkick', 'forceredirect', 'incognito', 'sendmail', 'modfallback', 'disablepm', 'eninbox', 'enablegreeting', 'sortupdown', 'hidechatters', 'personalnotes', 'filtermodkick'];
	$C['colour_settings']=['colbg', 'coltxt'];
	$C['msg_settings']=['msgenter', 'msgexit', 'msgmemreg', 'msgsureg', 'msgkick', 'msgmultikick', 'msgallkick', 'msgclean', 'msgsendall', 'msgsendmem', 'msgsendmod', 'msgsendadm', 'msgsendprv', 'msgattache'];
	$C['number_settings']=['memberexpire', 'guestexpire', 'kickpenalty', 'entrywait', 'captchatime', 'messageexpire', 'messagelimit', 'maxmessage', 'maxname', 'minpass', 'defaultrefresh', 'numnotes', 'maxuploadsize', 'enfileupload'];
	$C['textarea_settings']=['rulestxt', 'css', 'disabletext'];
	$C['text_settings']=['dateformat', 'captchachars', 'redirect', 'chatname', 'mailsender', 'mailreceiver', 'nickregex', 'passregex', 'externalcss'];
	$C['settings']=array_merge(['guestaccess', 'englobalpass', 'globalpass', 'captcha', 'dismemcaptcha', 'topic', 'guestreg', 'defaulttz'], $C['bool_settings'], $C['colour_settings'], $C['msg_settings'], $C['number_settings'], $C['textarea_settings'], $C['text_settings']); // All settings in the database
	if(!isset($_REQUEST['do'])){
	}elseif($_REQUEST['do']==='save'){
		save_setup($C);
	}elseif($_REQUEST['do']==='backup' && $U['status']==8){
		send_backup($C);
	}elseif($_REQUEST['do']==='restore' && $U['status']==8){
		restore_backup($C);
		send_backup($C);
	}elseif($_REQUEST['do']==='destroy' && $U['status']==8){
		if(isset($_REQUEST['confirm'])){
			destroy_chat($C);
		}else{
			send_destroy_chat();
		}
	}
	send_setup($C);
}

//  html output subs
function print_stylesheet($init=false){
	global $U;
	//default css
	echo '<style type="text/css">';
	echo 'body{background-color:#000000;color:#FFFFFF;font-size:14px;text-align:center;} ';
	echo 'a:visited{color:#B33CB4;} a:active{color:#FF0033;} a:link{color:#0000FF;} #messages{word-wrap:break-word;} ';
	echo 'input,select,textarea{color:#FFFFFF;background-color:#000000;} .messages a img{width:15%} .messages a:hover img{width:35%} ';
	echo '.error{color:#FF0033;text-align:left;} .delbutton{background-color:#660000;} .backbutton{background-color:#004400;} #exitbutton{background-color:#AA0000;} ';
	echo '.setup table table,.admin table table,.profile table table{width:100%;text-align:left} ';
	echo '.alogin table,.init table,.destroy_chat table,.delete_account table,.sessions table,.filter table,.linkfilter table,.notes table,.approve_waiting table,.del_confirm table,.profile table,.admin table,.backup table,.setup table{margin-left:auto;margin-right:auto;} ';
	echo '.setup table table table,.admin table table table,.profile table table table{border-spacing:0px;margin-left:auto;margin-right:unset;width:unset;} ';
	echo '.setup table table td,.backup #restoresubmit,.backup #backupsubmit,.admin table table td,.profile table table td,.login td+td,.alogin td+td{text-align:right;} ';
	echo '.init td,.backup #restorecheck td,.admin #clean td,.admin #regnew td,.session td,.messages,.inbox,.approve_waiting td,.choose_messages,.greeting,.help,.login td,.alogin td{text-align:left;} ';
	echo '.messages #chatters{max-height:100px;overflow-y:auto;} .messages #chatters a{text-decoration-line:none;} .messages #chatters table{border-spacing:0px;} ';
	echo '.messages #chatters th,.messages #chatters td,.post #firstline{vertical-align:top;} ';
	echo '.approve_waiting #action td:only-child,.help #backcredit,.login td:only-child,.alogin td:only-child,.init td:only-child{text-align:center;} .sessions td,.sessions th,.approve_waiting td,.approve_waiting th{padding: 5px;} ';
	echo '.sessions td td{padding: 1px;} .messages #bottom_link{position:fixed;top:0.5em;right:0.5em;} .messages #top_link{position:fixed;bottom:0.5em;right:0.5em;} ';
	echo '.post table,.controls table,.login table{border-spacing:0px;margin-left:auto;margin-right:auto;} .login table{border:2px solid;} .controls{overflow-y:none;} ';
	echo '#manualrefresh{display:block;position:fixed;text-align:center;left:25%;width:50%;top:-200%;animation:timeout_messages ';
	if(isset($U['refresh'])){
		echo $U['refresh']+20;
	}else{
		echo '160';
	}
	echo 's forwards;z-index:2;background-color:#500000;border:2px solid #ff0000;} ';
	echo '@keyframes timeout_messages{0%{top:-200%;} 99%{top:-200%;} 100%{top:0%;}} ';
	echo '.notes textarea{height:80vh;width:80%;}';
	echo '</style>';
	if($init){
		return;
	}
	$css=get_setting('css');
	$coltxt=get_setting('coltxt');
	if(!empty($U['bgcolour'])){
		$colbg=$U['bgcolour'];
	}else{
		$colbg=get_setting('colbg');
	}
	//overwrite with custom css
	echo "<style type=\"text/css\">body{background-color:#$colbg;color:#$coltxt;} $css</style>";
}

function print_end(){
	echo '</body></html>';
	exit;
}

function credit(){
	return '<small><br><br><a target="_blank" href="https://github.com/DanWin/le-chat-php">LE CHAT-PHP - ' . VERSION . '</a></small>';
}

function meta_html(){
	return '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><meta http-equiv="Pragma" content="no-cache"><meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate, max-age=0"><meta http-equiv="expires" content="0"><meta name="referrer" content="no-referrer">';
}

function form($action, $do=''){
	global $language;
	$form="<form action=\"$_SERVER[SCRIPT_NAME]\" enctype=\"multipart/form-data\" method=\"post\">".hidden('lang', $language).hidden('nc', substr(time(), -6)).hidden('action', $action);
	if(!empty($_REQUEST['session'])){
		$form.=hidden('session', $_REQUEST['session']);
	}
	if($do!==''){
		$form.=hidden('do', $do);
	}
	return $form;
}

function form_target($target, $action, $do=''){
	global $language;
	$form="<form action=\"$_SERVER[SCRIPT_NAME]\" enctype=\"multipart/form-data\" method=\"post\" target=\"$target\">".hidden('lang', $language).hidden('nc', substr(time(), -6)).hidden('action', $action);
	if(!empty($_REQUEST['session'])){
		$form.=hidden('session', $_REQUEST['session']);
	}
	if($do!==''){
		$form.=hidden('do', $do);
	}
	return $form;
}

function hidden($arg1='', $arg2=''){
	return "<input type=\"hidden\" name=\"$arg1\" value=\"$arg2\">";
}

function submit($arg1='', $arg2=''){
	return "<input type=\"submit\" value=\"$arg1\" $arg2>";
}

function thr(){
	echo '<tr><td><hr></td></tr>';
}

function print_start($class='', $ref=0, $url=''){
	global $I;
	if(!empty($url)){
		$url=str_replace('&amp;', '&', $url);// Don't escape "&" in URLs here, it breaks some (older) browsers and js refresh!
		header("Refresh: $ref; URL=$url");
	}
	echo '<!DOCTYPE html><html><head>'.meta_html();
	if(!empty($url)){
		echo "<meta http-equiv=\"Refresh\" content=\"$ref; URL=$url\">";
		$ref+=5;//only use js if browser refresh stopped working
		$ref*=1000;//js uses milliseconds
		echo "<script type=\"text/javascript\">setTimeout(function(){window.location.replace(\"$url\");}, $ref);</script>";
	}
	if($class==='init'){
		echo "<title>$I[init]</title>";
		print_stylesheet(true);
	}else{
		echo '<title>'.get_setting('chatname').'</title>';
		print_stylesheet();
	}
	echo "</head><body class=\"$class\">";
	if($class!=='init' && ($externalcss=get_setting('externalcss'))!=''){
		//external css - in body to make it non-renderblocking
		echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"$externalcss\">";
	}
}

function send_redirect($url){
	global $I;
	$url=trim(htmlspecialchars_decode(rawurldecode($url)));
	preg_match('~^(.*)://~u', $url, $match);
	$url=preg_replace('~^(.*)://~u', '', $url);
	$escaped=htmlspecialchars($url);
	if(isset($match[1]) && ($match[1]==='http' || $match[1]==='https')){
		print_start('redirect', 0, $match[0].$escaped);
		echo "<p>$I[redirectto] <a href=\"$match[0]$escaped\">$match[0]$escaped</a>.</p>";
	}else{
		print_start('redirect');
		if(!isset($match[0])){
			$match[0]='';
		}
		if(preg_match('~^(javascript|blob|data):~', $url)){
			echo "<p>$I[dangerousnonhttp] $match[0]$escaped</p>";
		} else {
			echo "<p>$I[nonhttp] <a href=\"$match[0]$escaped\">$match[0]$escaped</a>.</p>";
		}
		echo "<p>$I[httpredir] <a href=\"http://$escaped\">http://$escaped</a>.</p>";
	}
	print_end();
}

function send_access_denied(){
	global $I, $U;
	header('HTTP/1.1 403 Forbidden');
	print_start('access_denied');
	echo "<h1>$I[accessdenied]</h1>".sprintf($I['loggedinas'], style_this(htmlspecialchars($U['nickname']), $U['style'])).'<br>';
	echo form('logout');
	if(!isset($_REQUEST['session'])){
		echo hidden('session', $U['session']);
	}
	echo submit($I['logout'], 'id="exitbutton"')."</form>";
	print_end();
}

function send_captcha(){
	global $I, $db, $memcached;
	$difficulty=(int) get_setting('captcha');
	if($difficulty===0 || !extension_loaded('gd')){
		return;
	}
	$captchachars=get_setting('captchachars');
	$length=strlen($captchachars)-1;
	$code='';
	for($i=0;$i<5;++$i){
		$code.=$captchachars[mt_rand(0, $length)];
	}
	$randid=mt_rand();
	$time=time();
	if(MEMCACHED){
		$memcached->set(DBNAME . '-' . PREFIX . "captcha-$randid", $code, get_setting('captchatime'));
	}else{
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'captcha (id, time, code) VALUES (?, ?, ?);');
		$stmt->execute([$randid, $time, $code]);
	}
	echo "<tr id=\"captcha\"><td>$I[copy]<br>";
	if($difficulty===1){
		$im=imagecreatetruecolor(55, 24);
		$bg=imagecolorallocate($im, 0, 0, 0);
		$fg=imagecolorallocate($im, 255, 255, 255);
		imagefill($im, 0, 0, $bg);
		imagestring($im, 5, 5, 5, $code, $fg);
		echo '<img width="55" height="24" src="data:image/gif;base64,';
	}elseif($difficulty===2){
		$im=imagecreatetruecolor(55, 24);
		$bg=imagecolorallocate($im, 0, 0, 0);
		$fg=imagecolorallocate($im, 255, 255, 255);
		imagefill($im, 0, 0, $bg);
		imagestring($im, 5, 5, 5, $code, $fg);
		$line=imagecolorallocate($im, 255, 255, 255);
		for($i=0;$i<2;++$i){
			imageline($im, 0, mt_rand(0, 24), 55, mt_rand(0, 24), $line);
		}
		$dots=imagecolorallocate($im, 255, 255, 255);
		for($i=0;$i<100;++$i){
			imagesetpixel($im, mt_rand(0, 55), mt_rand(0, 24), $dots);
		}
		echo '<img width="55" height="24" src="data:image/gif;base64,';
	}else{
		$im=imagecreatetruecolor(150, 200);
		$bg=imagecolorallocate($im, 0, 0, 0);
		$fg=imagecolorallocate($im, 255, 255, 255);
		imagefill($im, 0, 0, $bg);
		$chars=[];
		for($i=0;$i<10;++$i){
			$found=false;
			while(!$found){
				$x=mt_rand(10, 140);
				$y=mt_rand(10, 180);
				$found=true;
				foreach($chars as $char){
					if($char['x']>=$x && ($char['x']-$x)<25){
						$found=false;
					}elseif($char['x']<$x && ($x-$char['x'])<25){
						$found=false;
					}
					if(!$found){
						if($char['y']>=$y && ($char['y']-$y)<25){
							break;
						}elseif($char['y']<$y && ($y-$char['y'])<25){
							break;
						}else{
							$found=true;
						}
					}
				}
			}
			$chars[]=['x', 'y'];
			$chars[$i]['x']=$x;
			$chars[$i]['y']=$y;
			if($i<5){
				imagechar($im, 5, $chars[$i]['x'], $chars[$i]['y'], $captchachars[mt_rand(0, $length)], $fg);
			}else{
				imagechar($im, 5, $chars[$i]['x'], $chars[$i]['y'], $code[$i-5], $fg);
			}
		}
		$follow=imagecolorallocate($im, 200, 0, 0);
		imagearc($im, $chars[5]['x']+4, $chars[5]['y']+8, 16, 16, 0, 360, $follow);
		for($i=5;$i<9;++$i){
			imageline($im, $chars[$i]['x']+4, $chars[$i]['y']+8, $chars[$i+1]['x']+4, $chars[$i+1]['y']+8, $follow);
		}
		$line=imagecolorallocate($im, 255, 255, 255);
		for($i=0;$i<5;++$i){
			imageline($im, 0, mt_rand(0, 200), 150, mt_rand(0, 200), $line);
		}
		$dots=imagecolorallocate($im, 255, 255, 255);
		for($i=0;$i<1000;++$i){
			imagesetpixel($im, mt_rand(0, 150), mt_rand(0, 200), $dots);
		}
		echo '<img width="150" height="200" src="data:image/gif;base64,';
	}
	ob_start();
	imagegif($im);
	imagedestroy($im);
	echo base64_encode(ob_get_clean()).'">';
	echo '</td><td>'.hidden('challenge', $randid).'<input type="text" name="captcha" size="15" autocomplete="off" required></td></tr>';
}

function send_setup($C){
	global $I, $U;
	print_start('setup');
	echo "<h2>$I[setup]</h2>".form('setup', 'save');
	if(!isset($_REQUEST['session'])){
		echo hidden('session', $U['session']);
	}
	echo '<table id="guestaccess">';
	thr();
	$ga=(int) get_setting('guestaccess');
	echo "<tr><td><table><tr><th>$I[guestacc]</th><td>";
	echo '<select name="guestaccess">';
	echo '<option value="1"';
	if($ga===1){
		echo ' selected';
	}
	echo ">$I[guestallow]</option>";
	echo '<option value="2"';
	if($ga===2){
		echo ' selected';
	}
	echo ">$I[guestwait]</option>";
	echo '<option value="3"';
	if($ga===3){
		echo ' selected';
	}
	echo ">$I[adminallow]</option>";
	echo '<option value="0"';
	if($ga===0){
		echo ' selected';
	}
	echo ">$I[guestdisallow]</option>";
	echo '<option value="4"';
	if($ga===4){
		echo ' selected';
	}
	echo ">$I[disablechat]</option>";
	echo '</select></td></tr></table></td></tr>';
	thr();
	$englobal=(int) get_setting('englobalpass');
	echo "<tr><td><table id=\"globalpass\"><tr><th>$I[globalloginpass]</th><td>";
	echo '<table>';
	echo '<tr><td><select name="englobalpass">';
	echo '<option value="0"';
	if($englobal===0){
		echo ' selected';
	}
	echo ">$I[disabled]</option>";
	echo '<option value="1"';
	if($englobal===1){
		echo ' selected';
	}
	echo ">$I[enabled]</option>";
	echo '<option value="2"';
	if($englobal===2){
		echo ' selected';
	}
	echo ">$I[onlyguests]</option>";
	echo '</select></td><td>&nbsp;</td>';
	echo '<td><input type="text" name="globalpass" value="'.htmlspecialchars(get_setting('globalpass')).'"></td></tr>';
	echo '</table></td></tr></table></td></tr>';
	thr();
	$ga=(int) get_setting('guestreg');
	echo "<tr><td><table id=\"guestreg\"><tr><th>$I[guestreg]</th><td>";
	echo '<select name="guestreg">';
	echo '<option value="0"';
	if($ga===0){
		echo ' selected';
	}
	echo ">$I[disabled]</option>";
	echo '<option value="1"';
	if($ga===1){
		echo ' selected';
	}
	echo ">$I[assuguest]</option>";
	echo '<option value="2"';
	if($ga===2){
		echo ' selected';
	}
	echo ">$I[asmember]</option>";
	echo '</select></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table id=\"sysmessages\"><tr><th>$I[sysmessages]</th><td>";
	echo '<table>';
	foreach($C['msg_settings'] as $setting){
		echo "<tr><td>&nbsp;$I[$setting]</td><td>&nbsp;<input type=\"text\" name=\"$setting\" value=\"".get_setting($setting).'"></td></tr>';
	}
	echo '</table></td></tr></table></td></tr>';
	foreach($C['text_settings'] as $setting){
		thr();
		echo "<tr><td><table id=\"$setting\"><tr><th>".$I[$setting].'</th><td>';
		echo "<input type=\"text\" name=\"$setting\" value=\"".htmlspecialchars(get_setting($setting)).'">';
		echo '</td></tr></table></td></tr>';
	}
	foreach($C['colour_settings'] as $setting){
		thr();
		echo "<tr><td><table id=\"$setting\"><tr><th>".$I[$setting].'</th><td>';
		echo "<input type=\"color\" name=\"$setting\" value=\"#".htmlspecialchars(get_setting($setting)).'">';
		echo '</td></tr></table></td></tr>';
	}
	thr();
	echo "<tr><td><table id=\"captcha\"><tr><th>$I[captcha]</th><td>";
	echo '<table>';
	if(!extension_loaded('gd')){
		echo "<tr><td>$I[gdextrequired]</td></tr>";
	}else{
		echo '<tr><td><select name="dismemcaptcha">';
		$dismemcaptcha=(bool) get_setting('dismemcaptcha');
		echo '<option value="0"';
		if(!$dismemcaptcha){
			echo ' selected';
		}
		echo ">$I[enabled]</option>";
		echo '<option value="1"';
		if($dismemcaptcha){
			echo ' selected';
		}
		echo ">$I[onlyguests]</option>";
		echo '</select></td><td><select name="captcha">';
		$captcha=(int) get_setting('captcha');
		echo '<option value="0"';
		if($captcha===0){
			echo ' selected';
		}
		echo ">$I[disabled]</option>";
		echo '<option value="1"';
		if($captcha===1){
			echo ' selected';
		}
		echo ">$I[simple]</option>";
		echo '<option value="2"';
		if($captcha===2){
			echo ' selected';
		}
		echo ">$I[moderate]</option>";
		echo '<option value="3"';
		if($captcha===3){
			echo ' selected';
		}
		echo ">$I[extreme]</option>";
		echo '</select></td></tr>';
	}
	echo '</table></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table id=\"defaulttz\"><tr><th>$I[defaulttz]</th><td>";
	echo "<select name=\"defaulttz\">";
	$tzs=timezone_identifiers_list();
	$defaulttz=get_setting('defaulttz');
	foreach($tzs as $tz){
		echo "<option value=\"$tz\"";
		if($defaulttz==$tz){
			echo ' selected';
		}
		echo ">$tz</option>";
	}
	echo '</select>';
	echo '</td></tr></table></td></tr>';
	foreach($C['textarea_settings'] as $setting){
		thr();
		echo "<tr><td><table id=\"$setting\"><tr><th>".$I[$setting].'</th><td>';
		echo "<textarea name=\"$setting\" rows=\"4\" cols=\"60\">".htmlspecialchars(get_setting($setting)).'</textarea>';
		echo '</td></tr></table></td></tr>';
	}
	foreach($C['number_settings'] as $setting){
		thr();
		echo "<tr><td><table id=\"$setting\"><tr><th>".$I[$setting].'</th><td>';
		echo "<input type=\"number\" name=\"$setting\" value=\"".htmlspecialchars(get_setting($setting)).'">';
		echo '</td></tr></table></td></tr>';
	}
	foreach($C['bool_settings'] as $setting){
		thr();
		echo "<tr><td><table id=\"$setting\"><tr><th>".$I[$setting].'</th><td>';
		echo "<select name=\"$setting\">";
		$value=(bool) get_setting($setting);
		echo '<option value="0"';
		if(!$value){
			echo ' selected';
		}
		echo ">$I[disabled]</option>";
		echo '<option value="1"';
		if($value){
			echo ' selected';
		}
		echo ">$I[enabled]</option>";
		echo '</select></td></tr>';
		echo '</table></td></tr>';
	}
	thr();
	echo '<tr><td>'.submit($I['apply']).'</td></tr></table></form><br>';
	if($U['status']==8){
		echo '<table id="actions"><tr><td>';
		echo form('setup', 'backup');
		if(!isset($_REQUEST['session'])){
			echo hidden('session', $U['session']);
		}
		echo submit($I['backuprestore']).'</form></td><td>';
		echo form('setup', 'destroy');
		if(!isset($_REQUEST['session'])){
			echo hidden('session', $U['session']);
		}
		echo submit($I['destroy'], 'class="delbutton"').'</form></td></tr></table><br>';
	}
	echo form_target('_parent', 'logout');
	if(!isset($_REQUEST['session'])){
		echo hidden('session', $U['session']);
	}
	echo submit($I['logout'], 'id="exitbutton"').'</form>'.credit();
	print_end();
}

function restore_backup($C){
	global $db, $memcached;
	if(!extension_loaded('json')){
		return;
	}
	$code=json_decode($_REQUEST['restore'], true);
	if(isset($_REQUEST['settings'])){
		foreach($C['settings'] as $setting){
			if(isset($code['settings'][$setting])){
				update_setting($setting, $code['settings'][$setting]);
			}
		}
	}
	if(isset($_REQUEST['filter']) && (isset($code['filters']) || isset($code['linkfilters']))){
		$db->exec('DELETE FROM ' . PREFIX . 'filter;');
		$db->exec('DELETE FROM ' . PREFIX . 'linkfilter;');
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'filter (filtermatch, filterreplace, allowinpm, regex, kick, cs) VALUES (?, ?, ?, ?, ?, ?);');
		foreach($code['filters'] as $filter){
			if(!isset($filter['cs'])){
				$filter['cs']=0;
			}
			$stmt->execute([$filter['match'], $filter['replace'], $filter['allowinpm'], $filter['regex'], $filter['kick'], $filter['cs']]);
		}
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'linkfilter (filtermatch, filterreplace, regex) VALUES (?, ?, ?);');
		foreach($code['linkfilters'] as $filter){
			$stmt->execute([$filter['match'], $filter['replace'], $filter['regex']]);
		}
		if(MEMCACHED){
			$memcached->delete(DBNAME . '-' . PREFIX . 'filter');
			$memcached->delete(DBNAME . '-' . PREFIX . 'linkfilter');
		}
	}
	if(isset($_REQUEST['members']) && isset($code['members'])){
		$db->exec('DELETE FROM ' . PREFIX . 'inbox;');
		$db->exec('DELETE FROM ' . PREFIX . 'members;');
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, regedby, lastlogin, timestamps, embed, incognito, style, nocache, tz, eninbox, sortupdown, hidechatters, nocache_old) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		foreach($code['members'] as $member){
			$new_settings=['nocache', 'tz', 'eninbox', 'sortupdown', 'hidechatters', 'nocache_old'];
			foreach($new_settings as $setting){
				if(!isset($member[$setting])){
					$member[$setting]=0;
				}
			}
			$stmt->execute([$member['nickname'], $member['passhash'], $member['status'], $member['refresh'], $member['bgcolour'], $member['regedby'], $member['lastlogin'], $member['timestamps'], $member['embed'], $member['incognito'], $member['style'], $member['nocache'], $member['tz'], $member['eninbox'], $member['sortupdown'], $member['hidechatters'], $member['nocache_old']]);
		}
	}
	if(isset($_REQUEST['notes']) && isset($code['notes'])){
		$db->exec('DELETE FROM ' . PREFIX . 'notes;');
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'notes (type, lastedited, editedby, text) VALUES (?, ?, ?, ?);');
		foreach($code['notes'] as $note){
			if($note['type']==='admin'){
				$note['type']=0;
			}elseif($note['type']==='staff'){
				$note['type']=1;
			}
			if(MSGENCRYPTED){
                $note['text']=base64_encode(sodium_crypto_aead_aes256gcm_encrypt($note['text'], '', AES_IV, ENCRYPTKEY));
			}
			$stmt->execute([$note['type'], $note['lastedited'], $note['editedby'], $note['text']]);
		}
	}
}

function send_backup($C){
	global $I, $db;
	$code=[];
	if($_REQUEST['do']==='backup'){
		if(isset($_REQUEST['settings'])){
			foreach($C['settings'] as $setting){
				$code['settings'][$setting]=get_setting($setting);
			}
		}
		if(isset($_REQUEST['filter'])){
			$result=$db->query('SELECT * FROM ' . PREFIX . 'filter;');
			while($filter=$result->fetch(PDO::FETCH_ASSOC)){
				$code['filters'][]=['match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'allowinpm'=>$filter['allowinpm'], 'regex'=>$filter['regex'], 'kick'=>$filter['kick'], 'cs'=>$filter['cs']];
			}
			$result=$db->query('SELECT * FROM ' . PREFIX . 'linkfilter;');
			while($filter=$result->fetch(PDO::FETCH_ASSOC)){
				$code['linkfilters'][]=['match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'regex'=>$filter['regex']];
			}
		}
		if(isset($_REQUEST['members'])){
			$result=$db->query('SELECT * FROM ' . PREFIX . 'members;');
			while($member=$result->fetch(PDO::FETCH_ASSOC)){
				$code['members'][]=$member;
			}
		}
		if(isset($_REQUEST['notes'])){
			$result=$db->query('SELECT * FROM ' . PREFIX . "notes;");
			while($note=$result->fetch(PDO::FETCH_ASSOC)){
				if(MSGENCRYPTED){
                    $note['text']=sodium_crypto_aead_aes256gcm_decrypt(base64_decode($note['text']), null, AES_IV, ENCRYPTKEY);
				}
				$code['notes'][]=$note;
			}
		}
	}
	if(isset($_REQUEST['settings'])){
		$chksettings=' checked';
	}else{
		$chksettings='';
	}
	if(isset($_REQUEST['filter'])){
		$chkfilters=' checked';
	}else{
		$chkfilters='';
	}
	if(isset($_REQUEST['members'])){
		$chkmembers=' checked';
	}else{
		$chkmembers='';
	}
	if(isset($_REQUEST['notes'])){
		$chknotes=' checked';
	}else{
		$chknotes='';
	}
	print_start('backup');
	echo "<h2>$I[backuprestore]</h2><table>";
	thr();
	if(!extension_loaded('json')){
		echo "<tr><td>$I[jsonextrequired]</td></tr>";
	}else{
		echo '<tr><td>'.form('setup', 'backup');
		echo '<table id="backup"><tr><td id="backupcheck">';
		echo "<label><input type=\"checkbox\" name=\"settings\" id=\"backupsettings\" value=\"1\"$chksettings>$I[settings]</label>";
		echo "<label><input type=\"checkbox\" name=\"filter\" id=\"backupfilter\" value=\"1\"$chkfilters>$I[filter]</label>";
		echo "<label><input type=\"checkbox\" name=\"members\" id=\"backupmembers\" value=\"1\"$chkmembers>$I[members]</label>";
		echo "<label><input type=\"checkbox\" name=\"notes\" id=\"backupnotes\" value=\"1\"$chknotes>$I[notes]</label>";
		echo '</td><td id="backupsubmit">'.submit($I['backup']).'</td></tr></table></form></td></tr>';
		thr();
		echo '<tr><td>'.form('setup', 'restore');
		echo '<table id="restore">';
		echo "<tr><td colspan=\"2\"><textarea name=\"restore\" rows=\"4\" cols=\"60\">".htmlspecialchars(json_encode($code)).'</textarea></td></tr>';
		echo "<tr><td id=\"restorecheck\"><label><input type=\"checkbox\" name=\"settings\" id=\"restoresettings\" value=\"1\"$chksettings>$I[settings]</label>";
		echo "<label><input type=\"checkbox\" name=\"filter\" id=\"restorefilter\" value=\"1\"$chkfilters>$I[filter]</label>";
		echo "<label><input type=\"checkbox\" name=\"members\" id=\"restoremembers\" value=\"1\"$chkmembers>$I[members]</label>";
		echo "<label><input type=\"checkbox\" name=\"notes\" id=\"restorenotes\" value=\"1\"$chknotes>$I[notes]</label>";
		echo '</td><td id="restoresubmit">'.submit($I['restore']).'</td></tr></table>';
		echo '</form></td></tr>';
	}
	thr();
	echo '<tr><td>'.form('setup').submit($I['initgosetup'], 'class="backbutton"')."</form></tr></td>";
	echo '</table>';
	print_end();
}

function send_destroy_chat(){
	global $I;
	print_start('destroy_chat');
	echo "<table><tr><td colspan=\"2\">$I[confirm]</td></tr><tr><td>";
	echo form_target('_parent', 'setup', 'destroy').hidden('confirm', 'yes').submit($I['yes'], 'class="delbutton"').'</form></td><td>';
	echo form('setup').submit($I['no'], 'class="backbutton"').'</form></td><tr></table>';
	print_end();
}

function send_delete_account(){
	global $I;
	print_start('delete_account');
	echo "<table><tr><td colspan=\"2\">$I[confirm]</td></tr><tr><td>";
	echo form('profile', 'delete').hidden('confirm', 'yes').submit($I['yes'], 'class="delbutton"').'</form></td><td>';
	echo form('profile').submit($I['no'], 'class="backbutton"').'</form></td><tr></table>';
	print_end();
}

function send_init(){
	global $I, $L;
	print_start('init');
	echo "<h2>$I[init]</h2>";
	echo form('init')."<table><tr><td><h3>$I[sulogin]</h3><table>";
	echo "<tr><td>$I[sunick]</td><td><input type=\"text\" name=\"sunick\" size=\"15\"></td></tr>";
	echo "<tr><td>$I[supass]</td><td><input type=\"password\" name=\"supass\" size=\"15\"></td></tr>";
	echo "<tr><td>$I[suconfirm]</td><td><input type=\"password\" name=\"supassc\" size=\"15\"></td></tr>";
	echo '</table></td></tr><tr><td><br>'.submit($I['initbtn']).'</td></tr></table></form>';
	echo "<p id=\"changelang\">$I[changelang]";
	foreach($L as $lang=>$name){
		echo " <a href=\"$_SERVER[SCRIPT_NAME]?action=setup&amp;lang=$lang\">$name</a>";
	}
	echo '</p>'.credit();
	print_end();
}

function send_update($msg){
	global $I;
	print_start('update');
	echo "<h2>$I[dbupdate]</h2><br>".form('setup').submit($I['initgosetup'])."</form>$msg<br>".credit();
	print_end();
}

function send_alogin(){
	global $I, $L;
	print_start('alogin');
	echo form('setup').'<table>';
	echo "<tr><td>$I[nick]</td><td><input type=\"text\" name=\"nick\" size=\"15\" autofocus></td></tr>";
	echo "<tr><td>$I[pass]</td><td><input type=\"password\" name=\"pass\" size=\"15\"></td></tr>";
	send_captcha();
	echo '<tr><td colspan="2">'.submit($I['login']).'</td></tr></table></form>';
	echo "<p id=\"changelang\">$I[changelang]";
	foreach($L as $lang=>$name){
		echo " <a href=\"$_SERVER[SCRIPT_NAME]?action=setup&amp;lang=$lang\">$name</a>";
	}
	echo '</p>'.credit();
	print_end();
}

function send_admin($arg=''){
	global $I, $U, $db;
	$ga=(int) get_setting('guestaccess');
	print_start('admin');
	$chlist="<select name=\"name[]\" size=\"5\" multiple><option value=\"\">$I[choose]</option>";
	$chlist.="<option value=\"s &amp;\">$I[allguests]</option>";
	$users=[];
	$stmt=$db->query('SELECT nickname, style, status FROM ' . PREFIX . 'sessions WHERE entry!=0 AND status>0 ORDER BY LOWER(nickname);');
	while($user=$stmt->fetch(PDO::FETCH_NUM)){
		$users[]=[htmlspecialchars($user[0]), $user[1], $user[2]];
	}
	foreach($users as $user){
		if($user[2]<$U['status']){
			$chlist.="<option value=\"$user[0]\" style=\"$user[1]\">$user[0]</option>";
		}
	}
	$chlist.='</select>';
	echo "<h2>$I[admfunc]</h2><i>$arg</i><table>";
	if($U['status']>=7){
		thr();
		echo '<tr><td>'.form_target('view', 'setup').submit($I['initgosetup']).'</form></td></tr>';
	}
	thr();
	echo "<tr><td><table id=\"clean\"><tr><th>$I[cleanmsgs]</th><td>";
	echo form('admin', 'clean');
	echo '<table><tr><td><label><input type="radio" name="what" id="room" value="room">';
	echo "$I[room]</label></td><td>&nbsp;</td><td><label><input type=\"radio\" name=\"what\" id=\"choose\" value=\"choose\" checked>";
	echo "$I[selection]</label></td><td>&nbsp;</td></tr><tr><td colspan=\"3\"><label><input type=\"radio\" name=\"what\" id=\"nick\" value=\"nick\">";
	echo "$I[cleannick]</label> <select name=\"nickname\" size=\"1\"><option value=\"\">$I[choose]</option>";
	$stmt=$db->prepare('SELECT poster FROM ' . PREFIX . "messages WHERE delstatus<? AND poster!='' GROUP BY poster;");
	$stmt->execute([$U['status']]);
	while($nick=$stmt->fetch(PDO::FETCH_NUM)){
		echo '<option value="'.htmlspecialchars($nick[0]).'">'.htmlspecialchars($nick[0]).'</option>';
	}
	echo '</select></td><td>';
	echo submit($I['clean'], 'class="delbutton"').'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo '<tr><td><table id="kick"><tr><th>'.sprintf($I['kickchat'], get_setting('kickpenalty')).'</th></tr><tr><td>';
	echo form('admin', 'kick');
	echo "<table><tr><td>$I[kickreason]</td><td><input type=\"text\" name=\"kickmessage\" size=\"30\"></td><td>&nbsp;</td></tr>";
	echo "<tr><td><label><input type=\"checkbox\" name=\"what\" value=\"purge\" id=\"purge\">$I[kickpurge]</label></td><td>$chlist</td><td>";
	echo submit($I['kick']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table id=\"logout\"><tr><th>$I[logoutinact]</th><td>";
	echo form('admin', 'logout');
	echo "<table><tr><td>$chlist</td><td>";
	echo submit($I['logout']).'</td></tr></table></form></td></tr></table></td></tr>';
	$views=['sessions', 'filter', 'linkfilter'];
	foreach($views as $view){
		thr();
		echo "<tr><td><table id=\"$view\"><tr><th>".$I[$view].'</th><td>';
		echo form('admin', $view);
		echo submit($I['view']).'</form></td></tr></table></td></tr>';
	}
	thr();
	echo "<tr><td><table id=\"topic\"><tr><th>$I[topic]</th><td>";
	echo form('admin', 'topic');
	echo '<table><tr><td><input type="text" name="topic" size="20" value="'.get_setting('topic').'"></td><td>';
	echo submit($I['change']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table id=\"guestaccess\"><tr><th>$I[guestacc]</th><td>";
	echo form('admin', 'guestaccess');
	echo '<table>';
	echo '<tr><td><select name="guestaccess">';
	echo '<option value="1"';
	if($ga===1){
		echo ' selected';
	}
	echo ">$I[guestallow]</option>";
	echo '<option value="2"';
	if($ga===2){
		echo ' selected';
	}
	echo ">$I[guestwait]</option>";
	echo '<option value="3"';
	if($ga===3){
		echo ' selected';
	}
	echo ">$I[adminallow]</option>";
	echo '<option value="0"';
	if($ga===0){
		echo ' selected';
	}
	echo ">$I[guestdisallow]</option>";
	if($ga===4){
		echo '<option value="4" selected';
		echo ">$I[disablechat]</option>";
	}
	echo '</select></td><td>'.submit($I['change']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	if(get_setting('suguests')){
		echo "<tr><td><table id=\"suguests\"><tr><th>$I[addsuguest]</th><td>";
		echo form('admin', 'superguest');
		echo "<table><tr><td><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($users as $user){
			if($user[2]==1){
				echo "<option value=\"$user[0]\" style=\"$user[1]\">$user[0]</option>";
			}
		}
		echo '</select></td><td>'.submit($I['register']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
	}
	if($U['status']>=7){
		echo "<tr><td><table id=\"status\"><tr><th>$I[admmembers]</th><td>";
		echo form('admin', 'status');
		echo "<table><td><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		$members=[];
		$result=$db->query('SELECT nickname, style, status FROM ' . PREFIX . 'members ORDER BY LOWER(nickname);');
		while($temp=$result->fetch(PDO::FETCH_NUM)){
			$members[]=[htmlspecialchars($temp[0]), $temp[1], $temp[2]];
		}
		foreach($members as $member){
			echo "<option value=\"$member[0]\" style=\"$member[1]\">$member[0]";
			if($member[2]==0){
				echo ' (!)';
			}elseif($member[2]==2){
				echo ' (G)';
			}elseif($member[2]==3){
			}elseif($member[2]==5){
				echo ' (M)';
			}elseif($member[2]==6){
				echo ' (SM)';
			}elseif($member[2]==7){
				echo ' (A)';
			}else{
				echo ' (SA)';
			}
			echo '</option>';
		}
		echo "</select><select name=\"set\" size=\"1\"><option value=\"\">$I[choose]</option><option value=\"-\">$I[memdel]</option><option value=\"0\">$I[memdeny]</option>";
		if(get_setting('suguests')){
			echo "<option value=\"2\">$I[memsuguest]</option>";
		}
		echo "<option value=\"3\">$I[memreg]</option>";
		echo "<option value=\"5\">$I[memmod]</option>";
		echo "<option value=\"6\">$I[memsumod]</option>";
		if($U['status']>=8){
			echo "<option value=\"7\">$I[memadm]</option>";
		}
		echo '</select></td><td>'.submit($I['change']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
		echo "<tr><td><table id=\"passreset\"><tr><th>$I[passreset]</th><td>";
		echo form('admin', 'passreset');
		echo "<table><td><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($members as $member){
			echo "<option value=\"$member[0]\" style=\"$member[1]\">$member[0]</option>";
		}
		echo '</select></td><td><input type="password" name="pass"></td><td>'.submit($I['change']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
		echo "<tr><td><table id=\"register\"><tr><th>$I[regguest]</th><td>";
		echo form('admin', 'register');
		echo "<table><tr><td><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($users as $user){
			if($user[2]==1){
				echo "<option value=\"$user[0]\" style=\"$user[1]\">$user[0]</option>";
			}
		}
		echo '</select></td><td>'.submit($I['register']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
		echo "<tr><td><table id=\"regnew\"><tr><th>$I[regmem]</th></tr><tr><td>";
		echo form('admin', 'regnew');
		echo "<table><tr><td>$I[nick]</td><td>&nbsp;</td><td><input type=\"text\" name=\"name\" size=\"20\"></td><td>&nbsp;</td></tr>";
		echo "<tr><td>$I[pass]</td><td>&nbsp;</td><td><input type=\"password\" name=\"pass\" size=\"20\"></td><td>";
		echo submit($I['register']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
	}
	echo "</table><br>";
	echo form('admin').submit($I['reload']).'</form>';
	print_end();
}

function send_sessions(){
	global $I, $U, $db;
	$stmt=$db->prepare('SELECT nickname, style, lastpost, status, useragent, ip FROM ' . PREFIX . 'sessions WHERE entry!=0 AND (incognito=0 OR status<? OR nickname=?) ORDER BY status DESC, lastpost DESC;');
	$stmt->execute([$U['status'], $U['nickname']]);
	if(!$lines=$stmt->fetchAll(PDO::FETCH_ASSOC)){
		$lines=[];
	}
	print_start('sessions');
	echo "<h1>$I[sessact]</h1><table>";
	echo "<tr><th>$I[sessnick]</th><th>$I[sesstimeout]</th><th>$I[sessua]</th>";
	$trackip=(bool) get_setting('trackip');
	$memexpire=(int) get_setting('memberexpire');
	$guestexpire=(int) get_setting('guestexpire');
	if($trackip) echo "<th>$I[sesip]</th>";
	echo "<th>$I[actions]</th></tr>";
	foreach($lines as $temp){
		if($temp['status']==0){
			$s=' (K)';
		}elseif($temp['status']<=2){
			$s=' (G)';
		}elseif($temp['status']==3){
			$s='';
		}elseif($temp['status']==5){
			$s=' (M)';
		}elseif($temp['status']==6){
			$s=' (SM)';
		}elseif($temp['status']==7){
			$s=' (A)';
		}else{
			$s=' (SA)';
		}
		echo '<tr><td class="nickname">'.style_this(htmlspecialchars($temp['nickname']).$s, $temp['style']).'</td><td class="timeout">';
		if($temp['status']>2){
			get_timeout($temp['lastpost'], $memexpire);
		}else{
			get_timeout($temp['lastpost'], $guestexpire);
		}
		echo '</td>';
		if($U['status']>$temp['status'] || $U['nickname']===$temp['nickname']){
			echo "<td class=\"ua\">$temp[useragent]</td>";
			if($trackip){
				echo "<td class=\"ip\">$temp[ip]</td>";
			}
			echo '<td class="action">';
			if($temp['nickname']!==$U['nickname']){
				echo '<table><tr>';
				if($temp['status']!=0){
					echo '<td>';
					echo form('admin', 'sessions');
					echo hidden('kick', '1').hidden('nick', htmlspecialchars($temp['nickname'])).submit($I['kick']).'</form>';
					echo '</td>';
				}
				echo '<td>';
				echo form('admin', 'sessions');
				echo hidden('logout', '1').hidden('nick', htmlspecialchars($temp['nickname'])).submit($temp['status']==0 ? $I['unban'] : $I['logout']).'</form>';
				echo '</td></tr></table>';
			}else{
				echo '-';
			}
			echo '</td></tr>';
		}else{
			echo '<td class="ua">-</td>';
			if($trackip){
				echo '<td class="ip">-</td>';
			}
			echo '<td class="action">-</td></tr>';
		}
	}
	echo "</table><br>";
	echo form('admin', 'sessions').submit($I['reload']).'</form>';
	print_end();
}

function check_filter_match(&$reg){
	global $I;
	$_REQUEST['match']=htmlspecialchars($_REQUEST['match']);
	if(isset($_REQUEST['regex']) && $_REQUEST['regex']==1){
		if(!valid_regex($_REQUEST['match'])){
			return "$I[incorregex]<br>$I[prevmatch]: $_REQUEST[match]";
		}
		$reg=1;
	}else{
		$_REQUEST['match']=preg_replace('/([^\w\d])/u', "\\\\$1", $_REQUEST['match']);
		$reg=0;
	}
	if(mb_strlen($_REQUEST['match'])>255){
		return "$I[matchtoolong]<br>$I[prevmatch]: $_REQUEST[match]";
	}
	return false;
}

function manage_filter(){
	global $db, $memcached;
	if(isset($_REQUEST['id'])){
		$reg=0;
		if($tmp=check_filter_match($reg)){
			return $tmp;
		}
		if(isset($_REQUEST['allowinpm']) && $_REQUEST['allowinpm']==1){
			$pm=1;
		}else{
			$pm=0;
		}
		if(isset($_REQUEST['kick']) && $_REQUEST['kick']==1){
			$kick=1;
		}else{
			$kick=0;
		}
		if(isset($_REQUEST['cs']) && $_REQUEST['cs']==1){
			$cs=1;
		}else{
			$cs=0;
		}
		if(preg_match('/^[0-9]+$/', $_REQUEST['id'])){
			if(empty($_REQUEST['match'])){
				$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'filter WHERE id=?;');
				$stmt->execute([$_REQUEST['id']]);
			}else{
				$stmt=$db->prepare('UPDATE ' . PREFIX . 'filter SET filtermatch=?, filterreplace=?, allowinpm=?, regex=?, kick=?, cs=? WHERE id=?;');
				$stmt->execute([$_REQUEST['match'], $_REQUEST['replace'], $pm, $reg, $kick, $cs, $_REQUEST['id']]);
			}
		}elseif($_REQUEST['id']==='+'){
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'filter (filtermatch, filterreplace, allowinpm, regex, kick, cs) VALUES (?, ?, ?, ?, ?, ?);');
			$stmt->execute([$_REQUEST['match'], $_REQUEST['replace'], $pm, $reg, $kick, $cs]);
		}
		if(MEMCACHED){
			$memcached->delete(DBNAME . '-' . PREFIX . 'filter');
		}
	}
}

function manage_linkfilter(){
	global $db, $memcached;
	if(isset($_REQUEST['id'])){
		$reg=0;
		if($tmp=check_filter_match($reg)){
			return $tmp;
		}
		if(preg_match('/^[0-9]+$/', $_REQUEST['id'])){
			if(empty($_REQUEST['match'])){
				$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'linkfilter WHERE id=?;');
				$stmt->execute([$_REQUEST['id']]);
			}else{
				$stmt=$db->prepare('UPDATE ' . PREFIX . 'linkfilter SET filtermatch=?, filterreplace=?, regex=? WHERE id=?;');
				$stmt->execute([$_REQUEST['match'], $_REQUEST['replace'], $reg, $_REQUEST['id']]);
			}
		}elseif($_REQUEST['id']==='+'){
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'linkfilter (filtermatch, filterreplace, regex) VALUES (?, ?, ?);');
			$stmt->execute([$_REQUEST['match'], $_REQUEST['replace'], $reg]);
		}
		if(MEMCACHED){
			$memcached->delete(DBNAME . '-' . PREFIX . 'linkfilter');
		}
	}
}

function get_filters(){
	global $db, $memcached;
	if(MEMCACHED){
		$filters=$memcached->get(DBNAME . '-' . PREFIX . 'filter');
	}
	if(!MEMCACHED || $memcached->getResultCode()!==Memcached::RES_SUCCESS){
		$filters=[];
		$result=$db->query('SELECT id, filtermatch, filterreplace, allowinpm, regex, kick, cs FROM ' . PREFIX . 'filter;');
		while($filter=$result->fetch(PDO::FETCH_ASSOC)){
			$filters[]=['id'=>$filter['id'], 'match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'allowinpm'=>$filter['allowinpm'], 'regex'=>$filter['regex'], 'kick'=>$filter['kick'], 'cs'=>$filter['cs']];
		}
		if(MEMCACHED){
			$memcached->set(DBNAME . '-' . PREFIX . 'filter', $filters);
		}
	}
	return $filters;
}

function get_linkfilters(){
	global $db, $memcached;
	if(MEMCACHED){
		$filters=$memcached->get(DBNAME . '-' . PREFIX . 'linkfilter');
	}
	if(!MEMCACHED || $memcached->getResultCode()!==Memcached::RES_SUCCESS){
		$filters=[];
		$result=$db->query('SELECT id, filtermatch, filterreplace, regex FROM ' . PREFIX . 'linkfilter;');
		while($filter=$result->fetch(PDO::FETCH_ASSOC)){
			$filters[]=['id'=>$filter['id'], 'match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'regex'=>$filter['regex']];
		}
		if(MEMCACHED){
			$memcached->set(DBNAME . '-' . PREFIX . 'linkfilter', $filters);
		}
	}
	return $filters;
}

function send_filter($arg=''){
	global $I, $U;
	print_start('filter');
	echo "<h2>$I[filter]</h2><i>$arg</i><table>";
	thr();
	echo '<tr><th><table style="width:100%;"><tr>';
	echo "<td style=\"width:8em;\">$I[fid]</td>";
	echo "<td style=\"width:12em;\">$I[match]</td>";
	echo "<td style=\"width:12em;\">$I[replace]</td>";
	echo "<td style=\"width:9em;\">$I[allowpm]</td>";
	echo "<td style=\"width:5em;\">$I[regex]</td>";
	echo "<td style=\"width:5em;\">$I[kick]</td>";
	echo "<td style=\"width:5em;\">$I[cs]</td>";
	echo "<td style=\"width:5em;\">$I[apply]</td>";
	echo '</tr></table></th></tr>';
	$filters=get_filters();
	foreach($filters as $filter){
		if($filter['allowinpm']==1){
			$check=' checked';
		}else{
			$check='';
		}
		if($filter['regex']==1){
			$checked=' checked';
		}else{
			$checked='';
			$filter['match']=preg_replace('/(\\\\(.))/u', "$2", $filter['match']);
		}
		if($filter['kick']==1){
			$checkedk=' checked';
		}else{
			$checkedk='';
		}
		if($filter['cs']==1){
			$checkedcs=' checked';
		}else{
			$checkedcs='';
		}
		echo '<tr><td>';
		echo form('admin', 'filter').hidden('id', $filter['id']);
		echo "<table style=\"width:100%;\"><tr><th style=\"width:8em;\">$I[filter] $filter[id]:</th>";
		echo "<td style=\"width:12em;\"><input type=\"text\" name=\"match\" value=\"$filter[match]\" size=\"20\" style=\"$U[style]\"></td>";
		echo '<td style="width:12em;"><input type="text" name="replace" value="'.htmlspecialchars($filter['replace'])."\" size=\"20\" style=\"$U[style]\"></td>";
		echo "<td style=\"width:9em;\"><label><input type=\"checkbox\" name=\"allowinpm\" value=\"1\"$check>$I[allowpm]</label></td>";
		echo "<td style=\"width:5em;\"><label><input type=\"checkbox\" name=\"regex\" value=\"1\"$checked>$I[regex]</label></td>";
		echo "<td style=\"width:5em;\"><label><input type=\"checkbox\" name=\"kick\" value=\"1\"$checkedk>$I[kick]</label></td>";
		echo "<td style=\"width:5em;\"><label><input type=\"checkbox\" name=\"cs\" value=\"1\"$checkedcs>$I[cs]</label></td>";
		echo '<td class="filtersubmit" style="width:5em;">'.submit($I['change']).'</td></tr></table></form></td></tr>';
	}
	echo '<tr><td>';
	echo form('admin', 'filter').hidden('id', '+');
	echo "<table style=\"width:100%;\"><tr><th style=\"width:8em\">$I[newfilter]</th>";
	echo "<td style=\"width:12em;\"><input type=\"text\" name=\"match\" value=\"\" size=\"20\" style=\"$U[style]\"></td>";
	echo "<td style=\"width:12em;\"><input type=\"text\" name=\"replace\" value=\"\" size=\"20\" style=\"$U[style]\"></td>";
	echo "<td style=\"width:9em;\"><label><input type=\"checkbox\" name=\"allowinpm\" id=\"allowinpm\" value=\"1\">$I[allowpm]</label></td>";
	echo "<td style=\"width:5em;\"><label><input type=\"checkbox\" name=\"regex\" id=\"regex\" value=\"1\">$I[regex]</label></td>";
	echo "<td style=\"width:5em;\"><label><input type=\"checkbox\" name=\"kick\" id=\"kick\" value=\"1\">$I[kick]</label></td>";
	echo "<td style=\"width:5em;\"><label><input type=\"checkbox\" name=\"cs\" id=\"cs\" value=\"1\">$I[cs]</label></td>";
	echo '<td class="filtersubmit" style="width:5em;">'.submit($I['add']).'</td></tr></table></form></td></tr>';
	echo "</table><br>";
	echo form('admin', 'filter').submit($I['reload']).'</form>';
	print_end();
}

function send_linkfilter($arg=''){
	global $I, $U;
	print_start('linkfilter');
	echo "<h2>$I[linkfilter]</h2><i>$arg</i><table>";
	thr();
	echo '<tr><th><table style="width:100%;"><tr>';
	echo "<td style=\"width:8em;\">$I[fid]</td>";
	echo "<td style=\"width:12em;\">$I[match]</td>";
	echo "<td style=\"width:12em;\">$I[replace]</td>";
	echo "<td style=\"width:5em;\">$I[regex]</td>";
	echo "<td style=\"width:5em;\">$I[apply]</td>";
	echo '</tr></table></th></tr>';
	$filters=get_linkfilters();
	foreach($filters as $filter){
		if($filter['regex']==1){
			$checked=' checked';
		}else{
			$checked='';
			$filter['match']=preg_replace('/(\\\\(.))/u', "$2", $filter['match']);
		}
		echo '<tr><td>';
		echo form('admin', 'linkfilter').hidden('id', $filter['id']);
		echo "<table style=\"width:100%;\"><tr><th style=\"width:8em;\">$I[filter] $filter[id]:</th>";
		echo "<td style=\"width:12em;\"><input type=\"text\" name=\"match\" value=\"$filter[match]\" size=\"20\" style=\"$U[style]\"></td>";
		echo '<td style="width:12em;"><input type="text" name="replace" value="'.htmlspecialchars($filter['replace'])."\" size=\"20\" style=\"$U[style]\"></td>";
		echo "<td style=\"width:5em;\"><label><input type=\"checkbox\" name=\"regex\" value=\"1\"$checked>$I[regex]</label></td>";
		echo '<td class="filtersubmit" style="width:5em;">'.submit($I['change']).'</td></tr></table></form></td></tr>';
	}
	echo '<tr><td>';
	echo form('admin', 'linkfilter').hidden('id', '+');
	echo "<table style=\"width:100%;\"><tr><th style=\"width:8em;\">$I[newfilter]</th>";
	echo "<td style=\"width:12em;\"><input type=\"text\" name=\"match\" value=\"\" size=\"20\" style=\"$U[style]\"></td>";
	echo "<td style=\"width:12em;\"><input type=\"text\" name=\"replace\" value=\"\" size=\"20\" style=\"$U[style]\"></td>";
	echo "<td style=\"width:5em;\"><label><input type=\"checkbox\" name=\"regex\" value=\"1\">$I[regex]</label></td>";
	echo '<td class="filtersubmit" style="width:5em;">'.submit($I['add']).'</td></tr></table></form></td></tr>';
	echo "</table><br>";
	echo form('admin', 'linkfilter').submit($I['reload']).'</form>';
	print_end();
}

function send_frameset(){
	global $I, $U, $db, $language;
	echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Frameset//EN" "http://www.w3.org/TR/html4/frameset.dtd"><html><head>'.meta_html();
	echo '<title>'.get_setting('chatname').'</title>';
	print_stylesheet();
	echo '</head>';
	if(isset($_REQUEST['sort'])){
		if($_REQUEST['sort']==1){
			$U['sortupdown']=1;
			$tmp=$U['nocache'];
			$U['nocache']=$U['nocache_old'];
			$U['nocache_old']=$tmp;
		}else{
			$U['sortupdown']=0;
			$tmp=$U['nocache'];
			$U['nocache']=$U['nocache_old'];
			$U['nocache_old']=$tmp;
		}
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET sortupdown=?, nocache=?, nocache_old=? WHERE nickname=?;');
		$stmt->execute([$U['sortupdown'], $U['nocache'], $U['nocache_old'], $U['nickname']]);
		if($U['status']>1){
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET sortupdown=?, nocache=?, nocache_old=? WHERE nickname=?;');
			$stmt->execute([$U['sortupdown'], $U['nocache'], $U['nocache_old'], $U['nickname']]);
		}
	}
	if($U['sortupdown']){
		$bottom='#bottom';
	}else{
		$bottom='';
	}
	if(($U['status']>=5 || ($U['status']>2 && get_count_mods()==0)) && get_setting('enfileupload')>0 && get_setting('enfileupload')<=$U['status']){
		$postheight=120;
	}else{
		$postheight=100;
	}
	if((!isset($_REQUEST['sort']) && !$U['sortupdown']) || (isset($_REQUEST['sort']) && $_REQUEST['sort']==0)){
		echo "<frameset rows=\"$postheight,*,45\" border=\"3\" frameborder=\"3\" framespacing=\"3\">";
		echo "<frame name=\"post\" src=\"$_SERVER[SCRIPT_NAME]?action=post&session=$U[session]&lang=$language\">";
		if(get_setting('enablegreeting')){
			echo "<frame name=\"view\" src=\"$_SERVER[SCRIPT_NAME]?action=greeting&session=$U[session]&lang=$language\">";
		}else{
			echo "<frame name=\"view\" src=\"$_SERVER[SCRIPT_NAME]?action=view&session=$U[session]&lang=$language$bottom\">";
		}
		echo "<frame name=\"controls\" src=\"$_SERVER[SCRIPT_NAME]?action=controls&session=$U[session]&lang=$language&sort=1\">";
	}else{
		echo "<frameset rows=\"45,*,$postheight\" border=\"3\" frameborder=\"3\" framespacing=\"3\">";
		echo "<frame name=\"controls\" src=\"$_SERVER[SCRIPT_NAME]?action=controls&session=$U[session]&lang=$language&sort=0\">";
		if(get_setting('enablegreeting')){
			echo "<frame name=\"view\" src=\"$_SERVER[SCRIPT_NAME]?action=greeting&session=$U[session]&lang=$language\">";
		}else{
			echo "<frame name=\"view\" src=\"$_SERVER[SCRIPT_NAME]?action=view&session=$U[session]&lang=$language$bottom\">";
		}
		echo "<frame name=\"post\" src=\"$_SERVER[SCRIPT_NAME]?action=post&session=$U[session]&lang=$language\">";
	}
	echo "<noframes><body>$I[noframes]".form_target('_parent', '').submit($I['backtologin'], 'class="backbutton"').'</form></body></noframes></frameset></html>';
	exit;
}

function send_messages(){
	global $I, $U, $language;
	if($U['nocache']){
		$nocache='&nc='.substr(time(), -6);
	}else{
		$nocache='';
	}
	if($U['sortupdown']){
		$sort='#bottom';
	}else{
		$sort='';
	}
	print_start('messages', $U['refresh'], "$_SERVER[SCRIPT_NAME]?action=view&session=$U[session]&lang=$language$nocache$sort");
	echo '<a id="top"></a>';
	echo "<a id=\"bottom_link\" href=\"#bottom\">$I[bottom]</a>";
	echo "<div id=\"manualrefresh\"><br>$I[manualrefresh]<br>".form('view').submit($I['reload']).'</form><br></div>';
	if(!$U['sortupdown']){
		echo '<div id="topic">';
		echo get_setting('topic');
		echo '</div>';
		print_chatters();
		print_notifications();
		print_messages();
	}else{
		print_messages();
		print_notifications();
		print_chatters();
		echo '<div id="topic">';
		echo get_setting('topic');
		echo '</div>';
	}
	echo "<a id=\"bottom\"></a><a id=\"top_link\" href=\"#top\">$I[top]</a>";
	print_end();
}

function send_inbox(){
	global $I, $U, $db;
	print_start('inbox');
	echo form('inbox', 'clean').submit($I['delselmes'], 'class="delbutton"').'<br><br>';
	$dateformat=get_setting('dateformat');
	if(!$U['embed'] && get_setting('imgembed')){
		$removeEmbed=true;
	}else{
		$removeEmbed=false;
	}
	if($U['timestamps'] && !empty($dateformat)){
		$timestamps=true;
	}else{
		$timestamps=false;
	}
	if($U['sortupdown']){
		$direction='ASC';
	}else{
		$direction='DESC';
	}
	$stmt=$db->prepare('SELECT id, postdate, text FROM ' . PREFIX . "inbox WHERE recipient=? ORDER BY id $direction;");
	$stmt->execute([$U['nickname']]);
	while($message=$stmt->fetch(PDO::FETCH_ASSOC)){
		prepare_message_print($message, $removeEmbed);
		echo "<div class=\"msg\"><label><input type=\"checkbox\" name=\"mid[]\" value=\"$message[id]\">";
		if($timestamps){
			echo ' <small>'.date($dateformat, $message['postdate']).' - </small>';
		}
		echo " $message[text]</label></div>";
	}
	echo '</form><br>'.form('view').submit($I['backtochat'], 'class="backbutton"').'</form>';
	print_end();
}

function send_notes($type){
	global $I, $U, $db;
	print_start('notes');
	$personalnotes=(bool) get_setting('personalnotes');
	if($U['status']>=5 && ($personalnotes || $U['status']>6)){
		echo '<table><tr>';
		if($U['status']>6){
			echo '<td>'.form_target('view', 'notes', 'admin').submit($I['admnotes']).'</form></td>';
		}
		echo '<td>'.form_target('view', 'notes', 'staff').submit($I['staffnotes']).'</form></td>';
		if($personalnotes){
			echo '<td>'.form_target('view', 'notes').submit($I['personalnotes']).'</form></td>';
		}
		echo '</tr></table>';
	}
	if($type===1){
		echo "<h2>$I[staffnotes]</h2><p>";
		$hiddendo=hidden('do', 'staff');
	}elseif($type===0){
		echo "<h2>$I[adminnotes]</h2><p>";
		$hiddendo=hidden('do', 'admin');
	}else{
		echo "<h2>$I[personalnotes]</h2><p>";
		$hiddendo='';
	}
	if(isset($_REQUEST['text'])){
		if(MSGENCRYPTED){
            $_REQUEST['text']=base64_encode(sodium_crypto_aead_aes256gcm_encrypt($_REQUEST['text'], '', AES_IV, ENCRYPTKEY));
		}
		$time=time();
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'notes (type, lastedited, editedby, text) VALUES (?, ?, ?, ?);');
		$stmt->execute([$type, $time, $U['nickname'], $_REQUEST['text']]);
		echo "<b>$I[notessaved]</b> ";
	}
	$dateformat=get_setting('dateformat');
	if($type!==2){
		$stmt=$db->prepare('SELECT COUNT(*) FROM ' . PREFIX . 'notes WHERE type=?;');
		$stmt->execute([$type]);
	}else{
		$stmt=$db->prepare('SELECT COUNT(*) FROM ' . PREFIX . 'notes WHERE type=? AND editedby=?;');
		$stmt->execute([$type, $U['nickname']]);
	}
	$num=$stmt->fetch(PDO::FETCH_NUM);
	if(!empty($_REQUEST['revision'])){
		$revision=intval($_REQUEST['revision']);
	}else{
		$revision=0;
	}
	if($type!==2){
		$stmt=$db->prepare('SELECT * FROM ' . PREFIX . "notes WHERE type=? ORDER BY id DESC LIMIT 1 OFFSET $revision;");
		$stmt->execute([$type]);
	}else{
		$stmt=$db->prepare('SELECT * FROM ' . PREFIX . "notes WHERE type=? AND editedby=? ORDER BY id DESC LIMIT 1 OFFSET $revision;");
		$stmt->execute([$type, $U['nickname']]);
	}
	if($note=$stmt->fetch(PDO::FETCH_ASSOC)){
		printf($I['lastedited'], htmlspecialchars($note['editedby']), date($dateformat, $note['lastedited']));
	}else{
		$note['text']='';
	}
	if(MSGENCRYPTED){
        $note['text']=sodium_crypto_aead_aes256gcm_decrypt(base64_decode($note['text']), null, AES_IV, ENCRYPTKEY);
	}
	echo "</p>".form('notes');
	echo "$hiddendo<textarea name=\"text\">".htmlspecialchars($note['text']).'</textarea><br>';
	echo submit($I['savenotes']).'</form><br>';
	if($num[0]>1){
		echo "<br><table><tr><td>$I[revisions]</td>";
		if($revision<$num[0]-1){
			echo '<td>'.form('notes').hidden('revision', $revision+1);
			echo $hiddendo.submit($I['older']).'</form></td>';
		}
		if($revision>0){
			echo '<td>'.form('notes').hidden('revision', $revision-1);
			echo $hiddendo.submit($I['newer']).'</form></td>';
		}
		echo '</tr></table>';
	}
	print_end();
}

function send_approve_waiting(){
	global $I, $db;
	print_start('approve_waiting');
	echo "<h2>$I[waitingroom]</h2>";
	$result=$db->query('SELECT * FROM ' . PREFIX . 'sessions WHERE entry=0 AND status=1 ORDER BY id LIMIT 100;');
	if($tmp=$result->fetchAll(PDO::FETCH_ASSOC)){
		echo form('admin', 'approve');
		echo '<table>';
		echo "<tr><th>$I[sessnick]</th><th>$I[sessua]</th></tr>";
		foreach($tmp as $temp){
			echo '<tr>'.hidden('alls[]', htmlspecialchars($temp['nickname']));
			echo '<td><label><input type="checkbox" name="csid[]" value="'.htmlspecialchars($temp['nickname']).'">';
			echo style_this(htmlspecialchars($temp['nickname']), $temp['style']).'</label></td>';
			echo "<td>$temp[useragent]</td></tr>";
		}
		echo "</table><br><table id=\"action\"><tr><td><label><input type=\"radio\" name=\"what\" value=\"allowchecked\" id=\"allowchecked\" checked>$I[allowchecked]</label></td>";
		echo "<td><label><input type=\"radio\" name=\"what\" value=\"allowall\" id=\"allowall\">$I[allowall]</label></td>";
		echo "<td><label><input type=\"radio\" name=\"what\" value=\"denychecked\" id=\"denychecked\">$I[denychecked]</label></td>";
		echo "<td><label><input type=\"radio\" name=\"what\" value=\"denyall\" id=\"denyall\">$I[denyall]</label></td></tr><tr><td colspan=\"8\">$I[denymessage] <input type=\"text\" name=\"kickmessage\" size=\"45\"></td>";
		echo '</tr><tr><td colspan="8">'.submit($I['butallowdeny']).'</td></tr></table></form>';
	}else{
		echo "$I[waitempty]<br>";
	}
	echo '<br>'.form('view').submit($I['backtochat'], 'class="backbutton"').'</form>';
	print_end();
}

function send_waiting_room(){
	global $I, $U, $db, $language;
	$ga=(int) get_setting('guestaccess');
	if($ga===3 && (get_count_mods()>0 || !get_setting('modfallback'))){
		$wait=false;
	}else{
		$wait=true;
	}
	check_expired();
	check_kicked();
	$timeleft=get_setting('entrywait')-(time()-$U['lastpost']);
	if($wait && ($timeleft<=0 || $ga===1)){
		$U['entry']=$U['lastpost'];
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET entry=lastpost WHERE session=?;');
		$stmt->execute([$U['session']]);
		send_frameset();
	}elseif(!$wait && $U['entry']!=0){
		send_frameset();
	}else{
		$refresh=(int) get_setting('defaultrefresh');
		print_start('waitingroom', $refresh, "$_SERVER[SCRIPT_NAME]?action=wait&session=$U[session]&lang=$language&nc=".substr(time(),-6));
		echo "<h2>$I[waitingroom]</h2><p>";
		if($wait){
			printf($I['waittext'], style_this(htmlspecialchars($U['nickname']), $U['style']), $timeleft);
		}else{
			printf($I['admwaittext'], style_this(htmlspecialchars($U['nickname']), $U['style']));
		}
		echo '</p><br><p>';
		printf($I['waitreload'], $refresh);
		echo '</p><br><br>';
		echo '<hr>'.form('wait');
		if(!isset($_REQUEST['session'])){
			echo hidden('session', $U['session']);
		}
		echo submit($I['reload']).'</form><br>';
		echo form('logout');
		if(!isset($_REQUEST['session'])){
			echo hidden('session', $U['session']);
		}
		echo submit($I['exit'], 'id="exitbutton"').'</form>';
		$rulestxt=get_setting('rulestxt');
		if(!empty($rulestxt)){
			echo "<div id=\"rules\"><h2>$I[rules]</h2><b>$rulestxt</b></div>";
		}
		print_end();
	}
}

function send_choose_messages(){
	global $I, $U;
	print_start('choose_messages');
	echo form('admin', 'clean');
	echo hidden('what', 'selected').submit($I['delselmes'], 'class="delbutton"').'<br><br>';
	print_messages($U['status']);
	echo '<br>'.submit($I['delselmes'], 'class="delbutton"')."</form>";
	print_end();
}

function send_del_confirm(){
	global $I;
	print_start('del_confirm');
	echo "<table><tr><td colspan=\"2\">$I[confirm]</td></tr><tr><td>".form('delete');
	if(isset($_REQUEST['multi'])){
		echo hidden('multi', 'on');
	}
	if(isset($_REQUEST['sendto'])){
		echo hidden('sendto', $_REQUEST['sendto']);
	}
	echo hidden('confirm', 'yes').hidden('what', $_REQUEST['what']).submit($I['yes'], 'class="delbutton"').'</form></td><td>'.form('post');
	if(isset($_REQUEST['multi'])){
		echo hidden('multi', 'on');
	}
	if(isset($_REQUEST['sendto'])){
		echo hidden('sendto', $_REQUEST['sendto']);
	}
	echo submit($I['no'], 'class="backbutton"').'</form></td><tr></table>';
	print_end();
}

function send_post($rejected=''){
	global $I, $U, $db;
	print_start('post');
	if(!isset($_REQUEST['sendto'])){
		$_REQUEST['sendto']='';
	}
	echo '<table><tr><td>'.form('post');
	echo hidden('postid', substr(time(), -6));
	if(isset($_REQUEST['multi'])){
		echo hidden('multi', 'on');
	}
	echo '<table><tr><td><table><tr id="firstline"><td>'.style_this(htmlspecialchars($U['nickname']), $U['style']).'</td><td>:</td>';
	if(isset($_REQUEST['multi'])){
		echo "<td><textarea name=\"message\" rows=\"3\" cols=\"40\" style=\"$U[style]\" autofocus>$rejected</textarea></td>";
	}else{
		echo "<td><input type=\"text\" name=\"message\" value=\"$rejected\" size=\"40\" style=\"$U[style]\" autofocus></td>";
	}
	echo '<td>'.submit($I['talkto']).'</td><td><select name="sendto" size="1">';
	echo '<option ';
	if($_REQUEST['sendto']==='s *'){
		echo 'selected ';
	}
	echo "value=\"s *\">-$I[toall]-</option>";
	if($U['status']>=3){
		echo '<option ';
		if($_REQUEST['sendto']==='s ?'){
			echo 'selected ';
		}
		echo "value=\"s ?\">-$I[tomem]-</option>";
	}
	if($U['status']>=5){
		echo '<option ';
		if($_REQUEST['sendto']==='s #'){
			echo 'selected ';
		}
		echo "value=\"s #\">-$I[tostaff]-</option>";
	}
	if($U['status']>=6){
		echo '<option ';
		if($_REQUEST['sendto']==='s &'){
			echo 'selected ';
		}
		echo "value=\"s &amp;\">-$I[toadmin]-</option>";
	}
	$disablepm=(bool) get_setting('disablepm');
	if(!$disablepm){
		$users=[];
		$stmt=$db->prepare('SELECT * FROM (SELECT nickname, style, 0 AS offline FROM ' . PREFIX . 'sessions WHERE entry!=0 AND status>0 AND incognito=0 UNION SELECT nickname, style, 1 AS offline FROM ' . PREFIX . 'members WHERE eninbox!=0 AND eninbox<=? AND nickname NOT IN (SELECT nickname FROM ' . PREFIX . 'sessions WHERE incognito=0)) AS t WHERE nickname NOT IN (SELECT ign FROM '. PREFIX . 'ignored WHERE ignby=? UNION SELECT ignby FROM '. PREFIX . 'ignored WHERE ign=?) ORDER BY LOWER(nickname);');
		$stmt->execute([$U['status'], $U['nickname'], $U['nickname']]);
		while($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
			if($tmp['offline']){
				$users[]=["$tmp[nickname] $I[offline]", $tmp['style'], $tmp['nickname']];
			}else{
				$users[]=[$tmp['nickname'], $tmp['style'], $tmp['nickname']];
			}
		}
		foreach($users as $user){
			if($U['nickname']!==$user[2]){
				echo '<option ';
				if($_REQUEST['sendto']==$user[2]){
					echo 'selected ';
				}
				echo 'value="'.htmlspecialchars($user[2])."\" style=\"$user[1]\">".htmlspecialchars($user[0]).'</option>';
			}
		}
	}
	echo '</select></td>';
	if(get_setting('enfileupload')>0 && get_setting('enfileupload')<=$U['status']){
		if(!$disablepm && ($U['status']>=5 || ($U['status']>=3 && get_count_mods()==0 && get_setting('memkick')))){
			echo '</tr></table><table><tr id="secondline">';
		}
		printf("<td><input type=\"file\" name=\"file\"><small>$I[maxsize]</small></td>", get_setting('maxuploadsize'));
	}
	if(!$disablepm && ($U['status']>=5 || ($U['status']>=3 && get_count_mods()==0 && get_setting('memkick')))){
		echo "<td><label><input type=\"checkbox\" name=\"kick\" id=\"kick\" value=\"kick\">$I[kick]</label></td>";
		echo "<td><label><input type=\"checkbox\" name=\"what\" id=\"what\" value=\"purge\" checked>$I[alsopurge]</label></td>";
	}
	echo '</tr></table></td></tr></table></form></td></tr><tr><td><table><tr id="thirdline"><td>'.form('delete');
	if(isset($_REQUEST['multi'])){
		echo hidden('multi', 'on');
	}
	echo hidden('sendto', $_REQUEST['sendto']).hidden('what', 'last');
	echo submit($I['dellast'], 'class="delbutton"').'</form></td><td>'.form('delete');
	if(isset($_REQUEST['multi'])){
		echo hidden('multi', 'on');
	}
	echo hidden('sendto', $_REQUEST['sendto']).hidden('what', 'all');
	echo submit($I['delall'], 'class="delbutton"').'</form></td><td style="width:10px;"></td><td>'.form('post');
	if(isset($_REQUEST['multi'])){
		echo submit($I['switchsingle']);
	}else{
		echo hidden('multi', 'on').submit($I['switchmulti']);
	}
	echo hidden('sendto', $_REQUEST['sendto']).'</form></td>';
	echo '</tr></table></td></tr></table>';
	print_end();
}

function send_greeting(){
	global $I, $U, $language;
	print_start('greeting', $U['refresh'], "$_SERVER[SCRIPT_NAME]?action=view&session=$U[session]&lang=$language");
	printf("<h1>$I[greetingmsg]</h1>", style_this(htmlspecialchars($U['nickname']), $U['style']));
	printf("<hr><small>$I[entryhelp]</small>", $U['refresh']);
	$rulestxt=get_setting('rulestxt');
	if(!empty($rulestxt)){
		echo "<hr><div id=\"rules\"><h2>$I[rules]</h2>$rulestxt</div>";
	}
	print_end();
}

function send_help(){
	global $I, $U;
	print_start('help');
	$rulestxt=get_setting('rulestxt');
	if(!empty($rulestxt)){
		echo "<div id=\"rules\"><h2>$I[rules]</h2>$rulestxt<br></div><hr>";
	}
	echo "<h2>$I[help]</h2>$I[helpguest]";
	if(get_setting('imgembed')){
		echo "<br>$I[helpembed]";
	}
	if($U['status']>=3){
		echo "<br>$I[helpmem]<br>";
		if($U['status']>=5){
			echo "<br>$I[helpmod]<br>";
			if($U['status']>=7){
				echo "<br>$I[helpadm]<br>";
			}
		}
	}
	echo '<br><hr><div id="backcredit">'.form('view').submit($I['backtochat'], 'class="backbutton"').'</form>'.credit().'</div>';
	print_end();
}

function send_profile($arg=''){
	global $I, $L, $U, $db, $language;
	print_start('profile');
	echo form('profile', 'save')."<h2>$I[profile]</h2><i>$arg</i><table>";
	thr();
	$ignored=[];
	$stmt=$db->prepare('SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=? ORDER BY LOWER(ign);');
	$stmt->execute([$U['nickname']]);
	while($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
		$ignored[]=htmlspecialchars($tmp['ign']);
	}
	if(count($ignored)>0){
		echo "<tr><td><table id=\"unignore\"><tr><th>$I[unignore]</th><td>";
		echo "<select name=\"unignore\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($ignored as $ign){
			echo "<option value=\"$ign\">$ign</option>";
		}
		echo '</select></td></tr></table></td></tr>';
		thr();
	}
	echo "<tr><td><table id=\"ignore\"><tr><th>$I[ignore]</th><td>";
	echo "<select name=\"ignore\" size=\"1\"><option value=\"\">$I[choose]</option>";
	$stmt=$db->prepare('SELECT poster, style FROM ' . PREFIX . 'messages INNER JOIN (SELECT nickname, style FROM ' . PREFIX . 'sessions UNION SELECT nickname, style FROM ' . PREFIX . 'members) AS t ON (' .  PREFIX . 'messages.poster=t.nickname) WHERE poster!=? AND poster NOT IN (SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=?) GROUP BY poster ORDER BY LOWER(poster);');
	$stmt->execute([$U['nickname'], $U['nickname']]);
	while($nick=$stmt->fetch(PDO::FETCH_NUM)){
		echo '<option value="'.htmlspecialchars($nick[0])."\" style=\"$nick[1]\">".htmlspecialchars($nick[0]).'</option>';
	}
	echo '</select></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table id=\"refresh\"><tr><th>$I[refreshrate]</th><td>";
	echo "<input type=\"number\" name=\"refresh\" size=\"3\" maxlength=\"3\" min=\"5\" max=\"150\" value=\"$U[refresh]\"></td></tr></table></td></tr>";
	thr();
	preg_match('/#([0-9a-f]{6})/i', $U['style'], $matches);
	echo "<tr><td><table id=\"colour\"><tr><th>$I[fontcolour] (<a href=\"$_SERVER[SCRIPT_NAME]?action=colours&amp;session=$U[session]&amp;lang=$language\" target=\"view\">$I[viewexample]</a>)</th><td>";
	echo "<input type=\"color\" value=\"#$matches[1]\" name=\"colour\"></td></tr></table></td></tr>";
	thr();
	echo "<tr><td><table id=\"bgcolour\"><tr><th>$I[bgcolour] (<a href=\"$_SERVER[SCRIPT_NAME]?action=colours&amp;session=$U[session]&amp;lang=$language\" target=\"view\">$I[viewexample]</a>)</th><td>";
	echo "<input type=\"color\" value=\"#$U[bgcolour]\" name=\"bgcolour\"></td></tr></table></td></tr>";
	thr();
	if($U['status']>=3){
		echo "<tr><td><table id=\"font\"><tr><th>$I[fontface]</th><td><table>";
		echo "<tr><td>&nbsp;</td><td><select name=\"font\" size=\"1\"><option value=\"\">* $I[roomdefault] *</option>";
		$F=load_fonts();
		foreach($F as $name=>$font){
			echo "<option style=\"$font\" ";
			if(strpos($U['style'], $font)!==false){
				echo 'selected ';
			}
			echo "value=\"$name\">$name</option>";
		}
		echo '</select></td><td>&nbsp;</td><td><label><input type="checkbox" name="bold" id="bold" value="on"';
		if(strpos($U['style'], 'font-weight:bold;')!==false){
			echo ' checked';
		}
		echo "><b>$I[bold]</b></label></td><td>&nbsp;</td><td><label><input type=\"checkbox\" name=\"italic\" id=\"italic\" value=\"on\"";
		if(strpos($U['style'], 'font-style:italic;')!==false){
			echo ' checked';
		}
		echo "><i>$I[italic]</i></label></td><td>&nbsp;</td><td><label><input type=\"checkbox\" name=\"small\" id=\"small\" value=\"on\"";
		if(strpos($U['style'], 'font-size:smaller;')!==false){
			echo ' checked';
		}
		echo "><small>$I[small]</small></label></td></tr></table></td></tr></table></td></tr>";
		thr();
	}
	echo '<tr><td>'.style_this(htmlspecialchars($U['nickname'])." : $I[fontexample]", $U['style']).'</td></tr>';
	thr();
	$bool_settings=['timestamps', 'nocache', 'sortupdown', 'hidechatters'];
	if(get_setting('imgembed')){
		$bool_settings[]='embed';
	}
	if($U['status']>=5 && get_setting('incognito')){
		$bool_settings[]='incognito';
	}
	foreach($bool_settings as $setting){
		echo "<tr><td><table id=\"$setting\"><tr><th>".$I[$setting].'</th><td>';
		echo "<label><input type=\"checkbox\" name=\"$setting\" value=\"on\"";
		if($U[$setting]){
			echo ' checked';
		}
		echo "><b>$I[enabled]</b></label></td></tr></table></td></tr>";
		thr();
	}
	if($U['status']>=2 && get_setting('eninbox')){
		echo "<tr><td><table id=\"eninbox\"><tr><th>$I[eninbox]</th><td>";
		echo "<select name=\"eninbox\" id=\"eninbox\">";
		echo '<option value="0"';
		if($U['eninbox']==0){
			echo ' selected';
		}
		echo ">$I[disabled]</option>";
		echo '<option value="1"';
		if($U['eninbox']==1){
			echo ' selected';
		}
		echo ">$I[eninall]</option>";
		echo '<option value="3"';
		if($U['eninbox']==3){
			echo ' selected';
		}
		echo ">$I[eninmem]</option>";
		echo '<option value="5"';
		if($U['eninbox']==5){
			echo ' selected';
		}
		echo ">$I[eninstaff]</option>";
		echo '</select></td></tr></table></td></tr>';
		thr();
	}
	echo "<tr><td><table id=\"tz\"><tr><th>$I[tz]</th><td>";
	echo "<select name=\"tz\">";
	$tzs=timezone_identifiers_list();
	foreach($tzs as $tz){
		echo "<option value=\"$tz\"";
		if($U['tz']==$tz){
			echo ' selected';
		}
		echo ">$tz</option>";
	}
	echo '</select></td></tr></table></td></tr>';
	thr();
	if($U['status']>=2){
		echo "<tr><td><table id=\"changepass\"><tr><th>$I[changepass]</th></tr>";
		echo '<tr><td><table>';
		echo "<tr><td>&nbsp;</td><td>$I[oldpass]</td><td><input type=\"password\" name=\"oldpass\" size=\"20\"></td></tr>";
		echo "<tr><td>&nbsp;</td><td>$I[newpass]</td><td><input type=\"password\" name=\"newpass\" size=\"20\"></td></tr>";
		echo "<tr><td>&nbsp;</td><td>$I[confirmpass]</td><td><input type=\"password\" name=\"confirmpass\" size=\"20\"></td></tr>";
		echo '</table></td></tr></table></td></tr>';
		thr();
		echo "<tr><td><table id=\"changenick\"><tr><th>$I[changenick]</th><td><table>";
		echo "<tr><td>&nbsp;</td><td>$I[newnickname]</td><td><input type=\"text\" name=\"newnickname\" size=\"20\">";
		echo '</table></td></tr></table></td></tr>';
		thr();
	}
	echo '<tr><td>'.submit($I['savechanges']).'</td></tr></table></form>';
	if($U['status']>1 && $U['status']<8){
		echo '<br>'.form('profile', 'delete').submit($I['deleteacc'], 'class="delbutton"').'</form>';
	}
	echo "<br><p id=\"changelang\">$I[changelang]";
	foreach($L as $lang=>$name){
		echo " <a href=\"$_SERVER[SCRIPT_NAME]?lang=$lang&amp;session=$U[session]&amp;action=controls\" target=\"controls\">$name</a>";
	}
	echo '</p><br>'.form('view').submit($I['backtochat'], 'class="backbutton"').'</form>';
	print_end();
}

function send_controls(){
	global $I, $U;
	print_start('controls');
	$personalnotes=(bool) get_setting('personalnotes');
	echo '<table><tr>';
	echo '<td>'.form_target('post', 'post').submit($I['reloadpb']).'</form></td>';
	echo '<td>'.form_target('view', 'view').submit($I['reloadmsgs']).'</form></td>';
	echo '<td>'.form_target('view', 'profile').submit($I['chgprofile']).'</form></td>';
	if($U['status']>=5){
		echo '<td>'.form_target('view', 'admin').submit($I['adminbtn']).'</form></td>';
		if(!$personalnotes){
			echo '<td>'.form_target('view', 'notes', 'staff').submit($I['notes']).'</form></td>';
		}
	}
	if($U['status']>=3){
		if($personalnotes){
			echo '<td>'.form_target('view', 'notes').submit($I['notes']).'</form></td>';
		}
		echo '<td>'.form_target('_blank', 'login').submit($I['clone']).'</form></td>';
	}
	if(!isset($_REQUEST['sort'])){
		$sort=0;
	}else{
		$sort=$_REQUEST['sort'];
	}
	echo '<td>'.form_target('_parent', 'login').hidden('sort', $sort).submit($I['sortframe']).'</form></td>';
	echo '<td>'.form_target('view', 'help').submit($I['randh']).'</form></td>';
	echo '<td>'.form_target('_parent', 'logout').submit($I['exit'], 'id="exitbutton"').'</form></td>';
	echo '</tr></table>';
	print_end();
}

function send_download(){
	global $I, $db;
	if(isset($_REQUEST['id'])){
		$stmt=$db->prepare('SELECT filename, type, data FROM ' . PREFIX . 'files WHERE hash=?;');
		$stmt->execute([$_REQUEST['id']]);
		if($data=$stmt->fetch(PDO::FETCH_ASSOC)){
			header("Content-Type: $data[type]");
			header("Content-Disposition: filename=\"$data[filename]\"");
			header('Pragma: no-cache');
			header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private');
			header('Expires: 0');
			echo base64_decode($data['data']);
		}else{
			send_error($I['filenotfound']);
		}
	}else{
		send_error($I['filenotfound']);
	}
}

function send_logout(){
	global $I, $U;
	print_start('logout');
	echo '<h1>'.sprintf($I['bye'], style_this(htmlspecialchars($U['nickname']), $U['style'])).'</h1>'.form_target('_parent', '').submit($I['backtologin'], 'class="backbutton"').'</form>';
	print_end();
}

function send_colours(){
	global $I;
	print_start('colours');
	echo "<h2>$I[colourtable]</h2><kbd><b>";
	for($red=0x00;$red<=0xFF;$red+=0x33){
		for($green=0x00;$green<=0xFF;$green+=0x33){
			for($blue=0x00;$blue<=0xFF;$blue+=0x33){
				$hcol=sprintf('%02X%02X%02X', $red, $green, $blue);
				echo "<span style=\"color:#$hcol\">$hcol</span> ";
			}
			echo '<br>';
		}
		echo '<br>';
	}
	echo '</b></kbd>'.form('profile').submit($I['backtoprofile'], ' class="backbutton"').'</form>';
	print_end();
}

function send_login(){
	global $I, $L;
	$ga=(int) get_setting('guestaccess');
	if($ga===4){
		send_chat_disabled();
	}
	print_start('login');
	$englobal=(int) get_setting('englobalpass');
	echo '<h1 id="chatname">'.get_setting('chatname').'</h1>';
	echo form_target('_parent', 'login');
	if($englobal===1 && isset($_REQUEST['globalpass'])){
		echo hidden('globalpass', $_REQUEST['globalpass']);
	}
	echo '<table>';
	if($englobal!==1 || (isset($_REQUEST['globalpass']) && $_REQUEST['globalpass']==get_setting('globalpass'))){
		echo "<tr><td>$I[nick]</td><td><input type=\"text\" name=\"nick\" size=\"15\" autofocus></td></tr>";
		echo "<tr><td>$I[pass]</td><td><input type=\"password\" name=\"pass\" size=\"15\"></td></tr>";
		send_captcha();
		if($ga!==0){
			if(get_setting('guestreg')!=0){
				echo "<tr><td>$I[regpass]</td><td><input type=\"password\" name=\"regpass\" size=\"15\" placeholder=\"$I[optional]\"></td></tr>";
			}
			if($englobal===2){
				echo "<tr><td>$I[globalloginpass]</td><td><input type=\"password\" name=\"globalpass\" size=\"15\"></td></tr>";
			}
			echo "<tr><td colspan=\"2\">$I[choosecol]<br><select name=\"colour\"><option value=\"\">* $I[randomcol] *</option>";
			print_colours();
			echo '</select></td></tr>';
		}else{
			echo "<tr><td colspan=\"2\">$I[noguests]</td></tr>";
		}
		echo '<tr><td colspan="2">'.submit($I['enter']).'</td></tr></table></form>';
		get_nowchatting();
		echo '<br><div id="topic">';
		echo get_setting('topic');
		echo '</div>';
		$rulestxt=get_setting('rulestxt');
		if(!empty($rulestxt)){
			echo "<div id=\"rules\"><h2>$I[rules]</h2><b>$rulestxt</b></div>";
		}
	}else{
		echo "<tr><td>$I[globalloginpass]</td><td><input type=\"password\" name=\"globalpass\" size=\"15\" autofocus></td></tr>";
		if($ga===0){
			echo "<tr><td colspan=\"2\">$I[noguests]</td></tr>";
		}
		echo '<tr><td colspan="2">'.submit($I['enter']).'</td></tr></table></form>';
	}
	echo "<p id=\"changelang\">$I[changelang]";
	foreach($L as $lang=>$name){
		echo " <a href=\"$_SERVER[SCRIPT_NAME]?lang=$lang\">$name</a>";
	}
	echo '</p>'.credit();
	print_end();
}

function send_chat_disabled(){
	print_start('disabled');
	echo get_setting('disabletext');
	print_end();
}

function send_error($err){
	global $I;
	print_start('error');
	echo "<h2>$I[error]: $err</h2>".form_target('_parent', '').submit($I['backtologin'], 'class="backbutton"').'</form>';
	print_end();
}

function send_fatal_error($err){
	global $I;
	echo '<!DOCTYPE html><html><head>'.meta_html();
	echo "<title>$I[fatalerror]</title>";
	echo "<style type=\"text/css\">body{background-color:#000000;color:#FF0033;}</style>";
	echo '</head><body>';
	echo "<h2>$I[fatalerror]: $err</h2>";
	print_end();
}

function print_notifications(){
	global $I, $U, $db;
	echo '<span id="notifications">';
	if($U['status']>=2 && $U['eninbox']!=0){
		$stmt=$db->prepare('SELECT COUNT(*) FROM ' . PREFIX . 'inbox WHERE recipient=?;');
		$stmt->execute([$U['nickname']]);
		$tmp=$stmt->fetch(PDO::FETCH_NUM);
		if($tmp[0]>0){
			echo '<p>'.form('inbox').submit(sprintf($I['inboxmsgs'], $tmp[0])).'</form></p>';
		}
	}
	if($U['status']>=5 && get_setting('guestaccess')==3){
		$result=$db->query('SELECT COUNT(*) FROM ' . PREFIX . 'sessions WHERE entry=0 AND status=1;');
		$temp=$result->fetch(PDO::FETCH_NUM);
		if($temp[0]>0){
			echo '<p>';
			echo form('admin', 'approve');
			echo submit(sprintf($I['approveguests'], $temp[0])).'</form></p>';
		}
	}
	echo '</span>';
}
function print_chatters(){
	global $I, $U, $db, $language;
	if(!$U['hidechatters']){
		echo '<div id="chatters"><table><tr>';
		$stmt=$db->prepare('SELECT nickname, style, status FROM ' . PREFIX . 'sessions WHERE entry!=0 AND status>0 AND incognito=0 AND nickname NOT IN (SELECT ign FROM '. PREFIX . 'ignored WHERE ignby=? UNION SELECT ignby FROM '. PREFIX . 'ignored WHERE ign=?) ORDER BY status DESC, lastpost DESC;');
		$stmt->execute([$U['nickname'], $U['nickname']]);
		$nc=substr(time(), -6);
		$G=$M=[];
		while($user=$stmt->fetch(PDO::FETCH_NUM)){
			$link="<a href=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]&amp;lang=$language&amp;nc=$nc&amp;sendto=".htmlspecialchars($user[0]).'" target="post">'.style_this(htmlspecialchars($user[0]), $user[1]).'</a>';
			if($user[2]<=2){
				$G[]=$link;
			}else{
				$M[]=$link;
			}
		}
		if(!empty($M)){
			echo "<th>$I[members]:</th><td>&nbsp;</td><td>".implode(' &nbsp; ', $M).'</td>';
			if(!empty($G)){
				echo '<td>&nbsp;&nbsp;</td>';
			}
		}
		if(!empty($G)){
			echo "<th>$I[guests]:</th><td>&nbsp;</td><td>".implode(' &nbsp; ', $G).'</td>';
		}
		echo '</tr></table></div>';
	}
}

//  session management

function create_session($setup, $nickname, $password){
	global $I, $U;
	$U['nickname']=preg_replace('/\s/', '', $nickname);
	if(check_member($password)){
		if($setup && $U['status']>=7){
			$U['incognito']=1;
		}
		$U['entry']=$U['lastpost']=time();
	}else{
		add_user_defaults($password);
		check_captcha(isset($_REQUEST['challenge']) ? $_REQUEST['challenge'] : '', isset($_REQUEST['captcha']) ? $_REQUEST['captcha'] : '');
		$ga=(int) get_setting('guestaccess');
		if(!valid_nick($U['nickname'])){
			send_error(sprintf($I['invalnick'], get_setting('maxname'), get_setting('nickregex')));
		}
		if(!valid_pass($password)){
			send_error(sprintf($I['invalpass'], get_setting('minpass'), get_setting('passregex')));
		}
		if($ga===0){
			send_error($I['noguests']);
		}elseif($ga===3){
			$U['entry']=0;
		}
		if(get_setting('englobalpass')!=0 && isset($_REQUEST['globalpass']) && $_REQUEST['globalpass']!=get_setting('globalpass')){
			send_error($I['wrongglobalpass']);
		}
	}
	write_new_session($password);
}

function check_captcha($challenge, $captcha_code){
	global $I, $db, $memcached;
	$captcha=(int) get_setting('captcha');
	if($captcha!==0){
		if(empty($challenge)){
			send_error($I['wrongcaptcha']);
		}
		if(MEMCACHED){
			if(!$code=$memcached->get(DBNAME . '-' . PREFIX . "captcha-$_REQUEST[challenge]")){
				send_error($I['captchaexpire']);
			}
			$memcached->delete(DBNAME . '-' . PREFIX . "captcha-$_REQUEST[challenge]");
		}else{
			$stmt=$db->prepare('SELECT code FROM ' . PREFIX . 'captcha WHERE id=?;');
			$stmt->execute([$challenge]);
			$stmt->bindColumn(1, $code);
			if(!$stmt->fetch(PDO::FETCH_BOUND)){
				send_error($I['captchaexpire']);
			}
			$time=time();
			$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'captcha WHERE id=? OR time<(?-(SELECT value FROM ' . PREFIX . "settings WHERE setting='captchatime'));");
			$stmt->execute([$challenge, $time]);
		}
		if($captcha_code!==$code){
			if($captcha!==3 || strrev($captcha_code)!==$code){
				send_error($I['wrongcaptcha']);
			}
		}
	}
}

function is_definitely_ssl() {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) {
        return true;
    }
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && ('https' == $_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        return true;
    }
    return false;
}

function write_new_session($password){
	global $I, $U, $db;
	$stmt=$db->prepare('SELECT * FROM ' . PREFIX . 'sessions WHERE nickname=?;');
	$stmt->execute([$U['nickname']]);
	if($temp=$stmt->fetch(PDO::FETCH_ASSOC)){
		// check whether alrady logged in
		if(password_verify($password, $temp['passhash'])){
			$U=$temp;
			check_kicked();
            if (version_compare(PHP_VERSION, '7.3.0') >= 0) {
                setcookie(COOKIENAME, $U['session'], ['expires' => 0, 'path' => '/', 'domain' => '', 'secure' => is_definitely_ssl(), 'httponly'=>true, 'samesite' => 'Strict']);
            }else{
                setcookie(COOKIENAME, $U['session'], 0, '/', '', is_definitely_ssl(), true);
            }
		}else{
			send_error("$I[userloggedin]<br>$I[wrongpass]");
		}
	}else{
		// create new session
		$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'sessions WHERE session=?;');
		do{
			if(function_exists('random_bytes')){
				$U['session']=bin2hex(random_bytes(16));
			}else{
				$U['session']=md5(uniqid($U['nickname'], true).mt_rand());
			}
			$stmt->execute([$U['session']]);
		}while($stmt->fetch(PDO::FETCH_NUM)); // check for hash collision
		if(isset($_SERVER['HTTP_USER_AGENT'])){
			$useragent=htmlspecialchars($_SERVER['HTTP_USER_AGENT']);
		}else{
			$useragent='';
		}
		if(get_setting('trackip')){
			$ip=$_SERVER['REMOTE_ADDR'];
		}else{
			$ip='';
		}
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'sessions (session, nickname, status, refresh, style, lastpost, passhash, useragent, bgcolour, entry, timestamps, embed, incognito, ip, nocache, tz, eninbox, sortupdown, hidechatters, nocache_old) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		$stmt->execute([$U['session'], $U['nickname'], $U['status'], $U['refresh'], $U['style'], $U['lastpost'], $U['passhash'], $useragent, $U['bgcolour'], $U['entry'], $U['timestamps'], $U['embed'], $U['incognito'], $ip, $U['nocache'], $U['tz'], $U['eninbox'], $U['sortupdown'], $U['hidechatters'], $U['nocache_old']]);
        if (version_compare(PHP_VERSION, '7.3.0') >= 0) {
            setcookie(COOKIENAME, $U['session'], ['expires' => 0, 'path' => '/', 'domain' => '', 'secure' => is_definitely_ssl(), 'httponly'=>true, 'samesite' => 'Strict']);
        }else{
            setcookie(COOKIENAME, $U['session'], 0, '/', '', is_definitely_ssl(), true);
        }
		if($U['status']>=3 && !$U['incognito']){
			add_system_message(sprintf(get_setting('msgenter'), style_this(htmlspecialchars($U['nickname']), $U['style'])));
		}
	}
}

function approve_session(){
	global $db;
	if(isset($_REQUEST['what'])){
		if($_REQUEST['what']==='allowchecked' && isset($_REQUEST['csid'])){
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET entry=lastpost WHERE nickname=?;');
			foreach($_REQUEST['csid'] as $nick){
				$stmt->execute([$nick]);
			}
		}elseif($_REQUEST['what']==='allowall' && isset($_REQUEST['alls'])){
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET entry=lastpost WHERE nickname=?;');
			foreach($_REQUEST['alls'] as $nick){
				$stmt->execute([$nick]);
			}
		}elseif($_REQUEST['what']==='denychecked' && isset($_REQUEST['csid'])){
			$time=60*(get_setting('kickpenalty')-get_setting('guestexpire'))+time();
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET lastpost=?, status=0, kickmessage=? WHERE nickname=? AND status=1;');
			foreach($_REQUEST['csid'] as $nick){
				$stmt->execute([$time, $_REQUEST['kickmessage'], $nick]);
			}
		}elseif($_REQUEST['what']==='denyall' && isset($_REQUEST['alls'])){
			$time=60*(get_setting('kickpenalty')-get_setting('guestexpire'))+time();
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET lastpost=?, status=0, kickmessage=? WHERE nickname=? AND status=1;');
			foreach($_REQUEST['alls'] as $nick){
				$stmt->execute([$time, $_REQUEST['kickmessage'], $nick]);
			}
		}
	}
}

function check_login(){
	global $I, $U, $db;
	$ga=(int) get_setting('guestaccess');
	if(isset($_REQUEST['session'])){
		parse_sessions();
	}
	if(isset($U['session'])){
		check_kicked();
	}elseif(get_setting('englobalpass')==1 && (!isset($_REQUEST['globalpass']) || $_REQUEST['globalpass']!=get_setting('globalpass'))){
		send_error($I['wrongglobalpass']);
	}elseif(!isset($_REQUEST['nick']) || !isset($_REQUEST['pass'])){
		send_login();
	}else{
		if($ga===4){
			send_chat_disabled();
		}
		if(!empty($_REQUEST['regpass']) && $_REQUEST['regpass']!==$_REQUEST['pass']){
			send_error($I['noconfirm']);
		}
		create_session(false, $_REQUEST['nick'], $_REQUEST['pass']);
		if(!empty($_REQUEST['regpass'])){
			$guestreg=(int) get_setting('guestreg');
			if($guestreg===1){
				register_guest(2, $_REQUEST['nick']);
				$U['status']=2;
			}elseif($guestreg===2){
				register_guest(3, $_REQUEST['nick']);
				$U['status']=3;
			}
		}
	}
	if($U['status']==1){
		if($ga===2 || $ga===3){
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET entry=0 WHERE session=?;');
			$stmt->execute([$U['session']]);
			send_waiting_room();
		}
	}
}

function kill_session(){
	global $U, $db;
	parse_sessions();
	check_expired();
	check_kicked();
	setcookie(COOKIENAME, false);
	$_REQUEST['session']='';
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'sessions WHERE session=?;');
	$stmt->execute([$U['session']]);
	if($U['status']>=3 && !$U['incognito']){
		add_system_message(sprintf(get_setting('msgexit'), style_this(htmlspecialchars($U['nickname']), $U['style'])));
	}
}

function kick_chatter($names, $mes, $purge){
	global $U, $db;
	$lonick='';
	$time=60*(get_setting('kickpenalty')-get_setting('guestexpire'))+time();
	$check=$db->prepare('SELECT style, entry FROM ' . PREFIX . 'sessions WHERE nickname=? AND status!=0 AND (status<? OR nickname=?);');
	$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET lastpost=?, status=0, kickmessage=? WHERE nickname=?;');
	$all=false;
	if($names[0]==='s &'){
		$tmp=$db->query('SELECT nickname FROM ' . PREFIX . 'sessions WHERE status=1;');
		$names=[];
		while($name=$tmp->fetch(PDO::FETCH_NUM)){
			$names[]=$name[0];
		}
		$all=true;
	}
	$i=0;
	foreach($names as $name){
		$check->execute([$name, $U['status'], $U['nickname']]);
		if($temp=$check->fetch(PDO::FETCH_ASSOC)){
			$stmt->execute([$time, $mes, $name]);
			if($purge){
				del_all_messages($name, $temp['entry']);
			}
			$lonick.=style_this(htmlspecialchars($name), $temp['style']).', ';
			++$i;
		}
	}
	if($i>0){
		if($all){
			add_system_message(get_setting('msgallkick'));
		}else{
			$lonick=substr($lonick, 0, -2);
			if($i>1){
				add_system_message(sprintf(get_setting('msgmultikick'), $lonick));
			}else{
				add_system_message(sprintf(get_setting('msgkick'), $lonick));
			}
		}
		return true;
	}
	return false;
}

function logout_chatter($names){
	global $U, $db;
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'sessions WHERE nickname=? AND status<?;');
	if($names[0]==='s &'){
		$tmp=$db->query('SELECT nickname FROM ' . PREFIX . 'sessions WHERE status=1;');
		$names=[];
		while($name=$tmp->fetch(PDO::FETCH_NUM)){
			$names[]=$name[0];
		}
	}
	foreach($names as $name){
		$stmt->execute([$name, $U['status']]);
	}
}

function check_session(){
	global $U;
	parse_sessions();
	check_expired();
	check_kicked();
	if($U['entry']==0){
		send_waiting_room();
	}
}

function check_expired(){
	global $I, $U;
	if(!isset($U['session'])){
		setcookie(COOKIENAME, false);
		$_REQUEST['session']='';
		send_error($I['expire']);
	}
}

function get_count_mods(){
	global $db;
	$c=$db->query('SELECT COUNT(*) FROM ' . PREFIX . 'sessions WHERE status>=5')->fetch(PDO::FETCH_NUM);
	return $c[0];
}

function check_kicked(){
	global $I, $U;
	if($U['status']==0){
		setcookie(COOKIENAME, false);
		$_REQUEST['session']='';
		send_error("$I[kicked]<br>$U[kickmessage]");
	}
}

function get_nowchatting(){
	global $I, $db;
	parse_sessions();
	$stmt=$db->query('SELECT COUNT(*) FROM ' . PREFIX . 'sessions WHERE entry!=0 AND status>0 AND incognito=0;');
	$count=$stmt->fetch(PDO::FETCH_NUM);
	echo '<div id="chatters">'.sprintf($I['curchat'], $count[0]).'<br>';
	if(!get_setting('hidechatters')){
		$stmt=$db->query('SELECT nickname, style FROM ' . PREFIX . 'sessions WHERE entry!=0 AND status>0 AND incognito=0 ORDER BY status DESC, lastpost DESC;');
		while($user=$stmt->fetch(PDO::FETCH_NUM)){
			echo style_this(htmlspecialchars($user[0]), $user[1]).' &nbsp; ';
		}
	}
	echo '</div>';
}

function parse_sessions(){
	global $U, $db;
	// look for our session
	if(isset($_REQUEST['session'])){
		$stmt=$db->prepare('SELECT * FROM ' . PREFIX . 'sessions WHERE session=?;');
		$stmt->execute([$_REQUEST['session']]);
		if($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
			$U=$tmp;
		}
	}
	set_default_tz();
}

//  member handling

function check_member($password){
	global $I, $U, $db;
	$stmt=$db->prepare('SELECT * FROM ' . PREFIX . 'members WHERE nickname=?;');
	$stmt->execute([$U['nickname']]);
	if($temp=$stmt->fetch(PDO::FETCH_ASSOC)){
		if(get_setting('dismemcaptcha')==0){
			check_captcha(isset($_REQUEST['challenge']) ? $_REQUEST['challenge'] : '', isset($_REQUEST['captcha']) ? $_REQUEST['captcha'] : '');
		}
		if($temp['passhash']===md5(sha1(md5($U['nickname'].$password)))){
			// old hashing method, update on the fly
			$temp['passhash']=password_hash($password, PASSWORD_DEFAULT);
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET passhash=? WHERE nickname=?;');
			$stmt->execute([$temp['passhash'], $U['nickname']]);
		}
		if(password_verify($password, $temp['passhash'])){
			$U=$temp;
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET lastlogin=? WHERE nickname=?;');
			$stmt->execute([time(), $U['nickname']]);
			return true;
		}else{
			send_error("$I[regednick]<br>$I[wrongpass]");
		}
	}
	return false;
}

function delete_account(){
	global $U, $db;
	if($U['status']<8){
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET status=1, incognito=0 WHERE nickname=?;');
		$stmt->execute([$U['nickname']]);
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'members WHERE nickname=?;');
		$stmt->execute([$U['nickname']]);
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'inbox WHERE recipient=?;');
		$stmt->execute([$U['nickname']]);
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'notes WHERE type=2 AND editedby=?;');
		$stmt->execute([$U['nickname']]);
		$U['status']=1;
	}
}

function register_guest($status, $nick){
	global $I, $U, $db;
	$stmt=$db->prepare('SELECT style FROM ' . PREFIX . 'members WHERE nickname=?');
	$stmt->execute([$nick]);
	if($tmp=$stmt->fetch(PDO::FETCH_NUM)){
		return sprintf($I['alreadyreged'], style_this(htmlspecialchars($nick), $tmp[0]));
	}
	$stmt=$db->prepare('SELECT * FROM ' . PREFIX . 'sessions WHERE nickname=? AND status=1;');
	$stmt->execute([$nick]);
	if($reg=$stmt->fetch(PDO::FETCH_ASSOC)){
		$reg['status']=$status;
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET status=? WHERE session=?;');
		$stmt->execute([$reg['status'], $reg['session']]);
	}else{
		return sprintf($I['cantreg'], htmlspecialchars($nick));
	}
	$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, regedby, timestamps, embed, style, incognito, nocache, tz, eninbox, sortupdown, hidechatters, nocache_old) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
	$stmt->execute([$reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['bgcolour'], $U['nickname'], $reg['timestamps'], $reg['embed'], $reg['style'], $reg['incognito'], $reg['nocache'], $reg['tz'], $reg['eninbox'], $reg['sortupdown'], $reg['hidechatters'], $reg['nocache_old']]);
	if($reg['status']==3){
		add_system_message(sprintf(get_setting('msgmemreg'), style_this(htmlspecialchars($reg['nickname']), $reg['style'])));
	}else{
		add_system_message(sprintf(get_setting('msgsureg'), style_this(htmlspecialchars($reg['nickname']), $reg['style'])));
	}
	return sprintf($I['successreg'], style_this(htmlspecialchars($reg['nickname']), $reg['style']));
}

function register_new($nick, $pass){
	global $I, $U, $db;
	$nick=preg_replace('/\s/', '', $nick);
	if(empty($nick)){
		return '';
	}
	$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'sessions WHERE nickname=?');
	$stmt->execute([$nick]);
	if($stmt->fetch(PDO::FETCH_NUM)){
		return sprintf($I['cantreg'], htmlspecialchars($nick));
	}
	if(!valid_nick($nick)){
		return sprintf($I['invalnick'], get_setting('maxname'), get_setting('nickregex'));
	}
	if(!valid_pass($pass)){
		return sprintf($I['invalpass'], get_setting('minpass'), get_setting('passregex'));
	}
	$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'members WHERE nickname=?');
	$stmt->execute([$nick]);
	if($stmt->fetch(PDO::FETCH_NUM)){
		return sprintf($I['alreadyreged'], htmlspecialchars($nick));
	}
	$reg=[
		'nickname'	=>$nick,
		'passhash'	=>password_hash($pass, PASSWORD_DEFAULT),
		'status'	=>3,
		'refresh'	=>get_setting('defaultrefresh'),
		'bgcolour'	=>get_setting('colbg'),
		'regedby'	=>$U['nickname'],
		'timestamps'	=>get_setting('timestamps'),
		'style'		=>'color:#'.get_setting('coltxt').';',
		'embed'		=>1,
		'incognito'	=>0,
		'nocache'	=>0,
		'nocache_old'	=>1,
		'tz'		=>get_setting('defaulttz'),
		'eninbox'	=>0,
		'sortupdown'	=>get_setting('sortupdown'),
		'hidechatters'	=>get_setting('hidechatters'),
	];
	$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, regedby, timestamps, style, embed, incognito, nocache, tz, eninbox, sortupdown, hidechatters, nocache_old) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
	$stmt->execute([$reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['bgcolour'], $reg['regedby'], $reg['timestamps'], $reg['style'], $reg['embed'], $reg['incognito'], $reg['nocache'], $reg['tz'], $reg['eninbox'], $reg['sortupdown'], $reg['hidechatters'], $reg['nocache_old']]);
	return sprintf($I['successreg'], htmlspecialchars($reg['nickname']));
}

function change_status($nick, $status){
	global $I, $U, $db;
	if(empty($nick)){
		return '';
	}elseif($U['status']<=$status || !preg_match('/^[023567\-]$/', $status)){
		return sprintf($I['cantchgstat'], htmlspecialchars($nick));
	}
	$stmt=$db->prepare('SELECT incognito, style FROM ' . PREFIX . 'members WHERE nickname=? AND status<?;');
	$stmt->execute([$nick, $U['status']]);
	if(!$old=$stmt->fetch(PDO::FETCH_NUM)){
		return sprintf($I['cantchgstat'], htmlspecialchars($nick));
	}
	if($_REQUEST['set']==='-'){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'members WHERE nickname=?;');
		$stmt->execute([$nick]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET status=1, incognito=0 WHERE nickname=?;');
		$stmt->execute([$nick]);
		return sprintf($I['succdel'], style_this(htmlspecialchars($nick), $old[1]));
	}else{
		if($status<5){
			$old[0]=0;
		}
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET status=?, incognito=? WHERE nickname=?;');
		$stmt->execute([$status, $old[0], $nick]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET status=?, incognito=? WHERE nickname=?;');
		$stmt->execute([$status, $old[0], $nick]);
		return sprintf($I['succchg'], style_this(htmlspecialchars($nick), $old[1]));
	}
}

function passreset($nick, $pass){
	global $I, $U, $db;
	if(empty($nick)){
		return '';
	}
	$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'members WHERE nickname=? AND status<?;');
	$stmt->execute([$nick, $U['status']]);
	if($stmt->fetch(PDO::FETCH_ASSOC)){
		$passhash=password_hash($pass, PASSWORD_DEFAULT);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET passhash=? WHERE nickname=?;');
		$stmt->execute([$passhash, $nick]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET passhash=? WHERE nickname=?;');
		$stmt->execute([$passhash, $nick]);
		return sprintf($I['succpassreset'], htmlspecialchars($nick));
	}else{
		return sprintf($I['cantresetpass'], htmlspecialchars($nick));
	}
}

function amend_profile(){
	global $U;
	if(isset($_REQUEST['refresh'])){
		$U['refresh']=$_REQUEST['refresh'];
	}
	if($U['refresh']<5){
		$U['refresh']=5;
	}elseif($U['refresh']>150){
		$U['refresh']=150;
	}
	if(preg_match('/^#([a-f0-9]{6})$/i', $_REQUEST['colour'], $match)){
		$colour=$match[1];
	}else{
		preg_match('/#([0-9a-f]{6})/i', $U['style'], $matches);
		$colour=$matches[1];
	}
	if(preg_match('/^#([a-f0-9]{6})$/i', $_REQUEST['bgcolour'], $match)){
		$U['bgcolour']=$match[1];
	}
	$U['style']="color:#$colour;";
	if($U['status']>=3){
		$F=load_fonts();
		if(isset($F[$_REQUEST['font']])){
			$U['style'].=$F[$_REQUEST['font']];
		}
		if(isset($_REQUEST['small'])){
			$U['style'].='font-size:smaller;';
		}
		if(isset($_REQUEST['italic'])){
			$U['style'].='font-style:italic;';
		}
		if(isset($_REQUEST['bold'])){
			$U['style'].='font-weight:bold;';
		}
	}
	if($U['status']>=5 && isset($_REQUEST['incognito']) && get_setting('incognito')){
		$U['incognito']=1;
	}else{
		$U['incognito']=0;
	}
	if(isset($_REQUEST['tz'])){
		$tzs=timezone_identifiers_list();
		if(in_array($_REQUEST['tz'], $tzs)){
			$U['tz']=$_REQUEST['tz'];
		}
	}
	if(isset($_REQUEST['eninbox']) && $_REQUEST['eninbox']>=0 && $_REQUEST['eninbox']<=5){
		$U['eninbox']=$_REQUEST['eninbox'];
	}
	$bool_settings=['timestamps', 'embed', 'nocache', 'sortupdown', 'hidechatters'];
	foreach($bool_settings as $setting){
		if(isset($_REQUEST[$setting])){
			$U[$setting]=1;
		}else{
			$U[$setting]=0;
		}
	}
}

function save_profile(){
	global $I, $U, $db;
	amend_profile();
	$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET refresh=?, style=?, bgcolour=?, timestamps=?, embed=?, incognito=?, nocache=?, tz=?, eninbox=?, sortupdown=?, hidechatters=? WHERE session=?;');
	$stmt->execute([$U['refresh'], $U['style'], $U['bgcolour'], $U['timestamps'], $U['embed'], $U['incognito'], $U['nocache'], $U['tz'], $U['eninbox'], $U['sortupdown'], $U['hidechatters'], $U['session']]);
	if($U['status']>=2){
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET refresh=?, bgcolour=?, timestamps=?, embed=?, incognito=?, style=?, nocache=?, tz=?, eninbox=?, sortupdown=?, hidechatters=? WHERE nickname=?;');
		$stmt->execute([$U['refresh'], $U['bgcolour'], $U['timestamps'], $U['embed'], $U['incognito'], $U['style'], $U['nocache'], $U['tz'], $U['eninbox'], $U['sortupdown'], $U['hidechatters'], $U['nickname']]);
	}
	if(!empty($_REQUEST['unignore'])){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'ignored WHERE ign=? AND ignby=?;');
		$stmt->execute([$_REQUEST['unignore'], $U['nickname']]);
	}
	if(!empty($_REQUEST['ignore'])){
		$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'messages WHERE poster=? AND poster NOT IN (SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=?);');
		$stmt->execute([$_REQUEST['ignore'], $U['nickname']]);
		if($U['nickname']!==$_REQUEST['ignore'] && $stmt->fetch(PDO::FETCH_NUM)){
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'ignored (ign, ignby) VALUES (?, ?);');
			$stmt->execute([$_REQUEST['ignore'], $U['nickname']]);
		}
	}
	if($U['status']>1 && !empty($_REQUEST['newpass'])){
		if(!valid_pass($_REQUEST['newpass'])){
			return sprintf($I['invalpass'], get_setting('minpass'), get_setting('passregex'));
		}
		if(!isset($_REQUEST['oldpass'])){
			$_REQUEST['oldpass']='';
		}
		if(!isset($_REQUEST['confirmpass'])){
			$_REQUEST['confirmpass']='';
		}
		if($_REQUEST['newpass']!==$_REQUEST['confirmpass']){
			return $I['noconfirm'];
		}else{
			$U['newhash']=password_hash($_REQUEST['newpass'], PASSWORD_DEFAULT);
		}
		if(!password_verify($_REQUEST['oldpass'], $U['passhash'])){
			return $I['wrongpass'];
		}
		$U['passhash']=$U['newhash'];
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET passhash=? WHERE session=?;');
		$stmt->execute([$U['passhash'], $U['session']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET passhash=? WHERE nickname=?;');
		$stmt->execute([$U['passhash'], $U['nickname']]);
	}
	if($U['status']>1 && !empty($_REQUEST['newnickname'])){
		$msg=set_new_nickname();
		if($msg!==''){
			return $msg;
		}
	}
	return $I['succprofile'];
}

function set_new_nickname(){
	global $I, $U, $db;
	$_REQUEST['newnickname']=preg_replace('/\s/', '', $_REQUEST['newnickname']);
	if(!valid_nick($_REQUEST['newnickname'])){
		return sprintf($I['invalnick'], get_setting('maxname'), get_setting('nickregex'));
	}
	$stmt=$db->prepare('SELECT id FROM ' . PREFIX . 'sessions WHERE nickname=? UNION SELECT id FROM ' . PREFIX . 'members WHERE nickname=?;');
	$stmt->execute([$_REQUEST['newnickname'], $_REQUEST['newnickname']]);
	if($stmt->fetch(PDO::FETCH_NUM)){
		return $I['nicknametaken'];
	}else{
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET nickname=? WHERE nickname=?;');
		$stmt->execute([$_REQUEST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET nickname=? WHERE nickname=?;');
		$stmt->execute([$_REQUEST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'messages SET poster=? WHERE poster=?;');
		$stmt->execute([$_REQUEST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'messages SET recipient=? WHERE recipient=?;');
		$stmt->execute([$_REQUEST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'ignored SET ignby=? WHERE ignby=?;');
		$stmt->execute([$_REQUEST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'ignored SET ign=? WHERE ign=?;');
		$stmt->execute([$_REQUEST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'inbox SET poster=? WHERE poster=?;');
		$stmt->execute([$_REQUEST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'notes SET editedby=? WHERE editedby=?;');
		$stmt->execute([$_REQUEST['newnickname'], $U['nickname']]);
		$U['nickname']=$_REQUEST['newnickname'];
	}
	return '';
}

//sets default settings for guests
function add_user_defaults($password){
	global $U;
	$U['refresh']=get_setting('defaultrefresh');
	$U['bgcolour']=get_setting('colbg');
	if(!isset($_REQUEST['colour']) || !preg_match('/^[a-f0-9]{6}$/i', $_REQUEST['colour']) || abs(greyval($_REQUEST['colour'])-greyval(get_setting('colbg')))<75){
		do{
			$colour=sprintf('%06X', mt_rand(0, 16581375));
		}while(abs(greyval($colour)-greyval(get_setting('colbg')))<75);
	}else{
		$colour=$_REQUEST['colour'];
	}
	$U['style']="color:#$colour;";
	$U['timestamps']=get_setting('timestamps');
	$U['embed']=1;
	$U['incognito']=0;
	$U['status']=1;
	$U['nocache']=get_setting('sortupdown');
	if($U['nocache']){
		$U['nocache_old']=0;
	}else{
		$U['nocache_old']=1;
	}
	$U['tz']=get_setting('defaulttz');
	$U['eninbox']=0;
	$U['sortupdown']=get_setting('sortupdown');
	$U['hidechatters']=get_setting('hidechatters');
	$U['passhash']=password_hash($password, PASSWORD_DEFAULT);
	$U['entry']=$U['lastpost']=time();
}

// message handling

function validate_input(){
	global $U, $db;
	$inbox=false;
	$maxmessage=get_setting('maxmessage');
	$message=mb_substr($_REQUEST['message'], 0, $maxmessage);
	$rejected=mb_substr($_REQUEST['message'], $maxmessage);
	if($U['postid']===$_REQUEST['postid']){// ignore double post=reload from browser or proxy
		$message='';
	}elseif((time()-$U['lastpost'])<=1){// time between posts too short, reject!
		$rejected=$_REQUEST['message'];
		$message='';
	}
	if(!empty($rejected)){
		$rejected=trim($rejected);
		$rejected=htmlspecialchars($rejected);
	}
	$message=htmlspecialchars($message);
	$message=preg_replace("/(\r?\n|\r\n?)/u", '<br>', $message);
	if(isset($_REQUEST['multi'])){
		$message=preg_replace('/\s*<br>/u', '<br>', $message);
		$message=preg_replace('/<br>(<br>)+/u', '<br><br>', $message);
		$message=preg_replace('/<br><br>\s*$/u', '<br>', $message);
		$message=preg_replace('/^<br>\s*$/u', '', $message);
	}else{
		$message=str_replace('<br>', ' ', $message);
	}
	$message=trim($message);
	$message=preg_replace('/\s+/u', ' ', $message);
	$recipient='';
	if($_REQUEST['sendto']==='s *'){
		$poststatus=1;
		$displaysend=sprintf(get_setting('msgsendall'), style_this(htmlspecialchars($U['nickname']), $U['style']));
	}elseif($_REQUEST['sendto']==='s ?' && $U['status']>=3){
		$poststatus=3;
		$displaysend=sprintf(get_setting('msgsendmem'), style_this(htmlspecialchars($U['nickname']), $U['style']));
	}elseif($_REQUEST['sendto']==='s #' && $U['status']>=5){
		$poststatus=5;
		$displaysend=sprintf(get_setting('msgsendmod'), style_this(htmlspecialchars($U['nickname']), $U['style']));
	}elseif($_REQUEST['sendto']==='s &' && $U['status']>=6){
		$poststatus=6;
		$displaysend=sprintf(get_setting('msgsendadm'), style_this(htmlspecialchars($U['nickname']), $U['style']));
	}else{ // known nick in room?
		if(get_setting('disablepm')){
			//PMs disabled
			return;
		}
		$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'ignored WHERE (ignby=? AND ign=?) OR (ign=? AND ignby=?);');
		$stmt->execute([$_REQUEST['sendto'], $U['nickname'], $_REQUEST['sendto'], $U['nickname']]);
		if($stmt->fetch(PDO::FETCH_NUM)){
			//ignored
			return;
		}
		$tmp=false;
		$stmt=$db->prepare('SELECT s.style, 0 AS inbox FROM ' . PREFIX . 'sessions AS s LEFT JOIN ' . PREFIX . 'members AS m ON (m.nickname=s.nickname) WHERE s.nickname=? AND (s.incognito=0 OR (m.eninbox!=0 AND m.eninbox<=?));');
		$stmt->execute([$_REQUEST['sendto'], $U['status']]);
		if(!$tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
			$stmt=$db->prepare('SELECT style, 1 AS inbox FROM ' . PREFIX . 'members WHERE nickname=? AND eninbox!=0 AND eninbox<=?;');
			$stmt->execute([$_REQUEST['sendto'], $U['status']]);
			if(!$tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
				//nickname left or disabled offline inbox for us
				return;
			}
		}
		$recipient=$_REQUEST['sendto'];
		$poststatus=9;
		$displaysend=sprintf(get_setting('msgsendprv'), style_this(htmlspecialchars($U['nickname']), $U['style']), style_this(htmlspecialchars($recipient), $tmp['style']));
		$inbox=$tmp['inbox'];
	}
	if($poststatus!==9 && preg_match('~^/me~iu', $message)){
		$displaysend=style_this(htmlspecialchars("$U[nickname] "), $U['style']);
		$message=preg_replace("~^/me\s?~iu", '', $message);
	}
	$message=apply_filter($message, $poststatus, $U['nickname']);
	$message=create_hotlinks($message);
	$message=apply_linkfilter($message);
	if(isset($_FILES['file']) && get_setting('enfileupload')>0 && get_setting('enfileupload')<=$U['status']){
		if($_FILES['file']['error']===UPLOAD_ERR_OK && $_FILES['file']['size']<=(1024*get_setting('maxuploadsize'))){
			$hash=sha1_file($_FILES['file']['tmp_name']);
			$name=htmlspecialchars($_FILES['file']['name']);
			$message=sprintf(get_setting('msgattache'), "<a class=\"attachement\" href=\"$_SERVER[SCRIPT_NAME]?action=download&amp;id=$hash\" target=\"_blank\">$name</a>", $message);
		}
	}
	if(add_message($message, $recipient, $U['nickname'], $U['status'], $poststatus, $displaysend, $U['style'])){
		$U['lastpost']=time();
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET lastpost=?, postid=? WHERE session=?;');
		$stmt->execute([$U['lastpost'], $_REQUEST['postid'], $U['session']]);
		$stmt=$db->prepare('SELECT id FROM ' . PREFIX . 'messages WHERE poster=? ORDER BY id DESC LIMIT 1;');
		$stmt->execute([$U['nickname']]);
		$id=$stmt->fetch(PDO::FETCH_NUM);
		if($inbox && $id){
			$newmessage=[
				'postdate'	=>time(),
				'poster'	=>$U['nickname'],
				'recipient'	=>$recipient,
				'text'		=>"<span class=\"usermsg\">$displaysend".style_this($message, $U['style']).'</span>'
			];
			if(MSGENCRYPTED){
                $newmessage['text']=base64_encode(sodium_crypto_aead_aes256gcm_encrypt($newmessage['text'], '', AES_IV, ENCRYPTKEY));
			}
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'inbox (postdate, postid, poster, recipient, text) VALUES(?, ?, ?, ?, ?)');
			$stmt->execute([$newmessage['postdate'], $id[0], $newmessage['poster'], $newmessage['recipient'], $newmessage['text']]);
		}
		if(isset($hash) && $id){
			if(!empty($_FILES['file']['type']) && preg_match('~^[a-z0-9/\-\.\+]*$~i', $_FILES['file']['type'])){
				$type=$_FILES['file']['type'];
			}else{
				$type='application/octet-stream';
			}
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'files (postid, hash, filename, type, data) VALUES (?, ?, ?, ?, ?);');
			$stmt->execute([$id[0], $hash, str_replace('"', '\"', $_FILES['file']['name']), $type, base64_encode(file_get_contents($_FILES['file']['tmp_name']))]);
			unlink($_FILES['file']['tmp_name']);
		}
	}
	return $rejected;
}

function apply_filter($message, $poststatus, $nickname){
	global $I, $U;
	$message=str_replace('<br>', "\n", $message);
	$message=apply_mention($message);
	$filters=get_filters();
	foreach($filters as $filter){
		if($poststatus!==9 || !$filter['allowinpm']){
			if($filter['cs']){
				$message=preg_replace("/$filter[match]/u", $filter['replace'], $message, -1, $count);
			}else{
				$message=preg_replace("/$filter[match]/iu", $filter['replace'], $message, -1, $count);
			}
		}
		if(isset($count) && $count>0 && $filter['kick'] && ($U['status']<5 || get_setting('filtermodkick'))){
			kick_chatter([$nickname], $filter['replace'], false);
			setcookie(COOKIENAME, false);
			$_REQUEST['session']='';
			send_error("$I[kicked]<br>$filter[replace]");
		}
	}
	$message=str_replace("\n", '<br>', $message);
	return $message;
}

function apply_linkfilter($message){
	$filters=get_linkfilters();
	foreach($filters as $filter){
		$message=preg_replace_callback("/<a href=\"([^\"]+)\" target=\"_blank\">(.*?(?=<\/a>))<\/a>/iu",
			function ($matched) use(&$filter){
				return "<a href=\"$matched[1]\" target=\"_blank\">".preg_replace("/$filter[match]/iu", $filter['replace'], $matched[2]).'</a>';
			}
		, $message);
	}
	$redirect=get_setting('redirect');
	if(get_setting('imgembed')){
		$message=preg_replace_callback('/\[img\]\s?<a href="([^"]+)" target="_blank">(.*?(?=<\/a>))<\/a>/iu',
			function ($matched){
				return str_ireplace('[/img]', '', "<br><a href=\"$matched[1]\" target=\"_blank\"><img src=\"$matched[1]\"></a><br>");
			}
		, $message);
	}
	if(empty($redirect)){
		$redirect="$_SERVER[SCRIPT_NAME]?action=redirect&amp;url=";
	}
	if(get_setting('forceredirect')){
		$message=preg_replace_callback('/<a href="([^"]+)" target="_blank">(.*?(?=<\/a>))<\/a>/u',
			function ($matched) use($redirect){
				return "<a href=\"$redirect".rawurlencode($matched[1])."\" target=\"_blank\">$matched[2]</a>";
			}
		, $message);
	}elseif(preg_match_all('/<a href="([^"]+)" target="_blank">(.*?(?=<\/a>))<\/a>/u', $message, $matches)){
		foreach($matches[1] as $match){
			if(!preg_match('~^http(s)?://~u', $match)){
				$message=preg_replace_callback('/<a href="('.preg_quote($match, '/').')\" target=\"_blank\">(.*?(?=<\/a>))<\/a>/u',
					function ($matched) use($redirect){
						return "<a href=\"$redirect".rawurlencode($matched[1])."\" target=\"_blank\">$matched[2]</a>";
					}
				, $message);
			}
		}
	}
	return $message;
}

function create_hotlinks($message){
	//Make hotlinks for URLs, redirect through dereferrer script to prevent session leakage
	// 1. all explicit schemes with whatever xxx://yyyyyyy
	$message=preg_replace('~(^|[^\w"])(\w+://[^\s<>]+)~iu', "$1<<$2>>", $message);
	// 2. valid URLs without scheme:
	$message=preg_replace('~((?:[^\s<>]*:[^\s<>]*@)?[a-z0-9\-]+(?:\.[a-z0-9\-]+)+(?::\d*)?/[^\s<>]*)(?![^<>]*>)~iu', "<<$1>>", $message); // server/path given
	$message=preg_replace('~((?:[^\s<>]*:[^\s<>]*@)?[a-z0-9\-]+(?:\.[a-z0-9\-]+)+:\d+)(?![^<>]*>)~iu', "<<$1>>", $message); // server:port given
	$message=preg_replace('~([^\s<>]*:[^\s<>]*@[a-z0-9\-]+(?:\.[a-z0-9\-]+)+(?::\d+)?)(?![^<>]*>)~iu', "<<$1>>", $message); // au:th@server given
	// 3. likely servers without any hints but not filenames like *.rar zip exe etc.
	$message=preg_replace('~((?:[a-z0-9\-]+\.)*(?:[a-z2-7]{55}d|[a-z2-7]{16})\.onion)(?![^<>]*>)~iu', "<<$1>>", $message);// *.onion
	$message=preg_replace('~([a-z0-9\-]+(?:\.[a-z0-9\-]+)+(?:\.(?!rar|zip|exe|gz|7z|bat|doc)[a-z]{2,}))(?=[^a-z0-9\-\.]|$)(?![^<>]*>)~iu', "<<$1>>", $message);// xxx.yyy.zzz
	// Convert every <<....>> into proper links:
	$message=preg_replace_callback('/<<([^<>]+)>>/u',
		function ($matches){
			if(strpos($matches[1], '://')===false){
				return "<a href=\"http://$matches[1]\" target=\"_blank\" rel=\"noreferrer noopener\">$matches[1]</a>";
			}else{
				return "<a href=\"$matches[1]\" target=\"_blank\" rel=\"noreferrer noopener\">$matches[1]</a>";
			}
		}
	, $message);
	return $message;
}

function apply_mention($message){
	return preg_replace_callback('/\@([^\s]+)/iu', function ($matched){
		global $db;
		$nick=htmlspecialchars_decode($matched[1]);
		$rest='';
		for($i=0;$i<=3;++$i){
			//match case-sensitive present nicknames
			$stmt=$db->prepare('SELECT style FROM ' . PREFIX . 'sessions WHERE nickname=?;');
			$stmt->execute([$nick]);
			if($tmp=$stmt->fetch(PDO::FETCH_NUM)){
				return style_this(htmlspecialchars("@$nick"), $tmp[0]).$rest;
			}
			//match case-insensitive present nicknames
			$stmt=$db->prepare('SELECT style FROM ' . PREFIX . 'sessions WHERE LOWER(nickname)=LOWER(?);');
			$stmt->execute([$nick]);
			if($tmp=$stmt->fetch(PDO::FETCH_NUM)){
				return style_this(htmlspecialchars("@$nick"), $tmp[0]).$rest;
			}
			//match case-sensitive members
			$stmt=$db->prepare('SELECT style FROM ' . PREFIX . 'members WHERE nickname=?;');
			$stmt->execute([$nick]);
			if($tmp=$stmt->fetch(PDO::FETCH_NUM)){
				return style_this(htmlspecialchars("@$nick"), $tmp[0]).$rest;
			}
			//match case-insensitive members
			$stmt=$db->prepare('SELECT style FROM ' . PREFIX . 'members WHERE LOWER(nickname)=LOWER(?);');
			$stmt->execute([$nick]);
			if($tmp=$stmt->fetch(PDO::FETCH_NUM)){
				return style_this(htmlspecialchars("@$nick"), $tmp[0]).$rest;
			}
			if(strlen($nick)===1){
				break;
			}
			$rest=mb_substr($nick, -1).$rest;
			$nick=mb_substr($nick, 0, -1);
		}
		return $matched[0];
	}, $message);
}

function add_message($message, $recipient, $poster, $delstatus, $poststatus, $displaysend, $style){
	global $db;
	if($message===''){
		return false;
	}
	$newmessage=[
		'postdate'	=>time(),
		'poststatus'	=>$poststatus,
		'poster'	=>$poster,
		'recipient'	=>$recipient,
		'text'		=>"<span class=\"usermsg\">$displaysend".style_this($message, $style).'</span>',
		'delstatus'	=>$delstatus
	];
	//prevent posting the same message twice, if no other message was posted in-between.
	$stmt=$db->prepare('SELECT id FROM ' . PREFIX . 'messages WHERE poststatus=? AND poster=? AND recipient=? AND text=? AND id IN (SELECT * FROM (SELECT id FROM ' . PREFIX . 'messages ORDER BY id DESC LIMIT 1) AS t);');
	$stmt->execute([$newmessage['poststatus'], $newmessage['poster'], $newmessage['recipient'], $newmessage['text']]);
	if($stmt->fetch(PDO::FETCH_NUM)){
		return false;
	}
	write_message($newmessage);
	return true;
}

function add_system_message($mes){
	if($mes===''){
		return;
	}
	$sysmessage=[
		'postdate'	=>time(),
		'poststatus'	=>1,
		'poster'	=>'',
		'recipient'	=>'',
		'text'		=>"<span class=\"sysmsg\">$mes</span>",
		'delstatus'	=>4
	];
	write_message($sysmessage);
}

function write_message($message){
	global $db;
	if(MSGENCRYPTED){
        $message['text']=base64_encode(sodium_crypto_aead_aes256gcm_encrypt($message['text'], '', AES_IV, ENCRYPTKEY));
	}
	$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'messages (postdate, poststatus, poster, recipient, text, delstatus) VALUES (?, ?, ?, ?, ?, ?);');
	$stmt->execute([$message['postdate'], $message['poststatus'], $message['poster'], $message['recipient'], $message['text'], $message['delstatus']]);
	if($message['poststatus']<9 && get_setting('sendmail')){
		$subject='New Chat message';
		$headers='From: '.get_setting('mailsender')."\r\nX-Mailer: PHP/".phpversion()."\r\nContent-Type: text/html; charset=UTF-8\r\n";
		$body='<html><body style="background-color:#'.get_setting('colbg').';color:#'.get_setting('coltxt').";\">$message[text]</body></html>";
		mail(get_setting('mailreceiver'), $subject, $body, $headers);
	}
}

function clean_room(){
	global $db;
	$db->query('DELETE FROM ' . PREFIX . 'messages;');
	add_system_message(sprintf(get_setting('msgclean'), get_setting('chatname')));
}

function clean_selected($status, $nick){
	global $db;
	if(isset($_REQUEST['mid'])){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE id=? AND (poster=? OR recipient=? OR (poststatus<? AND delstatus<?));');
		foreach($_REQUEST['mid'] as $mid){
			$stmt->execute([$mid, $nick, $nick, $status, $status]);
		}
	}
}

function clean_inbox_selected(){
	global $U, $db;
	if(isset($_REQUEST['mid'])){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'inbox WHERE id=? AND recipient=?;');
		foreach($_REQUEST['mid'] as $mid){
			$stmt->execute([$mid, $U['nickname']]);
		}
	}
}

function del_all_messages($nick, $entry){
	global $db;
	if($nick==''){
		return;
	}
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE poster=? AND postdate>=?;');
	$stmt->execute([$nick, $entry]);
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'inbox WHERE poster=? AND postdate>=?;');
	$stmt->execute([$nick, $entry]);
}

function del_last_message(){
	global $U, $db;
	if($U['status']>1){
		$entry=0;
	}else{
		$entry=$U['entry'];
	}
	$stmt=$db->prepare('SELECT id FROM ' . PREFIX . 'messages WHERE poster=? AND postdate>=? ORDER BY id DESC LIMIT 1;');
	$stmt->execute([$U['nickname'], $entry]);
	if($id=$stmt->fetch(PDO::FETCH_NUM)){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE id=?;');
		$stmt->execute($id);
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'inbox WHERE postid=?;');
		$stmt->execute($id);
	}
}

function print_messages($delstatus=0){
	global $U, $db;
	$dateformat=get_setting('dateformat');
	if(!$U['embed'] && get_setting('imgembed')){
		$removeEmbed=true;
	}else{
		$removeEmbed=false;
	}
	if($U['timestamps'] && !empty($dateformat)){
		$timestamps=true;
	}else{
		$timestamps=false;
	}
	if($U['sortupdown']){
		$direction='ASC';
	}else{
		$direction='DESC';
	}
	if($U['status']>1){
		$entry=0;
	}else{
		$entry=$U['entry'];
	}
	echo '<div id="messages">';
	if($delstatus>0){
		$stmt=$db->prepare('SELECT postdate, id, text FROM ' . PREFIX . 'messages WHERE '.
		"(poststatus<? AND delstatus<?) OR ((poster=? OR recipient=?) AND postdate>=?) ORDER BY id $direction;");
		$stmt->execute([$U['status'], $delstatus, $U['nickname'], $U['nickname'], $entry]);
		while($message=$stmt->fetch(PDO::FETCH_ASSOC)){
			prepare_message_print($message, $removeEmbed);
			echo "<div class=\"msg\"><label><input type=\"checkbox\" name=\"mid[]\" value=\"$message[id]\">";
			if($timestamps){
				echo ' <small>'.date($dateformat, $message['postdate']).' - </small>';
			}
			echo " $message[text]</label></div>";
		}
	}else{
		$stmt=$db->prepare('SELECT id, postdate, text FROM ' . PREFIX . 'messages WHERE (poststatus<=? OR '.
		'(poststatus=9 AND ( (poster=? AND recipient NOT IN (SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=?) ) OR recipient=?) AND postdate>=?)'.
		') AND poster NOT IN (SELECT ign FROM ' . PREFIX . "ignored WHERE ignby=?) ORDER BY id $direction;");
		$stmt->execute([$U['status'], $U['nickname'], $U['nickname'], $U['nickname'], $entry, $U['nickname']]);
		while($message=$stmt->fetch(PDO::FETCH_ASSOC)){
			prepare_message_print($message, $removeEmbed);
			echo '<div class="msg">';
			if($timestamps){
				echo '<small>'.date($dateformat, $message['postdate']).' - </small>';
			}
			echo "$message[text]</div>";
		}
	}
	echo '</div>';
}

function prepare_message_print(&$message, $removeEmbed){
	if(MSGENCRYPTED){
        $message['text']=sodium_crypto_aead_aes256gcm_decrypt(base64_decode($message['text']), null, AES_IV, ENCRYPTKEY);
	}
	if($removeEmbed){
		$message['text']=preg_replace_callback('/<img src="([^"]+)"><\/a>/u',
			function ($matched){
				return "$matched[1]</a>";
			}
		, $message['text']);
	}
}

// this and that

function send_headers(){
	header('Content-Type: text/html; charset=UTF-8');
	header('Pragma: no-cache');
	header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
	header('Expires: 0');
	header('Referrer-Policy: no-referrer');
	header("Content-Security-Policy: default-src 'self'; img-src *; media-src *; script-src 'self'; style-src *");
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: sameorigin');
    header('X-XSS-Protection: 1; mode=block');
	if($_SERVER['REQUEST_METHOD']==='HEAD'){
		exit; // headers sent, no further processing needed
	}
}

function save_setup($C){
	global $db;
	//sanity checks and escaping
	foreach($C['msg_settings'] as $setting){
		$_REQUEST[$setting]=htmlspecialchars($_REQUEST[$setting]);
	}
	foreach($C['number_settings'] as $setting){
		settype($_REQUEST[$setting], 'int');
	}
	foreach($C['colour_settings'] as $setting){
		if(preg_match('/^#([a-f0-9]{6})$/i', $_REQUEST[$setting], $match)){
			$_REQUEST[$setting]=$match[1];
		}else{
			unset($_REQUEST[$setting]);
		}
	}
	settype($_REQUEST['guestaccess'], 'int');
	if(!preg_match('/^[01234]$/', $_REQUEST['guestaccess'])){
		unset($_REQUEST['guestaccess']);
	}elseif($_REQUEST['guestaccess']==4){
		$db->exec('DELETE FROM ' . PREFIX . 'sessions WHERE status<7;');
	}
	settype($_REQUEST['englobalpass'], 'int');
	settype($_REQUEST['captcha'], 'int');
	settype($_REQUEST['dismemcaptcha'], 'int');
	settype($_REQUEST['guestreg'], 'int');
	if(isset($_REQUEST['defaulttz'])){
		$tzs=timezone_identifiers_list();
		if(!in_array($_REQUEST['defaulttz'], $tzs)){
			unset($_REQUEST['defualttz']);
		}
	}
	$_REQUEST['rulestxt']=preg_replace("/(\r?\n|\r\n?)/u", '<br>', $_REQUEST['rulestxt']);
	$_REQUEST['chatname']=htmlspecialchars($_REQUEST['chatname']);
	$_REQUEST['redirect']=htmlspecialchars($_REQUEST['redirect']);
	if($_REQUEST['memberexpire']<5){
		$_REQUEST['memberexpire']=5;
	}
	if($_REQUEST['captchatime']<30){
		$_REQUEST['memberexpire']=30;
	}
	if($_REQUEST['defaultrefresh']<5){
		$_REQUEST['defaultrefresh']=5;
	}elseif($_REQUEST['defaultrefresh']>150){
		$_REQUEST['defaultrefresh']=150;
	}
	if($_REQUEST['maxname']<1){
		$_REQUEST['maxname']=1;
	}elseif($_REQUEST['maxname']>50){
		$_REQUEST['maxname']=50;
	}
	if($_REQUEST['maxmessage']<1){
		$_REQUEST['maxmessage']=1;
	}elseif($_REQUEST['maxmessage']>16000){
		$_REQUEST['maxmessage']=16000;
	}
		if($_REQUEST['numnotes']<1){
		$_REQUEST['numnotes']=1;
	}
	if(!valid_regex($_REQUEST['nickregex'])){
		unset($_REQUEST['nickregex']);
	}
	if(!valid_regex($_REQUEST['passregex'])){
		unset($_REQUEST['passregex']);
	}
	//save values
	foreach($C['settings'] as $setting){
		if(isset($_REQUEST[$setting])){
			update_setting($setting, $_REQUEST[$setting]);
		}
	}
}

function set_default_tz(){
	global $U;
	if(isset($U['tz'])){
		date_default_timezone_set($U['tz']);
	}else{
		date_default_timezone_set(get_setting('defaulttz'));
	}
}

function valid_admin(){
	global $U;
	if(isset($_REQUEST['session'])){
		parse_sessions();
	}
	if(!isset($U['session']) && isset($_REQUEST['nick']) && isset($_REQUEST['pass'])){
		create_session(true, $_REQUEST['nick'], $_REQUEST['pass']);
	}
	if(isset($U['status'])){
		if($U['status']>=7){
			return true;
		}
		send_access_denied();
	}
	return false;
}

function valid_nick($nick){
	$len=mb_strlen($nick);
	if($len<1 || $len>get_setting('maxname')){
		return false;
	}
	return preg_match('/'.get_setting('nickregex').'/u', $nick);
}

function valid_pass($pass){
	if(mb_strlen($pass)<get_setting('minpass')){
		return false;
	}
	return preg_match('/'.get_setting('passregex').'/u', $pass);
}

function valid_regex(&$regex){
	$regex=preg_replace('~(^|[^\\\\])/~', "$1\/u", $regex); // Escape "/" if not yet escaped
	return (@preg_match("/$_REQUEST[match]/u", '') !== false);
}

function get_timeout($lastpost, $expire){
	$s=($lastpost+60*$expire)-time();
	$m=floor($s/60);
	$s%=60;
	if($s<10){
		$s="0$s";
	}
	if($m>60){
		$h=floor($m/60);
		$m%=60;
		if($m<10){
			$m="0$m";
		}
		echo "$h:$m:$s";
	}else{
		echo "$m:$s";
	}
}

function print_colours(){
	global $I;
	// Prints a short list with selected named HTML colours and filters out illegible text colours for the given background.
	// It's a simple comparison of weighted grey values. This is not very accurate but gets the job done well enough.
	// name=>[colour, greyval(colour)]
	$colours=['Beige'=>['F5F5DC', 242.25], 'Black'=>['000000', 0], 'Blue'=>['0000FF', 28.05], 'BlueViolet'=>['8A2BE2', 91.63], 'Brown'=>['A52A2A', 78.9], 'Cyan'=>['00FFFF', 178.5], 'DarkBlue'=>['00008B', 15.29], 'DarkGreen'=>['006400', 59], 'DarkRed'=>['8B0000', 41.7], 'DarkViolet'=>['9400D3', 67.61], 'DeepSkyBlue'=>['00BFFF', 140.74], 'Gold'=>['FFD700', 203.35], 'Grey'=>['808080', 128], 'Green'=>['008000', 75.52], 'HotPink'=>['FF69B4', 158.25], 'Indigo'=>['4B0082', 36.8], 'LightBlue'=>['ADD8E6', 204.64], 'LightGreen'=>['90EE90', 199.46], 'LimeGreen'=>['32CD32', 141.45], 'Magenta'=>['FF00FF', 104.55], 'Olive'=>['808000', 113.92], 'Orange'=>['FFA500', 173.85], 'OrangeRed'=>['FF4500', 117.21], 'Purple'=>['800080', 52.48], 'Red'=>['FF0000', 76.5], 'RoyalBlue'=>['4169E1', 106.2], 'SeaGreen'=>['2E8B57', 105.38], 'Sienna'=>['A0522D', 101.33], 'Silver'=>['C0C0C0', 192], 'Tan'=>['D2B48C', 184.6], 'Teal'=>['008080', 89.6], 'Violet'=>['EE82EE', 174.28], 'White'=>['FFFFFF', 255], 'Yellow'=>['FFFF00', 226.95], 'YellowGreen'=>['9ACD32', 172.65]];
	$greybg=greyval(get_setting('colbg'));
	foreach($colours as $name=>$colour){
		if(abs($greybg-$colour[1])>75){
			echo "<option value=\"$colour[0]\" style=\"color:#$colour[0];\">$I[$name]</option>";
		}
	}
}

function greyval($colour){
	return hexdec(substr($colour, 0, 2))*.3+hexdec(substr($colour, 2, 2))*.59+hexdec(substr($colour, 4, 2))*.11;
}

function style_this($text, $styleinfo){
	return "<span style=\"$styleinfo\">$text</span>";
}

function check_init(){
	global $db;
	return @$db->query('SELECT null FROM ' . PREFIX . 'settings LIMIT 1;');
}

// run every minute doing various database cleanup task
function cron(){
	global $db;
	$time=time();
	if(get_setting('nextcron')>$time){
		return;
	}
	update_setting('nextcron', $time+10);
	// delete old sessions
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'sessions WHERE (status<=2 AND lastpost<(?-60*(SELECT value FROM ' . PREFIX . "settings WHERE setting='guestexpire'))) OR (status>2 AND lastpost<(?-60*(SELECT value FROM " . PREFIX . "settings WHERE setting='memberexpire')));");
	$stmt->execute([$time, $time]);
	// delete old messages
	$limit=get_setting('messagelimit');
	$stmt=$db->query('SELECT id FROM ' . PREFIX . "messages WHERE poststatus=1 ORDER BY id DESC LIMIT 1 OFFSET $limit;");
	if($id=$stmt->fetch(PDO::FETCH_NUM)){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE id<=?;');
		$stmt->execute($id);
	}
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE id IN (SELECT * FROM (SELECT id FROM ' . PREFIX . 'messages WHERE postdate<(?-60*(SELECT value FROM ' . PREFIX . "settings WHERE setting='messageexpire'))) AS t);");
	$stmt->execute([$time]);
	// delete expired ignored people
	$result=$db->query('SELECT id FROM ' . PREFIX . 'ignored WHERE ign NOT IN (SELECT nickname FROM ' . PREFIX . 'sessions UNION SELECT nickname FROM ' . PREFIX . 'members UNION SELECT poster FROM ' . PREFIX . 'messages) OR ignby NOT IN (SELECT nickname FROM ' . PREFIX . 'sessions UNION SELECT nickname FROM ' . PREFIX . 'members UNION SELECT poster FROM ' . PREFIX . 'messages);');
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'ignored WHERE id=?;');
	while($tmp=$result->fetch(PDO::FETCH_NUM)){
		$stmt->execute($tmp);
	}
	// delete files that do not belong to any message
	$result=$db->query('SELECT id FROM ' . PREFIX . 'files WHERE postid NOT IN (SELECT id FROM ' . PREFIX . 'messages UNION SELECT postid FROM ' . PREFIX . 'inbox);');
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'files WHERE id=?;');
	while($tmp=$result->fetch(PDO::FETCH_NUM)){
		$stmt->execute($tmp);
	}
	// delete old notes
	$limit=get_setting('numnotes');
	$db->exec('DELETE FROM ' . PREFIX . 'notes WHERE type!=2 AND id NOT IN (SELECT * FROM ( (SELECT id FROM ' . PREFIX . "notes WHERE type=0 ORDER BY id DESC LIMIT $limit) UNION (SELECT id FROM " . PREFIX . "notes WHERE type=1 ORDER BY id DESC LIMIT $limit) ) AS t);");
	$result=$db->query('SELECT editedby, COUNT(*) AS cnt FROM ' . PREFIX . "notes WHERE type=2 GROUP BY editedby HAVING cnt>$limit;");
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'notes WHERE type=2 AND editedby=? AND id NOT IN (SELECT * FROM (SELECT id FROM ' . PREFIX . "notes WHERE type=2 AND editedby=? ORDER BY id DESC LIMIT $limit) AS t);");
	while($tmp=$result->fetch(PDO::FETCH_NUM)){
		$stmt->execute([$tmp[0], $tmp[0]]);
	}
	// delete old captchas
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'captcha WHERE time<(?-(SELECT value FROM ' . PREFIX . "settings WHERE setting='captchatime'));");
	$stmt->execute([$time]);
}

function destroy_chat($C){
	global $I, $db, $memcached;
	setcookie(COOKIENAME, false);
	$_REQUEST['session']='';
	print_start('destory');
	$db->exec('DROP TABLE ' . PREFIX . 'captcha;');
	$db->exec('DROP TABLE ' . PREFIX . 'files;');
	$db->exec('DROP TABLE ' . PREFIX . 'filter;');
	$db->exec('DROP TABLE ' . PREFIX . 'ignored;');
	$db->exec('DROP TABLE ' . PREFIX . 'inbox;');
	$db->exec('DROP TABLE ' . PREFIX . 'linkfilter;');
	$db->exec('DROP TABLE ' . PREFIX . 'members;');
	$db->exec('DROP TABLE ' . PREFIX . 'messages;');
	$db->exec('DROP TABLE ' . PREFIX . 'notes;');
	$db->exec('DROP TABLE ' . PREFIX . 'sessions;');
	$db->exec('DROP TABLE ' . PREFIX . 'settings;');
	if(MEMCACHED){
		$memcached->delete(DBNAME . '-' . PREFIX . 'filter');
		$memcached->delete(DBANEM . '-' . PREFIX . 'linkfilter');
		foreach($C['settings'] as $setting){
			$memcached->delete(DBNAME . '-' . PREFIX . "settings-$setting");
		}
		$memcached->delete(DBNAME . '-' . PREFIX . 'settings-dbversion');
		$memcached->delete(DBNAME . '-' . PREFIX . 'settings-msgencrypted');
		$memcached->delete(DBNAME . '-' . PREFIX . 'settings-nextcron');
	}
	echo "<h2>$I[destroyed]</h2><br><br><br>";
	echo form('setup').submit($I['init']).'</form>'.credit();
	print_end();
}

function init_chat(){
	global $I, $db;
	$suwrite='';
	if(check_init()){
		$suwrite=$I['initdbexist'];
		$result=$db->query('SELECT null FROM ' . PREFIX . 'members WHERE status=8;');
		if($result->fetch(PDO::FETCH_NUM)){
			$suwrite=$I['initsuexist'];
		}
	}elseif(!preg_match('/^[a-z0-9]{1,20}$/i', $_REQUEST['sunick'])){
		$suwrite=sprintf($I['invalnick'], 20, '^[A-Za-z1-9]*$');
	}elseif(mb_strlen($_REQUEST['supass'])<5){
		$suwrite=sprintf($I['invalpass'], 5, '.*');
	}elseif($_REQUEST['supass']!==$_REQUEST['supassc']){
		$suwrite=$I['noconfirm'];
	}else{
		ignore_user_abort(true);
		set_time_limit(0);
		if(DBDRIVER===0){//MySQL
			$memengine=' ENGINE=MEMORY';
			$diskengine=' ENGINE=InnoDB';
			$charset=' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
			$primary='integer PRIMARY KEY AUTO_INCREMENT';
			$longtext='longtext';
		}elseif(DBDRIVER===1){//PostgreSQL
			$memengine='';
			$diskengine='';
			$charset='';
			$primary='serial PRIMARY KEY';
			$longtext='text';
		}else{//SQLite
			$memengine='';
			$diskengine='';
			$charset='';
			$primary='integer PRIMARY KEY';
			$longtext='text';
		}
		$db->exec('CREATE TABLE ' . PREFIX . "captcha (id $primary, time integer NOT NULL, code char(5) NOT NULL)$memengine$charset;");
		$db->exec('CREATE TABLE ' . PREFIX . "files (id $primary, postid integer NOT NULL UNIQUE, filename varchar(255) NOT NULL, hash char(40) NOT NULL, type varchar(255) NOT NULL, data $longtext NOT NULL)$diskengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'files_hash ON ' . PREFIX . 'files(hash);');
		$db->exec('CREATE TABLE ' . PREFIX . "filter (id $primary, filtermatch varchar(255) NOT NULL, filterreplace text NOT NULL, allowinpm smallint NOT NULL, regex smallint NOT NULL, kick smallint NOT NULL, cs smallint NOT NULL)$diskengine$charset;");
		$db->exec('CREATE TABLE ' . PREFIX . "ignored (id $primary, ign varchar(50) NOT NULL, ignby varchar(50) NOT NULL)$diskengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'ign ON ' . PREFIX . 'ignored(ign);');
		$db->exec('CREATE INDEX ' . PREFIX . 'ignby ON ' . PREFIX . 'ignored(ignby);');
		$db->exec('CREATE TABLE ' . PREFIX . "inbox (id $primary, postdate integer NOT NULL, postid integer NOT NULL UNIQUE, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text text NOT NULL)$diskengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'inbox_poster ON ' . PREFIX . 'inbox(poster);');
		$db->exec('CREATE INDEX ' . PREFIX . 'inbox_recipient ON ' . PREFIX . 'inbox(recipient);');
		$db->exec('CREATE TABLE ' . PREFIX . "linkfilter (id $primary, filtermatch varchar(255) NOT NULL, filterreplace varchar(255) NOT NULL, regex smallint NOT NULL)$diskengine$charset;");
		$db->exec('CREATE TABLE ' . PREFIX . "members (id $primary, nickname varchar(50) NOT NULL UNIQUE, passhash varchar(255) NOT NULL, status smallint NOT NULL, refresh smallint NOT NULL, bgcolour char(6) NOT NULL, regedby varchar(50) DEFAULT '', lastlogin integer DEFAULT 0, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, style varchar(255) NOT NULL, nocache smallint NOT NULL, tz varchar(255) NOT NULL, eninbox smallint NOT NULL, sortupdown smallint NOT NULL, hidechatters smallint NOT NULL, nocache_old smallint NOT NULL)$diskengine$charset;");
		$db->exec('ALTER TABLE ' . PREFIX . 'inbox ADD FOREIGN KEY (recipient) REFERENCES ' . PREFIX . 'members(nickname) ON DELETE CASCADE ON UPDATE CASCADE;');
		$db->exec('CREATE TABLE ' . PREFIX . "messages (id $primary, postdate integer NOT NULL, poststatus smallint NOT NULL, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text text NOT NULL, delstatus smallint NOT NULL)$diskengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'poster ON ' . PREFIX . 'messages (poster);');
		$db->exec('CREATE INDEX ' . PREFIX . 'recipient ON ' . PREFIX . 'messages(recipient);');
		$db->exec('CREATE INDEX ' . PREFIX . 'postdate ON ' . PREFIX . 'messages(postdate);');
		$db->exec('CREATE INDEX ' . PREFIX . 'poststatus ON ' . PREFIX . 'messages(poststatus);');
		$db->exec('CREATE TABLE ' . PREFIX . "notes (id $primary, type smallint NOT NULL, lastedited integer NOT NULL, editedby varchar(50) NOT NULL, text text NOT NULL)$diskengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'notes_type ON ' . PREFIX . 'notes(type);');
		$db->exec('CREATE INDEX ' . PREFIX . 'notes_editedby ON ' . PREFIX . 'notes(editedby);');
		$db->exec('CREATE TABLE ' . PREFIX . "sessions (id $primary, session char(32) NOT NULL UNIQUE, nickname varchar(50) NOT NULL UNIQUE, status smallint NOT NULL, refresh smallint NOT NULL, style varchar(255) NOT NULL, lastpost integer NOT NULL, passhash varchar(255) NOT NULL, postid char(6) NOT NULL DEFAULT '000000', useragent varchar(255) NOT NULL, kickmessage varchar(255) DEFAULT '', bgcolour char(6) NOT NULL, entry integer NOT NULL, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, ip varchar(45) NOT NULL, nocache smallint NOT NULL, tz varchar(255) NOT NULL, eninbox smallint NOT NULL, sortupdown smallint NOT NULL, hidechatters smallint NOT NULL, nocache_old smallint NOT NULL)$memengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'status ON ' . PREFIX . 'sessions(status);');
		$db->exec('CREATE INDEX ' . PREFIX . 'lastpost ON ' . PREFIX . 'sessions(lastpost);');
		$db->exec('CREATE INDEX ' . PREFIX . 'incognito ON ' . PREFIX . 'sessions(incognito);');
		$db->exec('CREATE TABLE ' . PREFIX . "settings (setting varchar(50) NOT NULL PRIMARY KEY, value text NOT NULL)$diskengine$charset;");

		$settings=[
			['guestaccess', '0'],
			['globalpass', ''],
			['englobalpass', '0'],
			['captcha', '0'],
			['dateformat', 'm-d H:i:s'],
			['rulestxt', ''],
			['msgencrypted', '0'],
			['dbversion', DBVERSION],
			['css', ''],
			['memberexpire', '60'],
			['guestexpire', '15'],
			['kickpenalty', '10'],
			['entrywait', '120'],
			['messageexpire', '14400'],
			['messagelimit', '150'],
			['maxmessage', 2000],
			['captchatime', '600'],
			['colbg', '000000'],
			['coltxt', 'FFFFFF'],
			['maxname', '20'],
			['minpass', '5'],
			['defaultrefresh', '20'],
			['dismemcaptcha', '0'],
			['suguests', '0'],
			['imgembed', '1'],
			['timestamps', '1'],
			['trackip', '0'],
			['captchachars', '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'],
			['memkick', '1'],
			['forceredirect', '0'],
			['redirect', ''],
			['incognito', '1'],
			['chatname', 'My Chat'],
			['topic', ''],
			['msgsendall', $I['sendallmsg']],
			['msgsendmem', $I['sendmemmsg']],
			['msgsendmod', $I['sendmodmsg']],
			['msgsendadm', $I['sendadmmsg']],
			['msgsendprv', $I['sendprvmsg']],
			['msgenter', $I['entermsg']],
			['msgexit', $I['exitmsg']],
			['msgmemreg', $I['memregmsg']],
			['msgsureg', $I['suregmsg']],
			['msgkick', $I['kickmsg']],
			['msgmultikick', $I['multikickmsg']],
			['msgallkick', $I['allkickmsg']],
			['msgclean', $I['cleanmsg']],
			['numnotes', '3'],
			['mailsender', 'www-data <www-data@localhost>'],
			['mailreceiver', 'Webmaster <webmaster@localhost>'],
			['sendmail', '0'],
			['modfallback', '1'],
			['guestreg', '0'],
			['disablepm', '0'],
			['disabletext', "<h1>$I[disabledtext]</h1>"],
			['defaulttz', 'UTC'],
			['eninbox', '0'],
			['passregex', '.*'],
			['nickregex', '^[A-Za-z0-9]*$'],
			['externalcss', ''],
			['enablegreeting', '0'],
			['sortupdown', '0'],
			['hidechatters', '0'],
			['enfileupload', '0'],
			['msgattache', '%2$s [%1$s]'],
			['maxuploadsize', '1024'],
			['nextcron', '0'],
			['personalnotes', '1'],
			['filtermodkick', '0'],
		];
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'settings (setting, value) VALUES (?, ?);');
		foreach($settings as $pair){
			$stmt->execute($pair);
		}
		$reg=[
			'nickname'	=>$_REQUEST['sunick'],
			'passhash'	=>password_hash($_REQUEST['supass'], PASSWORD_DEFAULT),
			'status'	=>8,
			'refresh'	=>20,
			'bgcolour'	=>'000000',
			'timestamps'	=>1,
			'style'		=>'color:#FFFFFF;',
			'embed'		=>1,
			'incognito'	=>0,
			'nocache'	=>0,
			'nocache_old'	=>1,
			'tz'		=>'UTC',
			'eninbox'	=>0,
			'sortupdown'	=>0,
			'hidechatters'	=>0,
		];
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, timestamps, style, embed, incognito, nocache, tz, eninbox, sortupdown, hidechatters, nocache_old) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		$stmt->execute([$reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['bgcolour'], $reg['timestamps'], $reg['style'], $reg['embed'], $reg['incognito'], $reg['nocache'], $reg['tz'], $reg['eninbox'], $reg['sortupdown'], $reg['hidechatters'], $reg['nocache_old']]);
		$suwrite=$I['susuccess'];
	}
	print_start('init');
	echo "<h2>$I[init]</h2><br><h3>$I[sulogin]</h3>$suwrite<br><br><br>";
	echo form('setup').submit($I['initgosetup']).'</form>'.credit();
	print_end();
}

function update_db(){
	global $I, $db, $memcached;
	$dbversion=(int) get_setting('dbversion');
	$msgencrypted=(bool) get_setting('msgencrypted');
	if($dbversion>=DBVERSION && $msgencrypted===MSGENCRYPTED){
		return;
	}
	ignore_user_abort(true);
	set_time_limit(0);
	if(DBDRIVER===0){//MySQL
		$memengine=' ENGINE=MEMORY';
		$diskengine=' ENGINE=InnoDB';
		$charset=' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
		$primary='integer PRIMARY KEY AUTO_INCREMENT';
		$longtext='longtext';
	}elseif(DBDRIVER===1){//PostgreSQL
		$memengine='';
		$diskengine='';
		$charset='';
		$primary='serial PRIMARY KEY';
		$longtext='text';
	}else{//SQLite
		$memengine='';
		$diskengine='';
		$charset='';
		$primary='integer PRIMARY KEY';
		$longtext='text';
	}
	$msg='';
	if($dbversion<2){
		$db->exec('CREATE TABLE IF NOT EXISTS ' . PREFIX . "ignored (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, ignored varchar(50) NOT NULL, `by` varchar(50) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
	}
	if($dbversion<3){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('rulestxt', '');");
	}
	if($dbversion<4){
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD incognito smallint NOT NULL;');
	}
	if($dbversion<5){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('globalpass', '');");
	}
	if($dbversion<6){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('dateformat', 'm-d H:i:s');");
	}
	if($dbversion<7){
		$db->exec('ALTER TABLE ' . PREFIX . 'captcha ADD code char(5) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;');
	}
	if($dbversion<8){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('captcha', '0'), ('englobalpass', '0');");
		$ga=(int) get_setting('guestaccess');
		if($ga===-1){
			update_setting('guestaccess', 0);
			update_setting('englobalpass', 1);
		}elseif($ga===4){
			update_setting('guestaccess', 1);
			update_setting('englobalpass', 2);
		}
	}
	if($dbversion<9){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting,value) VALUES ('msgencrypted', '0');");
		$db->exec('ALTER TABLE ' . PREFIX . 'settings MODIFY value varchar(20000) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'messages DROP postid;');
	}
	if($dbversion<10){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('css', ''), ('memberexpire', '60'), ('guestexpire', '15'), ('kickpenalty', '10'), ('entrywait', '120'), ('messageexpire', '14400'), ('messagelimit', '150'), ('maxmessage', 2000), ('captchatime', '600');");
	}
	if($dbversion<11){
		$db->exec('ALTER TABLE ' , PREFIX . 'captcha CHARACTER SET utf8 COLLATE utf8_bin;');
		$db->exec('ALTER TABLE ' . PREFIX . 'filter CHARACTER SET utf8 COLLATE utf8_bin;');
		$db->exec('ALTER TABLE ' . PREFIX . 'ignored CHARACTER SET utf8 COLLATE utf8_bin;');
		$db->exec('ALTER TABLE ' . PREFIX . 'messages CHARACTER SET utf8 COLLATE utf8_bin;');
		$db->exec('ALTER TABLE ' . PREFIX . 'notes CHARACTER SET utf8 COLLATE utf8_bin;');
		$db->exec('ALTER TABLE ' . PREFIX . 'settings CHARACTER SET utf8 COLLATE utf8_bin;');
		$db->exec('CREATE TABLE ' . PREFIX . "linkfilter (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, `match` varchar(255) NOT NULL, `replace` varchar(255) NOT NULL, regex smallint NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_bin;");
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD style varchar(255) NOT NULL;');
		$result=$db->query('SELECT * FROM ' . PREFIX . 'members;');
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET style=? WHERE id=?;');
		$F=load_fonts();
		while($temp=$result->fetch(PDO::FETCH_ASSOC)){
			$style="color:#$temp[colour];";
			if(isset($F[$temp['fontface']])){
				$style.=$F[$temp['fontface']];
			}
			if(strpos($temp['fonttags'], 'i')!==false){
				$style.='font-style:italic;';
			}
			if(strpos($temp['fonttags'], 'b')!==false){
				$style.='font-weight:bold;';
			}
			$stmt->execute([$style, $temp['id']]);
		}
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('colbg', '000000'), ('coltxt', 'FFFFFF'), ('maxname', '20'), ('minpass', '5'), ('defaultrefresh', '20'), ('dismemcaptcha', '0'), ('suguests', '0'), ('imgembed', '1'), ('timestamps', '1'), ('trackip', '0'), ('captchachars', '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), ('memkick', '1'), ('forceredirect', '0'), ('redirect', ''), ('incognito', '1');");
	}
	if($dbversion<12){
		$db->exec('ALTER TABLE ' . PREFIX . 'captcha MODIFY code char(5) NOT NULL, DROP INDEX id, ADD PRIMARY KEY (id) USING BTREE;');
		$db->exec('ALTER TABLE ' . PREFIX . 'captcha ENGINE=MEMORY;');
		$db->exec('ALTER TABLE ' . PREFIX . 'filter MODIFY id integer unsigned NOT NULL AUTO_INCREMENT, MODIFY `match` varchar(255) NOT NULL, MODIFY replace varchar(20000) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'ignored MODIFY ignored varchar(50) NOT NULL, MODIFY `by` varchar(50) NOT NULL, ADD INDEX(ignored), ADD INDEX(`by`);');
		$db->exec('ALTER TABLE ' . PREFIX . 'linkfilter MODIFY match varchar(255) NOT NULL, MODIFY replace varchar(255) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'messages MODIFY poster varchar(50) NOT NULL, MODIFY recipient varchar(50) NOT NULL, MODIFY text varchar(20000) NOT NULL, ADD INDEX(poster), ADD INDEX(recipient), ADD INDEX(postdate), ADD INDEX(poststatus);');
		$db->exec('ALTER TABLE ' . PREFIX . 'notes MODIFY type char(5) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY editedby varchar(50) NOT NULL, MODIFY text varchar(20000) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'settings MODIFY id integer unsigned NOT NULL, MODIFY setting varchar(50) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY value varchar(20000) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'settings DROP PRIMARY KEY, DROP id, ADD PRIMARY KEY(setting);');
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('chatname', 'My Chat'), ('topic', ''), ('msgsendall', '$I[sendallmsg]'), ('msgsendmem', '$I[sendmemmsg]'), ('msgsendmod', '$I[sendmodmsg]'), ('msgsendadm', '$I[sendadmmsg]'), ('msgsendprv', '$I[sendprvmsg]'), ('numnotes', '3');");
	}
	if($dbversion<13){
		$db->exec('ALTER TABLE ' . PREFIX . 'filter CHANGE `match` filtermatch varchar(255) NOT NULL, CHANGE `replace` filterreplace varchar(20000) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'ignored CHANGE ignored ign varchar(50) NOT NULL, CHANGE `by` ignby varchar(50) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'linkfilter CHANGE `match` filtermatch varchar(255) NOT NULL, CHANGE `replace` filterreplace varchar(255) NOT NULL;');
	}
	if($dbversion<14){
		if(MEMCACHED){
			$memcached->delete(DBNAME . '-' . PREFIX . 'members');
			$memcached->delete(DBNAME . '-' . PREFIX . 'ignored');
		}
		if(DBDRIVER===0){//MySQL - previously had a wrong SQL syntax and the captcha table was not created.
			$db->exec('CREATE TABLE IF NOT EXISTS ' . PREFIX . 'captcha (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, time integer unsigned NOT NULL, code char(5) NOT NULL) ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_bin;');
		}
	}
	if($dbversion<15){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('mailsender', 'www-data <www-data@localhost>'), ('mailreceiver', 'Webmaster <webmaster@localhost>'), ('sendmail', '0'), ('modfallback', '1'), ('guestreg', '0');");
	}
	if($dbversion<17){
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN nocache smallint NOT NULL DEFAULT 0;');
	}
	if($dbversion<18){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('disablepm', '0');");
	}
	if($dbversion<19){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('disabletext', '<h1>$I[disabledtext]</h1>');");
	}
	if($dbversion<20){
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN tz smallint NOT NULL DEFAULT 0;');
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('defaulttz', 'UTC');");
	}
	if($dbversion<21){
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN eninbox smallint NOT NULL DEFAULT 0;');
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('eninbox', '0');");
		if(DBDRIVER===0){
			$db->exec('CREATE TABLE ' . PREFIX . "inbox (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, postid integer unsigned NOT NULL, postdate integer unsigned NOT NULL, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text varchar(20000) NOT NULL, INDEX(postid), INDEX(poster), INDEX(recipient)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
		}else{
			$db->exec('CREATE TABLE ' . PREFIX . "inbox (id $primary, postdate integer NOT NULL, postid integer NOT NULL, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text varchar(20000) NOT NULL);");
			$db->exec('CREATE INDEX ' . PREFIX . 'inbox_postid ON ' . PREFIX . 'inbox(postid);');
			$db->exec('CREATE INDEX ' . PREFIX . 'inbox_poster ON ' . PREFIX . 'inbox(poster);');
			$db->exec('CREATE INDEX ' . PREFIX . 'inbox_recipient ON ' . PREFIX . 'inbox(recipient);');
		}
	}
	if($dbversion<23){
		$db->exec('DELETE FROM ' . PREFIX . "settings WHERE setting='enablejs';");
	}
	if($dbversion<25){
		$db->exec('DELETE FROM ' . PREFIX . "settings WHERE setting='keeplimit';");
	}
	if($dbversion<26){
		$db->exec('INSERT INTO ' . PREFIX . 'settings (setting, value) VALUES (\'passregex\', \'.*\'), (\'nickregex\', \'^[A-Za-z0-9]*$\');');
	}
	if($dbversion<27){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('externalcss', '');");
	}
	if($dbversion<28){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('enablegreeting', '0');");
	}
	if($dbversion<29){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('sortupdown', '0');");
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN sortupdown smallint NOT NULL DEFAULT 0;');
	}
	if($dbversion<30){
		$db->exec('ALTER TABLE ' . PREFIX . 'filter ADD COLUMN cs smallint NOT NULL DEFAULT 0;');
		if(MEMCACHED){
			$memcached->delete(DBNAME . '-' . PREFIX . "filter");
		}
	}
	if($dbversion<31){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('hidechatters', '0');");
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN hidechatters smallint NOT NULL DEFAULT 0;');
	}
	if($dbversion<32 && DBDRIVER===0){
		//recreate db in utf8mb4
		try{
			$olddb=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
		}catch(PDOException $e){
			send_fatal_error($I['nodb']);
		}
		$db->exec('DROP TABLE ' . PREFIX . 'captcha;');
		$db->exec('CREATE TABLE ' . PREFIX . "captcha (id integer PRIMARY KEY AUTO_INCREMENT, time integer NOT NULL, code char(5) NOT NULL) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
		$result=$olddb->query('SELECT filtermatch, filterreplace, allowinpm, regex, kick, cs FROM ' . PREFIX . 'filter;');
		$data=$result->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'filter;');
		$db->exec('CREATE TABLE ' . PREFIX . "filter (id integer PRIMARY KEY AUTO_INCREMENT, filtermatch varchar(255) NOT NULL, filterreplace text NOT NULL, allowinpm smallint NOT NULL, regex smallint NOT NULL, kick smallint NOT NULL, cs smallint NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'filter (filtermatch, filterreplace, allowinpm, regex, kick, cs) VALUES(?, ?, ?, ?, ?, ?);');
		foreach($data as $tmp){
			$stmt->execute($tmp);
		}
		$result=$olddb->query('SELECT ign, ignby FROM ' . PREFIX . 'ignored;');
		$data=$result->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'ignored;');
		$db->exec('CREATE TABLE ' . PREFIX . "ignored (id integer PRIMARY KEY AUTO_INCREMENT, ign varchar(50) NOT NULL, ignby varchar(50) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'ignored (ign, ignby) VALUES(?, ?);');
		foreach($data as $tmp){
			$stmt->execute($tmp);
		}
		$db->exec('CREATE INDEX ' . PREFIX . 'ign ON ' . PREFIX . 'ignored(ign);');
		$db->exec('CREATE INDEX ' . PREFIX . 'ignby ON ' . PREFIX . 'ignored(ignby);');
		$result=$olddb->query('SELECT postdate, postid, poster, recipient, text FROM ' . PREFIX . 'inbox;');
		$data=$result->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'inbox;');
		$db->exec('CREATE TABLE ' . PREFIX . "inbox (id integer PRIMARY KEY AUTO_INCREMENT, postdate integer NOT NULL, postid integer NOT NULL UNIQUE, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text text NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'inbox (postdate, postid, poster, recipient, text) VALUES(?, ?, ?, ?, ?);');
		foreach($data as $tmp){
			$stmt->execute($tmp);
		}
		$db->exec('CREATE INDEX ' . PREFIX . 'inbox_poster ON ' . PREFIX . 'inbox(poster);');
		$db->exec('CREATE INDEX ' . PREFIX . 'inbox_recipient ON ' . PREFIX . 'inbox(recipient);');
		$result=$olddb->query('SELECT filtermatch, filterreplace, regex FROM ' . PREFIX . 'linkfilter;');
		$data=$result->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'linkfilter;');
		$db->exec('CREATE TABLE ' . PREFIX . "linkfilter (id integer PRIMARY KEY AUTO_INCREMENT, filtermatch varchar(255) NOT NULL, filterreplace varchar(255) NOT NULL, regex smallint NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'linkfilter (filtermatch, filterreplace, regex) VALUES(?, ?, ?);');
		foreach($data as $tmp){
			$stmt->execute($tmp);
		}
		$result=$olddb->query('SELECT nickname, passhash, status, refresh, bgcolour, regedby, lastlogin, timestamps, embed, incognito, style, nocache, tz, eninbox, sortupdown, hidechatters FROM ' . PREFIX . 'members;');
		$data=$result->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'members;');
		$db->exec('CREATE TABLE ' . PREFIX . "members (id integer PRIMARY KEY AUTO_INCREMENT, nickname varchar(50) NOT NULL UNIQUE, passhash char(32) NOT NULL, status smallint NOT NULL, refresh smallint NOT NULL, bgcolour char(6) NOT NULL, regedby varchar(50) DEFAULT '', lastlogin integer DEFAULT 0, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, style varchar(255) NOT NULL, nocache smallint NOT NULL, tz smallint NOT NULL, eninbox smallint NOT NULL, sortupdown smallint NOT NULL, hidechatters smallint NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, regedby, lastlogin, timestamps, embed, incognito, style, nocache, tz, eninbox, sortupdown, hidechatters) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		foreach($data as $tmp){
			$stmt->execute($tmp);
		}
		$result=$olddb->query('SELECT postdate, poststatus, poster, recipient, text, delstatus FROM ' . PREFIX . 'messages;');
		$data=$result->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'messages;');
		$db->exec('CREATE TABLE ' . PREFIX . "messages (id integer PRIMARY KEY AUTO_INCREMENT, postdate integer NOT NULL, poststatus smallint NOT NULL, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text text NOT NULL, delstatus smallint NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'messages (postdate, poststatus, poster, recipient, text, delstatus) VALUES(?, ?, ?, ?, ?, ?);');
		foreach($data as $tmp){
			$stmt->execute($tmp);
		}
		$db->exec('CREATE INDEX ' . PREFIX . 'poster ON ' . PREFIX . 'messages (poster);');
		$db->exec('CREATE INDEX ' . PREFIX . 'recipient ON ' . PREFIX . 'messages(recipient);');
		$db->exec('CREATE INDEX ' . PREFIX . 'postdate ON ' . PREFIX . 'messages(postdate);');
		$db->exec('CREATE INDEX ' . PREFIX . 'poststatus ON ' . PREFIX . 'messages(poststatus);');
		$result=$olddb->query('SELECT type, lastedited, editedby, text FROM ' . PREFIX . 'notes;');
		$data=$result->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'notes;');
		$db->exec('CREATE TABLE ' . PREFIX . "notes (id integer PRIMARY KEY AUTO_INCREMENT, type char(5) NOT NULL, lastedited integer NOT NULL, editedby varchar(50) NOT NULL, text text NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'notes (type, lastedited, editedby, text) VALUES(?, ?, ?, ?);');
		foreach($data as $tmp){
			$stmt->execute($tmp);
		}
		$result=$olddb->query('SELECT setting, value FROM ' . PREFIX . 'settings;');
		$data=$result->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'settings;');
		$db->exec('CREATE TABLE ' . PREFIX . "settings (setting varchar(50) NOT NULL PRIMARY KEY, value text NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'settings (setting, value) VALUES(?, ?);');
		foreach($data as $tmp){
			$stmt->execute($tmp);
		}
	}
	if($dbversion<33){
		$db->exec('CREATE TABLE ' . PREFIX . "files (id $primary, postid integer NOT NULL UNIQUE, filename varchar(255) NOT NULL, hash char(40) NOT NULL, type varchar(255) NOT NULL, data $longtext NOT NULL)$diskengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'files_hash ON ' . PREFIX . 'files(hash);');
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('enfileupload', '0'), ('msgattache', '%2\$s [%1\$s]'), ('maxuploadsize', '1024');");
	}
	if($dbversion<34){
		$msg.="<br>$I[cssupdate]";
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN nocache_old smallint NOT NULL DEFAULT 0;');
	}
	if($dbversion<37){
		$db->exec('ALTER TABLE ' . PREFIX . 'members MODIFY tz varchar(255) NOT NULL;');
		$db->exec('UPDATE ' . PREFIX . "members SET tz='UTC';");
		$db->exec('UPDATE ' . PREFIX . "settings SET value='UTC' WHERE setting='defaulttz';");
	}
	if($dbversion<38){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('nextcron', '0');");
		$db->exec('DELETE FROM ' . PREFIX . 'inbox WHERE recipient NOT IN (SELECT nickname FROM ' . PREFIX . 'members);'); // delete inbox of members who deleted themselves
	}
	if($dbversion<39){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('personalnotes', '1');");
		$result=$db->query('SELECT type, id FROM ' . PREFIX . 'notes;');
		while($tmp=$result->fetch(PDO::FETCH_NUM)){
			if($tmp[0]==='admin'){
				$tmp[0]=0;
			}else{
				$tmp[0]=1;
			}
			$data[]=$tmp;
		}
		$db->exec('ALTER TABLE ' . PREFIX . 'notes MODIFY type smallint NOT NULL;');
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'notes SET type=? WHERE id=?;');
		foreach($data as $tmp){
			$stmt->execute($tmp);
		}
		$db->exec('CREATE INDEX ' . PREFIX . 'notes_type ON ' . PREFIX . 'notes(type);');
		$db->exec('CREATE INDEX ' . PREFIX . 'notes_editedby ON ' . PREFIX . 'notes(editedby);');
	}
	if($dbversion<41){
		$db->exec('DROP TABLE ' . PREFIX . 'sessions;');
		$db->exec('CREATE TABLE ' . PREFIX . "sessions (id $primary, session char(32) NOT NULL UNIQUE, nickname varchar(50) NOT NULL UNIQUE, status smallint NOT NULL, refresh smallint NOT NULL, style varchar(255) NOT NULL, lastpost integer NOT NULL, passhash varchar(255) NOT NULL, postid char(6) NOT NULL DEFAULT '000000', useragent varchar(255) NOT NULL, kickmessage varchar(255) DEFAULT '', bgcolour char(6) NOT NULL, entry integer NOT NULL, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, ip varchar(45) NOT NULL, nocache smallint NOT NULL, tz varchar(255) NOT NULL, eninbox smallint NOT NULL, sortupdown smallint NOT NULL, hidechatters smallint NOT NULL, nocache_old smallint NOT NULL)$memengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'status ON ' . PREFIX . 'sessions(status);');
		$db->exec('CREATE INDEX ' . PREFIX . 'lastpost ON ' . PREFIX . 'sessions(lastpost);');
		$db->exec('CREATE INDEX ' . PREFIX . 'incognito ON ' . PREFIX . 'sessions(incognito);');
		$result=$db->query('SELECT nickname, passhash, status, refresh, bgcolour, regedby, lastlogin, timestamps, embed, incognito, style, nocache, nocache_old, tz, eninbox, sortupdown, hidechatters FROM ' . PREFIX . 'members;');
		$members=$result->fetchAll(PDO::FETCH_NUM);
		$result=$db->query('SELECT postdate, postid, poster, recipient, text FROM ' . PREFIX . 'inbox;');
		$inbox=$result->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'inbox;');
		$db->exec('DROP TABLE ' . PREFIX . 'members;');
		$db->exec('CREATE TABLE ' . PREFIX . "members (id $primary, nickname varchar(50) NOT NULL UNIQUE, passhash varchar(255) NOT NULL, status smallint NOT NULL, refresh smallint NOT NULL, bgcolour char(6) NOT NULL, regedby varchar(50) DEFAULT '', lastlogin integer DEFAULT 0, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, style varchar(255) NOT NULL, nocache smallint NOT NULL, nocache_old smallint NOT NULL, tz varchar(255) NOT NULL, eninbox smallint NOT NULL, sortupdown smallint NOT NULL, hidechatters smallint NOT NULL)$diskengine$charset");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, regedby, lastlogin, timestamps, embed, incognito, style, nocache, nocache_old, tz, eninbox, sortupdown, hidechatters) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		foreach($members as $tmp){
			$stmt->execute($tmp);
		}
		$db->exec('CREATE TABLE ' . PREFIX . "inbox (id $primary, postdate integer NOT NULL, postid integer NOT NULL UNIQUE, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text text NOT NULL)$diskengine$charset;");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'inbox (postdate, postid, poster, recipient, text) VALUES(?, ?, ?, ?, ?);');
		foreach($inbox as $tmp){
			$stmt->execute($tmp);
		}
		$db->exec('CREATE INDEX ' . PREFIX . 'inbox_poster ON ' . PREFIX . 'inbox(poster);');
		$db->exec('CREATE INDEX ' . PREFIX . 'inbox_recipient ON ' . PREFIX . 'inbox(recipient);');
		$db->exec('ALTER TABLE ' . PREFIX . 'inbox ADD FOREIGN KEY (recipient) REFERENCES ' . PREFIX . 'members(nickname) ON DELETE CASCADE ON UPDATE CASCADE;');
	}
	if($dbversion<42){
		$db->exec('INSERT IGNORE INTO ' . PREFIX . "settings (setting, value) VALUES ('filtermodkick', '1');");
	}
	update_setting('dbversion', DBVERSION);
	if($msgencrypted!==MSGENCRYPTED){
		if(!extension_loaded('sodium')){
			send_fatal_error($I['sodiumextrequired']);
		}
		$result=$db->query('SELECT id, text FROM ' . PREFIX . 'messages;');
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'messages SET text=? WHERE id=?;');
		while($message=$result->fetch(PDO::FETCH_ASSOC)){
			if(MSGENCRYPTED){
                $message['text']=base64_encode(sodium_crypto_aead_aes256gcm_encrypt($message['text'], '', AES_IV, ENCRYPTKEY));
			}else{
                $message['text']=sodium_crypto_aead_aes256gcm_decrypt(base64_decode($message['text']), null, AES_IV, ENCRYPTKEY);
			}
			$stmt->execute([$message['text'], $message['id']]);
		}
		$result=$db->query('SELECT id, text FROM ' . PREFIX . 'notes;');
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'notes SET text=? WHERE id=?;');
		while($message=$result->fetch(PDO::FETCH_ASSOC)){
			if(MSGENCRYPTED){
                $message['text']=base64_encode(sodium_crypto_aead_aes256gcm_encrypt($message['text'], '', AES_IV, ENCRYPTKEY));
			}else{
                $message['text']=sodium_crypto_aead_aes256gcm_decrypt(base64_decode($message['text']), null, AES_IV, ENCRYPTKEY);
			}
			$stmt->execute([$message['text'], $message['id']]);
		}
		update_setting('msgencrypted', (int) MSGENCRYPTED);
	}
	send_update($msg);
}

function get_setting($setting){
	global $db, $memcached;
	if(!MEMCACHED || !$value=$memcached->get(DBNAME . '-' . PREFIX . "settings-$setting")){
		$stmt=$db->prepare('SELECT value FROM ' . PREFIX . 'settings WHERE setting=?;');
		$stmt->execute([$setting]);
		$stmt->bindColumn(1, $value);
		$stmt->fetch(PDO::FETCH_BOUND);
		if(MEMCACHED){
			$memcached->set(DBNAME . '-' . PREFIX . "settings-$setting", $value);
		}
	}
	return $value;
}

function update_setting($setting, $value){
	global $db, $memcached;
	$stmt=$db->prepare('UPDATE ' . PREFIX . 'settings SET value=? WHERE setting=?;');
	$stmt->execute([$value, $setting]);
	if(MEMCACHED){
		$memcached->set(DBNAME . '-' . PREFIX . "settings-$setting", $value);
	}
}

// configuration, defaults and internals

function check_db(){
	global $I, $db, $memcached;
	$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT];
	try{
		if(DBDRIVER===0){
			if(!extension_loaded('pdo_mysql')){
				send_fatal_error($I['pdo_mysqlextrequired']);
			}
			$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';charset=utf8mb4', DBUSER, DBPASS, $options);
		}elseif(DBDRIVER===1){
			if(!extension_loaded('pdo_pgsql')){
				send_fatal_error($I['pdo_pgsqlextrequired']);
			}
			$db=new PDO('pgsql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, $options);
		}else{
			if(!extension_loaded('pdo_sqlite')){
				send_fatal_error($I['pdo_sqliteextrequired']);
			}
			$db=new PDO('sqlite:' . SQLITEDBFILE, NULL, NULL, $options);
		}
	}catch(PDOException $e){
		try{
			//Attempt to create database
			if(DBDRIVER===0){
				$db=new PDO('mysql:host=' . DBHOST, DBUSER, DBPASS, $options);
				if(false!==$db->exec('CREATE DATABASE ' . DBNAME)){
					$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';charset=utf8mb4', DBUSER, DBPASS, $options);
				}else{
					send_fatal_error($I['nodbsetup']);
				}

			}elseif(DBDRIVER===1){
				$db=new PDO('pgsql:host=' . DBHOST, DBUSER, DBPASS, $options);
				if(false!==$db->exec('CREATE DATABASE ' . DBNAME)){
					$db=new PDO('pgsql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, $options);
				}else{
					send_fatal_error($I['nodbsetup']);
				}
			}else{
				if(isset($_REQUEST['action']) && $_REQUEST['action']==='setup'){
					send_fatal_error($I['nodbsetup']);
				}else{
					send_fatal_error($I['nodb']);
				}
			}
		}catch(PDOException $e){
			if(isset($_REQUEST['action']) && $_REQUEST['action']==='setup'){
				send_fatal_error($I['nodbsetup']);
			}else{
				send_fatal_error($I['nodb']);
			}
		}
	}
	if(MEMCACHED){
		if(!extension_loaded('memcached')){
			send_fatal_error($I['memcachedextrequired']);
		}
		$memcached=new Memcached();
		$memcached->addServer(MEMCACHEDHOST, MEMCACHEDPORT);
	}
	if(!isset($_REQUEST['action']) || $_REQUEST['action']==='setup'){
		if(!check_init()){
			send_init();
		}
		update_db();
	}elseif($_REQUEST['action']==='init'){
		init_chat();
	}
}

function load_fonts(){
	return [
		'Arial'			=>"font-family:'Arial','Helvetica','sans-serif';",
		'Book Antiqua'		=>"font-family:'Book Antiqua','MS Gothic';",
		'Comic'			=>"font-family:'Comic Sans MS','Papyrus';",
		'Courier'		=>"font-family:'Courier New','Courier','monospace';",
		'Cursive'		=>"font-family:'Cursive','Papyrus';",
		'Fantasy'		=>"font-family:'Fantasy','Futura','Papyrus';",
		'Garamond'		=>"font-family:'Garamond','Palatino','serif';",
		'Georgia'		=>"font-family:'Georgia','Times New Roman','Times','serif';",
		'Serif'			=>"font-family:'MS Serif','New York','serif';",
		'System'		=>"font-family:'System','Chicago','sans-serif';",
		'Times New Roman'	=>"font-family:'Times New Roman','Times','serif';",
		'Verdana'		=>"font-family:'Verdana','Geneva','Arial','Helvetica','sans-serif';",
	];
}

function load_lang(){
	global $I, $L, $language;
	$L=[
		'bg'	=>'Български',
		'cz'	=>'čeština',
		'de'	=>'Deutsch',
		'en'	=>'English',
		'es'	=>'Español',
		'fr'	=>'Français',
		'id'	=>'Bahasa Indonesia',
		'it'	=>'Italiano',
		'ru'	=>'Русский',
		'uk'	=>'Українська',
		'zh_CN'	=>'简体中文',
	];
	if(isset($_REQUEST['lang']) && isset($L[$_REQUEST['lang']])){
		$language=$_REQUEST['lang'];
		if(!isset($_COOKIE['language']) || $_COOKIE['language']!==$language){
			setcookie('language', $language);
		}
	}elseif(isset($_COOKIE['language']) && isset($L[$_COOKIE['language']])){
		$language=$_COOKIE['language'];
	}else{
		$language=LANG;
		setcookie('language', $language);
	}
	include('lang_en.php'); //always include English
	if($language!=='en'){
		$T=[];
		include("lang_$language.php"); //replace with translation if available
		foreach($T as $name=>$translation){
			$I[$name]=$translation;
		}
	}
}

function load_config(){
	mb_internal_encoding('UTF-8');
	define('VERSION', '1.24'); // Script version
	define('DBVERSION', 42); // Database layout version
	define('MSGENCRYPTED', false); // Store messages encrypted in the database to prevent other database users from reading them - true/false - visit the setup page after editing!
	define('ENCRYPTKEY_PASS', 'MY_SECRET_KEY'); // Recommended length: 32. Encryption key for messages
    define('AES_IV_PASS', '012345678912'); // Recommended length: 12. AES Encryption IV
	define('DBHOST', 'localhost'); // Database host
	define('DBUSER', 'www-data'); // Database user
	define('DBPASS', 'YOUR_DB_PASS'); // Database password
	define('DBNAME', 'public_chat'); // Database
	define('PERSISTENT', true); // Use persistent database conection true/false
	define('PREFIX', ''); // Prefix - Set this to a unique value for every chat, if you have more than 1 chats on the same database or domain - use only alpha-numeric values (A-Z, a-z, 0-9, or _) other symbols might break the queries
	define('MEMCACHED', false); // Enable/disable memcached caching true/false - needs memcached extension and a memcached server.
	if(MEMCACHED){
		define('MEMCACHEDHOST', 'localhost'); // Memcached host
		define('MEMCACHEDPORT', '11211'); // Memcached port
	}
	define('DBDRIVER', 0); // Selects the database driver to use - 0=MySQL, 1=PostgreSQL, 2=sqlite
	if(DBDRIVER===2){
		define('SQLITEDBFILE', 'public_chat.sqlite'); // Filepath of the sqlite database, if sqlite is used - make sure it is writable for the webserver user
	}
	define('COOKIENAME', PREFIX . 'chat_session'); // Cookie name storing the session information
	define('LANG', 'en'); // Default language
    if (MSGENCRYPTED){
        //Do not touch: Compute real keys needed by encryption functions
        if (strlen(ENCRYPTKEY_PASS) !== SODIUM_CRYPTO_AEAD_AES256GCM_KEYBYTES){
            define('ENCRYPTKEY', substr(hash("sha512/256",ENCRYPTKEY_PASS),0, SODIUM_CRYPTO_AEAD_AES256GCM_KEYBYTES));
        }else{
            define('ENCRYPTKEY', ENCRYPTKEY_PASS);
        }
        if (strlen(AES_IV_PASS) !== SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES){
            define('AES_IV', substr(hash("sha512/256",AES_IV_PASS), 0, SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES));
        }else{
            define('AES_IV', AES_IV_PASS);
        }
    }
}
