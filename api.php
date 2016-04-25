<?php
include 'config.inc';
include '../library/common.inc';
include '../library/auth.inc';

$request_parts = explode('/', $_GET['url']);
if(isset($_GET['type'])) {
  $file_type     = $_GET['type'];
} else {
  $file_type     = "json";
}
$req_ver       = $request_parts[0];
$req_type      = $request_parts[1];
$req_method    = $_SERVER['REQUEST_METHOD'];
if ($req_method == 'POST' && array_key_exists('HTTP_X_HTTP_METHOD', $_SERVER)) {
  if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'DELETE') {
    $req_method = 'DELETE';
  } else if ($_SERVER['HTTP_X_HTTP_METHOD'] == 'PUT') {
    $req_method = 'PUT';
  } else {
    throw new Exception("Unexpected Header");
  }
}
switch($req_method) {
  case 'DELETE':
  case 'POST':
    $req_data = cleanInputs($_POST);
    $req_file = file_get_contents("php://input");
    $req_id = $request_parts[2];
    break;
  case 'GET':
    $req_data = cleanInputs($_GET);
    $req_file = "";
    $req_id = "";
    break;
  case 'PUT':
    $req_data = cleanInputs($_GET);
    $req_file = file_get_contents("php://input");
    $req_id = $request_parts[2];
    break;
  default:
    //Invalid Method
    $req_file = "";
    $req_id = "";
    break;
}

//echo "--$req_ver--$req_method--$req_type--$file_type--$req_id--$req_file--";
$output = process_request($dbtype,$dbserver,$dbname,$dbun,$dbpw,$req_ver,$req_method,$req_type,$file_type,$req_id,$req_file,$req_data);

//Output based on request
switch($file_type) {
    case 'json':
        echo json_encode($output) . "\n";
        break;
    case 'xml':
        echo xml_encode($output) . "\n";
        break;
    default:
        echo $output . "\n";
}

function process_request($dbt,$dbs,$dbn,$dbu,$dbp,$req_version,$req_action,$req_method,$req_ft,$req_id,$req_file,$req_data) {
  $reqret = "";
  switch($req_version) {
    case 'v0':
      //$reqret_auth = check_auth_v0($dbt,$dbs,$dbn,$dbu,$dbp,$req_action,$req_ft,$req_id,$req_file,$req_data,$req_method);
      //if(array_key_exists("userpkid",$reqret_auth["sparkbot"]["authentication"])) {
      //  $uid = $reqret_auth["sparkbot"]["authentication"]["userpkid"];
      //} else {
      //  $uid = "";
      //}
      //$authstat = $reqret_auth["sparkbot"]["authentication"]["response"];

      //auth bypass
      $uid = "";
      $authstat = "authorized";
      $reqret_auth = array();

      $reqret = array();
      if(strtolower($authstat)=="authorized") {
        switch($req_method) {
          case 'query':
            $reqret = process_query_v0($dbt,$dbs,$dbn,$dbu,$dbp,$req_action,$req_ft,$req_id,$req_file,$req_data,$uid);
            break;
          default:
            break;
        }
      } else if(strtolower($authstat)=="unknown") {
	//do nothing
      }
      break;
    default;
      break;
  }

  if((strtolower($authstat)!="authorized") && (count($reqret)<=0)) {
    $reqret = array("sparkbot"=>array("response"=>"failed","details"=>"Authorization Failed"));
  }
  return array_merge_recursive($reqret,$reqret_auth);
}

// @@auth
function check_auth_v0($dbt,$dbs,$dbn,$dbu,$dbp,$req_action,$req_ft,$req_id,$req_file,$req_data,$req_method) {
  $reqret1 = "";
  $reqret2 = "";
  $reqret3 = "";
  $reqret4 = "";

  if($req_ft!="xml") {
    $arr_data = json_decode($req_file,true);
  } else {
    $arr_data = xml_decode($req_file);
  }
  if($req_action=="GET") {
    if(isset($_GET['psk'])) {
      $gettoken = $_GET['psk'];

      if($gettoken==$GLOBALS['apipsk']) {
          $reqret1 = "authorized";
          $reqret2 = "Authentication Passed";
      } else {
          $reqret1 = "unauthorized";
          $reqret2 = "Authentication Failed";
      }
    } else {
      $reqret1 = "unauthorized";
      $reqret2 = "No Authentication Parameters Provided";
    }
  } else {
    $reqret1 = "unauthorized";
    $reqret2 = "No Authentication Parameters Provided";
  }

  $arr_reqret = array("sparkbot"=>array("authentication"=>array("response"=>$reqret1, "detail"=>$reqret2)));
  if($reqret3!="") {
    $arr_reqret["sparkbot"]["authentication"][$reqret4] = $reqret3;
  }
  return $arr_reqret;
}

// @@query
function process_query_v0($dbt,$dbs,$dbn,$dbu,$dbp,$req_action,$req_ft,$req_id,$req_file,$req_data,$userid) {
  $reqret1 = "failed";
  $reqret2 = "";
  $arr_reqret = array();

  $conn = do_db_connect($dbt,$dbs,$dbn,$dbu,$dbp);
  date_default_timezone_set("America/New_York");

  switch($req_action) {
    case 'GET':
      if(isset($_GET['query'])) {
        $msgsearch = $_GET['query'];
	$msgsearch = mysql_real_escape_string($msgsearch,$conn);
      }
	$beml = $GLOBALS['botemail'];
	$strSQL = "SELECT COUNT(messages.pkid) AS msgcount,messages.useremail,rooms.name AS roomname FROM messages INNER JOIN rooms ON (rooms.pkid = messages.fkroom) WHERE ((messages.message LIKE '$msgsearch') OR (messages.message LIKE '%$msgsearch') OR (messages.message LIKE '$msgsearch%') OR (messages.message LIKE '%$msgsearch%')) AND ((messages.message NOT LIKE '$beml') AND (messages.message NOT LIKE '%$beml') AND (messages.message NOT LIKE '$beml%') AND (messages.message NOT LIKE '%$beml%')) AND (messages.useremail NOT LIKE '$beml') GROUP BY rooms.name,messages.useremail";
      $arr_result = do_db_query($conn,$strSQL,1,$dbt,$dbs,$dbn,$dbu,$dbp);
      $num = $arr_result[0];
      $result = $arr_result[1];

      $arr_reqret["sparkbot"]["query"]=$msgsearch;
      //$arr_reqret["sparkbot"]["debug"]=$strSQL;

      if($num>0) {
	$arr_reqret["sparkbot"]["results"]=$num;
        for($x=0;$x<$num;$x++) {
	  $msgcount = do_db_result($dbt,$result,$x,"msgcount");
	  $useremail = do_db_result($dbt,$result,$x,"useremail");
	  $roomname = do_db_result($dbt,$result,$x,"roomname");

	  //if(strtolower($useremail)==strtolower($beml)) {
		//don't show messages from the bot
	  //} else {
        	$xd = $x+1;
        	$arr_reqret["sparkbot"]["u$xd"]["useremail"]=$useremail;
		$arr_reqret["sparkbot"]["u$xd"]["roomname"]=$roomname;
        	$arr_reqret["sparkbot"]["u$xd"]["msgcount"]=$msgcount;
	  //}
	}
      } else {
		$arr_reqret["sparkbot"]["results"]=0;
      }

      break;
    default:
      break;
  }

  do_db_close($dbt,$conn);

  return($arr_reqret);
}

function xml_encode($output) {
	return $output;
}

function xml_decode($output) {
	return $output;
}

function cleanInputs($data) {
  $clean_input = Array();
  if (is_array($data)) {
    foreach ($data as $k => $v) {
      $clean_input[$k] = cleanInputs($v);
    }
  } else {
    $clean_input = trim(strip_tags($data));
  }
  return $clean_input;
}

?>
