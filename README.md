# PackerWP

PackerWP is a deployment tool built using [Deployer](https://deployer.org/). It was designed as the successor to the legacy Rails deployment tool [WP Deploy](https://github.com/mixd/wp-deploy).

## Setup

### Prerequisites
In order to make use of PackerWP you will need to have a version of Deployer installed. Follow the [instructions from deployer.org](https://deployer.org/docs/installation.html).

### Standard setup
1. Download the latest release from Github and unzip it into your project.
2. Rename `database.example.json` to `database.json` and populate it with the appropriate database credentials.
3. Rename `app.example.php` to `app.php` and populate the appropriate values for your project.
4. Inside the `.config/deploy` folder, rename `production.example.php` to `production.php` and `staging.example.php` to `staging.php`. Populate both of these files with your specific environment values.

### Migrating from WP Deploy
If you are migrating a project away from WP Deploy to make use of PackerWP then you will be pleased to hear there are only minor changes that you need to make.

In addition to `deploy.php` which registers the custom deployment tasks, all of the environment config files and variables are contained within a `.config` folder at the root of the project.

Within `.config/deploy/` are two example environment PHP files. Duplicate these into `production.php` and `staging.php`. These files are intended to replace the Ruby files from WP Deploy.

The individual deployment "tasks" are stored within `./config/tasks` and these can be added to, or removed, if you desire.

"Shared files" like `.htaccess`, `robots.txt` and `wp-config.php` can be found in `.config/templates/` in the relevant environment folder. To avoid conflicts with existing `.gitignore` rules the `wp-config.php` template has been renamed to `wp-config.example.php`

>Note: We have opted to remove the `.erb` file suffix from the template files to promote better syntax highlighting.

>Note: Within the `wp-config.php` template you are not expected to populate the WP Salts. These will be generated during a deployment.

The `deploy.rb` from WP Deploy has been renamed to `app.php` and an example file can be found in the `.config` folder.

The `database.yml` has been renamed to `database.json` to improve on compatibility as the deployment tool is now written in PHP and an example file can be found in the `.config` folder.

## Usage
Open a Terminal in the same directory as `deploy.php` (This should be the root of your project).

The tasks that are available to you are listed below:

| Task | Description |
| ------------- | ------------- |
| deploy | Deploy your project |
| rollback | Rollback to previous release |
| backup-local-db | Backup a copy of a local database and upload it to a remote host |
| backup-remote-db | Backup a copy of a remote database and download it |
| pull-remote-db | Pull down a copy of the database from the remote host and import it into your local env |
| pull-remote-uploads | Pull media from a remote host |
| push-local-db | Push up a local copy of a database and import it into the remote host |
| push-local-uploads | Push media to a remote host |
| self-update | Updates deployer.phar to the latest version |
| setup-wp | Set up your project on the remote host |
| setup-local-wp | Set up your project locally |
| update-wp | Update your local version of WordPress |

You can run any of these tasks by running
```
$ dep <TASK>
```
This will default to running the TASK for the `staging` environment. If you wish to be more specific you can include the environment in your command.
```
$ dep <TASK> <ENV>
```
