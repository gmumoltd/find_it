<?php
// =====================================================================
// Site-wide settings.
// Change SITE_NAME here and it updates everywhere on the site.
// =====================================================================

define('SITE_NAME', 'FindPoint');
define('SITE_TAGLINE', 'Lost something? Found something? Let\'s reconnect.');

// Where item photos and profile photos are stored, relative to the
// project root. These folders must be writable by the web server.
define('ITEM_UPLOAD_DIR', __DIR__ . '/../uploads/items/');
define('PROFILE_UPLOAD_DIR', __DIR__ . '/../uploads/profiles/');

// Upload rules
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_IMAGE_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);
