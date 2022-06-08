# OpenEuropa Newsroom

The OpenEuropa oe_newsroom provides integration with the Newsroom service.

The main module offers a user interface to configure the required parameters for using the Newsroom API.

## Usage

In order to start using the module, you will need:
- universe;
- application (name);
- hash method;
- normalisation;
- private key.

These are provided by the Newsroom team. All except the private key can be configured by a user with the `administer newsroom configuration` permission
at the page `/admin/config/system/newsroom-settings`.

The private key must be configured in settings.php due the sensitive nature.\
It is recommended to add to the website `runner.yml` file the following line:
```
$settings['oe_newsroom']['newsroom_api_key'] = getenv('NEWSROOM_API_PRIVATE_KEY');
```
Then for local development the environment variable `NEWSROOM_API_PRIVATE_KEY` can be set in the `docker-compose.override.yml` file.

**NEVER commit the private key into GIT!**

Create a DevOps ticket to set the environment variable `NEWSROOM_API_PRIVATE_KEY` for acceptance and production environments.

## Sub-modules

### OpenEuropa Newsroom Newsletter

Provides configurable blocks to subscribe and unsubscribe to distribution lists.

## Limitations

- The oe_newsroom_newsletter sub-module contains a basic client that implements a subset of Newsroom APIs. This client will be moved to a
dedicated PHP library. For this reason, the client and all the classes that depend on it have been marked as `@internal`.
The client has not been declared as service to discourage using it as dependency.
- The unsubscribe Newsroom API must be invoked once for each distribution list. To avoid locking the website for a long time while
executing the requests, the maximum number of distribution lists that can be specified is limited to 5.

## Development setup

You can build the development site by running the following steps:

* Install the Composer dependencies:

```bash
composer install
```

A post command hook (`drupal:site-setup`) is triggered automatically after `composer install`.
This will symlink the module in the proper directory within the test site and perform token substitution in test configuration files.

**Please note:** project files and directories are symlinked within the test site by using the
[OpenEuropa Task Runner's Drupal project symlink](https://github.com/openeuropa/task-runner-drupal-project-symlink) command.

If you add a new file or directory in the root of the project, you need to re-run `drupal:site-setup` in order to make
sure they are be correctly symlinked.

If you don't want to re-run a full site setup for that, you can simply run:

```
$ ./vendor/bin/run drupal:symlink-project
```

* Install test site by running:

```bash
$ ./vendor/bin/run drupal:site-install
```

The development site web root should be available in the `build` directory.

### Using Docker Compose

Alternatively, you can build a development site using [Docker](https://www.docker.com/get-docker) and
[Docker Compose](https://docs.docker.com/compose/) with the provided configuration.

Docker provides the necessary services and tools such as a web server and a database server to get the site running,
regardless of your local host configuration.

#### Requirements:

- [Docker](https://www.docker.com/get-docker)
- [Docker Compose](https://docs.docker.com/compose/)

#### Configuration

By default, Docker Compose reads two files, a `docker-compose.yml` and an optional `docker-compose.override.yml` file.
By convention, the `docker-compose.yml` contains your base configuration and it's provided by default.
The override file, as its name implies, can contain configuration overrides for existing services or entirely new
services.
If a service is defined in both files, Docker Compose merges the configurations.

Find more information on Docker Compose extension mechanism on [the official Docker Compose documentation](https://docs.docker.com/compose/extends/).

#### Usage

To start, run:

```bash
docker-compose up
```

It's advised to not daemonize `docker-compose` so you can turn it off (`CTRL+C`) quickly when you're done working.
However, if you'd like to daemonize it, you have to add the flag `-d`:

```bash
docker-compose up -d
```

Then:

```bash
docker-compose exec web composer install
docker-compose exec web ./vendor/bin/run drupal:site-install
```

Using default configuration, the development site files should be available in the `build` directory and the development site
should be available at: [http://127.0.0.1:8080/build](http://127.0.0.1:8080/build).

#### Running the tests

To run the grumphp checks:

```bash
docker-compose exec web ./vendor/bin/grumphp run
```

To run the phpunit tests:

```bash
docker-compose exec web ./vendor/bin/phpunit
```

#### Step debugging

To enable step debugging from the command line, pass the `XDEBUG_SESSION` environment variable with any value to
the container:

```bash
docker-compose exec -e XDEBUG_SESSION=1 web <your command>
```

Please note that, starting from XDebug 3, a connection error message will be outputted in the console if the variable is
set but your client is not listening for debugging connections. The error message will cause false negatives for PHPUnit
tests.

To initiate step debugging from the browser, set the correct cookie using a browser extension or a bookmarklet
like the ones generated at https://www.jetbrains.com/phpstorm/marklets/.

## Contributing

Please read [the full documentation](https://github.com/openeuropa/openeuropa) for details on our code of conduct, and the process for submitting pull requests to us.

## Versioning

We use [SemVer](http://semver.org/) for versioning. For the available versions, see the [tags on this repository](https://github.com/openeuropa/oe_newsroom/tags).
