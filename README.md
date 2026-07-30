# CodeIgniter 4 Application Starter

## What is CodeIgniter?

CodeIgniter is a PHP full-stack web framework that is light, fast, flexible and secure.
More information can be found at the [official site](https://codeigniter.com).

This repository holds a composer-installable app starter.
It has been built from the
[development repository](https://github.com/codeigniter4/CodeIgniter4).

More information about the plans for version 4 can be found in [CodeIgniter 4](https://forum.codeigniter.com/forumdisplay.php?fid=28) on the forums.

You can read the [user guide](https://codeigniter.com/user_guide/)
corresponding to the latest version of the framework.

## Installation & updates

`composer create-project codeigniter4/appstarter` then `composer update` whenever
there is a new release of the framework.

When updating, check the release notes to see if there are any changes you might need to apply
to your `app` folder. The affected files can be copied or merged from
`vendor/codeigniter4/framework/app`.

## Setup (sdp-api-ci-2 local)

This API is intended to share the **legacy MariaDB** schema with CI2 (`db_sdp`),
not a greenfield empty database.

```bash
# 1. Dependencies
composer install

# 2. Environment (copy the example — .env is gitignored)
cp .env.example .env
# Edit app.baseURL / DB credentials if your ports differ

# 3. Shared MariaDB (sibling 102sdp stack — OrbStack/Docker)
#    See ../102sdp/docs/LOCAL_DEV_DB.md
#    Host: 127.0.0.1:3307  DB: db_sdp  user: sdp / sdp_local
cd ../102sdp
docker compose -f docker-compose.mariadb.yml up -d
./scripts/restore-db.sh    # first time
cd ../sdp-api-ci-2

# 4. App-only seed data (users / RBAC) — do NOT blindly migrate all greenfield
#    tables onto db_sdp. Prefer team-documented migrate/seed steps, then:
# php spark db:seed RbacSeeder
# php spark db:seed DemoAuthSeeder

# 5. Run API + smoke
php spark serve --host 127.0.0.1 --port 8082
./scripts/api.sh login
ORG_ID=<unit-org-id> ./scripts/api.sh wbp
php spark legacy:smoke-r01 --registrasi
```

**Env files**

| File | Purpose |
|------|---------|
| **`.env.example`** | Ready-to-copy local defaults for shared `db_sdp` (commit this) |
| **`.env`** | Your machine-only config (never commit) |
| **`env`** | Upstream CI4 full commented template |

Migration notes: `docs/MIGRATION_STRATEGY.md`, `docs/migration/`.

## Important Change with index.php

`index.php` is no longer in the root of the project! It has been moved inside the *public* folder,
for better security and separation of components.

This means that you should configure your web server to "point" to your project's *public* folder, and
not to the project root. A better practice would be to configure a virtual host to point there. A poor practice would be to point your web server to the project root and expect to enter *public/...*, as the rest of your logic and the
framework are exposed.

**Please** read the user guide for a better explanation of how CI4 works!

## Repository Management

We use GitHub issues, in our main repository, to track **BUGS** and to track approved **DEVELOPMENT** work packages.
We use our [forum](http://forum.codeigniter.com) to provide SUPPORT and to discuss
FEATURE REQUESTS.

This repository is a "distribution" one, built by our release preparation script.
Problems with it can be raised on our forum, or as issues in the main repository.

## Server Requirements

PHP version 8.2 or higher is required, with the following extensions installed:

- [intl](http://php.net/manual/en/intl.requirements.php)
- [mbstring](http://php.net/manual/en/mbstring.installation.php)

> [!WARNING]
> - The end of life date for PHP 7.4 was November 28, 2022.
> - The end of life date for PHP 8.0 was November 26, 2023.
> - The end of life date for PHP 8.1 was December 31, 2025.
> - If you are still using below PHP 8.2, you should upgrade immediately.
> - The end of life date for PHP 8.2 will be December 31, 2026.

Additionally, make sure that the following extensions are enabled in your PHP:

- json (enabled by default - don't turn it off)
- [mysqlnd](http://php.net/manual/en/mysqlnd.install.php) if you plan to use MySQL
- [libcurl](http://php.net/manual/en/curl.requirements.php) if you plan to use the HTTP\CURLRequest library
