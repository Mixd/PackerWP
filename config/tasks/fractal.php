<?php

namespace Deployer;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Fractal related tasks
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

task('fractal-build', function () {
    $project_root = get('abspath');
    if (file_exists($project_root . '/fractal.config.js')) {
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
        run('rm -r node_modules');
    }
})->desc('Build Fractal pattern library');

after('deploy:symlink', 'fractal-build');
