<?php

session_start();

$host = "localhost";
$db   = "db";
$user = "user";
$pass = 'passw';

function send_message($chat_id, $text) {
    $token = "";

    $url = "https://api.telegram.org/bot{$token}/sendMessage";

    $data = [
        "chat_id" => $chat_id,
        "text" => $text
    ];

    file_get_contents($url . "?" . http_build_query($data));
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

if (!$userId || !$postId) {
    die("Xatolik");
}


$stmt = $pdo->prepare("
    SELECT 1
    FROM likes
    WHERE post_id = ? AND user_id = ?
");

$stmt->execute([
    $postId,
    $userId
]);

$liked = $stmt->fetch();

$stmt->execute([
    $postId,
    $userId
]);

$liked = $stmt->fetch();

/* LIKE NOTIY EXPRIMENRTAL */
$stmt = $pdo->prepare("
    SELECT first_name, username FROM users WHERE id = ?
");

$stmt->execute([$_SESSION['telegram_user_id']]);
$telegramSend = $stmt->fetch(PDO::FETCH_ASSOC);

$first_name = $telegramSend['first_name'] ?? '';
$username = $telegramSend['username'] ?? '';

/* LIKE NOTIY to targetted user id EXPRIMENRTAL */
$stmt = $pdo->prepare("
    SELECT user_id, content FROM posts WHERE id = ?
");

$stmt->execute([$postId]);
$telegramUserId = $stmt->fetch(PDO::FETCH_ASSOC);

$tguid = $telegramUserId['user_id'] ?? '';
$tgucontent = $telegramUserId['content'] ?? '';






/*
|--------------------------------------------------------------------------
| Like / Unlike
|--------------------------------------------------------------------------
*/

if ($liked) {

    $stmt = $pdo->prepare("
        DELETE FROM likes
        WHERE post_id = ? AND user_id = ?
    ");

    $stmt->execute([
        $postId,
        $userId
    ]);
   

} else {

    $stmt = $pdo->prepare("
        INSERT INTO likes (post_id, user_id)
        VALUES (?, ?)
    ");

    $stmt->execute([
        $postId,
        $userId
    ]);
    
    $stmt = $pdo->prepare("
        INSERT INTO notify (pic, user_id, content)
        VALUES (?, ?, ?)
    ");

    $stmt->execute([
        $postId,
        $tguid,
        "$first_name\n@$username\nпоставил(а) лайк вашей публикации.\n$tgucontent"
    ]);
    
    send_open_message($tguid, "$first_name\n@$username\nпоставил(а) лайк вашей публикации.\n$tgucontent");
}


header("Location: index.php");
exit;
