<?php

namespace Deployer;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Heartbeat invokes a rollback if the site doesnt respond with a 200 OK post-deploy
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

task('deploy:heartbeat', function () {
    $stage = get('stage', 'local');
    $params = getenvbag($stage);
    $domain = $params['wp_home_url'];

    $response = run(
        '{{bin/curl}} -I --silent ' . $domain . ' 2>&1 | grep "HTTP"'
    );
    $status_code = substr($response, -6);

    $is_200_ok = strpos($status_code, '200') !== false;
    $is_3XX_redirect =
        strpos($status_code, '301') !== false ||
        strpos($status_code, '302') !== false;

    if ($is_200_ok) {
        write("<info>
    ========================================================================
        $domain responded with $response
    ========================================================================</info>
");
        $confirm = false;
    } elseif ($is_3XX_redirect) {
        write("<comment>
    ========================================================================
        WARNING: 'wp_home_url' for '$stage' may not be exactly right!
        $domain responded with $response
    ========================================================================</comment>
");
        $confirm = askConfirmation('Do you want to rollback?', false);
    } else {
        write("<error>
    ========================================================================
        ERROR: Website is not responding after a deployment
        $domain responded with $response
    ========================================================================</error>
");
        $confirm = askConfirmation('Do you want to rollback?', false);
    }

    if ($confirm == true) {
        invoke('rollback');
        invoke('deploy:unlock');
        exit();
    }
})->setPrivate();

after('deploy:symlink', 'deploy:heartbeat');
