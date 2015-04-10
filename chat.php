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

date_default_timezone_set('UTC');
$U=array();// This user data
$P=array();// All present users
$A=array();// All registered members
$M=array();// Members: display names
$G=array();// Guests: display names
$F=array();// Fonts
$C=array();// Configuration
$H=array();// HTML-stuff
$I=array();// Translations
$L=array();// Languages
$mysqli;// MySQL database connection
$countmods=0;
load_fonts();
load_config();
load_lang();
load_html();
check_db();

// set session variable to cookie if cookies are enabled
if(!isSet($_REQUEST['session']) && isSet($_COOKIE[$C['cookiename']])){
	$_REQUEST['session']=$_COOKIE[$C['cookiename']];
}

//  main program: decide what to do based on queries
if(!isSet($_REQUEST['action'])){
	send_login();
}elseif($_REQUEST['action']=='view'){
	check_session();
	send_messages();
}elseif($_REQUEST['action']=='redirect' && isSet($_GET['url']) && !$_GET['url']==''){
	send_redirect();
}elseif($_REQUEST['action']=='wait'){
	send_waiting_room();
}elseif($_REQUEST['action']=='post'){
	check_session();
	if(isSet($_REQUEST['kick']) && isSet($_REQUEST['sendto']) && valid_nick($_REQUEST['sendto'])){
		if($U['status']>=5 || ($C['memkick'] && $countmods==0 && $U['status']>=3)){
			if(isSet($_REQUEST['what']) && $_REQUEST['what']=='purge') kick_chatter(array($_REQUEST['sendto']), $_REQUEST['message'], true);
			else kick_chatter(array($_REQUEST['sendto']), $_REQUEST['message'], false);
		}
	}elseif(isSet($_REQUEST['message']) && isSet($_REQUEST['sendto']) && !preg_match('/^\s*$/',$_REQUEST['message'])){
		validate_input();
		add_message();
	}
	send_post();
}elseif($_REQUEST['action']=='login'){
	check_login();
	send_frameset();
}elseif($_REQUEST['action']=='controls'){
	check_session();
	send_controls();
}elseif($_REQUEST['action']=='delete'){
	check_session();
	if($_REQUEST['what']=='all') del_all_messages($U['nickname']);
	if($_REQUEST['what']=='last') del_last_message();
	send_post();
}elseif($_REQUEST['action']=='profile'){
	check_session();
	if(isSet($_REQUEST['do']) && $_REQUEST['do']=='save') save_profile();
	send_profile();
}elseif($_REQUEST['action']=='logout'){
	kill_session();
	send_logout();
}elseif($_REQUEST['action']=='colours'){
	check_session();
	send_colours();
}elseif($_REQUEST['action']=='notes'){
	check_session();
	if(!$U['status']>=5) send_login();
	send_notes('staff');
}elseif($_REQUEST['action']=='help'){
	check_session();
	send_help();
}elseif($_REQUEST['action']=='admnotes'){
	check_session();
	if(!$U['status']>=6) send_login();
	send_notes('admin');
}elseif($_REQUEST['action']=='admin'){
	check_session();
	if(!$U['status']>=5) send_login();
	if(!isSet($_REQUEST['do'])){
		send_admin();
	}elseif($_REQUEST['do']=='clean'){
		if($_REQUEST['what']=='choose') send_choose_messages();
		if($_REQUEST['what']=='selected') clean_selected();
		if($_REQUEST['what']=='room') clean_room();
		send_admin();
	}elseif($_REQUEST['do']=='kick'){
		if(!isSet($_REQUEST['name'])) send_admin();
		if(isSet($_REQUEST['what']) && $_REQUEST['what']=='purge') kick_chatter($_REQUEST['name'], $_REQUEST['kickmessage'], true);
		else kick_chatter($_REQUEST['name'], $_REQUEST['kickmessage'], false);
		send_admin();
	}elseif($_REQUEST['do']=='logout'){
		if(!isSet($_REQUEST['name'])) send_admin();
		logout_chatter($_REQUEST['name']);
		send_admin();
	}elseif($_REQUEST['do']=='sessions'){
		send_sessions();
	}elseif($_REQUEST['do']=='register'){
		register_guest(3);
		check_session();
		send_admin();
	}elseif($_REQUEST['do']=='superguest'){
		register_guest(2);
		check_session();
		send_admin();
	}elseif($_REQUEST['do']=='status'){
		change_status();
	}elseif($_REQUEST['do']=='regnew'){
		register_new();
	}elseif($_REQUEST['do']=='approve'){
		approve_session();
		send_approve_waiting();
	}elseif($_REQUEST['do']=='guestaccess'){
		if(isSet($_REQUEST['set']) && preg_match('/^[0123]$/', $_REQUEST['set'])){
			update_setting('guestaccess', $_REQUEST['set']);
		}
	}elseif($_REQUEST['do']=='filter'){
		manage_filter();
		send_filter();
	}
	send_admin();
}elseif($_REQUEST['action']=='setup'){
	$tables=array('captcha', 'filter', 'ignored', 'members', 'messages', 'notes', 'sessions', 'settings');
	$num_tables=0;
	$result=mysqli_query($mysqli, 'SHOW TABLES');
	while($tmp=mysqli_fetch_array($result, MYSQLI_NUM)){
		if(in_array($tmp[0],$tables)) $num_tables++;
	}
	if($num_tables<7) send_init();
	update_db();
	if(!valid_admin()) send_alogin();
	if(!isSet($_REQUEST['do'])){
	}elseif($_REQUEST['do']=='guestaccess'){
		if(isSet($_REQUEST['set']) && preg_match('/^[0123]$/', $_REQUEST['set'])){
			update_setting('guestaccess', $_REQUEST['set']);
		}
	}elseif($_REQUEST['do']=='messages'){
		update_messages();
	}elseif($_REQUEST['do']=='rules'){
		$_REQUEST['rulestxt']=preg_replace("/\r\n/", '<br>', $_REQUEST['rulestxt']);
		$_REQUEST['rulestxt']=preg_replace("/\n/", '<br>', $_REQUEST['rulestxt']);
		$_REQUEST['rulestxt']=preg_replace("/\r/", '<br>', $_REQUEST['rulestxt']);
		update_setting('rulestxt', $_REQUEST['rulestxt']);
	}
	send_setup();
}elseif($_REQUEST['action']=='init'){
	init_chat();
}else{
	send_login();
}
exit;

//  html output subs

function print_credits(){
	global $I, $C;
	echo '<small>';
	if($C['showcredits']){
		echo "<h2>$I[contributors]</h2>";
		echo 'Programming - <a href="mailto:d@winzen4.de">Daniel Winzen</a><br>';
		echo 'German - <a href="mailto:d@winzen4.de">Daniel Winzen</a><br>';
		echo 'English - <a href="mailto:d@winzen4.de">Daniel Winzen</a><br>';
	}
	echo "<br><br><a target=\"_blank\" href=\"https://github.com/DanWin/le-chat-php\">LE CHAT-PHP - $C[version]</a></small></center>";
}

function print_stylesheet($arg1=''){
	echo "\n<style type=\"text/css\">input,select,textarea{color:#FFFFFF;background-color:#000000;}a img{width:25%}a:hover img{width:35%}$arg1</style>";
}

function print_end(){
	echo '</body></html>';
	exit;
}

function frmpst($arg1='', $arg2=''){
	global $U, $H;
	$string="<$H[form]>".hidden('action', $arg1).hidden('session', $U['session']);
	if($arg2!==''){
		$string.=hidden('what', $arg2).@hidden('sendto', $_REQUEST['sendto']).@hidden('multi', $_REQUEST['multi']);
	}
	return $string;
}

function frmadm($arg1=''){
	global $U, $H;
	return "<$H[form]>".hidden('action', 'admin').hidden('do', $arg1).hidden('session', $U['session']);
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

function print_start($css='', $ref='', $url=''){
	global $H;
	header('Content-Type: text/html; charset=UTF-8'); header('Pragma: no-cache'); header('Expires: 0');
	if($url!=='') header("Refresh: $ref; URL=$url");
	echo "<!DOCTYPE html><html><head>$H[meta_html]";
	if($url!=='') echo "\n<meta http-equiv=\"Refresh\" content=\"$ref; URL=$url\">";
	print_stylesheet($css);
	echo "</head>$H[begin_body]";
}

function send_redirect(){
	if(preg_match('~^http(s)?://~', $_GET['url'])){
		header("Refresh: 0; URL=$_GET[url]");
		echo "<html><head><meta http-equiv=\"Refresh\" content=\"0; url=$_GET[url]\"></head><body><p>Redirecting to: <a href=\"$_GET[url]\">".htmlspecialchars($_GET['url'])."</a>.</p></body></html>";
	}else{
		$url=preg_replace('~(.*)://~', 'http://', $_GET['url']);
		echo '<html><head></head><body>';
		echo "<p>Non-http link requested: <a href=\"$_GET[url]\">".htmlspecialchars($_GET['url'])."</a>.</p>";
		echo "<p>If it's not working, try this one: <a href=\"$url\">".htmlspecialchars($url)."</a>.</p>";
		echo '</body></html>';
	}
}

function send_captcha(){
	global $C, $I, $mysqli;
	$length=strlen($C['captchachars']);
	$code='';
	for($i=0;$i<5;$i++) {
		$code .= $C['captchachars'][rand(0, $length-1)];
	}
	$randid=rand(0, 99999999);
	$enc=base64_encode(openssl_encrypt("$code, $randid", 'aes-128-cbc', $C['captchapass'], 0, '1234567890123456'));
	$stmt=mysqli_prepare($mysqli, 'INSERT INTO `captcha` (`id`, `time`) VALUES (?, \''.time().'\')');
	mysqli_stmt_bind_param($stmt, 'd', $randid);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	$im=imagecreatetruecolor(55, 24);
	$bg=imagecolorallocate($im, 0, 0, 0);
	$fg=imagecolorallocate($im, 255, 255, 255);
	imagefill($im, 0, 0, $bg);
	imagestring($im, 5, 5, 5, $code, $fg);
	echo "<tr><td align=\"left\">$I[copy]";
	echo '<img width="55" height="24" src="data:image/gif;base64,';
	ob_start();
	imagegif($im);
	imagedestroy($im);
	echo base64_encode(ob_get_clean()).'">';
	echo '</td><td align="right">'.hidden('challenge', $enc).'<input type="text" name="captcha" size="15" autocomplete="off"></td></tr>';
}

function send_setup(){
	global $H, $I, $mysqli, $C;
	$ga=get_setting('guestaccess');
	print_start();
	echo "<center><h2>$I[setup]</h2><table cellspacing=\"0\">";
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[guestacc]</b></td><td align=\"right\">";
	echo "<$H[form]>".hidden('action', 'setup').hidden('do', 'guestaccess').hidden('nick', $_REQUEST['nick']).hidden('pass', $_REQUEST['pass']).'<table cellspacing="0">';
	echo '<tr><td align="left">&nbsp;<input type="radio" name="set" id="set1" value="1"';
	if($ga==1) echo ' checked';
	echo "><label for=\"set1\">&nbsp;$I[guestallow]</label></td><td>&nbsp;</td><tr>";
	echo '<tr><td align="left">&nbsp;<input type="radio" name="set" id="set2" value="2"';
	if($ga==2) echo ' checked';
	echo "><label for=\"set2\">&nbsp;$I[guestwait]</label></td><td>&nbsp;</td><tr>";
	echo '<tr><td align="left">&nbsp;<input type="radio" name="set" id="set3" value="3"';
	if($ga==3) echo ' checked';
	echo "><label for=\"set3\">&nbsp;$I[adminallow]</label></td><td>&nbsp;</td><tr>";
	echo '<tr><td align="left">&nbsp;<input type="radio" name="set" id="set0" value="0"';
	if($ga==0) echo ' checked';
	echo "><label for=\"set0\">&nbsp;$I[guestdisallow]</label></td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td align=\"right\">".submit($I['change']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[sysmessages]</b></td><td align=\"right\">";
	echo "<$H[form]>".hidden('action', 'setup').hidden('do', 'messages').hidden('nick', $_REQUEST['nick']).hidden('pass', $_REQUEST['pass']).'<table cellspacing="0">';
	echo "<tr><td>&nbsp;$I[msgenter]</td><td>&nbsp;<input type=\"text\" name=\"msgenter\" value=\"".get_setting('msgenter').'"></td></tr>';
	echo "<tr><td>&nbsp;$I[msgexit]</td><td>&nbsp;<input type=\"text\" name=\"msgexit\" value=\"".get_setting('msgexit').'"></td></tr>';
	echo "<tr><td>&nbsp;$I[msgmemreg]</td><td>&nbsp;<input type=\"text\" name=\"msgmemreg\" value=\"".get_setting('msgmemreg').'"></td></tr>';
	if($C['suguests']) echo "<tr><td>&nbsp;$I[msgsureg]</td><td>&nbsp;<input type=\"text\" name=\"msgsureg\" value=\"".get_setting('msgsureg').'"></td></tr>';
	echo "<tr><td>&nbsp;$I[msgkick]</td><td>&nbsp;<input type=\"text\" name=\"msgkick\" value=\"".get_setting('msgkick').'"></td></tr>';
	echo "<tr><td>&nbsp;$I[msgmultikick]</td><td>&nbsp;<input type=\"text\" name=\"msgmultikick\" value=\"".get_setting('msgmultikick').'"></td></tr>';
	echo "<tr><td>&nbsp;$I[msgallkick]</td><td>&nbsp;<input type=\"text\" name=\"msgallkick\" value=\"".get_setting('msgallkick').'"></td></tr>';
	echo "<tr><td>&nbsp;$I[msgclean]</td><td>&nbsp;<input type=\"text\" name=\"msgclean\" value=\"".get_setting('msgclean').'"></td></tr>';
	echo '<tr><td>&nbsp;</td><td align="right">'.submit($I['apply']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[rules]</b></td><td align=\"right\">";
	echo "<$H[form]>".hidden('action', 'setup').hidden('do', 'rules').hidden('nick', $_REQUEST['nick']).hidden('pass', $_REQUEST['pass']).'<table cellspacing="0">';
	echo '<tr><td colspan=2><textarea name="rulestxt" rows="4" cols="60">'.htmlspecialchars(get_setting('rulestxt')).'</textarea></td></tr>';
	echo '<tr><td>&nbsp;</td><td align="right">'.submit($I['apply']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "</table><$H[form]>".hidden('action', 'setup').submit($I['logout']).'</form>';
	print_credits();
	print_end();
}

function send_init(){
	global $H, $I;
	print_start();
	echo "<center><h2>$I[init]</h2>";
	echo "<$H[form]>".hidden('action', 'init')."<table cellspacing=\"0\" width=\"1\"><tr><td align=center><h3>$I[sulogin]</h3><table cellspacing=\"0\">";
	echo "<tr><td>$I[sunick]</td><td><input type=\"text\" name=\"sunick\" size=\"15\"></td></tr>";
	echo "<tr><td>$I[supass]</td><td><input type=\"password\" name=\"supass\" size=\"15\"></td></tr>";
	echo "<tr><td>$I[suconfirm]</td><td><input type=\"password\" name=\"supassc\" size=\"15\"></td></tr>";
	echo "</table><br><br></td></tr><tr><td align=\"left\"><br><br><br></td></tr><tr><td align=\"center\">";
	echo "</td></tr><tr><td align=\"center\"><tr><td align=\"center\"><br>".submit($I['initbtn']).'</td></tr></table></form>';
	print_credits();
	print_end();
}

function send_update(){
	global $H, $I;
	print_start();
	echo "<center><h2>$I[dbupdate]</h2><br><$H[form]>".hidden('action', 'setup').submit($I['initgosetup']).'</form><br>';
	print_credits();
	print_end();
}

function send_alogin(){
	global $H, $I, $C;
	print_start();
	echo "<center><$H[form]>".hidden('action', 'setup').'<table>';
	echo "<tr><td align=\"left\">$I[nick]</td><td><input type=\"text\" name=\"nick\" size=\"15\"></td></tr>";
	echo "<tr><td align=\"left\">$I[pass]</td><td><input type=\"password\" name=\"pass\" size=\"15\"></td></tr>";
	if($C['enablecaptcha']) send_captcha();
	echo "<tr><td colspan=\"2\" align=\"right\">".submit($I['login']).'</td></tr></table></form>';
	print_credits();
	print_end();
}

function send_admin($arg=''){
	global $U, $C, $P, $H, $I, $mysqli;
	$ga=get_setting('guestaccess');
	print_start();
	$chlist="<select name=\"name[]\" size=\"5\" multiple><option value=\"\">$I[choose]</option>";
	$chlist.="<option value=\"&\">$I[allguests]</option>";
	array_multisort(array_map('strtolower', array_keys($P)), SORT_ASC, SORT_STRING, $P);
	foreach($P as $user){
		if($user[1]>0 && $user[1]<$U['status']) $chlist.="<option value=\"$user[0]\" style=\"$user[2]\">$user[0]</option>";
	}
	$chlist.='</select>';
	echo "<center><h2>$I[admfunc]</h2><i>$arg</i><table cellspacing=\"0\">";
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[cleanmsgs]</b></td><td align=\"right\">";
	echo frmadm('clean')."<table cellspacing=\"0\"><tr><td>&nbsp;</td><td><input type=\"radio\" name=\"what\" id=\"room\" value=\"room\"></td>";
	echo "<td align=\"left\"><label for=\"room\">$I[room]</label></td><td>&nbsp;</td><td><input type=\"radio\" name=\"what\" id=\"choose\" value=\"choose\" checked></td>";
	echo "<td align=\"left\"><label for=\"choose\">$I[selection]</label></td><td>&nbsp;</td><td>";
	echo submit($I['clean']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo '<tr><td><table cellspacing="0" width="100%"><tr><td align="left">'.sprintf($I['kickchat'], $C['kickpenalty']).'</td></tr><tr><td align="right">';
	echo frmadm('kick')."<table cellspacing=\"0\"><tr><td align=\"left\"><nobr>$I[kickmsg]<input type=\"text\" name=\"kickmessage\" size=\"30\"></nobr></td><td>&nbsp;</td><td>&nbsp;</td></tr>";
	echo "<tr><td align=\"left\"><input type=\"checkbox\" name=\"what\" value=\"purge\" id=\"purge\"><label for=\"purge\">&nbsp;$I[kickpurge]</label></td><td align=\"right\">$chlist</td><td>";
	echo submit($I['kick']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[logoutinact]</b></td><td align=\"right\">";
	echo frmadm('logout')."<table cellspacing=\"0\"><tr><td>&nbsp;</td><td align=\"right\">$chlist</td><td align=\"right\">";
	echo submit($I['logout']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[viewsess]</b></td><td align=\"right\">";
	echo frmadm('sessions')."<table cellspacing=\"0\"><tr><td>&nbsp;</td><td>".submit($I['view']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[filter]</b></td><td align=\"right\">";
	echo frmadm('filter')."<table cellspacing=\"0\"><tr><td>&nbsp;</td><td>".submit($I['view']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[guestacc]</b></td><td align=\"right\">";
	echo frmadm('guestaccess').'<table cellspacing="0">';
	echo "<tr><td align=\"left\">&nbsp;<input type=\"radio\" name=\"set\" id=\"set1\" value=\"1\"";
	if($ga==1) echo " checked";
	echo "><label for=\"set1\">&nbsp;$I[guestallow]</label></td><td>&nbsp;</td><tr>";
	echo "<tr><td align=\"left\">&nbsp;<input type=\"radio\" name=\"set\" id=\"set2\" value=\"2\"";
	if($ga==2) echo " checked";
	echo "><label for=\"set2\">&nbsp;$I[guestwait]</label></td><td>&nbsp;</td><tr>";
	echo "<tr><td align=\"left\">&nbsp;<input type=\"radio\" name=\"set\" id=\"set3\" value=\"3\"";
	if($ga==3) echo " checked";
	echo "><label for=\"set3\">&nbsp;$I[adminallow]</label></td><td>&nbsp;</td><tr>";
	echo "<tr><td align=\"left\">&nbsp;<input type=\"radio\" name=\"set\" id=\"set0\" value=\"0\"";
	if($ga==0) echo " checked";
	echo "><label for=\"set0\">&nbsp;$I[guestdisallow]</label></td><td>&nbsp;</td></tr>";
	echo '<tr><td>&nbsp;</td><td align="right">'.submit($I['change']).'</td></tr></table></form></td></tr></table></td></tr>';
	thr();
	if($C['suguests']){
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[addsuguest]</b></td><td align=\"right\">";
		echo frmadm('superguest')."<table cellspacing=\"0\"><tr><td>&nbsp;</td><td valign=\"bottom\"><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($P as $user){
			if($user[1]==1) echo "<option value=\"$user[0]\" style=\"$user[2]\">$user[0]</option>";
		}
		echo '</select></td><td valign="bottom">'.submit($I['register']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
	}
	if($U['status']>=7){
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[admmembers]</b></td></tr><tr><td align=\"right\">";
		echo frmadm('status')."<table cellspacing=\"0\"><tr><td>&nbsp;</td><td valign=\"bottom\" align=\"right\"><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		print_memberslist();
		echo "</select><select name=\"set\" size=\"1\"><option value=\"\">$I[choose]</option><option value=\"-\">$I[memdel]</option><option value=\"0\">$I[memdeny]</option>";
		if($C['suguests']) echo "<option value=\"2\">$I[memsuguest]</option>";
		echo "<option value=\"3\">$I[memreg]</option>";
		echo "<option value=\"5\">$I[memmod]</option>";
		echo "<option value=\"6\">$I[memsumod]</option>";
		if($U['status']>=8) echo "<option value=\"7\">$I[memadm]</option>";
		echo '</select></td><td valign="bottom">'.submit($I['change']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[regguest]</b></td><td align=\"right\">";
		echo frmadm('register')."<table cellspacing=\"0\"><tr><td>&nbsp;</td><td valign=\"bottom\"><select name=\"name\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($P as $user){
			if($user[1]==1) echo "<option value=\"$user[0]\" style=\"$user[2]\">$user[0]</option>";
		}
		echo '</select></td><td valign="bottom">'.submit($I['register']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[regmem]</b></td></tr><tr><td align=\"right\">";
		echo frmadm('regnew')."<table cellspacing=\"0\"><tr><td>&nbsp;</td><td align=\"left\">$I[nick]</td><td><input type=\"text\" name=\"name\" size=\"20\"></td><td>&nbsp;</td></tr>";
		echo "<tr><td>&nbsp;</td><td align=\"left\">$I[pass]</td><td><input type=\"text\" name=\"pass\" size=\"20\"></td><td valign=\"bottom\">";
		echo submit($I['register']).'</td></tr></table></form></td></tr></table></td></tr>';
		thr();
	}
	echo "</table>$H[backtochat]</center>";
	print_end();
}

function send_sessions(){
	global $U, $H, $I;
	$lines=parse_sessions();
	print_start();
	echo "<center><h1>$I[sessact]</h1><table border=\"0\" cellpadding=\"5\">";
	echo "<thead valign=\"middle\"><tr><th align=\"left\"><b>$I[sessnick]</b></th><th align=\"center\"><b>$I[sesstimeout]</b></th><th align=\"left\"><b>$I[sessua]</b></th></tr></thead><tbody valign=\"middle\">";
	if(isSet($lines)){
		foreach($lines as $temp){
			if($temp['status']!=0){
				if($temp['status']==1 || $temp['status']==2) $s='&nbsp;(G)';
				elseif($temp['status']==3) $s='';
				elseif($temp['status']==5 || $temp['status']==6) $s='&nbsp;(M)';
				elseif($temp['status']>=7) $s='&nbsp;(A)';
				echo '<tr><td align="left">'.style_this($temp['nickname'].$s, $temp['fontinfo']).'</td><td align="center">'.get_timeout($temp['lastpost'], $temp['status']).'</td><td align="left">';
				if($U['status']>$temp['status'] || $U['session']==$temp['session']) echo $temp['useragent'];
				else echo '-</td></tr>';
			}
		}
	}
	echo "</tbody></table><br>$H[backtochat]</center>";
	print_end();
}

function manage_filter(){
	global $mysqli, $I;
	if(isSet($_REQUEST['id'])){
		$_REQUEST['match']=htmlspecialchars($_REQUEST['match']);
		if(isSet($_REQUEST['regex']) && $_REQUEST['regex']==1){
			if(!is_int(@preg_match("/$_REQUEST[match]/", ''))) send_filter($I['incorregex']);
			$reg=1;
		}else{
			$_REQUEST['match']=preg_replace('/([^\w\d])/', "\\\\$1", $_REQUEST['match']);
			$reg=0;
		}
		if(isSet($_REQUEST['allowinpm']) && $_REQUEST['allowinpm']==1) $pm=1;
		else $pm=0;
		if(isSet($_REQUEST['kick']) && $_REQUEST['kick']==1) $kick=1;
		else $kick=0;
		if(preg_match('/^[0-9]*$/', $_REQUEST['id'])){
			if($_REQUEST['match']==''){
				$stmt=mysqli_prepare($mysqli, 'DELETE FROM `filter` WHERE `id`=?');
				mysqli_stmt_bind_param($stmt, 's', $_REQUEST['id']);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
			}else{
				$stmt=mysqli_prepare($mysqli, 'UPDATE `filter` SET `match`=?, `replace`=?, `allowinpm`=?, `regex`=?, `kick`=? WHERE `id`=?');
				mysqli_stmt_bind_param($stmt, 'ssdddd', $_REQUEST['match'], $_REQUEST['replace'], $pm, $reg, $kick, $_REQUEST['id']);
				mysqli_stmt_execute($stmt);
				mysqli_stmt_close($stmt);
			}
		}elseif(preg_match('/^\+$/', $_REQUEST['id'])){
			$stmt=mysqli_prepare($mysqli, 'INSERT INTO `filter` (`match`, `replace`, `allowinpm`, `regex`, `kick`) VALUES (?, ?, ?, ?, ?)');
			mysqli_stmt_bind_param($stmt, 'ssddd', $_REQUEST['match'], $_REQUEST['replace'], $pm, $reg, $kick);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
		}
	}
}

function send_filter($arg=''){
	global $U, $H, $I, $mysqli;
	print_start();
	echo "<center><h2>$I[filter]</h2><i>$arg</i><table cellspacing=\"0\">";
	thr();
	echo "<tr><th><table cellspacing=\"0\" width=\"100%\"><tr><td style=\"width:8em\"><center><b>$I[fid]</b></center></td>";
	echo "<td style=\"width:12em\"><center><b>$I[match]</b></center></td>";
	echo "<td style=\"width:12em\"><center><b>$I[replace]</b></center></td>";
	echo "<td style=\"width:9em\"><center><b>$I[allowpm]</b></center></td>";
	echo "<td style=\"width:5em\"><center><b>$I[regex]</b></center></td>";
	echo "<td style=\"width:5em\"><center><b>$I[kick]</b></center></td>";
	echo "<td style=\"width:5em\"><center><b>$I[apply]</b></center></td></tr></table></th></tr>";
	$result=mysqli_query($mysqli, 'SELECT * FROM `filter`');
	if(mysqli_num_rows($result)>0){
		while($filter=mysqli_fetch_array($result, MYSQLI_ASSOC)){
			if($filter['allowinpm']==1) $check=' checked';
			else $check='';
			if($filter['regex']==1) $checked=' checked';
			else $checked='';
			if($filter['kick']==1) $checkedk=' checked';
			else $checkedk='';
			if($filter['regex']==0) $filter['match']=preg_replace('/(\\\\(.))/', "$2", $filter['match']);
			echo '<tr><td>'.frmadm('filter').hidden('id', $filter['id']);
			echo "<table cellspacing=\"0\" width=\"100%\"><tr><td style=\"width:8em\"><b>$I[filter] $filter[id]:</b></td>";
			echo "<td style=\"width:12em\"><input type=\"text\" name=\"match\" value=\"".htmlspecialchars($filter['match'])."\" size=\"20\" style=\"$U[style]\"></td>";
			echo "<td style=\"width:12em\"><input type=\"text\" name=\"replace\" value=\"".htmlspecialchars($filter['replace'])."\" size=\"20\" style=\"$U[style]\"></td>";
			echo "<td style=\"width:9em\"><input type=\"checkbox\" name=\"allowinpm\" id=\"allowinpm-$filter[id]\" value=\"1\"$check><label for=\"allowinpm-$filter[id]\">$I[allowpm]</label></td>";
			echo "<td style=\"width:5em\"><input type=\"checkbox\" name=\"regex\" id=\"regex-$filter[id]\" value=\"1\"$checked><label for=\"regex-$filter[id]\">$I[regex]</label></td>";
			echo "<td style=\"width:5em\"><input type=\"checkbox\" name=\"kick\" id=\"kick-$filter[id]\" value=\"1\"$checkedk><label for=\"kick-$filter[id]\">$I[kick]</label></td>";
			echo '<td align="right" style="width:5em">'.submit($I['change']).'</td></tr></table></form></td></tr>';
		}
	}
	echo '<tr><td>'.frmadm('filter').hidden('id', '+');
	echo "<table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\" style=\"width:8em\"><b>$I[newfilter]</b></td>";
	echo "<td style=\"width:12em\"><input type=\"text\" name=\"match\" value=\"\" size=\"20\" style=\"$U[style]\"></td>";
	echo "<td style=\"width:12em\"><input type=\"text\" name=\"replace\" value=\"\" size=\"20\" style=\"$U[style]\"></td>";
	echo "<td style=\"width:9em\"><input type=\"checkbox\" name=\"allowinpm\" id=\"allowinpm\" value=\"1\"><label for=\"allowinpm\">$I[allowpm]</label></td>";
	echo "<td style=\"width:5em\"><input type=\"checkbox\" name=\"regex\" id=\"regex\" value=\"1\"><label for=\"regex\">$I[regex]</label></td>";
	echo "<td style=\"width:5em\"><input type=\"checkbox\" name=\"kick\" id=\"kick\" value=\"1\"><label for=\"kick\">$I[kick]</label></td>";
	echo '<td align="right" style="width:5em">'.submit($I['add']).'</td></tr></table></form></td></tr>';
	echo "</table><br>$H[backtochat]</center>";
	print_end();
}

function send_frameset(){
	global $U, $H, $I;
	header('Content-Type: text/html; charset=UTF-8'); header('Pragma: no-cache'); header('Expires: 0');
	echo "<!DOCTYPE html><html><head>$H[meta_html]";
	print_stylesheet();
	if(isSet($_COOKIE['test'])){
		echo "</head>\n<frameset rows=\"100,*,60\" border=\"3\" frameborder=\"3\" framespacing=\"3\"><frame name=\"post\" src=\"$_SERVER[SCRIPT_NAME]?action=post\"><frame name=\"view\" src=\"$_SERVER[SCRIPT_NAME]?action=view\"><frame name=\"controls\" src=\"$_SERVER[SCRIPT_NAME]?action=controls\"><noframes>$H[begin_body]$I[noframes]$H[backtologin]</body></noframes></frameset></html>";
	}else{
		echo "</head>\n<frameset rows=\"100,*,60\" border=\"3\" frameborder=\"3\" framespacing=\"3\"><frame name=\"post\" src=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]\"><frame name=\"view\" src=\"$_SERVER[SCRIPT_NAME]?action=view&amp;session=$U[session]\"><frame name=\"controls\" src=\"$_SERVER[SCRIPT_NAME]?action=controls&amp;session=$U[session]\"><noframes>$H[begin_body]$I[noframes]$H[backtologin]</body></noframes></frameset></html>";
	}
	exit;
}

function send_messages(){
	global $U, $C, $I;
	if(isSet($_COOKIE[$C['cookiename']])){
		$url="$_SERVER[SCRIPT_NAME]?action=view&nocache=";
	}else{
		$url="$_SERVER[SCRIPT_NAME]?action=view&session=$U[session]&nocache=";
	}
	if(!isSet($_REQUEST['nocache'])) $_REQUEST['nocache']='';
	print_start('', $U['refresh'], $url.substr(time(), -6));
	echo '<a name="top"></a>';
	print_chatters();
	echo "<table cellspacing=\"0\" width=\"100%\"><tr><td valign=\"top\" align=\"right\"><a href=\"$url$_REQUEST[nocache]#bottom\">$I[bottom]</a></td></tr></table>";
	print_messages();
	echo "<a name=\"bottom\"></a><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"right\"><a href=\"$url$_REQUEST[nocache]#top\">$I[top]</a></td></tr></table>";
	print_end();
}

function send_notes($type){
	global $U, $H, $I, $mysqli;
	$text='';
	print_start();
	if($type=="staff") echo "<center><h2>$I[staffnotes]</h2><p>";
	else echo "<center><h2>$I[adminnotes]</h2><p>";
	if(isset($_REQUEST['text'])){
		$stmt=mysqli_prepare($mysqli, 'INSERT INTO `notes`(`type`, `lastedited`, `editedby`, `text`) VALUES (?, ?, ?, ?)');
		mysqli_stmt_bind_param($stmt, 'sdss', $type, time(), $U['nickname'], $_REQUEST['text']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		echo "<b>$I[notessaved]</b> ";
	}
	$stmt=mysqli_prepare($mysqli, 'SELECT `lastedited`, `editedby`, `text` FROM `notes` WHERE `type`=? ORDER BY `lastedited` DESC LIMIT 1');
	mysqli_stmt_bind_param($stmt, 's', $type);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_bind_result($stmt, $lastedited, $editedby, $text);
	if(mysqli_stmt_fetch($stmt)){
		printf($I['lastedited'], $editedby, date('Y-m-d H:i:s', $lastedited));
	}
	mysqli_stmt_close($stmt);
	echo "</p><$H[form]>";
	if($type=='staff') echo hidden('action', 'notes');
	else echo hidden('action', 'admnotes');
	echo hidden('session', $U['session'])."<textarea name=\"text\" rows=\"$U[notesboxheight]\" cols=\"$U[notesboxwidth]\">".htmlspecialchars($text).'</textarea><br>';
	echo submit($I['savenotes']).'</form></center></body></html>';
}

function send_approve_waiting(){
	global $H, $U, $I, $mysqli;
	print_start('admin');
	echo "<center><h2>$I[waitingroom]</h2>";
	$result=mysqli_query($mysqli, 'SELECT * FROM `sessions` WHERE `entry`!=\'0\' AND `status`=\'1\' ORDER BY `entry`');
	if(mysqli_num_rows($result)>0){
		echo "<$H[form]>".hidden('action', 'admin').hidden('do', 'approve').hidden('session', $U['session'])."<table cellpadding=\"5\">";
		echo "<thead align=\"left\"><tr><th><b>$I[sessnick]</b></th><th><b>$I[sessua]</b></th></tr></thead><tbody align=\"left\" valign=\"middle\">";
		while($temp=mysqli_fetch_array($result, MYSQLI_ASSOC)){
			echo '<tr>'.hidden('alls[]', $temp['nickname'])."<td><input type=\"checkbox\" name=\"csid[]\" id=\"$temp[nickname]]\" value=\"$temp[nickname]\"><label for=\"$temp[nickname]\">&nbsp$temp[displayname]</label></td><td>$temp[useragent]</td></tr>";
		}
		echo "</tbody></table><br><table><tr><td><input type=\"radio\" name=\"what\" value=\"allowchecked\" id=\"allowchecked\" checked></td><td><label for=\"allowchecked\">$I[allowchecked]</label></td>";
		echo "<td><input type=\"radio\" name=\"what\" value=\"allowall\" id=\"allowall\"></td><td><label for=\"allowall\">$I[allowall]</label></td>";
		echo "<td><input type=\"radio\" name=\"what\" value=\"denychecked\" id=\"denychecked\"></td><td><label for=\"denychecked\">$I[denychecked]</label></td>";
		echo "<td><input type=\"radio\" name=\"what\" value=\"denyall\" id=\"denyall\"></td><td><label for=\"denyall\">$I[denyall]</label></td></tr><tr><td colspan=\"8\" align=\"center\">$I[denymessage] <input type=\"text\" name=\"kickmessage\" size=\"45\"></td>";
		echo '</tr><tr><td colspan="8" align="center">'.submit($I['butallowdeny']).'</td></tr></table></form><br>';
	}else{
		echo "$I[waitempty]<br><br>";
	}
	echo "$H[backtochat]</center>";
	print_end();
}

function send_waiting_room(){
	global $U, $C, $M, $H, $I, $countmods, $mysqli;
	parse_sessions();
	if(get_setting('guestaccess')==3 && $countmods>0) $wait=false;
	else $wait=true;
	if(!isSet($_REQUEST['session'])){
		setcookie($C['cookiename'], false);
		send_error($I['expire']);
	}
	$stmt=mysqli_prepare($mysqli, 'SELECT `session`, `nickname`, `displayname`, `status`, `refresh`, `fontinfo`, `style`, `lastpost`, `passhash`, `postid`, `boxwidth`, `boxheight`, `useragent`, `kickmessage`, `bgcolour`, `notesboxheight`, `notesboxwidth`, `entry`, `timestamps`, `embed` FROM `sessions` WHERE `session`=?');
	mysqli_stmt_bind_param($stmt, 's', $_REQUEST['session']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_bind_result($stmt, $U['session'], $U['nickname'], $U['displayname'], $U['status'], $U['refresh'], $U['fontinfo'], $U['style'], $U['lastpost'], $U['passhash'], $U['postid'], $U['boxwidth'], $U['boxheight'], $U['useragent'], $U['kickmessage'], $U['bgcolour'], $U['notesboxheight'], $U['notesboxwidth'], $U['entry'], $U['timestamps'], $U['embed']);
	if(mysqli_stmt_fetch($stmt)) add_user_defaults();
	mysqli_stmt_close($stmt);
	if(!isSet($U['session'])){
		setcookie($C['cookiename'], false);
		send_error($I['expire']);
	}
	if($U['status']==0){
		setcookie($C['cookiename'], false);
		send_error("$I[kicked]<br>$U[kickmessage]");
	}
	$timeleft=$C['entrywait']-(time()-$U['entry']);
	if(($timeleft<=0 || count($M)==0) && $wait){
		$stmt=mysqli_prepare($mysqli, 'UPDATE `sessions` SET `entry`=\'0\' WHERE `session`=?');
		mysqli_stmt_bind_param($stmt, 's', $U['session']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		send_frameset();
	}elseif(!$wait && $U['entry']==0){
		send_frameset();
	}else{
		$U['nocache']=substr(time(), -6);
		if(isSet($_COOKIE['test'])){
			header("Refresh: $C[defaultrefresh]; URL=$_SERVER[SCRIPT_NAME]?action=wait&nocache=$U[nocache]");
			echo "<!DOCTYPE html><html><head>$H[meta_html]\n<meta http-equiv=\"Refresh\" content=\"$C[defaultrefresh]; URL=$_SERVER[SCRIPT_NAME]?action=wait&nocache=$U[nocache]\">\n";
		}else{
			header("Refresh: $C[defaultrefresh]; URL=$_SERVER[SCRIPT_NAME]?action=wait&session=$U[session]&nocache=$U[nocache]");
			echo "<!DOCTYPE html><html><head>$H[meta_html]\n<meta http-equiv=\"Refresh\" content=\"$C[defaultrefresh]; URL=$_SERVER[SCRIPT_NAME]?action=wait&session=$U[session]&nocache=$U[nocache]\">\n";
		}
		print_stylesheet();
		if($wait){
			echo "</head>$H[begin_body]<center><h2>$I[waitingroom]</h2><p>".sprintf($I['waittext'], $U['displayname'], $timeleft).'</p><br><p>'.sprintf($I['waitreload'], $C['defaultrefresh']).'</p><br><br>';
		}else{
			echo "</head>$H[begin_body]<center><h2>$I[waitingroom]</h2><p>".sprintf($I['admwaittext'], $U['displayname']).'</p><br><p>'.sprintf($I['waitreload'], $C['defaultrefresh']).'</p><br><br>';
		}
		echo "<hr><form action=\"$_SERVER[SCRIPT_NAME]\" method=\"post\">".hidden('action', 'wait').hidden('session', $U['session']).submit($I['reload']).'</form><br>';
		echo "<h2>$I[rules]</h2><b>".get_setting('rulestxt').'</b></center>';
		print_end();
	}
}

function send_choose_messages(){
	global $U, $H, $I;
	print_start();
	echo frmadm('clean').hidden('what', 'selected').submit($I['delselmes'], ' style="background-color:#660000;color:#FFFFFF;"').'<br><br>';
	print_messages($U['status']);
	echo "</form><br>$H[backtochat]";
	print_end();
}

function send_post(){
	global $U, $C, $P, $I, $countmods, $mysqli;
	$U['postid']=substr(time(), -6);
	print_start();
	echo "<center><table cellspacing=\"0\"><tr><td align=\"center\">".frmpst('post').hidden('postid', $U['postid']).@hidden('multi', $_REQUEST['multi']);
	echo "<table cellspacing=\"0\"><tr><td valign=\"top\">$U[displayname]</td><td valign=\"top\">:</td>";
	if(!isSet($U['rejected'])) $U['rejected']='';
	if(isSet($_REQUEST['multi']) && $_REQUEST['multi']=="on"){
		echo "<td valign=\"top\"><textarea name=\"message\" wrap=\"virtual\" rows=\"$U[boxheight]\" cols=\"$U[boxwidth]\" style=\"$U[style]\">$U[rejected]</textarea></td>";
	}else{
		echo "<td valign=\"top\"><input type=\"text\" name=\"message\" value=\"$U[rejected]\" size=\"$U[boxwidth]\" maxlength=\"$C[maxmessage]\" style=\"$U[style]\"></td>";
	}
	echo '<td valign="top">'.submit($I['talkto'])."</td><td valign=\"top\"><select name=\"sendto\" size=\"1\">";
	echo '<option '; if(isSet($_REQUEST['sendto']) && $_REQUEST['sendto']=='*') echo 'selected '; echo "value=\"*\">-$I[toall]-</option>";
	if($U['status']>=3){
		echo '<option ';
		if(isSet($_REQUEST['sendto']) && $_REQUEST['sendto']=='?') echo 'selected ';
		echo "value=\"?\">-$I[tomem]-</option>";
	}
	if($U['status']>=5){
		echo '<option ';
		if(isSet($_REQUEST['sendto']) && $_REQUEST['sendto']=='#') echo 'selected ';
		echo "value=\"#\">-$I[tostaff]-</option>";
	}
	if($U['status']>=6){
		echo '<option ';
		if(isSet($_REQUEST['sendto']) && $_REQUEST['sendto']=='&') echo 'selected ';
		echo "value=\"&\">-$I[toadmin]-</option>";
	}
	$ignored=array();
	$stmt=mysqli_prepare($mysqli, '(SELECT `by` FROM `ignored` WHERE `ignored`=? OR `by`=?) UNION (SELECT `ignored` FROM `ignored` WHERE `ignored`=? OR `by`=?)');
	mysqli_stmt_bind_param($stmt, 'ssss', $U['nickname'], $U['nickname'], $U['nickname'], $U['nickname']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_bind_result($stmt, $ign);
	while(mysqli_stmt_fetch($stmt)){
		$ignored[]=$ign;
	}
	mysqli_stmt_close($stmt);
	array_multisort(array_map('strtolower', array_keys($P)), SORT_ASC, SORT_STRING, $P);
	foreach($P as $user){
		if($U['nickname']!==$user[0] && !in_array($user[0], $ignored)){
			echo '<option ';
			if(isSet($_REQUEST['sendto']) && $_REQUEST['sendto']==$user[0]) echo 'selected ';
			echo "value=\"$user[0]\" style=\"$user[2]\">$user[0]</option>";
		}
	}
	echo '</select>';
	if($U['status']>=5 || ($C['memkick'] && $countmods==0 && $U['status']>=3)){
		echo "<input type=\"checkbox\" name=\"kick\" id=\"kick\" value=\"kick\"><label for=\"kick\">&nbsp;$I[kick]</label>";
		echo "<input type=\"checkbox\" name=\"what\" id=\"what\" value=\"purge\" checked><label for=\"what\">&nbsp;$I[alsopurge]</label>";
	}
	echo '</td></tr></table></form></td></tr><tr><td height="8"></td></tr><tr><td align="center"><table cellspacing="0"><tr><td>';
	echo frmpst('delete', 'last').submit($I['dellast']).'</form></td><td>'.frmpst('delete', 'all').submit($I['delall']).'</form></td><td width="10"></td><td>';
	if(isSet($_REQUEST['multi']) && $_REQUEST['multi']=='on'){
		$switch=$I['switchsingle'];
		$multi='';
	}else{
		$switch=$I['switchmulti'];
		$multi='on';
	}
	echo frmpst('post').@hidden('sendto', $_REQUEST['sendto']).hidden('multi', $multi).submit($switch).'</form></td>';
	echo '</tr></table></td></tr></table></center>';
	print_end();
}

function send_help(){
	global $U, $C, $H, $I;
	print_start();
	echo "<h2>$I[rules]</h2>".get_setting('rulestxt')."<br><br><hr><h2>$I[help]</h2>$I[helpguest]";
	if($C['imgembed'] || $C['vidembed']) echo "<br>$I[helpembed]";
	if($U['status']>=3){
		echo "<br>$I[helpmem]<br>";
		if($U['status']>=5){
			echo "<br>$I[helpmod]<br>";
			if($U['status']>=7) echo "<br>$I[helpadm]<br>";
		}
	}
	echo "<br><hr><center>$H[backtochat]";
	print_credits();
	print_end();
}

function send_profile($arg=''){
	global $U, $F, $H, $I, $P, $C, $mysqli;
	print_start();
	echo "<center><$H[form]>".hidden('action', 'profile').hidden('do', 'save').hidden('session', $U['session'])."<h2>$I[profile]</h2><i>$arg</i><table cellspacing=\"0\">";
	thr();
	array_multisort(array_map('strtolower', array_keys($P)), SORT_ASC, SORT_STRING, $P);
	$ignored=array();
	$stmt=mysqli_prepare($mysqli, 'SELECT `ignored` FROM `ignored` WHERE `by`=?');
	mysqli_stmt_bind_param($stmt, 's', $U['nickname']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_store_result($stmt);
	if(mysqli_stmt_num_rows($stmt)>0){
		mysqli_stmt_bind_result($stmt, $ign);
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[unignore]</b></td><td align=\"right\"><table cellspacing=\"0\">";
		echo "<tr><td>&nbsp;</td><td><select name=\"unignore\" size=\"1\"><option value=\"\">$I[choose]</option>";
		while(mysqli_stmt_fetch($stmt)){
			$ignored[]=$ign;
			$style='';
			foreach($P as $user){
				if($ign==$user[0]){
					$style=" style=\"$user[2]\"";
					break;
				}
			}
			echo "<option value=\"$ign\"$style>$ign</option>";
		}
		echo '</select></td></tr></table></td></tr></table></td></tr>';
		thr();
	}
	mysqli_stmt_free_result($stmt);
	mysqli_stmt_close($stmt);
	if(count($P)-count($ignored)>1){
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[ignore]</b></td><td align=\"right\"><table cellspacing=\"0\">";
		echo "<tr><td>&nbsp;</td><td><select name=\"ignore\" size=\"1\"><option value=\"\">$I[choose]</option>";
		foreach($P as $user){
			if($U['nickname']!==$user[0] && !in_array($user[0], $ignored)){
				echo "<option value=\"$user[0]\" style=\"$user[2]\">$user[0]</option>";
			}
		}
		echo '</select></td></tr></table></td></tr></table></td></tr>';
		thr();
	}
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[refreshrate]</b></td><td align=\"right\"><table cellspacing=\"0\">";
	echo "<tr><td>&nbsp;</td><td><input type=\"text\" name=\"refresh\" size=\"3\" maxlength=\"3\" value=\"$U[refresh]\"></td></tr></table></td></tr></table></td></tr>";
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[fontcolour]</b> (<a href=\"$_SERVER[SCRIPT_NAME]?action=colours&amp;session=$U[session]\" target=\"view\">$I[viewexample]</a>)</td><td align=\"right\"><table cellspacing=\"0\">";
	echo "<tr><td>&nbsp;</td><td><input type=\"text\" size=\"7\" maxlength=\"6\" value=\"$U[colour]\" name=\"colour\"></td></tr></table></td></tr></table></td></tr>";
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[bgcolour]</b> (<a href=\"$_SERVER[SCRIPT_NAME]?action=colours&amp;session=$U[session]\" target=\"view\">$I[viewexample]</a>)</td><td align=\"right\"><table cellspacing=\"0\">";
	echo "<tr><td>&nbsp;</td><td><input type=\"text\" size=\"7\" maxlength=\"6\" value=\"$U[bgcolour]\" name=\"bgcolour\"></td></tr></table></td></tr></table></td></tr>";
	thr();
	if($U['status']>=3){
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[fontface]</b></td><td align=\"right\"><table cellspacing=\"0\">";
		echo "<tr><td>&nbsp;</td><td><select name=\"font\" size=\"1\"><option value=\"\">* $I[roomdefault] *</option>";
		foreach($F as $name=>$font){
			echo '<option style="'.get_style($font).'" ';
			if(preg_match("/$font/", $U['fontinfo'])) echo 'selected ';
			echo "value=\"$name\">$name</option>";
		}
		echo "</select></td><td>&nbsp;</td><td><input type=\"checkbox\" name=\"bold\" id=\"bold\" value=\"on\"";
		if(preg_match('/<i?bi?>/', $U['fontinfo'])) echo ' checked';
		echo "></td><td><label for=\"bold\"><b>$I[bold]</b></label></td><td>&nbsp;</td><td><input type=\"checkbox\" name=\"italic\" id=\"italic\" value=\"on\"";
		if(preg_match('/<b?ib?>/', $U['fontinfo'])) echo ' checked';
		echo "></td><td><label for=\"italic\"><i>$I[italic]</i></label></td></tr></table></td></tr></table></td></tr>";
		thr();
	}
	echo "<tr><td align=\"center\">$U[displayname]&nbsp;: ".style_this($I['fontexample'], $U['fontinfo']).'</td></tr>';
	thr();
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[timestamps]</b></td><td align=\"right\"><table cellspacing=\"0\">";
	echo "<tr><td>&nbsp;</td><td><input type=\"checkbox\" name=\"timestamps\" id=\"timestamps\" value=\"on\"";
	if($U['timestamps']) echo ' checked';
	echo "></td><td><label for=\"timestamps\"><b>$I[timestamps]</b></label></td></tr></table></td></tr></table></td></tr>";
	thr();
	if($C['imgembed'] || $C['vidembed']){
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[embed]</b></td><td align=\"right\"><table cellspacing=\"0\">";
		echo "<tr><td>&nbsp;</td><td><input type=\"checkbox\" name=\"embed\" id=\"embed\" value=\"on\"";
		if($U['embed']) echo ' checked';
		echo "></td><td><label for=\"embed\"><b>$I[embed]</b></label></td></tr></table></td></tr></table></td></tr>";
		thr();
	}
	echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[pbsize]</b></td><td align=\"right\"><table cellspacing=\"0\">";
	echo "<tr><td>&nbsp;</td><td>$I[width]</td><td><input type=\"text\" name=\"boxwidth\" size=\"3\" maxlength=\"3\" value=\"$U[boxwidth]\"></td>";
	echo "<td>&nbsp;</td><td>$I[height]</td><td><input type=\"text\" name=\"boxheight\" size=\"3\" maxlength=\"3\" value=\"$U[boxheight]\"></td>";
	echo '</tr></table></td></tr></table></td></tr>';
	thr();
	if($U['status']>=5){
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[nbsize]</b></td><td align=\"right\"><table cellspacing=\"0\">";
		echo "<tr><td>&nbsp;</td><td>$I[width]</td><td><input type=\"text\" name=\"notesboxwidth\" size=\"3\" maxlength=\"3\" value=\"$U[notesboxwidth]\"></td>";
		echo "<td>&nbsp;</td><td>$I[height]</td><td><input type=\"text\" name=\"notesboxheight\" size=\"3\" maxlength=\"3\" value=\"$U[notesboxheight]\"></td>";
		echo '</tr></table></td></tr></table></td></tr>';
		thr();
	}
	if($U['status']>=2){
		echo "<tr><td><table cellspacing=\"0\" width=\"100%\"><tr><td align=\"left\"><b>$I[changepass]</b></td></tr>";
		echo "<tr><td align=\"right\"><table cellspacing=\"0\"><tr><td>&nbsp;</td><td align=\"left\">$I[oldpass]</td><td><input type=\"password\" name=\"oldpass\" size=\"20\"></td></tr>";
		echo "<tr><td>&nbsp;</td><td align=\"left\">$I[newpass]</td><td><input type=\"password\" name=\"newpass\" size=\"20\"></td></tr>";
		echo "<tr><td>&nbsp;</td><td align=\"left\">$I[confirmpass]</td><td><input type=\"password\" name=\"confirmpass\" size=\"20\"></td></tr></table></td></tr></table></td></tr>";
		thr();
	}
	echo '<tr><td align="center">'.submit($I['savechanges'])."</td></tr></table></form><br>$H[backtochat]</center>";
	print_end();
}

function send_controls(){
	global $U, $H, $I;
	print_start();
	echo '<center><table cellspacing="0"><tr>';
	echo "<td><$H[form] target=\"post\">".hidden('action', 'post').hidden('session', $U['session']).submit($I['reloadpb']).'</form></td>';
	echo "<td><$H[form] target=\"view\">".hidden('action', 'view').hidden('session', $U['session']).hidden('nocache', '000001').submit($I['reloadmsgs']).'</form></td>';
	echo "<td><$H[form] target=\"view\">".hidden('action', 'profile').hidden('session', $U['session']).submit($I['chgprofile']).'</form></td>';
	if($U['status']>=5) echo "<td><$H[form] target=\"view\">".hidden('action', 'admin').hidden('session', $U['session']).submit($I['adminbtn']).'</form></td>';
	if($U['status']>=6) echo "<td><$H[form] target=\"view\">".hidden('action', 'admnotes').hidden('session', $U['session']).submit($I['admnotes']).'</form></td>';
	if($U['status']>=5) echo "<td><$H[form] target=\"view\">".hidden('action', 'notes').hidden('session', $U['session']).submit($I['notes']).'</form></td>';
	if($U['status']>=3) echo "<td><$H[form] target=\"_blank\">".hidden('action', 'login').hidden('session', $U['session']).submit($I['clone']).'</form></td>';
	echo "<td><$H[form] target=\"view\">".hidden('action', 'help').hidden('session', $U['session']).submit($I['randh']).'</form></td>';
	echo "<td><$H[form] target=\"_parent\">".hidden('action', 'logout').hidden('session', $U['session']).submit($I['exit']).'</form></td>';
	echo '</tr></table></center>';
	print_end();
}

function send_logout(){
	global $U, $H, $I;
	print_start();
	echo '<center><h1>'.sprintf($I['bye'], $U['displayname'])."</h1>$H[backtologin]</center>";
	print_end();
}

function send_colours(){
	global $H, $I;
	print_start();
	echo "<center><h2>$I[colourtable]</h2><tt>";
	for($red=0x00;$red<=0xFF;$red+=0x33){
		for($green=0x00;$green<=0xFF;$green+=0x33){
			for($blue=0x00;$blue<=0xFF;$blue+=0x33){
				$hcol=sprintf('%02X', $red).sprintf('%02X', $green).sprintf('%02X', $blue);
				echo "<font color=\"#$hcol\"><b>$hcol</b></font> ";
			}
			echo '<br>';
		}
		echo '<br>';
	}
	echo "</tt><$H[form]>".hidden('action', 'profile').hidden('session', $_REQUEST['session']).submit($I['backtoprofile'], ' style="background-color:#004400;color:#FFFFFF;"').'</form></center>';
	print_end();
}

function send_login(){
	global $C, $H, $I, $L;
	setcookie('test', '1');
	print_start();
	echo "<center><h1>$C[chatname]</h1><$H[form] target=\"_parent\">".hidden('action', 'login');
	echo "<table border=\"2\" width=\"1\" rules=\"none\"><tr><td align=\"left\">$I[nick]</td><td align=\"right\"><input type=\"text\" name=\"nick\" size=\"15\"></td></tr>";
	echo "<tr><td align=\"left\">$I[pass]</td><td align=\"right\"><input type=\"password\" name=\"pass\" size=\"15\"></td></tr>";
	if($C['enablecaptcha']) send_captcha();
	if(get_setting('guestaccess')>0){
		echo "<tr><td colspan=\"2\" align=\"center\">$I[choosecol]<br><select style=\"text-align:center;\" name=\"colour\"><option value=\"\">* $I[randomcol] *</option>";
		print_colours();
		echo '</select></td></tr>';
	}else{
		echo "<tr><td colspan=\"2\" align=\"center\">$I[noguests]</td></tr>";
	}
	echo '<tr><td colspan="2" align="center">'.submit($I['enter'])."</td></tr></table></form>";
	get_nowchatting();
	echo "<h2>$I[rules]</h2><b>".get_setting('rulestxt')."</b><br><br><p>$I[changelang]";
	foreach($L as $lang=>$name){
		echo " <a href=\"$_SERVER[SCRIPT_NAME]?lang=$lang\">$name</a>";
	}
	print_credits();
	print_end();
}

function send_error($err){
	global $H, $I;
	print_start('body{color:#FF0033;}');
	echo "<h2>$I[error] $err</h2>$H[backtologin]";
	print_end();
}

function print_chatters(){
	global $U, $M, $H, $G, $I, $mysqli;
	echo '<table cellspacing="0"><tr>';
	if($U['status']>=5 && get_setting('guestaccess')==3){
		$result=mysqli_query($mysqli, 'SELECT COUNT(*) FROM `sessions` WHERE `entry`!=\'0\' AND `status`=\'1\' ORDER BY `entry`');
		$temp=mysqli_fetch_array($result, MYSQLI_NUM);
		if($temp[0]>0) echo "<td valign=\"top\"><$H[form]>".hidden('action', 'admin').hidden('do', 'approve').hidden('session', $_REQUEST['session']).submit(sprintf($I['approveguests'], $temp[0])).'</form></td><td>&nbsp;</td>';
	}
	if(isSet($M[0])){
		echo "<td valign=\"top\"><b>$I[members]</b></td><td>&nbsp;</td><td valign=\"top\">".implode(' &nbsp; ', $M).'</td>';
		if(isSet($G[0])) echo '<td>&nbsp;&nbsp;</td>';
	}
	if(isSet($G[0])) echo "<td valign=\"top\"><b>$I[guests]</b></td><td>&nbsp;</td><td valign=\"top\">".implode(' &nbsp; ', $G).'</td>';
	echo '</tr></table>';
}

function print_memberslist(){
	global $A;
	read_members();
	array_multisort(array_map('strtolower', array_keys($A)), SORT_ASC, SORT_STRING, $A);
	foreach($A as $member){
		echo "<option value=\"$member[0]\" style=\"$member[2]\">$member[0]";
		if($member[1]==0) echo ' (!)';
		elseif($member[1]==2) echo ' (G)';
		elseif($member[1]==5 || $member[1]==6) echo ' (M)';
		elseif($member[1]>=7) echo ' (A)';
		echo '</option>';
	}
}

//  session management

function create_session(){
	global $U, $C, $I, $mysqli;
	$U['nickname']=cleanup_nick($_REQUEST['nick']);
	$U['passhash']=md5(sha1(md5($U['nickname'].$_REQUEST['pass'])));
	$U['colour']=$_REQUEST['colour'];
	$U['status']=1;
	check_member();
	add_user_defaults();
	if($C['enablecaptcha'] && ($U['status']==1 || !$C['dismemcaptcha'])){
		$captcha=explode(',', openssl_decrypt(base64_decode($_REQUEST['challenge']), 'aes-128-cbc', $C['captchapass'], 0, '1234567890123456'));
		if(current($captcha)!==$_REQUEST['captcha']) send_error($I['wrongcaptcha']);
		$stmt=mysqli_prepare($mysqli, 'SELECT * FROM `captcha` WHERE `id`=?');
		mysqli_stmt_bind_param($stmt, 'd', end($captcha));
		mysqli_stmt_execute($stmt);
		mysqli_stmt_store_result($stmt);
		if(mysqli_stmt_num_rows($stmt)==0) send_error($I['captchatime']);
		mysqli_stmt_free_result($stmt);
		mysqli_stmt_close($stmt);
		$stmt=mysqli_prepare($mysqli, 'DELETE FROM `captcha` WHERE `id`=? OR `time`<\''.(time()-60*10)."'");
		mysqli_stmt_bind_param($stmt, 'd', end($captcha));
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
	}
	if($U['status']==1){
		if(!valid_nick($U['nickname'])) send_error(sprintf($I['invalnick'], $C['maxname']));
		if(!valid_pass($_REQUEST['pass'])) send_error(sprintf($I['invalpass'], $C['minpass']));
		$ga=get_setting('guestaccess');
		if($ga==0) send_error($I['noguests']);
	}
	write_new_session();
}

function write_new_session(){
	global $U, $C, $I, $mysqli;
	// read and update current sessions
	$lines=parse_sessions();
	$sids; $inuse=0; $reentry=0;
	if(isSet($lines)){
		foreach($lines as $temp){
			$sids[$temp['session']]=1;// collect all existing ids
			if($temp['nickname']==$U['nickname']){// nick already here?
				if($U['passhash']==$temp['passhash']){
					$U=$temp;
					add_user_defaults();
					setcookie($C['cookiename'], $U['session']);
					$reentry=1;
				}else{
					$inuse=1;
				}
			}
		}
	}
	// create new session:
	if($inuse==0 && $reentry==0){
		do{
			$U['session']=md5(time().rand().$U['nickname']);
		}while(isSet($sids[$U['session']]));// check for hash collision
		$stmt=mysqli_prepare($mysqli, 'INSERT INTO `sessions`(`session`, `nickname`, `displayname`, `status`, `refresh`, `fontinfo`, `style`, `lastpost`, `passhash`, `postid`, `boxwidth`, `boxheight`, `useragent`, `bgcolour`, `notesboxwidth`, `notesboxheight`, `entry`, `timestamps`, `embed`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
		mysqli_stmt_bind_param($stmt, 'sssddssdsdddssddddd', $U['session'], $U['nickname'], $U['displayname'], $U['status'], $U['refresh'], $U['fontinfo'], $U['style'], $U['lastpost'], $U['passhash'], $U['postid'], $U['boxwidth'], $U['boxheight'], $U['useragent'], $U['bgcolour'], $U['notesboxwidth'], $U['notesboxheight'], $U['entry'], $U['timestamps'], $U['embed']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		setcookie($C['cookiename'], $U['session']);
		if($C['msglogin'] && $U['status']>=3) add_system_message(sprintf(get_setting('msgenter'), $U['displayname']));
	}elseif($inuse){
		send_error($I['wrongpass']);
	}elseif($U['status']==0){
		setcookie($C['cookiename'], false);
		send_error("$I[kicked]<br>$U[kickmessage]");
	}
}

function approve_session(){
	global $mysqli;
	if(isSet($_REQUEST['what'])){
		if($_REQUEST['what']=='allowchecked' && isSet($_REQUEST['csid'])){
			$stmt=mysqli_prepare($mysqli, 'UPDATE `sessions` SET `entry`=\'0\' WHERE `nickname`=?');
			foreach($_REQUEST['csid'] as $nick){
				mysqli_stmt_bind_param($stmt, 's', $nick);
				mysqli_stmt_execute($stmt);
			}
			mysqli_stmt_close($stmt);
		}elseif($_REQUEST['what']=='allowall' && isSet($_REQUEST['alls'])){
			$stmt=mysqli_prepare($mysqli, 'UPDATE `sessions` SET `entry`=\'0\' WHERE `nickname`=?');
			foreach($_REQUEST['alls'] as $nick){
				mysqli_stmt_bind_param($stmt, 's', $nick);
				mysqli_stmt_execute($stmt);
			}
				mysqli_stmt_close($stmt);
		}elseif($_REQUEST['what']=='denychecked' && isSet($_REQUEST['csid'])){
			$stmt=mysqli_prepare($mysqli, 'UPDATE `sessions` SET `lastpost`=\''.(60*($C['kickpenalty']-$C['guestsexpire'])+time()).'\', `status`=\'0\', `kickmessage`=? WHERE `nickname`=? AND `status`=\'1\'');
			foreach($_REQUEST['csid'] as $nick){
				mysqli_stmt_bind_param($stmt, 'ss', $_REQUEST['kickmessage'], $nick);
				mysqli_stmt_execute($stmt);
			}
			mysqli_stmt_close($stmt);
		}elseif($_REQUEST['what']=='denyall' && isSet($_REQUEST['alls'])){
			$stmt=mysqli_prepare($mysqli, 'UPDATE `sessions` SET `lastpost`=\''.(60*($C['kickpenalty']-$C['guestsexpire'])+time()).'\', `status`=\'0\', `kickmessage`=? WHERE `nickname`=? AND `status`=\'1\'');
			foreach($_REQUEST['alls'] as $nick){
				mysqli_stmt_bind_param($stmt, 'ss', $_REQUEST['kickmessage'], $nick);
				mysqli_stmt_execute($stmt);
			}
			mysqli_stmt_close($stmt);
		}
	}
}

function check_login(){
	global $mysqli, $C, $U, $I, $M;
	if(isSet($_POST['session'])){
		$stmt=mysqli_prepare($mysqli, 'SELECT `session`, `nickname`, `displayname`, `status`, `refresh`, `fontinfo`, `style`, `lastpost`, `passhash`, `postid`, `boxwidth`, `boxheight`, `useragent`, `kickmessage`, `bgcolour`, `notesboxheight`, `notesboxwidth`, `entry`, `timestamps`, `embed` FROM `sessions` WHERE `session`=?');
		mysqli_stmt_bind_param($stmt, 's', $_POST['session']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_bind_result($stmt, $U['session'], $U['nickname'], $U['displayname'], $U['status'], $U['refresh'], $U['fontinfo'], $U['style'], $U['lastpost'], $U['passhash'], $U['postid'], $U['boxwidth'], $U['boxheight'], $U['useragent'], $U['kickmessage'], $U['bgcolour'], $U['notesboxheight'], $U['notesboxwidth'], $U['entry'], $U['timestamps'], $U['embed']);
		if(mysqli_stmt_fetch($stmt)){
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
		mysqli_stmt_close($stmt);
	}else{
		create_session();
	}
	if($U['status']==1){
		$ga=get_setting('guestaccess');
		if(($ga==2 || $ga==3) && count($M)>0){
			$stmt=mysqli_prepare($mysqli, 'UPDATE `sessions` SET `entry`=\''.time().'\' WHERE `session`=?');
			mysqli_stmt_bind_param($stmt, 's', $U['session']);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			$_REQUEST['session']=$U['session'];
			send_waiting_room();
		}
	}
}

function kill_session(){
	global $U, $C, $I, $mysqli;
	parse_sessions();
	setcookie($C['cookiename'], false);
	if(!isSet($U['session'])) send_error($I['expire']);
	if($U['status']==0) send_error("$I[kicked]<br>$U[kickmessage]");
	$stmt=mysqli_prepare($mysqli, 'DELETE FROM `sessions` WHERE `session`=?');
	mysqli_stmt_bind_param($stmt, 's', $U['session']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	if($U['status']==1){
		$stmt=mysqli_prepare($mysqli, 'UPDATE `messages` SET `poster`=\'\' WHERE `poster`=? AND `poststatus`=\'9\'');
		mysqli_stmt_bind_param($stmt, 's', $U['nickname']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		$stmt=mysqli_prepare($mysqli, 'UPDATE `messages` SET `recipient`=\'\' WHERE `recipient`=? AND `poststatus`=\'9\'');
		mysqli_stmt_bind_param($stmt, 's', $U['nickname']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		$stmt=mysqli_prepare($mysqli, 'DELETE FROM `ignored` WHERE `ignored`=? OR `by`=?');
		mysqli_stmt_bind_param($stmt, 'ss', $U['nickname'], $U['nickname']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
	}
	elseif($C['msglogout'] && $U['status']>=3) add_system_message(sprintf(get_setting('msgexit'), $U['displayname']));
}

function kick_chatter($names, $mes, $purge){
	global $C, $U, $P, $mysqli;
	$lonick='';
	$lines=parse_sessions();
	$stmt=mysqli_prepare($mysqli, 'UPDATE `sessions` SET `lastpost`=\''.(60*($C['kickpenalty']-$C['guestsexpire'])+time()).'\', `status`=\'0\', `kickmessage`=? WHERE `session`=? AND `status`!=\'0\'');
	$i=0;
	if(isSet($lines)){
		foreach($names as $name){
			foreach($lines as $temp){
				if(($temp['nickname']==$U['nickname'] && $U['nickname']==$name) || ($U['status']>$temp['status'] && (($temp['nickname']==$name && $temp['status']>0) || ($name=='&' && $temp['status']==1)))){
					mysqli_stmt_bind_param($stmt, 'ss', $mes, $temp['session']);
					mysqli_stmt_execute($stmt);
					if($purge) del_all_messages($temp['nickname']);
					$lonick.="$temp[displayname], ";
					$i++;
					unset($P[$name]);
				}
			}
		}
	}
	mysqli_stmt_close($stmt);
	if($C['msgkick']){
		if($lonick!==''){
			if($names[0]=='&'){
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
	}
	if($lonick!=='') return true;
	return false;
}

function logout_chatter($names){
	global $U, $P, $mysqli;
	$lines=parse_sessions();
	$stmt=mysqli_prepare($mysqli, 'DELETE FROM `sessions` WHERE `session`=? AND `status`<? AND `status`!=\'0\'');
	$stmt1=mysqli_prepare($mysqli, 'UPDATE `messages` SET `poster`=\'\' WHERE `poster`=? AND `poststatus`=\'9\'');
	$stmt2=mysqli_prepare($mysqli, 'UPDATE `messages` SET `recipient`=\'\' WHERE `recipient`=? AND `poststatus`=\'9\'');
	$stmt3=mysqli_prepare($mysqli, 'DELETE FROM `ignored` WHERE `ignored`=? OR `by`=?');
	if(isSet($lines)){
		foreach($names as $name){
			foreach($lines as $temp){
				if($temp['nickname']==$name || ($name=='&' && $temp['status']==1)){
					mysqli_stmt_bind_param($stmt, 'sd', $temp['session'], $U['status']);
					mysqli_stmt_execute($stmt);
					if($temp['status']==1){
						mysqli_stmt_bind_param($stmt1, 's', $temp['nickname']);
						mysqli_stmt_bind_param($stmt2, 's', $temp['nickname']);
						mysqli_stmt_bind_param($stmt3, 'ss', $temp['nickname'], $temp['nickname']);
						mysqli_stmt_execute($stmt1);
						mysqli_stmt_execute($stmt2);
						mysqli_stmt_execute($stmt3);
					}
					unset($P[$name]);
				}
			}
		}
	}
	mysqli_stmt_close($stmt);
	mysqli_stmt_close($stmt1);
	mysqli_stmt_close($stmt2);
	mysqli_stmt_close($stmt3);
}

function update_session(){
	global $U, $C, $I, $mysqli;
	if($U['postid']==$_REQUEST['postid']){// ignore double post=reload from browser or proxy
		$_REQUEST['message']='';
	}elseif(time()-$U['lastpost']<=1){// time between posts too short, reject!
		$U['rejected']=$_REQUEST['message'];
		$_REQUEST['message']='';
	}else{// valid post
		$U['postid']=substr($_REQUEST['postid'], 0, 6);
		$U['lastpost']=time();
		$stmt=mysqli_prepare($mysqli, 'UPDATE `sessions` SET `lastpost`=?, `postid`=? WHERE `session`=?');
		mysqli_stmt_bind_param($stmt, 'dds', $U['lastpost'], $U['postid'], $U['session']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
	}
}

function check_session(){
	global $U, $C, $I;
	parse_sessions();
	if(!isSet($U['session'])){
		setcookie($C['cookiename'], false);
		send_error($I['expire']);
	}
	if($U['status']==0){
		setcookie($C['cookiename'], false);
		send_error("$I[kicked]<br>$U[kickmessage]");
	}
}

function get_nowchatting(){
	global $M, $G, $P, $I;
	parse_sessions();
	echo sprintf($I['curchat'], count($P)).'<br>'.implode(' &nbsp; ', $M).' &nbsp; '.implode(' &nbsp; ', $G);
}

function parse_sessions(){
	global $U, $P, $M, $G, $C, $mysqli, $countmods;
	$result=mysqli_query($mysqli, 'SELECT `nickname`, `status` FROM `sessions` WHERE (`lastpost`<\''.(time()-60*$C['guestsexpire']).'\' AND `status`<=\'2\') OR (`lastpost`<\''.(time()-60*$C['sessionexpire']).'\' AND `status`>\'2\')');
	if(mysqli_num_rows($result)>0){
		$stmt=mysqli_prepare($mysqli, 'DELETE FROM `sessions` WHERE `nickname`=?');
		$stmt1=mysqli_prepare($mysqli, 'UPDATE `messages` SET `poster`=\'\' WHERE `poster`=? AND `poststatus`=\'9\'');
		$stmt2=mysqli_prepare($mysqli, 'UPDATE `messages` SET `recipient`=\'\' WHERE `recipient`=? AND `poststatus`=\'9\'');
		$stmt3=mysqli_prepare($mysqli, 'DELETE FROM `ignored` WHERE `ignored`=? OR `by`=?');
		while($temp=mysqli_fetch_array($result, MYSQLI_ASSOC)){
			mysqli_stmt_bind_param($stmt, 's', $temp['nickname']);
			mysqli_stmt_execute($stmt);
			if($temp['status']<=1){
				mysqli_stmt_bind_param($stmt1, 's', $temp['nickname']);
				mysqli_stmt_bind_param($stmt2, 's', $temp['nickname']);
				mysqli_stmt_bind_param($stmt3, 'ss', $temp['nickname'], $temp['nickname']);
				mysqli_stmt_execute($stmt1);
				mysqli_stmt_execute($stmt2);
				mysqli_stmt_execute($stmt3);
			}
		}
		mysqli_stmt_close($stmt);
		mysqli_stmt_close($stmt1);
		mysqli_stmt_close($stmt2);
		mysqli_stmt_close($stmt3);
	}
	$result=mysqli_query($mysqli, 'SELECT * FROM `sessions` WHERE `entry`=\'0\' ORDER BY `status` DESC, `lastpost` DESC');
	if(mysqli_num_rows($result)>0){
		while($line=mysqli_fetch_array($result, MYSQLI_ASSOC)) $lines[]=$line;
		if(isSet($_REQUEST['session'])){
			foreach($lines as $temp){
				if($temp['session']==$_REQUEST['session']){
					$U=$temp;
					add_user_defaults();
				}
			}
		}
		$countmods=0;
		$G=array();
		$M=array();
		$P=array();
		foreach($lines as $temp){
			if($temp['status']==1 || $temp['status']==2){
				$P[$temp['nickname']]=[$temp['nickname'], $temp['status'], $temp['style']];
				$G[]=$temp['displayname'];
			}elseif($temp['status']>2){
				$P[$temp['nickname']]=[$temp['nickname'], $temp['status'], $temp['style']];
				$M[]=$temp['displayname'];
				if($temp['status']>=5) $countmods++;
			}
		}
		return $lines;
	}
	return;
}

//  member handling

function check_member(){
	global $U, $I, $mysqli;
	$stmt=mysqli_prepare($mysqli, 'SELECT `nickname`, `passhash`, `status`, `refresh`, `colour`, `bgcolour`, `fontface`, `fonttags`, `boxwidth`, `boxheight`, `notesboxwidth`, `notesboxheight`, `lastlogin`, `timestamps`, `embed` FROM `members` WHERE `nickname`=?');
	mysqli_stmt_bind_param($stmt, 's', $U['nickname']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_bind_result($stmt, $temp['nickname'], $temp['passhash'], $temp['status'], $temp['refresh'], $temp['colour'], $temp['bgcolour'], $temp['fontface'], $temp['fonttags'], $temp['boxwidth'], $temp['boxheight'], $temp['notesboxwidth'], $temp['notesboxheight'], $temp['lastlogin'], $temp['timestamps'], $temp['embed']);
	if(mysqli_stmt_fetch($stmt)){
		mysqli_stmt_close($stmt);
		if($temp['passhash']==$U['passhash']){
			$U=$temp;
			$stmt=mysqli_prepare($mysqli, 'UPDATE `members` SET `lastlogin`=? WHERE `nickname`=?');
			mysqli_stmt_bind_param($stmt, 'ds', time(), $U['nickname']);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
		}else{
			send_error($I['wrongpass']);
		}
	}else{
		mysqli_stmt_close($stmt);
	}
}

function read_members(){
	global $A, $F, $mysqli;
	$result=mysqli_query($mysqli, 'SELECT * FROM `members`');
	if(mysqli_num_rows($result)>0){
		while($temp=mysqli_fetch_array($result, MYSQLI_ASSOC)){
			$A[$temp['nickname']][0]=$temp['nickname'];
			$A[$temp['nickname']][1]=$temp['status'];
			$A[$temp['nickname']][2]=@get_style("#$temp[colour] {$F[$temp['fontface']]} <$temp[fonttags]>");
		}
	}
}

function register_guest($status){
	global $P, $U, $C, $I, $mysqli;
	if($_REQUEST['name']=='') send_admin();
	if(!isSet($P[$_REQUEST['name']])) send_admin(sprintf($I['cantreg'], $_REQUEST['name']));
	$stmt=mysqli_prepare($mysqli, 'SELECT `session`, `nickname`, `displayname`, `passhash`, `refresh`, `fontinfo`, `bgcolour`, `boxwidth`, `boxheight`, `notesboxwidth`, `notesboxheight`, `timestamps`, `embed` FROM `sessions` WHERE `nickname`=? AND `status`=\'1\'');
	mysqli_stmt_bind_param($stmt, 's', $_REQUEST['name']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_bind_result($stmt, $reg['session'], $reg['nickname'], $reg['displayname'], $reg['passhash'], $reg['refresh'], $reg['fontinfo'], $reg['bgcolour'], $reg['boxwidth'], $reg['boxheight'], $reg['notesboxwidth'], $reg['notesboxheight'], $reg['timestamps'], $reg['embed']);
	if(mysqli_stmt_fetch($stmt)){
		mysqli_stmt_close($stmt);
		$reg['status']=$status;
		if(preg_match('/#([a-f0-9]{6})/i', $reg['fontinfo'], $match)) $reg['colour']=$match[1];
		else $reg['colour']=$C['coltxt'];
		$stmt=mysqli_prepare($mysqli, 'UPDATE `sessions` SET `status`=? WHERE `session`=?');
		mysqli_stmt_bind_param($stmt, 'ds', $reg['status'], $reg['session']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
	}else{
		mysqli_stmt_close($stmt);
	}
	if(!isSet($reg['status'])) send_admin(sprintf($I['cantreg'], $_REQUEST['name']));
	$stmt=mysqli_prepare($mysqli, 'SELECT * FROM `members` WHERE `nickname`=?');
	mysqli_stmt_bind_param($stmt, 's', $_REQUEST['name']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_store_result($stmt);
	if(mysqli_stmt_num_rows($stmt)>0) send_admin(sprintf($I['alreadyreged'], $_REQUEST['name']));
	mysqli_stmt_free_result($stmt);
	mysqli_stmt_close($stmt);
	$stmt=mysqli_prepare($mysqli, 'INSERT INTO `members`(`nickname`, `passhash`, `status`, `refresh`, `colour`, `bgcolour`, `boxwidth`, `boxheight`, `notesboxwidth`, `notesboxheight`, `regedby`, `timestamps`, `embed`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
	mysqli_stmt_bind_param($stmt, 'ssddssddddsdd', $reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['colour'], $reg['bgcolour'], $reg['boxwidth'], $reg['boxheight'], $reg['notesboxwidth'], $reg['notesboxheight'], $U['nickname'], $reg['timestamps'], $reg['embed']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	if($reg['status']==3) add_system_message(sprintf(get_setting('msgmemreg'), $reg['displayname']));
	else add_system_message(sprintf(get_setting('msgsureg'), $reg['displayname']));
}

function register_new(){
	global $C, $U, $P, $I, $mysqli;
	$_REQUEST['name']=cleanup_nick($_REQUEST['name']);
	if($_REQUEST['name']=='') send_admin();
	if(isSet($P[$_REQUEST['name']])) send_admin(sprintf($I['cantreg'], $_REQUEST['name']));
	if(!valid_nick($_REQUEST['name'])) send_admin(sprintf($I['invalnick'], $C['maxname']));
	if(!valid_pass($_REQUEST['pass'])) send_admin(sprintf($I['invalpass'], $C['minpass']));
	$stmt=mysqli_prepare($mysqli, 'SELECT * FROM `members` WHERE `nickname`=?');
	mysqli_stmt_bind_param($stmt, 's', $_REQUEST['name']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_store_result($stmt);
	if(mysqli_stmt_num_rows($stmt)>0) send_admin(sprintf($I['alreadyreged'], $_REQUEST['name']));
	mysqli_stmt_free_result($stmt);
	mysqli_stmt_close($stmt);
	$reg=array(
		'nickname'	=>$_REQUEST['name'],
		'passhash'	=>md5(sha1(md5($_REQUEST['name'].$_REQUEST['pass']))),
		'status'	=>3,
		'refresh'	=>$C['defaultrefresh'],
		'colour'	=>$C['coltxt'],
		'bgcolour'	=>$C['colbg'],
		'boxwidth'	=>$C['boxwidth'],
		'boxheight'	=>$C['boxheight'],
		'notesboxwidth'	=>$C['notesboxwidth'],
		'notesboxheight'=>$C['notesboxheight'],
		'regedby'	=>$U['nickname'],
		'timestamps'	=>$C['timestamps'],
		'embed'		=>$C['embed']
	);
	$stmt=mysqli_prepare($mysqli, 'INSERT INTO `members`(`nickname`, `passhash`, `status`, `refresh`, `colour`, `bgcolour`, `boxwidth`, `boxheight`,`notesboxwidth`, `notesboxheight`, `regedby`, `timestamps`, `embed`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
	mysqli_stmt_bind_param($stmt, 'ssddssddddsdd', $reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['colour'], $reg['bgcolour'], $reg['boxwidth'], $reg['boxheight'], $reg['notesboxwidth'], $reg['notesboxheight'], $reg['regedby'], $reg['timestamps'], $reg['embed']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	send_admin(sprintf($I['successreg'], $reg['nickname']));
}

function change_status(){
	global $U, $I, $mysqli;
	if(!isSet($_REQUEST['name']) || $_REQUEST['name']=='') send_admin();
	if($U['status']<=$_REQUEST['set'] || !preg_match('/^[023567\-]$/', $_REQUEST['set'])) send_admin(sprintf($I['cantchgstat'], $_REQUEST['name']));
	$stmt=mysqli_prepare($mysqli, 'SELECT * FROM `members` WHERE `nickname`=? AND `status`<?');
	mysqli_stmt_bind_param($stmt, 'sd', $_REQUEST['name'], $U['status']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_store_result($stmt);
	if(mysqli_stmt_num_rows($stmt)>0){
		mysqli_stmt_free_result($stmt);
		mysqli_stmt_close($stmt);
		if($_REQUEST['set']=='-'){
			$stmt=mysqli_prepare($mysqli, 'DELETE FROM `members` WHERE `nickname`=?');
			mysqli_stmt_bind_param($stmt, 's', $_REQUEST['name']);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			$stmt=mysqli_prepare($mysqli, 'UPDATE `sessions` SET `status`=\'1\' WHERE `nickname`=?');
			mysqli_stmt_bind_param($stmt, 's', $_REQUEST['name']);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			send_admin(sprintf($I['succdel'], $_REQUEST['name']));
		}else{
			$stmt=mysqli_prepare($mysqli, 'UPDATE `members` SET `status`=? WHERE `nickname`=?');
			mysqli_stmt_bind_param($stmt, 'ds', $_REQUEST['set'], $_REQUEST['name']);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			$stmt=mysqli_prepare($mysqli, 'UPDATE `sessions` SET `status`=? WHERE `nickname`=?');
			mysqli_stmt_bind_param($stmt, 'ds', $_REQUEST['set'], $_REQUEST['name']);
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
			send_admin(sprintf($I['succchg'], $_REQUEST['name']));
		}
	}else{
		send_admin(sprintf($I['cantchgstat'], $_REQUEST['name']));
		mysqli_stmt_free_result($stmt);
		mysqli_stmt_close($stmt);
	}
}

function amend_profile(){
	global $U, $F, $C;
	if(isSet($_REQUEST['refresh'])) $U['refresh']=$_REQUEST['refresh'];
	else $U['refresh']=$C['defaultrefresh'];
	if($U['refresh']<20) $U['refresh']=20;
	if($U['refresh']>150) $U['refresh']=150;
	if(preg_match('/^[a-f0-9]{6}$/i', $_REQUEST['colour'])) $U['colour']=$_REQUEST['colour'];
	else $U['colour']=$C['coltxt'];
	if(preg_match('/^[a-f0-9]{6}$/i', $_REQUEST['bgcolour'])) $U['bgcolour']=$_REQUEST['bgcolour'];
	else $U['bgcolour']=$C['colbg'];
	$U['fonttags']='';
	if($U['status']>=3 && isSet($_REQUEST['bold'])) $U['fonttags'].='b';
	if($U['status']>=3 && isSet($_REQUEST['italic'])) $U['fonttags'].='i';
	if($U['status']>=3 && isSet($F[$_REQUEST['font']])) $U['fontface']=$_REQUEST['font'];
	@$U['fontinfo']="#$U[colour] {$F[$U['fontface']]} <$U[fonttags]>";
	if(!isSet($U['fontinfo'])) $U['fontinfo']='';
	$U['style']=get_style($U['fontinfo']);
	$U['displayname']=style_this($U['nickname'], $U['fontinfo']);
	if($_REQUEST['boxwidth']>0) $U['boxwidth']=$_REQUEST['boxwidth'];
	if($_REQUEST['boxheight']>0) $U['boxheight']=$_REQUEST['boxheight'];
	if(isSet($_REQUEST['notesboxwidth']) && $_REQUEST['notesboxwidth']>0) $U['notesboxwidth']=$_REQUEST['notesboxwidth'];
	if(isSet($_REQUEST['notesboxheight']) && $_REQUEST['notesboxheight']>0) $U['notesboxheight']=$_REQUEST['notesboxheight'];
	if(isSet($_REQUEST['timestamps'])) $U['timestamps']=true;
	else $U['timestamps']=false;
	if(isSet($_REQUEST['embed'])) $U['embed']=true;
	else $U['embed']=false;
	if($U['boxwidth']>=1000) $U['boxwidth']=40;
	if($U['boxheight']>=1000) $U['boxheight']=3;
	if($U['notesboxwidth']>=1000) $U['notesboxwidth']=80;
	if($U['notesboxheight']>=1000) $U['notesboxheight']=30;
}

function save_profile(){
	global $U, $C, $I, $mysqli;
	if(!isSet($_REQUEST['oldpass'])) $_REQUEST['oldpass']='';
	if(!isSet($_REQUEST['newpass'])) $_REQUEST['newpass']='';
	if(!isSet($_REQUEST['confirmpass'])) $_REQUEST['confirmpass']='';
	if($_REQUEST['newpass']!==$_REQUEST['confirmpass']){
		send_profile($I['noconfirm']);
	}elseif($_REQUEST['newpass']!==''){
		$U['oldhash']=md5(sha1(md5($U['nickname'].$_REQUEST['oldpass'])));
		$U['newhash']=md5(sha1(md5($U['nickname'].$_REQUEST['newpass'])));
	}else{
		$U['oldhash']=$U['newhash']=$U['passhash'];
	}
	if($U['passhash']!==$U['oldhash']) send_profile($I['wrongpass']);
	$U['passhash']=$U['newhash'];
	amend_profile();
	$stmt=mysqli_prepare($mysqli, 'UPDATE `sessions` SET `refresh`=?, `displayname`=?, `fontinfo`=?, `style`=?, `passhash`=?, `boxwidth`=?, `boxheight`=?, `bgcolour`=?, `notesboxwidth`=?, `notesboxheight`=?, `timestamps`=?, `embed`=? WHERE `session`=?');
	mysqli_stmt_bind_param($stmt, 'dssssddsdddds', $U['refresh'], $U['displayname'], $U['fontinfo'], $U['style'], $U['passhash'], $U['boxwidth'], $U['boxheight'], $U['bgcolour'], $U['notesboxwidth'], $U['notesboxheight'], $U['timestamps'], $U['embed'], $U['session']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	if($U['status']>=2){
		$stmt=mysqli_prepare($mysqli, 'UPDATE `members` SET `passhash`=?, `refresh`=?, `colour`=?, `bgcolour`=?, `fontface`=?, `fonttags`=?, `boxwidth`=?, `boxheight`=?, `notesboxwidth`=?, `notesboxheight`=?, `timestamps`=?, `embed`=? WHERE `nickname`=?');
		mysqli_stmt_bind_param($stmt, 'sdssssdddddds', $U['passhash'], $U['refresh'], $U['colour'], $U['bgcolour'], $U['fontface'], $U['fonttags'], $U['boxwidth'], $U['boxheight'], $U['notesboxwidth'], $U['notesboxheight'], $U['timestamps'], $U['embed'], $U['nickname']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
	}
	if(isSet($_REQUEST['unignore']) && $_REQUEST['unignore']!=''){
		$stmt=mysqli_prepare($mysqli, 'DELETE FROM `ignored` WHERE `ignored`=? AND `by`=?');
		mysqli_stmt_bind_param($stmt, 'ss', $_REQUEST['unignore'], $U['nickname']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
	}
	if(isSet($_REQUEST['ignore']) && $_REQUEST['ignore']!=''){
		$stmt=mysqli_prepare($mysqli, 'INSERT INTO `ignored` (`ignored`,`by`) VALUES (?, ?)');
		mysqli_stmt_bind_param($stmt, 'ss', $_REQUEST['ignore'], $U['nickname']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
	}
	send_profile($I['succprofile']);
}

function add_user_defaults(){
	global $U, $F, $C, $H;
	if(isSet($_SERVER['HTTP_USER_AGENT'])) $U['useragent']=htmlspecialchars($_SERVER['HTTP_USER_AGENT']);
	else $U['useragent']='';
	if(!isSet($U['refresh'])) $U['refresh']=$C['defaultrefresh'];
	if(!isSet($U['fontinfo'])){
		if(!preg_match('/^[a-f0-9]{6}$/i', $U['colour'])){
			$U['colour']=$C['coltxt'];
			do{
				$U['colour']=sprintf('%02X', rand(0, 256)).sprintf('%02X', rand(0, 256)).sprintf('%02X', rand(0, 256));
			}while(abs(greyval($U['colour'])-greyval($C['colbg']))<75);
		}
		$U['fontinfo']="#$U[colour]";
		@$U['fontinfo'].=" {$F[$U['fontface']]} <$U[fonttags]>";
	}
	if(!isSet($U['bgcolour']) || !preg_match('/^[a-f0-9]{6}$/i', $U['bgcolour'])) $U['bgcolour']=$C['colbg'];
	$H['begin_body']="\n<body bgcolor=\"#$U[bgcolour]\" text=\"#$C[coltxt]\" link=\"#$C[collnk]\" alink=\"#$C[colact]\" vlink=\"#$C[colvis]\">\n";
	if(!isSet($U['colour'])){
		preg_match('/([0-9a-f]{6})/i', $U['fontinfo'], $matches);
		$U['colour']=$matches[0];
	}
	if(!isSet($U['style'])) $U['style']=get_style($U['fontinfo']);
	if(!isSet($U['boxwidth'])) $U['boxwidth']=40;
	if(!isSet($U['boxheight'])) $U['boxheight']=3;
	if(!isSet($U['notesboxwidth'])) $U['notesboxwidth']=80;
	if(!isSet($U['notesboxheight'])) $U['notesboxheight']=30;
	if(!isSet($U['timestamps'])) $U['timestamps']=$C['timestamps'];
	if(!isSet($U['embed'])) $U['embed']=$C['embed'];
	if(!isSet($U['lastpost'])) $U['lastpost']=time();
	if(!isSet($U['entry'])) $U['entry']=0;
	if(!isSet($U['postid'])) $U['postid']='OOOOOO';
	if(!isSet($U['displayname'])) $U['displayname']=style_this($U['nickname'], $U['fontinfo']);
}

// message handling

function validate_input(){
	global $U, $P, $C, $mysqli;
	$U['message']=substr($_REQUEST['message'], 0, $C['maxmessage']);
	if(!isSet($U['rejected'])) $U['rejected']=substr($_REQUEST['message'], $C['maxmessage']);
	if(preg_match('/&[^;]{0,8}$/', $U['message']) && preg_match('/^([^;]{0,8};)/', $U['rejected'], $match)){
		$U['message'].=$match[0];
		$U['rejected']=preg_replace("/^$match[0]", '', $U['rejected']);
	}
	if($U['rejected']){
		$U['rejected']=htmlspecialchars($U['rejected']);
		$U['rejected']=preg_replace('/<br>(<br>)+/', '<br><br>', $U['rejected']);
		$U['rejected']=preg_replace('/<br><br>$/', '<br>', $U['rejected']);
		$U['rejected']=preg_replace('/<br>/', "\n", $U['rejected']);
		$U['rejected']=preg_replace('/^\s+|\s+$/', '', $U['rejected']);
	}
	$U['message']=htmlspecialchars($U['message']);
	$U['message']=preg_replace("/\r\n/", '<br>', $U['message']);
	$U['message']=preg_replace("/\n/", '<br>', $U['message']);
	$U['message']=preg_replace("/\r/", '<br>', $U['message']);
	if($_REQUEST['multi']=='on'){
		$U['message']=preg_replace('/<br>(<br>)+/', '<br><br>', $U['message']);
		$U['message']=preg_replace('/<br><br>$/', '<br>', $U['message']);
		$U['message']=preg_replace('/  /', ' &nbsp;', $U['message']);
		$U['message']=preg_replace('/<br> /', '<br>&nbsp;', $U['message']);
	}else{
		$U['message']=preg_replace('/<br>/', ' ', $U['message']);
		$U['message']=preg_replace('/^\s+|\s+$/', '', $U['message']);
		$U['message']=preg_replace('/\s+/', ' ', $U['message']);
	}
	$U['delstatus']=$U['status'];
	$U['recipient']='';
	if($_REQUEST['sendto']=='*'){
		$U['poststatus']='1';
		$U['displaysend']="$U[displayname] - ";
	}elseif($_REQUEST['sendto']=='?' && $U['status']>=3){
		$U['poststatus']='3';
		$U['displaysend']="[M] $U[displayname] - ";
	}elseif($_REQUEST['sendto']=='#' && $U['status']>=5){
		$U['poststatus']='5';
		$U['displaysend']="[Staff] $U[displayname] - ";
	}elseif($_REQUEST['sendto']=='&' && $U['status']>=6){
		$U['poststatus']='6';
		$U['displaysend']="[Admin] $U[displayname] - ";
	}else{// known nick in room?
		$stmt=mysqli_prepare($mysqli, 'SELECT * FROM `ignored` WHERE (`ignored`=? AND `by`=?) OR (`ignored`=? AND `by`=?)');
		mysqli_stmt_bind_param($stmt, 'ssss', $U['nickname'], $_REQUEST['sendto'], $_REQUEST['sendto'], $U['nickname']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_store_result($stmt);
		if(mysqli_stmt_num_rows($stmt)==0){
			foreach($P as $chatter){
				if($_REQUEST['sendto']==$chatter[0]){
					$U['recipient']=$chatter[0];
					$U['displayrecp']=style_this($chatter[0], $chatter[2]);
					break;
				}
			}
		}
		mysqli_stmt_free_result($stmt);
		mysqli_stmt_close($stmt);
		if($U['recipient']!==''){
			$U['poststatus']='9';
			$U['delstatus']='9';
			$U['displaysend']="[$U[displayname] to $U[displayrecp]] - ";
		}else{// nick left already or ignores us
			$U['message']='';
			$U['rejected']='';
		}
	}
	if(isSet($U['poststatus'])){
		update_session();
		if($U['poststatus']==9) apply_filter(true);
		else apply_filter(false);
		create_hotlinks();
	}
}

function apply_filter($pm){
	global $U, $I, $mysqli;
	$result=mysqli_query($mysqli, 'SELECT * FROM `filter`');
	if(mysqli_num_rows($result)>0){
		while($filter=mysqli_fetch_array($result, MYSQLI_ASSOC)){
			if(!$pm) $U['message']=preg_replace("/$filter[match]/i", $filter['replace'], $U['message'], -1, $count);
			elseif(!$filter['allowinpm']) $U['message']=preg_replace("/$filter[match]/i", $filter['replace'], $U['message'], -1, $count);
			if($count>0 && $filter['kick']){
				kick_chatter(array($U['nickname']), '', false);
				send_error("$I[kicked]");
			}
		}
	}
}

function create_hotlinks(){
	global $U, $C;
	//Make hotlinks for URLs, redirect through dereferrer script to prevent session leakage
	// 1. all explicit schemes with whatever xxx://yyyyyyy
	$U['message']=preg_replace('~(\w*://[^\s<>]+)~i', "<<$1>>", $U['message']);
	// 2. valid URLs without scheme:
	$U['message']=preg_replace('~((?:[^\s<>]*:[^\s<>]*@)?[a-z0-9\-]+(?:\.[a-z0-9\-]+)+(?::\d*)?/[^\s<>]*)(?![^<>]*>)~i', "<<$1>>", $U['message']); // server/path given
	$U['message']=preg_replace('~((?:[^\s<>]*:[^\s<>]*@)?[a-z0-9\-]+(?:\.[a-z0-9\-]+)+:\d+)(?![^<>]*>)~i', "<<$1>>", $U['message']); // server:port given
	$U['message']=preg_replace('~([^\s<>]*:[^\s<>]*@[a-z0-9\-]+(?:\.[a-z0-9\-]+)+(?::\d+)?)(?![^<>]*>)~i', "<<$1>>", $U['message']); // au:th@server given
	// 3. likely servers without any hints but not filenames like *.rar zip exe etc.
	$U['message']=preg_replace('~((?:[a-z0-9\-]+\.)*[a-z0-9]{16}\.onion)(?![^<>]*>)~i', "<<$1>>", $U['message']);// *.onion
	$U['message']=preg_replace('~([a-z0-9\-]+(?:\.[a-z0-9\-]+)+(?:\.(?!rar|zip|exe|gz|7z|bat|doc)[a-z]{2,}))(?=[^a-z0-9\-\.]|$)(?![^<>]*>)~i', "<<$1>>", $U['message']);// xxx.yyy.zzz
	// Convert every <<....>> into proper links:
	$U['message']=preg_replace_callback('/<<([^<>]+)>>/', function ($matches){if(strpos($matches[1], '://')==false){ return "<a href=\"http://$matches[1]\" target=\"_blank\">$matches[1]</a>";}else{ return "<a href=\"$matches[1]\" target=\"_blank\">$matches[1]</a>"; }}, $U['message']);
	if($C['imgembed']) $U['message']=preg_replace_callback('/\[img\]<a href="(.*?(?="))" target="_blank">(.*?(?=<\/a>))<\/a>/i', function ($matched){ return "<br><a href=\"$matched[1]\" target=\"_blank\"><img src=\"$matched[1]\"></a><br>";}, $U['message']);
	if($C['vidembed']) $U['message']=preg_replace_callback('/\[vid\]<a href="(.*?(?="))" target="_blank">(.*?(?=<\/a>))<\/a>/i', function ($matched){ return "<br><a href=\"$matched[1]\" target=\"_blank\"><video src=\"$matched[1]\"></a><br>";}, $U['message']);
	if($C['forceredirect']) $U['message']=preg_replace_callback('/<a href="(.*?(?="))" target="_blank">(.*?(?=<\/a>))<\/a>/', function ($matched){ global $C; return "<a href=\"$C[redirect]".urlencode($matched[1])."\" target=\"_blank\">$matched[2]</a>";}, $U['message']);
	if(preg_match_all('/<a href="(.*?(?="))" target="_blank">(.*?(?=<\/a>))<\/a>/', $U['message'], $matches)){
		foreach($matches[1] as $match){
			if(!preg_match('~^http(s)?://~', $match)){
				$U['message']=preg_replace_callback('/<a href="(.*?(?="))" target="_blank">(.*?(?=<\/a>))<\/a>/', function ($matched){ global $C; return "<a href=\"$C[redirect]".urlencode($matched[1])."\" target=\"_blank\">$matched[2]</a>";}, $U['message']);
				break;
			}
		}
	}
}

function add_message(){
	global $U;
	if($U['message']=='') return;
	$newmessage=array(
		'postdate'	=>time(),
		'postid'	=>$U['postid'],
		'poststatus'=>$U['poststatus'],
		'poster'	=>$U['nickname'],
		'recipient'	=>$U['recipient'],
		'text'		=>$U['displaysend'].style_this($U['message'], $U['fontinfo']),
		'delstatus'	=>$U['delstatus']
	);
	write_message($newmessage);
}

function add_system_message($mes){
	$sysmessage=array(
		'postdate'	=>time(),
		'postid'	=>substr(rand(), -6),
		'poststatus'	=>1,
		'poster'	=>'',
		'recipient'	=>'',
		'text'		=>$mes,
		'delstatus'	=>9
	);
	write_message($sysmessage);
}

function write_message($message){
	global $C, $mysqli;
	$stmt=mysqli_prepare($mysqli, 'INSERT INTO `messages`(`postdate`, `postid`, `poststatus`, `poster`, `recipient`, `text`, `delstatus`) VALUES (?, ?, ?, ?, ?, ?, ?)');
	mysqli_stmt_bind_param($stmt, 'dddsssd', $message['postdate'], $message['postid'], $message['poststatus'], $message['poster'], $message['recipient'], $message['text'], $message['delstatus']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	$stmt=mysqli_prepare($mysqli, 'DELETE FROM `messages` WHERE `id` NOT IN (SELECT `id` FROM (SELECT `id` FROM `messages` ORDER BY `postdate` DESC LIMIT ?) t )');
	mysqli_stmt_bind_param($stmt, 'd', $limit=$C['keeplimit']*$C['messagelimit']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
	if($C['sendmail'] && $message['poststatus']<9){
		$subject = 'New Chat message';
		$headers = "From: $C[mailsender]\r\nX-Mailer: PHP/".phpversion()."\r\nContent-Type: text/html; charset=UTF-8\r\n";
		mail($C['mailreceiver'], $subject, $message['text'], $headers);
	}
}

function clean_room(){
	global $C, $mysqli;
	mysqli_query($mysqli, 'DELETE FROM `messages`');
	$sysmessage=array(
		'postdate'	=>time(),
		'postid'	=>substr(rand(), -6),
		'poster'	=>'',
		'recipient'	=>'',
		'poststatus'	=>1,
		'text'		=>sprintf(get_setting('msgclean'), $C['chatname']),
		'delstatus'	=>9
	);
	write_message($sysmessage);
}

function clean_selected(){
	global $C, $mysqli;
	if(isSet($_REQUEST['mid'])){
		foreach($_REQUEST['mid'] as $mid) $mids[$mid]=1;
	}
	$result=mysqli_query($mysqli, 'SELECT * FROM `messages` ORDER BY `postdate` DESC');
	if(mysqli_num_rows($result)>0){
		$stmt=mysqli_prepare($mysqli, 'DELETE FROM `messages` WHERE `postdate`=? AND `postid`=?');
		while($temp=mysqli_fetch_array($result, MYSQLI_ASSOC)){
			if(isSet($mids[$temp['postdate'].$temp['postid']])){
				mysqli_stmt_bind_param($stmt, 'dd', $temp['postdate'], $temp['postid']);
				mysqli_stmt_execute($stmt);
			}
		}
		mysqli_stmt_close($stmt);
	}
}

function del_all_messages($nick){
	global $mysqli;
	$stmt=mysqli_prepare($mysqli, 'DELETE FROM `messages` WHERE `poster`=?');
	mysqli_stmt_bind_param($stmt, 's', $nick);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
}

function del_last_message(){
	global $U, $mysqli;
	$stmt=mysqli_prepare($mysqli, 'DELETE FROM `messages` WHERE `poster`=? ORDER BY `postdate` DESC LIMIT 1');
	mysqli_stmt_bind_param($stmt, 's', $U['nickname']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
}

function print_messages($delstatus=''){
	global $U, $C, $mysqli;
	mysqli_query($mysqli, 'DELETE FROM `messages` WHERE `postdate`<=\''.(time()-60*$C['messageexpire'])."'");
	$stmt=mysqli_prepare($mysqli, 'SELECT `postdate`, `postid`, `text`, `delstatus` FROM `messages` WHERE ('.
	'`id` IN (SELECT * FROM (SELECT `id` FROM `messages` WHERE `poststatus`=\'1\' ORDER BY `postdate` DESC LIMIT ?) AS t) '.
	'OR (`poststatus`>\'1\' AND `poststatus`<=?) '.
	'OR (`poststatus`=\'9\' AND ( (`poster`=? AND `recipient` NOT IN (SELECT * FROM (SELECT `ignored` FROM `ignored` WHERE `by`=?) AS t) ) OR `recipient`=?) )'.
	') AND `poster` NOT IN (SELECT * FROM (SELECT `ignored` FROM `ignored` WHERE `by`=?) AS t) ORDER BY `postdate` DESC');
	mysqli_stmt_bind_param($stmt, 'ddssss', $C['messagelimit'], $U['status'], $U['nickname'], $U['nickname'], $U['nickname'], $U['nickname']);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_bind_result($stmt, $message['postdate'], $message['postid'], $message['text'], $message['delstatus']);
	while(mysqli_stmt_fetch($stmt)){
		if($delstatus!==''){
			if($U['status']>$message['delstatus']){
				echo "<input type=\"checkbox\" name=\"mid[]\" id=\"$message[postdate]$message[postid]\" value=\"$message[postdate]$message[postid]\"><label for=\"$message[postdate]$message[postid]\">&nbsp;$message[text]</label><br>";
			}
		}else{
			if(!isSet($_COOKIE[$C['cookiename']]) && !$C['forceredirect']){
				$message['text']=preg_replace_callback('/<a href="(.*?(?="))" target="_blank">(.*?(?=<\/a>))<\/a>/', function ($matched){ global $C; return "<a href=\"$C[redirect]".urlencode($matched[1])."\" target=\"_blank\">$matched[2]</a>";}, $message['text']);
			}
				if(!$U['embed'] && preg_match('/<(img|video) src="(.*?(?="))">/', $message['text'], $matches)){
				$message['text']=preg_replace_callback("/<$matches[1] src=\"(.*?(?=\"))\">/", function ($matched){ return $matched[1];}, $message['text']);
			}
			if($U['timestamps']) echo '<small>'.date('m-d H:i:s', $message['postdate']).' - </small>';
			echo "$message[text]<br>";
		}
	}
	mysqli_stmt_close($stmt);
}

// this and that

function valid_admin(){
	global $mysqli, $C;
	if(isSet($_REQUEST['nick']) && isSet($_REQUEST['pass'])){
		if($C['enablecaptcha']){
			$captcha=explode(',', openssl_decrypt(base64_decode($_REQUEST['challenge']), 'aes-128-cbc', $C['captchapass'], 0, '1234567890123456'));
			if(current($captcha)!==$_REQUEST['captcha']) return false;
			$stmt=mysqli_prepare($mysqli, 'SELECT * FROM `captcha` WHERE `id`=?');
			mysqli_stmt_bind_param($stmt, 'd', end($captcha));
			mysqli_stmt_execute($stmt);
			mysqli_stmt_store_result($stmt);
			if(mysqli_stmt_num_rows($stmt)==0) return false;
			mysqli_stmt_free_result($stmt);
			mysqli_stmt_close($stmt);
			$stmt=mysqli_prepare($mysqli, 'DELETE FROM `captcha` WHERE `id`=? OR `time`<\''.(time()-60*10)."'");
			mysqli_stmt_bind_param($stmt, 'd', end($captcha));
			mysqli_stmt_execute($stmt);
			mysqli_stmt_close($stmt);
		}
		$stmt=mysqli_prepare($mysqli, 'SELECT * FROM `members` WHERE `nickname`=? AND `passhash`=? AND `status`>=\'7\'');
		mysqli_stmt_bind_param($stmt, 'ss', $_REQUEST['nick'], $pass=md5(sha1(md5($_REQUEST['nick'].$_REQUEST['pass']))));
		mysqli_stmt_execute($stmt);
		mysqli_stmt_store_result($stmt);
		if(mysqli_stmt_num_rows($stmt)>0) return true;
		mysqli_stmt_free_result($stmt);
		mysqli_stmt_close($stmt);
	}
	return false;
}

function valid_nick($nick){
	global $C;
	return preg_match("/^[a-z0-9]{1,$C[maxname]}$/i", $nick);
}

function valid_pass($pass){
	global $C;
	return preg_match('/^.{'.$C['minpass'].',}$/', $pass);
}

function cleanup_nick($nick){
	$nick=preg_replace('/\s+/', '', $nick);
	return $nick;
}

function get_timeout($lastpost, $status){ // lastpost, status
	global $C;
	if($status>2) $expire=$C['sessionexpire'];
	else $expire=$C['guestsexpire'];
	$s=($lastpost+60*$expire)-time();
	$m=$s/60;$m=floor($m);$s-=$m*60;
	$h=$m/60;$h=floor($h);$m-=$h*60;
	$s=substr('0'.$s, -2, 2);
	if($h>0){
		$m=substr('0'.$m, -2, 2);
		return "$h:$m:$s";
	}
	return "$m:$s";
}

function print_colours(){
	global $C, $I;
	// Prints a short list with selected named HTML colours and filters out illegible text colours for the given background.
	// It's a simple comparison of weighted grey values. This is not very accurate but gets the job done well enough.
	$colours=array('Beige'=>'F5F5DC', 'Black'=>'000000', 'Blue'=>'0000FF', 'BlueViolet'=>'8A2BE2', 'Brown'=>'A52A2A', 'Cyan'=>'00FFFF', 'DarkBlue'=>'00008B', 'DarkGreen'=>'006400', 'DarkRed'=>'8B0000', 'DarkViolet'=>'9400D3', 'DeepSkyBlue'=>'00BFFF', 'Gold'=>'FFD700', 'Grey'=>'808080', 'Green'=>'008000', 'HotPink'=>'FF69B4', 'Indigo'=>'4B0082', 'LightBlue'=>'ADD8E6', 'LightGreen'=>'90EE90', 'LimeGreen'=>'32CD32', 'Magenta'=>'FF00FF', 'Olive'=>'808000', 'Orange'=>'FFA500', 'OrangeRed'=>'FF4500', 'Purple'=>'800080', 'Red'=>'FF0000', 'RoyalBlue'=>'4169E1', 'SeaGreen'=>'2E8B57', 'Sienna'=>'A0522D', 'Silver'=>'C0C0C0', 'Tan'=>'D2B48C', 'Teal'=>'008080', 'Violet'=>'EE82EE', 'White'=>'FFFFFF', 'Yellow'=>'FFFF00', 'YellowGreen'=>'9ACD32');
	$greybg=greyval($C['colbg']);
	foreach($colours as $name=>$colour){
		if(abs($greybg-greyval($colour))>75) echo "<option value=\"$colour\" style=\"color:#$colour\">$I[$name]</option>";
	}
}

function greyval($colour){
	return hexdec(substr($colour, 0, 2))*.3+hexdec(substr($colour, 2, 2))*.59+hexdec(substr($colour, 4, 2))*.11;
}

function get_style($styleinfo){
	$fbold=preg_match('/(<i?bi?>|:bold)/', $styleinfo);
	$fitalic=preg_match('/(<b?ib?>|:italic)/', $styleinfo);
	$fsmall=preg_match('/(size="-1"|:smaller)/', $styleinfo);
	preg_match('/(#.{6})/', $styleinfo, $match);
	if(isSet($match[0])) $fcolour=$match[0];
	preg_match('/face=\'([^"]+)\'/', $styleinfo, $match);
	if(isSet($match[1])) $fface=$match[1];
	preg_match('/font-family:([^;]+);/', $styleinfo, $match);
	if(isSet($match[1])) $sface=$match[1];
	if(isSet($fface)){
		$sface=$fface;
		$sface=preg_replace('/^/', "'", $sface);
		$sface=preg_replace('/$/', "'", $sface);
		$sface=preg_replace('/,/', "','", $sface);
	}elseif(isSet($sface)){
		$fface=$sface;
		$fface=preg_replace("/'/", '', $fface);
	}
	$fstyle='';
	if(isSet($fcolour)) $fstyle.="color:$fcolour;";
	if(isSet($sface)) $fstyle.="font-family:$sface;";
	if($fsmall) $fstyle.='font-size:smaller;';
	if($fitalic) $fstyle.='font-style:italic;';
	if($fbold) $fstyle.='font-weight:bold;';
	return $fstyle;
}

function style_this($text, $styleinfo){
	$fbold=preg_match('/(<i?bi?>|:bold)/', $styleinfo);
	$fitalic=preg_match('/(<b?ib?>|:italic)/', $styleinfo);
	$fsmall=preg_match('/(size="-1"|:smaller)/', $styleinfo);
	preg_match('/(#.{6})/', $styleinfo, $match);
	if(isSet($match[0]))$fcolour=$match[0];
	preg_match('/face=\'([^"]+)\'/', $styleinfo, $match);
	if(isSet($match[1]))$fface=$match[1];
	preg_match('/font-family:([^;]+);/', $styleinfo, $match);
	if(isSet($match[1]))$sface=$match[1];
	if(isSet($fface)){
		$sface=$fface;
		$sface=preg_replace('/^/', "'", $sface);
		$sface=preg_replace('/$/', "'", $sface);
		$sface=preg_replace('/,/', "','", $sface);
	}elseif(isSet($sface)){
		$fface=$sface;
		$fface=preg_replace("/'/", '', $fface);
	}
	$fstyle='';
	if(isSet($fcolour)) $fstyle.="color:$fcolour;";
	if(isSet($sface)) $fstyle.="font-family:$sface;";
	if($fsmall) $fstyle.='font-size:smaller;';
	if($fitalic)$fstyle.='font-style:italic;';
	if($fbold) $fstyle.='font-weight:bold;';
	$fstart='<font';
	if(!isSet($fcolour)) $fstart.=" color=\"$fcolour\"";
	if(isSet($fface)) $fstart.=" face=\"$fface\"";
	if($fsmall) $fstart.=" size=\"-1\"";
	if($fstyle!=='') $fstart.=" style=\"$fstyle\"";
	$fstart.='>';
	if($fbold) $fstart.='<b>';
	if($fitalic) $fstart.='<i>';
	$fend='';
	if($fitalic) $fend.='</i>';
	if($fbold) $fend.='</b>';
	$fend.='</font>';
	return "$fstart$text$fend";
}

function init_chat(){
	global $H, $C, $U, $I, $mysqli;
	$suwrite='';
	$tables=array('captcha', 'filter', 'ignored', 'members', 'messages', 'notes', 'sessions', 'settings');
	$num_tables=0;
	$result=mysqli_query($mysqli, 'SHOW TABLES');
	while($tmp=mysqli_fetch_array($result, MYSQLI_NUM)){
		if(in_array($tmp[0],$tables)) $num_tables++;
	}
	if($num_tables>=7){
		$suwrite=$I['initdbexist'];
		$result=mysqli_query($mysqli, 'SELECT * FROM `members` WHERE `status`=\'8\'');
		if(mysqli_num_rows($result)>0){
			$suwrite=$I['initsuexist'];
		}
	}elseif(!valid_nick($_REQUEST['sunick'])){
		$suwrite=sprintf($I['invalnick'], $C['maxname']);
	}elseif(!valid_pass($_REQUEST['supass'])){
		$suwrite=sprintf($I['invalpass'], $C['minpass']);
	}elseif($_REQUEST['supass']!==$_REQUEST['supassc']){
		$suwrite=$I['noconfirm'];
	}else{
		mysqli_multi_query($mysqli, 	'CREATE TABLE IF NOT EXISTS `captcha` (`id` int(10) unsigned NOT NULL, `time` int(10) unsigned NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8; '.
						'CREATE TABLE IF NOT EXISTS `filter` (`id` tinyint(3) unsigned NOT NULL, `match` tinytext NOT NULL, `replace` text NOT NULL, `allowinpm` tinyint(1) unsigned NOT NULL, `regex` tinyint(1) unsigned NOT NULL, `kick` tinyint(1) unsigned NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8; '.
						'CREATE TABLE IF NOT EXISTS `ignored` (`id` int(10) unsigned NOT NULL, `ignored` tinytext NOT NULL, `by` tinytext NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8; '.
						'CREATE TABLE IF NOT EXISTS `members` (`id` tinyint(3) unsigned NOT NULL, `nickname` tinytext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, `passhash` tinytext NOT NULL, `status` tinyint(3) unsigned NOT NULL, `refresh` tinyint(3) unsigned NOT NULL, `colour` tinytext NOT NULL, `bgcolour` tinytext NOT NULL, `fontface` tinytext NOT NULL, `fonttags` tinytext NOT NULL, `boxwidth` tinyint(3) unsigned NOT NULL, `boxheight` tinyint(3) unsigned NOT NULL, `notesboxheight` tinyint(3) unsigned NOT NULL, `notesboxwidth` tinyint(3) unsigned NOT NULL, `regedby` tinytext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, `lastlogin` int(10) unsigned NOT NULL, `timestamps` tinyint(1) unsigned NOT NULL, `embed` tinyint(1) unsigned NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8; '.
						'CREATE TABLE IF NOT EXISTS `messages` (`id` int(10) unsigned NOT NULL, `postdate` int(10) unsigned NOT NULL, `postid` int(10) unsigned NOT NULL, `poststatus` tinyint(3) unsigned NOT NULL, `poster` tinytext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, `recipient` tinytext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, `text` text NOT NULL, `delstatus` tinyint(3) unsigned NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8; '.
						'CREATE TABLE IF NOT EXISTS `notes` (`id` int(10) unsigned NOT NULL, `type` tinytext NOT NULL, `lastedited` int(10) unsigned NOT NULL, `editedby` tinytext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, `text` text NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8; '.
						'CREATE TABLE IF NOT EXISTS `sessions` (`id` int(10) unsigned NOT NULL, `session` tinytext NOT NULL, `nickname` tinytext CHARACTER SET utf8 COLLATE utf8_bin NOT NULL, `displayname` text NOT NULL, `status` tinyint(3) unsigned NOT NULL, `refresh` tinyint(3) unsigned NOT NULL, `fontinfo` tinytext NOT NULL, `style` text NOT NULL, `lastpost` int(10) unsigned NOT NULL, `passhash` tinytext NOT NULL, `postid` int(10) unsigned NOT NULL, `boxwidth` tinyint(3) unsigned NOT NULL, `boxheight` tinyint(3) unsigned NOT NULL, `useragent` text NOT NULL, `kickmessage` text NOT NULL, `bgcolour` tinytext NOT NULL, `notesboxheight` tinyint(3) unsigned NOT NULL, `notesboxwidth` tinyint(3) unsigned NOT NULL, `entry` int(10) unsigned NOT NULL, `timestamps` tinyint(1) unsigned NOT NULL, `embed` tinyint(1) unsigned NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8; '.
						'CREATE TABLE IF NOT EXISTS `settings` (`id` tinyint(3) unsigned NOT NULL, `setting` tinytext NOT NULL, `value` tinytext NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8; '.
						'ALTER TABLE `captcha` ADD UNIQUE KEY `id` (`id`); '.
						'ALTER TABLE `filter` ADD PRIMARY KEY (`id`); '.
						'ALTER TABLE `ignored` ADD PRIMARY KEY (`id`); '.
						'ALTER TABLE `members` ADD PRIMARY KEY (`id`); '.
						'ALTER TABLE `messages` ADD PRIMARY KEY (`id`); '.
						'ALTER TABLE `notes` ADD PRIMARY KEY (`id`); '.
						'ALTER TABLE `sessions` ADD PRIMARY KEY (`id`); '.
						'ALTER TABLE `settings` ADD PRIMARY KEY (`id`); '.
						'ALTER TABLE `filter` MODIFY `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT; '.
						'ALTER TABLE `ignored` MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT; '.
						'ALTER TABLE `members` MODIFY `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT; '.
						'ALTER TABLE `messages` MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT; '.
						'ALTER TABLE `notes` MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT; '.
						'ALTER TABLE `sessions` MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT; '.
						'ALTER TABLE `settings` MODIFY `id` tinyint(3) unsigned NOT NULL AUTO_INCREMENT; '.
						'INSERT INTO `settings` (`setting`,`value`) VALUES (\'guestaccess\',\'0\'); '.
						'INSERT INTO `settings` (`setting`,`value`) VALUES (\'msgenter\',\'%s entered the chat.\'); '.
						'INSERT INTO `settings` (`setting`,`value`) VALUES (\'msgexit\',\'%s left the chat.\'); '.
						'INSERT INTO `settings` (`setting`,`value`) VALUES (\'msgmemreg\',\'%s is now a registered member.\'); '.
						'INSERT INTO `settings` (`setting`,`value`) VALUES (\'msgsureg\',\'%s is now a registered applicant.\'); '.
						'INSERT INTO `settings` (`setting`,`value`) VALUES (\'msgkick\',\'%s has been kicked.\'); '.
						'INSERT INTO `settings` (`setting`,`value`) VALUES (\'msgmultikick\',\'%s have been kicked.\'); '.
						'INSERT INTO `settings` (`setting`,`value`) VALUES (\'msgallkick\',\'All chatters have been kicked.\'); '.
						'INSERT INTO `settings` (`setting`,`value`) VALUES (\'msgclean\',\'%s has been cleaned.\'); '.
						'INSERT INTO `settings` (`setting`,`value`) VALUES (\'dbversion\',\''.$C['dbversion'].'\');');
		while(mysqli_next_result($mysqli)) {;}
		$reg=array(
			'nickname'	=>$_REQUEST['sunick'],
			'passhash'	=>md5(sha1(md5($_REQUEST['sunick'].$_REQUEST['supass']))),
			'status'	=>8,
			'refresh'	=>$C['defaultrefresh'],
			'colour'	=>$C['coltxt'],
			'bgcolour'	=>$C['colbg'],
			'boxwidth'	=>$C['boxwidth'],
			'boxheight'	=>$C['boxheight'],
			'notesboxwidth'	=>$C['notesboxwidth'],
			'notesboxheight'=>$C['notesboxheight'],
			'timestamps'	=>$C['timestamps'],
			'embed'		=>$C['embed']
		);
		$stmt=mysqli_prepare($mysqli, 'INSERT INTO `members` (`nickname`, `passhash`, `status`, `refresh`, `colour`, `bgcolour`, `boxwidth`, `boxheight`, `notesboxwidth`, `notesboxheight`, `timestamps`, `embed`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
		mysqli_stmt_bind_param($stmt, 'ssddssdddddd', $reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['colour'], $reg['bgcolour'], $reg['boxwidth'], $reg['boxheight'], $reg['notesboxwidth'], $reg['notesboxheight'], $reg['timestamps'], $reg['embed']);
		mysqli_stmt_execute($stmt);
		mysqli_stmt_close($stmt);
		$suwrite=$I['susuccess'];
	}
	print_start();
	echo "<center><h2>$I[init]</h2><br><h3>$I[sulogin]</h3>$suwrite<br><br><br>";
	echo "<$H[form]>".hidden('action', 'setup').hidden('nick', $_REQUEST['sunick']).hidden('pass', $_REQUEST['supass']).submit($I['initgosetup']).'</form>';
	print_credits();
	print_end();
}

function update_db(){
	global $C, $mysqli;
	$dbversion=get_setting('dbversion');
	if($dbversion<$C['dbversion']){
		if($dbversion<2){
			mysqli_query($mysqli, 'CREATE TABLE IF NOT EXISTS `ignored` (`id` int(10) unsigned NOT NULL, `ignored` tinytext NOT NULL, `by` tinytext NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8');
			mysqli_query($mysqli, 'ALTER TABLE `ignored` ADD PRIMARY KEY (`id`)');
			mysqli_query($mysqli, 'ALTER TABLE `ignored` MODIFY `id` int(10) unsigned NOT NULL AUTO_INCREMENT');
		}
		if($dbversion<3){
			mysqli_query($mysqli, 'INSERT INTO `settings` (`setting`, `value`) VALUES (\'rulestxt\', \'1. YOUR_RULS<br>2. YOUR_RULES\')');
		}
		update_setting('dbversion', $C['dbversion']);
		send_update();
	}
}

function update_messages(){
	global $C;
	update_setting('msgenter', $_REQUEST['msgenter']);
	update_setting('msgexit', $_REQUEST['msgexit']);
	update_setting('msgmemreg', $_REQUEST['msgmemreg']);
	if($C['suguests']) update_setting('msgsureg', $_REQUEST['msgsureg']);
	update_setting('msgkick', $_REQUEST['msgkick']);
	update_setting('msgmultikick', $_REQUEST['msgmultikick']);
	update_setting('msgallkick', $_REQUEST['msgallkick']);
	update_setting('msgclean', $_REQUEST['msgclean']);
}

function get_setting($setting){
	global $mysqli;
	$stmt=mysqli_prepare($mysqli, 'SELECT `value` FROM `settings` WHERE `setting`=?');
	mysqli_stmt_bind_param($stmt, 's', $setting);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_bind_result($stmt, $value);
	mysqli_stmt_fetch($stmt);
	mysqli_stmt_close($stmt);
	return $value;
}

function update_setting($setting, $value){
	global $mysqli;
	$stmt=mysqli_prepare($mysqli, 'UPDATE `settings` SET `value`=? WHERE `setting`=?');
	mysqli_stmt_bind_param($stmt, 'ss', $value, $setting);
	mysqli_stmt_execute($stmt);
	mysqli_stmt_close($stmt);
}

// configuration, defaults and internals

function load_fonts(){
	global $F;
	$F=array(
		'Arial'			=>" face='Arial,Helvetica,sans-serif'",
		'Book Antiqua'		=>" face='Book Antiqua,MS Gothic'",
		'Comic'			=>" face='Comic Sans MS,Papyrus'",
		'Comic small'		=>" face='Comic Sans MS,Papyrus' size=\"-1\"",
		'Courier'		=>" face='Courier New,Courier,monospace'",
		'Cursive'		=>" face='Cursive,Papyrus'",
		'Fantasy'		=>" face='Fantasy,Futura,Papyrus'",
		'Garamond'		=>" face='Garamond,Palatino,serif'",
		'Georgia'		=>" face='Georgia,Times New Roman,Times,serif'",
		'Serif'			=>" face='MS Serif,New York,serif'",
		'System'		=>" face='System,Chicago,sans-serif'",
		'Times New Roman'	=>" face='Times New Roman,Times,serif'",
		'Verdana'		=>" face='Verdana,Geneva,Arial,Helvetica,sans-serif'",
		'Verdana small'		=>" face='Verdana,Geneva,Arial,Helvetica,sans-serif' size=\"-1\""
	);
}

function load_html(){
	global $H, $C, $I;
	$H=array(// default HTML
		'begin_body'	=>"\n<body bgcolor=\"#$C[colbg]\" text=\"#$C[coltxt]\" link=\"#$C[collnk]\" alink=\"#$C[colact]\" vlink=\"#$C[colvis]\">\n",
		'form'		=>"form action=\"$_SERVER[SCRIPT_NAME]\" method=\"post\" style=\"margin:0;padding:0;\"",
		'meta_html'	=>"<title>$C[chatname]</title><meta name=\"robots\" content=\"noindex,nofollow\">\n<meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\">\n<meta http-equiv=\"Pragma\" content=\"no-cache\">\n<meta http-equiv=\"expires\" content=\"0\">"
	);
	$H=$H+array(
		'backtologin'	=>"<$H[form] target=\"_parent\">".submit($I['backtologin'], ' style="background-color:#004400;color:#FFFFFF;"').'</form>',
		'backtochat'	=>"<$H[form]>".hidden('action', 'view').@hidden('session', $_REQUEST['session']).submit($I['backtochat'], ' style="background-color:#004400;color:#FFFFFF;"').'</form>'
	);
}

function check_db(){
	global $mysqli, $C, $I;
	$mysqli=mysqli_connect($C['dbhost'], $C['dbuser'], $C['dbpass'], $C['dbname']);
	if(mysqli_connect_errno($mysqli)){
		if($_REQUEST['action']=='setup'){
			die($I['nodbsetup']);
		}else{
			die($I['nodb']);
		}
	}
}

function load_lang(){
	global $C, $I, $L;
	$L=array(
		'de'	=>'Deutsch',
		'en'	=>'English'
	);
	if(isSet($_REQUEST['lang']) && array_key_exists($_REQUEST['lang'], $L)){
		$C['lang']=$_REQUEST['lang'];
		setcookie('language', $C['lang']);
	}elseif(isSet($_COOKIE['language']) && array_key_exists($_COOKIE['language'], $L)){
		$C['lang']=$_COOKIE['language'];
	}
	include('lang_en.php'); //always include English
	if($C['lang']!=='en') include("lang_$C[lang].php"); //replace with translation if available
}

function load_config(){
	global $C;
	$C=array(
		'version'	=>'1.4', // Script version
		'dbversion'	=>3, // Database version
		'showcredits'	=>false, // Allow showing credits
		'colbg'		=>'000000', // Background colour
		'coltxt'	=>'FFFFFF', // Default text colour
		'collnk'	=>'0000FF', // Link colour
		'colvis'	=>'B33CB4', // Visited link colour
		'colact'	=>'FF0033', // Clicked link colour
		'sessionexpire'	=>60, // Minutes until a member session expires
		'guestsexpire'	=>15, // Minutes until a guest session expires
		'kickpenalty'	=>10, // Minutes a nickname is blocked when it got kicked
		'entrywait'	=>120, // Seconds to wait in the waiting room after login
		'cookiename'	=>'chat_session', // Cookie name storing the session information
		'chatname'	=>'My Chat', // Chat Name
		'messageexpire'	=>14400, // Minutes until a message expires
		'messagelimit'	=>150, // Max messages displayed
		'keeplimit'	=>3, // Numer of messages to keep in the database multiplied with max messages displayed - increase if you have many private messages
		'defaultrefresh'=>30, // Seconds to refresh the messages
		'maxmessage'	=>2000, // Longest number of characters for a message
		'maxname'	=>20, // Longest number of chatacters for a name
		'minpass'	=>5, // Shortest number of chatacters for a password
		'boxwidth'	=>40, // Default post box width
		'boxheight'	=>3, // Default post box height
		'notesboxwidth'	=>80, // Default notes box width
		'notesboxheight'=>30, // Default notes box height
		'dbhost'	=>'p:localhost', // Database host
		'dbuser'	=>'www-data', // Database user
		'dbpass'	=>'YOUR_DB_PASS', // Database password
		'dbname'	=>'public_chat', // Database
		'captchapass'	=>'YOUR_PASS', // Password used for captcha encryption
		'captchachars'	=>'0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', // Characters used for captcha generation
		'enablecaptcha'	=>true, // Enable captcha? ture/false
		'dismemcaptcha'	=>false, // Disable captcha for members? ture/false
		'embed'		=>true, // Default for displaying embedded imgs/vids or turn them into links true/false
		'imgembed'	=>true, // Allow image embedding in chat using [img] tag? ture/false Warning: this might leak session data to the image hoster when cookies are disabled.
		'vidembed'	=>false, // Allow video embedding in chat using [vid] tag? ture/false Warning: this might leak session data to the video hoster when cookies are disabled.
		'suguests'	=>false, // Adds option to add applicants. They will have a reserved nick protected with a password, but don't count as member true/false
		'timestamps'	=>true, // Display timestamps in front of the messages by default true/false
		'forceredirect'	=>false, // Force redirect script or only use when no cookies available? ture/false
		'msglogout'	=>false, // Add a message on member logout
		'msglogin'	=>true, // Add a message on member login
		'msgkick'	=>true, // Add a message when kicking someone
		'memkick'	=>true, // Let a member kick guests if no mod is present
		'sendmail'	=>false, // Send mail on new message - only activate on low traffic chat or your inbox will fill up very fast!
		'mailsender'	=>'www-data <www-data@localhost>', // Send mail using this e-Mail address
		'mailreceiver'	=>'Webmaster <webmaster@localhost>', // Send mail to this e-Mail address
		'redirect'	=>"$_SERVER[SCRIPT_NAME]?action=redirect&url=", // Redirect script default: "$_SERVER[SCRIPT_NAME]?action=redirect&url="
		'lang'		=>'en' // Default language
	);
}
?>
