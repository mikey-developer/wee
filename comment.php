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

$postId = $_POST['post_id'] ?? null;
$content = trim($_POST['content'] ?? '');

if (!$userId || !$postId || $content === '') {
    die("Comment xato");
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
    INSERT INTO comments
        (post_id, user_id, content)
    VALUES
        (?, ?, ?)
");

$stmt->execute([
    $postId,
    $userId,
    $content
]);

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

$stmt = $pdo->prepare("
    SELECT user_id, content
    FROM posts
    WHERE id = ?

");
$stmt->execute([
    $postId
]);
$fuser = $stmt->fetch(PDO::FETCH_ASSOC);
$authorId = $fuser['user_id'] ?? '';
$authorPost = $fuser['content'] ?? '';

send_open_message($authorId, "$fufirst_name\n@$fuusername\nпрокомментировал(а) вашу публикацию\n$authorPost:\n$content");

header("Location: index.php");
exit;
