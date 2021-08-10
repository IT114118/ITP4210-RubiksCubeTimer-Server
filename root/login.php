<?php
/*
	Input: (POST) "username", "password"

	Success responce JSON. Example:
	{"status":"success","token":"cfaf07932727ffcaa57fc684547a9ffaf77ff75c"}

	Error http response code
	500 -> PHP code error
	501 -> Username or password not set properly
	502 -> MySQL connect error
	503 -> User does not exist
	504 -> Password does not match
	505 -> Fail to create token
	506 -> Fail to update token
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

// Check user exists
$stmt = $conn->prepare("SELECT id, password FROM accounts WHERE username = ?");
$stmt->bind_param('s', $username);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows < 1) {
	http_response_code(503);
	die();
}

// Check password match
$row = $res->fetch_assoc();
$uid = $row["id"];
if ($row["password"] !== $password) {
	http_response_code(504);
	die();
}

// Get the token, last_login
$stmt = $conn->prepare("SELECT token, UNIX_TIMESTAMP(last_login) AS ll FROM authtoken WHERE uid = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows < 1) {
	// Create token and store to database if token not exists
	$token = bin2hex(openssl_random_pseudo_bytes(20));
	$stmt = $conn->prepare("INSERT INTO authtoken (uid, token) VALUES (?, ?)");
	$stmt->bind_param('is', $uid, $token);
	$stmt->execute();
	if ($stmt->affected_rows <= 0) {
		http_response_code(505);
		die();
	}
} else {
	$row = $res->fetch_assoc();
	$token = $row["token"]; // Get old token
	if ((time() - 604800) >= $row["ll"]) { // token expired after one week
		// Generate another token and update last_login and store to database if expired
		$token = bin2hex(openssl_random_pseudo_bytes(20));
		$stmt = $conn->prepare("UPDATE authtoken SET token = ?, last_login = now() WHERE uid = ?");
		$stmt->bind_param('si', $token, $uid);
		$stmt->execute();
		if ($stmt->affected_rows <= 0) {
			http_response_code(506);
			die();
		}
	} else {
		// Update last_login to extend the token life and store to database if not expired
		$stmt = $conn->prepare("UPDATE authtoken SET last_login = now() WHERE uid = ?");
		$stmt->bind_param('i', $uid);
		$stmt->execute();
	}
}

// Return JSON with token
header('Content-Type: application/json');
echo json_encode(array("action" => "login", "status" => "success", "token" => $token));
exit();
