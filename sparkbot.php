<?php
include 'common2.inc';
include 'config.inc';

DoRun($dbtype,$dbserver,$dbname,$dbun,$dbpw,$sparkaccesstoken,$personid,$personemail);

function DoRun($dbt,$dbs,$dbn,$dbu,$dbp,$token,$pid,$pem) {
	$entityBody = file_get_contents('php://input');
	$arr_body = json_decode($entityBody, true);
	$hookid = $arr_body["id"];
	$msgid = $arr_body["data"]["id"];
	$personid = $arr_body["data"]["personId"];
	$roomid = $arr_body["data"]["roomId"];
	//if($personid != $pid) { SendMessage($token,$roomid,"$roomid"); }

	if(($msgid!="") && ($personid != $pid)) {
		$arr_ret = DoGet("https://api.ciscospark.com/v1/messages/$msgid","","","Authorization: Bearer $token");
		$body = $arr_ret[0];
		$arr_body = json_decode($body, true);
		$bodytext = $arr_body["text"];
		$bodyfrom = $arr_body["personEmail"];
		$bodycre = $arr_body["created"];

		//if(strpos($bodytext,$pem)!==false) {
		//	$msgret = "Yes, master? I hear your voice, but I haven't been told how to obey!";
		//	$hookdata = array("roomId" => $roomid, "text" => $msgret);
		//	$hookdata = json_encode($hookdata);
		//	$arr_ret = DoPost("https://api.ciscospark.com/v1/messages",$hookdata,"","application/json","","","Authorization: Bearer $token");
		//}

		$conn = do_db_connect($dbt,$dbs,$dbn,$dbu,$dbp);
		$strSQL = "SELECT * FROM rooms WHERE roomid = '$roomid'";
		$arr_result = do_db_query($conn,$strSQL,1,$dbt,$dbs,$dbn,$dbu,$dbp);
		$num = $arr_result[0];
		//if($personid != $pid) { SendMessage($token,$roomid,"Count: $num"); }
		$result = $arr_result[1];
		if($num>0) {
			$pkid = do_db_result($dbt,$result,0,"pkid");
			$bodytext = mysql_real_escape_string($bodytext,$conn);
			$strSQL = "INSERT INTO messages (fkroom,messageid,message,useremail,userid,dcreated) VALUES ('$pkid','$msgid','$bodytext','$bodyfrom','$personid','$bodycre')";
			//if($personid != $pid) { SendMessage($token,$roomid,"$strSQL"); }
			$result = do_db_query($conn,$strSQL,0,$dbt,$dbs,$dbn,$dbu,$dbp);
		}

		if(strpos($bodytext,$pem)!==false) {
			//$msgret = "Hello! I am a bot, but I haven't been programmed to interact in this way.";
			$msgret = TryBotQuery($dbt,$dbs,$dbn,$dbu,$dbp,$bodytext,$pem,$roomid);
			if($msgret!="") {
				$hookdata = array("roomId" => $roomid, "text" => $msgret);
				$hookdata = json_encode($hookdata);
				$arr_ret = DoPost("https://api.ciscospark.com/v1/messages",$hookdata,"","application/json","","","Authorization: Bearer $token");
			}
		}

	} else {
		//Didn't get a Message ID
	}

}

function TryBotQuery($dbt,$dbs,$dbn,$dbu,$dbp,$msgtext,$pem,$rid) {
	$ret = "";
	$msglower = strtolower($msgtext);
	$msgsearch = "";

	$arr_keymsg = array("who talks about","who says");
	for($y=0;$y<count($arr_keymsg);$y++) {
		//$keymsg = "who talks about";
		$keymsg = $arr_keymsg[$y];
		$keyloc = strpos($msglower, $keymsg);

		if($keyloc!==false) {
			$msgsearch = trim(substr($msglower,$keyloc+mb_strlen($keymsg)));
			$msgsearch = preg_replace('/[^ \w]+/', '', $msgsearch);
			//$ret = "You mentioned ($msgsearch)";
			$strSQL = "SELECT COUNT(messages.pkid) AS msgcount,messages.useremail FROM messages INNER JOIN rooms ON (rooms.pkid = messages.fkroom) WHERE ((messages.message LIKE '$msgsearch') OR (messages.message LIKE '%$msgsearch') OR (messages.message LIKE '$msgsearch%') OR (messages.message LIKE '%$msgsearch%')) AND ((messages.message NOT LIKE '$pem') AND (messages.message NOT LIKE '%$pem') AND (messages.message NOT LIKE '$pem%') AND (messages.message NOT LIKE '%$pem%')) AND (messages.useremail NOT LIKE '$pem') AND (rooms.roomid = '$rid') GROUP BY messages.useremail";
			$arr_result = do_db_query($conn,$strSQL,1,$dbt,$dbs,$dbn,$dbu,$dbp);
			$num = $arr_result[0];
			$result = $arr_result[1];
			for($z=0;$z<$num;$z++) {
				$uemail = do_db_result($dbt,$result,$z,"useremail");
				if(strtolower($uemail)==strtolower($pem)) {
					//Don't mention the bots numbers
				} else {
					$umsgc = do_db_result($dbt,$result,$z,"msgcount");
					$ret .= "$uemail has mentioned '$msgsearch' $umsgc time(s) in this room.\n";
				}
			}
		}
	}

	if($ret=="") {
		if($msgsearch=="") {
			$ret = "I see you mentioned me, but I'm not sure what to do. Try asking me 'Who talks about x' or 'Who says x'.";
		} else {
			$ret = "No one has mentioned '$msgsearch'.";
		}
	}

	return $ret;
}

function SendMessage($token,$roomid,$msgret) {
	$hookdata = array("roomId" => $roomid, "text" => $msgret);
	$hookdata = json_encode($hookdata);
	$arr_ret = DoPost("https://api.ciscospark.com/v1/messages",$hookdata,"","application/json","","","Authorization: Bearer $token");
}

?>
