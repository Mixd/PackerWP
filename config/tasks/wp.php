<?php

namespace Deployer;

use Exception;

/**
 * WordPress setup check
 *
 * Determine if WordPress is already installed
 */
task('setup:wp:check', function () {
    if (test('[ -d {{deploy_path}}/current ]')) {
        within('{{release_path}}', function () {
            $stage = get('stage');
            if (
                test('{{bin/wp}} core is-installed') or
                test('{{bin/wp}} core is-installed --network')
            ) {
                throw new Exception(
                    "WordPress is already installed.\nRun 'dep reset $stage' to reset your installation"
                );
            }
        });
    }
})->setPrivate();

/**
 * Setup WordPress (remote)
 *
 * Install WordPress on a remote environment
 */
task('setup:wp:remote', function () {
    $config = getconfig();
    $stage = get('stage', 'local');
    $params = getenvbag($stage);

    $wp_user = $config['wp_user'];
    $wp_pwd = wp_password_create();
    $wp_email = $config['wp_email'];
    $domain = $params['wp_home_url'];

    within('{{release_path}}', function () use (
        $params,
        $config,
        $wp_user,
        $wp_pwd,
        $wp_email,
        $domain
    ) {
        // Create a wp-config.php
        wp_config_create(
            $domain,
            $params['db_name'],
            $params['db_user'],
            $params['db_password'],
            $params['db_host']
        );

        // Run the install
        wp_core_install(
            $config['is_multisite'],
            $domain,
            $config['wp_sitename'],
            $wp_user,
            $wp_pwd,
            $wp_email
        );

        // Run the cleanup
        wp_after_install($domain);
    });

    // Print results
    wp_print_finish($wp_user, $wp_pwd, $wp_email, $domain);
})->setPrivate();

/**
 * Setup WordPress (local)
 *
 * Install WordPress on a local environment
 */
task('setup-local-wp', function () {
    if (
        test('{{bin/wp}} core is-installed') or
        test('{{bin/wp}} core is-installed --network')
    ) {
        throw new Exception(
            "WordPress is already installed.\nRun 'dep reset' to reset your installation"
        );
    }

    $wp_config = getconfig();
    $stage = get('stage', 'local');
    $env_config = getenvbag($stage);

    $domain = $wp_config['wp_home_url'];
    $wp_user = $wp_config['wp_user'];
    $wp_email = $wp_config['wp_email'];

    $wp_pwd = wp_password_create();

    // Create a wp-config.php
    wp_config_create(
        $domain,
        $env_config['db_name'],
        $env_config['db_user'],
        $env_config['db_password'],
        $env_config['db_host']
    );

    // Run the install
    wp_core_install(
        $wp_config['is_multisite'],
        $domain,
        $wp_config['wp_sitename'],
        $wp_user,
        $wp_pwd,
        $wp_email
    );

    // Run the cleanup
    wp_after_install($domain);

    // Print results
    wp_print_finish($wp_user, $wp_pwd, $wp_email, $domain);
})
    ->desc('Set up your project locally')
    ->local();

/**
 * Setup Shared Files
 *
 * Set up a .htaccess and a robots.txt for your target environment
 */
task('copy:templates', function () {
    $files = get('templates');

    $stage = get('stage', 'local');

    $root = $stage == 'local' ? '.' : get('release_path');

    if (!empty($files)) {
        foreach ($files as $filename) {
            $src = get('abspath') . 'vendor/mixd/packerwp/config/templates/' . $stage . '/' . $filename;
            $dest = $root . '/' . $filename;
            upload($src, $dest);
        }
    }
})->desc('Set the robots.txt and .htaccess files');

/**
 * Reset Administrator Password
 *
 * Reset the WordPress Administrator password and shuffles the WordPress salts
 */
task('reset-admin-pwd', function () {
    $config = getconfig();
    $wp_user = $config['wp_user'];

    $wp_pwd = wp_password_create();
    $stage = get('stage', 'local');

    if (get('allow_input')) {
        $confirm = askConfirmation(
            "
        Are you sure you wish to reset the password for '" .
                $wp_user .
                "'?",
            false
        );
    } else {
        $confirm = true;
    }

    if ($confirm !== true) {
        writeln("<error>
    ========================================================================
        You did not want to continue so your task was aborted
    ========================================================================</error>
        ");
        exit();
    }

    $update_cmd =
        '
        {{bin/wp}} user update "' .
        $wp_user .
        '" --skip-email --user_pass="' .
        $wp_pwd .
        '" && \
        {{bin/wp}} config shuffle-salts
    ';
    if ($stage == 'local') {
        runLocally($update_cmd);
    } else {
        cd('{{release_path}}');
        run($update_cmd);
    }

    wp_print_finish($wp_user, $wp_pwd);
})->desc('Reset the super admin password on the target environment');

/**
 * 'Pull' Helper
 *
 * Pull both WordPress uploads and a database from a given host
 */
task('pull', ['pull-remote-db', 'pull-remote-uploads'])->desc(
    'Pull both WordPress uploads and a database from a given host'
);

/**
 * Reset
 *
 * Empty your database and optionally reset your wp-config.php
 *
 * Note: You cannot reset an installation when using Continuous Integration
 */
task('reset', function () {
    if (get('allow_input') == false) {
        throw new Exception("You cannot reset an installation using non-interactive mode");
    }

    $wp_config = getconfig();
    $stage = get('stage', 'local');
    $env_config = getenvbag($stage);

    if ($stage == 'local') {
        $domain = $wp_config['wp_home_url'];
    } else {
        $domain = $env_config['wp_home_url'];
    }

    if (get('stage', 'local') == 'local') {
        $abs = get('abspath');
    } else {
        $abs = get('release_path') . '/';
    }

    write("<error>
    ========================================================================
        WARNING: You're about to reset your database and installation
    ========================================================================</error>
    ");

    $confirm = askConfirmation(
        "
    Are you sure you wish to reset $domain?",
        false
    );
    if ($confirm == true) {
        $cmd = '{{bin/wp}} db reset --yes';
        if ($stage == 'local') {
            if (
                test('{{bin/wp}} core is-installed') or
                test('{{bin/wp}} core is-installed --network')
            ) {
                runLocally($cmd);
            }
        } else {
            if (test('[ -d {{current_path}} ]')) {
                within('{{current_path}}', function () use ($cmd) {
                    if (
                        test('{{bin/wp}} core is-installed') or
                        test('{{bin/wp}} core is-installed --network')
                    ) {
                        run($cmd);
                    }
                });
                run('rm -rfv {{current_path}}', ['tty' => get('allow_input')]);
                run('rm -rfv {{deploy_path}}/current', ['tty' => get('allow_input')]);
            }
        }
    }
})->desc('Reset the WordPress database and installation');

/**
 * Create a wp-config.php file in the root of the site
 *
 * @param string $domain WordPress Site URL
 * @param string $db_name WordPress Database Name
 * @param string $db_username WordPress Database Username
 * @param string $db_password WordPress Database Password
 * @param string $db_host WordPress Database Hostname
 * @param string $locale WordPress Site Locale (default: en_GB)
 * @return string
 */
function wp_config_create(
    string $domain,
    string $db_name,
    string $db_username,
    string $db_password,
    string $db_host,
    string $locale = 'en_GB'
) {
    $stage = get('stage', 'local');
    if ($stage == 'local') {
        $project_root = get('abspath');
        $config_root = realpath(dirname(__DIR__));
    } else {
        $project_root = get('release_path') . '/';
        $config_root = $project_root . 'vendor/mixd/packerwp/config';
    }

    writeln('');
    writeln('<comment>Creating wp-config.php</comment>');

    $path_to_generated_wpconfig = $project_root . 'wordpress/wp-config.php';

    // Create the wp-config.php file
    $cmd = "{{bin/wp}} config create --dbname=\"$db_name\" \
    --dbuser=\"$db_username\" \
    --dbpass=\"$db_password\" \
    --dbhost=\"$db_host\" \
    --locale=\"$locale\" \
    --skip-check \
    --force";

    $extras = $config_root . '/templates/extras.php';

    if ($stage == 'local') {
        $extras_exists = testLocally("[ -f $extras ]");
    } else {
        $extras_exists = test("[ -f $extras ]");
    }

    if ($extras_exists) {
        $cmd =
            "tail -n+2 '$extras' | " .
            ($cmd .= ' \
        --extra-php');
    }

    $result = run($cmd, ['tty' => get('allow_input')]);

    // Run a search-replace with the necessary values
    searchreplaceinfile($path_to_generated_wpconfig, '!!site_url!!', $domain);
    searchreplaceinfile(
        $path_to_generated_wpconfig,
        '!!debug!!',
        $params['wp_debug'] ?? 'false'
    );
    searchreplaceinfile($path_to_generated_wpconfig, '!!stage!!', $stage);

    return $result;
}

/**
 * Runs the WordPress Core Install task
 *
 * @param bool $is_multisite Is this a WordPress Multisite installation?
 * @param string $domain WordPress Site URL
 * @param string $wp_sitename WordPress Site Name
 * @param string $wp_user WordPress Administrator Username
 * @param string $wp_pwd WordPress Administrator Password
 * @param string $wp_email WordPress Administrator Email
 * @return string
 */
function wp_core_install(
    bool $is_multisite,
    string $domain,
    string $wp_sitename,
    string $wp_user,
    string $wp_pwd,
    string $wp_email
) {
    writeln('');
    if ($is_multisite) {
        writeln('<comment>Installing WordPress Multisite</comment>');
        $result = run(
            "{{bin/wp}} core multisite-install --url=\"$domain\" \
            --title=\"$wp_sitename\" \
            --admin_user=\"$wp_user\" \
            --admin_password=\"$wp_pwd\" \
            --admin_email=\"$wp_email\" \
            --skip-email \
            --subdomains",
            ['tty' => get('allow_input')]
        );
        // Write notice about multisite
        writeln('');
        writeln("<comment>Notice:</comment> In order for WordPress multisite to work when core is installed in a
        subdirectory you should install the following 'Must-Use' plugin:
        - WPMS Site URL Fixer
          https://github.com/felixarntz/multisite-fixes/blob/master/mu-plugins/wpms-site-url-fixer.php");
    } else {
        writeln('<comment>Installing WordPress</comment>');
        $result = run(
            "{{bin/wp}} core install --url=\"$domain\" \
            --title=\"$wp_sitename\" \
            --admin_user=\"$wp_user\" \
            --admin_password=\"$wp_pwd\" \
            --admin_email=\"$wp_email\" \
            --skip-email",
            ['tty' => get('allow_input')]
        );
    }

    run('{{bin/wp}} site empty --yes');

    return $result;
}

/**
 * Sets the correct WordPress options and moves the wp-config.php into the root of the site
 *
 * @param string $domain WordPress Site URL
 * @return void
 */
function wp_after_install(string $domain)
{
    if (get('stage', 'local') == 'local') {
        $project_root = get('abspath');
    } else {
        $project_root = get('release_path') . '/';
    }

    run("{{bin/wp}} option update home $domain");
    run("{{bin/wp}} option update siteurl $domain/wordpress");

    $path_to_generated_wpconfig = $project_root . 'wordpress/wp-config.php';
    $path_to_wpconfig = $project_root . 'wp-config.php';

    run("mv '{$path_to_generated_wpconfig}' '{$path_to_wpconfig}'");
}

/**
 * Print a summary of the installation
 *
 * @param string $wp_user WordPress Administrator Username
 * @param string $wp_pwd WordPress Administrator User
 * @param string $wp_email WordPress Administrator User
 * @param string $domain WordPress Site URL
 * @return void
 */
function wp_print_finish(
    string $wp_user,
    string $wp_pwd,
    string $wp_email = '',
    string $domain = ''
) {
    $txt = "
<comment>Here are your login details:</comment>
    Username:       <info>$wp_user</info>
    Password:       <info>$wp_pwd</info>";

    if (!empty($wp_email)) {
        $txt .= "\n    Email address:  <info>$wp_email</info>";
    }
    if (!empty($domain)) {
        $txt .= "\n    Log in at:      <info>$domain/wordpress/wp-admin</info>";
    }

    writeln($txt);
    writeln('');
}

/**
 * Create a random password
 *
 * @param integer $length Number of random bytes to use
 * @return string
 */
function wp_password_create(int $length = 24)
{
    return bin2hex(openssl_random_pseudo_bytes($length));
}

task('setup-wp', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'setup:wp:remote',
    'deploy:shared',
    'deploy:writable',
    'deploy:clear_paths',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup'
])->desc('Set up your project on the remote host');

/**
 * Prevents an accidental re-run of the setup task
 */
before('setup-wp', 'setup:wp:check');

/**
 * Ensure .htaccess and robots.txt are copied
 */
before('deploy:shared', 'copy:templates');
after('setup-local-wp', 'copy:templates');
