<?php

namespace Deployer;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Heartbeat invokes a rollback if the site doesnt respond with a 200 OK post-deploy
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

task('deploy:heartbeat', function () {
    $stage = get('stage', 'local');
    $params = getenvbag($stage);
    $domain = $params['wp_home_url'];

    $status_code = substr(
        run('{{bin/curl}} -I --silent ' . $domain . ' 2>&1 | grep "HTTP"'),
        -6
    );

    $is_200_ok = strpos($status_code, '200') !== false;
    $is_3XX_redirect =
        strpos($status_code, '301') !== false ||
        strpos($status_code, '302') !== false;

    if ($is_200_ok) {
        writeln('<info>Website responded with 200 OK. Safe to continue</info>');
        $confirm = false;
    } elseif ($is_3XX_redirect) {
        writeln(
            "<comment>Website returned a 300 status code. Is the 'wp_home_url' correct?</comment>"
        );
        $confirm = askConfirmation('Do you want to rollback?', false);
    } else {
        writeln('<error>Website returned an invalid status code.</error>');
        $confirm = askConfirmation('Do you want to rollback?', false);
    }

    if ($confirm == true) {
        invoke('rollback');
        invoke('deploy:unlock');
        exit();
    }
})->setPrivate();

after('deploy:symlink', 'deploy:heartbeat');
