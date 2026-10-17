<?php
declare(strict_types=1);

/**
 * WABYU application settings. These are NOT secrets (passwords live in config/secrets.php).
 */
define('WABYU_ROOT', dirname(__DIR__));
define('WABYU_DATA_DIR', WABYU_ROOT . '/data');
define('WABYU_UPLOAD_DIR', WABYU_ROOT . '/uploads/memories');
define('WABYU_UPLOAD_REL', 'uploads/memories');
define('WABYU_BACKUP_DIR', WABYU_ROOT . '/database-backups');
define('WABYU_LOG_FILE', WABYU_DATA_DIR . '/error.log');

const WABYU_MAX_IMAGE_BYTES = 15 * 1024 * 1024;           // 15 MB per photo
const WABYU_ALLOWED_MIME    = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const WABYU_RATE_MAX        = 6;                           // failed attempts allowed ...
const WABYU_RATE_WINDOW     = 900;                         // ... per 15 minutes, per IP
const WABYU_SESSION_IDLE    = 43200;                       // log out after 12 h of inactivity
const WABYU_BACKUP_KEEP     = 14;                          // backups kept by backup.php

// Editable gallery copy (shown after the introduction).
const WABYU_HERO_TITLE = 'Every moment has a place here.';
const WABYU_HERO_SUB   = 'A little space for the pictures, words, and memories worth keeping.';
