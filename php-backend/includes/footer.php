<?php
/**
 * Admin Panel Footer
 * Minimal footer for admin pages — no public CTA, social links, or navigation.
 */
$_footerSchoolName = $schoolName ?? getSetting('school_name', 'JNV School');
?>

    </div><!-- /.main-content -->

    <!-- Admin Footer -->
    <footer class="admin-footer">
        <div class="container-fluid">
            <div class="d-flex justify-content-end align-items-center gap-3 flex-wrap text-end">
                <small>
                    &copy; <?= date('Y') ?> <?= htmlspecialchars($_footerSchoolName) ?> — Admin Panel
                </small>
                <small class="text-muted">
                    JSchool Management System v3.4 |
                    <a href="https://jnvtech.in" target="_blank" class="admin-footer-link">
                        jnvtech.in
                    </a>
                </small>
            </div>
        </div>
    </footer>

    <style>
    .admin-footer {
        position: fixed;
        bottom: 0;
        right: 0;
        left: 0;
        margin-left: calc(var(--sidebar-width, 250px) + var(--sidebar-margin, 0px) * 2);
        padding: 0.75rem 1.5rem;
        border-top: 1px solid var(--border-color, #e5e7eb);
        background: var(--bg-card, #fff);
        color: var(--text-muted, #6b7280);
        font-size: 0.8rem;
        z-index: 999;
        transition: margin-left 0.3s cubic-bezier(.4,0,.2,1);
    }

    .sidebar.collapsed ~ .main-content ~ .admin-footer,
    html.sidebar-is-collapsed .admin-footer {
        margin-left: calc(var(--sidebar-collapsed-width, 70px) + var(--sidebar-margin, 0px) * 2);
    }

    .main-content {
        padding-bottom: 60px;
    }

    @media (max-width: 991.98px) {
        .admin-footer {
            margin-left: 0;
        }
    }

    .admin-footer-link {
        color: var(--text-muted, #6b7280);
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .admin-footer-link:hover {
        color: #2563eb;
        text-decoration: underline;
    }
    </style>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>