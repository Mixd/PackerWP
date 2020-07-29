<?php

namespace Deployer;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Heartbeat invokes a rollback if the site doesnt respond with a 200 OK post-deploy
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

set('bin/curl', function () {
    return run('which curl');
});

task('deploy:heartbeat', function () {
    $stage = strtoupper(get('stage'));
    cd('{{deploy_path}}');
    $status_code = substr(run('{{bin/curl}} -I --silent ' . $_ENV[$stage . '_STAGE_URL'] . ' 2>&1 | grep "HTTP"'), -6);
    if ($status_code !== "200 OK") {
        writeln("<error>Website did not return a 200 OK. Deployment assumed to have failed</error>");
        $confirm = askConfirmation("
        Do you wish to initiate an immediate rollback?",
            false
        );
        if ($confirm !== true) {
            invoke('rollback');
            invoke('deploy:unlock');
            exit;
        }
    } else {
        writeln("<info>Website responded with 200 OK. Safe to continue</info>");
        return;
    }
})->setPrivate();

after('deploy:symlink', 'deploy:heartbeat');
