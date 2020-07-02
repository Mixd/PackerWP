<?php
define('DB_NAME', '<<< DATABASE NAME >>>');
define('DB_USER', '<<< DATABASE USER >>>');
define('DB_PASSWORD', '<<< DATABASE PWD >>>');
define('DB_HOST', '<<< DATABASE HOST >>>');
define('DB_CHARSET', 'utf8');
define('DB_COLLATE', '');
define('WPLANG', '');
define('WP_DEBUG', true);

$table_prefix = 'wp_';

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases or use wp config shuffle-salts
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         '<<< REPLACE ME >>>');
define('SECURE_AUTH_KEY',  '<<< REPLACE ME >>>');
define('LOGGED_IN_KEY',    '<<< REPLACE ME >>>');
define('NONCE_KEY',        '<<< REPLACE ME >>>');
define('AUTH_SALT',        '<<< REPLACE ME >>>');
define('SECURE_AUTH_SALT', '<<< REPLACE ME >>>');
define('LOGGED_IN_SALT',   '<<< REPLACE ME >>>');
define('NONCE_SALT',       '<<< REPLACE ME >>>');
/**#@-*/

define('WP_HOME', '<<< WP SITE URL >>>');
define('WP_SITEURL', '<<< WP SITE URL >>>/wordpress');

define('WP_CONTENT_URL', '<<< WP SITE URL >>>/content');
define('WP_CONTENT_DIR', realpath($_SERVER['DOCUMENT_ROOT'] . '/content'));

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}

/**
 * Disable WordPress auto updates
 * WordPress Codex: https://codex.wordpress.org/Configuring_Automatic_Background_Updates
 */
if (!defined('AUTOMATIC_UPDATER_DISABLED')) {
    define('AUTOMATIC_UPDATER_DISABLED', true);
}

// Handle reverse proxy, passing the IP to the server.
// This is used by some plugins to fetch the user's IP.
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $_SERVER['REMOTE_ADDR'] = $ips[0];
}

require_once(ABSPATH . 'wp-settings.php');
