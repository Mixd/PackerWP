<?php

namespace Deployer;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Database related tasks
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

task('pull-remote-db', [
    'backup-remote-db',
    'db:import:local',
])->desc('Pull down a copy of the database from the remote host and import it into your local env');

task('push-local-db', [
    'db:confirm',
    'backup-local-db',
    'db:import:remote',
])->desc('Push up a local copy of a database and import it into the remote host');

task('backup-remote-db', function () {
    $remote_db_path = get('folder');
    run('mkdir -p "' . $remote_db_path . '"');
    cd('{{release_path}}');
    run('wp db export - | gzip > "' . $remote_db_path . get('file') . '"');

    $local_db_path = get('abspath') . '/db_backups/';
    runLocally('mkdir -p "' . $local_db_path . '"');
    download(get('folder') . get('file'), $local_db_path);
    run('rm -r ' . get('folder'));
})->desc('Backup a copy of a remote database and download it');

task('backup-local-db', function () {
    $stage = "local";
    $datetime = date('YmdHis');
    $file = $stage . "-" . $datetime . ".sql.gz";
    $local_db_path = get('abspath') . '/db_backups/';
    runLocally('mkdir -p "' . $local_db_path . '"');
    runLocally('wp db optimize');
    runLocally('wp db export - | gzip > "' . $local_db_path . $file . '"');
    runLocally('wp db size --human-readable', ['tty' => true]);
})->desc('Backup a copy of a local database and upload it to a remote host')->local();

task('db:confirm', function () {
    $stage = get('stage');
    $db_name = $_ENV[strtoupper($stage) . "_DB_NAME"];

    write("<error>
    ========================================================================
        WARNING: You're about to overwrite the database!
    ========================================================================</error>

    <comment>Environment: </comment><info>" . $stage . " </info>
    <comment>Database name: </comment><info>" . $db_name . " </info>
    ");

    $confirm = askConfirmation("
    Are you sure you wish to continue?",
        false
    );
    if ($confirm !== true) {
        writeln("<error>
    ========================================================================
        You did not want to continue so your task was aborted
    ========================================================================</error>
        ");
        return;
    }
})->setPrivate();

task('db:import:remote', function () {
    $local_db_path = get('abspath') . '/db_backups/';
    $remote_db_path = get('folder');
    upload($local_db_path . get('file'), $remote_db_path . get('file'));

    cd("{{release_path}}");
    run("gzip -c -d " . $remote_db_path . get('file') . " | wp db import -");
    run("wp search-replace --report-changed-only --all-tables " . get('local_url') . " " . get('stage_url'), [
        'tty' => true,
        'timeout' => null
    ]);
    run('wp db optimize');
    run('wp db size --human-readable', ['tty' => true]);
    run("rm " . $remote_db_path . get('file'));
})->setPrivate();

task('db:import:local', function () {
    $local_db_path = get('abspath') . '/db_backups/';
    runLocally('gzip -c -d "' . $local_db_path . get('file') . '" | wp db import -');
    runLocally("wp search-replace --report-changed-only --all-tables " . get('stage_url') . " " . get('local_url'), [
        'tty' => true,
        'timeout' => null
    ]);
    runLocally('wp db size --human-readable', ['tty' => true]);

    run("rm -f " . $local_db_path . get('file'));
})->setPrivate();

task('db:prepare', function () {
    $stage = get('stage');
    $local_url = $_ENV["WP_LOCALURL"];
    $stage_url = $_ENV[strtoupper($stage) . "_STAGE_URL"];
    $datetime = date('YmdHis');
    $file = $stage . "-" . $datetime . ".sql.gz";
    $folder = get('deploy_path') . '/db_backups/';
    $server = Task\Context::get()->getHost();
    $host = $server->getRealHostname();
    set('local_url', $local_url);
    set('stage_url', $stage_url);
    set('file', $file);
    set('folder', $folder);
    set('targetHost', $host);
})->setPrivate();

before('backup-local-db', 'deploy:lock');
before('backup-local-db', 'db:prepare');
before('backup-remote-db', 'deploy:lock');
before('backup-remote-db', 'db:prepare');
after('backup-local-db', 'deploy:unlock');
after('backup-remote-db', 'deploy:unlock');
