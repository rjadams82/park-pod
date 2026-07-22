<div class="park-container">

    <div class="about-section">
        <h2>About This Domain</h2>
        <p>This domain is part of the <?= htmlspecialchars($app->getSetting('business_name') ?? 'Domain Team') ?> portfolio and focuses on 
        <strong><?= htmlspecialchars($config['site']['topic']) ?></strong>. It may be available for lease or partnership opportunities.</p>
        <?php $domain = $config['site']['domain']; ?>
        <h3>Research</h3>
        <div class="market-grid">
            <a href="https://instantdomainsearch.com/?q=<?= urlencode($domain) ?>" target="_blank" class="market-card">
                <img src="https://www.google.com/s2/favicons?domain=instantdomainsearch.com&sz=32" alt="" width="32" height="32">
                <span>Instant Domain Search</span>
            </a>
            <a href="https://namebio.com/<?= urlencode($domain) ?>" target="_blank" class="market-card">
                <img src="https://www.google.com/s2/favicons?domain=namebio.com&sz=32" alt="" width="32" height="32">
                <span>NameBio Lookup</span>
            </a>
            <a href="https://www.godaddy.com/domain-value-appraisal/appraisal?domainToCheck=<?= urlencode($domain) ?>" target="_blank" class="market-card">
                <img src="https://www.google.com/s2/favicons?domain=godaddy.com&sz=32" alt="" width="32" height="32">
                <span>GoDaddy Appraisal</span>
            </a>
            <a href="https://web.archive.org/web/*/<?= urlencode($domain) ?>" target="_blank" class="market-card">
                <img src="https://www.google.com/s2/favicons?domain=web.archive.org&sz=32" alt="" width="32" height="32">
                <span>Wayback Machine</span>
            </a>
            <a href="https://humbleworth.com/valuation/single?domain=<?= urlencode($domain) ?>" target="_blank" class="market-card">
                <img src="https://www.google.com/s2/favicons?domain=humbleworth.com&sz=32" alt="" width="32" height="32">
                <span>HumbleWorth Appraisal</span>
            </a>
            <a href="https://domainindex.com/domains/<?= urlencode($domain) ?>" target="_blank" class="market-card">
                <img src="https://www.google.com/s2/favicons?domain=domainindex.com&sz=32" alt="" width="32" height="32">
                <span>DomainIndex Appraisal</span>
            </a>
        </div>
        <h3>Purchase</h3>
        <div class="market-grid">
            <a href="https://www.godaddy.com/domainsearch/find?domainToCheck=<?= urlencode($domain) ?>" target="_blank" class="market-card">
                <img src="https://www.google.com/s2/favicons?domain=godaddy.com&sz=32" alt="" width="32" height="32">
                <span>GoDaddy</span>
            </a>
            <a href="https://porkbun.com/checkout/search?q=<?= urlencode($domain) ?>" target="_blank" class="market-card">
                <img src="https://www.google.com/s2/favicons?domain=porkbun.com&sz=32" alt="" width="32" height="32">
                <span>PorkBun</span>
            </a>
            <a href="https://www.namecheap.com/domains/registration/results/?domain=<?= urlencode($domain) ?>" target="_blank" class="market-card">
                <img src="https://www.google.com/s2/favicons?domain=namecheap.com&sz=32" alt="" width="32" height="32">
                <span>NameCheap</span>
            </a>
            <a href="https://www.dynadot.com/?domain=<?= urlencode($domain) ?>" target="_blank" class="market-card">
                <img src="https://www.google.com/s2/favicons?domain=dynadot.com&sz=32" alt="" width="32" height="32">
                <span>DynaDot</span>
            </a>
            <a href="https://domainagents.com/offer/<?= urlencode($domain) ?>" target="_blank" class="market-card">
                <img src="https://www.google.com/s2/favicons?domain=domainagents.com&sz=32" alt="" width="32" height="32">
                <span>Domain Agents</span>
            </a>
            <a href="https://mediaoptions.com/domain-broker/<?= urlencode($domain) ?>" target="_blank" class="market-card">
                <img src="https://www.google.com/s2/favicons?domain=mediaoptions.com&sz=32" alt="" width="32" height="32">
                <span>Media Options</span>
            </a>
        </div>
        <a href="/" class="back-link">&laquo; Back to <?= htmlspecialchars($config['site']['domain']) ?></a>
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
