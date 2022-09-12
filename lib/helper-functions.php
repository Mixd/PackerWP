<?php

use Deployer;


/**
 * Get an array of environment vars
 *
 * @param string $stage
 * @return array
 */
function get_env_vars(string $stage)
{
    $config = Deployer\get('config');
    return $config['environments'][$stage];
}

/**
 * Get an array of default configuration options
 *
 * @return array
 */
function get_config()
{
    $config = Deployer\get('config');
    return $config['wordpress'];
}
