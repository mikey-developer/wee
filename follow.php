<?php

session_start();

$host = "localhost";
$db   = "db";
$user = "user";
$pass = 'passw';

$pdo = new PDO(
    "mysql:host=$host;dbname=$db;charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

$userId = $_SESSION['telegram_user_id'] ?? null;
$followingId = $_POST['user_id'] ?? null;

if (!$userId || !$followingId) {
    die("Xatolik");
}

if ($userId == $followingId) {
    die("O'zingizni follow qila olmaysiz");
}

function send_open_message($chat_id, $text) {
    $token = "";
    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    $data = [
        "chat_id" => $chat_id,
        "text" => $text,
        "reply_markup" => json_encode([
            "inline_keyboard" => [
                [
                    [
                        "text" => "Посмотреть",
                        "web_app" => [
                            "url" => "url")
                        ]
                    ]
                ]
            ]
        ])
    ];

    $options = [
        "http" => [
            "method" => "POST",
            "header" => "Content-Type: application/x-www-form-urlencoded",
            "content" => http_build_query($data),
            "timeout" => 10
        ]
    ];

    $context = stream_context_create($options);
    return file_get_contents($url, false, $context);
}

$stmt = $pdo->prepare("
    SELECT 1
    FROM follows
    WHERE follower_id = ?
      AND following_id = ?
");

$stmt->execute([
    $userId,
    $followingId
]);

$exists = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT first_name, username 
    FROM users 
    WHERE id = ?

");
$stmt->execute([
    $userId
]);
$fuser = $stmt->fetch(PDO::FETCH_ASSOC);
$fufirst_name = $fuser['first_name'] ?? '';
$fuusername = $fuser['username'] ?? '';

if ($exists) {

    $stmt = $pdo->prepare("
        DELETE FROM follows
        WHERE follower_id = ?
          AND following_id = ?
    ");

    $stmt->execute([
        $userId,
        $followingId
    ]);

} else {

    $stmt = $pdo->prepare("
        INSERT INTO follows
            (follower_id, following_id)
        VALUES
            (?, ?)
    ");

    $stmt->execute([
        $userId,
        $followingId
    ]);
    
    $stmt = $pdo->prepare("
        INSERT INTO notify (pic, user_id, content)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $userId,
        $followingId,
        "$fufirst_name\n@$fuusername\n\n$tgucontent\nподписал(а)сь на вас"
    ]);
    
    send_open_message($followingId, "$fufirst_name\n@$fuusername\nподписал(а)сь на вас");
}


header("Location: index.php");
exit;
