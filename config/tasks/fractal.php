<?php
namespace Deployer;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Fractal related tasks
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

set('bin/npm', function () {
    return run('which npm');
});

desc('Install npm packages');
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
});

desc('Build Fractal pattern library');
task('fractal-build', function () {
    invoke('npm:install');
    writeln("<info>Building pattern library</info>");
    run("cd {{release_path}} && {{bin/npm}} run build:fractal", ['tty' => true]);
});

after('deploy:symlink', 'fractal-build');
