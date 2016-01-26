<?php
/*
* LE CHAT-PHP - a PHP Chat based on LE CHAT - Main program
*
* Copyright (C) 2015 Daniel Winzen <d@winzen4.de>
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

header('Content-Type: text/html; charset=UTF-8');
header('Pragma: no-cache');
header('Cache-Control: no-cache');
header('Expires: 0');
if($_SERVER['REQUEST_METHOD']==='HEAD'){
	exit; // headers sent, no further processing needed
}
// initialize and load variables/configuration
date_default_timezone_set('UTC');
$A=array();// All registered members
$C=array();// Configuration
$F=array();// Fonts
$H=array();// HTML-stuff
$I=array();// Translations
$L=array();// Languages
$P=array();// All present users
$U=array();// This user data
$countmods=0;// Present moderators
$db;// Database connection
$memcached;// Memcached connection
load_config();
// set session variable to cookie if cookies are enabled
if(!isSet($_REQUEST['session']) && isSet($_COOKIE[$C['cookiename']])){
	$_REQUEST['session']=$_COOKIE[$C['cookiename']];
}
load_fonts();
load_lang();
load_html();
check_db();

//  main program: decide what to do based on queries
if(!isSet($_REQUEST['action'])){
	if(!check_init()){
		send_init();
	}
	send_login();
}elseif($_REQUEST['action']==='view'){
	check_session();
	send_messages(false);
}elseif($_REQUEST['action']==='jsview'){
	check_session();
	send_messages(true);
}elseif($_REQUEST['action']==='jsrefresh'){
	check_session();
	ob_start();
	print_messages();
	$msgs=ob_get_clean();
	ob_start();
	print_chatters();
	$chatters=ob_get_clean();
	echo json_encode(array($_REQUEST['id'], $msgs, $chatters, get_setting('topic')));
}elseif($_REQUEST['action']==='redirect' && !empty($_GET['url'])){
	send_redirect();
}elseif($_REQUEST['action']==='wait'){
	send_waiting_room();
}elseif($_REQUEST['action']==='post'){
	check_session();
	if(isSet($_REQUEST['kick']) && isSet($_REQUEST['sendto']) && valid_nick($_REQUEST['sendto'])){
		if($U['status']>=5 || ($U['status']>=3 && $countmods===0 && get_setting('memkick'))){
			if(isSet($_REQUEST['what']) && $_REQUEST['what']==='purge'){
				kick_chatter(array($_REQUEST['sendto']), $_REQUEST['message'], true);
			}else{
				kick_chatter(array($_REQUEST['sendto']), $_REQUEST['message'], false);
			}
		}
	}elseif(isSet($_REQUEST['message']) && isSet($_REQUEST['sendto'])){
		validate_input();
	}
	send_post();
}elseif($_REQUEST['action']==='login'){
	check_login();
	send_frameset();
}elseif($_REQUEST['action']==='controls'){
	check_session();
	send_controls();
}elseif($_REQUEST['action']==='delete'){
	check_session();
	if($_REQUEST['what']==='all'){
		if(isSet($_REQUEST['confirm'])){
			del_all_messages($U['nickname'], 10, $U['entry']);
		}else{
			send_del_confirm();
		}
	}elseif($_REQUEST['what']==='last'){
		del_last_message();
	}
	send_post();
}elseif($_REQUEST['action']==='profile'){
	check_session();
	if(isSet($_REQUEST['do']) && $_REQUEST['do']==='save'){
		save_profile();
	}
	send_profile();
}elseif($_REQUEST['action']==='logout'){
	kill_session();
	send_logout();
}elseif($_REQUEST['action']==='colours'){
	check_session();
	send_colours();
}elseif($_REQUEST['action']==='notes'){
	check_session();
	if(!empty($_REQUEST['do']) && $_REQUEST['do']==='admin' && $U['status']>6){
		send_notes('admin');
	}
	if($U['status']<5){
		send_access_denied();
	}
	send_notes('staff');
}elseif($_REQUEST['action']==='help'){
	check_session();
	send_help();
}elseif($_REQUEST['action']==='admin'){
	check_session();
	if($U['status']<5){
		send_access_denied();
	}
	if(empty($_REQUEST['do'])){
	}elseif($_REQUEST['do']==='clean'){
		if($_REQUEST['what']==='choose'){
			send_choose_messages();
		}elseif($_REQUEST['what']==='selected'){
			clean_selected();
		}elseif($_REQUEST['what']==='room'){
			clean_room();
		}elseif($_REQUEST['what']==='nick'){
			del_all_messages($_REQUEST['nickname'], $U['status'], 0);
		}
	}elseif($_REQUEST['do']==='kick'){
		if(!isSet($_REQUEST['name'])){
			send_admin();
		}
		if(isSet($_REQUEST['what']) && $_REQUEST['what']==='purge'){
			kick_chatter($_REQUEST['name'], $_REQUEST['kickmessage'], true);
		}else{
			kick_chatter($_REQUEST['name'], $_REQUEST['kickmessage'], false);
		}
	}elseif($_REQUEST['do']==='logout'){
		if(isSet($_REQUEST['name'])){
			logout_chatter($_REQUEST['name']);
		}
	}elseif($_REQUEST['do']==='sessions'){
		if(isSet($_REQUEST['nick'])){
			kick_chatter(array($_REQUEST['nick']), '', false);
		}
		send_sessions();
	}elseif($_REQUEST['do']==='register'){
		register_guest(3);
	}elseif($_REQUEST['do']==='superguest'){
		register_guest(2);
	}elseif($_REQUEST['do']==='status'){
		change_status();
	}elseif($_REQUEST['do']==='regnew'){
		register_new();
	}elseif($_REQUEST['do']==='approve'){
		approve_session();
		send_approve_waiting();
	}elseif($_REQUEST['do']==='guestaccess'){
		if(isSet($_REQUEST['guestaccess']) && preg_match('/^[0123]$/', $_REQUEST['guestaccess'])){
			update_setting('guestaccess', $_REQUEST['guestaccess']);
		}
	}elseif($_REQUEST['do']==='filter'){
		manage_filter();
		send_filter();
	}elseif($_REQUEST['do']==='linkfilter'){
		manage_linkfilter();
		send_linkfilter();
	}elseif($_REQUEST['do']==='topic'){
		if(isSet($_REQUEST['topic'])){
			update_setting('topic', htmlspecialchars($_REQUEST['topic']));
		}
	}elseif($_REQUEST['do']==='passreset'){
		passreset();
	}
	send_admin();
}elseif($_REQUEST['action']==='setup'){
	if(!check_init()){
		send_init();
	}
	update_db();
	if(!valid_admin()){
		send_alogin();
	}
	$C['bool_settings']=array('suguests', 'imgembed', 'timestamps', 'trackip', 'memkick', 'forceredirect', 'incognito', 'enablejs');
	$C['colour_settings']=array('colbg', 'coltxt');
	$C['msg_settings']=array('msgenter', 'msgexit', 'msgmemreg', 'msgsureg', 'msgkick', 'msgmultikick', 'msgallkick', 'msgclean', 'msgsendall', 'msgsendmem', 'msgsendmod', 'msgsendadm', 'msgsendprv');
	$C['number_settings']=array('memberexpire', 'guestexpire', 'kickpenalty', 'entrywait', 'captchatime', 'messageexpire', 'messagelimit', 'maxmessage', 'maxname', 'minpass', 'defaultrefresh', 'numnotes');
	$C['textarea_settings']=array('rulestxt', 'css');
	$C['text_settings']=array('dateformat', 'captchachars', 'redirect', 'chatname');
	$C['settings']=array_merge(array('guestaccess', 'englobalpass', 'globalpass', 'captcha', 'dismemcaptcha', 'topic'), $C['bool_settings'], $C['colour_settings'], $C['msg_settings'], $C['number_settings'], $C['textarea_settings'], $C['text_settings']); // All settings in the database
	if(empty($_REQUEST['do'])){
	}elseif($_REQUEST['do']==='save'){
		foreach($C['msg_settings'] as $setting){
			$_REQUEST[$setting]=htmlspecialchars($_REQUEST[$setting]);
		}
		foreach($C['number_settings'] as $setting){
			settype($_REQUEST[$setting], 'int');
		}
		$_REQUEST['rulestxt']=preg_replace("/(\r?\n|\r\n?)/", '<br>', $_REQUEST['rulestxt']);
		$_REQUEST['chatname']=htmlspecialchars($_REQUEST['chatname']);
		if(!preg_match('/^[a-f0-9]{6}$/i', $_REQUEST['colbg'])){
			unset($_REQUEST['colbg']);
		}
		if(!preg_match('/^[a-f0-9]{6}$/i', $_REQUEST['coltxt'])){
			unset($_REQUEST['coltxt']);
		}
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
		}elseif($_REQUEST['maxmessage']>20000){
			$_REQUEST['maxmessage']=20000;
		}
		if($_REQUEST['numnotes']<1){
			$_REQUEST['numnotes']=1;
		}
		foreach($C['settings'] as $setting){
			if(isSet($_REQUEST[$setting])) update_setting($setting, $_REQUEST[$setting]);
		}
	}elseif($_REQUEST['do']==='backup' && $U['status']==8){
		send_backup();
	}elseif($_REQUEST['do']==='restore' && $U['status']==8){
		restore_backup();
		send_backup();
	}elseif($_REQUEST['do']==='destroy' && $U['status']==8){
		if(isSet($_REQUEST['confirm'])){
			destroy_chat();
		}else{
			send_destroy_chat();
		}
	}
	send_setup();
}elseif($_REQUEST['action']==='init'){
	init_chat();
}else{
	send_login();
}
exit;

//  html output subs
function print_stylesheet(){
	$css=get_setting('css');
	$colbg=get_setting('colbg');
	$coltxt=get_setting('coltxt');
	echo "<style type=\"text/css\">body{background-color:#$colbg;color:#$coltxt;} $css</style>";
}

function print_end(){
	echo '</body></html>';
	exit;
}

function frmpst($arg1=''){
	global $H;
	echo "<$H[form]>$H[commonform]".hidden('action', $arg1);
}

function frmadm($arg1=''){
	global $H;
	echo "<$H[form]>$H[commonform]".hidden('action', 'admin').hidden('do', $arg1);
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

function print_start($class='',  $ref=0, $url=''){
	global $H, $I, $U;
	if(!empty($url)){
		header("Refresh: $ref; URL=$url");
	}
	echo "<!DOCTYPE html><html><head>$H[meta_html]";
	if(!empty($url)){
		echo "<meta http-equiv=\"Refresh\" content=\"$ref; URL=$url\">";
	}
	if($class==='init'){
		echo "<title>$I[init]</title>";
		echo "<style type=\"text/css\">body{background-color:#000000;color:#FFFFFF;} a:visited{color:#B33CB4;} a:active{color:#FF0033;} a:link{color:#0000FF;} input,select,textarea{color:#FFFFFF;background-color:#000000;} a img{width:15%} a:hover img{width:35%} .error{color:#FF0033;} .delbutton{background-color:#660000;} .backbutton{background-color:#004400;} #exitbutton{background-color:#AA0000;}</style>";
	}else{
		echo '<title>'.get_setting('chatname').'</title>';
		print_stylesheet();
	}
	if(!empty($U['bgcolour'])){
		$style=" style=\"background-color:#$U[bgcolour];\"";
	}else{
		$style='';
	}
	echo "</head><body$style class=\"$class\">";
}

function send_redirect(){
	global $I;
	if(preg_match('~^http(s)?://~', $_GET['url'])){
		print_start('redirect', 0, $_GET['url']);
		echo "<p>$I[redirectto] <a href=\"$_GET[url]\">".htmlspecialchars($_GET['url']).'</a>.</p>';
	}else{
		print_start('redirect');
		$url=preg_replace('~(.*)://~', 'http://', $_GET['url']);
		echo "<p>$I[nonhttp] <a href=\"$_GET[url]\">".htmlspecialchars($_GET['url']).'</a>.</p>';
		echo "<p>$I[httpredir] <a href=\"$url\">".htmlspecialchars($url).'</a>.</p>';
	}
	print_end();
}

function send_access_denied(){
	global $H, $I, $U;
	header('HTTP/1.1 401 Forbidden');
	print_start('access_denied');
	echo "<div style=\"text-align:center;\"><h1>$I[accessdenied]</h1>".sprintf($I['loggedinas'], style_this($U['nickname'], $U['style']));
	echo "<br><$H[form]>$H[commonform]".hidden('action', 'logout');
	if(!isSet($_REQUEST['session'])){
		hidden('session', $U['session']);
	}
	echo submit($I['logout'], 'id="exitbutton"')."</form></div>";
	print_end();
}

function send_captcha(){
	global $C, $I, $db, $memcached;
	$difficulty=(int) get_setting('captcha');
	if($difficulty===0){
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
	if($C['memcached']){
		$memcached->set("$C[dbname]-$C[prefix]captcha-$randid", $code, get_setting('captchatime'));
	}else{
		$stmt=$db->prepare("INSERT INTO $C[prefix]captcha (id, time, code) VALUES (?, ?, ?);");
		$stmt->execute(array($randid, $time, $code));
	}
	echo "<tr><td style=\"text-align:left;\">$I[copy]<br>";
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
		$line=imagecolorallocate($im, 100, 100, 100);
		for($i=0;$i<3;++$i){
			imageline($im, 0, mt_rand(0, 24), 55, mt_rand(0, 24), $line);
		}
		$dots=imagecolorallocate($im, 200, 200, 200);
		for($i=0;$i<100;++$i){
			imagesetpixel($im, mt_rand(0, 55), mt_rand(0, 24), $dots);
		}
		imagestring($im, 5, 5, 5, $code, $fg);
		echo '<img width="55" height="24" src="data:image/gif;base64,';
	}elseif($difficulty===3){
		$im=imagecreatetruecolor(150, 200);
		$bg=imagecolorallocate($im, 0, 0, 0);
		$fg=imagecolorallocate($im, 255, 255, 255);
		imagefill($im, 0, 0, $bg);
		$line=imagecolorallocate($im, 100, 100, 100);
		for($i=0;$i<5;++$i){
			imageline($im, 0, mt_rand(0, 200), 150, mt_rand(0, 200), $line);
		}
		$dots=imagecolorallocate($im, 200, 200, 200);
		for($i=0;$i<1000;++$i){
			imagesetpixel($im, mt_rand(0, 150), mt_rand(0, 200), $dots);
		}
		$chars=array();
		for($i=0;$i<5;++$i){
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
			$chars[]=array('x', 'y');
			$chars[$i]['x']=$x;
			$chars[$i]['y']=$y;
			imagechar($im, 5, $chars[$i]['x'], $chars[$i]['y'], $captchachars[mt_rand(0, $length)], $fg);
		}
		$x=$y=array();
		for($i=5;$i<10;++$i){
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
			$chars[]=array('x', 'y');
			$chars[$i]['x']=$x;
			$chars[$i]['y']=$y;
			imagechar($im, 5, $chars[$i]['x'], $chars[$i]['y'], $code[$i-5], $fg);
		}
		$follow=imagecolorallocate($im, 200, 0, 0);
		imagearc($im, $chars[5]['x']+4, $chars[5]['y']+8, 16, 16, 0, 360, $follow);
		for($i=5;$i<9;++$i){
			imageline($im, $chars[$i]['x']+4, $chars[$i]['y']+8, $chars[$i+1]['x']+4, $chars[$i+1]['y']+8, $follow);
		}
		echo '<img width="150" height="200" src="data:image/gif;base64,';
	}
	ob_start();
	imagegif($im);
	imagedestroy($im);
	echo base64_encode(ob_get_clean()).'">';
	echo '</td><td style="text-align:right;">'.hidden('challenge', $randid).'<input type="text" name="captcha" size="15" autocomplete="off"></td></tr>';
}

function send_setup(){
	global $C, $H, $I, $U;
	$ga=(int) get_setting('guestaccess');
	print_start('setup');
	echo "<div style=\"text-align:center;\"><h2>$I[setup]</h2><$H[form]>$H[commonform]".hidden('action', 'setup').hidden('do', 'save');
	if(!isSet($_REQUEST['session'])){
		echo hidden('session', $U['session']);
	}
	echo '<table style="margin-left:auto;margin-right:auto;">';
	thr();
	echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[guestacc]</th><td style=\"text-align:right;\">";
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
		echo ' selected'; echo ">$I[adminallow]</option>";
	}
	echo '<option value="0"';
	if($ga===0){
		echo ' selected';
	}
	echo ">$I[guestdisallow]</option>";
	echo '</select></td></tr></table></td></tr>';
	thr();
	$englobal=(int) get_setting('englobalpass');
	echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[globalloginpass]</th><td>";
	echo '<table style="border-spacing:0px;margin-left:auto;">';
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
	echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[sysmessages]</th><td>";
	echo '<table style="border-spacing:0px;text-align:right;margin-left:auto;">';
	foreach($C['msg_settings'] as $setting){
		echo "<tr><td>&nbsp;$I[$setting]</td><td>&nbsp;<input type=\"text\" name=\"$setting\" value=\"".get_setting($setting).'"></td></tr>';
	}
	echo '</table></td></tr></table></td></tr>';
	foreach($C['text_settings'] as $setting){
		thr();
		echo '<tr><td><table style="width:100%;text-align:left;"><tr><th>'.$I[$setting].'</th><td style="text-align:right;">';
		echo "<input type=\"text\" name=\"$setting\" value=\"".htmlspecialchars(get_setting($setting)).'">';
		echo '</td></tr></table></td></tr>';
	}
	foreach($C['colour_settings'] as $setting){
		thr();
		echo '<tr><td><table style="width:100%;text-align:left;"><tr><th>'.$I[$setting].'</th><td style="text-align:right;">';
		echo "<input type=\"text\" name=\"$setting\" size=\"6\" maxlength=\"6\" pattern=\"[a-fA-F0-9]{6}\" value=\"".htmlspecialchars(get_setting($setting)).'">';
		echo '</td></tr></table></td></tr>';
	}
	thr();
	echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[captcha]</th><td>";
	echo '<table style="border-spacing:0px;margin-left:auto;">';
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
	echo '</table></td></tr></table></td></tr>';
	foreach($C['textarea_settings'] as $setting){
		thr();
		echo '<tr><td><table style="width:100%;text-align:left;"><tr><th>'.$I[$setting].'</th><td style="text-align:right;">';
		echo "<textarea name=\"$setting\" rows=\"4\" cols=\"60\">".htmlspecialchars(get_setting($setting)).'</textarea>';
		echo '</td></tr></table></td></tr>';
	}
	foreach($C['number_settings'] as $setting){
		thr();
		echo '<tr><td><table style="width:100%;text-align:left;"><tr><th>'.$I[$setting].'</th><td style="text-align:right;">';
		echo "<input type=\"number\" name=\"$setting\" value=\"".htmlspecialchars(get_setting($setting)).'">';
		echo '</td></tr></table></td></tr>';
	}
	foreach($C['bool_settings'] as $setting){
		thr();
		echo '<tr><td><table style="width:100%;text-align:left;"><tr><th>'.$I[$setting].'</th><td style="text-align:right;">';
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
		echo '</select></td></tr></table></td></tr>';
	}
	thr();
	echo '<tr><td>'.submit($I['apply']).'</td></tr></table></form><br>';
	if($U['status']==8){
		echo '<table style="margin-left:auto;margin-right:auto;"><tr>';
		echo "<td><$H[form]>$H[commonform]".hidden('action', 'setup').hidden('do', 'backup');
		if(!isSet($_REQUEST['session'])){
			hidden('session', $U['session']);
		}
		echo submit($I['backuprestore']).'</form></td>';
		echo "<td><$H[form]>$H[commonform]".hidden('action', 'setup').hidden('do', 'destroy');
		if(!isSet($_REQUEST['session'])){
			hidden('session', $U['session']);
		}
		echo submit($I['destroy'], 'class="delbutton"').'</form></td></tr></table><br>';
	}
	echo "<$H[form]>$H[commonform]".hidden('action', 'logout');
	if(!isSet($_REQUEST['session'])){
		hidden('session', $U['session']);
	}
	echo submit($I['logout'], 'id="exitbutton"')."</form>$H[credit]</div>";
	print_end();
}

function restore_backup(){
	global $C, $db;
	$code=json_decode($_REQUEST['restore'], true);
	if(isSet($_REQUEST['settings'])){
		foreach($C['settings'] as $setting){
			if(isSet($code['settings'][$setting])){
				update_setting($setting, $code['settings'][$setting]);
			}
		}
	}
	if(isSet($_REQUEST['filter']) && (isSet($code['filters']) || isSet($code['linkfilters']))){
		$db->exec("DELETE FROM $C[prefix]filter;");
		$db->exec("DELETE FROM $C[prefix]linkfilter;");
		$stmt=$db->prepare("INSERT INTO $C[prefix]filter (filtermatch, filterreplace, allowinpm, regex, kick) VALUES (?, ?, ?, ?, ?);");
		foreach($code['filters'] as $filter){
			$stmt->execute(array($filter['match'], $filter['replace'], $filter['allowinpm'], $filter['regex'], $filter['kick']));
		}
		$stmt=$db->prepare("INSERT INTO $C[prefix]linkfilter (filtermatch, filterreplace, regex) VALUES (?, ?, ?);");
		foreach($code['linkfilters'] as $filter){
			$stmt->execute(array($filter['match'], $filter['replace'], $filter['regex']));
		}
		if($C['memcached']){
			$memcached->delete("$C[dbname]-$C[prefix]filter");
		}
		if($C['memcached']){
			$memcached->delete("$C[dbname]-$C[prefix]linkfilter");
		}
	}
	if(isSet($_REQUEST['members']) && isSet($code['members'])){
		$db->exec("DELETE FROM $C[prefix]members;");
		$stmt=$db->prepare("INSERT INTO $C[prefix]members (nickname, passhash, status, refresh, bgcolour, boxwidth, boxheight, notesboxwidth, notesboxheight, regedby, lastlogin, timestamps, embed, incognito, style) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);");
		foreach($code['members'] as $member){
			$stmt->execute(array($member['nickname'], $member['passhash'], $member['status'], $member['refresh'], $member['bgcolour'], $member['boxwidth'], $member['boxheight'], $member['notesboxwidth'], $member['notesboxheight'], $member['regedby'], $member['lastlogin'], $member['timestamps'], $member['embed'], $member['incognito'], $member['style']));
		}
	}
	if(isSet($_REQUEST['notes']) && isSet($code['notes'])){
		$db->exec("DELETE FROM $C[prefix]notes;");
		$stmt=$db->prepare("INSERT INTO $C[prefix]notes (type, lastedited, editedby, text) VALUES (?, ?, ?, ?);");
		foreach($code['notes'] as $note){
			$stmt->execute(array($note['type'], $note['lastedited'], $note['editedby'], $note['text']));
		}
	}
}

function send_backup(){
	global $C, $H, $I, $db;
	$code=array();
	if($_REQUEST['do']==='backup'){
		if(isSet($_REQUEST['settings'])){
			foreach($C['settings'] as $setting){
				$code['settings'][$setting]=get_setting($setting);
			}
		}
		if(isSet($_REQUEST['filter'])){
			$result=$db->query("SELECT filtermatch, filterreplace, allowinpm, regex, kick FROM $C[prefix]filter;");
			while($filter=$result->fetch(PDO::FETCH_ASSOC)){
				$code['filters'][]=array('match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'allowinpm'=>$filter['allowinpm'], 'regex'=>$filter['regex'], 'kick'=>$filter['kick']);
			}
			$result=$db->query("SELECT filtermatch, filterreplace, regex FROM $C[prefix]linkfilter;");
			while($filter=$result->fetch(PDO::FETCH_ASSOC)){
				$code['linkfilters'][]=array('match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'regex'=>$filter['regex']);
			}
		}
		if(isSet($_REQUEST['members'])){
			$result=$db->query("SELECT nickname, passhash, status, refresh, bgcolour, boxwidth, boxheight, notesboxwidth, notesboxheight, regedby, lastlogin, timestamps, embed, incognito, style FROM $C[prefix]members;");
			while($member=$result->fetch(PDO::FETCH_ASSOC)) $code['members'][]=$member;
		}
		if(isSet($_REQUEST['notes'])){
			$result=$db->query("SELECT type, lastedited, editedby, text FROM $C[prefix]notes WHERE type='admin' ORDER BY id DESC LIMIT 1;");
			$code['notes'][]=$result->fetch(PDO::FETCH_ASSOC);
			$result=$db->query("SELECT type, lastedited, editedby, text FROM $C[prefix]notes WHERE type='staff' ORDER BY id DESC LIMIT 1;");
			$code['notes'][]=$result->fetch(PDO::FETCH_ASSOC);
		}
	}
	if(isSet($_REQUEST['settings'])){
		$chksettings=' checked';
	}else{
		$chksettings='';
	}
	if(isSet($_REQUEST['filter'])){
		$chkfilters=' checked';
	}else{
		$chkfilters='';
	}
	if(isSet($_REQUEST['members'])){
		$chkmembers=' checked';
	}else{
		$chkmembers='';
	}
	if(isSet($_REQUEST['notes'])){
		$chknotes=' checked';
	}else{
		$chknotes='';
	}
	print_start('backup');
	echo "<div style=\"text-align:center;\"><h2>$I[backuprestore]</h2><table style=\"margin-left:auto;margin-right:auto;\">";
	thr();
	echo "<tr><td><$H[form]>$H[commonform]".hidden('action', 'setup').hidden('do', 'backup');
	echo '<table style="width:100%;text-align:left;"><tr><td>';
	echo "<input type=\"checkbox\" name=\"settings\" id=\"backupsettings\" value=\"1\"$chksettings><label for=\"backupsettings\">$I[settings]</label>";
	echo "<input type=\"checkbox\" name=\"filter\" id=\"backupfilter\" value=\"1\"$chkfilters><label for=\"backupfilter\">$I[filter]</label>";
	echo "<input type=\"checkbox\" name=\"members\" id=\"backupmembers\" value=\"1\"$chkmembers><label for=\"backupmembers\">$I[members]</label>";
	echo "<input type=\"checkbox\" name=\"notes\" id=\"backupnotes\" value=\"1\"$chknotes><label for=\"backupnotes\">$I[notes]</label>";
	echo '</td><td style="text-align:right;">'.submit($I['backup']).'</td></tr></table></form></td></tr>';
	thr();
	echo "<tr><td><$H[form]>$H[commonform]".hidden('action', 'setup').hidden('do', 'restore');
	echo '<table>';
	echo "<tr><td colspan=\"2\"><textarea name=\"restore\" rows=\"4\" cols=\"60\">".htmlspecialchars(json_encode($code)).'</textarea></td></tr>';
	echo "<tr><td style=\"text-align:left;\"><input type=\"checkbox\" name=\"settings\" id=\"restoresettings\" value=\"1\"$chksettings><label for=\"restoresettings\">$I[settings]</label>";
	echo "<input type=\"checkbox\" name=\"filter\" id=\"restorefilter\" value=\"1\"$chkfilters><label for=\"restorefilter\">$I[filter]</label>";
	echo "<input type=\"checkbox\" name=\"members\" id=\"restoremembers\" value=\"1\"$chkmembers><label for=\"restoremembers\">$I[members]</label>";
	echo "<input type=\"checkbox\" name=\"notes\" id=\"restorenotes\" value=\"1\"$chknotes><label for=\"restorenotes\">$I[notes]</label>";
	echo '</td><td style="text-align:right;">'.submit($I['restore']).'</td></tr></table>';
	echo '</form></td></tr>';
	thr();
	echo "<tr><td><$H[form]>$H[commonform]".hidden('action', 'setup').submit($I['initgosetup'], 'class="backbutton"')."</form></tr></td></div>";
	echo '</table>';
	print_end();
}

function send_destroy_chat(){
	global $H, $I;
	print_start('destroy_chat');
	echo "<table style=\"text-align:center;margin-left:auto;margin-right:auto;\"><tr><td colspan=\"2\">$I[confirm]</td></tr><tr><td>";
	echo "<$H[form] target=\"_parent\">$H[commonform]".hidden('action', 'setup').hidden('do', 'destroy').hidden('confirm', 'yes').submit($I['yes'], 'class="delbutton"').'</form></td><td>';
	echo "<$H[form]>$H[commonform]".hidden('action', 'setup').submit($I['no'], 'class="backbutton"').'</form></td><tr></table>';
	print_end();
}

function send_init(){
	global $H, $I, $L;
	print_start('init');
	echo "<div style=\"text-align:center;\"><h2>$I[init]</h2>";
	echo "<$H[form]>$H[commonform]".hidden('action', 'init')."<table style=\"margin-left:auto;margin-right:auto;\"><tr><td><h3>$I[sulogin]</h3><table style=\"text-align:left;\">";
	echo "<tr><td>$I[sunick]</td><td><input type=\"text\" name=\"sunick\" size=\"15\"></td></tr>";
	echo "<tr><td>$I[supass]</td><td><input type=\"password\" name=\"supass\" size=\"15\"></td></tr>";
	echo "<tr><td>$I[suconfirm]</td><td><input type=\"password\" name=\"supassc\" size=\"15\"></td></tr>";
	echo '</table></td></tr><tr><td><br>'.submit($I['initbtn']).'</td></tr></table></form>';
	echo "<p>$I[changelang]";
	foreach($L as $lang=>$name){
		echo " <a href=\"$_SERVER[SCRIPT_NAME]?action=setup&lang=$lang\">$name</a>";
	}
	echo "</p>$H[credit]</div>";
	print_end();
}

function send_update(){
	global $H, $I;
	print_start('update');
	echo "<div style=\"text-align:center;\"><h2>$I[dbupdate]</h2><br><$H[form]>$H[commonform]".hidden('action', 'setup').submit($I['initgosetup'])."</form><br>$H[credit]</div>";
	print_end();
}

function send_alogin(){
	global $H, $I, $L;
	print_start('alogin');
	echo "<div style=\"text-align:center;\"><$H[form]>$H[commonform]".hidden('action', 'setup').'<table style="text-align:left;margin-left:auto;margin-right:auto;">';
	echo "<tr><td>$I[nick]</td><td><input type=\"text\" name=\"nick\" size=\"15\" autofocus></td></tr>";
	echo "<tr><td>$I[pass]</td><td><input type=\"password\" name=\"pass\" size=\"15\"></td></tr>";
	send_captcha();
	echo '<tr><td colspan="2" style="text-align:right;">'.submit($I['login']).'</td></tr></table></form>';
	echo "<p>$I[changelang]";
	foreach($L as $lang=>$name){
		echo " <a href=\"$_SERVER[SCRIPT_NAME]?action=setup&lang=$lang\">$name</a>";
	}
	echo "</p>$H[credit]</div>";
	print_end();
}

function send_admin($arg=''){
	global $A, $C, $H, $I, $P, $U, $db;
	$ga=(int) get_setting('guestaccess');
	print_start('admin');
	$chlist="<select name=\"name[]\" size=\"5\" multiple><option value=\"\">$I[choose]</option>";
	$chlist.="<option value=\"&\">$I[allguests]</option>";
	array_multisort(array_map('strtolower', array_keys($P)), SORT_ASC, SORT_STRING, $P);
	foreach($P as $user){
		if($user[2]<$U['status']){
			$chlist.="<option value=\"$user[0]\" style=\"$user[1]\">$user[0]</option>";
		}
	}
	$chlist.='</select>';
	echo "<div style=\"text-align:center;\"><h2>$I[admfunc]</h2><i>$arg</i><table style=\"margin-left:auto;margin-right:auto;\">";
	if($U['status']>=7){
		thr();
		echo "<tr><td><$H[form] target=\"view\">$H[commonform]".hidden('action', 'setup').submit($I['initgosetup']).'</form></td></tr>';
	}
	thr();
	echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[cleanmsgs]</th><td>";
	frmadm('clean');
	echo '<table style="border-spacing:0px;text-align:left;margin-left:auto;"><tr><td><input type="radio" name="what" id="room" value="room">';
	echo "<label for=\"room\">$I[room]</label></td><td>&nbsp;</td><td><input type=\"radio\" name=\"what\" id=\"choose\" value=\"choose\" checked>";
	echo "<label for=\"choose\">$I[selection]</label></td><td>&nbsp;</td></tr><tr><td colspan=\"3\"><input type=\"radio\" name=\"what\" id=\"nick\" value=\"nick\">";
	echo "<label for=\"nick\">$I[cleannick] </label><select name=\"nickname\" size=\"1\"><option value=\"\">$I[choose]</option>";
	$stmt=$db->prepare("SELECT poster FROM $C[prefix]messages WHERE poststatus<9 AND delstatus<? GROUP BY poster;");
	$stmt->execute(array($U['status']));
	while($nick=$stmt->fetch(PDO::FETCH_NUM)){
		echo "<option value=\"$nick[0]\">$nick[0]</option>";
	}
	echo '</select></td><td>';
	echo submit($I['clean'], 'class="delbutton"').'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo '<tr><td><table style="width:100%;text-align:left;"><tr><td>'.sprintf($I['kickchat'], get_setting('kickpenalty')).'</td></tr><tr><td>';
	frmadm('kick');
	echo "<table style=\"border-spacing:0px;\"><tr><td style=\"margin-left:auto;\">$I[kickreason]</td><td style=\"text-align:right;\"><input type=\"text\" name=\"kickmessage\" size=\"30\"></td><td>&nbsp;</td></tr>";
	echo "<tr><td><input type=\"checkbox\" name=\"what\" value=\"purge\" id=\"purge\"><label for=\"purge\">&nbsp;$I[kickpurge]</label></td><td style=\"text-align:right;\">$chlist</td><td style=\"text-align:right;\">";
	echo submit($I['kick']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[logoutinact]</th><td>";
	frmadm('logout');
	echo "<table style=\"border-spacing:0px;margin-left:auto;\"><tr style=\"text-align:right;\"><td>$chlist</td><td>";
	echo submit($I['logout']).'</td></tr></table></form></td></tr></table></td></tr>';
	$views=array('sessions', 'filter', 'linkfilter');
	foreach($views as $view){
		thr();
		echo '<tr><td><table style="width:100%;text-align:left;"><tr><th>'.$I[$view].'</th><td style="text-align:right;">';
		frmadm($view);
		echo submit($I['view']).'</form></td></tr></table></td></tr>';
	}
	thr();
	echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[topic]</th><td>";
	frmadm('topic');
	echo '<table style="border-spacing:0px;margin-left:auto;"><tr><td><input type="text" name="topic" size="20" value="'.get_setting('topic').'"></td><td>';
	echo submit($I['change']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[guestacc]</th><td>";
	frmadm('guestaccess');
	echo '<table style="border-spacing:0px;margin-left:auto;">';
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
	echo '</select></td><td>'.submit($I['change']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	if(get_setting('suguests')){
		echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[addsuguest]</th><td>";
		frmadm('superguest');
		echo "<table style=\"border-spacing:0px;margin-left:auto;\"><tr><td><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($P as $user){
			if($user[2]==1){
				echo "<option value=\"$user[0]\" style=\"$user[1]\">$user[0]</option>";
			}
		}
		echo '</select></td><td>'.submit($I['register']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
	}
	if($U['status']>=7){
		echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[admmembers]</th><td>";
		frmadm('status');
		echo "<table style=\"border-spacing:0px;margin-left:auto;\"><td style=\"text-align:right;\"><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		read_members();
		array_multisort(array_map('strtolower', array_keys($A)), SORT_ASC, SORT_STRING, $A);
		foreach($A as $member){
			echo "<option value=\"$member[0]\" style=\"$member[2]\">$member[0]";
			if($member[1]==0){
				echo ' (!)';
			}elseif($member[1]==2){
				echo ' (G)';
			}elseif($member[1]==5){
				echo ' (M)';
			}elseif($member[1]==6){
				echo ' (SM)';
			}elseif($member[1]==7){
				echo ' (A)';
			}elseif($member[1]==8){
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
		echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[passreset]</th><td>";
		frmadm('passreset');
		echo "<table style=\"border-spacing:0px;margin-left:auto;\"><td><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($A as $member){
			echo "<option value=\"$member[0]\" style=\"$member[2]\">$member[0]</option>";
		}
		echo '</select></td><td><input type="password" name="pass"></td><td>'.submit($I['change']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
		echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[regguest]</th><td>";
		frmadm('register');
		echo "<table style=\"border-spacing:0px;margin-left:auto;\"><tr><td><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($P as $user){
			if($user[2]==1){
				echo "<option value=\"$user[0]\" style=\"$user[1]\">$user[0]</option>";
			}
		}
		echo '</select></td><td>'.submit($I['register']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
		echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[regmem]</th></tr><tr><td>";
		frmadm('regnew');
		echo "<table style=\"border-spacing:0px;margin-left:auto;\"><tr><td>$I[nick]</td><td>&nbsp;</td><td><input type=\"text\" name=\"name\" size=\"20\"></td><td>&nbsp;</td></tr>";
		echo "<tr><td>$I[pass]</td><td>&nbsp;</td><td><input type=\"password\" name=\"pass\" size=\"20\"></td><td>";
		echo submit($I['register']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
	}
	echo "</table>$H[backtochat]</div>";
	print_end();
}

function send_sessions(){
	global $H, $I, $U;
	$lines=parse_sessions();
	print_start('sessions');
	echo "<div style=\"text-align:center;\"><h1>$I[sessact]</h1><table style=\"margin-left:auto;margin-right:auto;\">";
	echo "<tr><th style=\"padding:5px;\">$I[sessnick]</th><th style=\"padding:5px\">$I[sesstimeout]</th><th style=\"padding:5px;\">$I[sessua]</th>";
	$trackip=(bool) get_setting('trackip');
	$memexpire=(int) get_setting('memberexpire');
	$guestexpire=(int) get_setting('guestexpire');
	if($trackip) echo "<th style=\"padding:5px;\">$I[sesip]</th>";
	echo "<th style=\"padding:5px;\">$I[actions]</th></tr>";
	foreach($lines as $temp){
		if($temp['status']!=0 && $temp['entry']!=0 && (!$temp['incognito'] || $temp['status']<$U['status'])){
			if($temp['status']<=2){
				$s='&nbsp;(G)';
			}elseif($temp['status']==3){
				$s='';
			}elseif($temp['status']==5){
				$s='&nbsp;(M)';
			}elseif($temp['status']==6){
				$s='&nbsp;(SM)';
			}elseif($temp['status']==7){
				$s='&nbsp;(A)';
			}elseif($temp['status']==8){
				$s='&nbsp;(SA)';
			}
			echo '<tr style="text-align:left;"><td style="padding:5px;">'.style_this($temp['nickname'].$s, $temp['style']).'</td><td style="padding:5px,">';
			if($temp['status']>2){
				get_timeout($temp['lastpost'], $memexpire);
			}else{
				get_timeout($temp['lastpost'], $guestexpire);
			}
			echo '</td>';
			if($U['status']>$temp['status'] || $U['session']===$temp['session']){
				echo "<td style=\"padding:5px;\">$temp[useragent]</td>";
				if($trackip){
					echo "<td style=\"padding:5px;\">$temp[ip]</td>";
				}
				echo '<td style="padding:5px;">';
				frmadm('sessions');
				echo hidden('nick', $temp['nickname']).submit($I['kick']).'</form></td></tr>';
			}else{
				echo '<td style="padding:5px;">-</td>';
				if($trackip){
					echo '<td style="padding:5px;">-</td>';
				}
				echo '<td style="padding:5px;">-</td></tr>';
			}
		}
	}
	echo "</table><br>$H[backtochat]</div>";
	print_end();
}

function manage_filter(){
	global $C, $I, $db, $memcached;
	if(isSet($_REQUEST['id'])){
		$_REQUEST['match']=htmlspecialchars($_REQUEST['match']);
		if(isSet($_REQUEST['regex']) && $_REQUEST['regex']==1){
			if(!is_int(@preg_match("/$_REQUEST[match]/", ''))){
				send_filter($I['incorregex']);
			}
			$reg=1;
		}else{
			$_REQUEST['match']=preg_replace('/([^\w\d])/', "\\\\$1", $_REQUEST['match']);
			$reg=0;
		}
		if(isSet($_REQUEST['allowinpm']) && $_REQUEST['allowinpm']==1){
			$pm=1;
		}else{
			$pm=0;
		}
		if(isSet($_REQUEST['kick']) && $_REQUEST['kick']==1){
			$kick=1;
		}else{
			$kick=0;
		}
		if(preg_match('/^[0-9]*$/', $_REQUEST['id'])){
			if(empty($_REQUEST['match'])){
				$stmt=$db->prepare("DELETE FROM $C[prefix]filter WHERE id=?;");
				$stmt->execute(array($_REQUEST['id']));
				if($C['memcached']){
					$memcached->delete("$C[dbname]-$C[prefix]filter");
				}
			}else{
				$stmt=$db->prepare("UPDATE $C[prefix]filter SET filtermatch=?, filterreplace=?, allowinpm=?, regex=?, kick=? WHERE id=?;");
				$stmt->execute(array($_REQUEST['match'], $_REQUEST['replace'], $pm, $reg, $kick, $_REQUEST['id']));
				if($C['memcached']){
					$memcached->delete("$C[dbname]-$C[prefix]filter");
				}
			}
		}elseif(preg_match('/^\+$/', $_REQUEST['id'])){
			$stmt=$db->prepare("INSERT INTO $C[prefix]filter (filtermatch, filterreplace, allowinpm, regex, kick) VALUES (?, ?, ?, ?, ?);");
			$stmt->execute(array($_REQUEST['match'], $_REQUEST['replace'], $pm, $reg, $kick));
			if($C['memcached']){
				$memcached->delete("$C[dbname]-$C[prefix]filter");
			}
		}
	}
}

function manage_linkfilter(){
	global $C, $I, $db, $memcached;
	if(isSet($_REQUEST['id'])){
		$_REQUEST['match']=htmlspecialchars($_REQUEST['match']);
		if(isSet($_REQUEST['regex']) && $_REQUEST['regex']==1){
			if(!is_int(@preg_match("/$_REQUEST[match]/", ''))){
				send_linkfilter($I['incorregex']);
			}
			$reg=1;
		}else{
			$_REQUEST['match']=preg_replace('/([^\w\d])/', "\\\\$1", $_REQUEST['match']);
			$reg=0;
		}
		if(preg_match('/^[0-9]*$/', $_REQUEST['id'])){
			if(empty($_REQUEST['match'])){
				$stmt=$db->prepare("DELETE FROM $C[prefix]linkfilter WHERE id=?;");
				$stmt->execute(array($_REQUEST['id']));
				if($C['memcached']){
					$memcached->delete("$C[dbname]-$C[prefix]linkfilter");
				}
			}else{
				$stmt=$db->prepare("UPDATE $C[prefix]linkfilter SET filtermatch=?, filterreplace=?, regex=? WHERE id=?;");
				$stmt->execute(array($_REQUEST['match'], $_REQUEST['replace'], $reg, $_REQUEST['id']));
				if($C['memcached']){
					$memcached->delete("$C[dbname]-$C[prefix]linkfilter");
				}
			}
		}elseif(preg_match('/^\+$/', $_REQUEST['id'])){
			$stmt=$db->prepare("INSERT INTO $C[prefix]linkfilter (filtermatch, filterreplace, regex) VALUES (?, ?, ?);");
			$stmt->execute(array($_REQUEST['match'], $_REQUEST['replace'], $reg));
			if($C['memcached']){
				$memcached->delete("$C[dbname]-$C[prefix]linkfilter");
			}
		}
	}
}

function send_filter($arg=''){
	global $C, $H, $I, $U, $db, $memcached;
	print_start('filter');
	echo "<div style=\"text-align:center;\"><h2>$I[filter]</h2><i>$arg</i><table style=\"margin-left:auto;margin-right:auto;\">";
	thr();
	echo "<tr><th><table style=\"width:100%;\"><tr><td style=\"width:8em;\">$I[fid]</td>";
	echo "<td style=\"width:12em;\">$I[match]</td>";
	echo "<td style=\"width:12em;\">$I[replace]</td>";
	echo "<td style=\"width:9em;\">$I[allowpm]</td>";
	echo "<td style=\"width:5em;\">$I[regex]</td>";
	echo "<td style=\"width:5em;\">$I[kick]</td>";
	echo "<td style=\"width:5em;\">$I[apply]</td></tr></table></th></tr>";
	if($C['memcached']){
		$filters=$memcached->get("$C[dbname]-$C[prefix]filter");
	}
	if(!$C['memcached'] || $memcached->getResultCode()!==Memcached::RES_SUCCESS){
		$filters=array();
		$result=$db->query("SELECT id, filtermatch, filterreplace, allowinpm, regex, kick FROM $C[prefix]filter;");
		while($filter=$result->fetch(PDO::FETCH_ASSOC)){
			$filters[]=array('id'=>$filter['id'], 'match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'allowinpm'=>$filter['allowinpm'], 'regex'=>$filter['regex'], 'kick'=>$filter['kick']);
		}
		if($C['memcached']){
			$memcached->set("$C[dbname]-$C[prefix]filter", $filters);
		}
	}
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
			$filter['match']=preg_replace('/(\\\\(.))/', "$2", $filter['match']);
		}
		if($filter['kick']==1){
			$checkedk=' checked';
		}else{
			$checkedk='';
		}
		echo '<tr><td>';
		frmadm('filter');
		echo hidden('id', $filter['id']);
		echo "<table style=\"width:100%;\"><tr><th style=\"width:8em;\">$I[filter] $filter[id]:</th>";
		echo "<td style=\"width:12em;\"><input type=\"text\" name=\"match\" value=\"$filter[match]\" size=\"20\" style=\"$U[style]\"></td>";
		echo '<td style="width:12em;"><input type="text" name="replace" value="'.htmlspecialchars($filter['replace'])."\" size=\"20\" style=\"$U[style]\"></td>";
		echo "<td style=\"width:9em;\"><input type=\"checkbox\" name=\"allowinpm\" id=\"allowinpm-$filter[id]\" value=\"1\"$check><label for=\"allowinpm-$filter[id]\">$I[allowpm]</label></td>";
		echo "<td style=\"width:5em;\"><input type=\"checkbox\" name=\"regex\" id=\"regex-$filter[id]\" value=\"1\"$checked><label for=\"regex-$filter[id]\">$I[regex]</label></td>";
		echo "<td style=\"width:5em;\"><input type=\"checkbox\" name=\"kick\" id=\"kick-$filter[id]\" value=\"1\"$checkedk><label for=\"kick-$filter[id]\">$I[kick]</label></td>";
		echo '<td style="width:5em;text-align:right;">'.submit($I['change']).'</td></tr></table></form></td></tr>';
	}
	echo '<tr><td>';
	frmadm('filter');
	echo hidden('id', '+');
	echo "<table style=\"width:100%;\"><tr><th style=\"width:8em\">$I[newfilter]</th>";
	echo "<td style=\"width:12em;\"><input type=\"text\" name=\"match\" value=\"\" size=\"20\" style=\"$U[style]\"></td>";
	echo "<td style=\"width:12em;\"><input type=\"text\" name=\"replace\" value=\"\" size=\"20\" style=\"$U[style]\"></td>";
	echo "<td style=\"width:9em;\"><input type=\"checkbox\" name=\"allowinpm\" id=\"allowinpm\" value=\"1\"><label for=\"allowinpm\">$I[allowpm]</label></td>";
	echo "<td style=\"width:5em;\"><input type=\"checkbox\" name=\"regex\" id=\"regex\" value=\"1\"><label for=\"regex\">$I[regex]</label></td>";
	echo "<td style=\"width:5em;\"><input type=\"checkbox\" name=\"kick\" id=\"kick\" value=\"1\"><label for=\"kick\">$I[kick]</label></td>";
	echo '<td style="width:5em;text-align:right;">'.submit($I['add']).'</td></tr></table></form></td></tr>';
	echo "</table><br>$H[backtochat]</div>";
	print_end();
}

function send_linkfilter($arg=''){
	global $C, $H, $I, $U, $db, $memcached;
	print_start('linkfilter');
	echo "<div style=\"text-align:center;\"><h2>$I[linkfilter]</h2><i>$arg</i><table style=\"margin-left:auto;margin-right:auto;\">";
	thr();
	echo "<tr><th><table style=\"width:100%;\"><tr><td style=\"width:8em;\">$I[fid]</td>";
	echo "<td style=\"width:12em;\">$I[match]</td>";
	echo "<td style=\"width:12em;\">$I[replace]</td>";
	echo "<td style=\"width:5em;\">$I[regex]</td>";
	echo "<td style=\"width:5em;\">$I[apply]</td></tr></table></th></tr>";
	if($C['memcached']){
		$filters=$memcached->get("$C[dbname]-$C[prefix]linkfilter");
	}
	if(!$C['memcached'] || $memcached->getResultCode()!==Memcached::RES_SUCCESS){
		$filters=array();
		$result=$db->query("SELECT id, filtermatch, filterreplace, regex FROM $C[prefix]linkfilter;");
		while($filter=$result->fetch(PDO::FETCH_ASSOC)){
			$filters[]=array('id'=>$filter['id'], 'match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'regex'=>$filter['regex']);
		}
		if($C['memcached']){
			$memcached->set("$C[dbname]-$C[prefix]linkfilter", $filters);
		}
	}
	foreach($filters as $filter){
		if($filter['regex']==1){
			$checked=' checked';
		}else{
			$checked='';
			$filter['match']=preg_replace('/(\\\\(.))/', "$2", $filter['match']);
		}
		echo '<tr><td>';
		frmadm('linkfilter');
		echo hidden('id', $filter['id']);
		echo "<table style=\"width:100%;\"><tr><th style=\"width:8em;\">$I[filter] $filter[id]:</th>";
		echo "<td style=\"width:12em;\"><input type=\"text\" name=\"match\" value=\"$filter[match]\" size=\"20\" style=\"$U[style]\"></td>";
		echo '<td style="width:12em;"><input type="text" name="replace" value="'.htmlspecialchars($filter['replace'])."\" size=\"20\" style=\"$U[style]\"></td>";
		echo "<td style=\"width:5em;\"><input type=\"checkbox\" name=\"regex\" id=\"regex-$filter[id]\" value=\"1\"$checked><label for=\"regex-$filter[id]\">$I[regex]</label></td>";
		echo '<td style="width:5em;text-align:right;">'.submit($I['change']).'</td></tr></table></form></td></tr>';
	}
	echo '<tr><td>';
	frmadm('linkfilter');
	echo hidden('id', '+');
	echo "<table style=\"width:100%;\"><tr><th style=\"width:8em;\">$I[newfilter]</th>";
	echo "<td style=\"width:12em;\"><input type=\"text\" name=\"match\" value=\"\" size=\"20\" style=\"$U[style]\"></td>";
	echo "<td style=\"width:12em;\"><input type=\"text\" name=\"replace\" value=\"\" size=\"20\" style=\"$U[style]\"></td>";
	echo "<td style=\"width:5em;\"><input type=\"checkbox\" name=\"regex\" id=\"regex\" value=\"1\"><label for=\"regex\">$I[regex]</label></td>";
	echo '<td style="width:5em;text-align:right;">'.submit($I['add']).'</td></tr></table></form></td></tr>';
	echo "</table><br>$H[backtochat]</div>";
	print_end();
}

function send_frameset(){
	global $C, $H, $I, $U;
	echo "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Frameset//EN\" \"http://www.w3.org/TR/html4/frameset.dtd\"><html><head>$H[meta_html]";
	echo '<title>'.get_setting('chatname').'</title>';
	print_stylesheet();
	if(isSet($_COOKIE['test'])){
		echo "</head><frameset rows=\"100,*,60\" border=\"3\" frameborder=\"3\" framespacing=\"3\"><frame name=\"post\" src=\"$_SERVER[SCRIPT_NAME]?action=post\"><frame name=\"view\" src=\"$_SERVER[SCRIPT_NAME]?action=view\"><frame name=\"controls\" src=\"$_SERVER[SCRIPT_NAME]?action=controls\"><noframes><body>$I[noframes]$H[backtologin]</body></noframes></frameset></html>";
	}else{
		echo "</head><frameset rows=\"100,*,60\" border=\"3\" frameborder=\"3\" framespacing=\"3\"><frame name=\"post\" src=\"$_SERVER[SCRIPT_NAME]?action=post&session=$U[session]&lang=$C[lang]\"><frame name=\"view\" src=\"$_SERVER[SCRIPT_NAME]?action=view&session=$U[session]&lang=$C[lang]\"><frame name=\"controls\" src=\"$_SERVER[SCRIPT_NAME]?action=controls&session=$U[session]&lang=$C[lang]\"><noframes><body>$I[noframes]$H[backtologin]</body></noframes></frameset></html>";
	}
	exit;
}

function send_messages($js){
	global $C, $I, $U;
	if(!$js){
		if(isSet($_COOKIE[$C['cookiename']])){
			print_start('messages', $U['refresh'], "$_SERVER[SCRIPT_NAME]?action=view");
			if(get_setting('enablejs')==1){
				echo "<script type=\"text/javascript\">window.location.assign('$_SERVER[SCRIPT_NAME]?action=jsview');</script>";
			}
		}else{
			print_start('messages', $U['refresh'], "$_SERVER[SCRIPT_NAME]?action=view&session=$U[session]&lang=$C[lang]");
			if(get_setting('enablejs')==1){
				echo "<script type=\"text/javascript\">window.location.assign('$_SERVER[SCRIPT_NAME]?action=jsview&session=$U[session]&lang=$C[lang]');</script>";
			}
		}
	}else{
		print_start('messages');
	}
	echo '<a id="top"></a>';
	echo '<div id="topic">';
	echo get_setting('topic');
	echo '</div><div id="chatters">';
	print_chatters();
	echo "</div><a style=\"position:fixed;top:0.5em;right:0.5em\" href=\"#bottom\">$I[bottom]</a><div id=\"messages\">";
	print_messages();
	echo '</div>';
	if($js){
		echo "<script type=\"text/javascript\">var id=$_REQUEST[id]; setInterval(function (){xmlhttp=new XMLHttpRequest(); xmlhttp.onreadystatechange=function(){if(xmlhttp.readyState==4 && xmlhttp.status==200){if(xmlhttp.responseText.match(/^</)){document.write(xmlhttp.responseText);}else{var obj=JSON.parse(xmlhttp.responseText); id=obj[0]; document.getElementById(\"messages\").innerHTML=obj[1]+document.getElementById(\"messages\").innerHTML; document.getElementById(\"chatters\").innerHTML=obj[2]; document.getElementById(\"topic\").innerHTML=obj[3];}}}; xmlhttp.open('POST','$_SERVER[SCRIPT_NAME]?action=jsrefresh&session=$U[session]&id='+id,true); xmlhttp.send();}, $U[refresh]000);</script>";
	}
	echo "<a id=\"bottom\"></a><a style=\"position:fixed;bottom:0.5em;right:0.5em\" href=\"#top\">$I[top]</a>";
	print_end();
}

function send_notes($type){
	global $C, $H, $I, $U, $db;
	print_start('notes');
	echo '<div style="text-align:center;">';
	if($U['status']>=6){
		echo "<table style=\"margin-left:auto;margin-right:auto;\"><tr><td><$H[form] target=\"view\">$H[commonform]".hidden('action', 'notes').hidden('do', 'admin').submit($I['admnotes']).'</form></td>';
		echo "<td><$H[form] target=\"view\">$H[commonform]".hidden('action', 'notes').submit($I['notes']).'</form></td></tr></table>';
	}
	if($type==='staff'){
		echo "<h2>$I[staffnotes]</h2><p>";
	}else{
		echo "<h2>$I[adminnotes]</h2><p>";
	}
	if(isset($_REQUEST['text'])){
		if($C['msgencrypted']){
			$_REQUEST['text']=openssl_encrypt($_REQUEST['text'], 'aes-256-cbc', $C['encryptkey'], 0, '1234567890123456');
		}
		$time=time();
		$stmt=$db->prepare("INSERT INTO $C[prefix]notes (type, lastedited, editedby, text) VALUES (?, ?, ?, ?);");
		$stmt->execute(array($type, $time, $U['nickname'], $_REQUEST['text']));
		$offset=get_setting('numnotes');
		$stmt=$db->prepare("SELECT id FROM $C[prefix]notes WHERE type=? ORDER BY id DESC LIMIT 1 OFFSET $offset;");
		$stmt->execute(array($type));
		if($id=$stmt->fetch(PDO::FETCH_NUM)){
			$stmt=$db->prepare("DELETE FROM $C[prefix]notes WHERE type=? AND id <=?;");
			$stmt->execute(array($type, $id[0]));
		}
		echo "<b>$I[notessaved]</b> ";
	}
	$dateformat=get_setting('dateformat');
	$stmt=$db->prepare("SELECT COUNT(*) FROM $C[prefix]notes WHERE type=?;");
	$stmt->execute(array($type));
	$num=$stmt->fetch(PDO::FETCH_NUM);
	if(!empty($_REQUEST['revision'])){
		$revision=intval($_REQUEST['revision']);
	}else{
		$revision=0;
	}
	$stmt=$db->prepare("SELECT * FROM $C[prefix]notes WHERE type=? ORDER BY id DESC LIMIT 1 OFFSET $revision;");
	$stmt->execute(array($type));
	if($note=$stmt->fetch(PDO::FETCH_ASSOC)){
			printf($I['lastedited'], $note['editedby'], date($dateformat, $note['lastedited']));
	}else{
		$note['text']='';
	}
	if($C['msgencrypted']){
		$note['text']=openssl_decrypt($note['text'], 'aes-256-cbc', $C['encryptkey'], 0, '1234567890123456');
	}
	echo "</p><$H[form]>$H[commonform]";
	if($type==='admin'){
		echo hidden('do', 'admin');
	}
	echo hidden('action', 'notes')."<textarea name=\"text\" rows=\"$U[notesboxheight]\" cols=\"$U[notesboxwidth]\">".htmlspecialchars($note['text']).'</textarea><br>';
	echo submit($I['savenotes']).'</form><br>';
	if($num[0]>1){
		echo "<br><table style=\"margin-left:auto;margin-right:auto;\"><tr><td>$I[revisions]</td>";
		if($revision<$num[0]-1){
			echo "<td><$H[form]>$H[commonform]".hidden('action', 'notes').hidden('revision', $revision+1);
			if($type==='admin'){
				echo hidden('do', 'admin');
			}
			echo submit($I['older']).'</form></td>';
		}
		if($revision>0){
			echo "<td><$H[form]>$H[commonform]".hidden('action', 'notes').hidden('revision', $revision-1);
			if($type==='admin'){
				echo hidden('do', 'admin');
			}
			echo submit($I['newer']).'</form></td>';
		}
		echo '</tr></table>';
	}
	echo '</div>';
	print_end();
}

function send_approve_waiting(){
	global $C, $H, $I, $db;
	print_start('approve_waiting');
	echo "<div style=\"text-align:center;\"><h2>$I[waitingroom]</h2>";
	$result=$db->query("SELECT * FROM $C[prefix]sessions WHERE entry=0 AND status=1 ORDER BY id;");
	if($tmp=$result->fetchAll(PDO::FETCH_ASSOC)){
		frmadm('approve');
		echo '<table style="text-align:left;margin-left:auto;margin-right:auto;">';
		echo "<tr><th style=\"padding:5px;\">$I[sessnick]</th><th style=\"padding:5px;\">$I[sessua]</th></tr>";
		foreach($tmp as $temp){
			echo '<tr>'.hidden('alls[]', $temp['nickname'])."<td style=\"padding:5px;\"><input type=\"checkbox\" name=\"csid[]\" id=\"$temp[nickname]]\" value=\"$temp[nickname]\"><label for=\"$temp[nickname]\"> ".style_this($temp['nickname'], $temp['style'])."</label></td><td style=\"padding:5px;\">$temp[useragent]</td></tr>";
		}
		echo "</table><br><table style=\"text-align:left;margin-left:auto;margin-right:auto;\"><tr><td><input type=\"radio\" name=\"what\" value=\"allowchecked\" id=\"allowchecked\" checked><label for=\"allowchecked\">$I[allowchecked]</label></td>";
		echo "<td><input type=\"radio\" name=\"what\" value=\"allowall\" id=\"allowall\"><label for=\"allowall\">$I[allowall]</label></td>";
		echo "<td><input type=\"radio\" name=\"what\" value=\"denychecked\" id=\"denychecked\"><label for=\"denychecked\">$I[denychecked]</label></td>";
		echo "<td><input type=\"radio\" name=\"what\" value=\"denyall\" id=\"denyall\"><label for=\"denyall\">$I[denyall]</label></td></tr><tr><td colspan=\"8\" style=\"text-align:center;\">$I[denymessage] <input type=\"text\" name=\"kickmessage\" size=\"45\"></td>";
		echo '</tr><tr><td colspan="8" style="text-align:center;">'.submit($I['butallowdeny']).'</td></tr></table></form>';
	}else{
		echo "$I[waitempty]<br>";
	}
	echo "<br>$H[backtochat]</div>";
	print_end();
}

function send_waiting_room(){
	global $C, $H, $I, $U, $countmods, $db;
	parse_sessions();
	$ga=(int) get_setting('guestaccess');
	if($ga===3 && $countmods>0){
		$wait=false;
	}else{
		$wait=true;
	}
	if(!isSet($U['session'])){
		setcookie($C['cookiename'], false);
		send_error($I['expire']);
	}
	if($U['status']==0){
		setcookie($C['cookiename'], false);
		send_error("$I[kicked]<br>$U[kickmessage]");
	}
	$timeleft=get_setting('entrywait')-(time()-$U['lastpost']);
	if($wait && ($timeleft<=0 || $ga===1)){
		$U['entry']=$U['lastpost'];
		$stmt=$db->prepare("UPDATE $C[prefix]sessions SET entry=lastpost WHERE session=?;");
		$stmt->execute(array($U['session']));
		send_frameset();
	}elseif(!$wait && $U['entry']!=0){
		send_frameset();
	}else{
		$refresh=(int) get_setting('defaultrefresh');
		if(isSet($_COOKIE['test'])){
			header("Refresh: $refresh; URL=$_SERVER[SCRIPT_NAME]?action=wait");
			print_start('waitingroom', $refresh, "$_SERVER[SCRIPT_NAME]?action=wait");
		}else{
			header("Refresh: $refresh; URL=$_SERVER[SCRIPT_NAME]?action=wait&session=$U[session]");
			print_start('waitingroom', $refresh, "$_SERVER[SCRIPT_NAME]?action=wait&session=$U[session]&lang=$C[lang]");
		}
		echo "<div style=\"text-align:center;\"><h2>$I[waitingroom]</h2><p>";
		if($wait){
			printf($I['waittext'], style_this($U['nickname'], $U['style']), $timeleft);
		}else{
			printf($I['admwaittext'], style_this($U['nickname'], $U['style']));
		}
		echo '</p><br><p>';
		printf($I['waitreload'], $refresh);
		echo '</p><br><br>';
		echo "<hr><$H[form]>$H[commonform]";
		if(!isSet($_REQUEST['session'])){
			hidden('session', $U['session']);
		}
		echo hidden('action', 'wait').submit($I['reload']).'</form><br>';
		echo "<$H[form]>$H[commonform]";
		if(!isSet($_REQUEST['session'])){
			hidden('session', $U['session']);
		}
		echo hidden('action', 'logout').submit($I['exit'], 'id="exitbutton"').'</form>';
		$rulestxt=get_setting('rulestxt');
		if(!empty($rulestxt)){
			echo "<h2>$I[rules]</h2><b>$rulestxt</b>";
		}
		echo '</div>';
		print_end();
	}
}

function send_choose_messages(){
	global $H, $I, $U;
	print_start('choose_messages');
	frmadm('clean');
	echo hidden('what', 'selected').submit($I['delselmes'], 'class="delbutton"').'<br><br>';
	print_messages($U['status']);
	echo "</form><br>$H[backtochat]";
	print_end();
}

function send_del_confirm(){
	global $I;
	print_start('del_confirm');
	echo "<div style=\"text-align:center;\"><table style=\"margin-left:auto;margin-right:auto;\"><tr><td colspan=\"2\">$I[confirm]</td></tr><tr><td>";
	frmpst('delete');
	if(isSet($_REQUEST['multi'])){
		echo hidden('multi', 'on');
	}
	if(isSet($_REQUEST['sendto'])){
		echo hidden('sendto', $_REQUEST['sendto']);
	}
	echo hidden('confirm', 'yes').hidden('what', $_REQUEST['what']).submit($I['yes'], 'class="delbutton"').'</form></td><td>';
	frmpst('post');
	if(isSet($_REQUEST['multi'])){
		echo hidden('multi', 'on');
	}
	if(isSet($_REQUEST['sendto'])){
		echo hidden('sendto', $_REQUEST['sendto']);
	}
	echo submit($I['no'], 'class="backbutton"').'</form></td><tr></table></div>';
	print_end();
}

function send_post(){
	global $I, $P, $U, $countmods;
	$U['postid']=substr(time(), -6);
	print_start('post');
	if(!isSet($_REQUEST['sendto'])){
		$_REQUEST['sendto']='';
	}
	echo '<div style="text-align:center;"><table style="border-spacing:0px;margin-left:auto;margin-right:auto;"><tr><td>';
	frmpst('post');
	echo hidden('postid', $U['postid']);
	if(isSet($_REQUEST['multi'])){
		echo hidden('multi', 'on');
	}
	echo '<table style="border-spacing:0px;"><tr style="vertical-align:top;"><td>'.style_this($U['nickname'], $U['style']).'</td><td>:</td>';
	if(!isSet($U['rejected'])){
		$U['rejected']='';
	}
	if(isSet($_REQUEST['multi'])){
		echo "<td><textarea name=\"message\" rows=\"$U[boxheight]\" cols=\"$U[boxwidth]\" style=\"$U[style]\" autofocus>$U[rejected]</textarea></td>";
	}else{
		echo "<td><input type=\"text\" name=\"message\" value=\"$U[rejected]\" size=\"$U[boxwidth]\" style=\"$U[style]\" autofocus></td>";
	}
	echo '<td>'.submit($I['talkto']).'</td><td><select name="sendto" size="1">';
	echo '<option ';
	if($_REQUEST['sendto']==='*'){
		echo 'selected ';
	}
	echo "value=\"*\">-$I[toall]-</option>";
	if($U['status']>=3){
		echo '<option ';
		if($_REQUEST['sendto']==='?'){
			echo 'selected ';
		}
		echo "value=\"?\">-$I[tomem]-</option>";
	}
	if($U['status']>=5){
		echo '<option ';
		if($_REQUEST['sendto']==='#'){
			echo 'selected ';
		}
		echo "value=\"#\">-$I[tostaff]-</option>";
	}
	if($U['status']>=6){
		echo '<option ';
		if($_REQUEST['sendto']==='&'){
			echo 'selected ';
		}
		echo "value=\"&\">-$I[toadmin]-</option>";
	}
	$ignored=array();
	$ignore=get_ignored();
	foreach($ignore as $ign){
		if($ign['ignored']===$U['nickname']){
			$ignored[]=$ign['by'];
		}
		if($ign['by']===$U['nickname']){
			$ignored[]=$ign['ignored'];
		}
	}
	array_multisort(array_map('strtolower', array_keys($P)), SORT_ASC, SORT_STRING, $P);
	foreach($P as $user){
		if($U['nickname']!==$user[0] && !in_array($user[0], $ignored)){
			echo '<option ';
			if($_REQUEST['sendto']===$user[0]){
				echo 'selected ';
			}
			echo "value=\"$user[0]\" style=\"$user[1]\">$user[0]</option>";
		}
	}
	echo '</select>';
	if($U['status']>=5 || ($U['status']>=3 && $countmods===0 && get_setting('memkick'))){
		echo "<input type=\"checkbox\" name=\"kick\" id=\"kick\" value=\"kick\"><label for=\"kick\">&nbsp;$I[kick]</label>";
		echo "<input type=\"checkbox\" name=\"what\" id=\"what\" value=\"purge\" checked><label for=\"what\">&nbsp;$I[alsopurge]</label>";
	}
	echo '</td></tr></table></form></td></tr><tr><td style="height:8px;"></td></tr><tr><td><table style="border-spacing:0px;margin-left:auto;margin-right:auto;"><tr><td>';
	frmpst('delete');
	if(isSet($_REQUEST['multi'])){
		echo hidden('multi', 'on');
	}
	echo hidden('sendto', $_REQUEST['sendto']).hidden('what', 'last');
	echo submit($I['dellast'], 'class="delbutton"').'</form></td><td>';
	frmpst('delete', 'all');
	if(isSet($_REQUEST['multi'])){
		echo hidden('multi', 'on');
	}
	echo hidden('sendto', $_REQUEST['sendto']).hidden('what', 'all');
	echo submit($I['delall'], 'class="delbutton"').'</form></td><td style="width:10px;"></td><td>';
	frmpst('post');
	if(isSet($_REQUEST['multi'])){
		echo submit($I['switchsingle']);
	}else{
		echo hidden('multi', 'on').submit($I['switchmulti']);
	}
	echo hidden('sendto', $_REQUEST['sendto']).'</form></td>';
	echo '</tr></table></td></tr></table></div>';
	print_end();
}

function send_help(){
	global $H, $I, $U;
	print_start('help');
	$rulestxt=get_setting('rulestxt');
	if(!empty($rulestxt)){
		echo "<h2>$I[rules]</h2>$rulestxt<br><br><hr>";
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
	echo "<br><hr><div style=\"text-align:center;\">$H[backtochat]$H[credit]</div>";
	print_end();
}

function send_profile($arg=''){
	global $C, $F, $H, $I, $P, $U, $db;
	print_start('profile');
	echo "<div style=\"text-align:center;\"><$H[form]>$H[commonform]".hidden('action', 'profile').hidden('do', 'save')."<h2>$I[profile]</h2><i>$arg</i><table style=\"margin-left:auto;margin-right:auto;\">";
	thr();
	array_multisort(array_map('strtolower', array_keys($P)), SORT_ASC, SORT_STRING, $P);
	$ignored=array();
	$ignore=get_ignored();
	foreach($ignore as $ign){
		if($ign['by']===$U['nickname']){
			$ignored[]=$ign['ignored'];
		}
	}
	if(count($ignored)>0){
		echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[unignore]</th><td style=\"text-align:right;\">";
		echo "<select name=\"unignore\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($ignored as $ign){
			$style='';
			foreach($P as $user){
				if($ign===$user[0]){
					$style=" style=\"$user[1]\"";
					break;
				}
			}
			echo "<option value=\"$ign\"$style>$ign</option>";
		}
		echo '</select></td></tr></table></td></tr>';
		thr();
	}
	if(count($P)-count($ignored)>1){
		echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[ignore]</th><td style=\"text-align:right;\">";
		echo "<select name=\"ignore\" size=\"1\"><option value=\"\">$I[choose]</option>";
		$stmt=$db->query("SELECT poster FROM $C[prefix]messages GROUP BY poster;");
		while($nick=$stmt->fetch(PDO::FETCH_NUM)){
			$nicks[]=$nick[0];
		}
		foreach($P as $user){
			if($U['nickname']!==$user[0] && !in_array($user[0], $ignored) && in_array($user[0], $nicks)){
				echo "<option value=\"$user[0]\" style=\"$user[1]\">$user[0]</option>";
			}
		}
		echo '</select></td></tr></table></td></tr>';
		thr();
	}
	echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[refreshrate]</th><td style=\"text-align:right;\">";
	echo "<input type=\"number\" name=\"refresh\" size=\"3\" maxlength=\"3\" min=\"5\" max=\"150\" value=\"$U[refresh]\"></td></tr></table></td></tr>";
	thr();
	if(!isSet($_COOKIE[$C['cookiename']])){
		$param="&session=$U[session]&lang=$C[lang]";
	}else{
		$param='';
	}
	preg_match('/#([0-9a-f]{6})/i', $U['style'], $matches);
	echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><td><b>$I[fontcolour]</b> (<a href=\"$_SERVER[SCRIPT_NAME]?action=colours$param\" target=\"view\">$I[viewexample]</a>)</td><td style=\"text-align:right;\">";
	echo "<input type=\"text\" size=\"6\" maxlength=\"6\" pattern=\"[a-fA-F0-9]{6}\" value=\"$matches[1]\" name=\"colour\"></td></tr></table></td></tr>";
	thr();
	echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><td><b>$I[bgcolour]</b> (<a href=\"$_SERVER[SCRIPT_NAME]?action=colours$param\" target=\"view\">$I[viewexample]</a>)</td><td style=\"text-align:right;\">";
	echo "<input type=\"text\" size=\"6\" maxlength=\"6\" pattern=\"[a-fA-F0-9]{6}\" value=\"$U[bgcolour]\" name=\"bgcolour\"></td></tr></table></td></tr>";
	thr();
	if($U['status']>=3){
		echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[fontface]</th><td><table style=\"border-spacing:0px;margin-left:auto;\">";
		echo "<tr><td>&nbsp;</td><td><select name=\"font\" size=\"1\"><option value=\"\">* $I[roomdefault] *</option>";
		foreach($F as $name=>$font){
			echo "<option style=\"$font\" ";
			if(strpos($U['style'], $font)!==false){
				echo 'selected ';
			}
			echo "value=\"$name\">$name</option>";
		}
		echo '</select></td><td>&nbsp;</td><td><input type="checkbox" name="bold" id="bold" value="on"';
		if(strpos($U['style'], 'font-weight:bold;')!==false){
			echo ' checked';
		}
		echo "><label for=\"bold\"><b>$I[bold]</b></label></td><td>&nbsp;</td><td><input type=\"checkbox\" name=\"italic\" id=\"italic\" value=\"on\"";
		if(strpos($U['style'], 'font-style:italic;')!==false){
			echo ' checked';
		}
		echo "><label for=\"italic\"><i>$I[italic]</i></label></td></tr></table></td></tr></table></td></tr>";
		thr();
	}
	echo '<tr><td>'.style_this("$U[nickname] : $I[fontexample]", $U['style']).'</td></tr>';
	thr();
	echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[timestamps]</th><td style=\"text-align:right;\">";
	echo '<input type="checkbox" name="timestamps" id="timestamps" value="on"';
	if($U['timestamps']){
		echo ' checked';
	}
	echo "><label for=\"timestamps\"><b>$I[enabled]</b></label></td></tr></table></td></tr>";
	thr();
	if(get_setting('imgembed')){
		echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[embed]</th><td style=\"text-align:right;\">";
		echo '<input type="checkbox" name="embed" id="embed" value="on"';
		if($U['embed'] && isSet($_COOKIE[$C['cookiename']])){
			echo ' checked';
		}
		echo "><label for=\"embed\"><b>$I[enabled]</b></label></td></tr></table></td></tr>";
		thr();
	}
	if($U['status']>=5 && get_setting('incognito')){
		echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[incognito]</th><td style=\"text-align:right;\">";
		echo '<input type="checkbox" name="incognito" id="incognito" value="on"';
		if($U['incognito']){
			echo ' checked';
		}
		echo "><label for=\"incognito\"><b>$I[enabled]</b></label></td></tr></table></td></tr>";
		thr();
	}
	echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[pbsize]</th><td><table style=\"border-spacing:0px;margin-left:auto;\">";
	echo "<tr><td>&nbsp;</td><td>$I[width]</td><td><input type=\"number\" name=\"boxwidth\" size=\"3\" maxlength=\"3\" value=\"$U[boxwidth]\"></td>";
	echo "<td>&nbsp;</td><td>$I[height]</td><td><input type=\"number\" name=\"boxheight\" size=\"3\" maxlength=\"3\" value=\"$U[boxheight]\"></td>";
	echo '</tr></table></td></tr></table></td></tr>';
	thr();
	if($U['status']>=5){
		echo "<tr><td><table style\"width:100%;text-align:left;\"><tr><th>$I[nbsize]</th><td><table style=\"border-spacing:0px;margin-left:auto;\">";
		echo "<tr><td>&nbsp;</td><td>$I[width]</td><td><input type=\"number\" name=\"notesboxwidth\" size=\"3\" maxlength=\"3\" value=\"$U[notesboxwidth]\"></td>";
		echo "<td>&nbsp;</td><td>$I[height]</td><td><input type=\"number\" name=\"notesboxheight\" size=\"3\" maxlength=\"3\" value=\"$U[notesboxheight]\"></td>";
		echo '</tr></table></td></tr></table></td></tr>';
		thr();
	}
	if($U['status']>=2){
		echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[changepass]</th></tr>";
		echo '<tr><td><table style="border-spacing:0px;margin-left:auto;">';
		echo "<tr><td>&nbsp;</td><td>$I[oldpass]</td><td><input type=\"password\" name=\"oldpass\" size=\"20\"></td></tr>";
		echo "<tr><td>&nbsp;</td><td>$I[newpass]</td><td><input type=\"password\" name=\"newpass\" size=\"20\"></td></tr>";
		echo "<tr><td>&nbsp;</td><td>$I[confirmpass]</td><td><input type=\"password\" name=\"confirmpass\" size=\"20\"></td></tr>";
		echo '</table></td></tr></table></td></tr>';
		thr();
		echo "<tr><td><table style=\"width:100%;text-align:left;\"><tr><th>$I[changenickname]</th></tr>";
		echo '<tr><td><table style="border-spacing:0px;margin-left:auto;">';
		echo "<tr><td>&nbsp;</td><td>$I[newnickname]</td><td><input type=\"text\" name=\"newnickname\" size=\"20\"></td></tr>";
		echo "<tr><td>&nbsp;</td><td>$I[newpass]</td><td><input type=\"password\" name=\"new_pass\" size=\"20\"></td></tr>";
		echo '</table></td></tr></table></td></tr>';
		thr();
	}
	echo '<tr><td>'.submit($I['savechanges'])."</td></tr></table></form><br>$H[backtochat]</div>";
	print_end();
}

function send_controls(){
	global $H, $I, $U;
	print_start('controls');
	echo '<table style="border-spacing:0px;margin-left:auto;margin-right:auto;"><tr>';
	echo "<td><$H[form] target=\"post\">$H[commonform]".hidden('action', 'post').submit($I['reloadpb']).'</form></td>';
	echo "<td><$H[form] target=\"view\">$H[commonform]".hidden('action', 'view').submit($I['reloadmsgs']).'</form></td>';
	echo "<td><$H[form] target=\"view\">$H[commonform]".hidden('action', 'profile').submit($I['chgprofile']).'</form></td>';
	if($U['status']>=5){
		echo "<td><$H[form] target=\"view\">$H[commonform]".hidden('action', 'admin').submit($I['adminbtn']).'</form></td>';
		echo "<td><$H[form] target=\"view\">$H[commonform]".hidden('action', 'notes').submit($I['notes']).'</form></td>';
	}
	if($U['status']>=3){
		echo "<td><$H[form] target=\"_blank\">$H[commonform]".hidden('action', 'login').submit($I['clone']).'</form></td>';
	}
	echo "<td><$H[form] target=\"view\">$H[commonform]".hidden('action', 'help').submit($I['randh']).'</form></td>';
	echo "<td><$H[form] target=\"_parent\">$H[commonform]".hidden('action', 'logout').submit($I['exit'], 'id="exitbutton"').'</form></td>';
	echo '</tr></table>';
	print_end();
}

function send_logout(){
	global $H, $I, $U;
	print_start('logout');
	echo '<div style="text-align:center;"><h1>'.sprintf($I['bye'], style_this($U['nickname'], $U['style']))."</h1>$H[backtologin]</div>";
	print_end();
}

function send_colours(){
	global $H, $I;
	print_start('colours');
	echo "<div style=\"text-align:center;\"><h2>$I[colourtable]</h2><tt>";
	for($red=0x00;$red<=0xFF;$red+=0x33){
		for($green=0x00;$green<=0xFF;$green+=0x33){
			for($blue=0x00;$blue<=0xFF;$blue+=0x33){
				$hcol=sprintf('%02X', $red).sprintf('%02X', $green).sprintf('%02X', $blue);
				echo "<span style=\"color:#$hcol\"><b>$hcol</b></span> ";
			}
			echo '<br>';
		}
		echo '<br>';
	}
	echo "</tt><$H[form]>$H[commonform]".hidden('action', 'profile').submit($I['backtoprofile'], ' class="backbutton"').'</form></div>';
	print_end();
}

function send_login(){
	global $H, $I, $L;
	setcookie('test', '1');
	print_start('login');
	$ga=(int) get_setting('guestaccess');
	$englobal=(int) get_setting('englobalpass');
	echo '<div style="text-align:center;"><h1>'.get_setting('chatname').'</h1>';
	echo "<$H[form] target=\"_parent\">$H[commonform]".hidden('action', 'login');
	if($englobal===1 && isSet($_POST['globalpass'])){
		echo hidden('globalpass', $_POST['globalpass']);
	}
	echo '<table style="border:2px solid;margin-left:auto;margin-right:auto;">';
	if($englobal!==1 || (isSet($_POST['globalpass']) && $_POST['globalpass']==get_setting('globalpass'))){
		echo "<tr><td style=\"text-align:left;\">$I[nick]</td><td style=\"text-align:right;\"><input type=\"text\" name=\"nick\" size=\"15\" autofocus></td></tr>";
		echo "<tr><td style=\"text-align:left;\">$I[pass]</td><td style=\"text-align:right;\"><input type=\"password\" name=\"pass\" size=\"15\"></td></tr>";
		send_captcha();
		if($ga!==0){
			if($englobal===2){
				echo "<tr><td style=\"text-align:left;\">$I[globalloginpass]</td><td style=\"text-align:right;\"><input type=\"password\" name=\"globalpass\" size=\"15\"></td></tr>";
			}
			echo "<tr><td colspan=\"2\">$I[choosecol]<br><select style=\"text-align:center;\" name=\"colour\"><option value=\"\">* $I[randomcol] *</option>";
			print_colours();
			echo '</select></td></tr>';
		}else{
			echo "<tr><td colspan=\"2\">$I[noguests]</td></tr>";
		}
		echo '<tr><td colspan="2">'.submit($I['enter']).'</td></tr></table></form>';
		get_nowchatting();
		echo '<br><br><div id="topic">';
		echo get_setting('topic');
		echo '</div>';
		$rulestxt=get_setting('rulestxt');
		if(!empty($rulestxt)){
			echo "<h2>$I[rules]</h2><b>$rulestxt</b><br>";
		}
	}else{
		echo "<tr><td style=\"text-align:left;\">$I[globalloginpass]</td><td style=\"text-align:right;\"><input type=\"password\" name=\"globalpass\" size=\"15\" autofocus></td></tr>";
		if($ga===0){
			echo "<tr><td colspan=\"2\">$I[noguests]</td></tr>";
		}
		echo '<tr><td colspan="2">'.submit($I['enter']).'</td></tr></table></form>';
	}
	echo "<p>$I[changelang]";
	foreach($L as $lang=>$name){
		echo " <a href=\"$_SERVER[SCRIPT_NAME]?lang=$lang\">$name</a>";
	}
	echo "</p>$H[credit]</div>";
	print_end();
}

function send_error($err){
	global $H, $I;
	print_start('error');
	echo "<h2>$I[error] $err</h2>$H[backtologin]";
	print_end();
}

function print_chatters(){
	global $C, $I, $P, $U, $db;
	echo '<table style="border-spacing:0px;"><tr>';
	if($U['status']>=5 && get_setting('guestaccess')==3){
		$result=$db->query("SELECT COUNT(*) FROM $C[prefix]sessions WHERE entry=0 AND status=1;");
		$temp=$result->fetch(PDO::FETCH_NUM);
		if($temp[0]>0){
			echo '<td>';
			frmadm('approve');
			echo submit(sprintf($I['approveguests'], $temp[0])).'</form></td><td>&nbsp;</td>';
		}
	}
	foreach($P as $user){
		if($user[2]<=2){
			$G[]=style_this($user[0], $user[1]);
		}else{
			$M[]=style_this($user[0], $user[1]);
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
	echo '</tr></table>';
}

//  session management

function create_session($setup){
	global $C, $I, $U, $db, $memcached;
	$U['nickname']=preg_replace('/\s+/', '', $_REQUEST['nick']);
	$U['passhash']=md5(sha1(md5($U['nickname'].$_REQUEST['pass'])));
	if(!check_member()){
		add_user_defaults();
	}
	$U['entry']=$U['lastpost']=time();
	if($setup){
		$U['incognito']=1;
	}
	if(get_setting('captcha')>0 && ($U['status']==1 || get_setting('dismemcaptcha')==0)){
		if(!isSet($_REQUEST['challenge'])){
			send_error($I['wrongcaptcha']);
		}
		if(!$C['memcached']){
			$stmt=$db->prepare("SELECT code FROM $C[prefix]captcha WHERE id=?;");
			$stmt->execute(array($_REQUEST['challenge']));
			$stmt->bindColumn(1, $code);
			if(!$stmt->fetch(PDO::FETCH_BOUND)){
				send_error($I['captchaexpire']);
			}
			$timeout=time()-get_setting('captchatime');
			$stmt=$db->prepare("DELETE FROM $C[prefix]captcha WHERE id=? OR time<?;");
			$stmt->execute(array($_REQUEST['challenge'], $timeout));
		}else{
			if(!$code=$memcached->get("$C[dbname]-$C[prefix]captcha-$_REQUEST[challenge]")){
				send_error($I['captchaexpire']);
			}
			$memcached->delete("$C[dbname]-$C[prefix]captcha-$_REQUEST[challenge]");
		}
		if($_REQUEST['captcha']!=$code){
			send_error($I['wrongcaptcha']);
		}
	}
	if($U['status']==1){
		$ga=(int) get_setting('guestaccess');
		if(!valid_nick($U['nickname'])){
			send_error(sprintf($I['invalnick'], get_setting('maxname')));
		}
		if(!valid_pass($_REQUEST['pass'])){
			send_error(sprintf($I['invalpass'], get_setting('minpass')));
		}
		if($ga===0){
			send_error($I['noguests']);
		}elseif($ga===3){
			$U['entry']=0;
		}
		if(get_setting('englobalpass')!=0 && isSet($_REQUEST['globalpass']) && $_REQUEST['globalpass']!=get_setting('globalpass')){
			send_error($I['wrongglobalpass']);
		}
	}
	write_new_session();
}

function write_new_session(){
	global $C, $I, $U, $db;
	$lines=parse_sessions();
	$sids; $reentry=false;
	foreach($lines as $temp){
		$sids[$temp['session']]=true;// collect all existing ids
		if($temp['nickname']===$U['nickname']){// nick already here?
			if($U['passhash']===$temp['passhash']){
				$U=$temp;
				if($U['status']==0){
					setcookie($C['cookiename'], false);
					send_error("$I[kicked]<br>$U[kickmessage]");
				}
				setcookie($C['cookiename'], $U['session']);
				$reentry=true;
				break;
			}else{
				send_error($I['wrongpass']);
			}
		}
	}
	// create new session:
	if(!$reentry){
		do{
			$U['session']=md5(time().mt_rand().$U['nickname']);
		}while(isSet($sids[$U['session']]));// check for hash collision
		if(isSet($_SERVER['HTTP_USER_AGENT'])){
			$useragent=htmlspecialchars($_SERVER['HTTP_USER_AGENT']);
		}else{
			$useragent='';
		}
		if(get_setting('trackip')){
			$ip=$_SERVER['REMOTE_ADDR'];
		}else{
			$ip='';
		}
		$stmt=$db->prepare("INSERT INTO $C[prefix]sessions (session, nickname, status, refresh, style, lastpost, passhash, boxwidth, boxheight, useragent, bgcolour, notesboxwidth, notesboxheight, entry, timestamps, embed, incognito, ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);");
		$stmt->execute(array($U['session'], $U['nickname'], $U['status'], $U['refresh'], $U['style'], $U['lastpost'], $U['passhash'], $U['boxwidth'], $U['boxheight'], $useragent, $U['bgcolour'], $U['notesboxwidth'], $U['notesboxheight'], $U['entry'], $U['timestamps'], $U['embed'], $U['incognito'], $ip));
		setcookie($C['cookiename'], $U['session']);
		if($U['status']>=3 && !$U['incognito']){
			add_system_message(sprintf(get_setting('msgenter'), style_this($U['nickname'], $U['style'])));
		}
	}
}

function approve_session(){
	global $C, $db;
	if(isSet($_REQUEST['what'])){
		if($_REQUEST['what']==='allowchecked' && isSet($_REQUEST['csid'])){
			$stmt=$db->prepare("UPDATE $C[prefix]sessions SET entry=lastpost WHERE nickname=?;");
			foreach($_REQUEST['csid'] as $nick){
				$stmt->execute(array($nick));
			}
		}elseif($_REQUEST['what']==='allowall' && isSet($_REQUEST['alls'])){
			$stmt=$db->prepare("UPDATE $C[prefix]sessions SET entry=lastpost WHERE nickname=?;");
			foreach($_REQUEST['alls'] as $nick){
				$stmt->execute(array($nick));
			}
		}elseif($_REQUEST['what']==='denychecked' && isSet($_REQUEST['csid'])){
			$time=60*(get_setting('kickpenalty')-get_setting('guestexpire'))+time();
			$stmt=$db->prepare("UPDATE $C[prefix]sessions SET lastpost=?, status=0, kickmessage=? WHERE nickname=? AND status=1;");
			foreach($_REQUEST['csid'] as $nick){
				$stmt->execute(array($time, $_REQUEST['kickmessage'], $nick));
			}
		}elseif($_REQUEST['what']==='denyall' && isSet($_REQUEST['alls'])){
			$time=60*(get_setting('kickpenalty')-get_setting('guestexpire'))+time();
			$stmt=$db->prepare("UPDATE $C[prefix]sessions SET lastpost=?, status=0, kickmessage=? WHERE nickname=? AND status=1;");
			foreach($_REQUEST['alls'] as $nick){
				$stmt->execute(array($time, $_REQUEST['kickmessage'], $nick));
			}
		}
	}
}

function check_login(){
	global $C, $I, $U, $db;
	$ga=(int) get_setting('guestaccess');
	if(isSet($_POST['session'])){
		$stmt=$db->prepare("SELECT * FROM $C[prefix]sessions WHERE session=?;");
		$stmt->execute(array($_POST['session']));
		if($U=$stmt->fetch(PDO::FETCH_ASSOC)){
			if($U['status']==0){
				setcookie($C['cookiename'], false);
				send_error("$I[kicked]<br>$U[kickmessage]");
			}else{
				setcookie($C['cookiename'], $U['session']);
			}
		}else{
			setcookie($C['cookiename'], false);
			send_error($I['expire']);

		}
	}elseif(get_setting('englobalpass')==1 && (!isSet($_POST['globalpass']) || $_POST['globalpass']!=get_setting('globalpass'))){
		send_error($I['wrongglobalpass']);
	}elseif(!isSet($_REQUEST['nick']) || !isSet($_REQUEST['pass'])){
		send_login();
	}else{
		create_session(false);
	}
	if($U['status']==1){
		if($ga===2 || $ga===3){
			$stmt=$db->prepare("UPDATE $C[prefix]sessions SET entry=0 WHERE session=?;");
			$stmt->execute(array($U['session']));
			send_waiting_room();
		}
	}
}

function kill_session(){
	global $C, $I, $U, $db;
	parse_sessions();
	setcookie($C['cookiename'], false);
	if(!isSet($U['session'])){
		send_error($I['expire']);
	}
	if($U['status']==0){
		send_error("$I[kicked]<br>$U[kickmessage]");
	}
	$stmt=$db->prepare("DELETE FROM $C[prefix]sessions WHERE session=?;");
	$stmt->execute(array($U['session']));
	if($U['status']==1){
		$stmt=$db->prepare("UPDATE $C[prefix]messages SET poster='' WHERE poster=? AND poststatus=9;");
		$stmt->execute(array($U['nickname']));
		$stmt=$db->prepare("UPDATE $C[prefix]messages SET recipient='' WHERE recipient=? AND poststatus=9;");
		$stmt->execute(array($U['nickname']));
		$stmt=$db->prepare("DELETE FROM $C[prefix]ignored WHERE ign=? OR ignby=?;");
		$stmt->execute(array($U['nickname'], $U['nickname']));
		$db->exec("DELETE FROM $C[prefix]messages WHERE poster='' AND recipient='' AND poststatus=9;");
	}elseif($U['status']>=3 && !$U['incognito']){
		add_system_message(sprintf(get_setting('msgexit'), style_this($U['nickname'], $U['style'])));
	}
}

function kick_chatter($names, $mes, $purge){
	global $C, $P, $U, $db;
	$lonick='';
	$lines=parse_sessions();
	$time=60*(get_setting('kickpenalty')-get_setting('guestexpire'))+time();
	$stmt=$db->prepare("UPDATE $C[prefix]sessions SET lastpost=?, status=0, kickmessage=? WHERE session=? AND status!=0;");
	$i=0;
	foreach($names as $name){
		foreach($lines as $temp){
			if(($temp['nickname']===$U['nickname'] && $U['nickname']===$name) || ($U['status']>$temp['status'] && (($temp['nickname']===$name && $temp['status']>0) || ($name==='&' && $temp['status']==1)))){
				$stmt->execute(array($time, $mes, $temp['session']));
				if($purge){
					del_all_messages($temp['nickname'], 10, 0);
				}
				$lonick.=style_this($temp['nickname'], $temp['style']).', ';
				++$i;
				unset($P[$name]);
			}
		}
	}
	if(!empty($lonick)){
		if($names[0]==='&'){
			add_system_message(get_setting('msgallkick'));
		}else{
			$lonick=preg_replace('/\,\s$/','',$lonick);
			if($i>1){
				add_system_message(sprintf(get_setting('msgmultikick'), $lonick));
			}else{
				add_system_message(sprintf(get_setting('msgkick'), $lonick));
			}
		}
	}
	if(!empty($lonick)){
		return true;
	}
	return false;
}

function logout_chatter($names){
	global $C, $P, $U, $db;
	$lines=parse_sessions();
	$stmt=$db->prepare("DELETE FROM $C[prefix]sessions WHERE session=? AND status<? AND status!=0;");
	$stmt1=$db->prepare("UPDATE $C[prefix]messages SET poster='' WHERE poster=? AND poststatus=9;");
	$stmt2=$db->prepare("UPDATE $C[prefix]messages SET recipient='' WHERE recipient=? AND poststatus=9;");
	$stmt3=$db->prepare("DELETE FROM $C[prefix]ignored WHERE ign=? OR ignby=?;");
	foreach($names as $name){
		foreach($lines as $temp){
			if($temp['nickname']===$name || ($name==='&' && $temp['status']==1)){
				$stmt->execute(array($temp['session'], $U['status']));
				if($temp['status']==1){
					$stmt1->execute(array($temp['nickname']));
					$stmt2->execute(array($temp['nickname']));
					$stmt3->execute(array($temp['nickname'], $temp['nickname']));
				}
				unset($P[$name]);
			}
		}
	}
	$db->exec("DELETE FROM $C[prefix]messages WHERE poster='' AND recipient='' AND poststatus=9;");
}

function check_session(){
	global $C, $I, $U;
	parse_sessions();
	if(!isSet($U['session'])){
		setcookie($C['cookiename'], false);
		send_error($I['expire']);
	}
	if($U['status']==0){
		setcookie($C['cookiename'], false);
		send_error("$I[kicked]<br>$U[kickmessage]");
	}
	if($U['entry']==0){
		send_waiting_room();
	}
}

function get_nowchatting(){
	global $I, $P;
	parse_sessions();
	echo sprintf($I['curchat'], count($P)).'<br>';
	foreach($P as $user){
		echo style_this($user[0], $user[1]).' &nbsp; ';
	}
}

function parse_sessions(){
	global $C, $P, $U, $countmods, $db;
	$guestexpire=time()-60*get_setting('guestexpire');
	$memberexpire=time()-60*get_setting('memberexpire');
	$result=$db->prepare("SELECT nickname, status FROM $C[prefix]sessions WHERE (status<=2 AND lastpost<?) OR (status>2 AND lastpost<?);");
	$result->execute(array($guestexpire, $memberexpire));
	if($tmp=$result->fetchAll(PDO::FETCH_ASSOC)){
		$stmt=$db->prepare("DELETE FROM $C[prefix]sessions WHERE nickname=?;");
		$stmt1=$db->prepare("UPDATE $C[prefix]messages SET poster='' WHERE poster=? AND poststatus=9;");
		$stmt2=$db->prepare("UPDATE $C[prefix]messages SET recipient='' WHERE recipient=? AND poststatus=9;");
		$stmt3=$db->prepare("DELETE FROM $C[prefix]ignored WHERE ign=? OR ignby=?;");
		foreach($tmp as $temp){
			$stmt->execute(array($temp['nickname']));
			if($temp['status']<=1){
				$stmt1->execute(array($temp['nickname']));
				$stmt2->execute(array($temp['nickname']));
				$stmt3->execute(array($temp['nickname'], $temp['nickname']));
			}
		}
		$db->exec("DELETE FROM $C[prefix]messages WHERE poster='' AND recipient='' AND poststatus=9;");
	}
	$result=$db->query("SELECT * FROM $C[prefix]sessions ORDER BY status DESC, lastpost DESC;");
	if(!$lines=$result->fetchAll(PDO::FETCH_ASSOC)) $lines=array();
	if(isSet($_REQUEST['session'])){
		foreach($lines as $temp){
			if($temp['session']===$_REQUEST['session']){
				$U=$temp;
				break;
			}
		}
	}
	$countmods=0;
	$P=array();
	foreach($lines as $temp){
		if($temp['entry']!=0 && $temp['status']>0){
			if(!$temp['incognito']){
				$P[$temp['nickname']]=[$temp['nickname'], $temp['style'], $temp['status']];
			}
			if($temp['status']>=5){
				++$countmods;
			}
		}
	}
	return $lines;
}

//  member handling

function check_member(){
	global $C, $I, $U, $db;
	$stmt=$db->prepare("SELECT * FROM $C[prefix]members WHERE nickname=?;");
	$stmt->execute(array($U['nickname']));
	if($temp=$stmt->fetch(PDO::FETCH_ASSOC)){
		if($temp['passhash']===$U['passhash']){
			$U=$temp;
			$time=time();
			$stmt=$db->prepare("UPDATE $C[prefix]members SET lastlogin=? WHERE nickname=?;");
			$stmt->execute(array($time, $U['nickname']));
			return true;
		}else{
			send_error($I['wrongpass']);
		}
	}
	return false;
}

function read_members(){
	global $A, $C, $db;
	$result=$db->query("SELECT * FROM $C[prefix]members;");
	while($temp=$result->fetch(PDO::FETCH_ASSOC)){
		$A[$temp['nickname']][0]=$temp['nickname'];
		$A[$temp['nickname']][1]=$temp['status'];
		$A[$temp['nickname']][2]=$temp['style'];
	}
}

function register_guest($status){
	global $A, $C, $I, $P, $U, $db;
	if(empty($_REQUEST['name'])){
		send_admin();
	}
	if(!isSet($P[$_REQUEST['name']])){
		send_admin(sprintf($I['cantreg'], $_REQUEST['name']));
	}
	read_members();
	if(isSet($A[$_REQUEST['name']])){
		send_admin(sprintf($I['alreadyreged'], $_REQUEST['name']));
	}
	$stmt=$db->prepare("SELECT * FROM $C[prefix]sessions WHERE nickname=? AND status=1;");
	$stmt->execute(array($_REQUEST['name']));
	if($reg=$stmt->fetch(PDO::FETCH_ASSOC)){
		$reg['status']=$status;
		$P[$_REQUEST['name']][2]=$status;
		$stmt=$db->prepare("UPDATE $C[prefix]sessions SET status=? WHERE session=?;");
		$stmt->execute(array($reg['status'], $reg['session']));
	}else{
		send_admin(sprintf($I['cantreg'], $_REQUEST['name']));
	}
	$stmt=$db->prepare("INSERT INTO $C[prefix]members (nickname, passhash, status, refresh, bgcolour, boxwidth, boxheight, regedby, timestamps, embed, style) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);");
	$stmt->execute(array($reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['bgcolour'], $reg['boxwidth'], $reg['boxheight'], $U['nickname'], $reg['timestamps'], $reg['embed'], $reg['style']));
	if($reg['status']==3){
		add_system_message(sprintf(get_setting('msgmemreg'), style_this($reg['nickname'], $reg['style'])));
	}else{
		add_system_message(sprintf(get_setting('msgsureg'), style_this($reg['nickname'], $reg['style'])));
	}
	send_admin(sprintf($I['successreg'], $reg['nickname']));
}

function register_new(){
	global $A, $C, $I, $P, $U, $db;
	$_REQUEST['name']=preg_replace('/\s+/', '', $_REQUEST['name']);
	if(empty($_REQUEST['name'])){
		send_admin();
	}elseif(isSet($P[$_REQUEST['name']])){
		send_admin(sprintf($I['cantreg'], $_REQUEST['name']));
	}elseif(!valid_nick($_REQUEST['name'])){
		send_admin(sprintf($I['invalnick'], get_setting('maxname')));
	}elseif(!valid_pass($_REQUEST['pass'])){
		send_admin(sprintf($I['invalpass'], get_setting('minpass')));
	}
	read_members();
	if(isSet($A[$_REQUEST['name']])){
		send_admin(sprintf($I['alreadyreged'], $_REQUEST['name']));
	}
	$reg=array(
		'nickname'	=>$_REQUEST['name'],
		'passhash'	=>md5(sha1(md5($_REQUEST['name'].$_REQUEST['pass']))),
		'status'	=>3,
		'refresh'	=>get_setting('defaultrefresh'),
		'bgcolour'	=>get_setting('colbg'),
		'regedby'	=>$U['nickname'],
		'timestamps'	=>get_setting('timestamps'),
		'style'		=>'color:#'.get_setting('coltxt').';'
	);
	$stmt=$db->prepare("INSERT INTO $C[prefix]members (nickname, passhash, status, refresh, bgcolour, regedby, timestamps, style) VALUES (?, ?, ?, ?, ?, ?, ?, ?);");
	$stmt->execute(array($reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['bgcolour'], $reg['regedby'], $reg['timestamps'], $reg['style']));
	send_admin(sprintf($I['successreg'], $reg['nickname']));
}

function change_status(){
	global $C, $I, $P, $U, $db;
	if(empty($_REQUEST['name'])){
		send_admin();
	}elseif($U['status']<=$_REQUEST['set'] || !preg_match('/^[023567\-]$/', $_REQUEST['set'])){
		send_admin(sprintf($I['cantchgstat'], $_REQUEST['name']));
	}
	$stmt=$db->prepare("SELECT * FROM $C[prefix]members WHERE nickname=? AND status<?;");
	$stmt->execute(array($_REQUEST['name'], $U['status']));
	if($stmt->fetch(PDO::FETCH_ASSOC)){
		if($_REQUEST['set']==='-'){
			$stmt=$db->prepare("DELETE FROM $C[prefix]members WHERE nickname=?;");
			$stmt->execute(array($_REQUEST['name']));
			$stmt=$db->prepare("UPDATE $C[prefix]sessions SET status=1 WHERE nickname=?;");
			$stmt->execute(array($_REQUEST['name']));
			if(isSet($P[$_REQUEST['name']])){
				$P[$_REQUEST['name']][2]=1;
			}
			send_admin(sprintf($I['succdel'], $_REQUEST['name']));
		}else{
			$stmt=$db->prepare("UPDATE $C[prefix]members SET status=? WHERE nickname=?;");
			$stmt->execute(array($_REQUEST['set'], $_REQUEST['name']));
			$stmt=$db->prepare("UPDATE $C[prefix]sessions SET status=? WHERE nickname=?;");
			$stmt->execute(array($_REQUEST['set'], $_REQUEST['name']));
			if(isSet($P[$_REQUEST['name']])){
				$P[$_REQUEST['name']][2]=$_REQUEST['set'];
			}
			send_admin(sprintf($I['succchg'], $_REQUEST['name']));
		}
	}else{
		send_admin(sprintf($I['cantchgstat'], $_REQUEST['name']));
	}
}

function passreset(){
	global $C, $I, $U, $db;
	if(empty($_REQUEST['name'])){
		send_admin();
	}
	$stmt=$db->prepare("SELECT * FROM $C[prefix]members WHERE nickname=? AND status<?;");
	$stmt->execute(array($_REQUEST['name'], $U['status']));
	if($stmt->fetch(PDO::FETCH_ASSOC)){
		$passhash=md5(sha1(md5($_REQUEST['name'].$_REQUEST['pass'])));
		$stmt=$db->prepare("UPDATE $C[prefix]members SET passhash=? WHERE nickname=?;");
		$stmt->execute(array($passhash, $_REQUEST['name']));
		$stmt=$db->prepare("UPDATE $C[prefix]sessions SET passhash=? WHERE nickname=?;");
		$stmt->execute(array($passhash, $_REQUEST['name']));
		send_admin(sprintf($I['succpassreset'], $_REQUEST['name']));
	}else{
		send_admin(sprintf($I['cantresetpass'], $_REQUEST['name']));
	}
}

function amend_profile(){
	global $F, $U;
	if(isSet($_REQUEST['refresh'])){
		$U['refresh']=$_REQUEST['refresh'];
	}
	if($U['refresh']<5){
		$U['refresh']=5;
	}elseif($U['refresh']>150){
		$U['refresh']=150;
	}
	if(preg_match('/^[a-f0-9]{6}$/i', $_REQUEST['colour'])){
		$U['colour']=$_REQUEST['colour'];
	}else{
		preg_match('/#([0-9a-f]{6})/i', $U['style'], $matches);
		$U['colour']=$matches[1];
	}
	if(preg_match('/^[a-f0-9]{6}$/i', $_REQUEST['bgcolour'])){
		$U['bgcolour']=$_REQUEST['bgcolour'];
	}
	$fonttags='';
	if($U['status']>=3 && isSet($_REQUEST['bold'])){
		$fonttags.='b';
	}
	if($U['status']>=3 && isSet($_REQUEST['italic'])){
		$fonttags.='i';
	}
	if($U['status']>=3 && isSet($F[$_REQUEST['font']])){
		$fontface=$F[$_REQUEST['font']];
	}else{
		$fontface='';
	}
	$U['style']=get_style("#$U[colour] $fontface <$fonttags>");
	if($_REQUEST['boxwidth']>0 && $_REQUEST['boxwidth']<1000){
		$U['boxwidth']=$_REQUEST['boxwidth'];
	}
	if($_REQUEST['boxheight']>0 && $_REQUEST['boxheight']<1000){
		$U['boxheight']=$_REQUEST['boxheight'];
	}
	if(isSet($_REQUEST['notesboxwidth']) && $_REQUEST['notesboxwidth']>0 && $_REQUEST['notesboxwidth']<1000){
		$U['notesboxwidth']=$_REQUEST['notesboxwidth'];
	}
	if(isSet($_REQUEST['notesboxheight']) && $_REQUEST['notesboxheight']>0 && $_REQUEST['notesboxheight']<1000){
		$U['notesboxheight']=$_REQUEST['notesboxheight'];
	}
	if(isSet($_REQUEST['timestamps'])){
		$U['timestamps']=1;
	}else{
		$U['timestamps']=0;
	}
	if(isSet($_REQUEST['embed'])){
		$U['embed']=1;
	}else{
		$U['embed']=0;
	}
	if($U['status']>=5 && isSet($_REQUEST['incognito']) && get_setting('incognito')){
		$U['incognito']=1;
	}else{
		$U['incognito']=0;
	}
}

function save_profile(){
	global $C, $I, $U, $db;
	if(!isSet($_REQUEST['oldpass'])){
		$_REQUEST['oldpass']='';
	}
	if(!isSet($_REQUEST['newpass'])){
		$_REQUEST['newpass']='';
	}
	if(!isSet($_REQUEST['confirmpass'])){
		$_REQUEST['confirmpass']='';
	}
	if($_REQUEST['newpass']!==$_REQUEST['confirmpass']){
		send_profile($I['noconfirm']);
	}elseif(!empty($_REQUEST['newpass']) && valid_pass($_REQUEST['newpass'])){
		$U['oldhash']=md5(sha1(md5($U['nickname'].$_REQUEST['oldpass'])));
		$U['newhash']=md5(sha1(md5($U['nickname'].$_REQUEST['newpass'])));
	}else{
		$U['oldhash']=$U['newhash']=$U['passhash'];
	}
	if($U['passhash']!==$U['oldhash']){
		send_profile($I['wrongpass']);
	}
	$U['passhash']=$U['newhash'];
	amend_profile();
	$stmt=$db->prepare("UPDATE $C[prefix]sessions SET refresh=?, style=?, passhash=?, boxwidth=?, boxheight=?, bgcolour=?, notesboxwidth=?, notesboxheight=?, timestamps=?, embed=?, incognito=? WHERE session=?;");
	$stmt->execute(array($U['refresh'], $U['style'], $U['passhash'], $U['boxwidth'], $U['boxheight'], $U['bgcolour'], $U['notesboxwidth'], $U['notesboxheight'], $U['timestamps'], $U['embed'], $U['incognito'], $U['session']));
	if($U['status']>=2){
		$stmt=$db->prepare("UPDATE $C[prefix]members SET passhash=?, refresh=?, bgcolour=?, boxwidth=?, boxheight=?, notesboxwidth=?, notesboxheight=?, timestamps=?, embed=?, incognito=?, style=? WHERE nickname=?;");
		$stmt->execute(array($U['passhash'], $U['refresh'], $U['bgcolour'], $U['boxwidth'], $U['boxheight'], $U['notesboxwidth'], $U['notesboxheight'], $U['timestamps'], $U['embed'], $U['incognito'], $U['style'], $U['nickname']));
	}
	if(!empty($_REQUEST['unignore'])){
		$stmt=$db->prepare("DELETE FROM $C[prefix]ignored WHERE ign=? AND ignby=?;");
		$stmt->execute(array($_REQUEST['unignore'], $U['nickname']));
	}
	if(!empty($_REQUEST['ignore'])){
		$stmt=$db->prepare("INSERT INTO $C[prefix]ignored (ign, ignby) VALUES (?, ?);");
		$stmt->execute(array($_REQUEST['ignore'], $U['nickname']));
	}
	if($U['status']>1 && !empty($_REQUEST['newnickname'])){
		set_new_nickname();
	}
	if(!empty($_REQUEST['newpass']) && !valid_pass($_REQUEST['newpass'])){
		send_profile(sprintf($I['invalpass'], get_setting('minpass')));
	}
	send_profile($I['succprofile']);
}

function set_new_nickname(){
	global $C, $I, $U, $db;
	if(!isSet($_REQUEST['new_pass']) || !valid_pass($_REQUEST['new_pass'])){
		send_profile(sprintf($I['nopass'], get_setting('minpass')));
	}
	if(!valid_nick($_REQUEST['newnickname'])){
		send_profile(sprintf($I['invalnick'], get_setting('maxname')));
	}
	$U['passhash']=md5(sha1(md5($_REQUEST['newnickname'].$_REQUEST['new_pass'])));
	$stmt=$db->prepare("SELECT id FROM $C[prefix]sessions WHERE nickname=? UNION SELECT id FROM $C[prefix]members WHERE nickname=?;");
	$stmt->execute(array($_REQUEST['newnickname'], $_REQUEST['newnickname']));
	if($stmt->fetch(PDO::FETCH_NUM)){
		send_profile($I['nicknametaken']);
	}else{
		if($U['status']>1){
			$entry=0;
		}else{
			$entry=$U['entry'];
		}
		$stmt=$db->prepare("UPDATE $C[prefix]members SET nickname=?, passhash=? WHERE nickname=?;");
		$stmt->execute(array($_REQUEST['newnickname'], $U['passhash'], $U['nickname']));
		$stmt=$db->prepare("UPDATE $C[prefix]sessions SET nickname=?, passhash=? WHERE nickname=?;");
		$stmt->execute(array($_REQUEST['newnickname'], $U['passhash'], $U['nickname']));
		$stmt=$db->prepare("UPDATE $C[prefix]messages SET poster=? WHERE poster=? AND postdate>?;");
		$stmt->execute(array($_REQUEST['newnickname'], $U['nickname'], $entry));
		$stmt=$db->prepare("UPDATE $C[prefix]messages SET recipient=? WHERE recipient=? AND postdate>?;");
		$stmt->execute(array($_REQUEST['newnickname'], $U['nickname'], $entry));
		$stmt=$db->prepare("UPDATE $C[prefix]ignored SET ignby=? WHERE ignby=?;");
		$stmt->execute(array($_REQUEST['newnickname'], $U['nickname']));
		$stmt=$db->prepare("UPDATE $C[prefix]ignored SET ign=? WHERE ign=?;");
		$stmt->execute(array($_REQUEST['newnickname'], $U['nickname']));
		$U['nickname']=$_REQUEST['newnickname'];
	}
}

function add_user_defaults(){
	global $U;
	$U['refresh']=get_setting('defaultrefresh');
	$U['bgcolour']=get_setting('colbg');
	if(!isSet($_REQUEST['colour']) || !preg_match('/^[a-f0-9]{6}$/i', $_REQUEST['colour'])){
		do{
			$U['colour']=sprintf('%02X', mt_rand(0, 256)).sprintf('%02X', mt_rand(0, 256)).sprintf('%02X', mt_rand(0, 256));
		}while(abs(greyval($U['colour'])-greyval(get_setting('colbg')))<75);
	}else{
		$U['colour']=$_REQUEST['colour'];
	}
	$U['style']=get_style("#$U[colour]");
	$U['boxwidth']=40;
	$U['boxheight']=3;
	$U['notesboxwidth']=80;
	$U['notesboxheight']=30;
	$U['timestamps']=get_setting('timestamps');
	$U['embed']=1;
	$U['incognito']=0;
	$U['status']=1;
}

// message handling

function validate_input(){
	global $C, $P, $U, $db;
	$maxmessage=get_setting('maxmessage');
	$U['message']=substr($_REQUEST['message'], 0, $maxmessage);
	$U['rejected']=substr($_REQUEST['message'], $maxmessage);
	if($U['postid']===$_REQUEST['postid']){// ignore double post=reload from browser or proxy
		$U['message']='';
	}elseif((time()-$U['lastpost'])<=1){// time between posts too short, reject!
		$U['rejected']=$_REQUEST['message'];
		$U['message']='';
	}
	if(preg_match('/&[^;]{0,8}$/', $U['message']) && preg_match('/^([^;]{0,8};)/', $U['rejected'], $match)){
		$U['message'].=$match[0];
		$U['rejected']=preg_replace("/^$match[0]", '', $U['rejected']);
	}
	if(!empty($U['rejected'])){
		$U['rejected']=trim($U['rejected']);
		$U['rejected']=htmlspecialchars($U['rejected']);
	}
	$U['message']=htmlspecialchars($U['message']);
	$U['message']=preg_replace("/(\r?\n|\r\n?)/", '<br>', $U['message']);
	if(isSet($_REQUEST['multi'])){
		$U['message']=preg_replace('/\s*<br>/', '<br>', $U['message']);
		$U['message']=preg_replace('/<br>(<br>)+/', '<br><br>', $U['message']);
		$U['message']=preg_replace('/<br><br>\s*$/', '<br>', $U['message']);
		$U['message']=preg_replace('/^<br>\s*$/', '', $U['message']);
	}else{
		$U['message']=str_replace('<br>', ' ', $U['message']);
	}
	$U['message']=trim($U['message']);
	$U['message']=preg_replace('/\s+/', ' ', $U['message']);
	$U['delstatus']=$U['status'];
	$U['recipient']='';
	if($_REQUEST['sendto']==='*'){
		$U['poststatus']='1';
		$U['displaysend']=sprintf(get_setting('msgsendall'), style_this($U['nickname'], $U['style']));
	}elseif($_REQUEST['sendto']==='?' && $U['status']>=3){
		$U['poststatus']='3';
		$U['displaysend']=sprintf(get_setting('msgsendmem'), style_this($U['nickname'], $U['style']));
	}elseif($_REQUEST['sendto']==='#' && $U['status']>=5){
		$U['poststatus']='5';
		$U['displaysend']=sprintf(get_setting('msgsendmod'), style_this($U['nickname'], $U['style']));
	}elseif($_REQUEST['sendto']==='&' && $U['status']>=6){
		$U['poststatus']='6';
		$U['displaysend']=sprintf(get_setting('msgsendadm'), style_this($U['nickname'], $U['style']));
	}else{// known nick in room?
		$stmt=$db->prepare("SELECT * FROM $C[prefix]ignored WHERE (ignby=? AND ign=?) OR (ignby=? AND ign=?);");
		$stmt->execute(array($U['nickname'], $_REQUEST['sendto'], $_REQUEST['sendto'], $U['nickname']));
		if(!$stmt->fetch(PDO::FETCH_NUM)){
			foreach($P as $chatter){
				if($_REQUEST['sendto']===$chatter[0]){
					$U['recipient']=$chatter[0];
					$U['displayrecp']=style_this($chatter[0], $chatter[1]);
					break;
				}
			}
		}
		if(!empty($U['recipient'])){
			$U['poststatus']='9';
			$U['delstatus']='9';
			$U['displaysend']=sprintf(get_setting('msgsendprv'), style_this($U['nickname'], $U['style']), $U['displayrecp']);
		}else{// nick left already or ignores us
			$U['message']='';
			$U['rejected']='';
		}
	}
	if(isSet($U['poststatus'])){
		apply_filter();
		create_hotlinks();
		apply_linkfilter();
		if(add_message()){
			$U['lastpost']=time();
			$stmt=$db->prepare("UPDATE $C[prefix]sessions SET lastpost=?, postid=? WHERE session=?;");
			$stmt->execute(array($U['lastpost'], $_REQUEST['postid'], $U['session']));
		}

	}
}

function apply_filter(){
	global $C, $I, $U, $db, $memcached;
	if($U['poststatus']!==9 && preg_match('~^/me~i', $U['message'])){
		$U['displaysend']=substr($U['displaysend'], 0, -3);
		$U['message']=preg_replace("~^/me~i", '', $U['message']);
	}
	$U['message']=preg_replace_callback('/\@([a-z0-9]{1,})/i', function ($matched){
		global $A, $P;
		if(isSet($P[$matched[1]])){
			return style_this($matched[0], $P[$matched[1]][1]);
		}
		$nick=strtolower($matched[1]);
		foreach($P as $user){
			if(strtolower($user[0])===$nick){
				return style_this($matched[0], $user[1]);
			}
		}
		read_members();
		if(isSet($A[$matched[1]])){
			return style_this($matched[0], $A[$matched[1]][2]);
		}
		foreach($A as $user){
			if(strtolower($user[0])===$nick){
				return style_this($matched[0], $user[2]);
			}
		}
		return "$matched[0]";
	}, $U['message']);
	if($C['memcached']){
		$filters=$memcached->get("$C[dbname]-$C[prefix]filter");
	}
	if(!$C['memcached'] || $memcached->getResultCode()!==Memcached::RES_SUCCESS){
		$filters=array();
		$result=$db->query("SELECT id, filtermatch, filterreplace, allowinpm, regex, kick FROM $C[prefix]filter;");
		while($filter=$result->fetch(PDO::FETCH_ASSOC)){
			$filters[]=array('id'=>$filter['id'], 'match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'allowinpm'=>$filter['allowinpm'], 'regex'=>$filter['regex'], 'kick'=>$filter['kick']);
		}
		if($C['memcached']) $memcached->set("$C[dbname]-$C[prefix]filter", $filters);
	}
	foreach($filters as $filter){
		if($U['poststatus']!==9){
			$U['message']=preg_replace("/$filter[match]/i", $filter['replace'], $U['message'], -1, $count);
		}elseif(!$filter['allowinpm']){
			$U['message']=preg_replace("/$filter[match]/i", $filter['replace'], $U['message'], -1, $count);
		}
		if(isSet($count) && $count>0 && $filter['kick']){
			kick_chatter(array($U['nickname']), '', false);
			send_error("$I[kicked]");
		}
	}
}

function apply_linkfilter(){
	global $C, $U, $db, $memcached;
	if($C['memcached']){
		$filters=$memcached->get("$C[dbname]-$C[prefix]linkfilter");
	}
	if(!$C['memcached'] || $memcached->getResultCode()!==Memcached::RES_SUCCESS){
		$filters=array();
		$result=$db->query("SELECT id, filtermatch, filterreplace, regex FROM $C[prefix]linkfilter;");
		while($filter=$result->fetch(PDO::FETCH_ASSOC)){
			$filters[]=array('id'=>$filter['id'], 'match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'regex'=>$filter['regex']);
		}
		if($C['memcached']){
			$memcached->set("$C[dbname]-$C[prefix]linkfilter", $filters);
		}
	}
	foreach($filters as $filter){
		$U['message']=preg_replace_callback("/<a href=\"([^\"]+)\" target=\"_blank\">([^<]+)<\/a>/i",
			function ($matched) use(&$filter){
				return "<a href=\"$matched[1]\" target=\"_blank\">".preg_replace("/$filter[match]/i", $filter['replace'], $matched[2]).'</a>';
			}
		, $U['message']);
	}
	$redirect=get_setting('redirect');
	if(get_setting('imgembed')){
		$U['message']=preg_replace_callback('/\[img\]\s?<a href="([^"]+)" target="_blank">([^<]+)<\/a>/i',
			function ($matched){
				return str_ireplace('[/img]', '', "<br><a href=\"$matched[1]\" target=\"_blank\"><img src=\"$matched[1]\"></a><br>");
			}
		, $U['message']);
	}
	if(empty($redirect)){
		$redirect="$_SERVER[SCRIPT_NAME]?action=redirect&url=";
	}
	if(get_setting('forceredirect')){
		$U['message']=preg_replace_callback('/<a href="([^"]+)" target="_blank">([^<]+)<\/a>/',
			function ($matched) use($redirect){
				return "<a href=\"$redirect".urlencode($matched[1])."\" target=\"_blank\">$matched[2]</a>";
			}
		, $U['message']);
	}elseif(preg_match_all('/<a href="([^"]+)" target="_blank">([^<]+)<\/a>/', $U['message'], $matches)){
		foreach($matches[1] as $match){
			if(!preg_match('~^http(s)?://~', $match)){
				$U['message']=preg_replace_callback('/<a href="('.str_replace('/', '\/', $match).')\" target=\"_blank\">([^<]+)<\/a>/',
					function ($matched) use($redirect){
						return "<a href=\"$redirect".urlencode($matched[1])."\" target=\"_blank\">$matched[2]</a>";
					}
				, $U['message']);
			}
		}
	}
}

function create_hotlinks(){
	global $U;
	//Make hotlinks for URLs, redirect through dereferrer script to prevent session leakage
	// 1. all explicit schemes with whatever xxx://yyyyyyy
	$U['message']=preg_replace('~(\w*://[^\s<>]+)~i', "<<$1>>", $U['message']);
	// 2. valid URLs without scheme:
	$U['message']=preg_replace('~((?:[^\s<>]*:[^\s<>]*@)?[a-z0-9\-]+(?:\.[a-z0-9\-]+)+(?::\d*)?/[^\s<>]*)(?![^<>]*>)~i', "<<$1>>", $U['message']); // server/path given
	$U['message']=preg_replace('~((?:[^\s<>]*:[^\s<>]*@)?[a-z0-9\-]+(?:\.[a-z0-9\-]+)+:\d+)(?![^<>]*>)~i', "<<$1>>", $U['message']); // server:port given
	$U['message']=preg_replace('~([^\s<>]*:[^\s<>]*@[a-z0-9\-]+(?:\.[a-z0-9\-]+)+(?::\d+)?)(?![^<>]*>)~i', "<<$1>>", $U['message']); // au:th@server given
	// 3. likely servers without any hints but not filenames like *.rar zip exe etc.
	$U['message']=preg_replace('~((?:[a-z0-9\-]+\.)*[a-z2-7]{16}\.onion)(?![^<>]*>)~i', "<<$1>>", $U['message']);// *.onion
	$U['message']=preg_replace('~([a-z0-9\-]+(?:\.[a-z0-9\-]+)+(?:\.(?!rar|zip|exe|gz|7z|bat|doc)[a-z]{2,}))(?=[^a-z0-9\-\.]|$)(?![^<>]*>)~i', "<<$1>>", $U['message']);// xxx.yyy.zzz
	// Convert every <<....>> into proper links:
	$U['message']=preg_replace_callback('/<<([^<>]+)>>/',
		function ($matches){
			if(strpos($matches[1], '://')===false){
				return "<a href=\"http://$matches[1]\" target=\"_blank\">$matches[1]</a>";
			}else{
				return "<a href=\"$matches[1]\" target=\"_blank\">$matches[1]</a>";
			}
		}
	, $U['message']);
}

function add_message(){
	global $U;
	if(empty($U['message'])){
		return false;
	}
	$newmessage=array(
		'postdate'	=>time(),
		'poststatus'	=>$U['poststatus'],
		'poster'	=>$U['nickname'],
		'recipient'	=>$U['recipient'],
		'text'		=>"<span class=\"usermsg\">$U[displaysend]".style_this($U['message'], $U['style']).'</span>',
		'delstatus'	=>$U['delstatus']
	);
	write_message($newmessage);
	return true;
}

function add_system_message($mes){
	if(empty($mes)){
		return;
	}
	$sysmessage=array(
		'postdate'	=>time(),
		'poststatus'	=>1,
		'poster'	=>'',
		'recipient'	=>'',
		'text'		=>"<span class=\"sysmsg\">$mes</span>",
		'delstatus'	=>9
	);
	write_message($sysmessage);
}

function write_message($message){
	global $C, $db;
	if($C['msgencrypted']){
		$message['text']=openssl_encrypt($message['text'], 'aes-256-cbc', $C['encryptkey'], 0, '1234567890123456');
	}
	$stmt=$db->prepare("INSERT INTO $C[prefix]messages (postdate, poststatus, poster, recipient, text, delstatus) VALUES (?, ?, ?, ?, ?, ?);");
	$stmt->execute(array($message['postdate'], $message['poststatus'], $message['poster'], $message['recipient'], $message['text'], $message['delstatus']));
	$limit=$C['keeplimit']*get_setting('messagelimit');
	$stmt=$db->query("SELECT id FROM $C[prefix]messages ORDER BY id DESC LIMIT 1 OFFSET $limit");
	if($id=$stmt->fetch(PDO::FETCH_NUM)){
		$stmt=$db->prepare("DELETE FROM $C[prefix]messages WHERE id<=?;");
		$stmt->execute(array($id[0]));
	}
	if($C['sendmail'] && $message['poststatus']<9){
		$subject='New Chat message';
		$headers="From: $C[mailsender]\r\nX-Mailer: PHP/".phpversion()."\r\nContent-Type: text/html; charset=UTF-8\r\n";
		$body='<html><body style="background-color:#'.get_setting('colbg').';color:#'.get_setting('coltxt').";\">$message[text]</body></html>";
		mail($C['mailreceiver'], $subject, $body, $headers);
	}
}

function clean_room(){
	global $C, $db;
	$db->query("DELETE FROM $C[prefix]messages;");
	$msg=get_setting('msgclean');
	add_system_message(sprintf($msg, get_setting('chatname')));
}

function clean_selected(){
	global $C, $db;
	if(isSet($_REQUEST['mid'])){
		$stmt=$db->prepare("DELETE FROM $C[prefix]messages WHERE id=?;");
		foreach($_REQUEST['mid'] as $mid){
			$stmt->execute(array($mid));
		}
	}
}

function del_all_messages($nick, $status, $entry){
	global $C, $U, $db;
	if($nick===$U['nickname']) $status=10;
	if($U['status']>1){
		$entry=0;
	}
	$stmt=$db->prepare("DELETE FROM $C[prefix]messages WHERE poster=? AND delstatus<? AND postdate>?;");
	$stmt->execute(array($nick, $status, $entry));
}

function del_last_message(){
	global $C, $U, $db;
	if($U['status']>1){
		$entry=0;
	}else{
		$entry=$U['entry'];
	}
	$stmt=$db->prepare("SELECT id FROM $C[prefix]messages WHERE poster=? AND postdate>? ORDER BY id DESC LIMIT 1;");
	$stmt->execute(array($U['nickname'], $entry));
	if($id=$stmt->fetch(PDO::FETCH_NUM)){
		$stmt=$db->prepare("DELETE FROM $C[prefix]messages WHERE id=?;");
		$stmt->execute(array($id[0]));
	}
}

function print_messages($delstatus=''){
	global $C, $U, $db;
	$dateformat=get_setting('dateformat');
	$messagelimit=(int) get_setting('messagelimit');
	if(!isSet($_COOKIE[$C['cookiename']]) && get_setting('forceredirect')==0){
		$injectRedirect=true;
		$redirect=get_setting('redirect');
		if(empty($redirect)){
			$redirect="$_SERVER[SCRIPT_NAME]?action=redirect&url=";
		}
	}else{
		$injectRedirect=false;
	}
	if(get_setting('imgembed') && (!$U['embed'] || !isSet($_COOKIE[$C['cookiename']]))){
		$removeEmbed=true;
	}else{
		$removeEmbed=false;
	}
	if($U['timestamps'] && !empty($dateformat)){
		$timestamps=true;
	}else{
		$timestamps=false;
	}
	$expire=time()-60*get_setting('messageexpire');
	$db->exec("DELETE FROM $C[prefix]messages WHERE id IN (SELECT * FROM (SELECT id FROM $C[prefix]messages WHERE postdate<$expire) AS t);");
	if(!empty($delstatus)){
		$stmt=$db->prepare("SELECT postdate, id, text FROM $C[prefix]messages WHERE ".
		"id IN (SELECT * FROM (SELECT id FROM $C[prefix]messages WHERE poststatus=1 ORDER BY id DESC LIMIT $messagelimit) AS t) ".
		"OR (poststatus>1 AND (poststatus<? OR poster=? OR recipient=?) ) ORDER BY id DESC;");
		$stmt->execute(array($U['status'], $U['nickname'], $U['nickname']));
		while($message=$stmt->fetch(PDO::FETCH_ASSOC)){
			if($C['msgencrypted']){
				$message['text']=openssl_decrypt($message['text'], 'aes-256-cbc', $C['encryptkey'], 0, '1234567890123456');
			}
			if($injectRedirect){
				$message['text']=preg_replace_callback('/<a href="([^"]+)" target="_blank">([^<]+)<\/a>/',
					function ($matched) use ($redirect){
						return "<a href=\"$redirect".urlencode($matched[1])."\" target=\"_blank\">$matched[2]</a>";
					}
				, $message['text']);
			}
			if($removeEmbed){
				$message['text']=preg_replace_callback('/<img src="([^"]+)"><\/a>/',
					function ($matched){
						return "$matched[1]</a>";
					}
				, $message['text']);
			}
			echo "<div class=\"msg\"><input type=\"checkbox\" name=\"mid[]\" id=\"$message[id]\" value=\"$message[id]\"><label for=\"$message[id]\">";
			if($timestamps){
				echo ' <small>'.date($dateformat, $message['postdate']).' - </small>';
			}
			echo " $message[text]</label></div>";
		}
	}else{
		if(!isSet($_REQUEST['id'])){
			$_REQUEST['id']=0;
		}
		$stmt=$db->prepare("SELECT id, postdate, text FROM $C[prefix]messages WHERE (".
		"id IN (SELECT * FROM (SELECT id FROM $C[prefix]messages WHERE poststatus=1 ORDER BY id DESC LIMIT $messagelimit) AS t) ".
		"OR (poststatus>1 AND poststatus<=?) ".
		"OR (poststatus=9 AND ( (poster=? AND recipient NOT IN (SELECT ign FROM $C[prefix]ignored WHERE ignby=?) ) OR recipient=?) )".
		") AND poster NOT IN (SELECT ign FROM $C[prefix]ignored WHERE ignby=?) AND id>? ORDER BY id DESC;");
		$stmt->execute(array($U['status'], $U['nickname'], $U['nickname'], $U['nickname'], $U['nickname'], $_REQUEST['id']));
		while($message=$stmt->fetch(PDO::FETCH_ASSOC)){
			if($C['msgencrypted']){
				$message['text']=openssl_decrypt($message['text'], 'aes-256-cbc', $C['encryptkey'], 0, '1234567890123456');
			}
			if($injectRedirect){
				$message['text']=preg_replace_callback('/<a href="([^"]+)" target="_blank">([^<]+)<\/a>/',
					function ($matched) use($redirect) {
						return "<a href=\"$redirect".urlencode($matched[1])."\" target=\"_blank\">$matched[2]</a>";
					}
				, $message['text']);
			}
			if($removeEmbed){
				$message['text']=preg_replace_callback('/<img src="([^"]+)"><\/a>/',
					function ($matched){
						return "$matched[1]</a>";
					}
				, $message['text']);
			}
			echo '<div class="msg">';
			if($timestamps){
				echo '<small>'.date($dateformat, $message['postdate']).' - </small>';
			}
			echo "$message[text]</div>";
			if($_REQUEST['id']<$message['id']){
				$_REQUEST['id']=$message['id'];
			}
		}
	}
}

// this and that

function get_ignored(){
	global $C, $db;
	$ignored=array();
	$result=$db->query("SELECT ign, ignby FROM $C[prefix]ignored;");
	while($tmp=$result->fetch(PDO::FETCH_ASSOC)){
		$ignored[]=array('ignored'=>$tmp['ign'], 'by'=>$tmp['ignby']);
	}
	return $ignored;
}

function valid_admin(){
	global $U;
	if(isSet($_REQUEST['session'])){
		check_session();
	}elseif(isSet($_REQUEST['nick']) && isSet($_REQUEST['pass'])){
		create_session(true);
	}
	if(isSet($U['status'])){
		if($U['status']>=7){
			return true;
		}
		send_access_denied();
	}
	return false;
}

function valid_nick($nick){
	return preg_match('/^[a-z0-9]{1,'.get_setting('maxname').'}$/i', $nick);
}

function valid_pass($pass){
	return preg_match('/^.{'.get_setting('minpass').',}$/', $pass);
}

function get_timeout($lastpost, $expire){
	$s=($lastpost+60*$expire)-time();
	$m=floor($s/60);
	$s-=$m*60;
	$h=floor($m/60);
	$m-=$h*60;
	$s=substr("0$s", -2, 2);
	if($h>0){
		$m=substr("0$m", -2, 2);
		echo "$h:$m:$s";
	}
	echo "$m:$s";
}

function print_colours(){
	global $I;
	// Prints a short list with selected named HTML colours and filters out illegible text colours for the given background.
	// It's a simple comparison of weighted grey values. This is not very accurate but gets the job done well enough.
	$colours=array('Beige'=>'F5F5DC', 'Black'=>'000000', 'Blue'=>'0000FF', 'BlueViolet'=>'8A2BE2', 'Brown'=>'A52A2A', 'Cyan'=>'00FFFF', 'DarkBlue'=>'00008B', 'DarkGreen'=>'006400', 'DarkRed'=>'8B0000', 'DarkViolet'=>'9400D3', 'DeepSkyBlue'=>'00BFFF', 'Gold'=>'FFD700', 'Grey'=>'808080', 'Green'=>'008000', 'HotPink'=>'FF69B4', 'Indigo'=>'4B0082', 'LightBlue'=>'ADD8E6', 'LightGreen'=>'90EE90', 'LimeGreen'=>'32CD32', 'Magenta'=>'FF00FF', 'Olive'=>'808000', 'Orange'=>'FFA500', 'OrangeRed'=>'FF4500', 'Purple'=>'800080', 'Red'=>'FF0000', 'RoyalBlue'=>'4169E1', 'SeaGreen'=>'2E8B57', 'Sienna'=>'A0522D', 'Silver'=>'C0C0C0', 'Tan'=>'D2B48C', 'Teal'=>'008080', 'Violet'=>'EE82EE', 'White'=>'FFFFFF', 'Yellow'=>'FFFF00', 'YellowGreen'=>'9ACD32');
	$greybg=greyval(get_setting('colbg'));
	foreach($colours as $name=>$colour){
		if(abs($greybg-greyval($colour))>75){
			echo "<option value=\"$colour\" style=\"color:#$colour;\">$I[$name]</option>";
		}
	}
}

function greyval($colour){
	return hexdec(substr($colour, 0, 2))*.3+hexdec(substr($colour, 2, 2))*.59+hexdec(substr($colour, 4, 2))*.11;
}

function get_style($styleinfo){
	$fbold=preg_match('/(<i?bi?>|:bold)/', $styleinfo);
	$fitalic=preg_match('/(<b?ib?>|:italic)/', $styleinfo);
	$fsmall=strpos($styleinfo, ':smaller');
	preg_match('/(#[a-f0-9]{6})/i', $styleinfo, $match);
	if(isSet($match[0])){
		$fcolour=$match[0];
	}
	preg_match('/font-family:([^;]+);/', $styleinfo, $match);
	if(isSet($match[1])){
		$sface=$match[1];
	}
	$fstyle='';
	if(isSet($fcolour)){
		$fstyle.="color:$fcolour;";
	}
	if(isSet($sface)){
		$fstyle.="font-family:$sface;";
	}
	if($fsmall){
		$fstyle.='font-size:smaller;';
	}
	if($fitalic){
		$fstyle.='font-style:italic;';
	}
	if($fbold){
		$fstyle.='font-weight:bold;';
	}
	return $fstyle;
}

function style_this($text, $styleinfo){
	return "<span style=\"$styleinfo\">$text</span>";
}

function check_init(){
	global $C, $db, $memcached;
	if(!$C['memcached'] || !$found=$memcached->get("$C[dbname]-$C[prefix]num-tables")){
		if($C['dbdriver']===0){
			$result=$db->query("SHOW TABLES LIKE '$C[prefix]settings';");
			$found=($result->fetch(PDO::FETCH_ASSOC)!==false);
		}else{
			$found=$db->query("SELECT * FROM $C[prefix]settings LIMIT 1;");
		}
		if($C['memcached']){
			$memcached->set("$C[dbname]-$C[prefix]num-tables", $found);
		}
	}
	return $found;
}

function destroy_chat(){
	global $C, $H, $I,$db;
	setcookie($C['cookiename'], false);
	print_start('destory');
	$db->exec("DROP TABLE $C[prefix]captcha;");
	$db->exec("DROP TABLE $C[prefix]filter;");
	$db->exec("DROP TABLE $C[prefix]ignored;");
	$db->exec("DROP TABLE $C[prefix]linkfilter;");
	$db->exec("DROP TABLE $C[prefix]members;");
	$db->exec("DROP TABLE $C[prefix]messages;");
	$db->exec("DROP TABLE $C[prefix]notes;");
	$db->exec("DROP TABLE $C[prefix]sessions;");
	$db->exec("DROP TABLE $C[prefix]settings;");
	if($C['memcached']){
		$memcached->delete("$C[dbname]-$C[prefix]num-tables");
		$memcached->delete("$C[dbname]-$C[prefix]filter");
		$memcached->delete("$C[dbname]-$C[prefix]linkfilter");
		foreach($C['settings'] as $setting){
			$memcached->delete("$C[dbname]-$C[prefix]settings-$setting");
		}
		$memcached->delete("$C[dbname]-$C[prefix]settings-dbversion");
		$memcached->delete("$C[dbname]-$C[prefix]settings-msgencrypted");
	}
	echo "<div style=\"text-align:center;\"><h2>$I[destroyed]</h2><br><br><br>";
	echo "<$H[form]>".hidden('lang', $C['lang']).hidden('action', 'setup').submit($I['init'])."</form>$H[credit]</div>";
	print_end();
}

function init_chat(){
	global $C, $H, $I, $db, $memcached;
	$suwrite='';
	if(check_init()){
		$suwrite=$I['initdbexist'];
		$result=$db->query("SELECT * FROM $C[prefix]members WHERE status=8;");
		if($result->fetch(PDO::FETCH_ASSOC)){
			$suwrite=$I['initsuexist'];
		}
	}elseif(!preg_match('/^[a-z0-9]{1,20}$/i', $_REQUEST['sunick'])){
		$suwrite=sprintf($I['invalnick'], 20);
	}elseif(!preg_match('/^.{5,}$/', $_REQUEST['supass'])){
		$suwrite=sprintf($I['invalpass'], 5);
	}elseif($_REQUEST['supass']!==$_REQUEST['supassc']){
		$suwrite=$I['noconfirm'];
	}else{
		if($C['dbdriver']===0){//MySQL
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]captcha (id int(10) unsigned NOT NULL AUTO_INCREMENT, time int(10) unsigned NOT NULL, code char(5) NOT NULL, PRIMARY KEY (id) USING BTREE) ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]filter (id int(10) unsigned NOT NULL AUTO_INCREMENT, filtermatch varchar(255) NOT NULL, filterreplace varchar(20000) NOT NULL, allowinpm tinyint(1) unsigned NOT NULL, regex tinyint(1) unsigned NOT NULL, kick tinyint(1) unsigned NOT NULL, PRIMARY KEY (id) USING BTREE) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]ignored (id int(10) unsigned NOT NULL AUTO_INCREMENT, ign varchar(50) NOT NULL, ignby varchar(50) NOT NULL, PRIMARY KEY (id) USING BTREE, INDEX(ign) USING BTREE, INDEX(ignby) USING BTREE) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]linkfilter (id int(10) unsigned NOT NULL AUTO_INCREMENT, filtermatch varchar(255) NOT NULL, filterreplace varchar(255) NOT NULL, regex tinyint(1) unsigned NOT NULL, PRIMARY KEY (id) USING BTREE) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]members (id int(10) unsigned NOT NULL AUTO_INCREMENT, nickname varchar(50) NOT NULL, passhash char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, status tinyint(3) unsigned NOT NULL, refresh tinyint(3) unsigned NOT NULL, bgcolour char(6) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, boxwidth tinyint(3) unsigned NOT NULL DEFAULT 40, boxheight tinyint(3) unsigned NOT NULL DEFAULT 3, notesboxheight tinyint(3) unsigned NOT NULL DEFAULT 30, notesboxwidth tinyint(3) unsigned NOT NULL DEFAULT 80, regedby varchar(50) NOT NULL, lastlogin int(10) unsigned NOT NULL, timestamps tinyint(1) unsigned NOT NULL, embed tinyint(1) unsigned NOT NULL DEFAULT 1, incognito tinyint(1) unsigned NOT NULL DEFAULT 0, style varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, PRIMARY KEY (id) USING BTREE, UNIQUE(nickname) USING BTREE) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]messages (id int(10) unsigned NOT NULL AUTO_INCREMENT, postdate int(10) unsigned NOT NULL, poststatus tinyint(3) unsigned NOT NULL, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text varchar(20000) NOT NULL, delstatus tinyint(3) unsigned NOT NULL, PRIMARY KEY (id) USING BTREE, INDEX(poster) USING BTREE, INDEX(recipient) USING BTREE, INDEX(postdate) USING BTREE, INDEX(poststatus) USING BTREE) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]notes (id int(10) unsigned NOT NULL AUTO_INCREMENT, type char(5) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, lastedited int(10) unsigned NOT NULL, editedby varchar(50) NOT NULL, text varchar(20000) NOT NULL, PRIMARY KEY (id) USING BTREE) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]sessions (id int(10) unsigned NOT NULL AUTO_INCREMENT, session char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, nickname varchar(50) NOT NULL, status tinyint(3) unsigned NOT NULL, refresh tinyint(3) unsigned NOT NULL, style varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, lastpost int(10) unsigned NOT NULL, passhash char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, postid char(6) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '000000', boxwidth tinyint(3) unsigned NOT NULL DEFAULT 40, boxheight tinyint(3) unsigned NOT NULL DEFAULT 3, useragent varchar(255) NOT NULL, kickmessage varchar(255) NOT NULL, bgcolour char(6) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, notesboxheight tinyint(3) unsigned NOT NULL DEFAULT 30, notesboxwidth tinyint(3) unsigned NOT NULL DEFAULT 80, entry int(10) unsigned NOT NULL, timestamps tinyint(1) unsigned NOT NULL, embed tinyint(1) unsigned NOT NULL DEFAULT 1, incognito tinyint(1) unsigned NOT NULL DEFAULT 0, ip varchar(45) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, PRIMARY KEY (id) USING BTREE, UNIQUE(session) USING BTREE, UNIQUE(nickname) USING BTREE, INDEX(status) USING BTREE, INDEX(lastpost) USING BTREE) ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]settings (setting varchar(50) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, value varchar(20000) NOT NULL, PRIMARY KEY (setting) USING BTREE) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
		}elseif($C['dbdriver']===1){//PostgreSQL
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]captcha (id serial PRIMARY KEY, time integer NOT NULL, code char(5) NOT NULL);");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]filter (id serial PRIMARY KEY, filtermatch varchar(255) NOT NULL, filterreplace varchar(20000) NOT NULL, allowinpm smallint NOT NULL, regex smallint NOT NULL, kick smallint NOT NULL);");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]ignored (id serial PRIMARY KEY, ign varchar(50) NOT NULL, ignby varchar(50) NOT NULL);");
			$db->exec("CREATE INDEX ign ON $C[prefix]ignored (ign);");
			$db->exec("CREATE INDEX ignby ON $C[prefix]ignored (ignby);");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]linkfilter (id serial PRIMARY KEY, filtermatch varchar(255) NOT NULL, filterreplace varchar(255) NOT NULL, regex smallint NOT NULL);");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]members (id serial PRIMARY KEY, nickname varchar(50) NOT NULL UNIQUE, passhash char(32) NOT NULL, status smallint NOT NULL, refresh smallint NOT NULL, bgcolour char(6) NOT NULL, boxwidth smallint NOT NULL DEFAULT 40, boxheight smallint NOT NULL DEFAULT 3, notesboxheight smallint NOT NULL DEFAULT 30, notesboxwidth smallint NOT NULL DEFAULT 80, regedby varchar(50) DEFAULT '', lastlogin integer DEFAULT 0, timestamps smallint NOT NULL, embed smallint DEFAULT 1, incognito smallint DEFAULT 0, style varchar(255) NOT NULL);");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]messages (id serial PRIMARY KEY, postdate integer NOT NULL, poststatus smallint NOT NULL, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text varchar(20000) NOT NULL, delstatus smallint NOT NULL);");
			$db->exec("CREATE INDEX poster ON $C[prefix]messages (poster);");
			$db->exec("CREATE INDEX recipient ON $C[prefix]messages (recipient);");
			$db->exec("CREATE INDEX postdate ON $C[prefix]messages (postdate);");
			$db->exec("CREATE INDEX poststatus ON $C[prefix]messages (poststatus);");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]notes (id serial PRIMARY KEY, type char(5) NOT NULL, lastedited integer NOT NULL, editedby varchar(50) NOT NULL, text varchar(20000) NOT NULL);");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]sessions (id serial PRIMARY KEY, session char(32) NOT NULL UNIQUE, nickname varchar(50) NOT NULL UNIQUE, status smallint NOT NULL, refresh smallint NOT NULL, style varchar(255) NOT NULL, lastpost integer NOT NULL, passhash char(32) NOT NULL, postid char(6) NOT NULL DEFAULT '000000', boxwidth smallint NOT NULL DEFAULT 40, boxheight smallint NOT NULL DEFAULT 3, useragent varchar(255) NOT NULL, kickmessage varchar(255) DEFAULT '', bgcolour char(6) NOT NULL, notesboxheight smallint NOT NULL DEFAULT 30, notesboxwidth smallint NOT NULL DEFAULT 80, entry integer NOT NULL, timestamps smallint NOT NULL, embed smallint DEFAULT 1, incognito smallint DEFAULT 0, ip varchar(45) NOT NULL);");
			$db->exec("CREATE INDEX status ON $C[prefix]sessions (status);");
			$db->exec("CREATE INDEX lastpost ON $C[prefix]sessions (lastpost);");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]settings (setting varchar(50) PRIMARY KEY, value varchar(20000) NOT NULL);");
		}else{//sqlite
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]captcha (id INTEGER PRIMARY KEY, time INTEGER NOT NULL, code TEXT NOT NULL);");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]filter (id INTEGER PRIMARY KEY, filtermatch TEXT NOT NULL, filterreplace TEXT NOT NULL, allowinpm INTEGER NOT NULL, regex INTEGER NOT NULL, kick INTEGER NOT NULL);");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]ignored (id INTEGER PRIMARY KEY, ign TEXT NOT NULL, ignby TEXT NOT NULL);");
			$db->exec("CREATE INDEX IF NOT EXISTS ign ON $C[prefix]ignored (ign);");
			$db->exec("CREATE INDEX IF NOT EXISTS ignby ON $C[prefix]ignored (ignby);");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]linkfilter (id INTEGER PRIMARY KEY, filtermatch TEXT NOT NULL, filterreplace TEXT NOT NULL, regex INTEGER NOT NULL);");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]members (id INTEGER PRIMARY KEY, nickname TEXT NOT NULL UNIQUE, passhash TEXT NOT NULL, status INTEGER NOT NULL, refresh INTEGER NOT NULL, bgcolour TEXT NOT NULL, boxwidth INTEGER NOT NULL DEFAULT 40, boxheight INTEGER NOT NULL DEFAULT 3, notesboxheight INTEGER NOT NULL DEFAULT 30, notesboxwidth INTEGER NOT NULL DEFAULT 80, regedby TEXT DEFAULT '', lastlogin INTEGER DEFAULT 0, timestamps INTEGER NOT NULL, embed INTEGER NOT NULL DEFAULT 1, incognito INTEGER NOT NULL DEFAULT 0, style TEXT NOT NULL);");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]messages (id INTEGER PRIMARY KEY, postdate INTEGER NOT NULL, poststatus INTEGER NOT NULL, poster TEXT NOT NULL, recipient TEXT NOT NULL, text TEXT NOT NULL, delstatus INTEGER NOT NULL);");
			$db->exec("CREATE INDEX IF NOT EXISTS poster ON $C[prefix]messages (poster);");
			$db->exec("CREATE INDEX IF NOT EXISTS recipient ON $C[prefix]messages (recipient);");
			$db->exec("CREATE INDEX IF NOT EXISTS postdate ON $C[prefix]messages (postdate);");
			$db->exec("CREATE INDEX IF NOT EXISTS poststatus ON $C[prefix]messages (poststatus);");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]notes (id INTEGER PRIMARY KEY, type TEXT NOT NULL, lastedited INTEGER NOT NULL, editedby TEXT NOT NULL, text TEXT NOT NULL);");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]sessions (id INTEGER PRIMARY KEY, session TEXT NOT NULL UNIQUE, nickname TEXT NOT NULL UNIQUE, status INTEGER NOT NULL, refresh INTEGER NOT NULL, style TEXT NOT NULL, lastpost INTEGER NOT NULL, passhash TEXT NOT NULL, postid TEXT NOT NULL DEFAULT '000000', boxwidth INTEGER NOT NULL DEFAULT 40, boxheight INTEGER NOT NULL DEFAULT 3, useragent TEXT NOT NULL, kickmessage TEXT DEFAULT '', bgcolour TEXT NOT NULL, notesboxheight INTEGER NOT NULL DEFAULT 30, notesboxwidth INTEGER NOT NULL DEFAULT 80, entry INTEGER NOT NULL, timestamps INTEGER NOT NULL, embed INTEGER NOT NULL DEFAULT 1, incognito INTEGER NOT NULL DEFAULT 0, ip TEXT NOT NULL);");
			$db->exec("CREATE INDEX IF NOT EXISTS status ON $C[prefix]sessions (status);");
			$db->exec("CREATE INDEX IF NOT EXISTS lastpost ON $C[prefix]sessions (lastpost);");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]settings (setting TEXT NOT NULL PRIMARY KEY, value TEXT NOT NULL);");
		}
		$settings=array(array('guestaccess', '0'), array('globalpass', ''), array('englobalpass', '0'), array('captcha', '0'), array('dateformat', 'm-d H:i:s'), array('rulestxt', ''), array('msgencrypted', '0'), array('dbversion', $C['dbversion']), array('css', 'a:visited{color:#B33CB4;} a:active{color:#FF0033;} a:link{color:#0000FF;} input,select,textarea{color:#FFFFFF;background-color:#000000;} a img{width:15%} a:hover img{width:35%} .error{color:#FF0033;} .delbutton{background-color:#660000;} .backbutton{background-color:#004400;} #exitbutton{background-color:#AA0000;}'), array('memberexpire', '60'), array('guestexpire', '15'), array('kickpenalty', '10'), array('entrywait', '120'), array('messageexpire', '14400'), array('messagelimit', '150'), array('maxmessage', 2000), array('captchatime', '600'), array('colbg', '000000'), array('coltxt', 'FFFFFF'), array('maxname', '20'), array('minpass', '5'), array('defaultrefresh', '20'), array('dismemcaptcha', '0'), array('suguests', '0'), array('imgembed', '1'), array('timestamps', '1'), array('trackip', '0'), array('captchachars', '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), array('memkick', '1'), array('forceredirect', '0'), array('redirect', ''), array('incognito', '1'), array('enablejs', '0'), array('chatname', 'My Chat'), array('topic', ''), array('msgsendall', $I['sendallmsg']), array('msgsendmem', $I['sendmemmsg']), array('msgsendmod', $I['sendmodmsg']), array('msgsendadm', $I['sendadmmsg']), array('msgsendprv', $I['sendprvmsg']), array('msgenter', $I['entermsg']), array('msgexit', $I['exitmsg']), array('msgmemreg', $I['memregmsg']), array('msgsureg', $I['suregmsg']), array('msgkick', $I['kickmsg']), array('msgmultikick', $I['multikickmsg']), array('msgallkick', $I['allkickmsg']), array('msgclean', $I['cleanmsg']), array('numnotes', '3'));
		$stmt=$db->prepare("INSERT INTO $C[prefix]settings (setting, value) VALUES (?, ?);");
		foreach($settings as $pair){
			$stmt->execute($pair);
		}
		if($C['memcached']){
			$memcached->delete("$C[dbname]-$C[prefix]num-tables");
		}
		$reg=array(
			'nickname'	=>$_REQUEST['sunick'],
			'passhash'	=>md5(sha1(md5($_REQUEST['sunick'].$_REQUEST['supass']))),
			'status'	=>8,
			'refresh'	=>20,
			'bgcolour'	=>'000000',
			'timestamps'	=>1,
			'style'		=>'color:#FFFFFF;'
		);
		$stmt=$db->prepare("INSERT INTO $C[prefix]members (nickname, passhash, status, refresh, bgcolour, timestamps, style) VALUES (?, ?, ?, ?, ?, ?, ?);");
		$stmt->execute(array($reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['bgcolour'], $reg['timestamps'], $reg['style']));
		$suwrite=$I['susuccess'];
	}
	print_start('init');
	echo "<div style=\"text-align:center;\"><h2>$I[init]</h2><br><h3>$I[sulogin]</h3>$suwrite<br><br><br>";
	echo "<$H[form]>$H[commonform]".hidden('action', 'setup').submit($I['initgosetup'])."</form>$H[credit]</div>";
	print_end();
}

function update_db(){
	global $C, $F, $I, $db, $memcached;
	$dbversion=(int) get_setting('dbversion');
	if($dbversion<$C['dbversion'] || get_setting('msgencrypted')!=$C['msgencrypted']){
		if($dbversion<2){
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]ignored (id int(10) unsigned NOT NULL AUTO_INCREMENT, ignored tinytext NOT NULL, `by` tinytext NOT NULL, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
		}
		if($dbversion<3){
			$db->exec("INSERT INTO $C[prefix]settings (setting, value) VALUES ('rulestxt', '');");
		}
		if($dbversion<4){
			$db->exec("ALTER TABLE $C[prefix]members ADD incognito TINYINT(1) UNSIGNED NOT NULL;");
			$db->exec("ALTER TABLE $C[prefix]sessions ADD incognito TINYINT(1) UNSIGNED NOT NULL;");
		}
		if($dbversion<5){
			$db->exec("INSERT INTO $C[prefix]settings (setting, value) VALUES ('globalpass', '');");
		}
		if($dbversion<6){
			$db->exec("INSERT INTO $C[prefix]settings (setting, value) VALUES ('dateformat', 'm-d H:i:s');");
		}
		if($dbversion<7){
			$db->exec("ALTER TABLE $C[prefix]captcha ADD code TINYTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;");
		}
		if($dbversion<8){
			$db->exec("INSERT INTO $C[prefix]settings (setting, value) VALUES ('captcha', '0'), ('englobalpass', '0');");
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
			$db->exec("INSERT INTO $C[prefix]settings (setting,value) VALUES ('msgencrypted', '0');");
			$db->exec("ALTER TABLE $C[prefix]settings MODIFY value text NOT NULL;");
			$db->exec("ALTER TABLE $C[prefix]messages DROP postid;");
		}
		if($dbversion<10){
			$db->exec("INSERT INTO $C[prefix]settings (setting, value) VALUES ('css', 'a:visited{color:#B33CB4;} a:active{color:#FF0033;} a:link{color:#0000FF;} input,select,textarea{color:#FFFFFF;background-color:#000000;} a img{width:15%} a:hover img{width:35%} .error{color:#FF0033;} .delbutton{background-color:#660000;} .backbutton{background-color:#004400;} #exitbutton{background-color:#AA0000;}'), ('memberexpire', '60'), ('guestexpire', '15'), ('kickpenalty', '10'), ('entrywait', '120'), ('messageexpire', '14400'), ('messagelimit', '150'), ('maxmessage', 2000), ('captchatime', '600');");
			$db->exec("ALTER TABLE $C[prefix]sessions ADD ip tinytext NOT NULL;");
		}
		if($dbversion<11){
			$db->exec("ALTER TABLE $C[prefix]captcha CHARACTER SET utf8 COLLATE utf8_bin;");
			$db->exec("ALTER TABLE $C[prefix]filter CHARACTER SET utf8 COLLATE utf8_bin;");
			$db->exec("ALTER TABLE $C[prefix]ignored CHARACTER SET utf8 COLLATE utf8_bin;");
			$db->exec("ALTER TABLE $C[prefix]members CHARACTER SET utf8 COLLATE utf8_bin;");
			$db->exec("ALTER TABLE $C[prefix]messages CHARACTER SET utf8 COLLATE utf8_bin;");
			$db->exec("ALTER TABLE $C[prefix]notes CHARACTER SET utf8 COLLATE utf8_bin;");
			$db->exec("ALTER TABLE $C[prefix]sessions CHARACTER SET utf8 COLLATE utf8_bin;");
			$db->exec("ALTER TABLE $C[prefix]settings CHARACTER SET utf8 COLLATE utf8_bin;");
			$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]linkfilter (id int(10) unsigned NOT NULL, `match` tinytext NOT NULL, `replace` tinytext NOT NULL, regex tinyint(1) unsigned NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_bin;");
			$db->exec("ALTER TABLE $C[prefix]linkfilter ADD PRIMARY KEY (id), MODIFY id int(10) unsigned NOT NULL AUTO_INCREMENT;");
			$db->exec("ALTER TABLE $C[prefix]sessions DROP fontinfo, DROP displayname;");
			$db->exec("ALTER TABLE $C[prefix]members ADD style TEXT NOT NULL;");
			$result=$db->query("SELECT * FROM $C[prefix]members;");
			$stmt=$db->prepare("UPDATE $C[prefix]members SET style=? WHERE id=?;");
			while($temp=$result->fetch(PDO::FETCH_ASSOC)){
				if(isSet($F[$temp['fontface']])){
					$fontface=$F[$temp['fontface']];
				}else{
					$fontface='';
				}
				$style=get_style("#$temp[colour] $fontface <$temp[fonttags]>");
				$stmt->execute(array($style, $temp['id']));
			}
			$db->exec("ALTER TABLE $C[prefix]members DROP colour, DROP fontface, DROP fonttags;");
			$db->exec("INSERT INTO $C[prefix]settings (setting, value) VALUES ('colbg', '000000'), ('coltxt', 'FFFFFF'), ('maxname', '20'), ('minpass', '5'), ('defaultrefresh', '20'), ('dismemcaptcha', '0'), ('suguests', '0'), ('imgembed', '1'), ('timestamps', '1'), ('trackip', '0'), ('captchachars', '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), ('memkick', '1'), ('forceredirect', '0'), ('redirect', ''), ('incognito', '1');");
		}
		if($dbversion<12){
			$db->exec("ALTER TABLE $C[prefix]captcha MODIFY code char(5) NOT NULL, DROP INDEX id, ADD PRIMARY KEY (id) USING BTREE;");
			$db->exec("ALTER TABLE $C[prefix]captcha ENGINE=MEMORY;");
			$db->exec("ALTER TABLE $C[prefix]filter MODIFY id int(10) unsigned NOT NULL AUTO_INCREMENT, MODIFY `match` varchar(255) NOT NULL, MODIFY replace varchar(20000) NOT NULL;");
			$db->exec("ALTER TABLE $C[prefix]ignored MODIFY ignored varchar(50) NOT NULL, MODIFY `by` varchar(50) NOT NULL, ADD INDEX(ignored) USING BTREE, ADD INDEX(`by`) USING BTREE;");
			$db->exec("ALTER TABLE $C[prefix]linkfilter MODIFY match varchar(255) NOT NULL, MODIFY replace varchar(255) NOT NULL;");
			$db->exec("ALTER TABLE $C[prefix]members MODIFY id int(10) unsigned NOT NULL AUTO_INCREMENT, MODIFY nickname varchar(50) NOT NULL, MODIFY passhash char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY bgcolour char(6) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY boxwidth tinyint(3) NOT NULL DEFAULT '40', MODIFY boxheight tinyint(3) NOT NULL DEFAULT '3', MODIFY notesboxheight tinyint(3) NOT NULL DEFAULT '30', MODIFY notesboxwidth tinyint(3) NOT NULL DEFAULT '80', MODIFY regedby varchar(50) NOT NULL, MODIFY embed tinyint(1) NOT NULL DEFAULT '1', MODIFY incognito tinyint(1) NOT NULL DEFAULT '0', MODIFY style varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, ADD UNIQUE(nickname) USING BTREE;");
			$db->exec("ALTER TABLE $C[prefix]messages MODIFY poster varchar(50) NOT NULL, MODIFY recipient varchar(50) NOT NULL, MODIFY text varchar(20000) NOT NULL, ADD INDEX(poster) USING BTREE, ADD INDEX(recipient) USING BTREE, ADD INDEX(postdate) USING BTREE, ADD INDEX(poststatus) USING BTREE;");
			$db->exec("ALTER TABLE $C[prefix]notes MODIFY type char(5) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY editedby varchar(50) NOT NULL, MODIFY text varchar(20000) NOT NULL;");
			$db->exec("ALTER TABLE $C[prefix]sessions MODIFY session char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY nickname varchar(50) NOT NULL, MODIFY style varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY passhash char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY postid char(6) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '000000', MODIFY boxwidth tinyint(3) unsigned NOT NULL DEFAULT '40', MODIFY boxheight tinyint(3) unsigned NOT NULL DEFAULT '3', MODIFY notesboxheight tinyint(3) unsigned NOT NULL DEFAULT '30', MODIFY notesboxwidth tinyint(3) unsigned NOT NULL DEFAULT '80', MODIFY bgcolour char(6) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY useragent varchar(255) NOT NULL, MODIFY kickmessage varchar(255) NOT NULL, MODIFY embed tinyint(1) unsigned NOT NULL DEFAULT '1', MODIFY incognito tinyint(1) unsigned NOT NULL DEFAULT '0', MODIFY ip varchar(45) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, ADD UNIQUE(session) USING BTREE, ADD UNIQUE(nickname) USING BTREE, ADD INDEX(status) USING BTREE, ADD INDEX(lastpost) USING BTREE;");
			$db->exec("ALTER TABLE $C[prefix]sessions ENGINE=MEMORY;");
			$db->exec("ALTER TABLE $C[prefix]settings MODIFY id int(10) unsigned NOT NULL, MODIFY setting varchar(50) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY value varchar(20000) NOT NULL;");
			$db->exec("ALTER TABLE $C[prefix]settings DROP PRIMARY KEY, DROP id, ADD PRIMARY KEY(setting) USING BTREE;");
			$db->exec("INSERT INTO $C[prefix]settings (setting, value) VALUES ('enablejs', '0'), ('chatname', 'My Chat'), ('topic', ''), ('msgsendall', '$I[sendallmsg]'), ('msgsendmem', '$I[sendmemmsg]'), ('msgsendmod', '$I[sendmodmsg]'), ('msgsendadm', '$I[sendadmmsg]'), ('msgsendprv', '$I[sendprvmsg]'), ('numnotes', '3');");
		}
		if($dbversion<13){
			$db->exec("ALTER TABLE $C[prefix]filter CHANGE `match` filtermatch varchar(255) NOT NULL, CHANGE `replace` filterreplace varchar(20000) NOT NULL;");
			$db->exec("ALTER TABLE $C[prefix]ignored CHANGE ignored ign varchar(50) NOT NULL, CHANGE `by` ignby varchar(50) NOT NULL;");
			$db->exec("ALTER TABLE $C[prefix]linkfilter CHANGE `match` filtermatch varchar(255) NOT NULL, CHANGE `replace` filterreplace varchar(255) NOT NULL;");
			$db->exec("ALTER TABLE $C[prefix]sessions MODIFY ip varchar(45) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL;");
		}
		if($dbversion<14){
			if($C['memcached']){
				$memcached->delete("$C[dbname]-$C[prefix]members");
				$memcached->delete("$C[dbname]-$C[prefix]ignored");
			}
			if($C['dbdriver']===0){//MySQL
				$db->exec("CREATE TABLE IF NOT EXISTS $C[prefix]captcha (id int(10) unsigned NOT NULL AUTO_INCREMENT, time int(10) unsigned NOT NULL, code char(5) NOT NULL, PRIMARY KEY (id) USING BTREE) ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
			}
		}
		if(get_setting('msgencrypted')!=$C['msgencrypted']){
			$result=$db->query("SELECT id, text FROM $C[prefix]messages;");
			$stmt=$db->prepare("UPDATE $C[prefix]messages SET text=? WHERE id=?;");
			while($message=$result->fetch(PDO::FETCH_ASSOC)){
				if($C['msgencrypted']){
					$message['text']=openssl_encrypt($message['text'], 'aes-256-cbc', $C['encryptkey'], 0, '1234567890123456');
				}else{
					$message['text']=openssl_decrypt($message['text'], 'aes-256-cbc', $C['encryptkey'], 0, '1234567890123456');
				}
				$stmt->execute(array($message['text'], $message['id']));
			}
			$result=$db->query("SELECT id, text FROM $C[prefix]notes;");
			$stmt=$db->prepare("UPDATE $C[prefix]notes SET text=? WHERE id=?;");
			while($message=$result->fetch(PDO::FETCH_ASSOC)){
				if($C['msgencrypted']){
					$message['text']=openssl_encrypt($message['text'], 'aes-256-cbc', $C['encryptkey'], 0, '1234567890123456');
				}else{
					$message['text']=openssl_decrypt($message['text'], 'aes-256-cbc', $C['encryptkey'], 0, '1234567890123456');
				}
				$stmt->execute(array($message['text'], $message['id']));
			}
			update_setting('msgencrypted', (int)$C['msgencrypted']);
		}
		update_setting('dbversion', $C['dbversion']);
		send_update();
	}
}

function get_setting($setting){
	global $C, $db, $memcached;
	if(!$C['memcached'] || !$value=$memcached->get("$C[dbname]-$C[prefix]settings-$setting")){
		$stmt=$db->prepare("SELECT value FROM $C[prefix]settings WHERE setting=?;");
		$stmt->execute(array($setting));
		$stmt->bindColumn(1, $value);
		$stmt->fetch(PDO::FETCH_BOUND);
		if($C['memcached']){
			$memcached->set("$C[dbname]-$C[prefix]settings-$setting", $value);
		}
	}
	return $value;
}

function update_setting($setting, $value){
	global $C, $db, $memcached;
	$stmt=$db->prepare("UPDATE $C[prefix]settings SET value=? WHERE setting=?;");
	$stmt->execute(array($value, $setting));
	if($C['memcached']){
		$memcached->set("$C[dbname]-$C[prefix]settings-$setting", $value);
	}
}

// configuration, defaults and internals

function check_db(){
	global $C, $I, $db, $memcached;
	$options=array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>$C['persistent']);
	try{
		if($C['dbdriver']===0){
			$db=new PDO("mysql:host=$C[dbhost];dbname=$C[dbname]", $C['dbuser'], $C['dbpass'], $options);
		}elseif($C['dbdriver']===1){
			$db=new PDO("pgsql:host=$C[dbhost];dbname=$C[dbname]", $C['dbuser'], $C['dbpass'], $options);
		}else{
			$db=new PDO("sqlite:$C[sqlitedbfile]", NULL, NULL, $options);
		}
	}catch(PDOException $e){
		if(isSet($_REQUEST['action']) && $_REQUEST['action']==='setup'){
			die($I['nodbsetup']);
		}else{
			die($I['nodb']);
		}
	}
	if($C['memcached']){
		$memcached=new Memcached();
		$memcached->addServer($C['memcachedhost'], $C['memcachedport']);
	}
}

function load_fonts(){
	global $F;
	$F=array(
		'Arial'			=>"font-family:'Arial','Helvetica','sans-serif';",
		'Book Antiqua'		=>"font-family:'Book Antiqua','MS Gothic';",
		'Comic'			=>"font-family:'Comic Sans MS','Papyrus';",
		'Comic small'		=>"font-family:'Comic Sans MS','Papyrus';font-size:smaller;",
		'Courier'		=>"font-family:'Courier New','Courier','monospace';",
		'Cursive'		=>"font-family:'Cursive','Papyrus';",
		'Fantasy'		=>"font-family:'Fantasy','Futura','Papyrus';",
		'Garamond'		=>"font-family:'Garamond','Palatino','serif';",
		'Georgia'		=>"font-family:'Georgia','Times New Roman','Times','serif';",
		'Serif'			=>"font-family:'MS Serif','New York','serif';",
		'System'		=>"font-family:'System','Chicago','sans-serif';",
		'Times New Roman'	=>"font-family:'Times New Roman','Times','serif';",
		'Verdana'		=>"font-family:'Verdana','Geneva','Arial','Helvetica','sans-serif';",
		'Verdana small'		=>"font-family:'Verdana','Geneva','Arial','Helvetica','sans-serif';font-size:smaller;"
	);
}

function load_html(){
	global $C, $H, $I;
	$H=array(// default HTML
		'form'		=>"form action=\"$_SERVER[SCRIPT_NAME]\" method=\"post\"",
		'meta_html'	=>"<meta name=\"robots\" content=\"noindex,nofollow\"><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"><meta http-equiv=\"Pragma\" content=\"no-cache\"><meta http-equiv=\"Cache-Control\" content=\"no-cache\"><meta http-equiv=\"expires\" content=\"0\">",
		'credit'	=>"<small><br><br><a target=\"_blank\" href=\"https://github.com/DanWin/le-chat-php\">LE CHAT-PHP - $C[version]</a></small>",
		'commonform'	=>hidden('lang', $C['lang'])
	);
	if(isSet($_REQUEST['session'])){
		$H['commonform'].=hidden('session', $_REQUEST['session']);
	}
	$H=$H+array(
		'backtologin'	=>"<$H[form] target=\"_parent\">".hidden('lang', $C['lang']).submit($I['backtologin'], 'class="backbutton"').'</form>',
		'backtochat'	=>"<$H[form]>$H[commonform]".hidden('action', 'view').submit($I['backtochat'], 'class="backbutton"').'</form>'
	);
}

function load_lang(){
	global $C, $I, $L;
	$L=array(
		'de'	=>'Deutsch',
		'en'	=>'English',
		'id'	=>'Bahasa Indonesia',
		'ru'	=>'Русский'
	);
	if(isSet($_REQUEST['lang']) && array_key_exists($_REQUEST['lang'], $L)){
		$C['lang']=$_REQUEST['lang'];
		setcookie('language', $C['lang']);
	}elseif(isSet($_COOKIE['language']) && array_key_exists($_COOKIE['language'], $L)){
		$C['lang']=$_COOKIE['language'];
	}
	include('lang_en.php'); //always include English
	if($C['lang']!=='en'){
		include("lang_$C[lang].php"); //replace with translation if available
		foreach($T as $name=>$translation){
			$I[$name]=$translation;
		}
	}
}

function load_config(){
	global $C;
	$C=array(
		'version'	=>'1.15.2', // Script version
		'dbversion'	=>14, // Database version
		'keeplimit'	=>3, // Amount of messages to keep in the database (multiplied with max messages displayed) - increase if you have many private messages
		'msgencrypted'	=>false, // Store messages encrypted in the database to prevent other database users from reading them - true/false - visit the setup page after editing!
		'encryptkey'	=>'MY_KEY', // Encryption key for messages
		'dbhost'	=>'localhost', // Database host
		'dbuser'	=>'www-data', // Database user
		'dbpass'	=>'YOUR_DB_PASS', // Database password
		'dbname'	=>'public_chat', // Database
		'persistent'	=>true, // Use persistent database conection true/false
		'prefix'	=>'', // Prefix - Set this to a unique value for every chat, if you have more than 1 chats on the same database or domain - use only alpha-numeric values (A-Z, a-z, 0-9, or _) other symbols might break the queries
		'memcached'	=>false, // Enable/disable memcached caching true/false - needs php5-memcached and a memcached server.
		'memcachedhost'	=>'localhost', // Memcached server
		'memcachedport'	=>'11211', // Memcached server
		'sendmail'	=>false, // Send mail on new message - only activate on low traffic chat or your inbox will fill up very fast!
		'mailsender'	=>'www-data <www-data@localhost>', // Send mail using this e-Mail address
		'mailreceiver'	=>'Webmaster <webmaster@localhost>', // Send mail to this e-Mail address
		'lang'		=>'en', // Default language
		'dbdriver'	=>0, // Selects the database driver to use - 0=MySQL, 1=PostgreSQL, 2=sqlite
		'sqlitedbfile'	=>'public_chat.sqlite' // Filepath of the sqlite database, if sqlite is used - make sure it is writable for the webserver user
	);
	$C['cookiename']="$C[prefix]chat_session"; // Cookie name storing the session information
}
?>
