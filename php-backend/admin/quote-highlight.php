<?php
$pageTitle = 'Quote Highlight';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$db = getDB();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $quoteText = trim($_POST['quote_text'] ?? '');
    $authorName = trim($_POST['author_name'] ?? '');

    if (!$quoteText) {
        setFlash('error', 'Quote text cannot be empty.');
    } else {
        // Check if a quote already exists
        $existing = $db->query("SELECT id FROM site_quotes WHERE is_active=1 ORDER BY id DESC LIMIT 1")->fetch();
        if ($existing) {
            $db->prepare("UPDATE site_quotes SET quote_text=?, author_name=?, updated_by=?, updated_at=NOW() WHERE id=?")
               ->execute([$quoteText, $authorName ?: null, currentUserId(), $existing['id']]);
        } else {
            $db->prepare("INSERT INTO site_quotes (quote_text, author_name, updated_by) VALUES (?, ?, ?)")
               ->execute([$quoteText, $authorName ?: null, currentUserId()]);
        }
        auditLog('update_quote', 'site_quotes');
        setFlash('success', 'Quote updated successfully.');
    }
    header('Location: /admin/quote-highlight.php');
    exit;
}

// Load current quote
$quote = null;
try {
    $quote = $db->query("SELECT q.*, u.name as updater_name FROM site_quotes q LEFT JOIN users u ON q.updated_by=u.id WHERE q.is_active=1 ORDER BY q.id DESC LIMIT 1")->fetch();
} catch (Exception $e) {
    // Table may not exist yet
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <!-- Editor -->
    <div class="col-lg-6">
        <div class="card border-0 rounded-3">
            <div class="card-header bg-white border-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-quote me-2"></i>Quote Highlight Manager</h6>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3" style="font-size:.82rem">This inspirational quote appears as a highlighted banner on the public About Us page between the Core Values and Footer sections.</p>
                <form method="POST" id="quoteForm">
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Quote Message <span class="text-danger">*</span></label>
                        <textarea name="quote_text" id="quoteText" class="form-control" rows="4" placeholder="Enter an inspirational quote..." required><?= e($quote['quote_text'] ?? '') ?></textarea>
                        <small class="text-muted" style="font-size:.7rem">This is the main quote text that will be displayed prominently.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Author Name</label>
                        <input type="text" name="author_name" id="authorName" class="form-control" placeholder="e.g. Nelson Mandela" value="<?= e($quote['author_name'] ?? '') ?>">
                        <small class="text-muted" style="font-size:.7rem">Optional — who said this quote.</small>
                    </div>
                    <?php if ($quote && $quote['updated_at']): ?>
                    <div class="bg-light rounded-3 p-2 mb-3">
                        <small class="text-muted" style="font-size:.72rem">
                            <i class="bi bi-clock me-1"></i>Last updated: <?= date('d M Y, h:i A', strtotime($quote['updated_at'])) ?>
                            <?php if ($quote['updater_name']): ?> by <strong><?= e($quote['updater_name']) ?></strong><?php endif; ?>
                        </small>
                    </div>
                    <?php endif; ?>
                    <button class="btn btn-primary w-100"><i class="bi bi-check-lg me-1"></i>Save Quote</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Live Preview -->
    <div class="col-lg-6">
        <div class="card border-0 rounded-3">
            <div class="card-header bg-white border-0">
                <h6 class="fw-semibold mb-0"><i class="bi bi-eye me-2"></i>Live Preview</h6>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3" style="font-size:.8rem">This is how the quote will appear on the About Us page.</p>
                <div id="quotePreview" style="background:#f8f9fa;border-radius:16px;padding:2.5rem 2rem;text-align:center;border-left:5px solid var(--primary, #1e40af);position:relative;overflow:hidden;">
                    <div style="position:absolute;top:-10px;left:20px;font-size:6rem;color:rgba(30,64,175,0.08);font-family:Georgia,serif;line-height:1;">"</div>
                    <p id="previewQuoteText" style="font-size:1.15rem;font-style:italic;color:#1e293b;line-height:1.7;margin-bottom:1rem;position:relative;z-index:1;font-family:'Playfair Display',Georgia,serif;">
                        <?= e($quote['quote_text'] ?? 'Your inspirational quote will appear here...') ?>
                    </p>
                    <div id="previewAuthor" style="font-size:.9rem;color:#64748b;font-weight:600;">
                        <?php if ($quote && $quote['author_name']): ?>— <?= e($quote['author_name']) ?><?php endif; ?>
                    </div>
                    <div id="previewDate" style="font-size:.7rem;color:#94a3b8;margin-top:.5rem;">
                        <?php if ($quote && $quote['updated_at']): ?>Last updated: <?= date('d M Y, h:i A', strtotime($quote['updated_at'])) ?><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Live preview updates
const quoteText = document.getElementById('quoteText');
const authorName = document.getElementById('authorName');
const previewQuote = document.getElementById('previewQuoteText');
const previewAuthor = document.getElementById('previewAuthor');

if (quoteText) {
    quoteText.addEventListener('input', function() {
        previewQuote.textContent = this.value || 'Your inspirational quote will appear here...';
    });
}
if (authorName) {
    authorName.addEventListener('input', function() {
        previewAuthor.textContent = this.value ? '— ' + this.value : '';
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>