<div class="admin-container">

    <div class="admin-box">
        <h2>Site Overview</h2>

        <p>Site Domain: <?= htmlspecialchars($config['site']['domain']) ?></p>
        <p>Admin Path: <?= htmlspecialchars($app->adminPath()) ?></p>
        <p>Domains: <?= $app->db->query("SELECT COUNT(*) FROM parked_domains")->fetchColumn() ?></p>
        <p>Providers: <?= $app->db->query("SELECT COUNT(*) FROM providers")->fetchColumn() ?></p>
        <p>Leads: <?= $app->db->query("SELECT COUNT(*) FROM leads")->fetchColumn() ?></p>
        <p>Users: <?= $app->db->query("SELECT COUNT(*) FROM users")->fetchColumn() ?></p>
        
    </div>

    <div class="admin-box">
        <h2>Cache</h2>
        <?php
        $cacheCount = $app->db->query("SELECT COUNT(*) FROM content_cache")->fetchColumn();
        ?>
        <p>Content cache entries: <?= number_format($cacheCount) ?></p>

        <?php if (isset($_GET['cache_cleared'])): ?>
            <p style="color:#2ecc71; margin-top:8px;">Cache cleared successfully.</p>
        <?php endif; ?>

        <form method="POST" style="margin-top:12px;">
            <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
            <input type="hidden" name="clear_content_cache" value="1">
            <button type="submit" onclick="return confirm('Clear all cached content?')">Clear Cache</button>
        </form>
    </div>

    <div class="admin-box">
        <h2>System Details</h2>
        <p>php version: <?= phpversion()?></p>
        <p>webserver: <?= $_SERVER['SERVER_SOFTWARE'] ?></p>
        <p>disk free: <?= round(disk_free_space(".") / (1024 * 1024 * 1024), 2) . ' GB' ?></p>
        <p>disk total: <?= round(disk_total_space(".") / (1024 * 1024 * 1024), 2) . ' GB' ?></p>
        <p>SQLite File: <?= $app->config['database']['path'] ?></p>
        <p>DB Size: <?= round(filesize(realpath($_SERVER['DOCUMENT_ROOT'] . '/../' . $app->config['database']['path'])) / 1024, 2) ?> KB</p>

        <form method="POST" style="margin-top:12px;">
            <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
            <input type="hidden" name="backup_database" value="1">
            <button type="submit">Backup Settings </button>
        </form>

        <?php if (isset($_GET['restored'])): ?>
            <p style="color:#2ecc71; margin-top:8px;">Settings restored successfully.</p>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="group" style="margin-top:12px;">
            <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
            <input type="hidden" name="restore_database" value="1">
            <label for="fileRestore" style="font-size:11px; text-transform:uppercase; letter-spacing:0.04em; color:#aaa; display:block; margin-bottom:4px;">Restore from .sql file</label>
            <input type="file" name="restore_file" id="fileRestore" accept=".sql" required style="margin-bottom:8px;">
            <button type="submit" onclick="return confirm('This will overwrite all current settings. Are you sure?')">Restore Settings</button>
        </form>
    </div>



    <div class="admin-box">
        <h2>Site Settings</h2>

        <form method="POST" style="flex-direction:column;">
            <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
            <input type="hidden" name="save_settings" value="1">

            <div class="admin-form-row">
                <div class="form-group">
                    <label>Business Name</label>
                    <input type="text" name="business_name" placeholder="My Business"
                           value="<?= htmlspecialchars($app->getSetting('business_name') ?? '') ?>">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="form-group">
                    <label>Tagline</label>
                    <input type="text" name="tagline" placeholder="Premium Domain Leasing & Lead Generation Services"
                           value="<?= htmlspecialchars($app->getSetting('tagline') ?? '') ?>">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="form-group">
                    <label>Introduction</label>
                    <textarea name="intro" rows="3" placeholder="About your business..."><?= htmlspecialchars($app->getSetting('intro') ?? '') ?></textarea>
                </div>
            </div>

            <div class="admin-form-row">
                <div class="form-group">
                    <label>Business Contact Email (public)</label>
                    <input type="email" name="lease_email" placeholder="sales@mybusiness.com"
                           value="<?= htmlspecialchars($app->getSetting('lease_email') ?? $config['site']['lease_email'] ?? '') ?>">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="form-group">
                    <label>Domain Lead Notifications (private)</label>
                    <input type="email" name="lead_email" placeholder="domains@mybusiness.com"
                           value="<?= htmlspecialchars($app->getSetting('lead_email') ?? $config['site']['lead_email'] ?? '') ?>">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="form-group">
                    <label>Parked Page Google Analytics ID</label>
                    <input type="text" name="ga_id" placeholder="G-XXXXXXXXXX"
                           value="<?= htmlspecialchars($app->getSetting('ga_id') ?? $config['site']['ga_id'] ?? '') ?>">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="form-group">
                    <label>Service Page Google Analytics ID</label>
                    <input type="text" name="admin_google_analytics" placeholder="G-XXXXXXXXXX"
                           value="<?= htmlspecialchars($app->getSetting('admin_google_analytics') ?? '') ?>">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="form-group">
                    <label>Google reCAPTCHA Site Key</label>
                    <input type="text" name="recaptcha_site_key" placeholder="6Ld..."
                           value="<?= htmlspecialchars($app->getSetting('recaptcha_site_key') ?? '') ?>">
                </div>
            </div>

            <div class="admin-form-row">
                <div class="form-group">
                    <label>Google reCAPTCHA Secret Key</label>
                    <input type="text" name="recaptcha_secret_key" placeholder="6Ld..."
                           value="<?= htmlspecialchars($app->getSetting('recaptcha_secret_key') ?? '') ?>">
                </div>
            </div>

            <div class="admin-form-actions">
                <button type="submit">Save Settings</button>
            </div>
        </form>
    </div>

    <div class="admin-box">
        <h2>Service Logo</h2>
        <?php
        $serviceLogo = $app->getSetting('service_logo');
        $logoUrl = $serviceLogo ? '/includes/media/' . $serviceLogo : null;
        ?>

        <?php if (isset($_GET['logo_uploaded'])): ?>
            <p style="color:#2ecc71; margin-top:8px;">Logo uploaded successfully.</p>
        <?php elseif (isset($_GET['logo_error'])): ?>
            <p style="color:#e74c3c; margin-top:8px;">Invalid file type. Accepted: PNG, JPG, GIF, WebP, SVG.</p>
        <?php elseif (isset($_GET['logo_removed'])): ?>
            <p style="color:#2ecc71; margin-top:8px;">Logo removed.</p>
        <?php endif; ?>

        <?php if ($logoUrl): ?>
            <div style="margin:12px 0;">
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Service Logo" style="max-width:200px; max-height:100px; border-radius:8px; border:1px solid #444;">
            </div>
            <form method="POST" style="margin-top:8px;">
                <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
                <input type="hidden" name="remove_service_logo" value="1">
                <button type="submit" onclick="return confirm('Remove the service logo?')">Remove Logo</button>
            </form>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" style="margin-top:12px;">
            <input type="hidden" name="csrf" value="<?= $app->auth->csrfToken() ?>">
            <input type="hidden" name="upload_service_logo" value="1">
            <input type="file" name="service_logo" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" required style="margin-bottom:8px;">
            <button type="submit">Upload Logo</button>
        </form>
    </div>



</div>
