<?php

namespace Deployer;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Fractal related tasks
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

set('bin/npm', function () {
    if (commandExist('npm')) {
        return run('which npm');
    } else {
        error('Unable to find npm');
        return;
    }
});

task('npm:install', function () {
    if (has('previous_release')) {
        if (test('[ -d {{previous_release}}/node_modules ]')) {
            run('cp -R {{previous_release}}/node_modules {{release_path}}');

            // If package.json is unmodified, then skip running `npm install`
            if (!run('diff {{previous_release}}/package.json {{release_path}}/package.json')) {
                return;
            }
        }
    }
    run("cd {{release_path}} && {{bin/npm}} install");
})->setPrivate();

task('fractal-build', function () {
    run("cd {{release_path}} && {{bin/npm}} run build:fractal", ['tty' => true]);
})->desc('Build Fractal pattern library');

before('fractal-build', 'npm:install');
after('deploy:symlink', 'fractal-build');
