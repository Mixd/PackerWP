<?php

namespace Deployer;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// WordPress uploads folder related tasks
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Pull remote 'upload' directory
 *
 * Downloads any files within the /content/uploads/ directory on the remote host
 */
task('pull-remote-uploads', function () {
    download(
        "{{current_path}}/content/uploads/",
        get('abspath') . "/content/uploads/",
        ["options" => ["flags" => "-rch"]]
    );
})->desc('Pull media from a remote host');

/**
 * Push local 'upload' directory
 *
 * Uploads any files within the /content/uploads/ directory on your local
 */
task('push-local-uploads', function () {
    upload(
        get('abspath') . "/content/uploads/",
        "{{current_path}}/content/uploads/",
        ["options" => ["flags" => "-rch"]]
    );
})->desc('Push media to a remote host');
