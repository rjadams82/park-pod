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
            $app->saveLead($name, $email, $msg);
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
            $app->saveLead($name, $email, $msg);
            $submitted = true;
        }
    }

    $app->render('park/about', [
        'title'     => $config['site']['domain'] . ' - About',
        'config'    => $config,
        'submitted' => $submitted
    ]);
    exit;
}

// PARKED DOMAIN HOME PAGE
$app->render('park/home', [
    'title'  => $config['site']['domain'],
    'config' => $config
]);
exit;
