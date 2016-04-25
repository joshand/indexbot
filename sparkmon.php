<?php
include 'common2.inc';
include 'config.inc';

$looptimer = 30;

doloop($dbtype,$dbserver,$dbname,$dbun,$dbpw,$looptimer,$sparkaccesstoken,$webhook);

function doloop($dbt,$dbs,$dbn,$dbu,$dbp,$lt,$token,$wh) {
        $x = 0;
        do {
                $x++;
		DoRun($dbt,$dbs,$dbn,$dbu,$dbp,$token,$wh);
                sleep($lt);
        } while ($x >= 0);
}

function DoRun($dbt,$dbs,$dbn,$dbu,$dbp,$token,$wh) {
	date_default_timezone_set("America/Chicago");
	$sqldt = date("Y-m-d H:i:s");
	$conn = do_db_connect($dbt,$dbs,$dbn,$dbu,$dbp);
	echo "DEBUG. sparkmon. ($sqldt) Set all rooms to inactive in DB.\n";
	$strSQL = "UPDATE rooms SET isactive = 0 WHERE true";
	$result = do_db_query($conn,$strSQL,0,$dbt,$dbs,$dbn,$dbu,$dbp);

	//Use Spark API to get List of Memberships
	//$arr_ret = DoGet("https://api.ciscospark.com/v1/memberships","","","Authorization: Bearer $token");
	$arr_ret = DoGet("https://api.ciscospark.com/v1/rooms","","","Authorization: Bearer $token");
	$retbody = $arr_ret[0];
	$arr_membs = json_decode($retbody, true);
	$roomid = "";
	$ret = "";
	$shortret = "";

	if(count($arr_membs["items"]) > 0) {
		for($x=0;$x<count($arr_membs["items"]);$x++) {
			//$roomid = $arr_membs["items"][$x]["roomId"];
			$roomid = $arr_membs["items"][$x]["id"];
			$roomtitle = $arr_membs["items"][$x]["title"];
			//echo "--$roomid--\n";
			$sqldt = date("Y-m-d H:i:s");

			$strSQL = "SELECT pkid FROM rooms WHERE roomid = '$roomid'";
			echo "DEBUG. sparkmon. ($sqldt) Room Query: $strSQL\n";
			$arr_result = do_db_query($conn,$strSQL,1,$dbt,$dbs,$dbn,$dbu,$dbp);
			$num = $arr_result[0];
			$result = $arr_result[1];
			if($num<=0) {
				echo "DEBUG. sparkmon. New Room: $roomid.\n";
				//$strSQL = "INSERT INTO rooms (name,roomid,dtcreated) VALUES ('$roomtitle','$roomid','$sqldt')";
				//$result = do_db_query($conn,$strSQL,0,$dbt,$dbs,$dbn,$dbu,$dbp);
				//Use Spark API to create Webhook
				$hookdata = array("name" => "Spark Test Bot", "targetUrl" => $wh,"resource" => "messages","event" => "created","filter" => "roomId=$roomid");
				$hookdata = json_encode($hookdata);
				$arr_ret = DoPost("https://api.ciscospark.com/v1/webhooks",$hookdata,"","application/json","","","Authorization: Bearer $token");
				$retbody = $arr_ret[0];
				$arr_hook = json_decode($retbody, true);
				$hookid = $arr_hook["id"];
				$hookstat = $arr_hook["event"];
				$sqldt = date("Y-m-d H:i:s");
				echo "DEBUG. sparkmon. ($sqldt) Hook Status: $hookstat.\n";
				if($hookstat=="created") {
					//Hook Created
					$strSQL = "INSERT INTO rooms (isactive,name,roomid,hookid,dtcreated) VALUES (1,'$roomtitle','$roomid','$hookid','$sqldt')";
					$sqldt = date("Y-m-d H:i:s");
					echo "DEBUG. sparkmon. ($sqldt) Database Query: $strSQL.\n";
	                        	$result = do_db_query($conn,$strSQL,0,$dbt,$dbs,$dbn,$dbu,$dbp);

					$hookdata = array("roomId" => $roomid, "text" => "Test Bot activated. The contents of this room will be archived.");
					$hookdata = json_encode($hookdata);
					$arr_ret = DoPost("https://api.ciscospark.com/v1/messages",$hookdata,"","application/json","","","Authorization: Bearer $token");
				} else {
					//Problem creating Web Hook
				}
			} else {
				//Room already in database, no need to add another Web Hook
				$sqldt = date("Y-m-d H:i:s");
				echo "DEBUG. sparkmon. ($sqldt) Set $roomid to active.\n";
				$strSQL = "UPDATE rooms SET isactive = 1 WHERE roomid = '$roomid'";
				$result = do_db_query($conn,$strSQL,0,$dbt,$dbs,$dbn,$dbu,$dbp);
			}
		}

		//Check for any rooms where the bot has been removed, and delete from db
		$strSQL = "SELECT pkid FROM rooms WHERE isactive = 0";
		$arr_result = do_db_query($conn,$strSQL,1,$dbt,$dbs,$dbn,$dbu,$dbp);
		$num = $arr_result[0];
		$result = $arr_result[1];
		for($x=0;$x<$num;$x++) {
			$pkid = do_db_result($dbt,$result,$x,"pkid");
			$sqldt = date("Y-m-d H:i:s");
			echo "DEBUG. sparkmon. ($sqldt) Deleting $pkid from DB.\n";
			$strSQL = "DELETE FROM messages WHERE fkroom = '$pkid'";
			$result = do_db_query($conn,$strSQL,0,$dbt,$dbs,$dbn,$dbu,$dbp);
		}
		$strSQL = "DELETE FROM rooms WHERE isactive = 0";
		$result = do_db_query($conn,$strSQL,0,$dbt,$dbs,$dbn,$dbu,$dbp);
	} else {
		$sqldt = date("Y-m-d H:i:s");
		echo "DEBUG. sparkmon. ($sqldt) Empty result set.\n";
	}

	do_db_close($dbt,$conn);
}

?>
