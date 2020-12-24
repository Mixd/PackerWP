<?php

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// START custom wp-config values
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Define some immutable paths
 */
define('WP_HOME', '<<< WP SITE URL >>>');
define('WP_SITEURL', '<<< WP SITE URL >>>/wordpress');
define('WP_CONTENT_URL', '<<< WP SITE URL >>>/content');
define('WP_CONTENT_DIR', str_replace('wordpress', '', realpath(dirname(__FILE__))) . 'content');
define('WP_DEBUG', <<< WP DEBUG >>>);

/**
 * Handle reverse proxy, passing the IP to the server.
 * This is used by some plugins to fetch the user's IP.
 */
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $_SERVER['REMOTE_ADDR'] = $ips[0];
}

/**
 * Disable WordPress auto updates
 * WordPress Codex: https://codex.wordpress.org/Configuring_Automatic_Background_Updates
 */
if (!defined('AUTOMATIC_UPDATER_DISABLED')) {
    define('AUTOMATIC_UPDATER_DISABLED', true);
}

/**
 * Disable the Plugin and Theme Editor
 *
 * Occasionally you may wish to disable the plugin or theme editor to prevent overzealous users from being able to edit
 * sensitive files and potentially crash the site. Disabling these also provides an additional layer of security if a
 * hacker gains access to a well-privileged user account.
 */
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', true);

/**
 * Used by the wpms-site-url-fixer.php MU Plugin to help rewrite Multisite URLs in wp-admin
 * when WordPress is in a subdirectory
 * @see https://github.com/felixarntz/multisite-fixes/blob/master/mu-plugins/wpms-site-url-fixer.php
 */
define('WP_CORE_DIRECTORY', 'wordpress');

/**
 * A series of pathing fixes for WordPress
 */
define('COOKIE_DOMAIN', "");
define('COOKIEPATH', preg_replace('|https?://[^/]+|i', '', WP_HOME . '/'));
define('SITECOOKIEPATH', preg_replace('|https?://[^/]+|i', '', WP_SITEURL . '/'));
define('ADMIN_COOKIE_PATH', SITECOOKIEPATH . 'wp-admin');
define('PLUGINS_COOKIE_PATH', preg_replace('|https?://[^/]+|i', '', WP_CONTENT_URL . '/plugins/'));

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// END custom wp-config values
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
