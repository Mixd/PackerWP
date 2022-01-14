<?php

namespace Deployer;

use Exception;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Database related tasks
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

task('pull-remote-db', [
    'backup-local-db',
    'backup-remote-db',
    'db:import:local'
])->desc(
    'Pull down a copy of the database from the remote host and import it into your local env'
);

task('push-local-db', [
    'db:confirm',
    'backup-remote-db',
    'backup-local-db',
    'db:import:remote'
])->desc(
    'Push up a local copy of a database and import it into the remote host'
);

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

task('backup-local-db', function () {
    $local_db_path = get('abspath') . 'db_backups/';
    $file = get('file');

    runLocally('mkdir -p "' . $local_db_path . '"');
    runLocally('wp db export - | gzip > "' . $local_db_path . $file . '"');
})->desc('Backup a copy of a local database');

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

task('db:import:local', function () {
    $local_db_path = get('abspath') . 'db_backups/';
    $file = get('file');

    runLocally('gzip -c -d "' . $local_db_path . $file . '" | wp db import -');
    invoke('db:rewrite:local');
    runLocally("rm '" . $local_db_path . get('file') . "'");
})->setPrivate();

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

task('db:rewrite:remote', function () {
    $stage = get('stage');
    $wp_config = getconfig();
    $env_config = getenvbag($stage);

    $from = $wp_config['wp_home_url'];
    $to = $env_config['wp_home_url'];

    writeln('<info>Processing URL replacements</info>');
    writeln("<comment>$from → $to</comment>");
    run(
        "wp search-replace --report-changed-only --all-tables $from $to",
        [
            'tty' => true,
            'timeout' => null
        ]
    );

    if (!empty($wp_config['rewrite'])) {
        foreach ($wp_config['rewrite'] as $ruleset) {
            $from = $ruleset['local'];
            $to = $ruleset[$stage];

            writeln("<comment>$from → $to</comment>");

            run(
                "wp search-replace --report-changed-only --all-tables $from $to",
                [
                    'tty' => true,
                    'timeout' => null
                ]
            );
        }
    }
})->setPrivate();

task('db:rewrite:local', function () {
    $stage = get('stage');
    $wp_config = getconfig();
    $env_config = getenvbag($stage);

    $from = $env_config['wp_home_url'];
    $to = $wp_config['wp_home_url'];

    writeln('<info>Processing URL replacements</info>');
    writeln("<comment>$from → $to</comment>");

    runLocally(
        "wp search-replace --report-changed-only --all-tables $from $to",
        [
            'tty' => true,
            'timeout' => null
        ]
    );

    if (!empty($wp_config['rewrite'])) {
        foreach ($wp_config['rewrite'] as $ruleset) {
            $from = $ruleset[$stage];
            $to = $ruleset['local'];

            writeln("<comment>$from → $to</comment>");

            runLocally(
                "wp search-replace --report-changed-only --all-tables $from $to",
                [
                    'tty' => true,
                    'timeout' => null
                ]
            );
        }
    }
})->setPrivate();

task('db:confirm', function () {
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

before('backup-remote-db', 'db:reachable');
before('backup-remote-db', 'deploy:lock');
before('backup-remote-db', 'db:prepare');

before('backup-local-db', 'db:prepare');

before('db:import:remote', 'deploy:lock');
before('db:import:remote', 'db:reachable');
after('db:import:remote', 'deploy:unlock');

after('backup-remote-db', 'deploy:unlock');
