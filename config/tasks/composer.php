<?php
namespace Deployer;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Composer related tasks
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

task('composer-install', function () {
    if (!commandExist('unzip')) {
        warning('To speed up composer installation setup "unzip" command with PHP zip extension.');
    }
    run('cd {{release_path}} && {{bin/composer}} {{composer_options}} 2>&1');
});

task('composer-install-local', function () {
    if (!commandExist('unzip')) {
        warning('To speed up composer installation setup "unzip" command with PHP zip extension.');
    }
    runLocally('{{bin/composer}} {{composer_options}} 2>&1');
})->local();
