<?php

namespace Deployer;

use Exception;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Dependencies
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

require 'recipe/common.php';

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
    $seperator = "/";
    $before = str_replace($seperator, "\\" . $seperator, $before);
    $after = str_replace($seperator, "\\" . $seperator, $after);

    /**
     * The syntax for 'sed' differs between OS X + Ubuntu
     * https://github.com/Mixd/PackerWP/issues/10
     */
    $which_sed = run('sed --version | head -n 1');
    if (strstr($which_sed, "GNU sed")) {
        $cmd = "sed -i 's" . $seperator . $before . $seperator . $after . $seperator . "g' \"$file\"";
    } else {
        $cmd = "sed -i '' 's" . $seperator . $before . $seperator . $after . $seperator . "g' \"$file\"";
    }

    $stage = get('stage', 'local');
    if ($stage == "local") {
        return runLocally($cmd);
    } else {
        return run($cmd);
    }
}

/**
 * wp-cli's search-replace helper function
 *
 * @param string $from
 * @param string $to
 * @return void
 */
function searchreplace(string $from, string $to)
{
    writeln("Replacing all instances of '$from' with '$to'");
    $cmd = "wp search-replace $from $to --all-tables --report-changed-only";
    $stage = get('stage', 'local');
    if ($stage == "local") {
        return runLocally($cmd);
    } else {
        return run($cmd);
    }
}

/**
 * Get an array of environment vars
 *
 * @param string $stage
 * @return array
 */
function getenvbag(string $stage)
{
    $config = get('config');
    return $config["environments"][$stage];
}

/**
 * Get an array of default configuration options
 *
 * @return array
 */
function getconfig()
{
    $config = get('config');
    unset($config["environments"]);
    return $config;
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Environments
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

$env_path = realpath(__DIR__) . "/config/";
if (file_exists($env_path . 'config.json') == false) {
    throw new Exception("Unable to find configuration file at " . $env_path . 'config.json');
} else {
    $json = file_get_contents($env_path . 'config.json');
    if ($json == false) {
        throw new Exception("Unable to read configuration file. Check your current user has permission to read the file");
    }
    $json = json_decode($json, true, 12);

    // register the repo
    set('repository', $json["git-repo"]);

    // register the local wp url
    set('local_url', $json["wp-local-url"]);

    // register the config
    set('config', $json);

    foreach ($json["environments"] as $env => $params) {
        $stage = strtolower($env);
        $host = $params["host"];

        if ($stage == "local") {
            // Define localhost
            localhost($params["host"]);
            set('vars', $params);
        } else {
            // Register a new target host
            host($stage)
                ->hostname($host)
                ->user($params["server-user"] ?? get_current_user())
                ->stage($stage)
                ->forwardAgent(true)
                ->multiplexing(true)
                ->set('branch', $params["git-branch"])
                ->set('stage_url', $params["url"])
                ->set('deploy_path', $params["server-path"])
                ->set('extra', $params);
        }
    }
}

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
    'content/uploads',
    'content/cache',
    'content/w3tc-config', // W3 Total Cache wants to write it's own config to disk
]);

// Define web user writeable directories
set('writable_dirs', [
    'content/uploads',
    'content/cache',
    'content/w3tc-config' // W3 Total Cache wants to write it's own config to disk
]);

// Use ACL to extend existing permissions
set('writable_mode', 'acl'); // chmod, chown, chgrp or acl.

// Set apache config options
set('http_user', function () {
   if ($webuser = run("cat /etc/apache2/envvars | grep 'APACHE_RUN_USER'")) {
       $www = explode("=", $webuser);
       return end($www);
   }
   return 'nobody';
});
set('http_group', function () {
    if ($webgroup = run("cat /etc/apache2/envvars | grep 'APACHE_RUN_GROUP'")) {
        $www = explode("=", $webgroup);
        return end($www);
    }
    return 'nobody';
});

// Every release should be datetime stamped
set('release_name', date('YmdHis'));

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
//// Eject button
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

task('reset', function () {
    $abs = get('abspath');
    write("<error>
    ========================================================================
        WARNING: You're about to reset your database and installation
    ========================================================================</error>
    ");

    $confirm = askConfirmation("
    Are you sure you wish to continue?",
        false
    );
    if ($confirm == true) {
        if (test("wp core is-installed") or test("wp core is-installed --network")) {
            runLocally("wp db reset --yes", ['tty' => true]);
        }
        if (file_exists($abs . "/wp-config.php")) {
            runLocally("rm -i '$abs/wp-config.php'", ['tty' => true]);
        }
    }
})->desc("Reset the local WordPress database and installation");

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
