<?php
namespace Deployer;

/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//// WordPress uploads folder related tasks
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

desc('Pull media from a remote host');
task('pull-remote-uploads', function () {
    download(
        "{{current_path}}/content/uploads/",
        "content/uploads/",
        ["flags" => "rzcE"]
    );
    write("Completed");
});

desc('Push media to a remote host');
task('push-local-uploads', function () {
    upload(
        "content/uploads/",
        "{{current_path}}/content/uploads/",
        ["flags" => "rzcE"]
    );
    write("Completed");
});
