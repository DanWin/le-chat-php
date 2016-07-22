<?php
/*
* LE CHAT-PHP - a PHP Chat based on LE CHAT - Main program
*
* Copyright (C) 2015-2016 Daniel Winzen <d@winzen4.de>
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
$A=array();// All registered members [display name, style, status, nickname]
$C=array();// Configuration
$F=array();// Fonts
$H=array();// HTML-stuff
$I=array();// Translations
$L=array();// Languages
$P=array();// All present users [display name, style, status, nickname]
$U=array();// This user data
$countmods=0;// Present moderators
$db;// Database connection
$memcached;// Memcached connection
$language;// user selected language
load_config();
// set session variable to cookie if cookies are enabled
if(!isSet($_REQUEST['session']) && isSet($_COOKIE[COOKIENAME])){
	$_REQUEST['session']=$_COOKIE[COOKIENAME];
}
load_fonts();
load_lang();
load_html();
check_db();
route();

//  main program: decide what to do based on queries
function route(){
	global $U, $countmods;
	if(!isSet($_REQUEST['action'])){
		if(!check_init()){
			send_init();
		}
		send_login();
	}elseif($_REQUEST['action']==='view'){
		check_session();
		send_messages();
	}elseif($_REQUEST['action']==='redirect' && !empty($_GET['url'])){
		send_redirect($_GET['url']);
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
		$arg='';
		if(!isSet($_REQUEST['do'])){
		}elseif($_REQUEST['do']==='save'){
			$arg=save_profile();
		}elseif($_REQUEST['do']==='delete'){
			if(isSet($_REQUEST['confirm'])){
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
		if(isSet($_REQUEST['do']) && $_REQUEST['do']==='admin' && $U['status']>6){
			send_notes('admin');
		}
		if($U['status']<5){
			send_access_denied();
		}
		send_notes('staff');
	}elseif($_REQUEST['action']==='help'){
		check_session();
		send_help();
	}elseif($_REQUEST['action']==='inbox'){
		check_session();
		if(isSet($_REQUEST['do'])){
			clean_inbox_selected();
		}
		send_inbox();
	}elseif($_REQUEST['action']==='admin'){
		check_session();
		send_admin(route_admin());
	}elseif($_REQUEST['action']==='setup'){
		route_setup();
		send_setup();
	}elseif($_REQUEST['action']==='init'){
		init_chat();
	}else{
		send_login();
	}
}

function route_admin(){
	global $U;
	if($U['status']<5){
		send_access_denied();
	}
	if(!isSet($_REQUEST['do'])){
	}elseif($_REQUEST['do']==='clean'){
		if($_REQUEST['what']==='choose'){
			send_choose_messages();
		}elseif($_REQUEST['what']==='selected'){
			clean_selected($U['status'], $U['nickname']);
		}elseif($_REQUEST['what']==='room'){
			clean_room();
		}elseif($_REQUEST['what']==='nick'){
			del_all_messages($_REQUEST['nickname'], $U['status'], 0);
		}
	}elseif($_REQUEST['do']==='kick'){
		if(isSet($_REQUEST['name'])){
			if(isSet($_REQUEST['what']) && $_REQUEST['what']==='purge'){
				kick_chatter($_REQUEST['name'], $_REQUEST['kickmessage'], true);
			}else{
				kick_chatter($_REQUEST['name'], $_REQUEST['kickmessage'], false);
			}
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
		if(isSet($_REQUEST['guestaccess']) && preg_match('/^[0123]$/', $_REQUEST['guestaccess'])){
			update_setting('guestaccess', $_REQUEST['guestaccess']);
		}
	}elseif($_REQUEST['do']==='filter'){
		send_filter(manage_filter());
	}elseif($_REQUEST['do']==='linkfilter'){
		send_linkfilter(manage_linkfilter());
	}elseif($_REQUEST['do']==='topic'){
		if(isSet($_REQUEST['topic'])){
			update_setting('topic', htmlspecialchars($_REQUEST['topic']));
		}
	}elseif($_REQUEST['do']==='passreset'){
		return passreset($_REQUEST['name'], $_REQUEST['pass']);
	}
}

function route_setup(){
	global $C, $U;
	if(!check_init()){
		send_init();
	}
	update_db();
	if(!valid_admin()){
		send_alogin();
	}
	$C['bool_settings']=array('suguests', 'imgembed', 'timestamps', 'trackip', 'memkick', 'forceredirect', 'incognito', 'sendmail', 'modfallback', 'disablepm', 'eninbox');
	$C['colour_settings']=array('colbg', 'coltxt');
	$C['msg_settings']=array('msgenter', 'msgexit', 'msgmemreg', 'msgsureg', 'msgkick', 'msgmultikick', 'msgallkick', 'msgclean', 'msgsendall', 'msgsendmem', 'msgsendmod', 'msgsendadm', 'msgsendprv');
	$C['number_settings']=array('memberexpire', 'guestexpire', 'kickpenalty', 'entrywait', 'captchatime', 'messageexpire', 'messagelimit', 'keeplimit', 'maxmessage', 'maxname', 'minpass', 'defaultrefresh', 'numnotes');
	$C['textarea_settings']=array('rulestxt', 'css', 'disabletext');
	$C['text_settings']=array('dateformat', 'captchachars', 'redirect', 'chatname', 'mailsender', 'mailreceiver');
	$C['settings']=array_merge(array('guestaccess', 'englobalpass', 'globalpass', 'captcha', 'dismemcaptcha', 'topic', 'guestreg', 'defaulttz'), $C['bool_settings'], $C['colour_settings'], $C['msg_settings'], $C['number_settings'], $C['textarea_settings'], $C['text_settings']); // All settings in the database
	if(!isSet($_REQUEST['do'])){
	}elseif($_REQUEST['do']==='save'){
		save_setup();
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
}

//  html output subs
function print_stylesheet(){
	global $U;
	$css=get_setting('css');
	$coltxt=get_setting('coltxt');
	if(!empty($U['bgcolour'])){
		$colbg=$U['bgcolour'];
	}else{
		$colbg=get_setting('colbg');
	}
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

function print_start($class='', $ref=0, $url=''){
	global $H, $I;
	if(!empty($url)){
		$url=str_replace('&amp;', '&', $url);// Don't escape "&" in URLs here, it breaks some (older) browsers and js refresh!
		header("Refresh: $ref; URL=$url");
	}
	echo "<!DOCTYPE html><html><head>$H[meta_html]";
	if(!empty($url)){
		echo "<meta http-equiv=\"Refresh\" content=\"$ref; URL=$url\">";
		$ref+=5;//only use js if browser refresh stopped working
		$ref*=1000;//js uses milliseconds
		echo "<script type=\"text/javascript\">setTimeout(function(){window.location.replace(\"$url\");}, $ref);</script>";
	}
	if($class==='init'){
		echo "<title>$I[init]</title>";
		echo "<style type=\"text/css\">body{background-color:#000000;color:#FFFFFF;text-align:center;} a:visited{color:#B33CB4;} a:active{color:#FF0033;} a:link{color:#0000FF;} input,select,textarea{color:#FFFFFF;background-color:#000000;} a img{width:15%} a:hover img{width:35%} .error{color:#FF0033;} .delbutton{background-color:#660000;} .backbutton{background-color:#004400;} #exitbutton{background-color:#AA0000;} .center-table{margin-left:auto;margin-right:auto;} .left-table{width:100%;text-align:left;} .right{text-align:right;} .left{text-align:left;} .right-table{border-spacing:0px;margin-left:auto;} .padded{padding:5px;} #chatters{max-height:100px;overflow-y:auto;} .center{text-align:center;}</style>";
	}else{
		echo '<title>'.get_setting('chatname').'</title>';
		print_stylesheet();
	}
	echo "</head><body class=\"$class\">";
}

function send_redirect($url){
	global $I;
	$url=htmlspecialchars_decode(rawurldecode($url));
	preg_match('~^(.*)://~', $url, $match);
	$url=preg_replace('~^(.*)://~', '', $url);
	$escaped=htmlspecialchars($url);
	if(isSet($match[1]) && ($match[1]==='http' || $match[1]==='https')){
		print_start('redirect', 0, $match[0].$escaped);
		echo "<p>$I[redirectto] <a href=\"$match[0]$escaped\">$match[0]$escaped</a>.</p>";
	}else{
		print_start('redirect');
		if(!isSet($match[0])){
			$match[0]='';
		}
		echo "<p>$I[nonhttp] <a href=\"$match[0]$escaped\">$match[0]$escaped</a>.</p>";
		echo "<p>$I[httpredir] <a href=\"http://$escaped\">http://$escaped</a>.</p>";
	}
	print_end();
}

function send_access_denied(){
	global $H, $I, $U;
	header('HTTP/1.1 403 Forbidden');
	print_start('access_denied');
	echo "<h1>$I[accessdenied]</h1>".sprintf($I['loggedinas'], style_this($U['nickname'], $U['style']));
	echo "<br><$H[form]>$H[commonform]".hidden('action', 'logout');
	if(!isSet($_REQUEST['session'])){
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
		$stmt->execute(array($randid, $time, $code));
	}
	echo "<tr><td class=\"left\">$I[copy]<br>";
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
	}else{
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
			$chars[]=array('x', 'y');
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
		echo '<img width="150" height="200" src="data:image/gif;base64,';
	}
	ob_start();
	imagegif($im);
	imagedestroy($im);
	echo base64_encode(ob_get_clean()).'">';
	echo '</td><td class="right">'.hidden('challenge', $randid).'<input type="text" name="captcha" size="15" autocomplete="off"></td></tr>';
}

function send_setup(){
	global $C, $H, $I, $U;
	print_start('setup');
	echo "<h2>$I[setup]</h2><$H[form]>$H[commonform]".hidden('action', 'setup').hidden('do', 'save');
	if(!isSet($_REQUEST['session'])){
		echo hidden('session', $U['session']);
	}
	echo '<table class="center-table">';
	thr();
	$ga=(int) get_setting('guestaccess');
	echo "<tr><td><table class=\"left-table\"><tr><th>$I[guestacc]</th><td class=\"right\">";
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
	echo "<tr><td><table class=\"left-table\"><tr><th>$I[globalloginpass]</th><td>";
	echo '<table class="right-table">';
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
	echo "<tr><td><table class=\"left-table\"><tr><th>$I[guestreg]</th><td class=\"right\">";
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
	echo "<tr><td><table class=\"left-table\"><tr><th>$I[sysmessages]</th><td>";
	echo '<table class="right right-table">';
	foreach($C['msg_settings'] as $setting){
		echo "<tr><td>&nbsp;$I[$setting]</td><td>&nbsp;<input type=\"text\" name=\"$setting\" value=\"".get_setting($setting).'"></td></tr>';
	}
	echo '</table></td></tr></table></td></tr>';
	foreach($C['text_settings'] as $setting){
		thr();
		echo '<tr><td><table class="left-table"><tr><th>'.$I[$setting].'</th><td class="right">';
		echo "<input type=\"text\" name=\"$setting\" value=\"".htmlspecialchars(get_setting($setting)).'">';
		echo '</td></tr></table></td></tr>';
	}
	foreach($C['colour_settings'] as $setting){
		thr();
		echo '<tr><td><table class="left-table"><tr><th>'.$I[$setting].'</th><td class="right">';
		echo "<input type=\"text\" name=\"$setting\" size=\"6\" maxlength=\"6\" pattern=\"[a-fA-F0-9]{6}\" value=\"".htmlspecialchars(get_setting($setting)).'">';
		echo '</td></tr></table></td></tr>';
	}
	thr();
	echo "<tr><td><table class=\"left-table\"><tr><th>$I[captcha]</th><td>";
	echo '<table class="right-table">';
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
	echo "<tr><td><table class=\"left-table\"><tr><th>$I[defaulttz]</th><td class=\"right\">";
	echo "<select name=\"defaulttz\" id=\"defaulttz\">";
	$tzs=[-12=>'-12', -11=>'-11', -10=>'-10', -9=>'-9', -8=>'-8', -7=>'-7', -6=>'-6', -5=>'-5', -4=>'-4', -3=>'-3', -2=>'-2', -1=>'-1', 0=>'', 1=>'+1', 2=>'+2', 3=>'+3', 4=>'+4', 5=>'+5', 6=>'+6', 7=>'+7', 8=>'+8', 9=>'+9', 10=>'+10', 11=>'+11', 12=>'+12', 13=>'+13', 14=>'+14'];
	$defaulttz=get_setting('defaulttz');
	foreach($tzs as $tz=>$name){
		$select = $defaulttz==$tz ? ' selected' : '';
		echo "<option value=\"$tz\"$select>UTC $name</option>";
	}
	echo '</select>';
	echo '</td></tr></table></td></tr>';
	foreach($C['textarea_settings'] as $setting){
		thr();
		echo '<tr><td><table class="left-table"><tr><th>'.$I[$setting].'</th><td class="right">';
		echo "<textarea name=\"$setting\" rows=\"4\" cols=\"60\">".htmlspecialchars(get_setting($setting)).'</textarea>';
		echo '</td></tr></table></td></tr>';
	}
	foreach($C['number_settings'] as $setting){
		thr();
		echo '<tr><td><table class="left-table"><tr><th>'.$I[$setting].'</th><td class="right">';
		echo "<input type=\"number\" name=\"$setting\" value=\"".htmlspecialchars(get_setting($setting)).'">';
		echo '</td></tr></table></td></tr>';
	}
	foreach($C['bool_settings'] as $setting){
		thr();
		echo '<tr><td><table class="left-table"><tr><th>'.$I[$setting].'</th><td class="right">';
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
		echo '<table class="center-table"><tr>';
		echo "<td><$H[form]>$H[commonform]".hidden('action', 'setup').hidden('do', 'backup');
		if(!isSet($_REQUEST['session'])){
			echo hidden('session', $U['session']);
		}
		echo submit($I['backuprestore']).'</form></td>';
		echo "<td><$H[form]>$H[commonform]".hidden('action', 'setup').hidden('do', 'destroy');
		if(!isSet($_REQUEST['session'])){
			echo hidden('session', $U['session']);
		}
		echo submit($I['destroy'], 'class="delbutton"').'</form></td></tr></table><br>';
	}
	echo "<$H[form] target=\"_parent\">$H[commonform]".hidden('action', 'logout');
	if(!isSet($_REQUEST['session'])){
		echo hidden('session', $U['session']);
	}
	echo submit($I['logout'], 'id="exitbutton"')."</form>$H[credit]";
	print_end();
}

function restore_backup(){
	global $C, $db;
	if(!extension_loaded('json')){
		return;
	}
	$code=json_decode($_REQUEST['restore'], true);
	if(isSet($_REQUEST['settings'])){
		foreach($C['settings'] as $setting){
			if(isSet($code['settings'][$setting])){
				update_setting($setting, $code['settings'][$setting]);
			}
		}
	}
	if(isSet($_REQUEST['filter']) && (isSet($code['filters']) || isSet($code['linkfilters']))){
		$db->exec('DELETE FROM ' . PREFIX . 'filter;');
		$db->exec('DELETE FROM ' . PREFIX . 'linkfilter;');
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'filter (filtermatch, filterreplace, allowinpm, regex, kick) VALUES (?, ?, ?, ?, ?);');
		foreach($code['filters'] as $filter){
			$stmt->execute(array($filter['match'], $filter['replace'], $filter['allowinpm'], $filter['regex'], $filter['kick']));
		}
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'linkfilter (filtermatch, filterreplace, regex) VALUES (?, ?, ?);');
		foreach($code['linkfilters'] as $filter){
			$stmt->execute(array($filter['match'], $filter['replace'], $filter['regex']));
		}
		if(MEMCACHED){
			$memcached->delete(DBNAME . '-' . PREFIX . 'filter');
			$memcached->delete(DBNAME . '-' . PREFIX . 'linkfilter');
		}
	}
	if(isSet($_REQUEST['members']) && isSet($code['members'])){
		$db->exec('DELETE FROM ' . PREFIX . 'inbox;');
		$db->exec('DELETE FROM ' . PREFIX . 'members;');
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, boxwidth, boxheight, notesboxwidth, notesboxheight, regedby, lastlogin, timestamps, embed, incognito, style, nocache, tz, eninbox) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		$defaulttz=get_setting('defaulttz');
		foreach($code['members'] as $member){
			if(!isSet($member['nocache'])){
				$member['nocache']=0;
			}
			if(!isSet($member['tz'])){
				$member['tz']=$defaulttz;
			}
			if(!isSet($member['eninbox'])){
				$member['eninbox']=0;
			}
			$stmt->execute(array($member['nickname'], $member['passhash'], $member['status'], $member['refresh'], $member['bgcolour'], $member['boxwidth'], $member['boxheight'], $member['notesboxwidth'], $member['notesboxheight'], $member['regedby'], $member['lastlogin'], $member['timestamps'], $member['embed'], $member['incognito'], $member['style'], $member['nocache'], $member['tz'], $member['eninbox']));
		}
	}
	if(isSet($_REQUEST['notes']) && isSet($code['notes'])){
		$db->exec('DELETE FROM ' . PREFIX . 'notes;');
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'notes (type, lastedited, editedby, text) VALUES (?, ?, ?, ?);');
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
			$result=$db->query('SELECT * FROM ' . PREFIX . 'filter;');
			while($filter=$result->fetch(PDO::FETCH_ASSOC)){
				$code['filters'][]=array('match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'allowinpm'=>$filter['allowinpm'], 'regex'=>$filter['regex'], 'kick'=>$filter['kick']);
			}
			$result=$db->query('SELECT * FROM ' . PREFIX . 'linkfilter;');
			while($filter=$result->fetch(PDO::FETCH_ASSOC)){
				$code['linkfilters'][]=array('match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'regex'=>$filter['regex']);
			}
		}
		if(isSet($_REQUEST['members'])){
			$result=$db->query('SELECT * FROM ' . PREFIX . 'members;');
			while($member=$result->fetch(PDO::FETCH_ASSOC)){
				$code['members'][]=$member;
			}
		}
		if(isSet($_REQUEST['notes'])){
			$result=$db->query('SELECT * FROM ' . PREFIX . "notes WHERE type='admin' ORDER BY id DESC LIMIT 1;");
			$code['notes'][]=$result->fetch(PDO::FETCH_ASSOC);
			$result=$db->query('SELECT * FROM ' . PREFIX . "notes WHERE type='staff' ORDER BY id DESC LIMIT 1;");
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
	echo "<h2>$I[backuprestore]</h2><table class=\"center-table\">";
	thr();
	if(!extension_loaded('json')){
		echo "<tr><td>$I[jsonextrequired]</td></tr>";
	}else{
		echo "<tr><td><$H[form]>$H[commonform]".hidden('action', 'setup').hidden('do', 'backup');
		echo '<table class="left-table"><tr><td>';
		echo "<input type=\"checkbox\" name=\"settings\" id=\"backupsettings\" value=\"1\"$chksettings><label for=\"backupsettings\">$I[settings]</label>";
		echo "<input type=\"checkbox\" name=\"filter\" id=\"backupfilter\" value=\"1\"$chkfilters><label for=\"backupfilter\">$I[filter]</label>";
		echo "<input type=\"checkbox\" name=\"members\" id=\"backupmembers\" value=\"1\"$chkmembers><label for=\"backupmembers\">$I[members]</label>";
		echo "<input type=\"checkbox\" name=\"notes\" id=\"backupnotes\" value=\"1\"$chknotes><label for=\"backupnotes\">$I[notes]</label>";
		echo '</td><td class="right">'.submit($I['backup']).'</td></tr></table></form></td></tr>';
		thr();
		echo "<tr><td><$H[form]>$H[commonform]".hidden('action', 'setup').hidden('do', 'restore');
		echo '<table>';
		echo "<tr><td colspan=\"2\"><textarea name=\"restore\" rows=\"4\" cols=\"60\">".htmlspecialchars(json_encode($code)).'</textarea></td></tr>';
		echo "<tr><td class=\"left\"><input type=\"checkbox\" name=\"settings\" id=\"restoresettings\" value=\"1\"$chksettings><label for=\"restoresettings\">$I[settings]</label>";
		echo "<input type=\"checkbox\" name=\"filter\" id=\"restorefilter\" value=\"1\"$chkfilters><label for=\"restorefilter\">$I[filter]</label>";
		echo "<input type=\"checkbox\" name=\"members\" id=\"restoremembers\" value=\"1\"$chkmembers><label for=\"restoremembers\">$I[members]</label>";
		echo "<input type=\"checkbox\" name=\"notes\" id=\"restorenotes\" value=\"1\"$chknotes><label for=\"restorenotes\">$I[notes]</label>";
		echo '</td><td class="right">'.submit($I['restore']).'</td></tr></table>';
		echo '</form></td></tr>';
	}
	thr();
	echo "<tr><td><$H[form]>$H[commonform]".hidden('action', 'setup').submit($I['initgosetup'], 'class="backbutton"')."</form></tr></td>";
	echo '</table>';
	print_end();
}

function send_destroy_chat(){
	global $H, $I;
	print_start('destroy_chat');
	echo "<table class=\"center-table\"><tr><td colspan=\"2\">$I[confirm]</td></tr><tr><td>";
	echo "<$H[form] target=\"_parent\">$H[commonform]".hidden('action', 'setup').hidden('do', 'destroy').hidden('confirm', 'yes').submit($I['yes'], 'class="delbutton"').'</form></td><td>';
	echo "<$H[form]>$H[commonform]".hidden('action', 'setup').submit($I['no'], 'class="backbutton"').'</form></td><tr></table>';
	print_end();
}

function send_delete_account(){
	global $H, $I;
	print_start('delete_account');
	echo "<table class=\"center-table\"><tr><td colspan=\"2\">$I[confirm]</td></tr><tr><td>";
	echo "<$H[form]>$H[commonform]".hidden('action', 'profile').hidden('do', 'delete').hidden('confirm', 'yes').submit($I['yes'], 'class="delbutton"').'</form></td><td>';
	echo "<$H[form]>$H[commonform]".hidden('action', 'profile').submit($I['no'], 'class="backbutton"').'</form></td><tr></table>';
	print_end();
}

function send_init(){
	global $H, $I, $L;
	print_start('init');
	echo "<h2>$I[init]</h2>";
	echo "<$H[form]>$H[commonform]".hidden('action', 'init')."<table class=\"center-table\"><tr><td><h3>$I[sulogin]</h3><table class=\"left\">";
	echo "<tr><td>$I[sunick]</td><td><input type=\"text\" name=\"sunick\" size=\"15\"></td></tr>";
	echo "<tr><td>$I[supass]</td><td><input type=\"password\" name=\"supass\" size=\"15\"></td></tr>";
	echo "<tr><td>$I[suconfirm]</td><td><input type=\"password\" name=\"supassc\" size=\"15\"></td></tr>";
	echo '</table></td></tr><tr><td><br>'.submit($I['initbtn']).'</td></tr></table></form>';
	echo "<p>$I[changelang]";
	foreach($L as $lang=>$name){
		echo " <a href=\"$_SERVER[SCRIPT_NAME]?action=setup&amp;lang=$lang\">$name</a>";
	}
	echo "</p>$H[credit]";
	print_end();
}

function send_update(){
	global $H, $I;
	print_start('update');
	echo "<h2>$I[dbupdate]</h2><br><$H[form]>$H[commonform]".hidden('action', 'setup').submit($I['initgosetup'])."</form><br>$H[credit]";
	print_end();
}

function send_alogin(){
	global $H, $I, $L;
	print_start('alogin');
	echo "<$H[form]>$H[commonform]".hidden('action', 'setup').'<table class="center-table left">';
	echo "<tr><td>$I[nick]</td><td><input type=\"text\" name=\"nick\" size=\"15\" autofocus></td></tr>";
	echo "<tr><td>$I[pass]</td><td><input type=\"password\" name=\"pass\" size=\"15\"></td></tr>";
	send_captcha();
	echo '<tr><td colspan="2" class="right">'.submit($I['login']).'</td></tr></table></form>';
	echo "<p>$I[changelang]";
	foreach($L as $lang=>$name){
		echo " <a href=\"$_SERVER[SCRIPT_NAME]?action=setup&amp;lang=$lang\">$name</a>";
	}
	echo "</p>$H[credit]";
	print_end();
}

function send_admin($arg=''){
	global $A, $H, $I, $P, $U, $db;
	$ga=(int) get_setting('guestaccess');
	print_start('admin');
	$chlist="<select name=\"name[]\" size=\"5\" multiple><option value=\"\">$I[choose]</option>";
	$chlist.="<option value=\"&\">$I[allguests]</option>";
	sort_names($P);
	foreach($P as $user){
		if($user[2]<$U['status']){
			$chlist.="<option value=\"$user[0]\" style=\"$user[1]\">$user[0]</option>";
		}
	}
	$chlist.='</select>';
	echo "<h2>$I[admfunc]</h2><i>$arg</i><table class=\"center-table\">";
	if($U['status']>=7){
		thr();
		echo "<tr><td><$H[form] target=\"view\">$H[commonform]".hidden('action', 'setup').submit($I['initgosetup']).'</form></td></tr>';
	}
	thr();
	echo "<tr><td><table class=\"left-table\"><tr><th>$I[cleanmsgs]</th><td>";
	frmadm('clean');
	echo '<table class="left right-table"><tr><td><input type="radio" name="what" id="room" value="room">';
	echo "<label for=\"room\">$I[room]</label></td><td>&nbsp;</td><td><input type=\"radio\" name=\"what\" id=\"choose\" value=\"choose\" checked>";
	echo "<label for=\"choose\">$I[selection]</label></td><td>&nbsp;</td></tr><tr><td colspan=\"3\"><input type=\"radio\" name=\"what\" id=\"nick\" value=\"nick\">";
	echo "<label for=\"nick\">$I[cleannick] </label><select name=\"nickname\" size=\"1\"><option value=\"\">$I[choose]</option>";
	$stmt=$db->prepare('SELECT poster FROM ' . PREFIX . 'messages WHERE poststatus<9 AND delstatus<? GROUP BY poster;');
	$stmt->execute(array($U['status']));
	while($nick=$stmt->fetch(PDO::FETCH_NUM)){
		echo "<option value=\"$nick[0]\">$nick[0]</option>";
	}
	echo '</select></td><td>';
	echo submit($I['clean'], 'class="delbutton"').'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo '<tr><td><table class="left-table"><tr><td>'.sprintf($I['kickchat'], get_setting('kickpenalty')).'</td></tr><tr><td>';
	frmadm('kick');
	echo "<table class=\"right-table\"><tr><td>$I[kickreason]</td><td class=\"right\"><input type=\"text\" name=\"kickmessage\" size=\"30\"></td><td>&nbsp;</td></tr>";
	echo "<tr><td><input type=\"checkbox\" name=\"what\" value=\"purge\" id=\"purge\"><label for=\"purge\">&nbsp;$I[kickpurge]</label></td><td class=\"right\">$chlist</td><td class=\"right\">";
	echo submit($I['kick']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table class=\"left-table\"><tr><th>$I[logoutinact]</th><td>";
	frmadm('logout');
	echo "<table class=\"right-table\"><tr class=\"right\"><td>$chlist</td><td>";
	echo submit($I['logout']).'</td></tr></table></form></td></tr></table></td></tr>';
	$views=array('sessions', 'filter', 'linkfilter');
	foreach($views as $view){
		thr();
		echo '<tr><td><table class="left-table"><tr><th>'.$I[$view].'</th><td class="right">';
		frmadm($view);
		echo submit($I['view']).'</form></td></tr></table></td></tr>';
	}
	thr();
	echo "<tr><td><table class=\"left-table\"><tr><th>$I[topic]</th><td>";
	frmadm('topic');
	echo '<table class="right-table"><tr><td><input type="text" name="topic" size="20" value="'.get_setting('topic').'"></td><td>';
	echo submit($I['change']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table class=\"left-table\"><tr><th>$I[guestacc]</th><td>";
	frmadm('guestaccess');
	echo '<table class="right-table">';
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
		echo "<tr><td><table class=\"left-table\"><tr><th>$I[addsuguest]</th><td>";
		frmadm('superguest');
		echo "<table class=\"right-table\"><tr><td><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($P as $user){
			if($user[2]==1){
				echo "<option value=\"$user[0]\" style=\"$user[1]\">$user[0]</option>";
			}
		}
		echo '</select></td><td>'.submit($I['register']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
	}
	if($U['status']>=7){
		echo "<tr><td><table class=\"left-table\"><tr><th>$I[admmembers]</th><td>";
		frmadm('status');
		echo "<table class=\"right-table\"><td class=\"right\"><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		read_members();
		sort_names($A);
		foreach($A as $member){
			echo "<option value=\"$member[0]\" style=\"$member[1]\">$member[0]";
			if($member[2]==0){
				echo ' (!)';
			}elseif($member[2]==2){
				echo ' (G)';
			}elseif($member[2]==5){
				echo ' (M)';
			}elseif($member[2]==6){
				echo ' (SM)';
			}elseif($member[2]==7){
				echo ' (A)';
			}elseif($member[2]==8){
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
		echo "<tr><td><table class=\"left-table\"><tr><th>$I[passreset]</th><td>";
		frmadm('passreset');
		echo "<table class=\"right-table\"><td><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($A as $member){
			echo "<option value=\"$member[0]\" style=\"$member[1]\">$member[0]</option>";
		}
		echo '</select></td><td><input type="password" name="pass"></td><td>'.submit($I['change']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
		echo "<tr><td><table class=\"left-table\"><tr><th>$I[regguest]</th><td>";
		frmadm('register');
		echo "<table class=\"right-table\"><tr><td><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($P as $user){
			if($user[2]==1){
				echo "<option value=\"$user[0]\" style=\"$user[1]\">$user[0]</option>";
			}
		}
		echo '</select></td><td>'.submit($I['register']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
		echo "<tr><td><table class=\"left-table\"><tr><th>$I[regmem]</th></tr><tr><td>";
		frmadm('regnew');
		echo "<table class=\"right-table\"><tr><td>$I[nick]</td><td>&nbsp;</td><td><input type=\"text\" name=\"name\" size=\"20\"></td><td>&nbsp;</td></tr>";
		echo "<tr><td>$I[pass]</td><td>&nbsp;</td><td><input type=\"password\" name=\"pass\" size=\"20\"></td><td>";
		echo submit($I['register']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
	}
	echo "</table>$H[backtochat]";
	print_end();
}

function send_sessions(){
	global $H, $I, $U, $db;
	$stmt=$db->prepare('SELECT nickname, style, lastpost, status, useragent, ip FROM ' . PREFIX . 'sessions WHERE status!=0 AND entry!=0 AND (incognito=0 OR status<?) ORDER BY status DESC, lastpost DESC;');
	$stmt->execute(array($U['status']));
	if(!$lines=$stmt->fetchAll(PDO::FETCH_ASSOC)){
		$lines=array();
	}
	print_start('sessions');
	echo "<h1>$I[sessact]</h1><table class=\"center-table\">";
	echo "<tr><th class=\"padded\">$I[sessnick]</th><th class=\"padded\">$I[sesstimeout]</th><th class=\"padded\">$I[sessua]</th>";
	$trackip=(bool) get_setting('trackip');
	$memexpire=(int) get_setting('memberexpire');
	$guestexpire=(int) get_setting('guestexpire');
	if($trackip) echo "<th class=\"padded\">$I[sesip]</th>";
	echo "<th class=\"padded\">$I[actions]</th></tr>";
	foreach($lines as $temp){
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
		echo '<tr class="left"><td class="padded">'.style_this($temp['nickname'].$s, $temp['style']).'</td><td class="padded">';
		if($temp['status']>2){
			get_timeout($temp['lastpost'], $memexpire);
		}else{
			get_timeout($temp['lastpost'], $guestexpire);
		}
		echo '</td>';
		if($U['status']>$temp['status'] || $U['nickname']===$temp['nickname']){
			echo "<td class=\"padded\">$temp[useragent]</td>";
			if($trackip){
				echo "<td class=\"padded\">$temp[ip]</td>";
			}
			echo '<td class="padded">';
			frmadm('sessions');
			echo hidden('nick', $temp['nickname']).submit($I['kick']).'</form></td></tr>';
		}else{
			echo '<td class="padded">-</td>';
			if($trackip){
				echo '<td class="padded">-</td>';
			}
			echo '<td class="padded">-</td></tr>';
		}
	}
	echo "</table><br>$H[backtochat]";
	print_end();
}

function check_filter_match(&$reg){
	global $I;
	$_REQUEST['match']=htmlspecialchars($_REQUEST['match']);
	if(isSet($_REQUEST['regex']) && $_REQUEST['regex']==1){
		$_REQUEST['match']=preg_replace('~(^|[^\\\\])/~', "$1\/", $_REQUEST['match']); // Escape "/" if not yet escaped
		if(@preg_match("/$_REQUEST[match]/", '')===false){
			return "$I[incorregex]<br>$I[prevmatch]: $_REQUEST[match]";
		}
		$reg=1;
	}else{
		$_REQUEST['match']=preg_replace('/([^\w\d])/', "\\\\$1", $_REQUEST['match']);
		$reg=0;
	}
	if(strlen($_REQUEST['match'])>255){
		return "$I[matchtoolong]<br>$I[prevmatch]: $_REQUEST[match]";
	}
	return false;
}

function manage_filter(){
	global $db, $memcached;
	if(isSet($_REQUEST['id'])){
		if($tmp=check_filter_match($reg)){
			return $tmp;
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
				$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'filter WHERE id=?;');
				$stmt->execute(array($_REQUEST['id']));
				if(MEMCACHED){
					$memcached->delete(DBNAME . '-' . PREFIX . 'filter');
				}
			}else{
				$stmt=$db->prepare('UPDATE ' . PREFIX . 'filter SET filtermatch=?, filterreplace=?, allowinpm=?, regex=?, kick=? WHERE id=?;');
				$stmt->execute(array($_REQUEST['match'], $_REQUEST['replace'], $pm, $reg, $kick, $_REQUEST['id']));
				if(MEMCACHED){
					$memcached->delete(DBNAME . '-' . PREFIX . 'filter');
				}
			}
		}elseif(preg_match('/^\+$/', $_REQUEST['id'])){
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'filter (filtermatch, filterreplace, allowinpm, regex, kick) VALUES (?, ?, ?, ?, ?);');
			$stmt->execute(array($_REQUEST['match'], $_REQUEST['replace'], $pm, $reg, $kick));
			if(MEMCACHED){
				$memcached->delete(DBNAME . '-' . PREFIX . 'filter');
			}
		}
	}
}

function manage_linkfilter(){
	global $db, $memcached;
	if(isSet($_REQUEST['id'])){
		if($tmp=check_filter_match($reg)){
			return $tmp;
		}
		if(preg_match('/^[0-9]*$/', $_REQUEST['id'])){
			if(empty($_REQUEST['match'])){
				$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'linkfilter WHERE id=?;');
				$stmt->execute(array($_REQUEST['id']));
				if(MEMCACHED){
					$memcached->delete(DBNAME . '-' . PREFIX . 'linkfilter');
				}
			}else{
				$stmt=$db->prepare('UPDATE ' . PREFIX . 'linkfilter SET filtermatch=?, filterreplace=?, regex=? WHERE id=?;');
				$stmt->execute(array($_REQUEST['match'], $_REQUEST['replace'], $reg, $_REQUEST['id']));
				if(MEMCACHED){
					$memcached->delete(DBNAME . '-' . PREFIX . 'linkfilter');
				}
			}
		}elseif(preg_match('/^\+$/', $_REQUEST['id'])){
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'linkfilter (filtermatch, filterreplace, regex) VALUES (?, ?, ?);');
			$stmt->execute(array($_REQUEST['match'], $_REQUEST['replace'], $reg));
			if(MEMCACHED){
				$memcached->delete(DBNAME . '-' . PREFIX . 'linkfilter');
			}
		}
	}
}

function get_filters(){
	global $db, $memcached;
	if(MEMCACHED){
		$filters=$memcached->get(DBNAME . '-' . PREFIX . 'filter');
	}
	if(!MEMCACHED || $memcached->getResultCode()!==Memcached::RES_SUCCESS){
		$filters=array();
		$result=$db->query('SELECT id, filtermatch, filterreplace, allowinpm, regex, kick FROM ' . PREFIX . 'filter;');
		while($filter=$result->fetch(PDO::FETCH_ASSOC)){
			$filters[]=array('id'=>$filter['id'], 'match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'allowinpm'=>$filter['allowinpm'], 'regex'=>$filter['regex'], 'kick'=>$filter['kick']);
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
		$filters=array();
		$result=$db->query('SELECT id, filtermatch, filterreplace, regex FROM ' . PREFIX . 'linkfilter;');
		while($filter=$result->fetch(PDO::FETCH_ASSOC)){
			$filters[]=array('id'=>$filter['id'], 'match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'regex'=>$filter['regex']);
		}
		if(MEMCACHED){
			$memcached->set(DBNAME . '-' . PREFIX . 'linkfilter', $filters);
		}
	}
	return $filters;
}

function send_filter($arg=''){
	global $H, $I, $U;
	print_start('filter');
	echo "<h2>$I[filter]</h2><i>$arg</i><table class=\"center-table\">";
	thr();
	echo "<tr><th><table style=\"width:100%;\"><tr><td style=\"width:8em;\">$I[fid]</td>";
	echo "<td style=\"width:12em;\">$I[match]</td>";
	echo "<td style=\"width:12em;\">$I[replace]</td>";
	echo "<td style=\"width:9em;\">$I[allowpm]</td>";
	echo "<td style=\"width:5em;\">$I[regex]</td>";
	echo "<td style=\"width:5em;\">$I[kick]</td>";
	echo "<td style=\"width:5em;\">$I[apply]</td></tr></table></th></tr>";
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
		echo '<td class="right" style="width:5em;">'.submit($I['change']).'</td></tr></table></form></td></tr>';
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
	echo '<td class="right" style="width:5em;">'.submit($I['add']).'</td></tr></table></form></td></tr>';
	echo "</table><br>$H[backtochat]";
	print_end();
}

function send_linkfilter($arg=''){
	global $H, $I, $U;
	print_start('linkfilter');
	echo "<h2>$I[linkfilter]</h2><i>$arg</i><table class=\"center-table\">";
	thr();
	echo "<tr><th><table style=\"width:100%;\"><tr><td style=\"width:8em;\">$I[fid]</td>";
	echo "<td style=\"width:12em;\">$I[match]</td>";
	echo "<td style=\"width:12em;\">$I[replace]</td>";
	echo "<td style=\"width:5em;\">$I[regex]</td>";
	echo "<td style=\"width:5em;\">$I[apply]</td></tr></table></th></tr>";
	$filters=get_linkfilters();
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
		echo '<td class="right" style="width:5em;">'.submit($I['change']).'</td></tr></table></form></td></tr>';
	}
	echo '<tr><td>';
	frmadm('linkfilter');
	echo hidden('id', '+');
	echo "<table style=\"width:100%;\"><tr><th style=\"width:8em;\">$I[newfilter]</th>";
	echo "<td style=\"width:12em;\"><input type=\"text\" name=\"match\" value=\"\" size=\"20\" style=\"$U[style]\"></td>";
	echo "<td style=\"width:12em;\"><input type=\"text\" name=\"replace\" value=\"\" size=\"20\" style=\"$U[style]\"></td>";
	echo "<td style=\"width:5em;\"><input type=\"checkbox\" name=\"regex\" id=\"regex\" value=\"1\"><label for=\"regex\">$I[regex]</label></td>";
	echo '<td class="right" style="width:5em;">'.submit($I['add']).'</td></tr></table></form></td></tr>';
	echo "</table><br>$H[backtochat]";
	print_end();
}

function send_frameset(){
	global $H, $I, $U, $language;
	echo "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Frameset//EN\" \"http://www.w3.org/TR/html4/frameset.dtd\"><html><head>$H[meta_html]";
	echo '<title>'.get_setting('chatname').'</title>';
	print_stylesheet();
	if(isSet($_COOKIE['language'])){
		echo "</head><frameset rows=\"100,*,60\" border=\"3\" frameborder=\"3\" framespacing=\"3\"><frame name=\"post\" src=\"$_SERVER[SCRIPT_NAME]?action=post\"><frame name=\"view\" src=\"$_SERVER[SCRIPT_NAME]?action=view\"><frame name=\"controls\" src=\"$_SERVER[SCRIPT_NAME]?action=controls\"><noframes><body>$I[noframes]$H[backtologin]</body></noframes></frameset></html>";
	}else{
		echo "</head><frameset rows=\"100,*,60\" border=\"3\" frameborder=\"3\" framespacing=\"3\"><frame name=\"post\" src=\"$_SERVER[SCRIPT_NAME]?action=post&session=$U[session]&lang=$language\"><frame name=\"view\" src=\"$_SERVER[SCRIPT_NAME]?action=view&session=$U[session]&lang=$language\"><frame name=\"controls\" src=\"$_SERVER[SCRIPT_NAME]?action=controls&session=$U[session]&lang=$language\"><noframes><body>$I[noframes]$H[backtologin]</body></noframes></frameset></html>";
	}
	exit;
}

function send_messages(){
	global $H, $I, $U, $db, $language;
	if($U['nocache']){
		$nocache='&nc='.substr(time(), -6);
	}else{
		$nocache='';
	}
	if(isSet($_COOKIE[COOKIENAME])){
		print_start('messages', $U['refresh'], "$_SERVER[SCRIPT_NAME]?action=view$nocache");
	}else{
		print_start('messages', $U['refresh'], "$_SERVER[SCRIPT_NAME]?action=view&session=$U[session]&lang=$language$nocache");
	}
	echo '<div class="left">';
	echo '<a id="top"></a>';
	echo '<div id="topic">';
	echo get_setting('topic');
	echo '</div><div id="chatters">';
	print_chatters();
	echo "</div><a style=\"position:fixed;top:0.5em;right:0.5em\" href=\"#bottom\">$I[bottom]</a><div id=\"messages\">";
	if($U['status']>=2 && $U['eninbox']!=0){
		$stmt=$db->prepare('SELECT COUNT(*) FROM ' . PREFIX . 'inbox WHERE recipient=?;');
		$stmt->execute(array($U['nickname']));
		$tmp=$stmt->fetch(PDO::FETCH_NUM);
		if($tmp[0]>0){
			echo "<p><$H[form]>$H[commonform]".hidden('action', 'inbox');
			echo submit(sprintf($I['inboxmsgs'], $tmp[0])).'</form></p>';
		}
	}
	print_messages();
	echo '</div>';
	echo "<a id=\"bottom\"></a><a style=\"position:fixed;bottom:0.5em;right:0.5em\" href=\"#top\">$I[top]</a>";
	echo '</div>';
	print_end();
}

function send_inbox(){
	global $H, $I, $U, $db;
	print_start('messages');
	echo '<div class="left">';
	echo "<$H[form]>$H[commonform]".hidden('action', 'inbox').hidden('do', 'clean').submit($I['delselmes'], 'class="delbutton"').'<br><br>';
	$dateformat=get_setting('dateformat');
	$tz=3600*$U['tz'];
	if(!isSet($_COOKIE[COOKIENAME]) && get_setting('forceredirect')==0){
		$injectRedirect=true;
		$redirect=get_setting('redirect');
		if(empty($redirect)){
			$redirect="$_SERVER[SCRIPT_NAME]?action=redirect&amp;url=";
		}
	}else{
		$injectRedirect=false;
		$redirect='';
	}
	if(get_setting('imgembed') && (!$U['embed'] || !isSet($_COOKIE[COOKIENAME]))){
		$removeEmbed=true;
	}else{
		$removeEmbed=false;
	}
	if($U['timestamps'] && !empty($dateformat)){
		$timestamps=true;
	}else{
		$timestamps=false;
	}
	if(MSGENCRYPTED){
		if(!extension_loaded('openssl')){
			send_fatal_error($I['opensslextrequired']);
		}
	}
	$stmt=$db->prepare('SELECT id, postdate, text FROM ' . PREFIX . 'inbox WHERE recipient=? ORDER BY id DESC;');
	$stmt->execute(array($U['nickname']));
	while($message=$stmt->fetch(PDO::FETCH_ASSOC)){
		prepare_message_print($message, $injectRedirect, $redirect, $removeEmbed);
		echo "<div class=\"msg\"><input type=\"checkbox\" name=\"mid[]\" id=\"$message[id]\" value=\"$message[id]\"><label for=\"$message[id]\">";
		if($timestamps){
			echo ' <small>'.date($dateformat, $message['postdate']+$tz).' - </small>';
		}
		echo " $message[text]</label></div>";
	}
	echo "</form><br>$H[backtochat]";
	echo '</div>';
	print_end();
}

function send_notes($type){
	global $H, $I, $U, $db;
	print_start('notes');
	if($U['status']>=6){
		echo "<table class=\"center-table\"><tr><td><$H[form] target=\"view\">$H[commonform]".hidden('action', 'notes').hidden('do', 'admin').submit($I['admnotes']).'</form></td>';
		echo "<td><$H[form] target=\"view\">$H[commonform]".hidden('action', 'notes').submit($I['notes']).'</form></td></tr></table>';
	}
	if($type==='staff'){
		echo "<h2>$I[staffnotes]</h2><p>";
	}else{
		echo "<h2>$I[adminnotes]</h2><p>";
	}
	if(isset($_REQUEST['text'])){
		if(MSGENCRYPTED){
			if(!extension_loaded('openssl')){
				send_fatal_error($I['opensslextrequired']);
			}
			$_REQUEST['text']=openssl_encrypt($_REQUEST['text'], 'aes-256-cbc', ENCRYPTKEY, 0, '1234567890123456');
		}
		$time=time();
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'notes (type, lastedited, editedby, text) VALUES (?, ?, ?, ?);');
		$stmt->execute(array($type, $time, $U['nickname'], $_REQUEST['text']));
		$offset=get_setting('numnotes');
		$stmt=$db->prepare('SELECT id FROM ' . PREFIX . "notes WHERE type=? ORDER BY id DESC LIMIT 1 OFFSET $offset;");
		$stmt->execute(array($type));
		if($id=$stmt->fetch(PDO::FETCH_NUM)){
			$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'notes WHERE type=? AND id <=?;');
			$stmt->execute(array($type, $id[0]));
		}
		echo "<b>$I[notessaved]</b> ";
	}
	$dateformat=get_setting('dateformat');
	$stmt=$db->prepare('SELECT COUNT(*) FROM ' . PREFIX . 'notes WHERE type=?;');
	$stmt->execute(array($type));
	$num=$stmt->fetch(PDO::FETCH_NUM);
	if(!empty($_REQUEST['revision'])){
		$revision=intval($_REQUEST['revision']);
	}else{
		$revision=0;
	}
	$stmt=$db->prepare('SELECT * FROM ' . PREFIX . "notes WHERE type=? ORDER BY id DESC LIMIT 1 OFFSET $revision;");
	$stmt->execute(array($type));
	if($note=$stmt->fetch(PDO::FETCH_ASSOC)){
			printf($I['lastedited'], $note['editedby'], date($dateformat, $note['lastedited']+3600*$U['tz']));
	}else{
		$note['text']='';
	}
	if(MSGENCRYPTED){
		if(!extension_loaded('openssl')){
			send_fatal_error($I['opensslextrequired']);
		}
		$note['text']=openssl_decrypt($note['text'], 'aes-256-cbc', ENCRYPTKEY, 0, '1234567890123456');
	}
	echo "</p><$H[form]>$H[commonform]";
	if($type==='admin'){
		echo hidden('do', 'admin');
	}
	echo hidden('action', 'notes')."<textarea name=\"text\" rows=\"$U[notesboxheight]\" cols=\"$U[notesboxwidth]\">".htmlspecialchars($note['text']).'</textarea><br>';
	echo submit($I['savenotes']).'</form><br>';
	if($num[0]>1){
		echo "<br><table class=\"center-table\"><tr><td>$I[revisions]</td>";
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
	print_end();
}

function send_approve_waiting(){
	global $H, $I, $db;
	print_start('approve_waiting');
	echo "<h2>$I[waitingroom]</h2>";
	$result=$db->query('SELECT * FROM ' . PREFIX . 'sessions WHERE entry=0 AND status=1 ORDER BY id;');
	if($tmp=$result->fetchAll(PDO::FETCH_ASSOC)){
		frmadm('approve');
		echo '<table class="center-table left">';
		echo "<tr><th class=\"padded\">$I[sessnick]</th><th class=\"padded\">$I[sessua]</th></tr>";
		foreach($tmp as $temp){
			echo '<tr>'.hidden('alls[]', $temp['nickname'])."<td class=\"padded\"><input type=\"checkbox\" name=\"csid[]\" id=\"$temp[nickname]\" value=\"$temp[nickname]\"><label for=\"$temp[nickname]\"> ".style_this($temp['nickname'], $temp['style'])."</label></td><td class=\"padded\">$temp[useragent]</td></tr>";
		}
		echo "</table><br><table class=\"center-table left\"><tr><td><input type=\"radio\" name=\"what\" value=\"allowchecked\" id=\"allowchecked\" checked><label for=\"allowchecked\">$I[allowchecked]</label></td>";
		echo "<td><input type=\"radio\" name=\"what\" value=\"allowall\" id=\"allowall\"><label for=\"allowall\">$I[allowall]</label></td>";
		echo "<td><input type=\"radio\" name=\"what\" value=\"denychecked\" id=\"denychecked\"><label for=\"denychecked\">$I[denychecked]</label></td>";
		echo "<td><input type=\"radio\" name=\"what\" value=\"denyall\" id=\"denyall\"><label for=\"denyall\">$I[denyall]</label></td></tr><tr><td colspan=\"8\" class=\"center\">$I[denymessage] <input type=\"text\" name=\"kickmessage\" size=\"45\"></td>";
		echo '</tr><tr><td colspan="8" class="center">'.submit($I['butallowdeny']).'</td></tr></table></form>';
	}else{
		echo "$I[waitempty]<br>";
	}
	echo "<br>$H[backtochat]";
	print_end();
}

function send_waiting_room(){
	global $H, $I, $U, $countmods, $db, $language;
	parse_sessions();
	$ga=(int) get_setting('guestaccess');
	if($ga===3 && ($countmods>0 || !get_setting('modfallback'))){
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
		$stmt->execute(array($U['session']));
		send_frameset();
	}elseif(!$wait && $U['entry']!=0){
		send_frameset();
	}else{
		$refresh=(int) get_setting('defaultrefresh');
		if(isSet($_COOKIE['language'])){
			print_start('waitingroom', $refresh, "$_SERVER[SCRIPT_NAME]?action=wait&nc=".substr(time(),-6));
		}else{
			print_start('waitingroom', $refresh, "$_SERVER[SCRIPT_NAME]?action=wait&session=$U[session]&lang=$language&nc=".substr(time(),-6));
		}
		echo "<h2>$I[waitingroom]</h2><p>";
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
			echo hidden('session', $U['session']);
		}
		echo hidden('action', 'wait').submit($I['reload']).'</form><br>';
		echo "<$H[form]>$H[commonform]";
		if(!isSet($_REQUEST['session'])){
			echo hidden('session', $U['session']);
		}
		echo hidden('action', 'logout').submit($I['exit'], 'id="exitbutton"').'</form>';
		$rulestxt=get_setting('rulestxt');
		if(!empty($rulestxt)){
			echo "<h2>$I[rules]</h2><b>$rulestxt</b>";
		}
		print_end();
	}
}

function send_choose_messages(){
	global $H, $I, $U;
	print_start('choose_messages');
	echo '<div class="left">';
	frmadm('clean');
	echo hidden('what', 'selected').submit($I['delselmes'], 'class="delbutton"').'<br><br>';
	print_messages($U['status']);
	echo "</form><br>$H[backtochat]";
	echo '</div>';
	print_end();
}

function send_del_confirm(){
	global $I;
	print_start('del_confirm');
	echo "<table class=\"center-table\"><tr><td colspan=\"2\">$I[confirm]</td></tr><tr><td>";
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
	echo submit($I['no'], 'class="backbutton"').'</form></td><tr></table>';
	print_end();
}

function send_post(){
	global $I, $P, $U, $countmods, $db;
	$U['postid']=substr(time(), -6);
	print_start('post');
	if(!isSet($_REQUEST['sendto'])){
		$_REQUEST['sendto']='';
	}
	echo '<table class="center-table" style="border-spacing:0px;"><tr><td>';
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
	$disablepm=(bool) get_setting('disablepm');
	if(!$disablepm){
		$stmt=$db->prepare('SELECT nickname, style, status FROM ' . PREFIX . 'members WHERE eninbox!=0 AND eninbox<=? AND nickname NOT IN (SELECT nickname FROM ' . PREFIX . 'sessions WHERE incognito=0) AND nickname NOT IN (SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=?) AND nickname NOT IN (SELECT ignby FROM ' . PREFIX . 'ignored WHERE ign=?);');
		$stmt->execute(array($U['status'], $U['nickname'], $U['nickname']));
		while($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
			$P[$tmp['nickname']]=["$tmp[nickname] $I[offline]", $tmp['style'], $tmp['status'], $tmp['nickname']];
		}
		sort_names($P);
		foreach($P as $user){
			if($U['nickname']!==$user[3]){
				echo '<option ';
				if($_REQUEST['sendto']==$user[3]){
					echo 'selected ';
				}
				echo "value=\"$user[3]\" style=\"$user[1]\">$user[0]</option>";
			}
		}
	}
	echo '</select>';
	if(!$disablepm && ($U['status']>=5 || ($U['status']>=3 && $countmods===0 && get_setting('memkick')))){
		echo "<input type=\"checkbox\" name=\"kick\" id=\"kick\" value=\"kick\"><label for=\"kick\">&nbsp;$I[kick]</label>";
		echo "<input type=\"checkbox\" name=\"what\" id=\"what\" value=\"purge\" checked><label for=\"what\">&nbsp;$I[alsopurge]</label>";
	}
	echo '</td></tr></table></form></td></tr><tr><td style="height:8px;"></td></tr><tr><td><table class="center-table" style="border-spacing:0px;"><tr><td>';
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
	echo '</tr></table></td></tr></table>';
	print_end();
}

function send_help(){
	global $H, $I, $U;
	print_start('help');
	echo '<div class="left">';
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
	echo "<br><hr><div class=\"center\">$H[backtochat]$H[credit]</div>";
	echo '</div>';
	print_end();
}

function send_profile($arg=''){
	global $F, $H, $I, $L, $P, $U, $db, $language;
	print_start('profile');
	echo "<$H[form]>$H[commonform]".hidden('action', 'profile').hidden('do', 'save')."<h2>$I[profile]</h2><i>$arg</i><table class=\"center-table\">";
	thr();
	sort_names($P);
	$ignored=[];
	$stmt=$db->prepare('SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=?;');
	$stmt->execute([$U['nickname']]);
	while($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
		$ignored[]=$tmp['ign'];
	}
	if(count($ignored)>0){
		echo "<tr><td><table class=\"left-table\"><tr><th>$I[unignore]</th><td class=\"right\">";
		echo "<select name=\"unignore\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($ignored as $ign){
			echo "<option value=\"$ign\">$ign</option>";
		}
		echo '</select></td></tr></table></td></tr>';
		thr();
	}
	echo "<tr><td><table class=\"left-table\"><tr><th>$I[ignore]</th><td class=\"right\">";
	echo "<select name=\"ignore\" size=\"1\"><option value=\"\">$I[choose]</option>";
	$stmt=$db->query('SELECT poster FROM ' . PREFIX . 'messages GROUP BY poster;');
	while($nick=$stmt->fetch(PDO::FETCH_NUM)){
		$nicks[]=$nick[0];
	}
	foreach($P as $user){
		if($U['nickname']!==$user[0] && in_array($user[0], $nicks) && $user[2]<=$U['status']){
			echo "<option value=\"$user[0]\" style=\"$user[1]\">$user[0]</option>";
		}
	}
	echo '</select></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table class=\"left-table\"><tr><th>$I[refreshrate]</th><td class=\"right\">";
	echo "<input type=\"number\" name=\"refresh\" size=\"3\" maxlength=\"3\" min=\"5\" max=\"150\" value=\"$U[refresh]\"></td></tr></table></td></tr>";
	thr();
	if(!isSet($_COOKIE[COOKIENAME])){
		$param="&amp;session=$U[session]&amp;lang=$language";
	}else{
		$param='';
	}
	preg_match('/#([0-9a-f]{6})/i', $U['style'], $matches);
	echo "<tr><td><table class=\"left-table\"><tr><td><b>$I[fontcolour]</b> (<a href=\"$_SERVER[SCRIPT_NAME]?action=colours$param\" target=\"view\">$I[viewexample]</a>)</td><td class=\"right\">";
	echo "<input type=\"text\" size=\"6\" maxlength=\"6\" pattern=\"[a-fA-F0-9]{6}\" value=\"$matches[1]\" name=\"colour\"></td></tr></table></td></tr>";
	thr();
	echo "<tr><td><table class=\"left-table\"><tr><td><b>$I[bgcolour]</b> (<a href=\"$_SERVER[SCRIPT_NAME]?action=colours$param\" target=\"view\">$I[viewexample]</a>)</td><td class=\"right\">";
	echo "<input type=\"text\" size=\"6\" maxlength=\"6\" pattern=\"[a-fA-F0-9]{6}\" value=\"$U[bgcolour]\" name=\"bgcolour\"></td></tr></table></td></tr>";
	thr();
	if($U['status']>=3){
		echo "<tr><td><table class=\"left-table\"><tr><th>$I[fontface]</th><td><table class=\"right-table\">";
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
	$bool_settings=['timestamps', 'nocache'];
	if(get_setting('imgembed')){
		$bool_settings[]='embed';
	}
	if($U['status']>=5 && get_setting('incognito')){
		$bool_settings[]='incognito';
	}
	foreach($bool_settings as $setting){
		echo '<tr><td><table class="left-table"><tr><th>'.$I[$setting].'</th><td class="right">';
		echo "<input type=\"checkbox\" name=\"$setting\" id=\"$setting\" value=\"on\"";
		if($U[$setting]){
			echo ' checked';
		}
		echo "><label for=\"$setting\"><b>$I[enabled]</b></label></td></tr></table></td></tr>";
		thr();
	}
	if($U['status']>=2 && get_setting('eninbox')){
		echo "<tr><td><table class=\"left-table\"><tr><th>$I[eninbox]</th><td class=\"right\">";
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
	echo "<tr><td><table class=\"left-table\"><tr><th>$I[tz]</th><td class=\"right\">";
	echo "<select name=\"tz\" id=\"tz\">";
	$tzs=[-12=>'-12', -11=>'-11', -10=>'-10', -9=>'-9', -8=>'-8', -7=>'-7', -6=>'-6', -5=>'-5', -4=>'-4', -3=>'-3', -2=>'-2', -1=>'-1', 0=>'', 1=>'+1', 2=>'+2', 3=>'+3', 4=>'+4', 5=>'+5', 6=>'+6', 7=>'+7', 8=>'+8', 9=>'+9', 10=>'+10', 11=>'+11', 12=>'+12', 13=>'+13', 14=>'+14'];
	foreach($tzs as $tz=>$name){
		$select = $U['tz']==$tz ? ' selected' : '';
		echo "<option value=\"$tz\"$select>UTC $name</option>";
	}
	echo '</select></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table class=\"left-table\"><tr><th>$I[pbsize]</th><td><table class=\"right-table\">";
	echo "<tr><td>&nbsp;</td><td>$I[width]</td><td><input type=\"number\" name=\"boxwidth\" size=\"3\" maxlength=\"3\" value=\"$U[boxwidth]\"></td>";
	echo "<td>&nbsp;</td><td>$I[height]</td><td><input type=\"number\" name=\"boxheight\" size=\"3\" maxlength=\"3\" value=\"$U[boxheight]\"></td>";
	echo '</tr></table></td></tr></table></td></tr>';
	thr();
	if($U['status']>=5){
		echo "<tr><td><table class=\"left-table\"><tr><th>$I[nbsize]</th><td><table class=\"right-table\">";
		echo "<tr><td>&nbsp;</td><td>$I[width]</td><td><input type=\"number\" name=\"notesboxwidth\" size=\"3\" maxlength=\"3\" value=\"$U[notesboxwidth]\"></td>";
		echo "<td>&nbsp;</td><td>$I[height]</td><td><input type=\"number\" name=\"notesboxheight\" size=\"3\" maxlength=\"3\" value=\"$U[notesboxheight]\"></td>";
		echo '</tr></table></td></tr></table></td></tr>';
		thr();
	}
	if($U['status']>=2){
		echo "<tr><td><table class=\"left-table\"><tr><th>$I[changepass]</th></tr>";
		echo '<tr><td><table class="right-table">';
		echo "<tr><td>&nbsp;</td><td>$I[oldpass]</td><td><input type=\"password\" name=\"oldpass\" size=\"20\"></td></tr>";
		echo "<tr><td>&nbsp;</td><td>$I[newpass]</td><td><input type=\"password\" name=\"newpass\" size=\"20\"></td></tr>";
		echo "<tr><td>&nbsp;</td><td>$I[confirmpass]</td><td><input type=\"password\" name=\"confirmpass\" size=\"20\"></td></tr>";
		echo "<tr><td>&nbsp;</td><td>$I[newnickname]</td><td><input type=\"text\" name=\"newnickname\" size=\"20\" placeholder=\"$I[optional]\"></td></tr>";
		echo '</table></td></tr></table></td></tr>';
		thr();
	}
	echo '<tr><td>'.submit($I['savechanges']).'</td></tr></table></form>';
	if($U['status']>1 && $U['status']<8){
		echo "<br><$H[form]>$H[commonform]".hidden('action', 'profile').hidden('do', 'delete').submit($I['deleteacc'], 'class="delbutton"').'</form>';
	}
	echo "<br><p>$I[changelang]";
	foreach($L as $lang=>$name){
		echo " <a href=\"$_SERVER[SCRIPT_NAME]?lang=$lang&amp;session=$U[session]&amp;action=controls\" target=\"controls\">$name</a>";
	}
	echo '</p></td></tr>';
	echo "<br>$H[backtochat]";
	print_end();
}

function send_controls(){
	global $H, $I, $U;
	print_start('controls');
	echo '<table class="center-table" style="border-spacing:0px;"><tr>';
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
	echo '<h1>'.sprintf($I['bye'], style_this($U['nickname'], $U['style']))."</h1>$H[backtologin]";
	print_end();
}

function send_colours(){
	global $H, $I;
	print_start('colours');
	echo "<h2>$I[colourtable]</h2><tt>";
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
	echo "</tt><$H[form]>$H[commonform]".hidden('action', 'profile').submit($I['backtoprofile'], ' class="backbutton"').'</form>';
	print_end();
}

function send_login(){
	global $H, $I, $L;
	$ga=(int) get_setting('guestaccess');
	if($ga===4){
		send_chat_disabled();
	}
	print_start('login');
	$englobal=(int) get_setting('englobalpass');
	echo '<h1>'.get_setting('chatname').'</h1>';
	echo "<$H[form] target=\"_parent\">$H[commonform]".hidden('action', 'login');
	if($englobal===1 && isSet($_POST['globalpass'])){
		echo hidden('globalpass', $_POST['globalpass']);
	}
	echo '<table class="center-table" style="border:2px solid;">';
	if($englobal!==1 || (isSet($_POST['globalpass']) && $_POST['globalpass']==get_setting('globalpass'))){
		echo "<tr><td class=\"left\">$I[nick]</td><td class=\"right\"><input type=\"text\" name=\"nick\" size=\"15\" autofocus></td></tr>";
		echo "<tr><td class=\"left\">$I[pass]</td><td class=\"right\"><input type=\"password\" name=\"pass\" size=\"15\"></td></tr>";
		send_captcha();
		if($ga!==0){
			if(get_setting('guestreg')!=0){
				echo "<tr><td class=\"left\">$I[regpass]</td><td class=\"right\"><input type=\"password\" name=\"regpass\" size=\"15\" placeholder=\"$I[optional]\"></td></tr>";
			}
			if($englobal===2){
				echo "<tr><td class=\"left\">$I[globalloginpass]</td><td class=\"right\"><input type=\"password\" name=\"globalpass\" size=\"15\"></td></tr>";
			}
			echo "<tr><td colspan=\"2\">$I[choosecol]<br><select class=\"center\" name=\"colour\"><option value=\"\">* $I[randomcol] *</option>";
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
		echo "<tr><td class=\"left\">$I[globalloginpass]</td><td class=\"right\"><input type=\"password\" name=\"globalpass\" size=\"15\" autofocus></td></tr>";
		if($ga===0){
			echo "<tr><td colspan=\"2\">$I[noguests]</td></tr>";
		}
		echo '<tr><td colspan="2">'.submit($I['enter']).'</td></tr></table></form>';
	}
	echo "<p>$I[changelang]";
	foreach($L as $lang=>$name){
		echo " <a href=\"$_SERVER[SCRIPT_NAME]?lang=$lang\">$name</a>";
	}
	echo "</p>$H[credit]";
	print_end();
}

function send_chat_disabled(){
	print_start('disabled');
	echo get_setting('disabletext');
	print_end();
}

function send_error($err){
	global $H, $I;
	print_start('error');
	echo "<div class=\"left\"><h2>$I[error]: $err</h2>$H[backtologin]</div>";
	print_end();
}

function send_fatal_error($err){
	global $H, $I;
	echo "<!DOCTYPE html><html><head>$H[meta_html]";
	echo "<title>$I[fatalerror]</title>";
	echo "<style type=\"text/css\">body{background-color:#000000;color:#FF0033;}</style>";
	echo '</head><body>';
	echo "<h2>$I[fatalerror]: $err</h2>";
	print_end();
}

function print_chatters(){
	global $I, $P, $U, $db;
	echo '<table style="border-spacing:0px;"><tr>';
	if($U['status']>=5 && get_setting('guestaccess')==3){
		$result=$db->query('SELECT COUNT(*) FROM ' . PREFIX . 'sessions WHERE entry=0 AND status=1;');
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
		echo "<th style=\"vertical-align:top;\">$I[members]:</th><td>&nbsp;</td><td style=\"vertical-align:top;\">".implode(' &nbsp; ', $M).'</td>';
		if(!empty($G)){
			echo '<td>&nbsp;&nbsp;</td>';
		}
	}
	if(!empty($G)){
		echo "<th style=\"vertical-align:top;\">$I[guests]:</th><td>&nbsp;</td><td style=\"vertical-align:top;\">".implode(' &nbsp; ', $G).'</td>';
	}
	echo '</tr></table>';
}

//  session management

function create_session($setup){
	global $I, $U, $db, $memcached;
	$U['nickname']=preg_replace('/\s+/', '', $_REQUEST['nick']);
	$U['passhash']=md5(sha1(md5($U['nickname'].$_REQUEST['pass'])));
	if(!check_member()){
		add_user_defaults();
	}
	$U['entry']=$U['lastpost']=time();
	if($setup){
		$U['incognito']=1;
	}
	$captcha=(int) get_setting('captcha');
	if($captcha!==0 && ($U['status']==1 || get_setting('dismemcaptcha')==0)){
		if(!isSet($_REQUEST['challenge'])){
			send_error($I['wrongcaptcha']);
		}
		if(!MEMCACHED){
			$stmt=$db->prepare('SELECT code FROM ' . PREFIX . 'captcha WHERE id=?;');
			$stmt->execute(array($_REQUEST['challenge']));
			$stmt->bindColumn(1, $code);
			if(!$stmt->fetch(PDO::FETCH_BOUND)){
				send_error($I['captchaexpire']);
			}
			$time=time();
			$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'captcha WHERE id=? OR time<(?-(SELECT value FROM ' . PREFIX . "settings WHERE setting='captchatime'));");
			$stmt->execute(array($_REQUEST['challenge'], $time));
		}else{
			if(!$code=$memcached->get(DBNAME . '-' . PREFIX . "captcha-$_REQUEST[challenge]")){
				send_error($I['captchaexpire']);
			}
			$memcached->delete(DBNAME . '-' . PREFIX . "captcha-$_REQUEST[challenge]");
		}
		if($_REQUEST['captcha']!==$code){
			if($captcha!==3 || strrev($_REQUEST['captcha'])!==$code){
				send_error($I['wrongcaptcha']);
			}
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
	global $I, $P, $U, $db;
	parse_sessions();
	$stmt=$db->prepare('SELECT * FROM ' . PREFIX . 'sessions WHERE nickname=?;');
	$stmt->execute(array($U['nickname']));
	if($temp=$stmt->fetch(PDO::FETCH_ASSOC)){
		if($U['passhash']===$temp['passhash']){
			$U=$temp;
			check_kicked();
			setcookie(COOKIENAME, $U['session']);
		}else{
			send_error("$I[userloggedin]<br>$I[wrongpass]");
		}
	}else{
		$sids=[];
		// create new session
		$stmt=$db->query('SELECT session FROM ' . PREFIX . 'sessions;');
		while($temp=$stmt->fetch(PDO::FETCH_ASSOC)){
			$sids[$temp['session']]=true;// collect all existing ids
		}
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
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'sessions (session, nickname, status, refresh, style, lastpost, passhash, boxwidth, boxheight, useragent, bgcolour, notesboxwidth, notesboxheight, entry, timestamps, embed, incognito, ip, nocache, tz, eninbox) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		$stmt->execute(array($U['session'], $U['nickname'], $U['status'], $U['refresh'], $U['style'], $U['lastpost'], $U['passhash'], $U['boxwidth'], $U['boxheight'], $useragent, $U['bgcolour'], $U['notesboxwidth'], $U['notesboxheight'], $U['entry'], $U['timestamps'], $U['embed'], $U['incognito'], $ip, $U['nocache'], $U['tz'], $U['eninbox']));
		setcookie(COOKIENAME, $U['session']);
		if($U['status']>=3 && !$U['incognito']){
			add_system_message(sprintf(get_setting('msgenter'), style_this($U['nickname'], $U['style'])));
		}
		$P[$U['nickname']]=[$U['nickname'], $U['style'], $U['status'], $U['nickname']];
	}
}

function approve_session(){
	global $db;
	if(isSet($_REQUEST['what'])){
		if($_REQUEST['what']==='allowchecked' && isSet($_REQUEST['csid'])){
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET entry=lastpost WHERE nickname=?;');
			foreach($_REQUEST['csid'] as $nick){
				$stmt->execute(array($nick));
			}
		}elseif($_REQUEST['what']==='allowall' && isSet($_REQUEST['alls'])){
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET entry=lastpost WHERE nickname=?;');
			foreach($_REQUEST['alls'] as $nick){
				$stmt->execute(array($nick));
			}
		}elseif($_REQUEST['what']==='denychecked' && isSet($_REQUEST['csid'])){
			$time=60*(get_setting('kickpenalty')-get_setting('guestexpire'))+time();
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET lastpost=?, status=0, kickmessage=? WHERE nickname=? AND status=1;');
			foreach($_REQUEST['csid'] as $nick){
				$stmt->execute(array($time, $_REQUEST['kickmessage'], $nick));
			}
		}elseif($_REQUEST['what']==='denyall' && isSet($_REQUEST['alls'])){
			$time=60*(get_setting('kickpenalty')-get_setting('guestexpire'))+time();
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET lastpost=?, status=0, kickmessage=? WHERE nickname=? AND status=1;');
			foreach($_REQUEST['alls'] as $nick){
				$stmt->execute(array($time, $_REQUEST['kickmessage'], $nick));
			}
		}
	}
}

function check_login(){
	global $I, $U, $db;
	$ga=(int) get_setting('guestaccess');
	if(isSet($_POST['session'])){
		$stmt=$db->prepare('SELECT * FROM ' . PREFIX . 'sessions WHERE session=?;');
		$stmt->execute(array($_POST['session']));
		if($U=$stmt->fetch(PDO::FETCH_ASSOC)){
			check_kicked();
			setcookie(COOKIENAME, $U['session']);
		}else{
			setcookie(COOKIENAME, false);
			send_error($I['expire']);

		}
	}elseif(get_setting('englobalpass')==1 && (!isSet($_POST['globalpass']) || $_POST['globalpass']!=get_setting('globalpass'))){
		send_error($I['wrongglobalpass']);
	}elseif(!isSet($_REQUEST['nick']) || !isSet($_REQUEST['pass'])){
		send_login();
	}else{
		if($ga===4){
			send_chat_disabled();
		}
		if(!empty($_REQUEST['regpass']) && $_REQUEST['regpass']!==$_REQUEST['pass']){
			send_error($I['noconfirm']);
		}
		create_session(false);
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
			$stmt->execute(array($U['session']));
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
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'sessions WHERE session=?;');
	$stmt->execute(array($U['session']));
	if($U['status']==1){
		$stmt=$db->prepare('UPDATE ' . PREFIX . "inbox SET poster='' WHERE poster=?;");
		$stmt->execute(array($U['nickname']));
		$stmt=$db->prepare('UPDATE ' . PREFIX . "messages SET poster='' WHERE poster=? AND poststatus=9;");
		$stmt->execute(array($U['nickname']));
		$stmt=$db->prepare('UPDATE ' . PREFIX . "messages SET recipient='' WHERE recipient=? AND poststatus=9;");
		$stmt->execute(array($U['nickname']));
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'ignored WHERE ign=? OR ignby=?;');
		$stmt->execute(array($U['nickname'], $U['nickname']));
		$db->exec('DELETE FROM ' . PREFIX . "messages WHERE poster='' AND recipient='' AND poststatus=9;");
	}elseif($U['status']>=3 && !$U['incognito']){
		add_system_message(sprintf(get_setting('msgexit'), style_this($U['nickname'], $U['style'])));
	}
}

function kick_chatter($names, $mes, $purge){
	global $P, $U, $db;
	$lonick='';
	$time=60*(get_setting('kickpenalty')-get_setting('guestexpire'))+time();
	$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET lastpost=?, status=0, kickmessage=? WHERE nickname=? AND status!=0;');
	$i=0;
	foreach($names as $name){
		foreach($P as $temp){
			if(($temp[0]===$U['nickname'] && $U['nickname']===$name) || ($U['status']>$temp[2] && (($temp[0]===$name && $temp[2]>0) || ($name==='&' && $temp[2]==1)))){
				$stmt->execute(array($time, $mes, $name));
				if($purge){
					del_all_messages($name, 10, 0);
				}
				$lonick.=style_this($name, $temp[1]).', ';
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
	global $P, $U, $db;
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'sessions WHERE nickname=? AND status<? AND status!=0;');
	$stmt1=$db->prepare('UPDATE ' . PREFIX . "messages SET poster='' WHERE poster=? AND poststatus=9;");
	$stmt2=$db->prepare('UPDATE ' . PREFIX . "messages SET recipient='' WHERE recipient=? AND poststatus=9;");
	$stmt3=$db->prepare('DELETE FROM ' . PREFIX . 'ignored WHERE ign=? OR ignby=?;');
	$stmt4=$db->prepare('UPDATE ' . PREFIX . "inbox SET poster='' WHERE poster=?;");
	foreach($names as $name){
		foreach($P as $temp){
			if($temp[0]===$name || ($name==='&' && $temp[2]==1)){
				$stmt->execute(array($name, $U['status']));
				if($temp[2]==1){
					$stmt1->execute(array($name));
					$stmt2->execute(array($name));
					$stmt3->execute(array($name, $name));
					$stmt4->execute(array($name));
				}
				unset($P[$name]);
			}
		}
	}
	$db->exec('DELETE FROM ' . PREFIX . "messages WHERE poster='' AND recipient='' AND poststatus=9;");
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
	if(!isSet($U['session'])){
		setcookie(COOKIENAME, false);
		send_error($I['expire']);
	}
}

function check_kicked(){
	global $I, $U;
	if($U['status']==0){
		setcookie(COOKIENAME, false);
		send_error("$I[kicked]<br>$U[kickmessage]");
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
	global $P, $U, $countmods, $db;
	// delete old sessions
	$time=time();
	$result=$db->prepare('SELECT nickname, status FROM ' . PREFIX . 'sessions WHERE (status<=2 AND lastpost<(?-60*(SELECT value FROM ' . PREFIX . "settings WHERE setting='guestexpire'))) OR (status>2 AND lastpost<(?-60*(SELECT value FROM " . PREFIX . "settings WHERE setting='memberexpire')));");
	$result->execute(array($time, $time));
	if($tmp=$result->fetchAll(PDO::FETCH_ASSOC)){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'sessions WHERE nickname=?;');
		$stmt1=$db->prepare('UPDATE ' . PREFIX . "messages SET poster='' WHERE poster=? AND poststatus=9;");
		$stmt2=$db->prepare('UPDATE ' . PREFIX . "messages SET recipient='' WHERE recipient=? AND poststatus=9;");
		$stmt3=$db->prepare('DELETE FROM ' . PREFIX . 'ignored WHERE ign=? OR ignby=?;');
		$stmt4=$db->prepare('UPDATE ' . PREFIX . "inbox SET poster='' WHERE poster=?;");
		foreach($tmp as $temp){
			$stmt->execute(array($temp['nickname']));
			if($temp['status']<=1){
				$stmt1->execute(array($temp['nickname']));
				$stmt2->execute(array($temp['nickname']));
				$stmt3->execute(array($temp['nickname'], $temp['nickname']));
				$stmt4->execute(array($temp['nickname']));
			}
		}
		$db->exec('DELETE FROM ' . PREFIX . "messages WHERE poster='' AND recipient='' AND poststatus=9;");
	}
	// look for our session
	if(isSet($_REQUEST['session'])){
		$stmt=$db->prepare('SELECT * FROM ' . PREFIX . 'sessions WHERE session=?;');
		$stmt->execute(array($_REQUEST['session']));
		if($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
			$U=$tmp;
		}
	}
	// load other sessions
	$countmods=0;
	$P=array();
	if(isSet($U['nickname'])){
		$stmt=$db->prepare('SELECT nickname, style, status, incognito FROM ' . PREFIX . 'sessions WHERE entry!=0 AND status>0 AND nickname NOT IN (SELECT ign FROM '. PREFIX . 'ignored WHERE ignby=?) AND nickname NOT IN (SELECT ignby FROM '. PREFIX . 'ignored WHERE ign=?) ORDER BY status DESC, lastpost DESC;');
		$stmt->execute([$U['nickname'], $U['nickname']]);
	}else{
		$stmt=$db->query('SELECT nickname, style, status, incognito FROM ' . PREFIX . 'sessions WHERE entry!=0 AND status>0 ORDER BY status DESC, lastpost DESC;');
	}
	while($temp=$stmt->fetch(PDO::FETCH_ASSOC)){
		if(!$temp['incognito']){
			$P[$temp['nickname']]=[$temp['nickname'], $temp['style'], $temp['status'], $temp['nickname']];
		}
		if($temp['status']>=5){
			++$countmods;
		}
	}
}

//  member handling

function check_member(){
	global $I, $U, $db;
	$stmt=$db->prepare('SELECT * FROM ' . PREFIX . 'members WHERE nickname=?;');
	$stmt->execute(array($U['nickname']));
	if($temp=$stmt->fetch(PDO::FETCH_ASSOC)){
		if($temp['passhash']===$U['passhash']){
			$U=$temp;
			$time=time();
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET lastlogin=? WHERE nickname=?;');
			$stmt->execute(array($time, $U['nickname']));
			return true;
		}else{
			send_error("$I[regednick]<br>$I[wrongpass]");
		}
	}
	return false;
}

function read_members(){
	global $A, $db;
	$result=$db->query('SELECT * FROM ' . PREFIX . 'members;');
	while($temp=$result->fetch(PDO::FETCH_ASSOC)){
		$A[$temp['nickname']]=[$temp['nickname'], $temp['style'], $temp['status'], $temp['nickname']];
	}
}

function delete_account(){
	global $U, $db;
	if($U['status']<8){
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET status=1 WHERE nickname=?;');
		$stmt->execute(array($U['nickname']));
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'members WHERE nickname=?;');
		$stmt->execute(array($U['nickname']));
		$U['status']=1;
	}
}

function register_guest($status, $nick){
	global $A, $I, $P, $U, $db;
	if(!isSet($P[$nick])){
		return sprintf($I['cantreg'], $nick);
	}
	read_members();
	if(isSet($A[$nick])){
		return sprintf($I['alreadyreged'], $nick);
	}
	$stmt=$db->prepare('SELECT * FROM ' . PREFIX . 'sessions WHERE nickname=? AND status=1;');
	$stmt->execute(array($nick));
	if($reg=$stmt->fetch(PDO::FETCH_ASSOC)){
		$reg['status']=$status;
		$P[$nick][2]=$status;
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET status=? WHERE session=?;');
		$stmt->execute(array($reg['status'], $reg['session']));
	}else{
		return sprintf($I['cantreg'], $nick);
	}
	$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, boxwidth, boxheight, regedby, timestamps, embed, style, incognito, nocache, tz, eninbox) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
	$stmt->execute(array($reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['bgcolour'], $reg['boxwidth'], $reg['boxheight'], $U['nickname'], $reg['timestamps'], $reg['embed'], $reg['style'], $reg['incognito'], $reg['nocache'], $reg['tz'], $reg['eninbox']));
	if($reg['status']==3){
		add_system_message(sprintf(get_setting('msgmemreg'), style_this($reg['nickname'], $reg['style'])));
	}else{
		add_system_message(sprintf(get_setting('msgsureg'), style_this($reg['nickname'], $reg['style'])));
	}
	return sprintf($I['successreg'], $reg['nickname']);
}

function register_new($nick, $pass){
	global $A, $I, $P, $U, $db;
	$nick=preg_replace('/\s+/', '', $nick);
	if(empty($nick)){
		return '';
	}elseif(isSet($P[$nick])){
		return sprintf($I['cantreg'], $nick);
	}elseif(!valid_nick($nick)){
		return sprintf($I['invalnick'], get_setting('maxname'));
	}elseif(!valid_pass($pass)){
		return sprintf($I['invalpass'], get_setting('minpass'));
	}
	read_members();
	if(isSet($A[$nick])){
		return sprintf($I['alreadyreged'], $nick);
	}
	$reg=array(
		'nickname'	=>$nick,
		'passhash'	=>md5(sha1(md5($nick.$pass))),
		'status'	=>3,
		'refresh'	=>get_setting('defaultrefresh'),
		'bgcolour'	=>get_setting('colbg'),
		'regedby'	=>$U['nickname'],
		'timestamps'	=>get_setting('timestamps'),
		'style'		=>'color:#'.get_setting('coltxt').';',
		'embed'		=>1,
		'incognito'	=>0,
		'nocache'	=>0,
		'tz'		=>get_setting('defaulttz'),
		'eninbox'	=>0
	);
	$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, regedby, timestamps, style, embed, incognito, nocache, tz, eninbox) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
	$stmt->execute(array($reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['bgcolour'], $reg['regedby'], $reg['timestamps'], $reg['style'], $reg['embed'], $reg['incognito'], $reg['nocache'], $reg['tz'], $reg['eninbox']));
	return sprintf($I['successreg'], $reg['nickname']);
}

function change_status($nick, $status){
	global $I, $P, $U, $db;
	if(empty($nick)){
		return '';
	}elseif($U['status']<=$status || !preg_match('/^[023567\-]$/', $status)){
		return sprintf($I['cantchgstat'], $nick);
	}
	$stmt=$db->prepare('SELECT incognito FROM ' . PREFIX . 'members WHERE nickname=? AND status<?;');
	$stmt->execute(array($nick, $U['status']));
	if(!$old=$stmt->fetch(PDO::FETCH_NUM)){
		return sprintf($I['cantchgstat'], $nick);
	}
	if($_REQUEST['set']==='-'){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'inbox WHERE recipient=?;');
		$stmt->execute(array($nick));
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'members WHERE nickname=?;');
		$stmt->execute(array($nick));
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET status=1, incognito=0 WHERE nickname=?;');
		$stmt->execute(array($nick));
		if(isSet($P[$nick])){
			$P[$nick][2]=1;
		}
		return sprintf($I['succdel'], $nick);
	}else{
		if($status<5){
			$old[0]=0;
		}
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET status=?, incognito=? WHERE nickname=?;');
		$stmt->execute(array($status, $old[0], $nick));
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET status=?, incognito=? WHERE nickname=?;');
		$stmt->execute(array($status, $old[0], $nick));
		if(isSet($P[$nick])){
			$P[$nick][2]=$status;
		}
		return sprintf($I['succchg'], $nick);
	}
}

function passreset($nick, $pass){
	global $I, $U, $db;
	if(empty($nick)){
		return '';
	}
	$stmt=$db->prepare('SELECT * FROM ' . PREFIX . 'members WHERE nickname=? AND status<?;');
	$stmt->execute(array($nick, $U['status']));
	if($stmt->fetch(PDO::FETCH_ASSOC)){
		$passhash=md5(sha1(md5($nick.$pass)));
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET passhash=? WHERE nickname=?;');
		$stmt->execute(array($passhash, $nick));
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET passhash=? WHERE nickname=?;');
		$stmt->execute(array($passhash, $nick));
		return sprintf($I['succpassreset'], $nick);
	}else{
		return sprintf($I['cantresetpass'], $nick);
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
	if(isSet($_REQUEST['nocache'])){
		$U['nocache']=1;
	}else{
		$U['nocache']=0;
	}
	if(isSet($_REQUEST['tz'])){
		settype($_REQUEST['tz'], 'int');
		if($_REQUEST['tz']>=-12 && $_REQUEST['tz']<=14){
			$U['tz']=$_REQUEST['tz'];
		}
	}
	if(isSet($_REQUEST['eninbox']) && $_REQUEST['eninbox']>=0 && $_REQUEST['eninbox']<=5){
		$U['eninbox']=$_REQUEST['eninbox'];
	}
}

function save_profile(){
	global $I, $P, $U, $db;
	amend_profile();
	$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET refresh=?, style=?, boxwidth=?, boxheight=?, bgcolour=?, notesboxwidth=?, notesboxheight=?, timestamps=?, embed=?, incognito=?, nocache=?, tz=?, eninbox=? WHERE session=?;');
	$stmt->execute(array($U['refresh'], $U['style'], $U['boxwidth'], $U['boxheight'], $U['bgcolour'], $U['notesboxwidth'], $U['notesboxheight'], $U['timestamps'], $U['embed'], $U['incognito'], $U['nocache'], $U['tz'], $U['eninbox'], $U['session']));
	if($U['status']>=2){
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET refresh=?, bgcolour=?, boxwidth=?, boxheight=?, notesboxwidth=?, notesboxheight=?, timestamps=?, embed=?, incognito=?, style=?, nocache=?, tz=?, eninbox=? WHERE nickname=?;');
		$stmt->execute(array($U['refresh'], $U['bgcolour'], $U['boxwidth'], $U['boxheight'], $U['notesboxwidth'], $U['notesboxheight'], $U['timestamps'], $U['embed'], $U['incognito'], $U['style'], $U['nocache'], $U['tz'], $U['eninbox'], $U['nickname']));
	}
	if(!empty($_REQUEST['unignore'])){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'ignored WHERE ign=? AND ignby=?;');
		$stmt->execute(array($_REQUEST['unignore'], $U['nickname']));
	}
	if(!empty($_REQUEST['ignore'])){
		if($_REQUEST['ignore']!==$U['nickname'] && $P[$_REQUEST['ignore']][2]<=$U['status']){
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'ignored (ign, ignby) VALUES (?, ?);');
			$stmt->execute(array($_REQUEST['ignore'], $U['nickname']));
		}
	}
	if($U['status']>1 && !empty($_REQUEST['newpass'])){
		if(!valid_pass($_REQUEST['newpass'])){
			return sprintf($I['invalpass'], get_setting('minpass'));
		}
		if(!isSet($_REQUEST['oldpass'])){
			$_REQUEST['oldpass']='';
		}
		if(!isSet($_REQUEST['confirmpass'])){
			$_REQUEST['confirmpass']='';
		}
		if($_REQUEST['newpass']!==$_REQUEST['confirmpass']){
			return $I['noconfirm'];
		}else{
			$U['oldhash']=md5(sha1(md5($U['nickname'].$_REQUEST['oldpass'])));
			$U['newhash']=md5(sha1(md5($U['nickname'].$_REQUEST['newpass'])));
		}
		if($U['passhash']!==$U['oldhash']){
			return $I['wrongpass'];
		}
		$U['passhash']=$U['newhash'];
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET passhash=? WHERE session=?;');
		$stmt->execute(array($U['passhash'], $U['session']));
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET passhash=? WHERE nickname=?;');
		$stmt->execute(array($U['passhash'], $U['nickname']));
		if(!empty($_REQUEST['newnickname'])){
			$msg=set_new_nickname();
			if($msg!==''){
				return $msg;
			}
		}
	}
	return $I['succprofile'];
}

function set_new_nickname(){
	global $I, $U, $db;
	if(!valid_nick($_REQUEST['newnickname'])){
		return sprintf($I['invalnick'], get_setting('maxname'));
	}
	$U['passhash']=md5(sha1(md5($_REQUEST['newnickname'].$_REQUEST['newpass'])));
	$stmt=$db->prepare('SELECT id FROM ' . PREFIX . 'sessions WHERE nickname=? UNION SELECT id FROM ' . PREFIX . 'members WHERE nickname=?;');
	$stmt->execute(array($_REQUEST['newnickname'], $_REQUEST['newnickname']));
	if($stmt->fetch(PDO::FETCH_NUM)){
		return $I['nicknametaken'];
	}else{
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET nickname=?, passhash=? WHERE nickname=?;');
		$stmt->execute(array($_REQUEST['newnickname'], $U['passhash'], $U['nickname']));
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET nickname=?, passhash=? WHERE nickname=?;');
		$stmt->execute(array($_REQUEST['newnickname'], $U['passhash'], $U['nickname']));
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'messages SET poster=? WHERE poster=?;');
		$stmt->execute(array($_REQUEST['newnickname'], $U['nickname']));
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'messages SET recipient=? WHERE recipient=?;');
		$stmt->execute(array($_REQUEST['newnickname'], $U['nickname']));
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'ignored SET ignby=? WHERE ignby=?;');
		$stmt->execute(array($_REQUEST['newnickname'], $U['nickname']));
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'ignored SET ign=? WHERE ign=?;');
		$stmt->execute(array($_REQUEST['newnickname'], $U['nickname']));
		$U['nickname']=$_REQUEST['newnickname'];
	}
	return '';
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
	$U['nocache']=0;
	$U['tz']=get_setting('defaulttz');
	$U['eninbox']=0;
}

// message handling

function validate_input(){
	global $P, $U, $db;
	$inbox=false;
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
		if(get_setting('disablepm')){
			return;
		}
		$stmt=$db->prepare('SELECT nickname, style, status FROM ' . PREFIX . 'members WHERE nickname=? AND eninbox!=0 AND eninbox<=? AND nickname NOT IN (SELECT nickname FROM ' . PREFIX . 'sessions WHERE incognito=0) AND nickname NOT IN (SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=?) AND nickname NOT IN (SELECT ignby FROM ' . PREFIX . 'ignored WHERE ign=?);');
		$stmt->execute(array($_REQUEST['sendto'], $U['status'], $U['nickname'], $U['nickname']));
		if($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
			$P[$tmp['nickname']]=[$tmp['nickname'], $tmp['style'], $tmp['status'], $tmp['nickname']];
			$inbox=true;
		}
		if(isSet($P[$_REQUEST['sendto']])){
			$U['recipient']=$P[$_REQUEST['sendto']][0];
			$U['displayrecp']=style_this($U['recipient'], $P[$_REQUEST['sendto']][1]);
			$U['poststatus']='9';
			$U['delstatus']='9';
			$U['displaysend']=sprintf(get_setting('msgsendprv'), style_this($U['nickname'], $U['style']), $U['displayrecp']);
		}
		if(empty($U['recipient'])){// nick left already or ignores us
			$U['message']='';
			$U['rejected']='';
			return;
		}
	}
	apply_filter();
	create_hotlinks();
	apply_linkfilter();
	if(add_message()){
		$U['lastpost']=time();
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET lastpost=?, postid=? WHERE session=?;');
		$stmt->execute(array($U['lastpost'], $_REQUEST['postid'], $U['session']));
		if($inbox){
			$message=array(
				'postdate'	=>time(),
				'poster'	=>$U['nickname'],
				'recipient'	=>$U['recipient'],
				'text'		=>"<span class=\"usermsg\">$U[displaysend]".style_this($U['message'], $U['style']).'</span>'
			);
			if(MSGENCRYPTED){
				$message['text']=openssl_encrypt($message['text'], 'aes-256-cbc', ENCRYPTKEY, 0, '1234567890123456');
			}
			$stmt=$db->prepare('SELECT id FROM ' . PREFIX . "messages WHERE poster=? ORDER BY id DESC LIMIT 1");
			$stmt->execute(array($U['nickname']));
			if($id=$stmt->fetch(PDO::FETCH_NUM)){
				$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'inbox (postdate, postid, poster, recipient, text) VALUES(?, ?, ?, ?, ?)');
				$stmt->execute(array($message['postdate'], $id[0], $message['poster'], $message['recipient'], $message['text']));
			}
		}
	}
}

function apply_filter(){
	global $I, $U;
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
			return style_this($matched[0], $A[$matched[1]][1]);
		}
		foreach($A as $user){
			if(strtolower($user[0])===$nick){
				return style_this($matched[0], $user[1]);
			}
		}
		return "$matched[0]";
	}, $U['message']);
	$U['message']=str_replace('<br>', "\n", $U['message']);
	$filters=get_filters();
	foreach($filters as $filter){
		if($U['poststatus']!==9){
			$U['message']=preg_replace("/$filter[match]/i", $filter['replace'], $U['message'], -1, $count);
		}elseif(!$filter['allowinpm']){
			$U['message']=preg_replace("/$filter[match]/i", $filter['replace'], $U['message'], -1, $count);
		}
		if(isSet($count) && $count>0 && $filter['kick']){
			kick_chatter(array($U['nickname']), '', false);
			setcookie(COOKIENAME, false);
			send_error("$I[kicked]");
		}
	}
	$U['message']=str_replace("\n", '<br>', $U['message']);
}

function apply_linkfilter(){
	global $U;
	$filters=get_linkfilters();
	foreach($filters as $filter){
		$U['message']=preg_replace_callback("/<a href=\"([^\"]+)\" target=\"_blank\">(.*?(?=<\/a>))<\/a>/i",
			function ($matched) use(&$filter){
				return "<a href=\"$matched[1]\" target=\"_blank\">".preg_replace("/$filter[match]/i", $filter['replace'], $matched[2]).'</a>';
			}
		, $U['message']);
	}
	$redirect=get_setting('redirect');
	if(get_setting('imgembed')){
		$U['message']=preg_replace_callback('/\[img\]\s?<a href="([^"]+)" target="_blank">(.*?(?=<\/a>))<\/a>/i',
			function ($matched){
				return str_ireplace('[/img]', '', "<br><a href=\"$matched[1]\" target=\"_blank\"><img src=\"$matched[1]\"></a><br>");
			}
		, $U['message']);
	}
	if(empty($redirect)){
		$redirect="$_SERVER[SCRIPT_NAME]?action=redirect&amp;url=";
	}
	if(get_setting('forceredirect')){
		$U['message']=preg_replace_callback('/<a href="([^"]+)" target="_blank">(.*?(?=<\/a>))<\/a>/',
			function ($matched) use($redirect){
				return "<a href=\"$redirect".rawurlencode($matched[1])."\" target=\"_blank\">$matched[2]</a>";
			}
		, $U['message']);
	}elseif(preg_match_all('/<a href="([^"]+)" target="_blank">(.*?(?=<\/a>))<\/a>/', $U['message'], $matches)){
		foreach($matches[1] as $match){
			if(!preg_match('~^http(s)?://~', $match)){
				$U['message']=preg_replace_callback('/<a href="('.str_replace('/', '\/', $match).')\" target=\"_blank\">(.*?(?=<\/a>))<\/a>/',
					function ($matched) use($redirect){
						return "<a href=\"$redirect".rawurlencode($matched[1])."\" target=\"_blank\">$matched[2]</a>";
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
	$U['message']=preg_replace('~(\w+://[^\s<>]+)~i', "<<$1>>", $U['message']);
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
		'delstatus'	=>4
	);
	write_message($sysmessage);
}

function write_message($message){
	global $I, $db;
	if(MSGENCRYPTED){
		if(!extension_loaded('openssl')){
			send_fatal_error($I['opensslextrequired']);
		}
		$message['text']=openssl_encrypt($message['text'], 'aes-256-cbc', ENCRYPTKEY, 0, '1234567890123456');
	}
	$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'messages (postdate, poststatus, poster, recipient, text, delstatus) VALUES (?, ?, ?, ?, ?, ?);');
	$stmt->execute(array($message['postdate'], $message['poststatus'], $message['poster'], $message['recipient'], $message['text'], $message['delstatus']));
	$limit=get_setting('keeplimit')*get_setting('messagelimit');
	$stmt=$db->query('SELECT id FROM ' . PREFIX . "messages ORDER BY id DESC LIMIT 1 OFFSET $limit");
	if($id=$stmt->fetch(PDO::FETCH_NUM)){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE id<=?;');
		$stmt->execute(array($id[0]));
	}
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
	$msg=get_setting('msgclean');
	add_system_message(sprintf($msg, get_setting('chatname')));
}

function clean_selected($status, $nick){
	global $db;
	if(isSet($_REQUEST['mid'])){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE id=? AND (poster=? OR recipient=? OR delstatus<?);');
		foreach($_REQUEST['mid'] as $mid){
			$stmt->execute(array($mid, $nick, $nick, $status));
		}
	}
}

function clean_inbox_selected(){
	global $U, $db;
	if(isSet($_REQUEST['mid'])){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'inbox WHERE id=? AND recipient=?;');
		foreach($_REQUEST['mid'] as $mid){
			$stmt->execute(array($mid, $U['nickname']));
		}
	}
}

function del_all_messages($nick, $status, $entry){
	global $U, $db;
	if($nick===$U['nickname']) $status=10;
	if($U['status']>1){
		$entry=0;
	}
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE poster=? AND delstatus<? AND postdate>?;');
	$stmt->execute(array($nick, $status, $entry));
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'inbox WHERE poster=?;');
	$stmt->execute(array($nick));
}

function del_last_message(){
	global $U, $db;
	if($U['status']>1){
		$entry=0;
	}else{
		$entry=$U['entry'];
	}
	$stmt=$db->prepare('SELECT id FROM ' . PREFIX . 'messages WHERE poster=? AND postdate>? ORDER BY id DESC LIMIT 1;');
	$stmt->execute(array($U['nickname'], $entry));
	if($id=$stmt->fetch(PDO::FETCH_NUM)){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE id=?;');
		$stmt->execute(array($id[0]));
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'inbox WHERE postid=?;');
		$stmt->execute(array($id[0]));
	}
}

function print_messages($delstatus=''){
	global $I, $U, $db;
	$dateformat=get_setting('dateformat');
	$tz=3600*$U['tz'];
	$messagelimit=(int) get_setting('messagelimit');
	if(!isSet($_COOKIE[COOKIENAME]) && get_setting('forceredirect')==0){
		$injectRedirect=true;
		$redirect=get_setting('redirect');
		if(empty($redirect)){
			$redirect="$_SERVER[SCRIPT_NAME]?action=redirect&amp;url=";
		}
	}else{
		$injectRedirect=false;
		$redirect='';
	}
	if(get_setting('imgembed') && (!$U['embed'] || !isSet($_COOKIE[COOKIENAME]))){
		$removeEmbed=true;
	}else{
		$removeEmbed=false;
	}
	if($U['timestamps'] && !empty($dateformat)){
		$timestamps=true;
	}else{
		$timestamps=false;
	}
	if(MSGENCRYPTED){
		if(!extension_loaded('openssl')){
			send_fatal_error($I['opensslextrequired']);
		}
	}
	$expire=time()-60*get_setting('messageexpire');
	$db->exec('DELETE FROM ' . PREFIX . 'messages WHERE id IN (SELECT * FROM (SELECT id FROM ' . PREFIX . "messages WHERE postdate<$expire) AS t);");
	if(!empty($delstatus)){
		$stmt=$db->prepare('SELECT postdate, id, text FROM ' . PREFIX . 'messages WHERE '.
		'(id IN (SELECT * FROM (SELECT id FROM ' . PREFIX . "messages WHERE poststatus=1 ORDER BY id DESC LIMIT $messagelimit) AS t) ".
		'OR (poststatus>1 AND (poststatus<? OR poster=? OR recipient=?) ) ) AND (poster=? OR recipient=? OR delstatus<?) ORDER BY id DESC;');
		$stmt->execute(array($U['status'], $U['nickname'], $U['nickname'], $U['nickname'], $U['nickname'], $delstatus));
		while($message=$stmt->fetch(PDO::FETCH_ASSOC)){
			prepare_message_print($message, $injectRedirect, $redirect, $removeEmbed);
			echo "<div class=\"msg\"><input type=\"checkbox\" name=\"mid[]\" id=\"$message[id]\" value=\"$message[id]\"><label for=\"$message[id]\">";
			if($timestamps){
				echo ' <small>'.date($dateformat, $message['postdate']+$tz).' - </small>';
			}
			echo " $message[text]</label></div>";
		}
	}else{
		if(!isSet($_REQUEST['id'])){
			$_REQUEST['id']=0;
		}
		$stmt=$db->prepare('SELECT id, postdate, text FROM ' . PREFIX . 'messages WHERE ('.
		'id IN (SELECT * FROM (SELECT id FROM ' . PREFIX . "messages WHERE poststatus=1 ORDER BY id DESC LIMIT $messagelimit) AS t) ".
		'OR (poststatus>1 AND poststatus<=?) '.
		'OR (poststatus=9 AND ( (poster=? AND recipient NOT IN (SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=?) ) OR recipient=?) )'.
		') AND poster NOT IN (SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=?) AND id>? ORDER BY id DESC;');
		$stmt->execute(array($U['status'], $U['nickname'], $U['nickname'], $U['nickname'], $U['nickname'], $_REQUEST['id']));
		while($message=$stmt->fetch(PDO::FETCH_ASSOC)){
			prepare_message_print($message, $injectRedirect, $redirect, $removeEmbed);
			echo '<div class="msg">';
			if($timestamps){
				echo '<small>'.date($dateformat, $message['postdate']+$tz).' - </small>';
			}
			echo "$message[text]</div>";
			if($_REQUEST['id']<$message['id']){
				$_REQUEST['id']=$message['id'];
			}
		}
	}
}

function prepare_message_print(&$message, $injectRedirect, $redirect, $removeEmbed){
	if(MSGENCRYPTED){
		$message['text']=openssl_decrypt($message['text'], 'aes-256-cbc', ENCRYPTKEY, 0, '1234567890123456');
	}
	if($injectRedirect){
		$message['text']=preg_replace_callback('/<a href="([^"]+)" target="_blank">(.*?(?=<\/a>))<\/a>/',
			function ($matched) use($redirect) {
				return "<a href=\"$redirect".rawurlencode($matched[1])."\" target=\"_blank\">$matched[2]</a>";
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
}

// this and that

function sort_names(&$names){
	$keys=[];
	foreach($names as $v){
		$keys[]=$v[3];
	}
	array_multisort(array_map('strtolower', $keys), SORT_ASC, SORT_STRING, $names);
}

function send_headers(){
	header('Content-Type: text/html; charset=UTF-8');
	header('Pragma: no-cache');
	header('Cache-Control: no-cache');
	header('Expires: 0');
	if($_SERVER['REQUEST_METHOD']==='HEAD'){
		exit; // headers sent, no further processing needed
	}
}

function save_setup(){
	global $C, $db;
	foreach($C['msg_settings'] as $setting){
		$_REQUEST[$setting]=htmlspecialchars($_REQUEST[$setting]);
	}
	foreach($C['number_settings'] as $setting){
		settype($_REQUEST[$setting], 'int');
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
	settype($_REQUEST['defaulttz'], 'int');
	if($_REQUEST['defaulttz']<-12 || $_REQUEST['defaulttz']>14){
		unset($_REQUEST['defaulttz']);
	}
	$_REQUEST['rulestxt']=preg_replace("/(\r?\n|\r\n?)/", '<br>', $_REQUEST['rulestxt']);
	$_REQUEST['chatname']=htmlspecialchars($_REQUEST['chatname']);
	$_REQUEST['redirect']=htmlspecialchars($_REQUEST['redirect']);
	$_REQUEST['css']=htmlspecialchars($_REQUEST['css']);
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
		if(isSet($_REQUEST[$setting])){
			update_setting($setting, $_REQUEST[$setting]);
		}
	}
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
	global $db;
	return @$db->query('SELECT * FROM ' . PREFIX . 'settings LIMIT 1;');
}

function destroy_chat(){
	global $C, $H, $I, $db, $language;
	setcookie(COOKIENAME, false);
	print_start('destory');
	$db->exec('DROP TABLE ' . PREFIX . 'captcha;');
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
	}
	echo "<h2>$I[destroyed]</h2><br><br><br>";
	echo "<$H[form]>".hidden('lang', $language).hidden('action', 'setup').submit($I['init'])."</form>$H[credit]";
	print_end();
}

function init_chat(){
	global $H, $I, $db;
	$suwrite='';
	if(check_init()){
		$suwrite=$I['initdbexist'];
		$result=$db->query('SELECT * FROM ' . PREFIX . 'members WHERE status=8;');
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
		if(DBDRIVER===0){//MySQL
			$db->exec('CREATE TABLE ' . PREFIX . "captcha (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, time integer unsigned NOT NULL, code char(5) NOT NULL) ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
			$db->exec('CREATE TABLE ' . PREFIX . "filter (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, filtermatch varchar(255) NOT NULL, filterreplace varchar(20000) NOT NULL, allowinpm smallint NOT NULL, regex smallint NOT NULL, kick smallint NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
			$db->exec('CREATE TABLE ' . PREFIX . "ignored (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, ign varchar(50) NOT NULL, ignby varchar(50) NOT NULL, INDEX(ign), INDEX(ignby)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
			$db->exec('CREATE TABLE ' . PREFIX . "inbox (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, postid integer unsigned NOT NULL, postdate integer unsigned NOT NULL, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text varchar(20000) NOT NULL, INDEX(postid), INDEX(poster), INDEX(recipient)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
			$db->exec('CREATE TABLE ' . PREFIX . "linkfilter (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, filtermatch varchar(255) NOT NULL, filterreplace varchar(255) NOT NULL, regex smallint NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
			$db->exec('CREATE TABLE ' . PREFIX . "members (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, nickname varchar(50) NOT NULL UNIQUE, passhash char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, status smallint unsigned NOT NULL, refresh smallint unsigned NOT NULL, bgcolour char(6) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, boxwidth smallint unsigned NOT NULL DEFAULT 40, boxheight smallint unsigned NOT NULL DEFAULT 3, notesboxheight smallint unsigned NOT NULL DEFAULT 30, notesboxwidth smallint unsigned NOT NULL DEFAULT 80, regedby varchar(50) NOT NULL, lastlogin integer unsigned NOT NULL, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, style varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, nocache smallint NOT NULL, tz smallint NOT NULL, eninbox smallint NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
			$db->exec('CREATE TABLE ' . PREFIX . "messages (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, postdate integer unsigned NOT NULL, poststatus smallint unsigned NOT NULL, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text varchar(20000) NOT NULL, delstatus smallint unsigned NOT NULL, INDEX(poster), INDEX(recipient), INDEX(postdate), INDEX(poststatus)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
			$db->exec('CREATE TABLE ' . PREFIX . "notes (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, type char(5) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, lastedited integer unsigned NOT NULL, editedby varchar(50) NOT NULL, text varchar(20000) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
			$db->exec('CREATE TABLE ' . PREFIX . "sessions (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, session char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL UNIQUE, nickname varchar(50) NOT NULL UNIQUE, status smallint unsigned NOT NULL, refresh smallint unsigned NOT NULL, style varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, lastpost integer unsigned NOT NULL, passhash char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, postid char(6) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '000000', boxwidth smallint unsigned NOT NULL DEFAULT 40, boxheight smallint unsigned NOT NULL DEFAULT 3, useragent varchar(255) NOT NULL, kickmessage varchar(255) NOT NULL, bgcolour char(6) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, notesboxheight smallint unsigned NOT NULL DEFAULT 30, notesboxwidth smallint unsigned NOT NULL DEFAULT 80, entry integer NOT NULL, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL DEFAULT 0, ip varchar(45) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, nocache smallint NOT NULL, tz smallint NOT NULL, eninbox smallint NOT NULL, INDEX(status) USING BTREE, INDEX(lastpost) USING BTREE, INDEX(incognito) USING BTREE) ENGINE=MEMORY DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
			$db->exec('CREATE TABLE ' . PREFIX . "settings (setting varchar(50) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL PRIMARY KEY, value varchar(20000) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
		}else{
			if(DBDRIVER===1){//PostgreSQL
				$primary='serial PRIMARY KEY';
			}else{//SQLite
				$primary='integer PRIMARY KEY';
			}
			$db->exec('CREATE TABLE ' . PREFIX . "captcha (id $primary, time integer NOT NULL, code char(5) NOT NULL);");
			$db->exec('CREATE TABLE ' . PREFIX . "filter (id $primary, filtermatch varchar(255) NOT NULL, filterreplace varchar(20000) NOT NULL, allowinpm smallint NOT NULL, regex smallint NOT NULL, kick smallint NOT NULL);");
			$db->exec('CREATE TABLE ' . PREFIX . "ignored (id $primary, ign varchar(50) NOT NULL, ignby varchar(50) NOT NULL);");
			$db->exec('CREATE INDEX ' . PREFIX . 'ign ON ' . PREFIX . 'ignored(ign);');
			$db->exec('CREATE INDEX ' . PREFIX . 'ignby ON ' . PREFIX . 'ignored(ignby);');
			$db->exec('CREATE TABLE ' . PREFIX . "inbox (id $primary, postdate integer NOT NULL, postid integer NOT NULL, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text varchar(20000) NOT NULL);");
			$db->exec('CREATE INDEX ' . PREFIX . 'inbox_postid ON ' . PREFIX . 'inbox(postid);');
			$db->exec('CREATE INDEX ' . PREFIX . 'inbox_poster ON ' . PREFIX . 'inbox(poster);');
			$db->exec('CREATE INDEX ' . PREFIX . 'inbox_recipient ON ' . PREFIX . 'inbox(recipient);');
			$db->exec('CREATE TABLE ' . PREFIX . "linkfilter (id $primary, filtermatch varchar(255) NOT NULL, filterreplace varchar(255) NOT NULL, regex smallint NOT NULL);");
			$db->exec('CREATE TABLE ' . PREFIX . "members (id $primary, nickname varchar(50) NOT NULL UNIQUE, passhash char(32) NOT NULL, status smallint NOT NULL, refresh smallint NOT NULL, bgcolour char(6) NOT NULL, boxwidth smallint NOT NULL DEFAULT 40, boxheight smallint NOT NULL DEFAULT 3, notesboxheight smallint NOT NULL DEFAULT 30, notesboxwidth smallint NOT NULL DEFAULT 80, regedby varchar(50) DEFAULT '', lastlogin integer DEFAULT 0, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, style varchar(255) NOT NULL, nocache smallint NOT NULL, tz smallint NOT NULL, eninbox smallint NOT NULL);");
			$db->exec('CREATE TABLE ' . PREFIX . "messages (id $primary, postdate integer NOT NULL, poststatus smallint NOT NULL, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text varchar(20000) NOT NULL, delstatus smallint NOT NULL);");
			$db->exec('CREATE INDEX ' . PREFIX . 'poster ON ' . PREFIX . 'messages (poster);');
			$db->exec('CREATE INDEX ' . PREFIX . 'recipient ON ' . PREFIX . 'messages(recipient);');
			$db->exec('CREATE INDEX ' . PREFIX . 'postdate ON ' . PREFIX . 'messages(postdate);');
			$db->exec('CREATE INDEX ' . PREFIX . 'poststatus ON ' . PREFIX . 'messages(poststatus);');
			$db->exec('CREATE TABLE ' . PREFIX . "notes (id $primary, type char(5) NOT NULL, lastedited integer NOT NULL, editedby varchar(50) NOT NULL, text varchar(20000) NOT NULL);");
			$db->exec('CREATE TABLE ' . PREFIX . "sessions (id $primary, session char(32) NOT NULL UNIQUE, nickname varchar(50) NOT NULL UNIQUE, status smallint NOT NULL, refresh smallint NOT NULL, style varchar(255) NOT NULL, lastpost integer NOT NULL, passhash char(32) NOT NULL, postid char(6) NOT NULL DEFAULT '000000', boxwidth smallint NOT NULL DEFAULT 40, boxheight smallint NOT NULL DEFAULT 3, useragent varchar(255) NOT NULL, kickmessage varchar(255) DEFAULT '', bgcolour char(6) NOT NULL, notesboxheight smallint NOT NULL DEFAULT 30, notesboxwidth smallint NOT NULL DEFAULT 80, entry integer NOT NULL, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, ip varchar(45) NOT NULL, nocache smallint NOT NULL, tz smallint NOT NULL, eninbox smallint NOT NULL);");
			$db->exec('CREATE INDEX ' . PREFIX . 'status ON ' . PREFIX . 'sessions(status);');
			$db->exec('CREATE INDEX ' . PREFIX . 'lastpost ON ' . PREFIX . 'sessions(lastpost);');
			$db->exec('CREATE INDEX ' . PREFIX . 'incognito ON ' . PREFIX . 'sessions(incognito);');
			$db->exec('CREATE TABLE ' . PREFIX . "settings (setting varchar(50) NOT NULL PRIMARY KEY, value varchar(20000) NOT NULL);");
		}
		$settings=array(array('guestaccess', '0'), array('globalpass', ''), array('englobalpass', '0'), array('captcha', '0'), array('dateformat', 'm-d H:i:s'), array('rulestxt', ''), array('msgencrypted', '0'), array('dbversion', DBVERSION), array('css', 'a:visited{color:#B33CB4;} a:active{color:#FF0033;} a:link{color:#0000FF;} input,select,textarea{color:#FFFFFF;background-color:#000000;} a img{width:15%} a:hover img{width:35%} .error{color:#FF0033;} .delbutton{background-color:#660000;} .backbutton{background-color:#004400;} #exitbutton{background-color:#AA0000;} .center-table{margin-left:auto;margin-right:auto;} body{text-align:center;} .left-table{width:100%;text-align:left;} .right{text-align:right;} .left{text-align:left;} .right-table{border-spacing:0px;margin-left:auto;} .padded{padding:5px;} #chatters{max-height:100px;overflow-y:auto;} .center{text-align:center;}'), array('memberexpire', '60'), array('guestexpire', '15'), array('kickpenalty', '10'), array('entrywait', '120'), array('messageexpire', '14400'), array('messagelimit', '150'), array('maxmessage', 2000), array('captchatime', '600'), array('colbg', '000000'), array('coltxt', 'FFFFFF'), array('maxname', '20'), array('minpass', '5'), array('defaultrefresh', '20'), array('dismemcaptcha', '0'), array('suguests', '0'), array('imgembed', '1'), array('timestamps', '1'), array('trackip', '0'), array('captchachars', '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), array('memkick', '1'), array('forceredirect', '0'), array('redirect', ''), array('incognito', '1'), array('chatname', 'My Chat'), array('topic', ''), array('msgsendall', $I['sendallmsg']), array('msgsendmem', $I['sendmemmsg']), array('msgsendmod', $I['sendmodmsg']), array('msgsendadm', $I['sendadmmsg']), array('msgsendprv', $I['sendprvmsg']), array('msgenter', $I['entermsg']), array('msgexit', $I['exitmsg']), array('msgmemreg', $I['memregmsg']), array('msgsureg', $I['suregmsg']), array('msgkick', $I['kickmsg']), array('msgmultikick', $I['multikickmsg']), array('msgallkick', $I['allkickmsg']), array('msgclean', $I['cleanmsg']), array('numnotes', '3'), array('keeplimit', '3'), array('mailsender', 'www-data <www-data@localhost>'), array('mailreceiver', 'Webmaster <webmaster@localhost>'), array('sendmail', '0'), array('modfallback', '1'), array('guestreg', '0'), array('disablepm', '0'), array('disabletext', "<h1>$I[disabledtext]</h1>"), array('defaulttz', '0'), array('eninbox', '0'));
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'settings (setting, value) VALUES (?, ?);');
		foreach($settings as $pair){
			$stmt->execute($pair);
		}
		$reg=array(
			'nickname'	=>$_REQUEST['sunick'],
			'passhash'	=>md5(sha1(md5($_REQUEST['sunick'].$_REQUEST['supass']))),
			'status'	=>8,
			'refresh'	=>20,
			'bgcolour'	=>'000000',
			'timestamps'	=>1,
			'style'		=>'color:#FFFFFF;',
			'embed'		=>1,
			'incognito'	=>0,
			'nocache'	=>0,
			'tz'		=>0,
			'eninbox'	=>0
		);
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, timestamps, style, embed, incognito, nocache, tz, eninbox) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		$stmt->execute(array($reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['bgcolour'], $reg['timestamps'], $reg['style'], $reg['embed'], $reg['incognito'], $reg['nocache'], $reg['tz'], $reg['eninbox']));
		$suwrite=$I['susuccess'];
	}
	print_start('init');
	echo "<h2>$I[init]</h2><br><h3>$I[sulogin]</h3>$suwrite<br><br><br>";
	echo "<$H[form]>$H[commonform]".hidden('action', 'setup').submit($I['initgosetup'])."</form>$H[credit]";
	print_end();
}

function update_db(){
	global $F, $I, $db, $memcached;
	$dbversion=(int) get_setting('dbversion');
	if($dbversion<DBVERSION || get_setting('msgencrypted')!=MSGENCRYPTED){
		if(DBDRIVER===1){//PostgreSQL
			$primary='serial PRIMARY KEY';
		}else{//SQLite
			$primary='integer PRIMARY KEY';
		}
		if($dbversion<2){
			$db->exec('CREATE TABLE IF NOT EXISTS ' . PREFIX . "ignored (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, ignored varchar(50) NOT NULL, `by` varchar(50) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
		}
		if($dbversion<3){
			$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('rulestxt', '');");
		}
		if($dbversion<4){
			$db->exec('ALTER TABLE ' . PREFIX . 'members ADD incognito smallint NOT NULL;');
			$db->exec('ALTER TABLE ' . PREFIX . 'sessions ADD incognito smallint NOT NULL;');
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
			$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('css', 'a:visited{color:#B33CB4;} a:active{color:#FF0033;} a:link{color:#0000FF;} input,select,textarea{color:#FFFFFF;background-color:#000000;} a img{width:15%} a:hover img{width:35%} .error{color:#FF0033;} .delbutton{background-color:#660000;} .backbutton{background-color:#004400;} #exitbutton{background-color:#AA0000;}'), ('memberexpire', '60'), ('guestexpire', '15'), ('kickpenalty', '10'), ('entrywait', '120'), ('messageexpire', '14400'), ('messagelimit', '150'), ('maxmessage', 2000), ('captchatime', '600');");
			$db->exec('ALTER TABLE ' . PREFIX . 'sessions ADD ip varchar(45) NOT NULL;');
		}
		if($dbversion<11){
			$db->exec('ALTER TABLE ' , PREFIX . 'captcha CHARACTER SET utf8 COLLATE utf8_bin;');
			$db->exec('ALTER TABLE ' . PREFIX . 'filter CHARACTER SET utf8 COLLATE utf8_bin;');
			$db->exec('ALTER TABLE ' . PREFIX . 'ignored CHARACTER SET utf8 COLLATE utf8_bin;');
			$db->exec('ALTER TABLE ' . PREFIX . 'members CHARACTER SET utf8 COLLATE utf8_bin;');
			$db->exec('ALTER TABLE ' . PREFIX . 'messages CHARACTER SET utf8 COLLATE utf8_bin;');
			$db->exec('ALTER TABLE ' . PREFIX . 'notes CHARACTER SET utf8 COLLATE utf8_bin;');
			$db->exec('ALTER TABLE ' . PREFIX . 'sessions CHARACTER SET utf8 COLLATE utf8_bin;');
			$db->exec('ALTER TABLE ' . PREFIX . 'settings CHARACTER SET utf8 COLLATE utf8_bin;');
			$db->exec('CREATE TABLE ' . PREFIX . "linkfilter (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, `match` varchar(255) NOT NULL, `replace` varchar(255) NOT NULL, regex smallint NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_bin;");
			$db->exec('ALTER TABLE ' . PREFIX . 'sessions DROP fontinfo, DROP displayname;');
			$db->exec('ALTER TABLE ' . PREFIX . 'members ADD style varchar(255) NOT NULL;');
			$result=$db->query('SELECT * FROM ' . PREFIX . 'members;');
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET style=? WHERE id=?;');
			while($temp=$result->fetch(PDO::FETCH_ASSOC)){
				if(isSet($F[$temp['fontface']])){
					$fontface=$F[$temp['fontface']];
				}else{
					$fontface='';
				}
				$style=get_style("#$temp[colour] $fontface <$temp[fonttags]>");
				$stmt->execute(array($style, $temp['id']));
			}
			$db->exec('ALTER TABLE ' . PREFIX . 'members DROP colour, DROP fontface, DROP fonttags;');
			$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('colbg', '000000'), ('coltxt', 'FFFFFF'), ('maxname', '20'), ('minpass', '5'), ('defaultrefresh', '20'), ('dismemcaptcha', '0'), ('suguests', '0'), ('imgembed', '1'), ('timestamps', '1'), ('trackip', '0'), ('captchachars', '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), ('memkick', '1'), ('forceredirect', '0'), ('redirect', ''), ('incognito', '1');");
		}
		if($dbversion<12){
			$db->exec('ALTER TABLE ' . PREFIX . 'captcha MODIFY code char(5) NOT NULL, DROP INDEX id, ADD PRIMARY KEY (id) USING BTREE;');
			$db->exec('ALTER TABLE ' . PREFIX . 'captcha ENGINE=MEMORY;');
			$db->exec('ALTER TABLE ' . PREFIX . 'filter MODIFY id integer unsigned NOT NULL AUTO_INCREMENT, MODIFY `match` varchar(255) NOT NULL, MODIFY replace varchar(20000) NOT NULL;');
			$db->exec('ALTER TABLE ' . PREFIX . 'ignored MODIFY ignored varchar(50) NOT NULL, MODIFY `by` varchar(50) NOT NULL, ADD INDEX(ignored), ADD INDEX(`by`);');
			$db->exec('ALTER TABLE ' . PREFIX . 'linkfilter MODIFY match varchar(255) NOT NULL, MODIFY replace varchar(255) NOT NULL;');
			$db->exec('ALTER TABLE ' . PREFIX . "members MODIFY id integer unsigned NOT NULL AUTO_INCREMENT, MODIFY nickname varchar(50) NOT NULL UNIQUE, MODIFY passhash char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY bgcolour char(6) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY boxwidth smallint NOT NULL DEFAULT 40, MODIFY boxheight smallint NOT NULL DEFAULT 3, MODIFY notesboxheight smallint NOT NULL DEFAULT 30, MODIFY notesboxwidth smallint NOT NULL DEFAULT 80, MODIFY regedby varchar(50) NOT NULL, MODIFY embed smallint NOT NULL DEFAULT 1, MODIFY incognito smallint NOT NULL DEFAULT 0, MODIFY style varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL;");
			$db->exec('ALTER TABLE ' . PREFIX . 'messages MODIFY poster varchar(50) NOT NULL, MODIFY recipient varchar(50) NOT NULL, MODIFY text varchar(20000) NOT NULL, ADD INDEX(poster), ADD INDEX(recipient), ADD INDEX(postdate), ADD INDEX(poststatus);');
			$db->exec('ALTER TABLE ' . PREFIX . 'notes MODIFY type char(5) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY editedby varchar(50) NOT NULL, MODIFY text varchar(20000) NOT NULL;');
			$db->exec('ALTER TABLE ' . PREFIX . "sessions MODIFY session char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL UNIQUE, MODIFY nickname varchar(50) NOT NULL UNIQUE, MODIFY style varchar(255) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY passhash char(32) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY postid char(6) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL DEFAULT '000000', MODIFY boxwidth smallint unsigned NOT NULL DEFAULT 40, MODIFY boxheight smallint unsigned NOT NULL DEFAULT 3, MODIFY notesboxheight smallint unsigned NOT NULL DEFAULT 30, MODIFY notesboxwidth smallint unsigned NOT NULL DEFAULT 80, MODIFY bgcolour char(6) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY useragent varchar(255) NOT NULL, MODIFY kickmessage varchar(255) NOT NULL, MODIFY embed smallint NOT NULL DEFAULT 1, MODIFY incognito smallint NOT NULL DEFAULT 0, MODIFY ip varchar(45) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, ADD INDEX(status) USING BTREE, ADD INDEX(lastpost) USING BTREE;");
			$db->exec('ALTER TABLE ' . PREFIX . 'sessions ENGINE=MEMORY;');
			$db->exec('ALTER TABLE ' . PREFIX . 'settings MODIFY id integer unsigned NOT NULL, MODIFY setting varchar(50) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY value varchar(20000) NOT NULL;');
			$db->exec('ALTER TABLE ' . PREFIX . 'settings DROP PRIMARY KEY, DROP id, ADD PRIMARY KEY(setting);');
			$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('chatname', 'My Chat'), ('topic', ''), ('msgsendall', '$I[sendallmsg]'), ('msgsendmem', '$I[sendmemmsg]'), ('msgsendmod', '$I[sendmodmsg]'), ('msgsendadm', '$I[sendadmmsg]'), ('msgsendprv', '$I[sendprvmsg]'), ('numnotes', '3');");
		}
		if($dbversion<13){
			$db->exec('ALTER TABLE ' . PREFIX . 'filter CHANGE `match` filtermatch varchar(255) NOT NULL, CHANGE `replace` filterreplace varchar(20000) NOT NULL;');
			$db->exec('ALTER TABLE ' . PREFIX . 'ignored CHANGE ignored ign varchar(50) NOT NULL, CHANGE `by` ignby varchar(50) NOT NULL;');
			$db->exec('ALTER TABLE ' . PREFIX . 'linkfilter CHANGE `match` filtermatch varchar(255) NOT NULL, CHANGE `replace` filterreplace varchar(255) NOT NULL;');
			$db->exec('ALTER TABLE ' . PREFIX . 'sessions MODIFY ip varchar(45) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL;');
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
			$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('keeplimit', '3'), ('mailsender', 'www-data <www-data@localhost>'), ('mailreceiver', 'Webmaster <webmaster@localhost>'), ('sendmail', '0'), ('modfallback', '1'), ('guestreg', '0');");
		}
		if($dbversion<16){
			$css=get_setting('css');
			$css.=' .center-table{margin-left:auto;margin-right:auto;} body{text-align:center;} .left-table{width:100%;text-align:left;} .right{text-align:right;} .left{text-align:left;} .right-table{border-spacing:0px;margin-left:auto;} .padded{padding:5px;} #chatters{max-height:100px;overflow-y:auto;} .center{text-align:center;}';
			update_setting('css', $css);
		}
		if($dbversion<17){
			$db->exec('ALTER TABLE ' . PREFIX . 'sessions ADD COLUMN nocache smallint NOT NULL DEFAULT 0;');
			$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN nocache smallint NOT NULL DEFAULT 0;');
		}
		if($dbversion<18){
			$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('disablepm', '0');");
		}
		if($dbversion<19){
			$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('disabletext', '<h1>$I[disabledtext]</h1>');");
		}
		if($dbversion<20){
			$db->exec('ALTER TABLE ' . PREFIX . 'sessions ADD COLUMN tz smallint NOT NULL DEFAULT 0;');
			$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN tz smallint NOT NULL DEFAULT 0;');
			$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('defaulttz', '0');");
		}
		if($dbversion<21){
			$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN eninbox smallint NOT NULL DEFAULT 0;');
			$db->exec('ALTER TABLE ' . PREFIX . 'sessions ADD COLUMN eninbox smallint NOT NULL DEFAULT 0;');
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
		if($dbversion<22){
			$db->exec('CREATE INDEX ' . PREFIX . 'incognito ON ' . PREFIX . 'sessions(incognito);');
		}
		if($dbversion<23){
			$db->exec('DELETE FROM ' . PREFIX . "settings WHERE setting='enablejs';");
			if(MEMCACHED){
				$memcached->delete(DBNAME . '-' . PREFIX . "settings-enablejs");
			}
		}
		if($dbversion<24){
			$db->exec('DELETE FROM ' . PREFIX . 'ignored WHERE id IN (SELECT id FROM (SELECT ' . PREFIX . 'ignored.id, ign, ignby FROM ' . PREFIX . 'ignored, ' . PREFIX . 'members WHERE nickname=ignby AND status < (SELECT status FROM ' . PREFIX . 'members WHERE nickname=ign) ) AS t);');
		}
		update_setting('dbversion', DBVERSION);
		if(get_setting('msgencrypted')!=MSGENCRYPTED){
			if(!extension_loaded('openssl')){
				send_fatal_error($I['opensslextrequired']);
			}
			$result=$db->query('SELECT id, text FROM ' . PREFIX . 'messages;');
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'messages SET text=? WHERE id=?;');
			while($message=$result->fetch(PDO::FETCH_ASSOC)){
				if(MSGENCRYPTED){
					$message['text']=openssl_encrypt($message['text'], 'aes-256-cbc', ENCRYPTKEY, 0, '1234567890123456');
				}else{
					$message['text']=openssl_decrypt($message['text'], 'aes-256-cbc', ENCRYPTKEY, 0, '1234567890123456');
				}
				$stmt->execute(array($message['text'], $message['id']));
			}
			$result=$db->query('SELECT id, text FROM ' . PREFIX . 'notes;');
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'notes SET text=? WHERE id=?;');
			while($message=$result->fetch(PDO::FETCH_ASSOC)){
				if(MSGENCRYPTED){
					$message['text']=openssl_encrypt($message['text'], 'aes-256-cbc', ENCRYPTKEY, 0, '1234567890123456');
				}else{
					$message['text']=openssl_decrypt($message['text'], 'aes-256-cbc', ENCRYPTKEY, 0, '1234567890123456');
				}
				$stmt->execute(array($message['text'], $message['id']));
			}
			update_setting('msgencrypted', (int) MSGENCRYPTED);
		}
		send_update();
	}
}

function get_setting($setting){
	global $db, $memcached;
	if(!MEMCACHED || !$value=$memcached->get(DBNAME . '-' . PREFIX . "settings-$setting")){
		$stmt=$db->prepare('SELECT value FROM ' . PREFIX . 'settings WHERE setting=?;');
		$stmt->execute(array($setting));
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
	$stmt->execute(array($value, $setting));
	if(MEMCACHED){
		$memcached->set(DBNAME . '-' . PREFIX . "settings-$setting", $value);
	}
}

// configuration, defaults and internals

function check_db(){
	global $I, $db, $memcached;
	$options=array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT);
	try{
		if(DBDRIVER===0){
			if(!extension_loaded('pdo_mysql')){
				send_fatal_error($I['pdo_mysqlextrequired']);
			}
			$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, $options);
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
					$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, $options);
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
				if(isSet($_REQUEST['action']) && $_REQUEST['action']==='setup'){
					send_fatal_error($I['nodbsetup']);
				}else{
					send_fatal_error($I['nodb']);
				}
			}
		}catch(PDOException $e){
			if(isSet($_REQUEST['action']) && $_REQUEST['action']==='setup'){
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
	global $H, $I, $language;
	$H=array(// default HTML
		'form'		=>"form action=\"$_SERVER[SCRIPT_NAME]\" method=\"post\"",
		'meta_html'	=>"<meta name=\"robots\" content=\"noindex,nofollow\"><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"><meta http-equiv=\"Pragma\" content=\"no-cache\"><meta http-equiv=\"Cache-Control\" content=\"no-cache\"><meta http-equiv=\"expires\" content=\"0\">",
		'credit'	=>'<small><br><br><a target="_blank" href="https://github.com/DanWin/le-chat-php">LE CHAT-PHP - ' . VERSION . '</a></small>',
		'commonform'	=>hidden('lang', $language).hidden('nc', substr(time(), -6))
	);
	if(isSet($_REQUEST['session'])){
		$H['commonform'].=hidden('session', $_REQUEST['session']);
	}
	$H=$H+array(
		'backtologin'	=>"<$H[form] target=\"_parent\">".hidden('lang', $language).submit($I['backtologin'], 'class="backbutton"').'</form>',
		'backtochat'	=>"<$H[form]>$H[commonform]".hidden('action', 'view').submit($I['backtochat'], 'class="backbutton"').'</form>'
	);
}

function load_lang(){
	global $I, $L, $language;
	$L=array(
		'de'	=>'Deutsch',
		'en'	=>'English',
		'es_AR'	=>'Español (Argentina)',
		'es_ES'	=>'Español (España)',
		'fr'	=>'Français',
		'id'	=>'Bahasa Indonesia',
		'ru'	=>'Русский'
	);
	if(isSet($_REQUEST['lang']) && isSet($L[$_REQUEST['lang']])){
		$language=$_REQUEST['lang'];
		if(!isSet($_COOKIE['language']) || $_COOKIE['language']!==$language){
			setcookie('language', $language);
		}
	}elseif(isSet($_COOKIE['language']) && isSet($L[$_COOKIE['language']])){
		$language=$_COOKIE['language'];
	}else{
		$language=LANG;
		setcookie('language', $language);
	}
	include('lang_en.php'); //always include English
	if($language!=='en'){
		include("lang_$language.php"); //replace with translation if available
		foreach($T as $name=>$translation){
			$I[$name]=$translation;
		}
	}
}

function load_config(){
	date_default_timezone_set('UTC');
	define('VERSION', '1.20.5'); // Script version
	define('DBVERSION', 24); // Database version
	define('MSGENCRYPTED', false); // Store messages encrypted in the database to prevent other database users from reading them - true/false - visit the setup page after editing!
	define('ENCRYPTKEY', 'MY_KEY'); // Encryption key for messages
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
}
?>
