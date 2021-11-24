<?php

namespace Deployer;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Fractal related tasks
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

task('fractal:detect', function () {
    $project_root = get('abspath');
    if (file_exists($project_root . '/fractal.config.js')) {
        writeln('');

        $do_build = askConfirmation(
            "'fractal.config.js' detected! Do you want to build it?",
            false
        );

        if ($do_build == true) {
            invoke('fractal:build');
        } else {
            writeln('Skipped');
        }
    }
})->setPrivate();

task('fractal:build', function () {
    writeln('<info>Installing fractal binary</info>');
    if (
        has('previous_release') &&
        test('[ -d {{previous_release}}/node_modules ]')
    ) {
        run(
            'cp -R {{previous_release}}/node_modules {{release_path}}/node_modules'
        );
    }

    cd('{{release_path}}');
    run('{{bin/npm}} install @frctl/fractal @frctl/twig');
    run('{{bin/npm}} run build:fractal', [
        'tty' => true
    ]);
})->desc('Build Fractal pattern library');

after('deploy:symlink', 'fractal:detect');
