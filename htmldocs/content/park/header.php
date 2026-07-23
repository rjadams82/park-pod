<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= $title ?></title>
        <link rel="stylesheet" href="/content/park/style.css">
        <?php if (!empty($palette)): ?>
        <style id="park-palette">:root { <?= implode('; ', json_decode($palette)) ?>; }</style>
        <?php endif; ?>
        <?php if (!empty($visual)): ?>
        <?php
            $patterns = [
                'diagonal' => "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20'%3E%3Cpath d='M0 20L20 0' stroke='var(--pp-accent)' stroke-width='.5' fill='none'/%3E%3C/svg%3E",
                'dots'     => "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20'%3E%3Ccircle cx='10' cy='10' r='1.5' fill='var(--pp-accent)'/%3E%3C/svg%3E",
                'waves'    => "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='40' height='10'%3E%3Cpath d='M0 5 Q10 0 20 5 Q30 10 40 5' stroke='var(--pp-accent)' stroke-width='.5' fill='none'/%3E%3C/svg%3E",
                'grid'     => "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20'%3E%3Cpath d='M20 0L0 0 0 20' stroke='var(--pp-accent)' stroke-width='.5' fill='none'/%3E%3C/svg%3E",
            ];
            $patternUrl = $patterns[$visual['pattern']] ?? $patterns['dots'];
        ?>
        <style id="park-pattern">.park-section[data-pattern]::before { background-image: url('<?= $patternUrl ?>'); }</style>
        <?php endif; ?>

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
