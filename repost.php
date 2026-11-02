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

if (!$userId || !$postId) {
    die("Xatolik");
}


// Post mavjudligini tekshirish
$stmt = $pdo->prepare("
    SELECT id
    FROM posts
    WHERE id = ?
");

$stmt->execute([$postId]);

if (!$stmt->fetch()) {
    die("Post topilmadi");
}


// Avval repost qilinganmi?
$stmt = $pdo->prepare("
    SELECT id
    FROM reposts
    WHERE post_id = ?
      AND user_id = ?
");

$stmt->execute([
    $postId,
    $userId
]);

$existing = $stmt->fetch();


// Agar allaqachon repost qilgan bo'lsa
if ($existing) {

    $stmt = $pdo->prepare("
        DELETE FROM reposts
        WHERE id = ?
    ");

    $stmt->execute([
        $existing['id']
    ]);

} else {

    // Repost yaratish
    $stmt = $pdo->prepare("
        INSERT INTO reposts
            (post_id, user_id, content)
        VALUES
            (?, ?, ?)
    ");

    $stmt->execute([
        $postId,
        $userId,
        $content !== '' ? $content : null
    ]);
}


header("Location: index.php", true, 303);
exit;
