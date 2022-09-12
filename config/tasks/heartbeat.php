<?php

namespace Deployer;

/**
 * Heartbeat
 *
 * Performs a HTTP health check on the WordPress Home URL to determine if a deployment was successful
 */
task('deploy:heartbeat', function () {
    $stage = get('stage', 'local');
    $params = get_env_vars($stage);
    $domain = $params['wp_home_url'];

    $response = run(
        '{{bin/curl}} -I --silent ' . $domain . ' 2>&1 | grep "HTTP"'
    );
    $status_code = substr($response, -6);

    $is_200_ok = strpos($status_code, '200') !== false;
    $is_401 = strpos($status_code, '401') !== false;
    $is_3XX_redirect =
        strpos($status_code, '301') !== false ||
        strpos($status_code, '302') !== false;

    if (get('allow_input') == false) {
        write("<comment>
    ========================================================================
        Deployment triggered non-interactively.
        If this task fails a rollback will be automatically triggered
    ========================================================================</comment>
");
    }

    if ($is_200_ok or $is_401) {
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
        if (get('allow_input')) {
            $confirm = askConfirmation('Do you want to rollback?', false);
        } else {
            $confirm = false;
        }
    } else {
        write("<error>
    ========================================================================
        ERROR: Website is not responding after a deployment
        $domain responded with $response
    ========================================================================</error>
");
        if (get('allow_input')) {
            $confirm = askConfirmation('Do you want to rollback?', false);
        } else {
            $confirm = true;
        }
    }

    if ($confirm == true) {
        invoke('rollback');
        invoke('deploy:unlock');
        exit();
    }
})->hidden();

after('deploy:symlink', 'deploy:heartbeat');
