<?php

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// START custom wp-config values
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Define some immutable paths
 */
define('WP_HOME', '!!site_url!!');
define('WP_SITEURL', '!!site_url!!/wordpress');
define('WP_CONTENT_URL', '!!site_url!!/content');
define(
    'WP_CONTENT_DIR',
    str_replace('wordpress', '', realpath($_SERVER['DOCUMENT_ROOT'])) .
        '/content'
);
define('WP_DEBUG', !!debug!!);

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

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// END custom wp-config values
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
