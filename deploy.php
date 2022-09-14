<?php

namespace Mixd\Packer;

use Exception;

use function Deployer\{
    set,
    get,
    localhost,
    host,
    runLocally,
    run,
    commandExist,
    task,
    writeln,
    after,
    cd,
    fail
};

use function Mixd\Packer\config_parser;

require 'recipe/common.php';

// Define the project root
set('abspath', realpath(getcwd()) . '/');

// Load in the deploy.json file
$config = config_parser(realpath(getcwd()) . '/deploy.json');

/**
 * Exit early if there is a problem with the config file. This could either be that it doesnt exist 
 * or the JSON is not valid. Check your Config JSON against the example for a correct schema.
 */
if (!$config) {
    throw new Exception('Unable to parse configuration file, does it exist and is it valid JSON?');
}

set('config', $config);
set('repository', $config['git_repo']);
set('application', $config['wp_sitename']);
set('local_url', $config['wp_home_url']);

// These are new Vars that need rolling out to whole codebase
set('wp_user', $config['wp_user']);
set('wp_email', $config['wp_email']);
set('is_multisite', $config['is_multisite']);
set('rewrite', $config['rewrite']);
set('environments', $config['environments']);

$env_array = get('environments');

/**
 * Get the Local configuration values from the config json.
 */
if ($env_array and isset($env_array['local'])) {
    $local_json = $env_array['local'];

    $host = localhost();
    $host->hostname('localhost');
    $host->set('local', true);
}

/**
 * Setup environmental configuration
 * 
 * Loops through each environmental configuration and sets the correct variables within Deployer
 */
if ($env_array and is_array($env_array)) {
    foreach ($env_array as $env_name => $env_config) {
        if ($env_name !== 'local') {
            $host = host($env_name);
            $host->set('stage', $env_name);
            // Forward Agent tells Deployer to attempt the deploy using the users stored local SSH credentials
            $host->set('forwardAgent', true);

            /**
             * Loop through each environmental configuration and set some values.
             * 
             * Note: hostname and user have their own methods
             */
            foreach ($env_config as $key => $value) {
                if ($key == 'hostname') {
                    $host->hostname($value);
                } elseif ($key == 'deploy_user') {
                    $host->user($value);
                } else {
                    $host->set($key, $value);
                }
            }
        }
    }
}

/**
 * Define and setup the configuration vars for the local and remote environments.
 * 
 * Please note that changing values her can have unexpected consequences and must be tested
 */


// Detect the current operator
set('user', function () {
    try {
        return runLocally('whoami');
    } catch (\Throwable $exception) {
        return 'no_user';
    }
});

// Can a user interactive with the TTY?
set('allow_input', function () {
    $user = get('user');
    // Do not allow interaction for when used with CI/CD
    if ($user != 'runner') {
        return true;
    }

    return false;
});

// Define a list of files that should be shared between deployments
set('shared_files', [
    'wp-config.php',
    '.htaccess',
    'robots.txt'
]);

// Define a list of files that should be copied from the 'templates' folder to the root
set('templates', [
    '.htaccess',
    'robots.txt'
]);

// Should a TTY be opened for Git?
set('git_tty', get('allow_input'));

// Set the directory where shared files are set
set('shared_dir', function () {
    if (get('stage', 'local') == 'local') {
        return get('abspath');
    }

    return get('deploy_path') . '/shared/';
});

// Define a directory that is shared between deployments
set('shared_dirs', [
    'content/uploads',
    'content/w3tc-config' // W3 Total Cache wants to write it's own config to disk
]);

// Define web user writeable directories
set('writable_dirs', [
    'content/uploads',
    'content/w3tc-config' // W3 Total Cache wants to write it's own config to disk
]);

// Use ACL to extend existing permissions
set('writable_mode', 'chgrp'); // chmod, chown, chgrp or acl.
set('writable_chmod_mode', '775');

// Default to only shallow clone
set('git_recursive', false);

// Set apache config options
set('http_user', function () {
    if ($webuser = run("cat /etc/apache2/envvars | grep 'APACHE_RUN_USER'")) {
        $www = explode('=', $webuser);
        return end($www);
    }

    return 'nobody';
});

set('http_group', function () {
    if ($webgroup = run("cat /etc/apache2/envvars | grep 'APACHE_RUN_GROUP'")) {
        $www = explode('=', $webgroup);
        return end($www);
    }

    return 'nobody';
});

// Detect which version of sed is being used on the target
set('bin/sed', function () {
    if (!commandExist('sed')) {
        throw new Exception("sed was not detected in your \$PATH");
    }

    $sed = run("command -v 'sed' || which 'sed' || type -p 'sed'");
    $which_sed = run($sed . ' --version | head -n 1');

    if (strstr($which_sed, 'GNU sed')) {
        return "$sed -i";
    } else {
        return "$sed -i ''";
    }
});

// Detect which version of curl is being used
set('bin/curl', function () {
    if (!commandExist('curl')) {
        throw new Exception("curl was not detected in your \$PATH");
    }

    return run("command -v 'curl' || which 'curl' || type -p 'curl'");
});

// Detect which version of sed is being used on the target
set('bin/wp', function () {
    if (!commandExist('wp')) {
        throw new Exception("wp-cli was not detected in your \$PATH");
    }

    return run("command -v 'wp' || which 'wp' || type -p 'wp'");
});

// Set WP bin to local path
set('bin/wpl', function () {
    return runLocally("command -v 'wp' || which 'wp' || type -p 'wp'");
});

// Detect which version of npm is being used on the target
set('bin/npm', function () {
    if (!commandExist('npm')) {
        throw new Exception("npm was not detected in your \$PATH");
    }

    return run("command -v 'npm' || which 'npm' || type -p 'npm'");
});

// Returns Composer binary path if found
set('bin/composer', function () {
    if (!commandExist('composer')) {
        throw new Exception("composer was not detected in your \$PATH");
    }

    return run("command -v 'composer' || which 'composer' || type -p 'composer'");
});

// Every release should be datetime stamped
set('release_name', date('YmdHis'));

// Try to use git cache where applicable
set('git_cache', true);

// Disable usage data
set('allow_anonymous_stats', false);

// Ensure permissions are set recursively
set('writable_recursive', true);

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Tasks
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

task('deploy', [
    'deploy:info',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:clear_paths',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success'
])->desc('Deploy your project');

fail('deploy', 'deploy:unlock');

/**
 * Log the deployment info into a revisions file in the deployment path
 */
task('signoff', function () {
    $signoff = 'Branch ({{branch}}) deployed by ({{user}}) for release ({{release_name}})';
    cd('{{deploy_path}}');
    run('touch revisions.log');
    run('echo "' . $signoff . '" >> revisions.log');
    writeln('<info>' . $signoff . '</info>');
})->hidden();

/**
 * Helpful debug task
 */
task('debug:info', function () {
    writeln('Current user: <info>' . get('user') . '</info>');
    writeln('Current branch: <info>' . get('branch') . '</info>');
    writeln('Supports TTY: <info>' . (get('allow_input') ? 'Yes' : 'No') . '</info>');
    writeln(
        'Binaries:
         <comment>wp</comment>: ' . get('bin/wp') .
            '<comment>curl</comment>: ' . get('bin/curl') .
            '<comment>npm</comment>: ' . get('bin/npm') .
            '<comment>sed</comment>: ' . get('bin/sed') .
            '<comment>composer</comment>: ' . get('bin/composer') .
            '<comment>php</comment>: ' . get('bin/php') .
            '<comment>git</comment>: ' . get('bin/git') .
            '<comment>symlink</comment>: ' . get('bin/symlink')
    );
});

after('cleanup', 'signoff');

$autoload = array_diff(scandir(realpath(__DIR__) . '/config/tasks/', SCANDIR_SORT_ASCENDING), ['..', '.', '.DS_Store']);

if (!empty($autoload)) {
    array_map(function ($task) {
        require_once realpath(__DIR__) . '/config/tasks/' . $task;
    }, $autoload);
}

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Hide uncommon tasks from the CLI
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

task('deploy:clear_paths')->hidden();
task('deploy:copy_dirs')->hidden();
task('deploy:prepare')->hidden();
task('deploy:release')->hidden();
task('deploy:shared')->hidden();
task('deploy:symlink')->hidden();
task('deploy:update_code')->hidden();
task('deploy:vendors')->hidden();
task('deploy:writable')->hidden();
task('cleanup')->hidden();
