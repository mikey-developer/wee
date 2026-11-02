<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


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

    die("Baza ulanishda xatolik");

}



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $data = json_decode(
        file_get_contents("php://input"),
        true
    );

    header("Content-Type: application/json; charset=utf-8");


    if (!empty($data['id'])) {

        $userId    = (int)$data['id'];
        $firstName = $data['first_name'] ?? '';
        $username  = $data['username'] ?? null;
        $photoUrl  = $data['photo_url'] ?? null;


        $sql = "
            INSERT INTO users
                (id, first_name, username, photo_url)
            VALUES
                (?, ?, ?, ?)

            ON DUPLICATE KEY UPDATE
                first_name = VALUES(first_name),
                username   = VALUES(username),
                photo_url  = VALUES(photo_url)
        ";


        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $userId,
            $firstName,
            $username,
            $photoUrl
        ]);


        $_SESSION['telegram_user_id'] = $userId;


        $stmt = $pdo->prepare("
            SELECT
                id,
                first_name,
                username,
                photo_url

            FROM users

            WHERE id = ?

            LIMIT 1
        ");

        $stmt->execute([$userId]);

        $currentUser = $stmt->fetch();


        echo json_encode([
            "ok" => true,
            "user" => $currentUser
        ]);

        exit;
    }


    echo json_encode([
        "ok" => false,
        "error" => "Telegram user ID kelmadi"
    ]);

    exit;
}


$userId = null;
$currentUser = null;


if (!empty($_SESSION['telegram_user_id'])) {

    $userId = (int)$_SESSION['telegram_user_id'];


    $stmt = $pdo->prepare("
        SELECT
            id,
            first_name,
            username,
            photo_url

        FROM users

        WHERE id = ?

        LIMIT 1
    ");

    $stmt->execute([$userId]);

    $currentUser = $stmt->fetch();
    
    if (!$currentUser) {
        $userId = null;
        unset($_SESSION['telegram_user_id']);
    }
}


$stmt = $pdo->prepare("
    SELECT
        'post' AS feed_type,
        posts.id AS feed_id,
        posts.id AS post_id,
        posts.user_id,
        posts.content,
        posts.image,
        posts.created_at,

        users.first_name,
        users.username,
        users.photo_url,

        NULL AS original_first_name,
        NULL AS original_username,
        NULL AS original_photo_url,
        NULL AS original_content,
        NULL AS original_image,

        (SELECT COUNT(*)
         FROM likes
         WHERE likes.post_id = posts.id) AS like_count,

        (SELECT COUNT(*)
         FROM comments
         WHERE comments.post_id = posts.id) AS comment_count,

        (SELECT COUNT(*)
         FROM reposts
         WHERE reposts.post_id = posts.id) AS repost_count

    FROM posts

    INNER JOIN users
        ON users.id = posts.user_id

    WHERE posts.user_id IN (
        SELECT following_id
        FROM follows
        WHERE follower_id = :user_id
    )

    ORDER BY posts.id DESC
");

$stmt->execute([
    'user_id' => $userId
]);

$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT
        'repost' AS feed_type,

        reposts.id AS feed_id,
        reposts.post_id AS post_id,

        reposts.user_id,
        reposts.content,
        NULL AS image,
        reposts.created_at,

        repost_user.first_name,
        repost_user.username,
        repost_user.photo_url,

        original_user.id AS original_user_id,
        original_user.first_name AS original_first_name,
        original_user.username AS original_username,
        original_user.photo_url AS original_photo_url,

        original_post.content AS original_content,
        original_post.image AS original_image,

        (
            SELECT COUNT(*)
            FROM likes
            WHERE likes.post_id = reposts.post_id
        ) AS like_count,

        (
            SELECT COUNT(*)
            FROM comments
            WHERE comments.post_id = reposts.post_id
        ) AS comment_count,

        (
            SELECT COUNT(*)
            FROM reposts AS r
            WHERE r.post_id = reposts.post_id
        ) AS repost_count

    FROM reposts

    INNER JOIN posts AS original_post
        ON original_post.id = reposts.post_id

    INNER JOIN users AS repost_user
        ON repost_user.id = reposts.user_id

    INNER JOIN users AS original_user
        ON original_user.id = original_post.user_id

    WHERE reposts.user_id IN (
        SELECT following_id
        FROM follows
        WHERE follower_id = :user_id
    )

    ORDER BY reposts.created_at DESC
");

$stmt->execute([
    'user_id' => $userId
]);

$reposts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$feed = array_merge($posts, $reposts);


$uniqueFeed = [];
$seen = [];

foreach ($feed as $item) {

    $key = $item['feed_type'] . '_' . $item['feed_id'];

    if (isset($seen[$key])) {
        continue;
    }

    $seen[$key] = true;

    $uniqueFeed[] = $item;
}


$posts = $uniqueFeed;


usort(
    $posts,
    function ($a, $b) {

        return strtotime($b['created_at'])
             <=> strtotime($a['created_at']);

    }
);
// 1. Feed ichidagi asl post ID larini olish
$postIds = [];

foreach ($posts as $post) {
    $postIds[] = (int) ($post['post_id'] ?? 0);
}

$postIds = array_values(array_unique(array_filter($postIds)));

// Har bir feed elementiga bo'sh kommentlar biriktirish
foreach ($posts as $key => $post) {
    $posts[$key]['comments'] = [];
}

if (!empty($postIds)) {

    $placeholders = implode(',', array_fill(0, count($postIds), '?'));

    $stmtComments = $pdo->prepare("
        SELECT
            c.id,
            c.post_id,
            c.user_id,
            c.content,
            c.created_at,
            c.reply_id,
            c.replied_user_id,
            c.replied_content,
            c.replied_at,

            u.first_name,
            u.username,
            u.photo_url,

            ru.first_name AS replied_first_name,
            ru.username AS replied_username,
            ru.photo_url AS replied_photo_url

        FROM comments c

        LEFT JOIN users u
            ON u.id = c.user_id

        LEFT JOIN users ru
            ON ru.id = c.replied_user_id

        WHERE c.post_id IN ($placeholders)

        ORDER BY c.id ASC
    ");

    $stmtComments->execute($postIds);
    $allComments = $stmtComments->fetchAll(PDO::FETCH_ASSOC);

    // Asosiy kommentlar va reply'larni ajratish
    $commentsGrouped = [];
    $repliesGrouped = [];

    foreach ($allComments as $comment) {

        if ($comment['reply_id'] === null) {

            // Asosiy komment
            $postId = (int) $comment['post_id'];
            $comment['replies'] = [];

            $commentsGrouped[$postId][(int) $comment['id']] = $comment;

        } else {

            // Reply'ni asosiy komment ID si bo'yicha guruhlash
            $parentId = (int) $comment['reply_id'];

            $repliesGrouped[$parentId][] = $comment;
        }
    }

    // Reply'larni tegishli asosiy kommentga biriktirish
    foreach ($commentsGrouped as $postId => &$comments) {

        foreach ($comments as $commentId => &$comment) {
            $comment['replies'] = $repliesGrouped[$commentId] ?? [];
        }

        unset($comment);
    }

    unset($comments);

    // Postlarga kommentlarni biriktirish
    foreach ($posts as $key => $post) {

        $postId = (int) ($post['post_id'] ?? 0);

        $posts[$key]['comments'] = array_values(
            $commentsGrouped[$postId] ?? []
        );
    }
}
?>


<!DOCTYPE html>

<html lang="uz">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>WritterG2</title>


    <!-- BOOTSTRAP ICONS -->

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
    >


    <!-- BOOTSTRAP -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@6.0.0-alpha.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-B/GM4XqrwHnWXNOWMbloTmrYXZg10cakYGmpfsR/bbzQ6JAJI4ihuyADKLnBgrCe" crossorigin="anonymous">
    <script type="module" src="https://cdn.jsdelivr.net/npm/bootstrap@6.0.0-alpha.1/dist/js/bootstrap.bundle.min.js" integrity="sha384-1a/pXj49ZQ1aHEmrJ+gMw1otqoVsYwlEnlD8mIfY2TV03r20Y0CN7uqx1tQogjPL" crossorigin="anonymous"></script>
    
    <!-- FONTAWESOME -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css"
        rel="stylesheet"
    >
    
    <!-- GOOGLE FONT POPPINS -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">

    <!-- TELEGRAM -->

    <script src="https://telegram.org/js/telegram-web-app.js"></script>


    <style>

        body {
            background: linear-gradient(
                0deg,
                #F5F5F5 95%,
                #0000004A 100%
            );
            background-repeat: no-repeat;
            background-size: 100% 100vh;
            background-color: #F5F5F5;
        }


        .section-nav {
            position: sticky;
            top: 0;
            padding: 15px;
 
        }


        .nav {
            /* position: sticky;
            top: 0; */
            width: 190px;
            margin: auto;
            background: white;
            border-radius: 30px;
            padding: 0 5px;
            display: flex;
            margin-top: 25px;
            align-items: center;
        }


        .ppic {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            background: #ddd;
        }


        .p-ptext {
            margin-left: 3px;
            flex: 1;
            font-family: 'Poppins', Sans-Serif;
        }


        .p-ptext strong {
            display: block;
            font-size: 12px;
            font-family: 'Poppins', Sans-Serif;
        }


        .p-ptext small {
            color: #888;
            font-family: 'Poppins', Sans-Serif;
            font-size: 11px;
        }


        .pplus {
            width: 30px;
            height: 30px;

            border: none;

            border-radius: 50%;

            background: #000;

            color: white;

            font-size: 20px;
        }


        .section-posts {
            padding: 10px 15px;
        }


        .content {
            width: 350px;
            max-width: 100%;

            margin: 0 auto 15px;

            background: white;

            border-radius: 25px;

            padding: 15px;
        }


        .post-header {
            display: flex;
            align-items: center;
        }


        .user-profile {
            width: 42px;
            height: 42px;

            border-radius: 50%;

            object-fit: cover;

            background: #ddd;

            flex-shrink: 0;
        }


        .user-info {
            margin-left: 10px;
        }


        .user-info strong {
            display: block;
        }


        .user-info small {
            color: #888;
        }


        .post-content {
            margin-top: 15px;
            font-family: 'Poppins', Sans-Serif;
            color: #424242;
        }


        .post-image {
            width: 100%;

            border-radius: 18px;

            margin-top: 10px;

            display: block;
        }


        .post-items {
            width: 100%;

            margin-top: 12px;

            display: flex;

            gap: 15px;

            justify-content: space-between;
        }


        .post-btn {
            border: none;

            background: none;

            padding: 0;

            color: #555;
        }



        .repost-label {
            color: #777;

            font-size: 13px;

            margin-bottom: 8px;
        }


        .quote-post {
            margin-top: 12px;

            border: 1px solid #ddd;

            border-radius: 18px;

            padding: 12px;
    
            background: #fafafa;
        }


        .quote-post .post-content {
            margin-top: 10px;
        }


        .quote-post .post-image {
            margin-top: 10px;
        }


    </style>

</head>


<body>




<section class="section-nav">

    <div class="nav">


        <?php if (!empty($currentUser['photo_url'])): ?>

            <img
                class="ppic"
                src="<?= htmlspecialchars($currentUser['photo_url']) ?>"
                data-bs-toggle="dialog"
                data-bs-target="#exampleDialog"
            >

        <?php else: ?>

            <div class="ppic"></div>

        <?php endif; ?>


        <div class="p-ptext">

            <a
                href="profile.php"
                class="text-decoration-none text-dark"
            >

                <strong>
                    <?= htmlspecialchars(
                        $currentUser['first_name'] ?? 'Гость'
                    ) ?>
                </strong>


                <?php if (!empty($currentUser['username'])): ?>

                    <small>
                        @<?= htmlspecialchars(
                            $currentUser['username']
                        ) ?>
                    </small>

                <?php else: ?>

                    <small>
                        Telegram user
                    </small>

                <?php endif; ?>

            </a>

        </div>


        <button
            class="pplus"
            data-bs-toggle="modal"
            data-bs-target="#postModal"
        >
            +
        </button>

    </div>

</section>




<section class="section-posts">


<?php foreach ($posts as $post): ?>


    <div id="<?= $post['id']; ?>" class="content">




        <?php if ($post['feed_type'] === 'repost'): ?>

            <div class="repost-label">

                <i class="bi bi-repeat"></i>

                <strong>
                    <?= htmlspecialchars(
                        $post['first_name']
                    ) ?>
                </strong>

                репостнул

            </div>

        <?php endif; ?>




        <div class="post-header">



            <?php if (!empty($post['photo_url'])): ?>

                <img
                    class="user-profile"
                    src="<?= htmlspecialchars(
                        $post['photo_url']
                    ) ?>"
                >

            <?php else: ?>

                <div class="user-profile"></div>

            <?php endif; ?>


            <div class="user-info">


                <a
                    href="profile.php?id=<?= $post['user_id'] ?>"
                    class="text-decoration-none text-dark"
                >


                    <?php if ($post['user_id'] == 8206296286): ?>

                        <strong>

                            <?= htmlspecialchars(
                                $post['first_name']
                            ) ?>

                            <i class="fa-solid fa-crown text-warning"></i>

                        </strong>

                    <?php else: ?>

                        <strong>
                            <?= htmlspecialchars(
                                $post['first_name']
                            ) ?>
                        </strong>

                    <?php endif; ?>


                    <?php if (!empty($post['username'])): ?>

                        <small>
                            @<?= htmlspecialchars(
                                $post['username']
                            ) ?>
                        </small>

                    <?php endif; ?>


                </a>

            </div>


 
            <?php if (
                $userId &&
                $post['user_id'] != $userId
            ): ?>


                <?php

                $stmtFollow = $pdo->prepare("
                    SELECT 1

                    FROM follows

                    WHERE follower_id = ?
                    AND following_id = ?

                    LIMIT 1
                ");

                $stmtFollow->execute([
                    $userId,
                    $post['user_id']
                ]);

                $isFollowing =
                    $stmtFollow->fetch();

                ?>


                <form
                    action="follow.php"
                    method="POST"
                    class="ms-auto"
                >

                    <input
                        type="hidden"
                        name="user_id"
                        value="<?= $post['user_id'] ?>"
                    >


                    <button
                        type="submit"
                        class="btn btn-sm
                        <?= $isFollowing
                            ? 'btn-secondary'
                            : 'btn-primary'
                        ?>"
                    >

                        <?= $isFollowing
                            ? 'Читаю'
                            : 'Читать'
                        ?>

                    </button>

                </form>


            <?php endif; ?>


        </div>



        <?php if ($post['feed_type'] === 'repost'): ?>


            <!-- USER'S QUOTE -->

            <?php if (!empty($post['content'])): ?>

                <div class="post-content">

                    <?= nl2br(
                        htmlspecialchars(
                            $post['content']
                        )
                    ) ?>

                </div>

            <?php endif; ?>


            <!-- ORIGINAL POST -->

            <div class="quote-post">


                <div class="post-header">


                    <?php if (
                        !empty(
                            $post['original_photo_url']
                        )
                    ): ?>

                        <img
                            class="user-profile"
                            src="<?= htmlspecialchars(
                                $post['original_photo_url']
                            ) ?>"
                        >

                    <?php else: ?>

                        <div class="user-profile"></div>

                    <?php endif; ?>


                    <div class="user-info">


                        <a
                            href="profile.php?id=<?= $post['original_user_id'] ?? 0 ?>"
                            class="text-decoration-none text-dark"
                        >

                            <strong>

                                <?= htmlspecialchars(
                                    $post['original_first_name']
                                ) ?>

                            </strong>


                            <?php if (
                                !empty(
                                    $post['original_username']
                                )
                            ): ?>

                                <small>

                                    @<?= htmlspecialchars(
                                        $post['original_username']
                                    ) ?>

                                </small>

                            <?php endif; ?>

                        </a>


                    </div>


                </div>


                <?php if (
                    !empty(
                        $post['original_content']
                    )
                ): ?>

                    <div class="post-content">

                        <?= nl2br(
                            htmlspecialchars(
                                $post['original_content']
                            )
                        ) ?>

                    </div>

                <?php endif; ?>


                <?php if (
                    !empty(
                        $post['original_image']
                    )
                ): ?>

                    <img
                        class="post-image"
                        src="<?= htmlspecialchars(
                            $post['original_image']
                        ) ?>"
                        alt="Original post"
                    >

                <?php endif; ?>


            </div>


        <?php else: ?>




            <?php if (!empty($post['content'])): ?>

                <div class="post-content">

                    <?= nl2br(
                        htmlspecialchars(
                            $post['content']
                        )
                    ) ?>

                </div>

            <?php endif; ?>


            <?php if (!empty($post['image'])): ?>

                <img
                    class="post-image"
                    src="<?= htmlspecialchars(
                        $post['image']
                    ) ?>"
                    alt="Post image"
                >

            <?php endif; ?>

            
        <?php endif; ?>
        
        <div>
            <span style="background: #f5f5f5; color: #424242; padding: 0 3px; border-radius: 3px;font-size: 12px;">
                <?= $post['created_at'] ?>
            </span>
        </div>
        
        <div class="post-items">


            <!-- COMMENTS -->

            <button
                class="post-btn"
                type="button"

                data-bs-toggle="collapse"

                data-bs-target="#comments<?= $post['feed_id'] ?>"
            >

                <i class="bi bi-chat-fill text-primary"></i>

                <?= $post['comment_count'] ?>

            </button>


            <!-- LIKE -->

            <form
                action="like.php"
                method="POST"
            >

                <input
                    type="hidden"
                    name="post_id"
                    value="<?= $post['post_id'] ?>"
                >


                <button
                    type="submit"
                    class="post-btn"
                >

                    <i class="bi bi-heart-fill text-danger"></i>

                    <?= $post['like_count'] ?>

                </button>

            </form>


            <!-- QUOTE REPOST -->

            <button
                type="button"

                class="post-btn"

                data-bs-toggle="modal"

                data-bs-target="#repostModal<?= $post['feed_id'] ?>"
            >

                <i class="bi bi-repeat text-success"></i>

                <?= $post['repost_count'] ?>
            </button>


        </div>

    </div>
        <!-- ==================================================
             COMMENTS
        ================================================== -->
        



    <div class="collapse mt-3" id="comments<?= $post['feed_id'] ?? $post['post_id'] ?>">



<?php foreach ($post['comments'] as $comment): ?>

    <?php
    $commentId = (int) $comment['id'];

    $commentReplies = array_filter(
        $allReplies ?? [],
        fn($reply) =>
            (int) ($reply['reply_id'] ?? 0) === $commentId
            && (int) $reply['post_id'] === (int) $post['post_id']
    );
    ?>

    <div class="d-flex mb-3">

        <?php if (!empty($comment['photo_url'])): ?>
            <img
                src="<?= htmlspecialchars($comment['photo_url']) ?>"
                alt=""
                style="width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0;"
            >
        <?php else: ?>
            <div style="width:36px;height:36px;border-radius:50%;background:#ddd;flex-shrink:0;"></div>
        <?php endif; ?>

        <div class="ms-2 flex-grow-1">

            <strong>
                <?= htmlspecialchars($comment['first_name'] ?? '') ?>
            </strong>

            <?php if (!empty($comment['username'])): ?>
                <small class="text-muted">
                    @<?= htmlspecialchars($comment['username']) ?>
                </small>
            <?php endif; ?>

            <div>
                <?= nl2br(htmlspecialchars($comment['content'] ?? '')) ?>
            </div>

            
            <button type="submit" class="btn btn-sm text-primary p-0" style="margin-bottom: 16px:" data-bs-toggle="modal" data-bs-target="#commentModal">
                <i class="bi bi-reply-fill text-primary"></i> Ответить
            </button>
            <br>
            <small class="text-muted">
                <?= htmlspecialchars($comment['created_at'] ?? '') ?>
            </small>
            
            <?php if (!empty($currentUserId) && $comment['user_id'] == $currentUserId): ?>
                <form action="delete_comment.php" method="POST" class="mt-1">
                    <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                    <button type="submit" class="btn btn-sm text-danger p-0" style="margin-bottom: 16px:">
                        <i class="bi bi-trash text-danger"></i> Удалить
                    </button>
                </form> 
            <?php endif; ?>


            <?php foreach ($comment['replies'] as $reply): ?>

    <div class="d-flex mt-3 ms-4">

        <?php if (!empty($reply['photo_url'])): ?>
            <img
                src="<?= htmlspecialchars($reply['photo_url']) ?>"
                alt=""
                style="width:30px;height:30px;border-radius:50%;object-fit:cover;flex-shrink:0;"
            >
        <?php endif; ?>

        <div class="ms-2">

            <strong>
                <?= htmlspecialchars($reply['first_name'] ?? '') ?>
            </strong>

            <?php if (!empty($reply['username'])): ?>
                <small class="text-muted">
                    @<?= htmlspecialchars($reply['username']) ?>
                </small>
            <?php endif; ?>

            <div>
                <?= nl2br(htmlspecialchars($reply['content'] ?? '')) ?>
            </div>

            <small class="text-muted">
                <?= htmlspecialchars($reply['created_at'] ?? '') ?>
            </small>

        </div>
    </div>

<?php endforeach; ?>
        </div>
    </div>

<?php endforeach; ?>


    <!-- ADD COMMENT -->
    <form action="comment.php" method="POST">
        <input type="hidden" name="post_id" value="<?= $post['feed_id'] ?? $post['post_id'] ?>">
        <div class="input-group">
            <input type="text" name="content" class="form-control" placeholder="Ответить..." required>
            <button class="btn btn-dark" type="submit">Отправить</button>
        </div>
    </form>
    <br><br>

</div>

    <!-- ==================================================
         REPOST MODAL
    ================================================== -->

    <div
        class="modal fade"
        id="repostModal<?= $post['feed_id'] ?>"
        tabindex="-1"
    >

        <div
            class="modal-dialog modal-dialog-centered"
        >

            <div class="modal-content">


                <div class="modal-header">

                    <h5 class="modal-title">
                        Репостнуть
                    </h5>


                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>


                <form
                    action="repost.php"
                    method="POST"
                >


                    <div class="modal-body">


                        <textarea
                            name="content"
                            class="form-control"
                            rows="4"
                            placeholder="Репостик"
                        ></textarea>


                        <input
                            type="hidden"
                            name="post_id"
                            value="<?= $post['post_id'] ?>"
                        >


                    </div>


                    <div class="modal-footer">


                        <button
                            type="button"
                            class="btn btn-secondary"
                            data-bs-dismiss="modal"
                        >
                            Отменить
                        </button>


                        <button
                            type="submit"
                            class="btn btn-dark"
                        >

                            <i class="bi bi-repeat"></i>

                            Репост

                        </button>


                    </div>


                </form>


            </div>

        </div>

    </div>


<?php endforeach; ?>


<?php if (empty($posts)): ?>

    <div class="text-center text-muted mt-4">
        Пока что пусто :(
        <br>
        Поиск друзей по имени пользователя (username) или ID.
            
    </div>
    <button class="btn btn-primary w-100" data-bs-toggle="dialog" data-bs-target="#exampleDialog">
        <i class="bi bi-search text-light"></i>
        Поиск
    </button>
        


<?php endif; ?>


</section>


<!-- ==================================================
     NEW POST MODAL
================================================== -->

<div
    class="modal fade"
    id="postModal"
    tabindex="-1"
>

    <div
        class="modal-dialog modal-dialog-centered"
    >

        <div class="modal-content">


            <div class="modal-header">

                <h5 class="modal-title">
                    New post
                </h5>


                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>


            <form
                action="add_post.php"
                method="POST"
                enctype="multipart/form-data"
            >


                <div class="modal-body">


                    <textarea
                        name="content"
                        class="form-control"
                        rows="5"
                        placeholder="What's happening?"
                        required
                    ></textarea>


                    <input
                        type="file"
                        name="image"
                        class="form-control mt-3"
                        accept="image/jpeg,image/png,image/webp"
                    >


                </div>


                <div class="modal-footer">


                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>


                    <button
                        type="submit"
                        class="btn btn-dark"
                    >
                        Post
                    </button>


                </div>


            </form>


        </div>

    </div>

</div>


<dialog class="dialog" id="exampleDialog">
    <div class="dialog-header">
        <h1 class="dialog-title">Поиск по имени</h1>

        <button type="button"
                class="btn-close"
                data-bs-dismiss="dialog"
                aria-label="Close">
        </button>
    </div>

    <div class="dialog-body">
        <form action="getuser.php" method="POST">
            <div class="mb-3">
                <label for="userSearch" class="form-label">
                    Поиск по имени пользователья или по айди
                </label>

                <input
                    type="search"
                    class="form-control"
                    id="userSearch"
                    name="request"
                    placeholder="На пример mikey_developer или 1112223333"
                    autocomplete="off"
                    required
                >
            </div>

            <div class="dialog-footer">
                <button type="button"
                        class="btn-solid theme-secondary"
                        data-bs-dismiss="dialog">
                    Отмена
                </button>

                <button type="submit"
                        class="btn-solid theme-primary">
                    Го
                </button>
            </div>
        </form>
    </div>
</dialog>

<!-- ################################ -->
<div class="modal fade" id="commentModal" tabindex="-1">

    <div
        class="modal-dialog modal-dialog-centered"
    >

        <div class="modal-content">


            <div class="modal-header">

                <h5 class="modal-title">
                    Рил хотите ответить на каммент?
                </h5>

            </div>


            <form
                action="c-reply.php"
                method="POST"
                enctype="multipart/form-data"
            >


                <div class="modal-body">


                    <input name="content" class="form-control" placeholder="Ответить" required>
                    <input type="hidden" name="postid" value="<?= $post['post_id'] ?>">
                    <input type="hidden" name="commentid" value="<?= $comment['id'] ?>">
                    <input type="hidden" name="authorid" value="<?= $comment['user_id'] ?>">

                </div>


                <div class="modal-footer">


                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Отмена
                    </button>


                    <button
                        type="submit"
                        class="btn btn-dark"
                    >
                        Ответить
                    </button>


                </div>


            </form>


        </div>

    </div>

</div>


<!-- BOOTSTRAP JS -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>


<!-- ==================================================
     TELEGRAM JS
================================================== -->

<script>

const tg = window.Telegram.WebApp;

tg.ready();


if (
    typeof tg.requestFullscreen === 'function'
) {

    tg.requestFullscreen();

}


tg.expand();


if (tg.BackButton) {

    tg.BackButton.hide();

}


const telegramUser =
    tg.initDataUnsafe?.user;


const currentUserId =
    <?= json_encode($userId) ?>;


if (
    telegramUser &&
    (
        !currentUserId ||
        currentUserId !== telegramUser.id
    )
) {

    fetch(
        "index.php",
        {
            method: "POST",

            credentials: "include",

            headers: {
                "Content-Type":
                    "application/json"
            },

            body:
                JSON.stringify(
                    telegramUser
                )
        }
    )

    .then(
        response =>
            response.json()
    )

    .then(
        data => {

            if (data.ok) {

                window.location.reload();

            } else {

                console.error(
                    "Xatolik:",
                    data.error
                );

            }

        }
    )

    .catch(
        error => {

            console.error(
                "User update xatoligi:",
                error
            );

        }
    );

}

</script>


</body>

</html>
