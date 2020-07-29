<?php

namespace Deployer;

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

    if (test("wp core is-installed")) {
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
    $stage = 'local';
    $db_host = $_ENV[strtoupper($stage) . "_DB_HOST"];
    $db_name = $_ENV[strtoupper($stage) . "_DB_NAME"];
    $db_username = $_ENV[strtoupper($stage) . "_DB_USER"];
    $db_password = str_replace("#", "\#", $_ENV[strtoupper($stage) . "_DB_PASS"]);
    $domain = get('local_url');
    $wp_user = $_ENV["WP_USER"];
    $wp_email = $_ENV["WP_EMAIL"];
    $wp_sitename = $_ENV["WP_SITENAME"];
    $wp_pwd = bin2hex(openssl_random_pseudo_bytes(16));
    $abs = get('abspath');
    $path_to_wpconfig = $abs . "/wp-config.php";

    if (test("wp core is-installed")) {
        writeln("<error>WordPress is already installed</error>");
        exit;
    }

    // Prep remote files
    run('cp "' . $abs . '/config/templates/local/wp-config.example.php" "' . $path_to_wpconfig . '"');
    run('cp "' . $abs . '/config/templates/local/.htaccess" "' . $abs . '/.htaccess"');
    run('cp "' . $abs . '/config/templates/local/robots.txt" "' . $abs . '/robots.txt"');

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

    writeln("<info>");
    writeln("=========================================================================");
    writeln("WordPress has successfully been installed. Here are your login details:");
    writeln("Username:       " . $wp_user);
    writeln("Password:       " . $wp_pwd);
    writeln("Email address:  " . $wp_email . "");
    writeln("Log in at:      " . $domain . "/wordpress/wp-admin");
    writeln("=========================================================================");
    writeln("</info>");
})->desc('Set up your project locally')->local();

task('signoff', function () {
    $signoff = "Branch ({{branch}}) deployed by ({{user}}) for release ({{release_name}})";
    cd('{{deploy_path}}');
    run('touch revisions.log');
    run('echo "' . $signoff . '" >> revisions.log');
    writeln("<info>" . $signoff . "</info>");
})->setPrivate();

task('reset-admin-pwd', function () {
    $wp_pwd = bin2hex(openssl_random_pseudo_bytes(16));
    $wp_user = $_ENV["WP_USER"];
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
