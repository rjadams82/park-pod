<?php

class App {
    public array $config = [];
    public PDO $db;
    public string $rootPath;
    public Auth $auth;
    public ?Content $content = null;

    public function __construct(?string $rootPath = null) {
        session_start();

        $this->rootPath = $this->resolveRootPath($rootPath);
        $this->loadConfig();
        $this->initDb();
        $this->auth = new Auth($this->db, $this->config);
    }

    protected function resolveRootPath(?string $rootPath): string
    {
        if ($rootPath === null || $rootPath === '') {
            $rootPath = dirname(__DIR__);
        }

        $rootPath = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $rootPath);
        return rtrim($rootPath, DIRECTORY_SEPARATOR);
    }

    protected function path(string $relativePath): string
    {
        return $this->rootPath . DIRECTORY_SEPARATOR . ltrim($relativePath, '/\\');
    }

    public function adminPath(): string
    {
        $path = $this->config['site']['admin_path'] ?? '/admin';
        return '/' . trim($path, '/');
    }

    public function adminUrl(string $page = ''): string
    {
        $path = $this->adminPath();

        if ($page === '') {
            return $path;
        }

        return $path . '?admin=' . urlencode($page);
    }

    /* ---------------------------------------------------------
       CONFIG LOADING
    --------------------------------------------------------- */
    protected function loadConfig(): void
    {        
        $prod = $this->path('_config/config.prod.php');

        if (file_exists($prod)) {
            $this->config = require $prod;
            return;
        }

        $this->config = require $this->path('_config/config.php');
    }

    /* ---------------------------------------------------------
       DATABASE INIT
    --------------------------------------------------------- */
    protected function initDb(): void
    {
        $dbFile = $this->path($this->config['database']['path']) ?? $this->path('db/db.sqlite');
        $schemaFile = $this->path('db/schema.sql');

        $dbDir = dirname($dbFile);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }

        $this->db = new PDO('sqlite:' . $dbFile);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if (file_exists($schemaFile)) {
            $schema = file_get_contents($schemaFile);
            $this->db->exec($schema);
        }

        // Migrations for existing databases
        $cols = $this->db->query("PRAGMA table_info(leads)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if (!in_array('archived', $cols, true)) {
            $this->db->exec("ALTER TABLE leads ADD COLUMN archived INTEGER DEFAULT 0");
        }

    }

    /* ---------------------------------------------------------
       TEMPLATE RENDERING
    --------------------------------------------------------- */
    public function render(string $template, array $vars = [])
    {
        $parts = explode('/', $template);
        $site = $parts[0];

        // Inject $app into all templates
        $vars['app'] = $this;

        extract($vars);

        require $this->path("htmldocs/content/{$site}/header.php");
        require $this->path("htmldocs/content/{$template}.php");
        require $this->path("htmldocs/content/{$site}/footer.php");
    }

    /* ---------------------------------------------------------
       SETTINGS
    --------------------------------------------------------- */
    public function getSetting(string $key): ?string
    {
        $stmt = $this->db->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['value'] ?? null;
    }

    public function setSetting(string $key, string $value): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO settings (key, value)
            VALUES (?, ?)
            ON CONFLICT(key) DO UPDATE SET value = excluded.value
        ");
        $stmt->execute([$key, $value]);
    }

    /* ---------------------------------------------------------
       LEADS
    --------------------------------------------------------- */
    public function saveLead(string $name, string $email, string $message, string $domain = ''): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO leads (name, email, message, domain, created_at)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$name, $email, $message, $domain, time()]);
    }

    public function verifyRecaptcha(): bool
    {
        $secretKey = $this->getSetting('recaptcha_secret_key');
        if (empty($secretKey)) {
            return true;
        }

        $response = $_POST['g-recaptcha-response'] ?? '';
        if (empty($response)) {
            return false;
        }

        $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'secret'   => $secretKey,
                'response' => $response,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
            ]),
            CURLOPT_TIMEOUT => 10,
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        if ($body === false) {
            return false;
        }

        $json = json_decode($body, true);
        return !empty($json['success']) && ($json['score'] ?? 0) >= 0.5;
    }

    /* ---------------------------------------------------------
       ACCESS LOGS
    --------------------------------------------------------- */
    public function recordAccess(string $host, string $path, ?string $referrer = null): void
    {
        $parts = explode('.', $host);
        $domain = implode('.', array_slice($parts, -2));

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        // check for bots before logging
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (is_bot($ua)) {
            return; // skip logging
        }

        $stmt = $this->db->prepare("
            INSERT INTO access_logs (host, domain, path, referrer, user_ip, created_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$host, $domain, $path, $referrer, $ip, time()]);
    }

    public function getRecentAccessHosts(int $limit = 20): array
    {
        $stmt = $this->db->prepare("
            SELECT domain, MAX(created_at) AS last_access, COUNT(*) AS hits
            FROM access_logs
            GROUP BY domain
            ORDER BY last_access DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $hosts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($hosts as &$row) {
            $check = $this->db->prepare("SELECT id FROM parked_domains WHERE host = ? LIMIT 1");
            $check->execute([$row['domain']]);
            $row['is_parked'] = $check->fetch() !== false;
        }

        return $hosts;
    }

    /* ---------------------------------------------------------
       ADMIN POST HANDLER
    --------------------------------------------------------- */
    public function handleAdminPost(string $page): void
    {
        if (!$this->auth->validateCsrf()) {
            logger("CSRF validation failed for page {$page}", 'WARNING');
            $this->render('admin/info', [
                'title' => 'Request Failed',
                'error' => 'Request failed; invalid token. Please reach out to system administrator.'
            ]);
            exit;
        }

        switch ($page) {
            case 'providers':
                $this->content->handleProviderPost($_POST);
                break;

            case 'users':
                if (!$this->auth->canManageUsers()) {
                    $this->render('admin/info', [
                        'title' => 'Access Denied',
                        'error' => 'You do not have permission to manage users.'
                    ]);
                    exit;
                }
                $this->auth->handleUserPost($_POST);
                break;

            case 'domains':
                $this->handleDomainsPost();
                break;

            case 'settings':
                if (!$this->auth->canManageSettings()) {
                    $this->render('admin/info', [
                        'title' => 'Access Denied',
                        'error' => 'You do not have permission to manage settings.'
                    ]);
                    exit;
                }
                $this->handleSettingsPost();
                break;

            case 'leads':
                $this->handleLeadPost();
                break;

            case 'traffic':
                $this->handleTrafficPost();
                break;

            case 'content':
                $this->handleContentPost();
                break;

            case 'login':
                $this->handleLoginPost();
                break;
        }
    }

    /* ---------------------------------------------------------
       DOMAINS
    --------------------------------------------------------- */
    protected function handleDomainsPost(): void
    {
        if (isset($_POST['edit_parked_domain_id'])) {
            $id = intval($_POST['edit_parked_domain_id']);
            $category = trim($_POST['parked_domain_category'] ?? '');
            $subjectTags = trim($_POST['parked_domain_subject_tags'] ?? '');
            $stmt = $this->db->prepare("UPDATE parked_domains SET category = ?, subject_tags = ? WHERE id = ?");
            $stmt->execute([$category, $subjectTags, $id]);
            return;
        }

        if (isset($_POST['parked_domain_host'])) {
            $host = strtolower(trim($_POST['parked_domain_host'] ?? ''));
            $category = trim($_POST['parked_domain_category'] ?? '');
            $subjectTags = trim($_POST['parked_domain_subject_tags'] ?? '');

            if ($host !== '') {
                $stmt = $this->db->prepare(
                    "INSERT INTO parked_domains (host, category, subject_tags, enabled) VALUES (?, ?, ?, 1) " .
                    "ON CONFLICT(host) DO UPDATE SET category = excluded.category, subject_tags = excluded.subject_tags, enabled = 1"
                );
                $stmt->execute([$host, $category, $subjectTags]);
            }
        }

        if (isset($_POST['delete_parked_domain_id'])) {
            $stmt = $this->db->prepare("DELETE FROM parked_domains WHERE id = ?");
            $stmt->execute([intval($_POST['delete_parked_domain_id'])]);
        }

        if (isset($_FILES['parked_domain_csv']) && is_uploaded_file($_FILES['parked_domain_csv']['tmp_name'])) {
            $handle = fopen($_FILES['parked_domain_csv']['tmp_name'], 'r');
            if ($handle !== false) {
                $rowNum = 0;
                while (($row = fgetcsv($handle)) !== false) {
                    $rowNum++;
                    if ($rowNum === 1) {
                        continue;
                    }

                    $host = strtolower(trim($row[0] ?? ''));
                    $category = trim($row[1] ?? '');
                    $subjectTags = trim($row[2] ?? '');

                    if ($host === '') {
                        continue;
                    }

                    $stmt = $this->db->prepare(
                        "INSERT INTO parked_domains (host, category, subject_tags, enabled) VALUES (?, ?, ?, 1) " .
                        "ON CONFLICT(host) DO UPDATE SET category = excluded.category, subject_tags = excluded.subject_tags, enabled = 1"
                    );
                    $stmt->execute([$host, $category, $subjectTags]);
                }
                fclose($handle);
            }
        }

        if (isset($_POST['export_parked_domains'])) {
            $stmt = $this->db->query("SELECT host, category, subject_tags FROM parked_domains WHERE enabled = 1 ORDER BY host ASC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="parked-domains.csv"');

            $out = fopen('php://output', 'w');
            fputcsv($out, ['host', 'category', 'subject_tags']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['host'], $row['category'], $row['subject_tags']]);
            }
            fclose($out);
            exit;
        }
    }

    /* ---------------------------------------------------------
       TRAFFIC LOG
    --------------------------------------------------------- */
    protected function handleTrafficPost(): void
    {
        $where = [];
        $params = [];

        if (isset($_POST['clear_traffic_log'])) {
            $domain = trim($_POST['clear_domain'] ?? '');
            $host = trim($_POST['clear_host'] ?? '');
            $dateFrom = trim($_POST['clear_date_from'] ?? '');
            $dateTo = trim($_POST['clear_date_to'] ?? '');

            if ($domain !== '') {
                $where[] = 'domain = ?';
                $params[] = $domain;
            }
            if ($host !== '') {
                $where[] = 'host LIKE ?';
                $params[] = '%' . $host . '%';
            }
            if ($dateFrom !== '') {
                $where[] = 'created_at >= ?';
                $params[] = (int) strtotime($dateFrom);
            }
            if ($dateTo !== '') {
                $where[] = 'created_at <= ?';
                $params[] = (int) strtotime($dateTo . ' 23:59:59');
            }

            $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
            $this->db->prepare("DELETE FROM access_logs {$whereClause}")->execute($params);

            header('Location: ' . $this->adminUrl('traffic'));
            exit;
        }

        if (isset($_POST['export_traffic_log'])) {
            $domain = trim($_POST['export_domain'] ?? '');
            $host = trim($_POST['export_host'] ?? '');
            $dateFrom = trim($_POST['export_date_from'] ?? '');
            $dateTo = trim($_POST['export_date_to'] ?? '');

            if ($domain !== '') {
                $where[] = 'domain = ?';
                $params[] = $domain;
            }
            if ($host !== '') {
                $where[] = 'host LIKE ?';
                $params[] = '%' . $host . '%';
            }
            if ($dateFrom !== '') {
                $where[] = 'created_at >= ?';
                $params[] = (int) strtotime($dateFrom);
            }
            if ($dateTo !== '') {
                $where[] = 'created_at <= ?';
                $params[] = (int) strtotime($dateTo . ' 23:59:59');
            }

            $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
            $stmt = $this->db->prepare("SELECT * FROM access_logs {$whereClause} ORDER BY created_at DESC");
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="traffic-log.csv"');

            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'domain', 'host', 'path', 'referrer', 'user_ip']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    date('Y-m-d H:i:s', (int) $row['created_at']),
                    $row['domain'],
                    $row['host'],
                    $row['path'],
                    $row['referrer'] ?? '',
                    $row['user_ip'] ?? '',
                ]);
            }
            fclose($out);
            exit;
        }
    }

    /* ---------------------------------------------------------
       CONTENT FETCH LOG
    --------------------------------------------------------- */
    protected function handleContentPost(): void
    {
        $where = [];
        $params = [];

        if (isset($_POST['clear_fetch_log'])) {
            $providerId = (int) ($_POST['clear_provider'] ?? 0);
            $host = trim($_POST['clear_host'] ?? '');
            $status = trim($_POST['clear_status'] ?? '');

            if ($providerId > 0) {
                $where[] = 'provider_id = ?';
                $params[] = $providerId;
            }
            if ($host !== '') {
                $where[] = 'host = ?';
                $params[] = $host;
            }
            if ($status !== '') {
                $where[] = 'status = ?';
                $params[] = $status;
            }

            $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
            $this->db->prepare("DELETE FROM provider_fetch_logs {$whereClause}")->execute($params);

            header('Location: ' . $this->adminUrl('content'));
            exit;
        }

        if (isset($_POST['export_fetch_log'])) {
            $providerId = (int) ($_POST['export_provider'] ?? 0);
            $host = trim($_POST['export_host'] ?? '');
            $status = trim($_POST['export_status'] ?? '');

            if ($providerId > 0) {
                $where[] = 'provider_id = ?';
                $params[] = $providerId;
            }
            if ($host !== '') {
                $where[] = 'host = ?';
                $params[] = $host;
            }
            if ($status !== '') {
                $where[] = 'status = ?';
                $params[] = $status;
            }

            $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
            $stmt = $this->db->prepare("SELECT * FROM provider_fetch_logs {$whereClause} ORDER BY created_at DESC");
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="fetch-log.csv"');

            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'provider', 'type', 'status', 'host', 'topic', 'endpoint', 'message', 'item_count']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    date('Y-m-d H:i:s', (int) $row['created_at']),
                    $row['provider_name'],
                    $row['provider_type'],
                    $row['status'],
                    $row['host'] ?? '',
                    $row['topic'],
                    $row['endpoint'],
                    $row['message'],
                    $row['item_count'],
                ]);
            }
            fclose($out);
            exit;
        }
    }

    /* ---------------------------------------------------------
       SETTINGS (GA)
    --------------------------------------------------------- */
    protected function handleSettingsPost(): void
    {
        if (isset($_POST['backup_database'])) {
            $onlyTables = ['providers', 'settings', 'parked_domains'];

            $tables = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="parkpod-backup-' . date('Y-m-d-His') . '.sql"');
            header('Cache-Control: no-cache, must-revalidate');

            echo "-- ParkPod Database Backup\n";
            echo "-- Date: " . date('Y-m-d H:i:s') . "\n\n";

            foreach ($tables as $table) {
                if (in_array($table, $onlyTables, true)) {
                    $createRow = $this->db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name = " . $this->db->quote($table))->fetch();
                    if ($createRow) {
                        $sql = preg_replace('/^CREATE TABLE /i', 'CREATE TABLE IF NOT EXISTS ', $createRow['sql']);
                        echo $sql . ";\n\n";
                    }

                    $rows = $this->db->query("SELECT * FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $row) {
                        $cols = array_map(fn($c) => $this->db->quote($c), array_keys($row));
                        $vals = array_map(fn($v) => $v === null ? 'NULL' : $this->db->quote($v), array_values($row));
                        echo "INSERT INTO {$table} (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
                    }
                    echo "\n";
                }
            }

            exit;
        }

        if (isset($_POST['restore_database']) && isset($_FILES['restore_file']) && is_uploaded_file($_FILES['restore_file']['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES['restore_file']['name'], PATHINFO_EXTENSION));

            if ($ext === 'sql') {
                $restoreTables = ['providers', 'settings', 'parked_domains'];
                foreach ($restoreTables as $table) {
                    $this->db->exec("DELETE FROM {$table}");
                }

                $sql = file_get_contents($_FILES['restore_file']['tmp_name']);
                $this->db->exec($sql);
                header("Location: " . $this->adminUrl('settings') . '&restored=1');
                exit;
            }
            // assume invalid file
            $this->render('admin/info', [
                'title' => 'Restore Failed',
                'error' => 'Invalid restore file. Please upload a valid SQL file.'
            ]);
        }

        if (isset($_POST['save_settings'])) {
            $fields = ['ga_id', 'admin_google_analytics', 'lease_email', 'lead_email', 'admin_domain', 'business_name', 'tagline', 'intro', 'recaptcha_site_key', 'recaptcha_secret_key'];
            foreach ($fields as $field) {
                if (isset($_POST[$field])) {
                    $this->setSetting($field, trim($_POST[$field]));
                }
            }
        }

        if (isset($_POST['clear_content_cache'])) {
            $this->db->exec("DELETE FROM content_cache");
            header("Location: " . $this->adminUrl('settings') . '&cache_cleared=1');
            exit;
        }

        if (isset($_POST['upload_service_logo']) && isset($_FILES['service_logo']) && is_uploaded_file($_FILES['service_logo']['tmp_name'])) {
            $allowedTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($_FILES['service_logo']['tmp_name']);

            if (!in_array($mime, $allowedTypes, true)) {
                header("Location: " . $this->adminUrl('settings') . '&logo_error=1');
                exit;
            }

            $extMap = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];
            $ext = $extMap[$mime] ?? 'png';
            $destDir = $_SERVER['DOCUMENT_ROOT'] . '/includes/media';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0755, true);
            }

            $dest = $destDir . '/service-logo.' . $ext;
            move_uploaded_file($_FILES['service_logo']['tmp_name'], $dest);

            $this->setSetting('service_logo', 'service-logo.' . $ext);
            header("Location: " . $this->adminUrl('settings') . '&logo_uploaded=1');
            exit;
        }

        if (isset($_POST['remove_service_logo'])) {
            $current = $this->getSetting('service_logo');
            if ($current) {
                $path = $_SERVER['DOCUMENT_ROOT'] . '/includes/media/' . $current;
                if (file_exists($path)) {
                    unlink($path);
                }
                $this->setSetting('service_logo', '');
            }
            header("Location: " . $this->adminUrl('settings') . '&logo_removed=1');
            exit;
        }
    }

    /* ---------------------------------------------------------
       LEADS
    --------------------------------------------------------- */
    protected function handleLeadPost(): void
    {
        // DELETE
        if (isset($_POST['delete_id'])) {
            $stmt = $this->db->prepare("DELETE FROM leads WHERE id = ?");
            $stmt->execute([intval($_POST['delete_id'])]);
            return;
        }

        // ARCHIVE / UNARCHIVE
        if (isset($_POST['toggle_archive_id'])) {
            $id = intval($_POST['toggle_archive_id']);
            $this->db->prepare("UPDATE leads SET archived = CASE WHEN archived = 1 THEN 0 ELSE 1 END WHERE id = ?")->execute([$id]);
            return;
        }

        // EXPORT CSV
        if (isset($_POST['export'])) {
            $archived = isset($_POST['export_archived']) ? 1 : 0;

            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="leads' . ($archived ? '-archived' : '') . '.csv"');

            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Email', 'Domain', 'Message', 'Date']);

            $stmt = $this->db->prepare("SELECT * FROM leads WHERE archived = ? ORDER BY created_at DESC");
            $stmt->execute([$archived]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $lead) {
                fputcsv($out, [
                    $lead['name'],
                    $lead['email'],
                    $lead['domain'] ?? '',
                    $lead['message'],
                    date('Y-m-d H:i', $lead['created_at'])
                ]);
            }

            fclose($out);
            exit;
        }
    }

    /* ---------------------------------------------------------
       LOGIN (delegates to Auth)
    --------------------------------------------------------- */
    protected function handleLoginPost(): void
    {
        $result = $this->auth->login(
            trim($_POST['username'] ?? ''),
            $_POST['password'] ?? ''
        );

        if ($result === 'db') {
            header("Location: /admin");
            exit;
        }

        if ($result === 'lockout') {
            header("Location: " . $this->adminUrl('users'));
            exit;
        }

        $this->render('admin/login', [
            'title' => 'Admin Login',
            'error' => 'Invalid username or password.'
        ]);
        exit;
    }
}

class Auth {
    private PDO $db;
    private array $config;

    public function __construct(PDO &$db, array &$config) {
        $this->db = &$db;
        $this->config = &$config;
    }

    /* ---------------------------------------------------------
       SESSION
    --------------------------------------------------------- */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function clear(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function destroy(): void
    {
        session_destroy();
    }

    /* ---------------------------------------------------------
       CSRF
    --------------------------------------------------------- */
    public function csrfToken(): string
    {
        if ($this->get('csrf') === null) {
            $this->set('csrf', bin2hex(random_bytes(16)));
        }
        return $this->get('csrf');
    }

    public function validateCsrf(): bool
    {
        return isset($_POST['csrf']) && $_POST['csrf'] === $this->get('csrf', '');
    }

    /* ---------------------------------------------------------
       AUTHENTICATION
    --------------------------------------------------------- */
    public function isAuthenticated(): bool
    {
        return $this->get('admin') !== null;
    }

    public function hasUsers(): bool
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM users");
        return (int) $stmt->fetchColumn() > 0;
    }

    public function login(string $username, string $password): string
    {
        if ($username === '' || $password === '') {
            return 'failed';
        }

        logger("Login attempt for username: " . $username, 'INFO');

        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            $this->set('admin', $user['id']);
            $this->set('admin_role', $user['role'] ?? 'admin');
            logger("DB Login successful for username: " . $username, 'INFO');
            return 'db';
        }

        logger("DB Login failed for username: " . $username, 'WARNING');

        if (isset($this->config['admin']['username'], $this->config['admin']['password'])) {
            if ($username === $this->config['admin']['username'] && $password === $this->config['admin']['password']) {
                $this->set('admin', 1000);
                $this->set('admin_role', 'lockout');
                logger("Config Login successful for lockout admin: " . $username, 'INFO');
                return 'lockout';
            }
        }

        logger("Login failed for username: " . $username, 'ERROR');
        return 'failed';
    }

    public function handleFirstRun(string $username, string $password, string $confirm): ?string
    {
        if ($this->hasUsers()) {
            return 'An administrator already exists.';
        }

        $username = trim($username);
        $password = trim($password);
        $confirm = trim($confirm);

        if ($username === '' || $password === '' || $confirm === '') {
            return 'Please enter a username and password.';
        }

        if ($password !== $confirm) {
            return 'Passwords do not match.';
        }

        $stmt = $this->db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), 'admin']);

        $userId = (int) $this->db->lastInsertId();
        $this->set('admin', $userId);
        $this->set('admin_role', 'admin');

        return null;
    }

    /* ---------------------------------------------------------
       ROLES
    --------------------------------------------------------- */
    public function getRole(): string
    {
        return $this->get('admin_role', 'viewer');
    }

    public function canEdit(): bool
    {
        return $this->getRole() !== 'viewer';
    }

    public function canManageUsers(): bool
    {
        return in_array($this->getRole(), ['admin', 'lockout'], true);
    }

    public function canManageSettings(): bool
    {
        return $this->getRole() === 'admin';
    }

    public function canAccessPage(string $page): bool
    {
        $role = $this->getRole();

        if ($role === 'lockout') {
            return $page === 'users';
        }

        if ($role === 'admin') {
            return true;
        }

        $editorPages = ['dashboard', 'domains', 'providers', 'content', 'leads', 'traffic', 'cache'];
        $viewerPages = ['dashboard', 'content', 'leads', 'traffic', 'cache'];

        if ($role === 'editor') {
            return in_array($page, $editorPages, true);
        }

        return in_array($page, $viewerPages, true);
    }

    /* ---------------------------------------------------------
       USER CRUD
    --------------------------------------------------------- */
    public function handleUserPost(array $post): void
    {
        if (isset($post['delete_id'])) {
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([intval($post['delete_id'])]);
            return;
        }

        if (isset($post['edit_id'])) {
            $id = intval($post['edit_id']);
            $username = trim($post['username'] ?? '');
            $role = trim($post['role'] ?? 'admin');

            if ($username !== '') {
                $fields = ['username = ?', 'role = ?'];
                $values = [$username, $role, $id];
                $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";

                if (!empty($post['password'])) {
                    $sql = "UPDATE users SET username = ?, role = ?, password = ? WHERE id = ?";
                    $values = [$username, $role, password_hash($post['password'], PASSWORD_DEFAULT), $id];
                }

                $stmt = $this->db->prepare($sql);
                $stmt->execute($values);
            }
            return;
        }

        if (isset($post['username'], $post['password'])) {
            $stmt = $this->db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([
                trim($post['username']),
                password_hash($post['password'], PASSWORD_DEFAULT),
                trim($post['role'] ?? 'admin')
            ]);
        }
    }
}

function logger($message, $level = 'INFO', $filename = null) {
    $rootPath = $GLOBALS['app_root_path'] ?? dirname(__DIR__);

    if ($filename === null) {
        $filename = rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'app.log';
    }
    // If an array or object is passed, convert it to JSON string
    if (is_array($message) || is_object($message)) {
        $message = json_encode($message);
    }
    
    $formattedMessage = sprintf("[%s] [%s]: %s%s", date('Y-m-d H:i:s'), strtoupper($level), $message, PHP_EOL);
    
    file_put_contents($filename, $formattedMessage, FILE_APPEND | LOCK_EX);
}