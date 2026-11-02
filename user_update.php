<?php

session_start();

header("Content-Type: application/json; charset=utf-8");

$host = "localhost";
$db   = "db";
$user = "user";
$pass = 'passw';

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

} catch (PDOException $e) {

    echo json_encode([
        "ok" => false,
        "error" => "DB error"
    ]);

    exit;
}


$data = json_decode(
    file_get_contents("php://input"),
    true
);


if (!$data || empty($data['id'])) {

    echo json_encode([
        "ok" => false,
        "error" => "Telegram user kelmadi",
        "received" => $data
    ]);

    exit;
}


$id        = (int)$data['id'];
$firstName = $data['first_name'] ?? '';
$lastName  = $data['last_name'] ?? '';
$username  = $data['username'] ?? null;
$photoUrl  = $data['photo_url'] ?? null;


// USER DATABASE
$sql = "
    INSERT INTO users
        (id, first_name, last_name, username, photo_url)

    VALUES
        (?, ?, ?, ?, ?)

    ON DUPLICATE KEY UPDATE
        first_name = VALUES(first_name),
        last_name = VALUES(last_name),
        username = VALUES(username),
        photo_url = VALUES(photo_url)
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    $id,
    $firstName,
    $lastName,
    $username,
    $photoUrl
]);


// SESSION
$_SESSION['user_id'] = $id;

// Sessionni majburan yozib qo'yamiz
session_write_close();


echo json_encode([
    "ok" => true,
    "telegram_id" => $id,
    "session_id" => session_id()
]);

exit;
