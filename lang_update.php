<?php
$native = 'Deutsch'; // Native lanugae name
$english = 'German'; // Enlish language name
$code = 'de'; // Language code

ob_start();
echo "<?php
/*
* LE CHAT-PHP - a PHP Chat based on LE CHAT - $english translation
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

//Native language name: $native
\$T=[
";
if(file_exists("lang_$code.php")){
	include("lang_$code.php");
}
include('lang_en.php');
foreach($T as $id=>$value){
	if(isSet($I[$id])){
		$I[$id]=$value;
	}
}
foreach($I as $id=>$value){
	echo "\t'$id' => '".str_replace("'", "\'", $value)."',\n";
}
echo "];\n?>\n";
$file=ob_get_clean();
file_put_contents("lang_$code.php", $file);
?>
