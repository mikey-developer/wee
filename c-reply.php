<?php

session_start();

$host = "localhost";
$db   = "base";
$user = "user";
$pass = 'passw';
$userId = $_SESSION['telegram_user_id'] ?? null;
$pdo = new PDO(
    "mysql:host=$host;dbname=$db;charset=utf8mb4",
    $user,
    $pass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

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
                            "url" => ""
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


$postId = trim($_POST['postid'] ?? '');
$content = trim($_POST['content'] ?? '');
$commentId = trim($_POST['commentid'] ?? '');
$authorId = trim($_POST['authorid'] ?? '');

if (!$userId) {
    die("User topilmadi");
}

$stmt = $pdo->prepare("
    INSERT INTO comments 
    (post_id, reply_id, replied_user_id, content, user_id)
    VALUES
    (?, ?, ?, ?, ?)
");

$stmt->execute([
    $postId, $commentId, $authorId, $content, $userId
]);

send_open_message($authorId, "$fufirst_name\n@$fuusername\nОтветил(а) на ваш коммент\n$content");
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
