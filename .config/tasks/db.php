<?php
namespace Deployer;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Database related tasks
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

desc('Pull down a copy of the database from the remote host and import it into your local env');
task('pull-remote-db', [
    'deploy:lock',
    'prepare',
    'backup-remote-db',
    'import-local-db',
    'deploy:unlock',
    'success'
]);

desc('Push up a local copy of a database and import it into the remote host');
task('push-local-db', [
    'deploy:lock',
    'prepare',
    'backup-local-db',
    'import-remote-db',
    'deploy:unlock',
    'success'
]);

task('import-remote-db', function () {
    cd("{{release_path}}");
    run("gzip -c -d " . get('folder') . get('file') . " | wp db import -");
    run("wp search-replace {{wp_localurl}} {{stage_url}}", ['tty' => true, 'timeout' => null]);
    run("rm " . get('folder') . get('file'));
});

task('import-local-db', function () {
    runLocally("gzip -c -d db_backups/" . get('file') . " | wp db import -");
    runLocally("wp search-replace {{stage_url}} {{wp_localurl}}", ['tty' => true, 'timeout' => null]);
    runLocally("rm db_backups/" . get('file'));
});

task('prepare', function () {
    $datetime = date('YmdHis');
    $file = get('stage') . "-" . $datetime . ".sql.gz";
    $folder = get('deploy_path') . '/db_backups/';
    $server = Task\Context::get()->getHost();
    $host = $server->getRealHostname();
    set('file', $file);
    set('folder', $folder);
    set('targetHost', $host);
});

desc('Backup a copy of a remote database and download it');
task('backup-remote-db', function () {
    runLocally('mkdir -p db_backups');
    cd('{{deploy_path}}');
    run('mkdir -p db_backups');
    cd('{{release_path}}');
    run("wp db export - | gzip > " . get('folder') . get('file'));
    runLocally(
        "scp " . get('targetHost') . ":" . get('folder') . get('file') . " db_backups/",
        ['tty' => true, 'timeout' => null]
    );
});

desc('Backup a copy of a local database and upload it to a remote host');
task('backup-local-db', function () {
    runLocally('mkdir -p db_backups');
    runLocally("wp db export - | gzip > db_backups/" . get('file'));
    cd('{{deploy_path}}');
    run('mkdir -p db_backups');
    runLocally(
        "scp db_backups/" . get('file') . " " . get('targetHost') . ":" . get('folder') . get('file'),
        ['tty' => true, 'timeout' => null]
    );
});

/**
 * Load database variables for a given stage
 *
 * @param string $stage
 * @return array
 */
function getDatabaseVars($stage)
{
    $database = json_decode(file_get_contents(realpath(__DIR__) . '/.config/database.json'), true);
    return $database[$stage];
}
