<?php
session_start();
require_once("base.php");

$userId = $_SESSION['telegram_user_id'] ?? '';

if (!$userId) {
    die("Unknown user");
}

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

$request = trim($_POST['request'] ?? '');
$request = ltrim($request, '@');

$users = [];

if ($request !== '') {
    $stmt = $pdo->prepare("
        SELECT id, photo_url, first_name, username
        FROM users
        WHERE CAST(id AS CHAR) LIKE ?
           OR username LIKE ?
           OR id LIKE ?
        LIMIT 20
    ");

    $search = "%$request%";
    $stmt->execute([$search, $search, $search]);

    $users = $stmt->fetchAll();
}
?>

<section class="section-nav">
    <div class="nav" style="padding: 15px">
        <div class="p-ptext">
            <a href="index.php" class="text-decoration-none text-dark">
                <strong>
                    <i class="bi bi-arrow-left"></i>
                    &nbsp;Поиск пользователей
                </strong>
                <small>
                    Найдите пользователя по имени, юзернейму или ID
                </small>
            </a>
        </div>
    </div>
</section>

<section class="section-posts">

    <div class="content search-form-card">
        <form method="POST" action="">
            <div class="search-input-wrapper">
                <i class="bi bi-search"></i>

                <input
                    type="search"
                    name="request"
                    class="search-input"
                    placeholder="Имя, @username или ID..."
                    value="<?= htmlspecialchars($request) ?>"
                    autocomplete="off"
                    required
                >
            </div>

            <button type="submit" class="btn btn-primary search-button">
                Найти
            </button>
        </form>
    </div>

    <?php if ($request === ''): ?>

        <div class="content empty-users">
            <i class="bi bi-people"></i>
            <p>Введите имя, юзернейм или ID пользователя.</p>
        </div>

    <?php elseif (empty($users)): ?>

        <div class="content empty-users">
            <i class="bi bi-person-x"></i>
            <p>Пользователь не найден.</p>
        </div>

    <?php else: ?>

        <?php foreach ($users as $foundUser): ?>

            <div class="content user-result">
                <a
                    href="profile.php?id=<?= urlencode($foundUser['id']) ?>"
                    class="user-result-link"
                >
                    <div class="user-profile search-avatar">
                        <img
                            src="<?= htmlspecialchars(
                                $foundUser['photo_url'] ?: 'default-avatar.png'
                            ) ?>"
                            alt=""
                        >
                    </div>

                    <div class="user-info search-user-info">
                        <div class="search-user-name">
                            <?= htmlspecialchars($foundUser['first_name'] ?? 'User') ?>
                        </div>

                        <?php if (!empty($foundUser['username'])): ?>
                            <div class="search-username">
                                @<?= htmlspecialchars($foundUser['username']) ?>
                            </div>
                        <?php else: ?>
                            <div class="search-username">
                                ID: <?= htmlspecialchars((string)$foundUser['id']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="search-arrow">
                        <i class="bi bi-chevron-right"></i>
                    </div>
                </a>
            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</section>

<script>
    const tg = window.Telegram.WebApp;

    tg.ready();

    if (typeof tg.requestFullscreen === 'function') {
        tg.requestFullscreen();
    }

    tg.expand();
</script>

<style>
    .search-form-card {
        padding: 15px;
        margin-bottom: 12px;
        border-radius: 20px;
    }

    .search-form-card form {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .search-input-wrapper {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0 14px;
        min-height: 48px;
        border-radius: 15px;
        background: var(--bs-tertiary-bg);
    }

    .search-input-wrapper i {
        font-size: 18px;
        color: #888;
        flex-shrink: 0;
    }

    .search-input {
        width: 100%;
        min-width: 0;
        border: none;
        outline: none;
        background: transparent;
        color: inherit;
        font-size: 15px;
    }

    .search-input::placeholder {
        color: #999;
    }

    .search-button {
        width: 100%;
        border-radius: 14px;
        padding: 11px;
    }

    .user-result {
        padding: 0;
        margin-bottom: 12px;
        border-radius: 20px;
        overflow: hidden;
    }

    .user-result-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px;
        text-decoration: none;
        color: inherit;
        width: 100%;
    }

    .search-avatar {
        width: 48px;
        height: 48px;
        flex-shrink: 0;
        overflow: hidden;
        border-radius: 50%;
    }

    .search-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .search-user-info {
        flex: 1;
        min-width: 0;
    }

    .search-user-name {
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    .search-username {
        color: #888;
        font-size: 13px;
        overflow-wrap: anywhere;
        margin-top: 2px;
    }

    .search-arrow {
        color: #999;
        flex-shrink: 0;
    }

    .empty-users {
        text-align: center;
        padding: 35px 15px;
        border-radius: 20px;
        color: #888;
    }

    .empty-users i {
        display: block;
        font-size: 32px;
        margin-bottom: 10px;
    }

    .empty-users p {
        margin: 0;
    }
</style>
