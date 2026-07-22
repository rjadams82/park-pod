<?php
    $back_url = isset($_SERVER['HTTP_REFERER']) ? htmlspecialchars($_SERVER['HTTP_REFERER']) : ''; 
?>

<div class="login-wrapper">
    <?php if (!empty($info)): ?>
        <h1><?= htmlspecialchars($info) ?></h1>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="login-error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>


    <p style="margin-top:16px; text-align:center;">
        <a href="<?php echo $back_url; ?>">< Back</a>
    </p>

    <p style="margin-top:16px; text-align:center;">
        <a href="<?= $app->adminUrl('') ?>" style="color:#ffcc00;">Admin</a>
    </p>

    <p style="margin-top:16px; text-align:center;">
        <a href="/" style="color:#ffcc00;">Home</a>
    </p>
</div>
