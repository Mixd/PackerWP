<?php

namespace Deployer;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// WordPress uploads folder related tasks
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

task('pull-remote-uploads', function () {
    download(
        "{{current_path}}/content/uploads/",
        get('abspath') . "/content/uploads/",
        ["flags" => "rzcE"]
    );
})->desc('Pull media from a remote host');

task('push-local-uploads', function () {
    upload(
        get('abspath') . "/content/uploads/",
        "{{current_path}}/content/uploads/",
        ["flags" => "rzcE"]
    );
})->desc('Push media to a remote host');
