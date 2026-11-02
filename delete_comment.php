<?php

session_start();

$host = "localhost";
$db   = "base";
$user = "user";
$pass = 'passw;

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
$commentId = $_POST['comment_id'] ?? null;

if (!$userId || !$commentId) {
    die("Xatolik");
}

$stmt = $pdo->prepare("
    DELETE FROM comments
    WHERE id = ?
      AND user_id = ?
");

$stmt->execute([
    $commentId,
    $userId
]);


header("Location: index.php");
exit;
