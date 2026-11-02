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
$postId = $_GET['id'];

if (!$userId) {
    die("User topilmadi");
}

$stmt = $pdo->prepare("
    DELETE FROM posts WHERE id = ?
");

$stmt->execute([
    $postId
]);
header("Location: profile.php");
exit;
