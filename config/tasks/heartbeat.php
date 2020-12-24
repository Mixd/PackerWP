<?php

namespace Deployer;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Heartbeat invokes a rollback if the site doesnt respond with a 200 OK post-deploy
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

task('deploy:heartbeat', function () {
    $stage = get('stage', 'local');
    $params = getenvbag($stage);
    $domain = $params["url"];

    $status_code = substr(run('{{bin/curl}} -I --silent ' . $domain . ' 2>&1 | grep "HTTP"'), -6);
    if (strpos($status_code, '200') !== false) {
        writeln("<info>Website responded with 200 OK. Safe to continue</info>");
        return;
    } else {
        writeln("<error>Website did not return a 200 OK. Deployment assumed to have failed</error>");
        $confirm = askConfirmation("
        Do you wish to initiate an immediate rollback?",
            false
        );
        if ($confirm == true) {
            invoke('rollback');
            invoke('deploy:unlock');
            exit;
        }
    }
})->setPrivate();

after('deploy:symlink', 'deploy:heartbeat');
