<?php
/**
 * Public entry point for the Join Us / Careers page.
 * Accessible at: jschool.jnvweb.in/join-us.php
 * 
 * This file includes the admin/join-us.php page directly,
 * which does NOT require admin authentication.
 */

// Set the base path so included files resolve correctly
chdir(__DIR__ . '/admin');

require_once __DIR__ . '/admin/join-us.php';