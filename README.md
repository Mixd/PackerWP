# PackerWP

Built by [Mixd](https://github.com/Mixd/)

![Mixd logo](https://avatars1.githubusercontent.com/u/2025589?s=75 "Mixd - World Class Web Design")

**Latest stable version: 1.1.0**

PackerWP is a custom [Deployer](https://deployer.org/) runbook. It was designed as the successor to the legacy Rails deployment tool [WP Deploy](https://github.com/mixd/wp-deploy) and has been tailored to include all the Capistrano tasks that were previously provided by WP Deploy.

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

## Starting a new project

To get started, create a new project with Composer. This command will download PackerWP, it's dependencies, and  WordPress into the `wordpress` directory.

```
$ composer create-project mixd/packerwp --stability=dev
```

Next, you will need to ensure you have created a MySQL database for your project.

Inside the `config` folder you will see an `env` example file. Duplicate and rename it to `.env`. Now fill in your environment options.

The minimum required fields are:

    'WP_USER'
    'WP_EMAIL'
    'WP_SITENAME'
    'WP_LOCALURL'
    'REPOSITORY'
    'LOCAL_DB_HOST'
    'LOCAL_DB_NAME'
    'LOCAL_DB_USER'
    'LOCAL_DB_PASS'

Now you are ready to set up your local WordPress environment.

### Setting up

You can start by running

```
$ dep setup-local-wp
```

This task will copy the `wp-config.example.php`, `.htaccess` and `robots.txt` from the `config/templates/local/` folder and place them in the project root.

It will then populate the `wp-config.php` file with the local environment variables that are defined in your `.env` file.

Finally, it will run the WordPress installation, reset the WordPress Salts and present you with the new admin password and a link to the WordPress Admin.

>If you need to re-roll the admin password, you can reset it at anytime by running
>
>`$ dep reset-admin-pwd [stage]`
>
> if you omit `[stage]` you will reset the password for the local environment, otherwise you can supply `staging` or `production` appropriately.
>
>Note: This task will also re-roll the WordPress salts.

### Working with databases

Occasionally you may wish to push or pull your MySQL Database to a specific environment.

Ensure that you have populated the `STAGE_URL` and `DB_$` entries in your `.env` for your target environment.

To download a copy of the database from a remote host and import it into your local environment you can run:
```
$ dep pull-remote-db [stage]
```

To upload your local database and import it into the remote host you can run:
```
$ dep push-local-db [stage]
```
>Both the `pull` and `push` tasks will handle searching and replacing the necessary URLs to convert from `local` to `[stage]` or from `[stage]` to `local`.

If you wish to simply make a backup without importing or replacements, you can run:
```
$ dep backup-remote-db
```
or
```
$ dep backup-local-db
```
each of these tasks will make a MySQL Backup using `wp db export` and gzip it into a `db_backups` folder in the project root.

### Working with uploaded media

To download the WordPress upload folder from a remote host run:
```
$ dep pull-remote-uploads [stage]
```

To upload your local WordPress upload folder to a remote host run:
```
$ dep push-local-uploads [stage]
```

### Deployment

If you're ready to deploy your work to a remote host, simply run:
```
$ dep deploy [stage]
```

By default, PackerWP is configured to name every release using PHP's [date](https://www.php.net/manual/en/function.date.php) function in the following format `YmdHis`.

## Contributing

If you'd like to request changes or additional tasks we encourage you to create a Pull Request on the [Github repo](https://github.com/mixd/packerwp).
