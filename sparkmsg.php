<?php
include 'common2.inc';
include 'config.inc';

$looptimer = 30;

doloop($dbtype,$dbserver,$dbname,$dbun,$dbpw,$looptimer,$sparkaccesstoken);

function doloop($dbt,$dbs,$dbn,$dbu,$dbp,$lt,$token) {
        $x = 0;
        do {
                $x++;
		DoRun($dbt,$dbs,$dbn,$dbu,$dbp,$token);
                sleep($lt);
        } while ($x >= 0);
}

function DoRun($dbt,$dbs,$dbn,$dbu,$dbp,$token) {
	$conn = do_db_connect($dbt,$dbs,$dbn,$dbu,$dbp);
	//$strSQL = "SELECT * FROM rooms";

	date_default_timezone_set("America/Chicago");
	$sqldt = date("Y-m-d H:i:s");
	$strSQL = "SELECT rooms.*, DATEDIFF('$sqldt', rooms.dtarchived) AS doffset FROM rooms";
	$arr_result = do_db_query($conn,$strSQL,1,$dbt,$dbs,$dbn,$dbu,$dbp);
	$num = $arr_result[0];
	$result = $arr_result[1];
	for($x=0;$x<$num;$x++) {
		$rid = do_db_result($dbt,$result,$x,"roomid");
		$pkid = do_db_result($dbt,$result,$x,"pkid");
		$doff = do_db_result($dbt,$result,$x,"doffset");
		if((is_null($doff)) || ($doff > 1)) {
			GetRoomMessages($dbt,$dbs,$dbn,$dbu,$dbp,$token,$rid,$pkid);
			$strSQL2 = "UPDATE rooms SET dtarchived = '$sqldt' WHERE roomid = '$rid'";
			$result2 = do_db_query($conn,$strSQL2,0,$dbt,$dbs,$dbn,$dbu,$dbp);
		}
	}
}

function GetRoomMessages($dbt,$dbs,$dbn,$dbu,$dbp,$token,$rid,$pkid) {
	$conn = do_db_connect($dbt,$dbs,$dbn,$dbu,$dbp);
	//Use Spark API to get List of Messages
	$beforemsg = "";
	do {
		$arr_ret = DoGet("https://api.ciscospark.com/v1/messages?roomId=$rid&max=10$beforemsg","","","Authorization: Bearer $token");
		$retbody = $arr_ret[0];
		$arr_mess = json_decode($retbody, true);
//var_dump($arr_mess);
		if(count($arr_mess["items"]) <= 0) { break; }

		date_default_timezone_set("America/Chicago");

		for($y=0;$y<count($arr_mess["items"]);$y++) {
			$msgid = $arr_mess["items"][$y]["id"];
			$msgtext = $arr_mess["items"][$y]["text"];
			$msgtext = mysql_real_escape_string($msgtext,$conn);
			$msgfrom = $arr_mess["items"][$y]["personId"];
			$msgeml = $arr_mess["items"][$y]["personEmail"];
			$msgcre = $arr_mess["items"][$y]["created"];
			echo "DEBUG. sparkmsg. $msgtext\n";
			$sqldt = date("Y-m-d H:i:s");

			$strSQL = "SELECT pkid FROM messages WHERE messageid = '$msgid'";
			$arr_result = do_db_query($conn,$strSQL,1,$dbt,$dbs,$dbn,$dbu,$dbp);
			$num = $arr_result[0];
			$result = $arr_result[1];
			if($num<=0) {
				$strSQL = "INSERT INTO messages (fkroom,messageid,message,useremail,userid,dcreated) VALUES ('$pkid','$msgid','$msgtext','$msgeml','$msgfrom','$msgcre')";
				$result = do_db_query($conn,$strSQL,0,$dbt,$dbs,$dbn,$dbu,$dbp);
			} else {
				//Message already in database
			}
		}
		$beforemsg = "&beforeMessage=$msgid";
	} while(count($arr_mess["items"]) > 0);

	//do_db_close($dbt,$conn);
}

?>
