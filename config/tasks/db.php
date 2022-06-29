<?php

namespace Deployer;

use Exception;

/**
 * Backup Remote Database
 *
 * Performs a 'wp db export' on the remote environment, gzips it, then downloads it
 */
task('backup-remote-db', function () {
    invoke('db:prepare');

    $local_db_path = get('abspath') . 'db_backups/';
    $remote_db_path = get('folder');

    $file = get('file');

    // Create a remote folder to store the backup if it doesn't already exist
    if (!test('[ -d ' . $remote_db_path . ' ]')) {
        run('mkdir -p "' . $remote_db_path . '"');
        writeln('<info>Created ' . $remote_db_path . '</info>');
    }

    writeln('Using remote backup source folder: <comment>' . $remote_db_path . '</comment>');
    writeln('Using local backup destination folder: <comment>' . $local_db_path . '</comment>');
    writeln('Using filename: <comment>' . $file . '</comment>');

    within('{{release_path}}', function () use ($remote_db_path, $file) {
        // Get a list of all WP Tables that doesn't match SearchWP.
        // We do this to avoid SWP Token rows that result in duplicate UNIQUE keys and break the export
        $prefix = run('{{bin/wp}} db prefix');
        writeln("Detected WordPress table prefix: <comment>$prefix</comment>");
        writeln('<info>Exporting MySQL Database, please wait...</info>');

        // Test for searchwp tables
        $cmd = '{{bin/wp}} db tables --all-tables "' . $prefix . 'searchwp*" --format=csv';

        if (test($cmd . ' &>/dev/null')) {
            $tables = run($cmd);
            writeln('Excluding tables: <comment>' . $tables . '</comment>');
        } else {
            $tables = '';
        }

        // Run the export but exclude specified tables
        run(
            '{{bin/wp}} db export --set-gtid-purged=OFF --defaults --exclude_tables=' .
                $tables .
                ' --porcelain ' .
                $remote_db_path .
                $file
        );

        // Zip it up for faster xfer speed
        writeln('Compressing <comment>' . $file . '</comment> for a faster download...');
        run('gzip -f "' . $remote_db_path . $file . '"');
    });

    // Create a local folder to hold the backup if it doesn't already exist
    if (!testLocally('[ -d ' . $local_db_path . ' ]')) {
        runLocally('mkdir -p "' . $local_db_path . '"');
        writeln('<info>Created ' . $local_db_path . '</info>');
    }

    // Download the file
    writeln('<info>Downloading MySQL Database backup...</info>');
    download($remote_db_path . $file . '.gz', $local_db_path . $file . '.gz', [
        'options' => ['flags' => '-W']
    ]);

    writeln('Backup completed: <info>' . $local_db_path . $file . '.gz</info>');
})->desc('Backup a copy of a remote database and download it');

/**
 * Backup Local Database
 *
 * Performs a 'wp db export' on your local environment and gzips it
 */
task('backup-local-db', function () {
    // Override stage for this step
    $stage = get('stage');
    set('stage', 'local');

    invoke('db:prepare');
    $local_db_path = get('abspath') . 'db_backups/';
    $file = get('file');

    // Create a local folder to hold the backup if it doesn't already exist
    if (!testLocally('[ -d ' . $local_db_path . ' ]')) {
        runLocally('mkdir -p "' . $local_db_path . '"');
        writeln('<info>Created ' . $local_db_path . '</info>');
    }

    writeln('Using local backup destination folder: <comment>' . $local_db_path . '</comment>');
    writeln('Using filename: <comment>' . $file . '</comment>');

    // Get a list of all WP Tables that doesn't match SearchWP.
    // We do this to avoid SWP Token rows that result in duplicate UNIQUE keys and break the export
    $prefix = runLocally('{{bin/wpl}} db prefix');
    writeln("Detected WordPress table prefix: <comment>$prefix</comment>");
    writeln('<info>Exporting MySQL Database, please wait...</info>');

    // Test for searchwp tables
    $cmd = '{{bin/wpl}} db tables --all-tables "' . $prefix . 'searchwp*" --format=csv';

    if (testLocally($cmd . ' &>/dev/null')) {
        $tables = runLocally($cmd);
        writeln('Excluding tables: <comment>' . $tables . '</comment>');
    } else {
        $tables = '';
    }

    // Run the export but exclude specified tables
    runLocally(
        '{{bin/wpl}} db export --set-gtid-purged=OFF --defaults --exclude_tables=' .
            $tables .
            ' --porcelain ' .
            $local_db_path .
            $file
    );

    // Zip it up for faster xfer speed
    writeln('Compressing <comment>' . $file . '</comment> for a smaller filesize...');
    runLocally('gzip -f "' . $local_db_path . $file . '"');

    writeln('Backup completed: <info>' . $local_db_path . $file . '.gz</info>');

    // Restore stage
    set('stage', $stage);
})->desc('Backup a copy of a local database');

/**
 * Import Database (remote)
 *
 * Uploads a local gzipped database, un-gzips it, imports it using 'wp db import',
 * then performs a search and replace using 'wp search-replace'
 */
task('db:import:remote', function () {
    invoke('db:prepare');
    $stage = get('stage');
    $remote_db_path = get('folder');
    $local_db_path = get('abspath') . 'db_backups/';

    $file = get('file');

    // Need to force this to local_ instead of the default <stage>_
    $file = str_replace($stage, 'local', $file);

    // Create a remote folder to store the backup if it doesn't already exist
    if (!test('[ -d ' . $remote_db_path . ' ]')) {
        run('mkdir -p "' . $remote_db_path . '"');
        writeln('<info>Created ' . $remote_db_path . '</info>');
    }

    writeln('<info>Uploading ' . $file . '.gz, please wait...</info>');

    upload($local_db_path . $file . '.gz', $remote_db_path . $file . '.gz', [
        'options' => ['flags' => '-W']
    ]);

    within('{{release_path}}', function () use ($remote_db_path, $file) {
        writeln("Decompressing <comment>$file.gz</comment>");
        run('gzip -df "' . $remote_db_path . $file . '.gz"');

        writeln('<info>Importing MySQL Database backup, please wait...</info>');
        run('{{bin/wp}} db import --defaults --skip-optimization "' . $remote_db_path . $file . '"', ['tty' => true]);
        invoke('db:rewrite:remote');
    });

    run('rm "' . $remote_db_path . $file . '"');
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

    writeln("Decompressing <comment>$file.gz</comment>");
    runLocally('gzip -df "' . $local_db_path . $file . '.gz"');

    writeln('<info>Importing MySQL Database backup, please wait...</info>');
    runLocally('{{bin/wpl}} db import --defaults --skip-optimization "' . $local_db_path . $file . '"', [
        'tty' => true
    ]);

    invoke('db:rewrite:local');
    runLocally('rm "' . $local_db_path . $file . '"');
})->setPrivate();

/**
 * Database preflight check
 *
 * Sets a destination filename and folder for use when handling database backups
 */
task('db:prepare', function () {
    $stage = get('stage', 'local');
    $file = $stage . '_' . date('Ymd') . '.sql';

    if ($stage == 'local' || $stage == false) {
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
        throw new Exception("Unable to locate database '{$db_name}' on '{$db_host}'");
    } else {
        writeln("<info>'$db_name'</info> found on host <info>'$db_host'</info>");
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
        throw new Exception('You cannot operate on databases when using non-interactive mode');
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
task('pull-remote-db', ['backup-local-db', 'backup-remote-db', 'db:import:local'])->desc(
    'Pull down a copy of the database from the remote host and import it into your local env'
);

/**
 * Group subtasks together for the 'push-local-db' primary task
 */
task('push-local-db', ['db:confirm', 'backup-remote-db', 'backup-local-db', 'db:import:remote'])->desc(
    'Push up a local copy of a database and import it into the remote host'
);

before('backup-remote-db', 'db:reachable');
before('backup-remote-db', 'deploy:lock');
after('backup-remote-db', 'deploy:unlock');

before('db:import:remote', 'deploy:lock');
before('db:import:remote', 'db:reachable');
after('db:import:remote', 'deploy:unlock');

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
    $bin = $stage == 'local' ? get('bin/wpl') : get('bin/wp');

    $runas($bin . ' search-replace --report-changed-only --all-tables "' . $from . '" "' . $to . '"', [
        'tty' => get('allow_input'),
        'timeout' => null
    ]);

    /**
     * Bug: https://github.com/Mixd/PackerWP/issues/14
     * wp search-replace doesnt account for JSON encoded URLs so do a quick and quiet re-run
     */
    $from = str_replace('"', '', json_encode($from));
    $to = str_replace('"', '', json_encode($to));
    $runas($bin . ' search-replace --quiet --all-tables "' . $from . '" "' . $to . '"', [
        'tty' => false,
        'timeout' => null
    ]);
}
