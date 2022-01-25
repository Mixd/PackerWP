<?php

namespace Deployer;

/**
 * Detect Fractal
 *
 * Detects if your project has a 'fractal.config.js' file in the root. If it does
 * you are prompted to build it using 'npm run build:fractal'
 */
task('fractal:detect', function () {
    if (get('allow_input') == false) {
        write("<comment>
    ========================================================================
        Deployment triggered non-interactively.
        This task will be skipped
    ========================================================================</comment>
");
        return;
    }

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

/**
 * Build Fractal
 *
 * Performs an 'npm run fractal:build'
 */
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
        'tty' => get('allow_input')
    ]);
})->desc('Build Fractal pattern library');

after('deploy:symlink', 'fractal:detect');
