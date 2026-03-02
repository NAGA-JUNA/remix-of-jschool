<?php
/**
 * Shared Public Footer Component
 * 
 * Expected variables from parent page:
 *   $schoolName, $navLogo, $logoPath
 *   Optional: $schoolAddress, $schoolPhone, $schoolEmail, $schoolTimings
 */

$_footerAddress  = $schoolAddress  ?? getSetting('school_address', 'Anantapuram, Andhra Pradesh');
$_footerPhone    = $schoolPhone    ?? getSetting('school_phone', '+91 00000 00000');
$_footerEmail    = $schoolEmail    ?? getSetting('school_email', 'info@school.edu.in');
$_footerTimings  = $schoolTimings  ?? getSetting('school_timings', 'Mon – Sat: 8:00 AM – 4:00 PM');
$_footerFacebook = getSetting('social_facebook', '#');
$_footerTwitter  = getSetting('social_twitter', '#');
$_footerInsta    = getSetting('social_instagram', '#');
$_footerYoutube  = getSetting('social_youtube', '#');

// Dynamic footer color settings
$_clrFooterBg = getSetting('color_footer_bg', '#1a1a2e');
$_clrFooterCtaBg = getSetting('color_footer_cta_bg', '#0f2557');
$_clrFooterCtaEnd = getSetting('color_footer_cta_end', '#1a3a7a');
$_clrBrandPri = getSetting('brand_primary', '#1e40af');
$_clrBrandSec = getSetting('brand_secondary', '#6366f1');
?>

<!-- Footer Styles -->
<style>
/* ── Footer CTA ── */
.footer-cta {
    background: linear-gradient(135deg, <?= e($_clrFooterCtaBg) ?> 0%, <?= e($_clrFooterCtaEnd) ?> 100%);
    padding: 3.5rem 0;
    text-align: center;
}
.footer-cta h2 {
    font-family: 'Playfair Display', serif;
    font-size: 2rem;
    font-weight: 700;
    color: #fff;
    margin-bottom: 0.75rem;
}
.footer-cta p {
    color: rgba(255,255,255,0.75);
    font-size: 1.05rem;
    max-width: 550px;
    margin: 0 auto 1.5rem;
}
.footer-cta .btn-cta {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #fff; border: none; border-radius: 50px;
    padding: 0.65rem 2rem; font-weight: 600; font-size: 0.95rem;
    box-shadow: 0 4px 15px rgba(239,68,68,0.35);
    transition: all 0.3s; text-decoration: none; display: inline-block;
}
.footer-cta .btn-cta:hover { transform: translateY(-2px); box-shadow: 0 6px 25px rgba(239,68,68,0.5); color: #fff; }
.footer-cta .btn-outline {
    background: transparent; color: #fff;
    border: 1.5px solid rgba(255,255,255,0.4); border-radius: 50px;
    padding: 0.65rem 2rem; font-weight: 600; font-size: 0.95rem;
    transition: all 0.3s; text-decoration: none; display: inline-block;
}
.footer-cta .btn-outline:hover { background: rgba(255,255,255,0.1); border-color: #fff; color: #fff; }

/* ── Main Footer ── */
.site-footer {
    background: <?= e($_clrFooterBg) ?>;
    color: rgba(255,255,255,0.7);
    padding: 3.5rem 0 0;
    font-size: 0.9rem;
    line-height: 1.7;
}
.site-footer .footer-logo img {
    height: 60px !important; width: auto !important; background: transparent !important;
    padding: 0 !important; border: none !important; box-shadow: none !important;
    border-radius: 0 !important; object-fit: contain;
}
.site-footer .footer-location {
    color: rgba(255,255,255,0.55);
    font-size: 0.82rem;
    margin-top: 0.5rem;
}
.site-footer .footer-tagline {
    color: #60a5fa;
    font-size: 0.85rem;
    font-weight: 500;
    font-style: italic;
    margin: 0.4rem 0 0.75rem;
}
.site-footer .footer-desc {
    color: rgba(255,255,255,0.6);
    font-size: 0.85rem;
    margin-bottom: 1.2rem;
    max-width: 300px;
}

/* Headings */
.footer-heading {
    color: #fff;
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 1.2rem;
    padding-bottom: 0.6rem;
    position: relative;
}
.footer-heading::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0;
    width: 30px; height: 2px;
    background: linear-gradient(90deg, <?= e($_clrBrandPri) ?>, <?= e($_clrBrandSec) ?>);
    border-radius: 2px;
}

/* Links */
.footer-links { list-style: none; padding: 0; margin: 0; }
.footer-links li { margin-bottom: 0.5rem; }
.footer-links a {
    color: rgba(255,255,255,0.6);
    text-decoration: none;
    font-size: 0.88rem;
    transition: color 0.2s, padding-left 0.2s;
}
.footer-links a:hover { color: #fff; padding-left: 4px; }

/* Contact */
.footer-contact-item {
    display: flex; align-items: flex-start; gap: 0.6rem;
    margin-bottom: 0.85rem; color: rgba(255,255,255,0.65);
}
.footer-contact-item i {
    color: <?= e($_clrBrandPri) ?>; font-size: 1rem; margin-top: 2px; flex-shrink: 0;
}

/* Social */
.footer-social { display: flex; gap: 0.6rem; }
.footer-social a {
    width: 36px; height: 36px; border-radius: 50%;
    background: rgba(255,255,255,0.08);
    display: flex; align-items: center; justify-content: center;
    color: rgba(255,255,255,0.7); font-size: 1rem;
    text-decoration: none; transition: all 0.3s;
}
.footer-social a:hover { background: <?= e($_clrBrandPri) ?>; color: #fff; transform: translateY(-2px); }

/* Bottom Bar */
.footer-bottom {
    margin-top: 2.5rem;
    padding: 1.2rem 0;
    border-top: 1px solid rgba(255,255,255,0.08);
    text-align: center;
    color: rgba(255,255,255,0.4);
    font-size: 0.8rem;
}

/* ── Responsive ── */
@media (max-width: 991.98px) {
    .footer-cta h2 { font-size: 1.6rem; }
}
@media (max-width: 767.98px) {
    .site-footer { padding: 2.5rem 0 0; }
    .footer-cta { padding: 2.5rem 0; }
    .footer-cta h2 { font-size: 1.4rem; }
    .footer-heading { margin-top: 0.5rem; }
    .footer-brand-row { flex-direction: column; text-align: center; }
    .footer-desc { margin-left: auto; margin-right: auto; }
    .footer-social { justify-content: center; }
    .footer-heading::after { left: 50%; transform: translateX(-50%); }
}
</style>

<!-- Footer CTA Section -->
<section class="footer-cta">
    <div class="container">
        <h2>Become a Part of <?= e($schoolName) ?></h2>
        <p>Empowering students with quality education, strong values, and the skills to succeed in a changing world.</p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="/public/admission-form.php" class="btn-cta"><i class="bi bi-send me-1"></i>Get In Touch</a>
            <a href="/public/about.php" class="btn-outline"><i class="bi bi-arrow-right me-1"></i>Learn More</a>
        </div>
    </div>
</section>

<!-- Main Footer -->
<footer class="site-footer">
    <div class="container">
        <div class="row g-4">
            <!-- Brand Column -->
            <div class="col-lg-4 col-md-6">
                <div class="footer-logo" style="margin-bottom:15px;">
                    <?php if ($navLogo): ?>
                        <img src="<?= e($logoPath) ?>" alt="<?= e($schoolName) ?> Logo">
                    <?php else: ?>
                        <div style="width:50px;height:50px;border-radius:10px;background:linear-gradient(135deg,#1e40af,#3b82f6);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem;">
                            <i class="bi bi-mortarboard-fill"></i>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="footer-tagline">Inspiring Excellence in Education</div>
                <p class="footer-desc">Dedicated to nurturing young minds through innovative teaching, holistic development, and a commitment to academic excellence.</p>
                <div class="footer-social">
                    <a href="<?= e($_footerFacebook) ?>" aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                    <a href="<?= e($_footerTwitter) ?>" aria-label="X / Twitter"><i class="bi bi-twitter-x"></i></a>
                    <a href="<?= e($_footerInsta) ?>" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                    <a href="<?= e($_footerYoutube) ?>" aria-label="YouTube"><i class="bi bi-youtube"></i></a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-2 col-md-6 col-6">
                <h6 class="footer-heading">Quick Links</h6>
                <ul class="footer-links">
                    <li><a href="/public/about.php">About Us</a></li>
                    <li><a href="/public/teachers.php">Our Teachers</a></li>
                    <li><a href="/public/admission-form.php">Admissions</a></li>
                    <li><a href="/public/gallery.php">Gallery</a></li>
                    <li><a href="/public/events.php">Events</a></li>
                    <li><a href="/login.php">Admin Login</a></li>
                </ul>
            </div>

            <!-- Programs -->
            <div class="col-lg-3 col-md-6 col-6">
                <h6 class="footer-heading">Programs</h6>
                <ul class="footer-links">
                    <li><a href="#">Pre-Primary</a></li>
                    <li><a href="#">Primary School</a></li>
                    <li><a href="#">Upper Primary</a></li>
                    <li><a href="#">Co-Curricular Activities</a></li>
                    <li><a href="#">Sports Programs</a></li>
                </ul>
            </div>

            <!-- Contact Info -->
            <div class="col-lg-3 col-md-6">
                <h6 class="footer-heading">Contact Info</h6>
                <div class="footer-contact-item">
                    <i class="bi bi-geo-alt-fill"></i>
                    <span><?= e($_footerAddress) ?></span>
                </div>
                <div class="footer-contact-item">
                    <i class="bi bi-telephone-fill"></i>
                    <span><?= e($_footerPhone) ?></span>
                </div>
                <div class="footer-contact-item">
                    <i class="bi bi-envelope-fill"></i>
                    <span><?= e($_footerEmail) ?></span>
                </div>
                <div class="footer-contact-item">
                    <i class="bi bi-clock-fill"></i>
                    <span><?= e($_footerTimings) ?></span>
                </div>
            </div>
        </div>

        <!-- Bottom Bar -->
        <div class="footer-bottom">
            &copy; <?= date('Y') ?> <?= e($schoolName) ?>. All rights reserved.
        </div>
    </div>
</footer>

<!-- WhatsApp Floating Button -->
<?php
    $_waNumber = '';
    if (function_exists('getSetting')) {
        $_waNumber = getSetting('whatsapp_api_number', '');
    }
    if (empty($_waNumber) && !empty($whatsappNumber)) {
        $_waNumber = $whatsappNumber;
    }
    if (empty($_waNumber) && !empty($_footerPhone)) {
        $_waNumber = $_footerPhone;
    }
    if ($_waNumber):
        $_waNum = preg_replace('/[^0-9]/', '', $_waNumber);
        $_waText = urlencode('Hi, I need help regarding ' . ($schoolName ?? 'your school'));
?>
<a href="https://wa.me/<?= e($_waNum) ?>?text=<?= $_waText ?>" target="_blank" class="wa-float-btn" title="Chat on WhatsApp">
    <i class="bi bi-whatsapp"></i>
    <span class="wa-float-text">Chat with us</span>
</a>
<?php endif; ?>
<!-- Need Help Sidebar Tab -->
<div class="need-help-tab" data-bs-toggle="modal" data-bs-target="#needHelpModal" title="Need Help?">
    <i class="bi bi-journal-text"></i>
    <span>Need Help?</span>
</div>
<!-- Need Help Modal -->
<div class="modal fade" id="needHelpModal" tabindex="-1" aria-labelledby="needHelpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border:none;border-radius:12px;overflow:hidden;">
            <div class="modal-header" style="background:#fff;border-bottom:1px solid #eee;padding:20px 24px;">
                <div style="border-left:4px solid #DC3545;padding-left:12px;">
                    <h5 class="modal-title fw-bold mb-0" id="needHelpModalLabel" style="color:#DC3545;">Need Help?</h5>
                    <small class="text-muted">We're here to assist you</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" style="padding:24px;">
                <p class="text-muted small mb-3">Share your details below and our admissions experts will get in touch with you to guide you personally.</p>
                <form id="needHelpForm">
                    <!-- Honeypot field for spam protection -->
                    <input type="text" name="website_url" style="display:none;" tabindex="-1" autocomplete="off">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Parent's Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="parent_name" required maxlength="100" placeholder="Enter your full name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Mobile Number <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text" style="background:#f8f9fa;font-weight:600;">+91</span>
                            <input type="tel" class="form-control" name="mobile" required maxlength="10" pattern="[0-9]{10}" placeholder="Enter 10 digit mobile number">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Email Address</label>
                        <input type="email" class="form-control" name="email" maxlength="255" placeholder="Enter your email (optional)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Your Message / Query</label>
                        <textarea class="form-control" name="message" rows="3" maxlength="500" placeholder="How can we help you?"></textarea>
                    </div>
                    <button type="submit" class="btn w-100 fw-semibold" style="background:#DC3545;color:#fff;padding:12px;border-radius:8px;" id="needHelpSubmitBtn">
                        <i class="bi bi-telephone-fill me-2"></i>Request a Call Back
                    </button>
                </form>
                <div id="needHelpSuccess" class="text-center py-4" style="display:none;">
                    <div style="width:64px;height:64px;background:#d4edda;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;margin-bottom:16px;">
                        <i class="bi bi-check-lg" style="font-size:2rem;color:#28a745;"></i>
                    </div>
                    <h5 class="fw-bold text-success">Thank You!</h5>
                    <p class="text-muted small">We'll contact you soon to assist you.</p>
                </div>
                <p class="text-center text-muted small mt-3 mb-0"><i class="bi bi-shield-check me-1"></i>Our admissions team will contact you shortly.</p>
            </div>
        </div>
    </div>
</div>
<!-- Floating Elements CSS -->
<style>
/* WhatsApp Floating Button */
.wa-float-btn {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    background: #25D366;
    color: #fff;
    border-radius: 50px;
    padding: 12px 20px;
    display: flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
    font-weight: 600;
    font-size: 0.95rem;
    box-shadow: 0 4px 15px rgba(37,211,102,0.4);
    transition: transform 0.2s, box-shadow 0.2s;
    animation: waPulse 2s infinite;
}
.wa-float-btn:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(37,211,102,0.5);
    color: #fff;
}
.wa-float-btn i { font-size: 1.4rem; }
@keyframes waPulse {
    0%, 100% { box-shadow: 0 4px 15px rgba(37,211,102,0.4); }
    50% { box-shadow: 0 4px 25px rgba(37,211,102,0.7); }
}
@media (max-width: 576px) {
    .wa-float-btn {
        width: 52px;
        height: 52px;
        padding: 0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        bottom: 16px;
        right: 16px;
    }
    .wa-float-text { display: none; }
    .wa-float-btn i { font-size: 1.5rem; }
}
/* Need Help Sidebar Tab */
.need-help-tab {
    position: fixed;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    z-index: 9998;
    background: #DC3545;
    color: #fff;
    writing-mode: vertical-rl;
    text-orientation: mixed;
    padding: 14px 10px;
    border-radius: 8px 0 0 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 6px;
    box-shadow: -2px 0 10px rgba(220,53,69,0.3);
    transition: padding-right 0.2s, background 0.2s;
}
.need-help-tab:hover {
    padding-right: 14px;
    background: #c82333;
}
.need-help-tab i { font-size: 1rem; }
@media (max-width: 576px) {
    .need-help-tab {
        padding: 10px 7px;
        font-size: 0.75rem;
    }
}
</style>
<!-- Need Help Form JS -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manual click handler for Need Help tab (bypasses data-bs-toggle dependency)
    var tab = document.querySelector('.need-help-tab');
    var modalEl = document.getElementById('needHelpModal');
    if (tab && modalEl) {
        tab.removeAttribute('data-bs-toggle');
        tab.removeAttribute('data-bs-target');
        tab.addEventListener('click', function() {
            if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modal.show();
            } else {
                // Vanilla fallback if Bootstrap JS failed to load
                modalEl.classList.add('show');
                modalEl.style.display = 'block';
                document.body.classList.add('modal-open');
                var backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                backdrop.id = 'needHelpBackdrop';
                document.body.appendChild(backdrop);
                var closeBtn = modalEl.querySelector('.btn-close');
                if (closeBtn) {
                    closeBtn.addEventListener('click', function() {
                        modalEl.classList.remove('show');
                        modalEl.style.display = 'none';
                        document.body.classList.remove('modal-open');
                        var bd = document.getElementById('needHelpBackdrop');
                        if (bd) bd.remove();
                    }, { once: true });
                }
            }
        });
    }

    const form = document.getElementById('needHelpForm');
    const successDiv = document.getElementById('needHelpSuccess');
    const submitBtn = document.getElementById('needHelpSubmitBtn');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const fd = new FormData(form);
        const name = (fd.get('parent_name') || '').toString().trim();
        const mobile = (fd.get('mobile') || '').toString().trim();
        const email = (fd.get('email') || '').toString().trim();
        const message = (fd.get('message') || '').toString().trim();
        if (!name || !mobile || !/^[0-9]{10}$/.test(mobile)) {
            alert('Please enter your name and a valid 10-digit mobile number.');
            return;
        }
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            alert('Please enter a valid email address.');
            return;
        }
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
        const body = new URLSearchParams();
        body.append('parent_name', name);
        body.append('mobile', '91' + mobile);
        body.append('email', email);
        body.append('message', message);
        body.append('source', 'need_help_popup');
        fetch('/public/ajax/enquiry-submit.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(r => r.json())
        .then(data => {
            form.style.display = 'none';
            successDiv.style.display = 'block';
            setTimeout(() => {
                const modal = bootstrap.Modal.getInstance(document.getElementById('needHelpModal'));
                if (modal) modal.hide();
                setTimeout(() => {
                    form.reset();
                    form.style.display = 'block';
                    successDiv.style.display = 'none';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-telephone-fill me-2"></i>Request a Call Back';
                }, 500);
            }, 2500);
        })
        .catch(() => {
            alert('Something went wrong. Please try again.');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-telephone-fill me-2"></i>Request a Call Back';
        });
    });
});
</script>