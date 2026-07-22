<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>About <?= htmlspecialchars($config['site']['domain']) ?></title>
<link rel="stylesheet" href="/content/style.css">
</head>
<body class="parkbody">

<div class="park-container">

    <div class="about-section">
        <h2>About This Domain</h2>
        <p>This domain is part of the <?= htmlspecialchars($app->getSetting('business_name') ?? 'Domain Team') ?> portfolio and focuses on 
        <strong><?= htmlspecialchars($config['site']['topic']) ?></strong>. It may be available for lease or partnership opportunities.</p>
        <p><a href="/">&laquo; Back to <?= htmlspecialchars($config['site']['domain']) ?></a></p>
    </div>

    <div class="about-form">
        <h2>Contact Us</h2>

        <?php if (!empty($submitted)): ?>
            <p><strong>Thank you — your message has been received.</strong></p>
        <?php else: ?>
            <form method="POST" id="about-form">
                <input type="text" name="name" placeholder="Your Name">
                <input type="email" name="email" placeholder="Your Email" required>
                <textarea name="message" rows="5" placeholder="Your Message"></textarea>
                <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
                <?php $recaptchaKey = $app->getSetting('recaptcha_site_key'); ?>
                <?php if (!empty($recaptchaKey)): ?>
                    <script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($recaptchaKey) ?>"></script>
                <?php endif; ?>
                <button type="submit">Send Message</button>
            </form>
        <?php endif; ?>
    </div>


</div>

<script>
<?php $recaptchaKey = $app->getSetting('recaptcha_site_key'); ?>
<?php if (!empty($recaptchaKey)): ?>
document.getElementById('about-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var form = this;
    grecaptcha.ready(function() {
        grecaptcha.execute('<?= htmlspecialchars($recaptchaKey) ?>', {action: 'submit'}).then(function(token) {
            document.getElementById('g-recaptcha-response').value = token;
            form.submit();
        });
    });
});
<?php endif; ?>
</script>

</body>
</html>
