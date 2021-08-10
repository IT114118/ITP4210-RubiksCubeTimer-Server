<?php
/*
	Input: (POST) "username", "token", "record"

	Success responce JSON. Example:
	{"status":"success","rank":1}

	Error http response code
	500 -> PHP code error
	501 -> Username or token or record not set properly
	502 -> MySQL connect error
	503 -> Token does not exists
	504 -> Token expired
	505 -> Record not found
	506 -> Fail to get rank
*/

// Hide php warning and notice
error_reporting(0);

if (!isset($_POST["username"]) || !isset($_POST["token"]) || !isset($_POST["record"])) {
	http_response_code(501);
	die();
}

$conn = mysqli_connect("127.0.0.1", "root", "usbw", "project4210");
if (!$conn) {
	http_response_code(502);
	die();
}

$username = $_POST["username"];
$token = $_POST["token"];
$record = $_POST["record"];

// Check the token exists and availability
$prep = "SELECT uid, UNIX_TIMESTAMP(last_login) AS ll FROM accounts a, authtoken t WHERE a.id = t.uid AND username = ? AND token = ?";
$stmt = $conn->prepare($prep);
$stmt->bind_param('ss', $username, $token);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows < 1) {
	http_response_code(503);
	die();
}

// Check is token expired (one week)
$row = $res->fetch_assoc();
$uid = $row["uid"];
if ((time() - 604800) >= $row["ll"]) {
	http_response_code(504);
	die();
}

// Update last_login to extend the token life and store to database
$stmt = $conn->prepare("UPDATE authtoken SET last_login = now() WHERE uid = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();

// Check record exists
$stmt = $conn->prepare("SELECT uid FROM records WHERE uid = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows < 1) {
	// Add new record if not exists
	$stmt = $conn->prepare("INSERT INTO records (uid, record) VALUES (?, ?)");
	$stmt->bind_param('id', $uid, $record);
	$stmt->execute();
} else {
	// Update the record if exists
	$stmt = $conn->prepare("UPDATE records SET record = ? WHERE uid = ?");
	$stmt->bind_param('di', $record, $uid);
	$stmt->execute();
}


$res = $conn->query("SELECT username, record FROM records r, accounts a WHERE r.uid = a.id ORDER BY record");
if ($res->num_rows < 1) {
	http_response_code(505);
	die();
}

$i = 1;
$return = array();
while ($row = $res->fetch_assoc()) {
	$info = array();
	$info["rank"] = ($row["record"] == -1) ? -1 : $i++;
	
	$len = sizeof($return);
	if ($i > 1 && $return[$len-1]["record"] == $row["record"]) {
		$info["rank"] = $return[$len-1]["rank"];
	}

	$info["record"] = number_format($row["record"], 3);
	if ($row["username"] == $username) {
		// Return Global Rank
		header('Content-Type: application/json');
		echo json_encode(array("action" => "get_global_rank", "status" => "success", "rank" => $info["rank"]));
		exit();
	}
	
	$return[] = $info;
}

http_response_code(506);
die();
