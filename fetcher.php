<?php
	include_once("msql.php");

	class LuckyFetcher
	{
		private $sql;
		
		function __construct()
		{
			// init mysql connection
			$this->sql = get_mysql_conn();
			mysql_query("SET NAMES 'utf8'");
			mysql_query("SET CHARACTER_SET_CLIENT=utf8");
			mysql_query("SET CHARACTER_SET_RESULTS=utf8");
			mysql_select_db("sa072686");
		}
		
		// update database, if $force is false (default) then it only updates when last update date is not today.
		function update($force = false)
		{
			$update_f = $force;
			// ask for date
			$day_str = date("Ymd");
			$day = intval($day_str);
			// if not forced, get last update date from database.
			if(!$update_f)
			{
				$res = mysql_query("SELECT `value` FROM `lcat_conf` WHERE `name` = 'utime'");
				$buf = mysql_fetch_array($res);
				if(intval($buf[0]) != $day)
				{
					$update_f = true;
				}
			}
			// if decide to update, do it!
			if($update_f)
			{
				// first modify update date
				mysql_query("UPDATE `lcat_conf` SET `value` = '".$day."' WHERE `name` = 'utime'");
				// connect to luckycat to fetch problem list
				$fp = fsockopen("luckycat.kshs.kh.edu.tw", 80, $errno, $errstr, 30);
				if(!$fp)
				{
					return false;
				}
				$out = sprintf("POST /select.asp HTTP/1.1\r\n", $id);
				$out .= "Host: luckycat.kshs.kh.edu.tw\r\n";
				$out .= "Connection: Close\r\n";
				$out .= "Content-Type: application/x-www-form-urlencoded\r\n";
				$out .= "Cache-Control: max-age=0\r\n";
				$out .= "User-Agent: Mozilla/5.0 (Windows NT 6.1) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.78 Safari/535.11\r\n";
				$out .= "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n";
				$out .= "Accept-Encoding: gzip,deflate,sdch\r\n";
				$out .= "Accept-Language: zh-TW,zh;q=0.8,en-US;q=0.6,en;q=0.4\r\n";
				$out .= "Accept-Charset: Big5,utf-8;q=0.7,*;q=0.3\r\n";
				$out .= "Content-Length: 17\r\n";
				$out .= "\r\n";
				$out .= "R1=999&B1=+Go%21+";
				fputs($fp, $out);
				$log = '';
				while (!feof($fp)){
					$log .= fgets($fp, 32768);
				}
				fclose($fp);
				
				// fetch existing list, build hash table
				$res = mysql_query("SELECT * FROM `lcat_plist`");
				$tbl = array();
				while($t = mysql_fetch_assoc($res))
				{
					$tbl[intval($t['pnum'])] = $t;
				}
				
				// parse html data
				preg_match_all("/<td\\salign=\"cen.+?>\s*(\d+).+?href=(.+?)>(.*?)<.+?<td>(.*?)<\/td.*?<td>(.*?)<\/td.+?<td.*?>(.*?)<\/td.+?<td.*?>(.*?)</s", $log, $buf);
				
				// fetch problem list from uhunt, for pnum -> pid translation
				$fp = fsockopen("uhunt.onlinejudge.org", 80, $errno, $errstr, 30);
				if(!$fp)
				{
					return false;
				}
				$out = sprintf("GET /api/p HTTP/1.1\r\n");
				$out .= "Host: uhunt.onlinejudge.org\r\n";
				$out .= "Connection: Close\r\n";
				$out .= "Content-Type: application/x-www-form-urlencoded\r\n";
				$out .= "Cache-Control: max-age=0\r\n";
				$out .= "User-Agent: Mozilla/5.0 (Windows NT 6.1) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.78 Safari/535.11\r\n";
				$out .= "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n";
				$out .= "Accept-Encoding: gzip,deflate,sdch\r\n";
				$out .= "Accept-Language: zh-TW,zh;q=0.8,en-US;q=0.6,en;q=0.4\r\n";
				$out .= "Accept-Charset: Big5,utf-8;q=0.7,*;q=0.3\r\n";
				$out .= "\r\n";
				fputs($fp, $out);
				$log = '';
				while (!feof($fp)){
					$log .= fgets($fp, 32768);
				}
				fclose($fp);
				// decode gzip, use china code
				$loc = mb_strpos($log, "\r\n\r\n", 0, 'utf8');
				$body = mb_substr($log, $loc+4, -1, 'utf8');
				$log = '';
				$chunk        = strtok($body,"\r\n");//获取第一个chunked string的16进制串长标识
				while( $len = (hexdec($chunk) + 0) ) //最后一个chunked string的串长铁定是0，这是协议规范
				{
					$start   = strlen($chunk) + 2;//从chunked标识后读取串, +2是因为还要考虑"\r\n"
					$log      .= substr($body , $start , $len );//读取真正要decode的http body
					$body      = substr($body , $start + $len + 2); //body把上一个chunked去掉
					$chunk   = strtok($body,"\r\n");//查找下一个chunked长度标识
				}
				$log = gzinflate(substr($log,10));//忽略前10个字符
				$uhunt_plist = json_decode($log);
				$uhunt_table = array();
				for ($i=0, $lim=count($uhunt_plist); $i<$lim; $i++)
				{
					$uhunt_table[$uhunt_plist[$i][1]] = $uhunt_plist[$i];
				}
				
				// add problems which is not in current problem list
				for($i=0, $lim=count($buf[0]); $i<$lim; $i++)
				{
					// only adding data when it's not exist yet.
					if(!$tbl[intval($buf[1][$i])])
					{
						// need to translate problem number to problem id, felix API only gives solved list by pid, not pnum
						$pid = $uhunt_table[$buf[1][$i]][0];
						// insert into database
						preg_match("/pid.+?(\d+),/", $log, $b);
						$buf[7][$i] = preg_replace("/\//", "-", $buf[7][$i]);
						$cmd = "INSERT INTO `lcat_plist` SET `pnum`='".$buf[1][$i]."', `pid`='".$pid."', `name`='".$buf[3][$i]."', `url`='".$buf[2][$i]."', ";
						$cmd .= "`hint`='".$buf[5][$i]."', `type`='".$buf[6][$i]."', `date`='".$buf[7][$i]."', `lvl`='".(strlen($buf[4][$i])/2-1)."'";
						mysql_query($cmd);
					}
				}
			}
			return true;
		}
		
		// display problems not solved
		function display($uid)
		{
			// first use felix API to get solved list by uid
			$fp = fsockopen("uhunt.onlinejudge.org", 80, $errno, $errstr, 30);
			if(!$fp)
			{
				return false;
			}
			$out = "GET /api/solved-bits/".$uid." HTTP/1.1\r\n";
			$out .= "Host: uhunt.onlinejudge.org\r\n";
			$out .= "Connection: Close\r\n";
			$out .= "Content-Type: application/x-www-form-urlencoded\r\n";
			$out .= "Cache-Control: max-age=0\r\n";
			$out .= "User-Agent: Mozilla/5.0 (Windows NT 6.1) AppleWebKit/535.11 (KHTML, like Gecko) Chrome/17.0.963.78 Safari/535.11\r\n";
			$out .= "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n";
			$out .= "Accept-Encoding: gzip,deflate,sdch\r\n";
			$out .= "Accept-Language: zh-TW,zh;q=0.8,en-US;q=0.6,en;q=0.4\r\n";
			$out .= "Accept-Charset: Big5,utf-8;q=0.7,*;q=0.3\r\n";
			$out .= "\r\n";
			fputs($fp, $out);
			$log = '';
			while (!feof($fp)){
				$log .= fgets($fp, 32768);
			}
			fclose($fp);
			$loc = mb_strpos($log, "\r\n\r\n", 0, 'utf8');
			$body = mb_substr($log, $loc+4, -1, 'utf8');	
			$log = '';
			$chunk        = strtok($body,"\r\n");//获取第一个chunked string的16进制串长标识
			while( $len = (hexdec($chunk) + 0) ) //最后一个chunked string的串长铁定是0，这是协议规范
			{
				$start   = strlen($chunk) + 2;//从chunked标识后读取串, +2是因为还要考虑"\r\n"
				$log      .= substr($body , $start , $len );//读取真正要decode的http body
				$body      = substr($body , $start + $len + 2); //body把上一个chunked去掉
				$chunk   = strtok($body,"\r\n");//查找下一个chunked长度标识
			}
			$log = gzinflate(substr($log,10));//忽略前10个字符
			// parse solved list, it's by binary T/F table format, by PID not PNUM
			$f = false;
			preg_match_all("/\d+/", $log, $buf);
			$buf = $buf[0];
			$res = mysql_query("SELECT * FROM `lcat_plist`");
			$cnt = 0;
			$sb = '';
			while($t = mysql_fetch_assoc($res))
			{
				$id = intval($t['pid']);
				// if not solved
				if(!(intval($buf[($id>>5)+1]) & (1<<($id&31))))
				{
					// header
					if(!$f)
					{
						$sb .= <<<EOF
							<table border=1 id='tbl'>
								<tr><td>題號</td><td>題目名稱</td><td>難度</td><td>提示</td><td>解題方向</td><td>更新日期</td></tr>
EOF;
						$f = true;
					}
					// output format
					$sb .= "<tr><td>".$t['pnum']."</td><td><a href='http://luckycat.kshs.kh.edu.tw/".$t['url']."' target=_blank>".$t['name']."</a>";
					$sb .= "(<a href='http://uva.onlinejudge.org/external/".(floor($t['pnum']/100))."/".$t['pnum'].".html' target=_blank>原文</a>)";
					$sb .= " (<a href='http://uva.onlinejudge.org/index.php?option=com_onlinejudge&Itemid=8&page=submit_problem&problemid=".$t['pid']."' target=_blank>提交</a>)</td>";
					$sb .= "<td>".str_repeat("★", $t['lvl'])."</td>";
					$sb .= "<td><span id='h".$t['pnum']."' style='display:none;'>".$t['hint']."　</span><span id='hh".$t['pnum']."'><a href='javascript:show_hint(".$t['pnum'].");'>顯示</a></span></td>";
					$sb .= "<td><span id='t".$t['pnum']."' style='display:none;'>".$t['type']."　</span><span id='tt".$t['pnum']."'><a href='javascript:show_type(".$t['pnum'].");'>顯示</a></span></td>";
					$sb .= "<td>".$t['date']."　</td>";
					$sb .= "</tr>";
					$cnt++;
				}
			}
			if(!$f)
			{
				echo "恭喜！您已將 Lucky 貓的中譯題全寫掉了！";
			}
			else
			{
				echo "您還有 ".$cnt." 題中譯題未解！<br /><br />";
				echo $sb."</table>";
			}
		}
	}
?>
