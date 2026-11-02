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

if (!$userId) {
    die("User topilmadi");
}

function send_open_message($chat_id, $text, $userId) {
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
            "timeout" => 10,
            "ignore_errors" => true
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    error_log("Telegram javobi: " . ($response === false ? "So'rov bajarilmadi" : $response));

    return $response;
}

function send_channel_message($chat_id, $text, $userId) {
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
                        "url" => ""
                    ]
                ]
            ]
        ], JSON_UNESCAPED_UNICODE)
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
$content = trim($_POST['content'] ?? '');

$image = null;

if (!empty($_FILES['image']['name'])) {

    $uploadDir = "uploads/posts/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    $mime = mime_content_type($_FILES['image']['tmp_name']);

    if (!isset($allowed[$mime])) {
        die("Faqat JPG, PNG yoki WEBP rasm yuklash mumkin");
    }

    $extension = $allowed[$mime];

    $fileName = uniqid() . "." . $extension;

    $filePath = $uploadDir . $fileName;

    if (!move_uploaded_file(
        $_FILES['image']['tmp_name'],
        $filePath
    )) {
        die("Rasmni yuklashda xatolik");
    }

    $image = $filePath;
}


if ($content === '' && $image === null) {
    die("Post bo'sh bo'lishi mumkin emas");
}

$stmt = $pdo->prepare("
    INSERT INTO posts
        (user_id, content, image, created_at)

    VALUES
        (?, ?, ?, NOW())
");

$stmt->execute([
    $userId,
    $content !== '' ? $content : null,
    $image
]);

$stmt = $pdo->prepare("
    SELECT first_name, username, tg_channel
    FROM users
    WHERE id = ?
");

$stmt->execute([$userId]);

$uid = $stmt->fetch(PDO::FETCH_ASSOC);

$uidfname = $uid['first_name'] ?? '';
$uiduname = !empty($uid['username']) ? '@' . $uid['username'] : '';
$tgchannel = !empty($uid['tg_channel']) ? $uid['tg_channel'] : '';

send_channel_message(
    $tgchannel,
    "$content",
    $userId
);

$stmt = $pdo->prepare("
    SELECT follower_id
    FROM follows
    WHERE following_id = ?
");

$stmt->execute([$userId]);

$followers = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($followers as $followerId) {
    send_open_message(
        $followerId,
        "Подписки\n{$uidfname}\n{$uiduname}\n{$content}",
        $userId
    );
}

header("Location: profile.php");
exit;
