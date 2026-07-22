
<div class="hero">
    <div class="hero-inner">
        <?php $serviceLogo = $app->getSetting('service_logo'); ?>
        <?php if ($serviceLogo): ?>
            <div class="service-logo"><img src="/includes/media/<?= htmlspecialchars($serviceLogo) ?>" alt="<?= htmlspecialchars($app->getSetting('business_name') ?? 'Domain Team') ?>" style="max-width:280px; max-height:120px;"></div>
        <?php else: ?>
            <div class="service-logo"><?= htmlspecialchars($app->getSetting('business_name') ?? 'Domain Team') ?></div>
        <?php endif; ?>
        <p><?= htmlspecialchars($app->getSetting('tagline') ?? 'Premium Domain Leasing & Lead Generation Services') ?></p>
        <a class="cta-btn" href="#contact-modal">Contact Us</a>
    </div>
</div>

<div class="wide-section">
    <h2>What We Do</h2>
    <p class="intro">
        <?= nl2br(htmlspecialchars($app->getSetting('intro') ?? 'Domain team manages a portfolio of high-value domains and builds custom landing pages designed to capture leads, generate traffic, and support businesses looking for strong digital positioning.')) ?>
    </p>

    <div class="feature-grid">
        <div class="feature">
            <h3>Domain Leasing</h3>
            <p>Lease premium domains for branding, marketing, or redirect campaigns.</p>
        </div>
        <div class="feature">
            <h3>Lead Generation</h3>
            <p>We build targeted landing pages that convert visitors into qualified leads.</p>
        </div>
        <div class="feature">
            <h3>Traffic Monetization</h3>
            <p>Ad-optimized pages designed to generate recurring revenue.</p>
        </div>
        <div class="feature">
            <h3>Custom Landing Pages</h3>
            <p>Fast, lightweight PHP landing pages tailored to your business niche.</p>
        </div>
    </div>
</div>

<!-- Portfolio Showcase -->
<div class="portfolio">
    <h2>Domain Portfolio</h2>
    <p class="intro">A sample of domains available for lease or lead‑gen deployment.</p>

    <div class="portfolio-grid">
        <div class="portfolio-item">
            <h4>4bmotorsports.com</h4>
            <p>Motorsports branding & automotive lead generation.</p>
        </div>
        <div class="portfolio-item">
            <h4>admiralpropertiesct.com</h4>
            <p>Connecticut real estate & property management leads.</p>
        </div>
        <div class="portfolio-item">
            <h4>prosoundri.com</h4>
            <p>Audio production, DJ services, and event leads.</p>
        </div>
        <div class="portfolio-item">
            <h4>getridofmyproperty.com</h4>
            <p>Distressed seller & investor lead generation.</p>
        </div>
        <div class="portfolio-item">
            <h4>rebuildmysite.com</h4>
            <p>Web repair, redesign, and emergency site recovery.</p>
        </div>
        <div class="portfolio-item">
            <h4>escapingkabul.com</h4>
            <p>Documentary, journalism, and humanitarian projects.</p>
        </div>
    </div>
</div>

<!-- Pricing Table -->
<div class="pricing">
    <h2>Pricing</h2>
    <p class="intro">Flexible plans for domain leasing and lead generation services.</p>

    <table class="pricing-table">
        <tr>
            <th>Service</th>
            <th>Description</th>
            <th>Monthly Cost</th>
        </tr>
        <tr>
            <td>Domain Leasing</td>
            <td>Exclusive use of a premium domain for branding or redirect campaigns.</td>
            <td>$25 – $150</td>
        </tr>
        <tr>
            <td>Lead Generation</td>
            <td>Custom landing page + exclusive leads delivered to your inbox.</td>
            <td>$50 – $300</td>
        </tr>
        <tr>
            <td>Traffic Monetization Setup</td>
            <td>AdSense/Ezoic integration and content optimization.</td>
            <td>$40 – $120</td>
        </tr>
        <tr>
            <td>Custom Landing Page</td>
            <td>Fully managed PHP landing page tailored to your niche.</td>
            <td>$75 – $250</td>
        </tr>
    </table>
</div>

<!-- Contact Modal -->
<div id="contact-modal" class="contact-modal"<?= !empty($contactSubmitted) ? '' : ' style="display:none;"' ?>>
    <div class="contact-modal-content">
        <div class="contact-modal-header">
            <span class="contact-modal-title">Contact Us</span>
            <a href="#" class="contact-modal-close">&times;</a>
        </div>
        <form method="POST" class="contact-modal-body" id="contact-form">
            <input type="text" name="contact_name" placeholder="Your Name">
            <input type="email" name="contact_email" placeholder="Your Email" required>
            <textarea name="contact_message" rows="4" placeholder="Your Message"></textarea>
            <input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
            <?php $recaptchaKey = $app->getSetting('recaptcha_site_key'); ?>
            <?php if (!empty($recaptchaKey)): ?>
                <script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($recaptchaKey) ?>"></script>
            <?php endif; ?>
            <button type="submit">Send Message</button>
            <?php if (!empty($contactSubmitted)): ?>
                <div id="contact-thankyou">
                    <p>Thank you - your message has been received.</p>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>




