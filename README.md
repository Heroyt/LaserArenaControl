# Laser arena control

[![PHP Unit test](https://github.com/Heroyt/LaserArenaControl/actions/workflows/php_test.yml/badge.svg)](https://github.com/Heroyt/LaserArenaControl/actions/workflows/php_test.yml)

Vojík Tomáš <xvojik00@stud.vutbr.cz>, <vojik@wboy.cz>

## Installation

First, you need to set up the app's config file.

Copy the `/private/config_dummy.ini` file to `/private/config.ini` and change the necessary DB connection configuration.

Then, install the application by calling:

```shell
$ composer install-app
```

This will install all dependencies, build webpack assets (css and js), create all DB tables and seed it with starting
data.

Lastly, you can start the PHP server with:

```shell
$ composer serve
```

*(You don't have to start the server if you have Apache setup already)*

## Useful commands

```shell
$ composer build
```

Installs composer dependencies, installs npm dependencies, builds webpack assets.

```shell
$ composer build-production
```

Installs composer dependencies, installs npm dependencies, builds webpack assets and removes dev-dependencies.

```shell
$ composer install-app
```

Runs `composer build` and installs database - creating DB tables and seeding some data.

```shell
$ composer test
```

Runs unit tests.

```shell
$ composer serve
```

Starts PHP server on port `8000`.

```shell
$ composer docs
```

Generates doxygen API docs.

```shell
$ npm run build
```

Builds webpack assets in production mode.

```shell
$ npm run build-dev
```

Builds webpack assets in development mode.

```shell
$ npm run watch
```

Builds webpack assets. Watches assets changes and auto-builds on save.
