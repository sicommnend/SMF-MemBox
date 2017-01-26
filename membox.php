<?php

$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
$start = $time;

define('SMF', 1);
require_once(dirname(__FILE__) . '/Settings.php');
require_once($sourcedir . '/Load.php');

if (isset($_COOKIE[$cookiename])) {
	if (preg_match('~^a:[34]:\{i:0;(i:\d{1,6}|s:[1-8]:"\d{1,8}");i:1;s:(0|40):"([a-fA-F0-9]{40})?";i:2;[id]:\d{1,14};(i:3;i:\d;)?\}$~i', $_COOKIE[$cookiename]) == 1) {
		list ($id_member, $password) = safe_unserialize($_COOKIE[$cookiename]);
		$id_member = !empty($id_member) && strlen($password) > 0 ? (int) $id_member : 0;
	}
}

if ($id_member != 0 && ($user_settings = cache_get_data('user_settings-'.$id_member.'smf', 60)) == null) {
	mysql_select_db($db_name, mysql_connect($db_server, $db_user, $db_passwd));
	$result = mysql_query('
		SELECT mem.*, IFNULL(a.id_attach, 0) AS id_attach, a.filename, a.attachment_type
		FROM '.$db_prefix.'members AS mem
		LEFT JOIN '.$db_prefix.'attachments AS a ON (a.id_member = '.$id_member.')
		WHERE mem.id_member = '.$id_member.'
		LIMIT 1');
	$user_settings = mysql_fetch_assoc($result);
	mysql_free_result($result);
	// This is a pure memory based chat, we want to limit our database connections
	// Doing it the SMF way but we are not checking cache level.
	cache_put_data('user_settings-'.$id_member.'smf', $user_settings, 60);
}

if ($id_member != 0) {chatMain();} else {echo 'access denied<br />';}

function chatMain() {

	$action = array(
		'get' => 'chatGet',
		'send' => 'chatSend',
	);

	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" ); 
	header("Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" ); 
	header("Cache-Control: no-cache, must-revalidate" ); 
	header("Pragma: no-cache" );

	if(array_key_exists($_REQUEST['act'],$action)) {
		header("Content-Type: text/xml; charset=utf-8");
		call_user_func($action[$_REQUEST['act']]);
	} else {
		chatHome();
	}
}

function chatHome() {

	echo '<html>
	<head>
		<title>Membox</title>
		<script language="JavaScript" type="text/javascript">
			var timer;
			function chatStart() {
				document.getElementById(\'message\').focus();
				chatGet();
			}
			function chatGet() {
				var xmlhttp = new XMLHttpRequest();
				xmlhttp.open("GET", "?act=get", true);
				xmlhttp.send();
				xmlhttp.onreadystatechange = function() {
					if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
						document.getElementById("chat").innerHTML = xmlhttp.responseText;
						document.getElementById("chat").scrollTop = document.getElementById("chat").scrollHeight;
					}
				};
				timer = setTimeout(\'chatGet();\',2000);
			}
			function chatSend() {
				if(document.getElementById(\'message\').value == \'\') {
					return;
				}
				var xmlhttp = new XMLHttpRequest();
				xmlhttp.open("POST", "?act=send", true);
				xmlhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
				var param = \'msg=\' + document.getElementById(\'message\').value;
				xmlhttp.send(param);
				document.getElementById(\'message\').value = \'\';
				clearInterval(timer);
				chatGet();
			}
			function chatSubmit() {
				chatSend();
				return false;
			}
		</script>
	</head>
	<body onLoad="javascript:chatStart();">
		<div id="chat" style="height:300px;width:500px;overflow:auto;"></div>
		<form onSubmit="return chatSubmit();">
			<input type="button" value="Refresh Chat" onClick="javascript:chatGet();" />
			<input type="text" id="message" name="message" style="width:500px;" />
			<input type="button" value="Send" onClick="javascript:chatSend();" />
		</form>
	</body>
</html>';

}

function chatGet() {
	global $start;
	if (($data = cache_get_data('membox', 600)) != null) {
		$msgs = array_reverse($data['msg']);
		foreach ($msgs as &$msg)
			echo $msg.'<br />';
	} else
		echo 'No Chat Data.';
	$time = microtime();
	$time = explode(' ', $time);
	$time = $time[1] + $time[0];
	$finish = $time;
	$total_time = round(($finish - $start), 4);
	echo 'Lag '.$total_time.' seconds.';
}

function chatSend() {

	global $user_settings;
	
	if (($data = cache_get_data('membox', 600)) == null) {
		$data = array(
			'time_start' => time(),
			'msg' => array(),
		);
	} else {
		$data['msg'] = array_slice($data['msg'], 0, 20);
	}

	if (isset($_REQUEST['msg'])) {
		$post = str_replace(array("\r\n", "\r", "\n"), "", $_REQUEST['msg']);
		array_unshift($data['msg'], '<b>'.$user_settings['member_name'].'</b> - '.htmlspecialchars(strip_tags($post)));
	}
	cache_put_data('membox', $data, 600);
}

function safe_unserialize($data) {
	if (preg_match('/(^|;|{|})O:([0-9]|\+|\-)+/', $data) === 0)
		return @unserialize($data);
}
?>
