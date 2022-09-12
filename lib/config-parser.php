<?php

namespace Mixd\Packer;

use Exception;

//require 'recipe/common.php';

/**
 * Config parser
 * 
 * Parses a json config file and returns the values as a simplified PHP array.
 *
 * @param string $config_file the path to the config file e.g. realpath(getcwd()) . '/deploy.json'
 * @return array a PHP array containing the variables stored in the config file
 */
function config_parser($config_file)
{
    if (file_exists($config_file) == false) {
        throw new Exception('Unable to find configuration file at ' . $config_file);
    }

    $json_config = file_get_contents($config_file);

    if ($json_config == false) {
        throw new Exception('Unable to read config.json. Check your current user has permission to read the file');
    }

    $json = json_decode($json_config, true, 12);

    if (!$json) {
        throw new Exception('Unable to parse config.json. The file must be valid JSON');
    }

    $config = [
        'git_repo' => $json['git_repo'],
        'wp_user' => $json['wordpress']['wp_user'],
        'wp_email' => $json['wordpress']['wp_email'],
        'wp_sitename' => $json['wordpress']['wp_sitename'],
        'wp_home_url' => $json['wordpress']['wp_home_url'],
        'is_multisite' => $json['wordpress']['is_multisite'],
        'rewrite' => $json['rewrite'],
        'environments' => $json['environments']
    ];

    return $config;
}
