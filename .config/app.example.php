<?php
namespace Deployer;

////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// Setup project
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// The WordPress admin user
set('wp_user', 'username');

// The WordPress admin email address
set('wp_email', 'email');

// The WordPress 'Site Title' for the website
set('wp_sitename', 'sitename');

# The local environment URL.
set('wp_localurl', 'http://site.local');

// Set the default stage for deployment
set('default_stage', 'local');

// An identifying name for the application to be used by Capistrano
set('application', 'site.local');
set('repository', '');
