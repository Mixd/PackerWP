<?php

namespace Deployer;

use Dotenv\Dotenv;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Dependencies
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

require 'recipe/common.php';
require realpath(__DIR__) . '/vendor/autoload.php';

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Tasks
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$autoload = array_diff(
    scandir(realpath(__DIR__) . '/config/tasks/', SCANDIR_SORT_ASCENDING),
    ['..', '.', '.DS_Store']
);
if (!empty($autoload)) {
    array_map(function ($task) {
        require_once realpath(__DIR__) . '/config/tasks/' . $task;
    }, $autoload);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Functions
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Searches $file for $before and replaces it with $after
 *
 * @param string $file
 * @param string $before
 * @param string $after
 * @return bool
 */
function searchreplaceinfile(string $file, string $before, string $after)
{
    $seperator = "~";
    $before = str_replace($seperator, "\\" . $seperator, $before);
    $after = str_replace($seperator, "\\" . $seperator, $after);
    $cmd = "sed -i '' 's" . $seperator . $before . $seperator . $after . $seperator . "g' \"$file\"";
    $stage = get('stage', 'local');
    if ($stage == "local") {
        return runLocally($cmd);
    } else {
        return run($cmd);
    }
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Environments
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$env_path = realpath(__DIR__) . "/config/";
$dotenv = Dotenv::createImmutable($env_path, ".env");
$dotenv->load();
$dotenv->required([
    'WP_USER',
    'WP_EMAIL',
    'WP_SITENAME',
    'WP_LOCALURL',
    'REPOSITORY',
    'LOCAL_DB_HOST',
    'LOCAL_DB_NAME',
    'LOCAL_DB_USER',
    'LOCAL_DB_PASS',
])->notEmpty();

$hosts = [
    "STAGING",
    "PRODUCTION"
];

foreach ($hosts as $env) {
    $stage = strtolower($env);
    $host = null;
    if (isset($_ENV[$env . "_HOST"])) {
        $host = host($stage)
            ->hostname($_ENV[$env . "_HOST"])
            ->user($_ENV[$env . "_DEPLOY_USER"])
            ->stage($stage)
            ->forwardAgent(true)
            ->set('branch', $_ENV[$env . "_BRANCH"])
            ->set('stage_url', $_ENV[$env . "_STAGE_URL"])
            ->set('deploy_path', $_ENV[$env . "_DEPLOY_PATH"]);
    }
}

// Include reference to localhost
localhost($_ENV["LOCAL_HOST"]);

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Below be dragons - tread carefully!
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// Define the project root
set('abspath', realpath(__DIR__));

// Define a list of files that should be shared between deployments
set('shared_files', [
    'wp-config.php',
    '.htaccess',
    'robots.txt'
]);

// Allow interaction for Git clone
set('git_tty', true);

// Define a directory that is shared between deployments
set('shared_dirs', [
    'content/uploads'
]);

// Define web user writeable directories
set('writable_dirs', [
    'content/uploads',
    'content/cache'
]);

// Use ACL to extend existing permissions
set('writable_mode', 'acl'); // chmod, chown, chgrp or acl.

// Set apache config options
set('http_user', 'www-data');
set('http_group', 'www-data');

// Every release should be datetime stamped
set('release_name', date('YmdHis'));

// register the repo
set('repository', $_ENV["REPOSITORY"]);

// register the local wp url
set('local_url', $_ENV["WP_LOCALURL"]);

// Try to use git cache where applicable
set('git_cache', true);

// Disable usage data
set('allow_anonymous_stats', false);

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Master runbook
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

task('deploy', [
    'deploy:info',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable',
    'composer-install',
    'deploy:clear_paths',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'signoff',
    'success'
])->desc('Deploy your project');

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Hide uncommon tasks from the CLI
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

task('deploy:clear_paths')->setPrivate();
task('deploy:copy_dirs')->setPrivate();
task('deploy:prepare')->setPrivate();
task('deploy:release')->setPrivate();
task('deploy:shared')->setPrivate();
task('deploy:symlink')->setPrivate();
task('deploy:update_code')->setPrivate();
task('deploy:vendors')->setPrivate();
task('deploy:writable')->setPrivate();
task('cleanup')->setPrivate();
