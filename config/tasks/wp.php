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

task('setup:wp:remote', function () {
    cd('{{release_path}}');
    $stage = get('stage');
    $domain = $_ENV[strtoupper($stage) . "_STAGE_URL"];
    $db_host = $_ENV[strtoupper($stage) . "_DB_HOST"];
    $db_name = $_ENV[strtoupper($stage) . "_DB_NAME"];
    $db_username = $_ENV[strtoupper($stage) . "_DB_USER"];
    $db_password = str_replace("#", "\#", $_ENV[strtoupper($stage) . "_DB_PASS"]);
    $wp_user = $_ENV["WP_USER"];
    $wp_email = $_ENV["WP_EMAIL"];
    $wp_sitename = $_ENV["WP_SITENAME"];
    $wp_pwd = bin2hex(openssl_random_pseudo_bytes(16));
    $path_to_wpconfig = "{{release_path}}/wp-config.php";

    if (test("wp core is-installed") or test("wp core is-installed --network")) {
        writeln("<error>WordPress is already installed</error>");
        exit;
    }

    run("cp {{release_path}}/config/templates/{{stage}}/wp-config.example.php " . $path_to_wpconfig);
    run("cp {{release_path}}/config/templates/{{stage}}/.htaccess {{release_path}}/.htaccess");
    run("cp {{release_path}}/config/templates/{{stage}}/robots.txt {{release_path}}/robots.txt");

    // Run a search-replace with the necessary values
    searchreplaceinfile($path_to_wpconfig, "<<< DATABASE NAME >>>", $db_name);
    searchreplaceinfile($path_to_wpconfig, "<<< DATABASE USER >>>", $db_username);
    searchreplaceinfile($path_to_wpconfig, "<<< DATABASE PWD >>>", $db_password);
    searchreplaceinfile($path_to_wpconfig, "<<< DATABASE HOST >>>", $db_host);
    searchreplaceinfile($path_to_wpconfig, "<<< WP SITE URL >>>", $domain);

    run('wp core install --url="' . $domain . '" \
        --title="' . $wp_sitename . '" \
        --admin_user="' . $wp_user . '" \
        --admin_password="' . $wp_pwd . '" \
        --admin_email="' . $wp_email . '" \
        --skip-email
    ');

    // Shuffle the salts
    run("wp config shuffle-salts");

    write("
    \e[32m
    =========================================================================
    WordPress has successfully been installed. Here are your login details:

    Username:       " . $wp_user . "
    Password:       " . $wp_pwd . "
    Email address:  " . $wp_email . "
    Log in at:      " . $domain . "/wordpress/wp-admin
    =========================================================================
    \e[0m
    ");
})->setPrivate();

before('setup:wp:remote', 'composer-install');

task('setup-local-wp', function () {
    if (test("wp core is-installed") or test("wp core is-installed --network")) {
        throw new Exception("WordPress is already installed.\nRun 'dep reset' to reset your installation");
    }

    $abs = get('abspath');
    $path_to_generated_wpconfig = $abs . "/wordpress/wp-config.php";
    $path_to_wpconfig = $abs . "/wp-config.php";

    $config = getconfig();
    $stage = get('stage', 'local');
    $params = getenvbag($stage);

    $db_host = $params["db-host"];
    $db_name = $params["db-name"];
    $db_username = $params["db-user"];
    $db_password = str_replace("#", "\#", $params["db-password"]);

    $domain = $config["wp-local-url"];
    $wp_user = $config["wp-user"];
    $wp_email = $config["wp-email"];
    $wp_sitename = $config["wp-sitename"];
    $wp_pwd = bin2hex(openssl_random_pseudo_bytes(16));

    // Create the wp-config.php file
    $extras = $abs . '/config/templates/extras.php';
    writeln("<comment>Creating wp-config.php...</comment>");
    $cmd = "wp config create --dbname=$db_name \
        --dbuser=$db_username \
        --dbpass=$db_password \
        --dbhost=$db_host \
        --locale=en_GB \
        --force \
        --extra-php
    ";
    if (file_exists($extras)) {
        $cmd = "tail -n+2 '$extras' | " . $cmd;
    }
    run($cmd, ['tty' => true]);

    // Run a search-replace with the necessary values
    searchreplaceinfile($path_to_generated_wpconfig, "<<< WP SITE URL >>>", $domain);
    searchreplaceinfile($path_to_generated_wpconfig, "<<< WP DEBUG >>>", $params["wp-debug"] ?? 'false');

    if ($config["is-multisite"] == true) {
        writeln("<comment>Installing WordPress Multisite</comment>");
        run('wp core multisite-install --url="' . $domain . '" \
            --title="' . $wp_sitename . '" \
            --admin_user="' . $wp_user . '" \
            --admin_password="' . $wp_pwd . '" \
            --admin_email="' . $wp_email . '" \
            --skip-email \
            --subdomains', ['tty' => true]);

        // Write notice about multisite
        writeln("<comment>Notice:</comment> In order for WordPress multisite to work when core is installed in a
        subdirectory you should install the following 'Must-Use' plugin:
        WPMS Site URL Fixer
        https://github.com/felixarntz/multisite-fixes/blob/master/mu-plugins/wpms-site-url-fixer.php");
    } else {
        writeln("<comment>Installing WordPress</comment>");
        run('wp core install --url="' . $domain . '" \
            --title="' . $wp_sitename . '" \
            --admin_user="' . $wp_user . '" \
            --admin_password="' . $wp_pwd . '" \
            --admin_email="' . $wp_email . '" \
            --skip-email
        ', ['tty' => true]);
    }

    run("wp option update home $domain");
    run("wp option update siteurl $domain/wordpress");

    run("mv '{$path_to_generated_wpconfig}' '{$path_to_wpconfig}'");

    writeln("");
    writeln("<comment>Here are your login details:</comment>
    Username:       <info>" . $wp_user . "</info>
    Password:       <info>" . $wp_pwd . "</info>
    Email address:  <info>" . $wp_email . "</info>
    Log in at:      <info>" . $domain . "/wordpress/wp-admin</info>");
})->desc('Set up your project locally')->local();

task('signoff', function () {
    $signoff = "Branch ({{branch}}) deployed by ({{user}}) for release ({{release_name}})";
    cd('{{deploy_path}}');
    run('touch revisions.log');
    run('echo "' . $signoff . '" >> revisions.log');
    writeln("<info>" . $signoff . "</info>");
})->setPrivate();

task('reset-admin-pwd', function () {
    $config = getconfig();
    $wp_user = $config["wp-user"];
    $wp_pwd = bin2hex(openssl_random_pseudo_bytes(16));
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
        wp user update "' . $wp_user . '" --skip-email --user_pass="' . $wp_pwd . '" && \
        wp config shuffle-salts
    ';
    if ($stage == "local") {
        runLocally($update_cmd);
    } else {
        cd("{{release_path}}");
        run($update_cmd);
    }

    writeln("<info>
    ========================================================================
        Your " . $stage . " administrator password has been set to:

        <comment>" . $wp_pwd . "</comment>
    ========================================================================</info>");
})->desc('Reset the super admin password on the target environment');

task('pull', [
    'pull-remote-db',
    'pull-remote-uploads'
])->desc('Pull both WordPress uploads and a database from a given host');
