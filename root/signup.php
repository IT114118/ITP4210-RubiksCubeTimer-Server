<?php
/*
	Input: (POST) "username", "password"

	Success responce JSON. Example:
	{"status":"success","token":"6a7197a397bdb3f713a6269555ef606dec1350af"}

	Error http response code
	500 -> PHP code error
	501 -> Username or password not set properly
	502 -> MySQL connect error
	503 -> User already exist
	504 -> Fail to create account
	505 -> Fail to create token
*/

// Hide php warning and notice
error_reporting(0);

if (!isset($_POST["username"]) || !isset($_POST["password"])) {
	http_response_code(501);
	die();
}

$conn = mysqli_connect("127.0.0.1", "root", "usbw", "project4210");
if (!$conn) {
	http_response_code(502);
	die();
}

$username = $_POST["username"];
$password = $_POST["password"];

// Check username exist in table `accounts`
$stmt = $conn->prepare("SELECT id FROM accounts WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows >= 1) {
	http_response_code(503);
	die();
}

// Add username and password to table `accounts`
$stmt = $conn->prepare("INSERT INTO accounts (username, password) VALUES (?, ?)");
$stmt->bind_param('ss', $username, $password);
$stmt->execute();
if ($stmt->affected_rows <= 0) {
	http_response_code(504);
	die();
}

// Get account id
$stmt = $conn->prepare("SELECT id FROM accounts WHERE username = ? LIMIT 1");
$stmt->bind_param('s', $username);
$stmt->execute();
$res = $stmt->get_result();
$uid = $res->fetch_assoc()["id"];

// Create token and store to database
$token = bin2hex(openssl_random_pseudo_bytes(20));
$stmt = $conn->prepare("INSERT INTO authtoken (uid, token) VALUES (?, ?)");
$stmt->bind_param('is', $uid, $token);
$stmt->execute();
if ($stmt->affected_rows <= 0) {
	http_response_code(505);
	die();
}

// Return JSON with token
header('Content-Type: application/json');
echo json_encode(array("action" => "signup", "status" => "success", "token" => $token));
exit();
