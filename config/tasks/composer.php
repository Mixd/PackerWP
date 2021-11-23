<?php

namespace Deployer;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Composer related tasks
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

task('composer:install', function () {
    $project_root = get('abspath');
    if (file_exists($project_root . '/composer.json')) {
        if (!commandExist('unzip')) {
            warning(
                'To speed up composer installation setup "unzip" command with PHP zip extension.'
            );
        }
        if (has('previous_release')) {
            run('cp -R {{previous_release}}/vendor {{release_path}}/vendor');
        }
        run('cd {{release_path}} && {{bin/composer}} {{composer_options}}', [
            'tty' => true
        ]);
    }
})->setPrivate();

after('deploy:writable', 'composer:install');
