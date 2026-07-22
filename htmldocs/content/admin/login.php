<?php
// require __DIR__ . '/../shared/components.php';
?>

        <div class="login-wrapper">
            <h1><?= $config['admin']['title'] ?? 'ParkPod' ?> Login</h1>

            <?php if (!empty($error)): ?>
                <div class="login-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= $app->adminUrl('login') ?>">
                <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">

                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>

                <button type="submit">Login</button>
            </form>
        </div>
