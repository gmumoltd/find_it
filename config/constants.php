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

define('MPESA_ENVIRONMENT', 'sandbox'); // 'sandbox' or 'production'
define('MPESA_CALLBACK_URL', 'https://your-public-domain.ngrok-free.app/mpesa-callback.php'); // Change this to your public callback URL for M-Pesa sandbox/live
define('MPESA_CONSUMER_KEY', '83R8ewAaFkcoUQzmu6Rb1bMxmAujK1h5Jk4Nhu5McMawnCZh'); // Replace with your Daraja Consumer Key
define('MPESA_CONSUMER_SECRET', 'FSOyjgLPMiAksUPpPTaRscOPr20Vtt4NyzUx5wjQnwONznv5psTIsWcgevl4R28A'); // Replace with your Daraja Consumer Secret
define('MPESA_BUSINESS_SHORTCODE', '174379'); // Sandbox Paybill
define('MPESA_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'); // Sandbox Passkey
