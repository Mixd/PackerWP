<?php
namespace Deployer;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Dependencies
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

require 'recipe/common.php';
require realpath(__DIR__) . '/config/app.php';

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Environments
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

if (file_exists(realpath(__DIR__) . '/config/deploy/staging.php')) {
    require realpath(__DIR__) . '/config/deploy/staging.php';
}
if (file_exists(realpath(__DIR__) . '/config/deploy/production.php')) {
    require realpath(__DIR__) . '/config/deploy/production.php';
}

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
//// Below be dragons - tread carefully!
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

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

// Try to use git cache where applicable
set('git_cache', true);

// Disable usage data
set('allow_anonymous_stats', false);


/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Helper functions
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Load database variables for a given stage
 * @return array
 */
set('database_vars', function () {
    $stage = get("stage");
    $database = json_decode(file_get_contents(realpath(__DIR__) . '/config/database.json'), true);
    return $database[$stage];
});
