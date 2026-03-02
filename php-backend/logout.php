<?php
require_once __DIR__ . '/includes/auth.php';
auditLog('logout', 'user', currentUserId());
session_destroy();
header('Location: /login.php');
exit;
