<?php
namespace Deployer;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// WordPress uploads folder related tasks
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

desc('Set up your project on the remote host');
task('setup-wp', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'setup-remote-wp',
    'deploy:shared',
    'deploy:writable',
    'deploy:clear_paths',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success'
]);

// setup-remote-wp - Sets up any templated config files
task('setup-remote-wp', function () {
    // Load app vars
    $domain = get('stage_url');
    $wp_user = get('wp_user');
    $wp_email = get('wp_email');
    $wp_sitename = get('wp_sitename');
    $wp_pwd = bin2hex(openssl_random_pseudo_bytes(8));

    // Load database vars
    $database = getDatabaseVars(get('stage'));
    $db_host = $database["host"];
    $db_database = $database["database"];
    $db_username = $database["username"];
    $db_password = $database["password"];

    // Prep remote files
    run("cp {{release_path}}/.config/templates/{{stage}}/wp-config.example.php {{release_path}}/wp-config.php;");
    run("cp {{release_path}}/.config/templates/{{stage}}/.htaccess {{release_path}}/.htaccess;");
    run("cp {{release_path}}/.config/templates/{{stage}}/robots.txt {{release_path}}/robots.txt;");

    // Run a search-replace with the necessary values
    run("
        sed -i -- 's#<<< DATABASE NAME >>>#" . $db_database . "#g' {{release_path}}/wp-config.php;
        sed -i -- 's#<<< DATABASE USER >>>#" . $db_username . "#g' {{release_path}}/wp-config.php;
        sed -i -- 's#<<< DATABASE PWD >>>#" . $db_password . "#g' {{release_path}}/wp-config.php;
        sed -i -- 's#<<< DATABASE HOST >>>#" . $db_host . "#g' {{release_path}}/wp-config.php;
        sed -i -- 's#<<< WP SITE URL >>>#" . $domain . "#g' {{release_path}}/wp-config.php;
    ");

    // Run the wp install
    run("cd {{release_path}} && wp core install --url='" . $domain . "' --title='" . $wp_sitename . "' --admin_user='" . $wp_user . "' --admin_password='" . $wp_pwd . "' --admin_email='" . $wp_email . "'");

    // Shuffle the salts
    run("cd {{release_path}}; wp config shuffle-salts");

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
});

desc('Set up your project locally');
task('setup-local-wp', function () {
    // Load app vars
    $domain = get('wp_localurl');
    $wp_user = get('wp_user');
    $wp_email = get('wp_email');
    $wp_sitename = get('wp_sitename');
    $wp_pwd = bin2hex(openssl_random_pseudo_bytes(8));

    // Load database vars
    $database = getDatabaseVars("local");
    $db_host = $database["host"];
    $db_database = $database["database"];
    $db_username = $database["username"];
    $db_password = $database["password"];

    // Prep remote files
    run("cp ./.config/templates/local/wp-config.example.php ./wp-config.php;");
    run("cp ./.config/templates/local/.htaccess ./.htaccess;");
    run("cp ./.config/templates/local/robots.txt ./robots.txt;");

    // Run a search-replace with the necessary values
    run("
        sed -i '' 's#<<< DATABASE NAME >>>#" . $db_database . "#g' ./wp-config.php;
        sed -i '' 's#<<< DATABASE USER >>>#" . $db_username . "#g' ./wp-config.php;
        sed -i '' 's#<<< DATABASE PWD >>>#" . $db_password . "#g' ./wp-config.php;
        sed -i '' 's#<<< DATABASE HOST >>>#" . $db_host . "#g' ./wp-config.php;
        sed -i '' 's#<<< WP SITE URL >>>#" . $domain . "#g' ./wp-config.php;
    ");

    // Run the wp install
    run("
        wp core install \
        --url='" . $domain . "' \
        --title='" . $wp_sitename . "' \
        --admin_user='" . $wp_user . "' \
        --admin_password='" . $wp_pwd . "' \
        --admin_email='" . $wp_email . "'
    ");

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
})->local();

desc('Deploy your project');
task('deploy', [
    'deploy:info',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable',
    'deploy:clear_paths',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success'
]);

after('cleanup', 'signoff');
task('signoff', function () {
    cd('{{deploy_path}}');
    run('touch revisions.log');
    run('echo "Branch ({{branch}}) deployed by ({{user}}) for release ({{release_name}})" > revisions.log');
});

desc('Update your local version of WordPress');
task('update-wp', function () {
    cd('wordpress');
    run('git fetch --tags');
    run(
        'latestTag=$(git tag -l --sort -version:refname | head -n 1) && git checkout $latestTag',
        ['tty' => true]
    );
})->local();
