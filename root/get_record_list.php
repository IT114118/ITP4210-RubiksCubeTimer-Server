<?php
/*
	Input: <null>

	Success responce JSON. Example:
	[{"rank":1,"username":"test","record":"1.000"},{"rank":1,"username":"admin","record":"1.000"}]

	Error http response code
	500 -> PHP code error
	501 -> Username or token not set properly
	502 -> MySQL connect error
	503 -> Token does not exists
	504 -> Token expired
*/

// Hide php warning and notice
error_reporting(0);

$conn = mysqli_connect("127.0.0.1", "root", "usbw", "project4210");
if (!$conn) {
	http_response_code(502);
	die();
}

$res = $conn->query("SELECT username, record FROM records r, accounts a WHERE r.uid = a.id ORDER BY record");
if ($res->num_rows < 1) {
	http_response_code(503);
	die();
}

$i = 1;
$return = array();
$invalid = array();
while ($row = $res->fetch_assoc()) {
	$info = array();
	$info["rank"] = ($row["record"] == -1) ? -1 : $i++;
	
	$len = sizeof($return);
	if ($i > 1 && $return[$len-1]["record"] == $row["record"]) {
		$info["rank"] = $return[$len-1]["rank"];
	}

	$info["username"] = $row["username"];
	$info["record"] = number_format($row["record"], 3);
	
	if ($row["record"] == -1) {
		$invalid[] = $info;
	} else {
		$return[] = $info;
	}
}

header('Content-Type: application/json');
echo json_encode($return);
exit();
