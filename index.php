<?php
/* tools for UVa Online Judge, analyzing problem list LuckyCat ACM for user */
/* use uHunt API, fetch and analyze LuckyCat ACM site */

// set header
include_once("fetcher.php");
header("Content-type:text/html; charset:UTF-8");
date_default_timezone_set('Asia/Taipei');
echo <<<EOF
<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<title></title>
</head>
<body>
EOF;
echo "<script type='text/javascript' src='cat.js'></script>";
// check uid, if not specified, require user to input
if(!intval($_GET['uid']))
{
	echo <<<EOF
		<form action='index.php' method='get'>
		請輸入您的 UVa User ID: <input type='text' name='uid' id='uid'>
		(若不知道請在此輸入登入帳號查詢: <input type='text' id='unam'> <input type='submit' value='查詢我的UserID' onclick='unam_to_uid(); return false;'>)
		<span id='qmsg' style='color:#FF0000'></span><br />
		<input type='submit' value='送出'>
		</form>
		</body></html>
EOF;
	exit;
}
// prepare fetcher
$maid = new LuckyFetcher();
// update database, not forced
$maid->update();
// get data to display
$maid->display(intval($_GET['uid']));
echo <<<EOF
</body>
</html>
EOF;
?>