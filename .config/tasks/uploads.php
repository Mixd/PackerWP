<?php
namespace Deployer;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// WordPress uploads folder related tasks
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

desc('Pull media from a remote host');
task('pull-remote-uploads', function () {
    $server = Task\Context::get()->getHost();
    $host = $server->getRealHostname();
    runLocally(
        'rsync -avzO ' . $host . ':{{deploy_path}}/shared/content/uploads/ content/uploads/ --progress',
        ['tty' => true, 'timeout' => null]
    );
    write("Completed");
});

desc('Push media to a remote host');
task('push-local-uploads', function () {
    $server = Task\Context::get()->getHost();
    $host = $server->getRealHostname();
    runLocally(
        'rsync -avzO content/uploads/ ' . $host . ':{{deploy_path}}/shared/content/uploads/ --progress',
        ['tty' => true, 'timeout' => null]
    );
    write("Completed");
});
