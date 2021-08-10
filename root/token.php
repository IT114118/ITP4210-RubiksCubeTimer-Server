<?php
/*
	Input: (POST) "username", "token"

	Success responce JSON. Example:
	{"status":"success","token":"cfaf07932727ffcaa57fc684547a9ffaf77ff75c"}

	Error http response code
	500 -> PHP code error
	501 -> Username or token not set properly
	502 -> MySQL connect error
	503 -> Token does not exists
	504 -> Token expired
*/

// Hide php warning and notice
error_reporting(0);

if (!isset($_POST["username"]) || !isset($_POST["token"])) {
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
if ((time() - 604800) >= $row["ll"]) {
	http_response_code(504);
	die();
}

// Update last_login to extend the token life and store to database
$stmt = $conn->prepare("UPDATE authtoken SET last_login = now() WHERE uid = ?");
$stmt->bind_param('i', $row["uid"]);
$stmt->execute();

// or ( Use this if only allows one session only )
// Generate another token and update last_login and store to database
#$token = bin2hex(openssl_random_pseudo_bytes(20));
#$stmt = $conn->prepare("UPDATE authtoken SET token = ?, last_login = now() WHERE uid = ?");
#$stmt->bind_param('si', $token, $uid);
#$stmt->execute();

header('Content-Type: application/json');
echo json_encode(array("action" => "token", "status" => "success", "token" => $token));
exit();
