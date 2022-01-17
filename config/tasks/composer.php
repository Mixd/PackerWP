<?php

namespace Deployer;

set('composer_action', 'install');
set('composer_options', '--verbose --prefer-dist --no-progress --no-interaction --no-dev --optimize-autoloader');

/**
 * Composer
 *
 * Runs a composer install
 */
task('composer:install', function () {
    $project_root = get('abspath');
    if (file_exists($project_root . '/composer.json')) {
        if (!commandExist('unzip')) {
            warning(
                'To speed up composer installation setup "unzip" command with PHP zip extension.'
            );
        }
        if (
            has('previous_release') &&
            test('[ -d {{previous_release}}/vendor ]')
        ) {
            run('cp -R {{previous_release}}/vendor {{release_path}}/vendor');
        }

        run('cd {{release_path}} && {{bin/composer}} {{composer_action}} {{composer_options}} 2>&1', [
            'tty' => get('allow_input')
        ]);
    }
})->setPrivate();

after('deploy:update_code', 'composer:install');
