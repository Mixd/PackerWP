# PackerWP

Built by [Mixd](https://github.com/Mixd/)
## ![Mixd logo](https://avatars1.githubusercontent.com/u/2025589?s=75 "Mixd - World Class Web Design")

PackerWP is a custom [Deployer](https://deployer.org/) runbook. It was designed as the successor to the legacy Rails deployment tool [WP Deploy](https://github.com/mixd/wp-deploy) and has been tailored to include all the Capistrano tasks that were previously provided by WP Deploy.

## Setup

Create a new project with Composer.

```
$ composer create-project mixd/packerwp
```

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
