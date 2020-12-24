<?php

namespace Deployer;

use Exception;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// WordPress setup related tasks
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

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
task('setup:wp:check', function () {
    if (test('[ -d {{deploy_path}}/current ]')) {
        within('{{release_path}}', function () {
            $stage = get('stage');
            if (test("{{bin/wp}} core is-installed") or test("{{bin/wp}} core is-installed --network")) {
                throw new Exception("WordPress is already installed.\nRun 'dep reset $stage' to reset your installation");
            }
        });
    }
})->setPrivate();

/**
 * Install WordPress on a target environment
 */
task('setup:wp:remote', function () {
    $config = getconfig();
    $stage = get('stage', 'local');
    $params = getenvbag($stage);

    $wp_user = $config["wp-user"];
    $wp_pwd = wp_password_create();
    $wp_email = $config["wp-email"];
    $domain = $params["url"];

    within('{{release_path}}', function () use ($params, $config, $wp_user, $wp_pwd, $wp_email, $domain) {
        // Create a wp-config.php
        wp_config_create(
            $domain,
            $params["db-name"],
            $params["db-user"],
            str_replace("#", "\#", $params["db-password"]),
            $params["db-host"]
        );

        // Run the install
        wp_core_install(
            $config["is-multisite"],
            $domain,
            $config["wp-sitename"],
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
before('setup:wp:remote', 'composer-install');

/**
 * Install WordPress on a local environment
 */
task('setup-local-wp', function () {
    if (test("{{bin/wp}} core is-installed") or test("{{bin/wp}} core is-installed --network")) {
        throw new Exception("WordPress is already installed.\nRun 'dep reset' to reset your installation");
    }

    $config = getconfig();
    $stage = get('stage', 'local');
    $params = getenvbag($stage);

    $domain = $config["wp-local-url"];
    $wp_user = $config["wp-user"];
    $wp_email = $config["wp-email"];

    $wp_pwd = wp_password_create();

    // Create a wp-config.php
    wp_config_create(
        $domain,
        $params["db-name"],
        $params["db-user"],
        str_replace("#", "\#", $params["db-password"]),
        $params["db-host"]
    );

    // Run the install
    wp_core_install(
        $config["is-multisite"],
        $domain,
        $config["wp-sitename"],
        $wp_user,
        $wp_pwd,
        $wp_email
    );

    // Run the cleanup
    wp_after_install($domain);

    // Print results
    wp_print_finish(
        $wp_user,
        $wp_pwd,
        $wp_email,
        $domain
    );

})->desc('Set up your project locally')->local();

/**
 * Reset the WordPress Administrator password and shuffle the WordPress salts
 */
task('reset-admin-pwd', function () {
    $config = getconfig();
    $wp_user = $config["wp-user"];

    $wp_pwd = wp_password_create();
    $stage = get('stage', 'local');
    $confirm = askConfirmation("
    Are you sure you wish to reset the password for '" . $wp_user . "'?",
        false
    );
    if ($confirm !== true) {
        writeln("<error>
    ========================================================================
        You did not want to continue so your task was aborted
    ========================================================================</error>
        ");
        exit;
    }

    $update_cmd = '
        {{bin/wp}} user update "' . $wp_user . '" --skip-email --user_pass="' . $wp_pwd . '" && \
        {{bin/wp}} config shuffle-salts
    ';
    if ($stage == "local") {
        runLocally($update_cmd);
    } else {
        cd("{{release_path}}");
        run($update_cmd);
    }

    wp_print_finish($wp_user, $wp_pwd);

})->desc('Reset the super admin password on the target environment');

/**
 * Pull both WordPress uploads and a database from a given host
 */
task('pull', [
    'pull-remote-db',
    'pull-remote-uploads'
])->desc('Pull both WordPress uploads and a database from a given host');

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
function wp_config_create(string $domain, string $db_name, string $db_username, string $db_password, string $db_host, string $locale = "en_GB")
{
    writeln("");
    writeln("<comment>Creating wp-config.php...</comment>");

    $path_to_generated_wpconfig = "./wordpress/wp-config.php";

    // Create the wp-config.php file
    $extras = './config/templates/extras.php';
    $cmd = "{{bin/wp}} config create --dbname=$db_name \
        --dbuser=$db_username \
        --dbpass=$db_password \
        --dbhost=$db_host \
        --locale=$locale \
        --force \
        --extra-php
    ";
    if (file_exists($extras)) {
        $cmd = "tail -n+2 '$extras' | " . $cmd;
    }
    $result = run($cmd, ['tty' => true]);

    // Run a search-replace with the necessary values
    searchreplaceinfile($path_to_generated_wpconfig, "!!site_url!!", $domain);
    searchreplaceinfile($path_to_generated_wpconfig, "!!debug!!", $params["wp-debug"] ?? 'false');

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
function wp_core_install(bool $is_multisite, string $domain, string $wp_sitename, string $wp_user, string $wp_pwd, string $wp_email)
{
    writeln("");
    if ($is_multisite) {
        writeln("<comment>Installing WordPress Multisite</comment>");
        $result = run('{{bin/wp}} core multisite-install --url="' . $domain . '" \
            --title="' . $wp_sitename . '" \
            --admin_user="' . $wp_user . '" \
            --admin_password="' . $wp_pwd . '" \
            --admin_email="' . $wp_email . '" \
            --skip-email \
            --subdomains', ['tty' => true]);
        // Write notice about multisite
        writeln("");
        writeln("<comment>Notice:</comment> In order for WordPress multisite to work when core is installed in a
        subdirectory you should install the following 'Must-Use' plugin:
        - WPMS Site URL Fixer
          https://github.com/felixarntz/multisite-fixes/blob/master/mu-plugins/wpms-site-url-fixer.php");
    } else {
        writeln("<comment>Installing WordPress</comment>");
        $result = run('{{bin/wp}} core install --url="' . $domain . '" \
            --title="' . $wp_sitename . '" \
            --admin_user="' . $wp_user . '" \
            --admin_password="' . $wp_pwd . '" \
            --admin_email="' . $wp_email . '" \
            --skip-email
        ', ['tty' => true]);
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
    run("{{bin/wp}} option update home $domain");
    run("{{bin/wp}} option update siteurl $domain/wordpress");

    $path_to_generated_wpconfig = "./wordpress/wp-config.php";
    $path_to_wpconfig = "./wp-config.php";

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
function wp_print_finish(string $wp_user, string $wp_pwd, string $wp_email = "", string $domain = "")
{
    writeln("");
    write("<comment>Here are your login details:</comment>
    Username:       <info>" . $wp_user . "</info>
    Password:       <info>" . $wp_pwd . "</info>");
    if (!empty($wp_email)) {
        write("Email address:       <info>" . $wp_email . "</info>");
    }
    if (!empty($domain)) {
        write("Log in at:       <info>" . $domain . "/wordpress/wp-admin</info>");
    }
    writeln("");
}

/**
 * Create a random password
 *
 * @param integer $length Number of random bytes to use
 * @return string
 */
function wp_password_create(int $length = 24)
{
    return bin2hex(
        openssl_random_pseudo_bytes($length)
    );
}
