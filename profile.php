<?php

session_start();

$host = "localhost";
$db   = "db";
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

$currentUserId = $_SESSION['telegram_user_id'] ?? null;

if (!$currentUserId) {
    die("User topilmadi");
}


/*
|--------------------------------------------------------------------------
| Qaysi profil?
|--------------------------------------------------------------------------
*/

$profileId = $_GET['id'] ?? $currentUserId;

if (!is_numeric($profileId)) {
    die("Noto'g'ri profile");
}


/*
|--------------------------------------------------------------------------
| Profile
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        first_name,
        username,
        photo_url
    FROM users
    WHERE id = ?
");

$stmt->execute([$profileId]);

$profile = $stmt->fetch();

if (!$profile) {
    die("Profile topilmadi");
}


/*
|--------------------------------------------------------------------------
| Followers
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM follows
    WHERE following_id = ?
");

$stmt->execute([$profileId]);

$followers = $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Following
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM follows
    WHERE follower_id = ?
");

$stmt->execute([$profileId]);

$following = $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Posts count
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM posts
    WHERE user_id = ?
");

$stmt->execute([$profileId]);

$postCount = $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Current user follow qilganmi?
|--------------------------------------------------------------------------
*/

$isFollowing = false;

if ($currentUserId != $profileId) {

    $stmt = $pdo->prepare("
        SELECT 1
        FROM follows
        WHERE follower_id = ?
          AND following_id = ?
    ");

    $stmt->execute([
        $currentUserId,
        $profileId
    ]);

    $isFollowing = (bool) $stmt->fetch();
}


/*
|--------------------------------------------------------------------------
| Profile posts
|--------------------------------------------------------------------------
*/


$stmt = $pdo->prepare("
    SELECT
        posts.id AS id,
        posts.id AS feed_id,
        posts.id AS post_id,
        'post' AS feed_type,
        posts.user_id,
        posts.content,
        posts.image,
        posts.created_at,
        users.first_name,
        users.username,
        users.photo_url,

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
    INNER JOIN users ON users.id = posts.user_id
    WHERE posts.user_id = ?
    ORDER BY posts.id DESC
");

$stmt->execute([$profileId]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);


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

    <title>
        <?= htmlspecialchars($profile['first_name']) ?>
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.2/font/bootstrap-icons.min.css">

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

        a { text-decoration: none; color: #424242; }

        .profile {
            width: 350px;
            max-width: 100%;
            margin: 20px auto;
        }

        .profile-card {
            background: none;
            border-radius: 25px;
            padding: 25px;
            /* margin-top: 50px; */
            text-align: center;
        }

        .profile-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            background: #ddd;
        }
        
       .profile-photo:hover {
           width: 150px;
           height: auto;
           border-radius: 5px;
           transition-duration: 0.4s;
       }

        .stats {
            display: flex;
            justify-content: space-around;
            margin-top: 25px;
        }

        .stat strong {
            display: block;
            font-size: 20px;
        }

        .stat span {
            color: #888;
            font-size: 13px;
        }

        .post {
            background: white;
            border-radius: 20px;
            padding: 15px;
            margin-top: 10px;
        }

        .post-image {
            width: 100%;
            border-radius: 15px;
            margin-top: 10px;
        }
        
        .config {
            width: 100%;
            display: flex;
            text-align: center;
            margin-top: 15px;
            justify-content: space-between;
            padding: 0 5px;
            gap: 5px;
        }
        
        .settings {
            width: auto;
            height: 40px;
            border: none;
            text-align: center;
            align-items: center;
            margin: auto;
            display: flex;
            padding: 25px;
            background: #FFF;
            border-radius: 10px;
        }
        
        .pinned {
            width: auto;
            height: 40px;
            border: none;
            align-items: center;
            margin: auto;
            display: flex;
            padding: 25px;
            background: #FFF;
            border-radius: 10px;
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



        .content {
            width: 100%;
            /* margin: 0 auto 15px; */
            background: white;
            border-radius: 25px;
            padding: 5px 20px;
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</head>

<body>

<div class="profile">


    <!-- PROFILE -->

    <div class="profile-card">

        <?php if (!empty($profile['photo_url'])): ?>

            <img
                class="profile-photo"
                src="<?= htmlspecialchars($profile['photo_url']) ?>"
            >

        <?php else: ?>

            <div class="profile-photo mx-auto"></div>

        <?php endif; ?>


        <h4 class="mt-3 mb-0">

            <?= htmlspecialchars($profile['first_name']) ?>

        </h4>


        <?php if (!empty($profile['username'])): ?>

            <div class="text-muted">

                @<?= htmlspecialchars($profile['username']) ?>

            </div>

        <?php endif; ?>


        <!-- FOLLOW -->

        <?php if ($currentUserId != $profileId): ?>

            <form
                action="follow.php"
                method="POST"
                class="mt-3"
            >

                <input
                    type="hidden"
                    name="user_id"
                    value="<?= $profileId ?>"
                >

                <button
                    type="submit"
                    class="btn <?= $isFollowing
                        ? 'btn-secondary'
                        : 'btn-dark' ?>"
                >

                    <?= $isFollowing
                        ? 'Читаю'
                        : 'Читать' ?>

                </button>

            </form>

        <?php endif; ?>


        <!-- STATS -->

        <div class="stats">

            <div class="stat">

                <strong>
                    <?= $postCount ?>
                </strong>

                <span>
                    Посты
                </span>

            </div>


            <div class="stat">

                <strong>
                    <?= $followers ?>
                </strong>

                <span>
                    Читатели 
                </span>

            </div>


            <div class="stat">

                <strong>
                    <?= $following ?>
                </strong>

                <span>
                    Читает
                </span>

            </div>

        </div>
        <?php if ($profileId == $_SESSION['telegram_user_id']): ?>
        <div class="config">
            <a href="settings.php">
                <div class="settings">
                    <i class="bi bi-gear-fill" style="font-size: 25px;"></i>
                    &nbsp;<span>Настройки</span>
                </div>
            </a>
            <div class="pinned">
                <i class="bi bi-pin-fill" style="font-size: 25px;"></i>
                &nbsp;<span>Пины</span>
            </div>
        </div>
        <?php endif; ?>

    </div>


    <!-- POSTS -->

    <section class="section-posts">


<?php foreach ($posts as $post): ?>


    <div id="<?= $post['id']; ?>" class="content mt-2">




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
            
           <?php if ($profile['id'] == $currentUserId): ?>
                <button class="post-btn">
                    <a href="p-delete.php?id=<?= $post['id']; ?>">
                      <i class="bi bi-trash text-danger"></i>
                    </a>
                </button>        
               
            <?php endif; ?>


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
            Пока что нечего нету :(
        </div>

    <?php endif; ?>


</div>

<div class="modal fade" id="postModal" tabindex="-1">

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
<script src="https://telegram.org/js/telegram-web-app.js"></script>

<script>
    const tg = window.Telegram.WebApp;
    
    tg.ready();

     if (
        typeof tg.requestFullscreen === 'function'
    ) {
    
        tg.requestFullscreen();
    
    }
    tg.expand();
    
    tg.BackButton.show();
    
    tg.BackButton.onClick(function () {
        history.back();
    });
</script>
</body>

</html>
