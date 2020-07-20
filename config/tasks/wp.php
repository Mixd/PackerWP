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

    cd('{{release_path}}');

    if (testLocally("wp core is-installed") && file_exists($abs . '/wp-config.php')) {
        writeln("<error>Existing wp-config.php detected at '" . $abs . "/wp-config.php'</error>");
        exit;
    }

    run("cp ./config/templates/{{stage}}/wp-config.example.php ./wp-config.php");
    run("cp ./config/templates/{{stage}}/.htaccess ./.htaccess");
    run("cp ./config/templates/{{stage}}/robots.txt ./robots.txt");

    // Run a search-replace with the necessary values
    run("
        sed -i -- 's#<<< DATABASE NAME >>>#" . $db_name . "#g' ./wp-config.php;
        sed -i -- 's#<<< DATABASE USER >>>#" . $db_username . "#g' ./wp-config.php;
        sed -i -- 's#<<< DATABASE PWD >>>#" . $db_password . "#g' ./wp-config.php;
        sed -i -- 's#<<< DATABASE HOST >>>#" . $db_host . "#g' ./wp-config.php;
        sed -i -- 's#<<< WP SITE URL >>>#" . $domain . "#g' ./wp-config.php;
    ");

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

    if (test("wp core is-installed") && file_exists($abs . '/wp-config.php')) {
        writeln("<error>Existing wp-config.php detected at '" . $abs . "/wp-config.php'</error>");
        exit;
    }

    // Prep remote files
    run('cp "' . $abs . '/config/templates/local/wp-config.example.php" "' . $abs . '/wp-config.php"');
    run('cp "' . $abs . '/config/templates/local/.htaccess" "' . $abs . '/.htaccess"');
    run('cp "' . $abs . '/config/templates/local/robots.txt" "' . $abs . '/robots.txt"');

    // Run a search-replace with the necessary values
    run("
        sed -i '' 's#<<< DATABASE NAME >>>#" . $db_name . "#g' \"" . $abs . "/wp-config.php\";
        sed -i '' 's#<<< DATABASE USER >>>#" . $db_username . "#g' \"" . $abs . "/wp-config.php\";
        sed -i '' 's#<<< DATABASE PWD >>>#" . $db_password . "#g' \"" . $abs . "/wp-config.php\";
        sed -i '' 's#<<< DATABASE HOST >>>#" . $db_host . "#g' \"" . $abs . "/wp-config.php\";
        sed -i '' 's#<<< WP SITE URL >>>#" . $domain . "#g' \"" . $abs . "/wp-config.php\";
    ");
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
})->desc('Set up your project locally')->local();

task('signoff', function () {
    cd('{{deploy_path}}');
    run('touch revisions.log');
    run('echo "Branch ({{branch}}) deployed by ({{user}}) for release ({{release_name}})" > revisions.log');
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
