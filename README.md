# PackerWP

Built by [Mixd](https://github.com/Mixd/)

![Mixd logo](https://avatars1.githubusercontent.com/u/2025589?s=75 "Mixd - World Class Web Design")

PackerWP is a custom [Deployer](https://deployer.org/) runbook. It was designed as the successor to the legacy Ruby
deployment tool [WP Deploy](https://github.com/mixd/wp-deploy) and has been tailored to include all the Capistrano
tasks that were previously provided by WP Deploy.

## Available tasks

| Task                   | Description                                                                             |
|------------------------|-----------------------------------------------------------------------------------------|
| backup-local-db        | Backup a copy of a local database and upload it to a remote host                        |
| backup-remote-db       | Backup a copy of a remote database and download it                                      |
| deploy                 | Deploy your project                                                                     |
| rollback               | Rollback to previous release                                                            |
| pull                   | Pull both WordPress uploads and a database from a given host                            |
| pull-remote-db         | Pull down a copy of the database from the remote host and import it into your local env |
| pull-remote-uploads    | Pull media from a remote host                                                           |
| push-local-db          | Push up a local copy of a database and import it into the remote host                   |
| push-local-uploads     | Push media to a remote host                                                             |
| reset-admin-pwd        | Reset the super admin password on the target environment                                |
| setup-local-wp         | Set up your project locally                                                             |
| setup-wp               | Set up your project on the remote host                                                  |
| ssh                    | Connect to host through ssh                                                             |
| debug:task             | Display the task-tree for a given task                                                  |
| deploy:unlock          | Unlock broken deployment                                                                |

This tool has been designed around Mixd's NHS Framework though should be portable enough to drop into any other
WordPress-based project.

## Starting a new project

To get started, you'll need to install PackerWP into your project using composer

```
$ composer require mixd/packerwp
```

In order to use the custom tasks created by Mixd you will need to create a symbolic link which will reference the deploy.php file within the mixd/packerwp composer package. This can done by running the following command at the root of the project.

```
ln -s vendor/mixd/packerwp/deploy.php
```

With PackerWP now installed, you should make sure you have the Deployer binary installed to your local system to make it easier
to run commands.

```
$ curl -LO https://deployer.org/deployer.phar
$ mv deployer.phar /usr/local/bin/dep
$ chmod +x /usr/local/bin/dep
```


### Setting up

PackerWP uses a `deploy.json` file in the root of your project to define all of the environment config.

Run `cp ./vendor/mixd/packerwp/config/deploy.example.json ./deploy.json` to get a copy of the accepted config rules added to the root of your project.

Go ahead and start populating it.

Now, add a symbolic link to the root of the project to link up the custom Deployer tasks. `ln -s vendor/mixd/packerwp/deploy.php`.

You should now be able to run `dep` and see a list of all the available tasks.

### Tasks

#### Initial WordPress Installation
Once you have your environment set up, you can install WordPress by running

```
$ dep setup-local-wp
```

This task will generate a wp-config.php for you and place it in the project root, then it will run the WordPress installation, reset the WordPress Salts and present you with the new admin password and a link to the WordPress Admin.

>If you need to re-roll the admin password, you can reset it at anytime by running
>
>`$ dep reset-admin-pwd [stage]`
>
> if you omit `[stage]` you will reset the password for the local environment, otherwise you can supply `staging` or
`production` appropriately.
>
>Note: This task will also re-roll the WordPress salts.

#### Working with databases

Occasionally you may wish to push or pull your MySQL Database to a specific environment.

Ensure that you have populated the `wp_home_url` and `db_$` entries in your `deploy.json` for your target environment.

To download a copy of the database from a remote host and import it into your local environment you can run:
```
$ dep pull-remote-db [stage]
```

To upload your local database and import it into the remote host you can run:
```
$ dep push-local-db [stage]
```
>Both the `pull` and `push` tasks will handle searching and replacing the necessary URLs to convert from `local` to
`[stage]` or from `[stage]` to `local`.

If you wish to simply make a backup without importing or replacements, you can run:
```
$ dep backup-remote-db
```
or
```
$ dep backup-local-db
```
each of these tasks will make a MySQL Backup using `wp db export` and gzip it into a `db_backups` folder in the project root.

#### Working with uploaded media

To download the WordPress upload folder from a remote host run:
```
$ dep pull-remote-uploads [stage]
```

To upload your local WordPress upload folder to a remote host run:
```
$ dep push-local-uploads [stage]
```

#### Deployment

If you're ready to deploy your work to a remote host, simply run:
```
$ dep deploy [stage]
```

By default, PackerWP is configured to name every release using PHP's
[date](https://www.php.net/manual/en/function.date.php) function in the following format `Ymd`.

## Templates

PackerWP also supports wp-config.php extras. If you want to override the WP Config extras that get added during the `dep setup-wp` task then do the following:

Create the templates folder: `mkdir ./templates/`

Copy the bundled WP Extras file: `cp vendor/mixd/packerwp/config/templates/extras.php ./templates/extras.php`

Now you can edit the extras file. Be sure to commit and push this file to Github so it persists for other developers.

## Contributing

If you'd like to request changes or additional tasks we encourage you to create a Pull Request on the
[Github repo](https://github.com/mixd/packerwp).
