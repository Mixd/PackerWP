<?php

namespace Deployer;

use Exception;

/**
 * Backup Remote Database
 *
 * Performs a 'wp db export' on the remote environment, gzips it, then downloads it
 */
task('backup-remote-db', function () {
    $local_db_path = get('abspath') . 'db_backups/';
    $remote_db_path = get('folder');

    $file = get('file');
    run('mkdir -p "' . $remote_db_path . '"');
    cd('{{release_path}}');
    run('wp db export - | gzip > "' . $remote_db_path . $file . '"');

    runLocally('mkdir -p "' . $local_db_path . '"');
    download($remote_db_path . $file, $local_db_path . $file, [
        'options' => ['flags' => '-ch']
    ]);
})->desc('Backup a copy of a remote database and download it');

/**
 * Backup Local Database
 *
 * Performs a 'wp db export' on your local environment and gzips it
 */
task('backup-local-db', function () {
    $local_db_path = get('abspath') . 'db_backups/';
    $file = get('file');

    runLocally('mkdir -p "' . $local_db_path . '"');
    runLocally('wp db export - | gzip > "' . $local_db_path . $file . '"');
})->desc('Backup a copy of a local database');

/**
 * Import Database (remote)
 *
 * Uploads a local gzipped database, un-gzips it, imports it using 'wp db import',
 * then performs a search and replace using 'wp search-replace'
 */
task('db:import:remote', function () {
    $remote_db_path = get('folder');
    $local_db_path = get('abspath') . 'db_backups/';

    $file = get('file');
    run('mkdir -p "' . $remote_db_path . '"');

    upload($local_db_path . $file, $remote_db_path . $file, [
        'options' => ['flags' => '-ch']
    ]);

    cd('{{release_path}}');
    run('gzip -c -d ' . $remote_db_path . $file . ' | wp db import -');
    invoke('db:rewrite:remote');
    run('rm ' . $remote_db_path . $file);
})->setPrivate();

/**
 * Import Database (local)
 *
 * Un-gzips a previously downloaded database export, then imports it using 'wp db import',
 * then performs a search and replace using 'wp search-replace'
 */
task('db:import:local', function () {
    $local_db_path = get('abspath') . 'db_backups/';
    $file = get('file');

    runLocally('gzip -c -d "' . $local_db_path . $file . '" | wp db import -');
    invoke('db:rewrite:local');
    runLocally("rm '" . $local_db_path . get('file') . "'");
})->setPrivate();

/**
 * Database preflight check
 *
 * Sets a destination filename and folder for use when handling database backups
 */
task('db:prepare', function () {
    $stage = get('stage', 'local');
    $file = $stage . '_' . date('YmdHis') . '.sql.gz';

    if ($stage == 'local') {
        $root_path = get('abspath');
    } else {
        $root_path = get('deploy_path') . '/';
    }

    $folder = $root_path . 'db_backups/';

    set('file', $file);
    set('folder', $folder);
})->setPrivate();

/**
 * Database viability check
 *
 * Tests to see if the database you want to operate on exists on the database host you specified
 */
task('db:reachable', function () {
    $stage = get('stage', 'local');
    $params = getenvbag($stage);

    $db_host = $params['db_host'];
    $db_name = $params['db_name'];
    $db_user = $params['db_user'];
    $db_pass = $params['db_password'];

    $does_exist = run("
        mysql -h \"$db_host\" -u \"$db_user\" -p\"$db_pass\" --batch \
            --skip-column-names -e \"SHOW DATABASES LIKE '$db_name';\" | grep \"$db_name\" > /dev/null;
        echo \"$?\"");

    if ($does_exist === '1') {
        throw new Exception(
            "Unable to locate database '{$db_name}' on '{$db_host}'"
        );
    } else {
        writeln(
            "<info>'$db_name'</info> found on host <info>'$db_host'</info>"
        );
    }
})->setPrivate();

/**
 * Database Rewrite (remote)
 *
 * Performs a search and replace of the remote database using 'wp search-replace'
 */
task('db:rewrite:remote', function () {
    $stage = get('stage');
    $wp_config = getconfig();
    $env_config = getenvbag($stage);

    $from = $wp_config['wp_home_url'];
    $to = $env_config['wp_home_url'];

    writeln('<info>Processing URL replacements</info>');
    wp_search_replace($from, $to, $stage);

    if (!empty($wp_config['rewrite'])) {
        foreach ($wp_config['rewrite'] as $ruleset) {
            $from = $ruleset['local'];
            $to = $ruleset[$stage];

            wp_search_replace($from, $to, $stage);
        }
    }
})->setPrivate();

/**
 * Database Rewrite (local)
 *
 * Performs a search and replace of the local database using 'wp search-replace'
 */
task('db:rewrite:local', function () {
    $stage = get('stage');
    $wp_config = getconfig();
    $env_config = getenvbag($stage);

    $from = $env_config['wp_home_url'];
    $to = $wp_config['wp_home_url'];

    writeln('<info>Processing URL replacements</info>');
    wp_search_replace($from, $to, 'local');

    if (!empty($wp_config['rewrite'])) {
        foreach ($wp_config['rewrite'] as $ruleset) {
            $from = $ruleset[$stage];
            $to = $ruleset['local'];

            wp_search_replace($from, $to, 'local');
        }
    }
})->setPrivate();

/**
 * Database confirmation
 *
 * When executing 'push' tasks, you must double-check your action before continuing
 *
 * Note: you cannot perform overwrites when using PackerWP with Continuous Integration
 */
task('db:confirm', function () {
    // Safety net to prevent database overwriting using CI/CD
    if (get('allow_input') == false) {
        throw new Exception("You cannot operate on databases when using non-interactive mode");
    }

    $stage = get('stage');
    $params = getenvbag($stage);
    $db_name = $params['db_name'];

    write(
        "<error>
    ========================================================================
        WARNING: You're about to overwrite the database!
    ========================================================================</error>

    <comment>Environment: </comment><info>" .
            $stage .
            " </info>
    <comment>Database name: </comment><info>" .
            $db_name .
            " </info>
    "
    );

    $confirm = askConfirmation(
        "
    Are you sure you wish to continue?",
        false
    );
    if ($confirm !== true) {
        writeln("<error>
    ========================================================================
        You did not want to continue so your task was aborted
    ========================================================================</error>
        ");
        exit();
    }
})->setPrivate();

/**
 * Group subtasks together for the 'pull-remote-db' primary task
 */
task('pull-remote-db', [
    'backup-local-db',
    'backup-remote-db',
    'db:import:local'
])->desc(
    'Pull down a copy of the database from the remote host and import it into your local env'
);

/**
 * Group subtasks together for the 'push-local-db' primary task
 */
task('push-local-db', [
    'db:confirm',
    'backup-remote-db',
    'backup-local-db',
    'db:import:remote'
])->desc(
    'Push up a local copy of a database and import it into the remote host'
);

before('backup-remote-db', 'db:reachable');
before('backup-remote-db', 'deploy:lock');
before('backup-remote-db', 'db:prepare');
before('backup-local-db', 'db:prepare');
before('db:import:remote', 'deploy:lock');
before('db:import:remote', 'db:reachable');

after('db:import:remote', 'deploy:unlock');
after('backup-remote-db', 'deploy:unlock');

/**
 * Run a `wp search-replace $from $to`
 *
 * @param string $from
 * @param string $to
 * @return void
 */
function wp_search_replace($from, $to, $stage = 'local')
{
    writeln("<comment>$from → $to</comment>");

    $runas = __NAMESPACE__ . '\\' . ($stage == 'local' ? 'runLocally' : 'run');

    $runas(
        "wp search-replace --report-changed-only --all-tables $from $to",
        [
            'tty' => get('allow_input'),
            'timeout' => null
        ]
    );

    /**
     * Bug: https://github.com/Mixd/PackerWP/issues/14
     * wp search-replace doesnt account for JSON encoded URLs so do a quick and quiet re-run
     */
    $from = str_replace('"', '', json_encode($from));
    $to = str_replace('"', '', json_encode($to));
    $runas(
        "wp search-replace --quiet --all-tables $from $to",
        [
            'tty' => false,
            'timeout' => null
        ]
    );
}
