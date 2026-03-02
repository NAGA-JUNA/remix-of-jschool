<?php
$pageTitle = 'Support';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    :root {
        --jnv-primary: #1a56db;
        --jnv-primary-dark: #0f3a8e;
        --jnv-accent: #3b82f6;
        --jnv-gradient: linear-gradient(135deg, #1a56db 0%, #0f2a5e 100%);
        --jnv-gradient-light: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        --jnv-success: #16a34a;
        --jnv-whatsapp: #25d366;
        --jnv-card-shadow: 0 4px 24px rgba(0,0,0,0.07);
        --jnv-card-hover-shadow: 0 8px 32px rgba(26,86,219,0.13);
        --jnv-radius: 16px;
    }
    [data-bs-theme="dark"] {
        --jnv-gradient: linear-gradient(135deg, #1e3a6e 0%, #0a1628 100%);
        --jnv-gradient-light: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        --jnv-card-shadow: 0 4px 24px rgba(0,0,0,0.25);
        --jnv-card-hover-shadow: 0 8px 32px rgba(59,130,246,0.18);
    }

    /* ── Hero ── */
    .support-hero {
        background: var(--jnv-gradient);
        color: #fff;
        padding: 3.5rem 0 3rem;
        position: relative;
        overflow: hidden;
        border-radius: var(--jnv-radius);
        margin-bottom: 2rem;
    }
    .support-hero::after {
        content: '';
        position: absolute;
        top: -40%; right: -10%;
        width: 500px; height: 500px;
        background: rgba(255,255,255,0.04);
        border-radius: 50%;
    }
    .hero-logo { height: 56px; margin-bottom: 0.5rem; }
    .hero-brand { font-size: 1.75rem; font-weight: 700; }
    .hero-tagline { opacity: 0.85; font-size: 0.95rem; }
    .hero-title { font-size: 2rem; font-weight: 700; line-height: 1.3; }
    .hero-subtitle { opacity: 0.88; font-size: 1.05rem; margin-top: 0.75rem; max-width: 540px; }

    /* Chat mockup card */
    .chat-mockup {
        background: rgba(255,255,255,0.1);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255,255,255,0.15);
        border-radius: var(--jnv-radius);
        padding: 1.5rem;
        max-width: 340px;
        margin-left: auto;
    }
    .chat-bubble {
        border-radius: 12px;
        padding: 0.6rem 1rem;
        margin-bottom: 0.5rem;
        font-size: 0.85rem;
        max-width: 85%;
    }
    .chat-bubble.agent {
        background: rgba(255,255,255,0.18);
        color: #fff;
    }
    .chat-bubble.user {
        background: var(--jnv-accent);
        color: #fff;
        margin-left: auto;
    }
    .chat-input-mock {
        background: rgba(255,255,255,0.12);
        border: 1px solid rgba(255,255,255,0.18);
        border-radius: 24px;
        padding: 0.5rem 1rem;
        color: rgba(255,255,255,0.5);
        font-size: 0.85rem;
        margin-top: 0.75rem;
    }

    /* ── Cards ── */
    .support-card {
        background: var(--bs-body-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: var(--jnv-radius);
        box-shadow: var(--jnv-card-shadow);
        padding: 1.75rem;
        height: 100%;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .support-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--jnv-card-hover-shadow);
    }
    .support-card .card-icon {
        width: 52px; height: 52px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        margin-bottom: 1rem;
    }
    .icon-whatsapp { background: #dcfce7; color: var(--jnv-whatsapp); }
    .icon-email    { background: #dbeafe; color: var(--jnv-primary); }
    .icon-clock    { background: #fef3c7; color: #d97706; }

    [data-bs-theme="dark"] .icon-whatsapp { background: #14532d; }
    [data-bs-theme="dark"] .icon-email    { background: #1e3a5f; }
    [data-bs-theme="dark"] .icon-clock    { background: #451a03; }

    .contact-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1.25rem;
        border-radius: 10px;
        font-weight: 600;
        font-size: 0.9rem;
        text-decoration: none;
        transition: all 0.2s ease;
    }
    .contact-btn:hover { transform: scale(1.04); }
    .btn-wa  { background: var(--jnv-whatsapp); color: #fff; }
    .btn-wa:hover  { background: #1fb855; color: #fff; }
    .btn-email { background: var(--jnv-primary); color: #fff; }
    .btn-email:hover { background: var(--jnv-primary-dark); color: #fff; }

    /* ── Features ── */
    .feature-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: var(--jnv-gradient-light);
        border: 1px solid var(--bs-border-color);
        border-radius: 12px;
        padding: 0.75rem 1.25rem;
        font-size: 0.9rem;
        font-weight: 500;
        margin-bottom: 0.5rem;
        width: 100%;
    }
    .feature-badge i { color: var(--jnv-primary); font-size: 1.1rem; }

    .checklist-item {
        display: flex;
        align-items: flex-start;
        gap: 0.6rem;
        padding: 0.45rem 0;
        font-size: 0.92rem;
    }
    .checklist-item i { color: var(--jnv-success); margin-top: 2px; font-size: 0.95rem; }

    /* ── Quick Links ── */
    .quick-link-card {
        background: var(--bs-body-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: var(--jnv-radius);
        box-shadow: var(--jnv-card-shadow);
        padding: 1.5rem;
    }
    .quick-link-item {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.55rem 0.75rem;
        border-radius: 10px;
        text-decoration: none;
        color: var(--bs-body-color);
        font-size: 0.9rem;
        transition: background 0.15s ease;
    }
    .quick-link-item:hover {
        background: var(--jnv-gradient-light);
        color: var(--jnv-primary);
    }
    .quick-link-item i { color: var(--jnv-accent); }

    /* ── Footer ── */
    .support-footer-strip {
        background: var(--jnv-gradient);
        color: rgba(255,255,255,0.8);
        padding: 1.5rem;
        font-size: 0.85rem;
        margin-top: 2rem;
        border-radius: var(--jnv-radius);
    }
    .support-footer-strip a { color: rgba(255,255,255,0.9); text-decoration: underline; }

    .section-title {
        font-size: 1.1rem;
        font-weight: 700;
        margin-bottom: 1rem;
        color: var(--bs-heading-color, var(--bs-body-color));
    }

    /* ── Services Section ── */
    .services-section { background: var(--bs-body-bg); padding: 80px 0; }
    .services-heading { font-size: 1.75rem; font-weight: 700; text-align: center; margin-bottom: 0.5rem; }
    .services-subtitle { text-align: center; color: var(--bs-secondary-color); max-width: 640px; margin: 0 auto 2.5rem; font-size: 0.95rem; }

    .service-card {
        background: var(--bs-body-bg);
        border: 1px solid var(--bs-border-color);
        border-radius: var(--jnv-radius);
        box-shadow: var(--jnv-card-shadow);
        padding: 2rem 1.5rem;
        height: 100%;
        text-align: center;
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .service-card:hover { transform: translateY(-6px); box-shadow: var(--jnv-card-hover-shadow); }

    .service-icon {
        width: 56px; height: 56px;
        border-radius: 14px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }
    .icon-hosting   { background: #dbeafe; color: #2563eb; }
    .icon-domain    { background: #ede9fe; color: #7c3aed; }
    .icon-email-svc { background: #ccfbf1; color: #0d9488; }
    .icon-dev       { background: #ffedd5; color: #ea580c; }
    .icon-ssl       { background: #dcfce7; color: #16a34a; }
    .icon-perf      { background: #fee2e2; color: #dc2626; }

    [data-bs-theme="dark"] .service-card { background: var(--bs-tertiary-bg); }
    [data-bs-theme="dark"] .icon-hosting   { background: #1e3a5f; }
    [data-bs-theme="dark"] .icon-domain    { background: #2e1065; }
    [data-bs-theme="dark"] .icon-email-svc { background: #134e4a; }
    [data-bs-theme="dark"] .icon-dev       { background: #431407; }
    [data-bs-theme="dark"] .icon-ssl       { background: #14532d; }
    [data-bs-theme="dark"] .icon-perf      { background: #450a0a; }

    .cta-strip {
        background: var(--jnv-gradient-light);
        border-radius: var(--jnv-radius);
        padding: 2rem;
        text-align: center;
        margin-top: 2.5rem;
    }
    .cta-strip p { font-size: 1.05rem; font-weight: 500; margin-bottom: 1rem; }
    .cta-strip .btn { padding: 0.6rem 2rem; font-weight: 600; border-radius: 10px; }

    @media (max-width: 768px) {
        .services-section { padding: 48px 0; }
        .services-heading { font-size: 1.35rem; }
    }
</style>

<!-- ═══════════════════ HERO ═══════════════════ -->
<section class="support-hero">
    <div class="container-fluid">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="mb-3">
                    <img src="assets/jnvweb-logo.png" alt="JNV Web Logo" class="hero-logo">
                </div>
                <p class="mb-3 opacity-75" style="font-size:0.95rem;">Professional Web Design &amp; Hosting Solutions</p>
                <h1 class="hero-title">Stress-free website management with proactive customer support</h1>
                <p class="hero-subtitle">Our expert technical team is available to assist you with website development, hosting, maintenance, and troubleshooting.</p>
            </div>
            <div class="col-lg-5 d-none d-lg-block">
                <div class="chat-mockup">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div style="width:10px;height:10px;border-radius:50%;background:#22c55e;"></div>
                        <span style="font-size:0.8rem;opacity:0.8;">JNV Web Support – Online</span>
                    </div>
                    <div class="chat-bubble agent">Hi! 👋 How can we help you today?</div>
                    <div class="chat-bubble user">I need help with SSL setup</div>
                    <div class="chat-bubble agent">Sure! I'll guide you through it right away. 🔒</div>
                    <div class="chat-input-mock"><i class="bi bi-chat-dots me-2"></i>Type a message…</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════ FEATURES + QUICK LINKS ═══════════════════ -->
<section class="py-4">
    <div class="row g-4">
        <!-- Left: Features -->
        <div class="col-lg-8">
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="feature-badge">
                        <i class="bi bi-headset"></i>
                        <div>
                            <strong>24/7 Support in English &amp; Hindi</strong><br>
                            <small class="text-body-secondary">Live chat, WhatsApp, and email assistance</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="feature-badge">
                        <i class="bi bi-lightning-charge-fill"></i>
                        <div>
                            <strong>Prompt &amp; Friendly Responses</strong><br>
                            <small class="text-body-secondary">Average response time under 30 minutes</small>
                        </div>
                    </div>
                </div>
            </div>

            <h5 class="section-title"><i class="bi bi-patch-check-fill text-primary me-2"></i>What Our Support Team Helps You With</h5>
            <div class="row g-2">
                <div class="col-sm-6">
                    <div class="checklist-item"><i class="bi bi-check-circle-fill"></i> Website development issues</div>
                    <div class="checklist-item"><i class="bi bi-check-circle-fill"></i> Hosting migration assistance</div>
                    <div class="checklist-item"><i class="bi bi-check-circle-fill"></i> cPanel &amp; email configuration</div>
                    <div class="checklist-item"><i class="bi bi-check-circle-fill"></i> SSL installation &amp; renewal</div>
                </div>
                <div class="col-sm-6">
                    <div class="checklist-item"><i class="bi bi-check-circle-fill"></i> Website speed optimization</div>
                    <div class="checklist-item"><i class="bi bi-check-circle-fill"></i> Bug fixes &amp; troubleshooting</div>
                    <div class="checklist-item"><i class="bi bi-check-circle-fill"></i> Backup &amp; restore support</div>
                    <div class="checklist-item"><i class="bi bi-check-circle-fill"></i> Security monitoring &amp; malware cleanup</div>
                </div>
            </div>
        </div>

        <!-- Right: Quick Links -->
        <div class="col-lg-4">
            <div class="quick-link-card">
                <h6 class="section-title"><i class="bi bi-link-45deg me-1"></i>Quick Links</h6>
                <a href="#" class="quick-link-item"><i class="bi bi-bug"></i> Report a Bug</a>
                <a href="#" class="quick-link-item"><i class="bi bi-lightbulb"></i> Request a Feature</a>
                <a href="#" class="quick-link-item"><i class="bi bi-arrow-left-right"></i> Website Migration Help</a>
                <a href="#" class="quick-link-item"><i class="bi bi-hdd-rack"></i> Hosting Support</a>
                <a href="#" class="quick-link-item"><i class="bi bi-tools"></i> Technical Assistance</a>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════ SERVICES ═══════════════════ -->
<section class="services-section">
    <h2 class="services-heading">Everything you need to build and grow your website</h2>
    <p class="services-subtitle">From hosting and domains to business email, website design, and AI-powered tools — all in one place with JNV Web.</p>
    <div class="row g-4">
        <div class="col-lg-4 col-md-6">
            <div class="service-card">
                <div class="service-icon icon-hosting mx-auto"><i class="bi bi-hdd-rack-fill"></i></div>
                <h6 class="fw-bold mb-2">Web Hosting</h6>
                <p class="text-body-secondary mb-0" style="font-size:0.9rem;">Fast, secure, and scalable hosting solutions with 99.9% uptime guarantee.</p>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="service-card">
                <div class="service-icon icon-domain mx-auto"><i class="bi bi-globe2"></i></div>
                <h6 class="fw-bold mb-2">Domain Registration</h6>
                <p class="text-body-secondary mb-0" style="font-size:0.9rem;">Search and register your perfect domain name easily.</p>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="service-card">
                <div class="service-icon icon-email-svc mx-auto"><i class="bi bi-envelope-at-fill"></i></div>
                <h6 class="fw-bold mb-2">Business Email</h6>
                <p class="text-body-secondary mb-0" style="font-size:0.9rem;">Professional email addresses with spam protection and reliability.</p>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="service-card">
                <div class="service-icon icon-dev mx-auto"><i class="bi bi-code-slash"></i></div>
                <h6 class="fw-bold mb-2">Website Development</h6>
                <p class="text-body-secondary mb-0" style="font-size:0.9rem;">Custom website design and development tailored for your business.</p>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="service-card">
                <div class="service-icon icon-ssl mx-auto"><i class="bi bi-shield-lock-fill"></i></div>
                <h6 class="fw-bold mb-2">SSL &amp; Security</h6>
                <p class="text-body-secondary mb-0" style="font-size:0.9rem;">Free SSL, malware monitoring, and website security solutions.</p>
            </div>
        </div>
        <div class="col-lg-4 col-md-6">
            <div class="service-card">
                <div class="service-icon icon-perf mx-auto"><i class="bi bi-speedometer2"></i></div>
                <h6 class="fw-bold mb-2">Performance Optimization</h6>
                <p class="text-body-secondary mb-0" style="font-size:0.9rem;">Speed optimization, caching setup, and technical enhancements.</p>
            </div>
        </div>
    </div>
    <div class="cta-strip">
        <p>Start your online journey with JNV Web today.</p>
        <a href="https://jnvweb.in" target="_blank" class="btn btn-primary"><i class="bi bi-rocket-takeoff me-2"></i>Get Started</a>
    </div>
</section>

<!-- ═══════════════════ CONTACT CARDS ═══════════════════ -->
<section class="pb-4">
    <div class="row g-4">
        <!-- WhatsApp -->
        <div class="col-md-4">
            <div class="support-card text-center">
                <div class="card-icon icon-whatsapp mx-auto"><i class="bi bi-whatsapp"></i></div>
                <h6 class="fw-bold mb-2">WhatsApp Support</h6>
                <p class="text-body-secondary mb-3" style="font-size:0.9rem;">Chat instantly with our support team</p>
                <a href="https://wa.me/918106811171" target="_blank" class="contact-btn btn-wa">
                    <i class="bi bi-whatsapp"></i> +91 81068 11171
                </a>
            </div>
        </div>
        <!-- Email -->
        <div class="col-md-4">
            <div class="support-card text-center">
                <div class="card-icon icon-email mx-auto"><i class="bi bi-envelope-fill"></i></div>
                <h6 class="fw-bold mb-2">Email Support</h6>
                <p class="text-body-secondary mb-3" style="font-size:0.9rem;">Send us detailed queries</p>
                <a href="mailto:info@jnvweb.in" class="contact-btn btn-email">
                    <i class="bi bi-envelope"></i> info@jnvweb.in
                </a>
            </div>
        </div>
        <!-- Hours -->
        <div class="col-md-4">
            <div class="support-card text-center">
                <div class="card-icon icon-clock mx-auto"><i class="bi bi-clock-fill"></i></div>
                <h6 class="fw-bold mb-2">Support Hours</h6>
                <p class="text-body-secondary mb-1" style="font-size:0.9rem;"><strong>Mon – Sat:</strong> 9:00 AM – 7:00 PM</p>
                <p class="text-body-secondary mb-0" style="font-size:0.9rem;"><strong>Sunday:</strong> Closed</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════ FOOTER STRIP ═══════════════════ -->
<div class="support-footer-strip">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <strong>JNV Web</strong> – Professional Web Solutions &nbsp;|&nbsp;
            <a href="https://jnvweb.in" target="_blank">jnvweb.in</a> &nbsp;|&nbsp;
            <a href="mailto:info@jnvweb.in">info@jnvweb.in</a>
        </div>
        <div class="opacity-75">Version v3.0</div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>