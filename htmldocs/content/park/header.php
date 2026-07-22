<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $title ?></title>
        <link rel="stylesheet" href="/content/park/style.css">

        <?php
        $gaId = $app->getSetting('ga_id') ?? $config['site']['ga_id'] ?? '';
        ?>

        <?php if (!empty($gaId)): ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($gaId) ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '<?= htmlspecialchars($gaId) ?>');
        </script>
        <?php endif; ?>
    </head>
    <body class="parkbody">
