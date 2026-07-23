<?php

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$adminPath = $app->adminPath();

$referrer = $_SERVER['HTTP_REFERER'] ?? null;
$host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? null;


// ADMIN SITE
// check for admin URI path without admin session, redirect to login
$adminPrefix = $adminPath === '/' ? '/' : $adminPath . '/';
if ($isAdminDomain && ($requestPath === $adminPath || str_starts_with($requestPath, $adminPrefix))) {
    logger("Admin access requested: {$_SERVER['REQUEST_URI']} - session: " . ($app->auth->isAuthenticated() ? $app->auth->get('admin') : 'none'));
    $page = $_GET['admin'] ?? null;

    if (!$app->auth->isAuthenticated() && !$app->auth->hasUsers() && $page !== 'login') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $setupError = $app->auth->handleFirstRun(
                $_POST['username'] ?? '',
                $_POST['password'] ?? '',
                $_POST['password_confirm'] ?? ''
            );
            if ($setupError === null) {
                header("Location: " . $app->adminUrl('dashboard'));
                exit;
            }

            $app->render('admin/first-run', ['title' => 'Initial Admin Setup', 'config' => $config, 'error' => $setupError]);
            exit;
        }

        $app->render('admin/first-run', ['title' => 'Initial Admin Setup', 'config' => $config]);
        exit;
    }

    // Not authenticated — redirect to login unless it's the login or logout page post then proceed
    if (!$app->auth->isAuthenticated() && $page !== 'login' && $page !== 'logout') {
        logger("Admin access denied - redirecting to login");
        $app->render('admin/login', ['title' => 'Admin Login']);
        exit;
    }

    // what about non authenticated but page is 'login'


    // Authenticated on bare /admin — redirect to appropriate page
    if (!$page) {
        $defaultPage = $app->auth->getRole() === 'lockout' ? 'users' : 'dashboard';
        logger("Admin access granted — redirecting to {$defaultPage}");
        header("Location: " . $app->adminUrl($defaultPage));
        exit;
    }

    // Role-based page access check
    if ($page !== 'logout' && $page !== 'login' && !$app->auth->canAccessPage($page)) {
        logger("Access denied for role {$app->auth->getRole()} to page {$page}");
        http_response_code(403);
        $app->render('admin/info', [
            'title' => 'Access Denied',
            'error' => 'You do not have permission to access this page.'
        ]);
        exit;
    }

    // Handle POST actions before rendering pages
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($page !== 'login' && !$app->auth->canEdit()) {
            logger("Edit denied for viewer role on page {$page}");
            http_response_code(403);
            $app->render('admin/info', [
                'title' => 'Access Denied',
                'error' => 'You do not have permission to make changes.'
            ]);
            exit;
        }
        $app->handleAdminPost($page);        
    }

    switch ($page) {
        case 'domains':
            $app->render('admin/domains', [
                'title' => 'Domains',
                'config' => $config
            ]);
            break;

        case 'providers':
            $app->render('admin/providers', [
                'title' => 'Content Providers',
                'config' => $config
            ]);
            break;

        case 'leads':
            $app->render('admin/leads', [
                'title' => 'Lead Tracking',
                'config' => $config
            ]);
            break;

        case 'users':
            $app->render('admin/users', [
                'title' => 'User Management',
                'config' => $config
            ]);
            break;

        case 'settings':
            $app->render('admin/settings', [
                'title' => 'GA & System Settings',
                'config' => $config
            ]);
            break;

        case 'traffic':
            $app->render('admin/traffic', [
                'title' => 'Traffic Log',
                'config' => $config
            ]);
            break;

            case 'content':
            $app->render('admin/content', [
                'title' => 'Content Fetch Log',
                'config' => $config
            ]);
            break;

            case 'cache':
            $app->render('admin/cache', [
                'title' => 'Content Cache',
                'config' => $config
            ]);
            break;

            case 'logout':
            $app->auth->destroy();
            header("Location: " . $app->adminPath());
            exit;

        default:
            $app->render('admin/dashboard', [
                'title' => 'Dashboard',
                'config' => $config
            ]);
    }
    exit;
}

// SERVICE PROVIDER SITE (public facing)
if ($isAdminDomain) {
    $contactSubmitted = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_name'], $_POST['contact_email'])) {
        $name  = trim($_POST['contact_name'] ?? '');
        $email = trim($_POST['contact_email'] ?? '');
        $msg   = trim($_POST['contact_message'] ?? '');

        if ($email !== '' && $app->verifyRecaptcha()) {
            $app->saveLead($name, $email, $msg, $host);
            $contactSubmitted = true;
        }
    }

    $app->render('service/home', [
        'title' => ($app->getsetting('business_name') ?? 'Domain Team') . ' - Domain Leasing & Lead Generation',
        'config' => $config,
        'contactSubmitted' => $contactSubmitted,
    ]);
    exit;
}

// else parked domain, load up parking data

function parkPalette(string $domain): string {
    $palettes = [
        // Teal
        '[
            "--pp-bg:#f5f3f0","--pp-card:#ffffff","--pp-card-tint:#f9f8f7","--pp-card-tint-hover:#f0eeeb",
            "--pp-shadow:0 2px 8px rgba(0,0,0,0.06)","--pp-shadow-hover:0 4px 12px rgba(0,0,0,0.1)",
            "--pp-border:#e8e4df","--pp-heading:#2c2c2c","--pp-body:#4a4a4a",
            "--pp-accent:#1a7a6d","--pp-accent-hover:#14635a",
            "--pp-focus-ring:rgba(26,122,109,0.2)","--pp-back-link:#5a6570","--pp-header-dark:#1a3a35"
        ]',
        // Slate Blue
        '[
            "--pp-bg:#f3f4f7","--pp-card:#ffffff","--pp-card-tint:#f5f6f9","--pp-card-tint-hover:#eaecf1",
            "--pp-shadow:0 2px 8px rgba(0,0,0,0.06)","--pp-shadow-hover:0 4px 12px rgba(0,0,0,0.1)",
            "--pp-border:#dde1e8","--pp-heading:#1e2a3a","--pp-body:#4a5568",
            "--pp-accent:#3b6fa0","--pp-accent-hover:#2d5a87",
            "--pp-focus-ring:rgba(59,111,160,0.2)","--pp-back-link:#6b7a8d","--pp-header-dark:#1a2a40"
        ]',
        // Warm Amber
        '[
            "--pp-bg:#f8f5f0","--pp-card:#ffffff","--pp-card-tint:#faf7f2","--pp-card-tint-hover:#f0ebe2",
            "--pp-shadow:0 2px 8px rgba(0,0,0,0.06)","--pp-shadow-hover:0 4px 12px rgba(0,0,0,0.1)",
            "--pp-border:#e8e0d2","--pp-heading:#2c2418","--pp-body:#5a4e3e",
            "--pp-accent:#b8860b","--pp-accent-hover:#9a7209",
            "--pp-focus-ring:rgba(184,134,11,0.2)","--pp-back-link:#7a6e5e","--pp-header-dark:#3a2a10"
        ]',
        // Forest
        '[
            "--pp-bg:#f2f5f0","--pp-card:#ffffff","--pp-card-tint:#f5f8f3","--pp-card-tint-hover:#e8ede5",
            "--pp-shadow:0 2px 8px rgba(0,0,0,0.06)","--pp-shadow-hover:0 4px 12px rgba(0,0,0,0.1)",
            "--pp-border:#dde5d8","--pp-heading:#1a2e1a","--pp-body:#4a5e4a",
            "--pp-accent:#2d7a3a","--pp-accent-hover:#236a2e",
            "--pp-focus-ring:rgba(45,122,58,0.2)","--pp-back-link:#6a7a6a","--pp-header-dark:#1a2e15"
        ]',
        // Plum
        '[
            "--pp-bg:#f5f2f5","--pp-card:#ffffff","--pp-card-tint:#f8f5f8","--pp-card-tint-hover:#ede8ed",
            "--pp-shadow:0 2px 8px rgba(0,0,0,0.06)","--pp-shadow-hover:0 4px 12px rgba(0,0,0,0.1)",
            "--pp-border:#e2dce5","--pp-heading:#2a1e30","--pp-body:#5a4a60",
            "--pp-accent:#7a4a8a","--pp-accent-hover:#6a3a7a",
            "--pp-focus-ring:rgba(122,74,138,0.2)","--pp-back-link:#7a6a80","--pp-header-dark:#2a1535"
        ]',
    ];
    $index = hexdec(substr(md5($domain), 0, 8)) % count($palettes);
    return $palettes[$index];
}

function topicVisual(string $topic): array {
    $map = [
        'Outdoor Recreation'                => ['svg' => 'mountain',  'glyph' => "\u{26F0}\u{FE0F}",  'pattern' => 'diagonal'],
        'Travel & Tourism'                  => ['svg' => 'mountain',  'glyph' => "\u{2708}\u{FE0F}",  'pattern' => 'diagonal'],
        'Real Estate'                       => ['svg' => 'building',  'glyph' => "\u{1F3E0}",          'pattern' => 'grid'],
        'Family & Community Support'        => ['svg' => 'building',  'glyph' => "\u{1F465}",          'pattern' => 'dots'],
        'Automotive'                        => ['svg' => 'gear',      'glyph' => "\u{1F697}",          'pattern' => 'diagonal'],
        'Footwear & Gear'                   => ['svg' => 'gear',      'glyph' => "\u{1F97E}",          'pattern' => 'diagonal'],
        'Financial Information'             => ['svg' => 'chart',     'glyph' => "\u{1F4C8}",          'pattern' => 'grid'],
        'Technology'                        => ['svg' => 'code',      'glyph' => "\u{1F4BB}",          'pattern' => 'grid'],
        'Artificial Intelligence'           => ['svg' => 'code',      'glyph' => "\u{1F916}",          'pattern' => 'grid'],
        'Website Development'               => ['svg' => 'code',      'glyph' => "\u{1F5A5}\u{FE0F}", 'pattern' => 'grid'],
        'Health & Wellness'                 => ['svg' => 'heart',     'glyph' => "\u{2764}\u{FE0F}",   'pattern' => 'waves'],
        'Personal Services'                 => ['svg' => 'heart',     'glyph' => "\u{2B50}",           'pattern' => 'waves'],
        'Food & Cooking'                    => ['svg' => 'utensils',  'glyph' => "\u{1F373}",          'pattern' => 'dots'],
        'Audio Production'                  => ['svg' => 'wave',      'glyph' => "\u{1F3B5}",          'pattern' => 'waves'],
        'Local Information: Lisbon, CT'     => ['svg' => 'pin',       'glyph' => "\u{1F4CD}",          'pattern' => 'dots'],
        'Local Information: Connecticut'    => ['svg' => 'pin',       'glyph' => "\u{1F4CD}",          'pattern' => 'dots'],
        'Current Events & Humanitarian Relief' => ['svg' => 'globe', 'glyph' => "\u{1F30D}",          'pattern' => 'diagonal'],
    ];
    return $map[$topic] ?? ['svg' => 'chart', 'glyph' => "\u{1F4A1}", 'pattern' => 'dots'];
}

$parkedConfig = $app->content->getParkedDomainConfig($host);

if (empty($config['site']['topic'])) {
    if ($parkedConfig) {
        $config['site']['topic'] = $parkedConfig['category'] ?: $app->content->inferTopicFromRequest($config['site']['domain'], $referrer);
        $config['site']['subject_tags'] = $parkedConfig['subject_tags'];
    } else {
        $config['site']['topic'] = $app->content->inferTopicFromRequest($config['site']['domain'], $referrer);
    }
}

$config['site']['content_query_phrases'] = $app->content->buildContentQueryPhrases($config['site']['domain'], $referrer, $parkedConfig ?? null);


// PARKED DOMAIN ABOUT PAGE
$showAboutPage = isset($_GET['about']) || $requestPath === '/about' || $requestPath === '/about/';
if ($showAboutPage) {

    $submitted = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $msg   = trim($_POST['message'] ?? '');

        if ($email !== '' && $app->verifyRecaptcha()) {
            $app->saveLead($name, $email, $msg, $config['site']['domain']);
            $submitted = true;
        }
    }

    $app->render('park/about', [
        'title'     => $config['site']['domain'] . ' - About',
        'config'    => $config,
        'submitted' => $submitted,
        'palette'   => parkPalette($config['site']['domain']),
        'visual'    => topicVisual($config['site']['topic']),
    ]);
    exit;
}

// PARKED DOMAIN HOME PAGE
$app->render('park/home', [
    'title'   => $config['site']['domain'],
    'config'  => $config,
    'palette' => parkPalette($config['site']['domain']),
    'visual'  => topicVisual($config['site']['topic']),
]);
exit;
