<?php 
    session_start();
    
    $userId = $_SESSION['telegram_user_id'];
    
    if (!$userId) {
        die("user isn't found");
    }
    
    include("base.php");
?>

    <section class="section-nav">
        <div class="nav" style="padding: 15px">
            <div class="p-ptext">
                <a href="profile.php" class="text-decoration-none text-dark">
                    <strong>
                        <i class="bi bi-arrow-left"></i>
                        &nbsp;Настройки
                    </strong>
                    <small>
                        Несколько функций
                    </small>
                </a>
            </div>
        </div>
    </section>


<section class="section-posts">

            <div class="content notification-item">
                <div class="post-header">
                    <div class="user-info notification-info">

                        <div class="notification-text">
                            <button class="bt-no-style" data-bs-toggle="modal" data-bs-target="#postModal">
                                <i class="bi bi-telegram text-primary"></i>
                                Телеграм канал   
                            </button>
                        </div>

                    </div>
                </div>
            </div>
            <p class="description">С помощью этой функции вы можете делиться своими публикациями из WRITTER прямо в своём Telegram-канале. Только не забудьте добавить @writterappbot в администраторы канала!</p>


</section>

<div class="modal fade" id="postModal" tabindex="-1">

    <div
        class="modal-dialog modal-dialog-centered"
    >

        <div class="modal-content">


            <div class="modal-header">

                <h5 class="modal-title">
                    Телеграм Канал
                </h5>

            </div>


            <form
                action="s-telegram.php"
                method="POST"
                enctype="multipart/form-data"
            >


                <div class="modal-body">


                    <input
                        name="id"
                        class="form-control"
                        rows="5"
                        placeholder="Телеграм канал айди"
                    >

                </div>


                <div class="modal-footer">


                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Назад
                    </button>


                    <button
                        type="submit"
                        class="btn btn-dark"
                    >
                        Готово
                    </button>


                </div>


            </form>


        </div>

    </div>

</div>

<script>
    const tg = window.Telegram.WebApp;

    tg.ready();
    
    
    if (
        typeof tg.requestFullscreen === 'function'
    ) {
    
        tg.requestFullscreen();
    
    }
    
    
    tg.expand();
</script>
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>
<style>
    .bt-no-style {
        background: none;
        border: none;
    }
    .description {
        font-size: 12px;
        padding: 0 20px;
    }
    .notification-item {
        padding: 15px;
        margin-bottom: 12px;
        border-radius: 20px;
    }
    
    .notification-icon {
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f0f2f5;
        color: #2481cc;
        font-size: 20px;
        flex-shrink: 0;
    }
    
    .notification-info {

        min-width: 0;
    }
    
    .notification-text {
        /* display: flex; */
    }
    
    .empty-notifications {
        text-align: center;
        padding: 35px 15px;
        color: #888;
    }
    
    .empty-notifications i {
        font-size: 32px;
        display: block;
        margin-bottom: 10px;
    }
    
    .empty-notifications p {
        margin: 0;
    }
</style>





    

    