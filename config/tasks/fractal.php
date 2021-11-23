<?php

namespace Deployer;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Fractal related tasks
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

task('fractal-build', function () {
    $project_root = get('abspath');
    if (file_exists($project_root . '/fractal.config.js')) {
        writeln('');
        $do_build = askConfirmation(
            "'fractal.config.js' detected! Do you want to build it?",
            false
        );

        if ($do_build == true) {
            writeln('<info>Installing fractal binary</info>');
            if (has('previous_release')) {
                run(
                    'cp -R {{previous_release}}/node_modules {{release_path}}/node_modules'
                );
            }

            cd('{{release_path}}');
            run('{{bin/npm}} install @frctl/fractal @frctl/twig');
            run('{{bin/npm}} run build:fractal', [
                'tty' => true
            ]);
        } else {
            writeln('Skipped');
        }
    }
})->desc('Build Fractal pattern library');

after('deploy:symlink', 'fractal-build');
