# HKFree UserDB
Powerful [HKFree.org](http://www.hkfree.org) user and IP management system

Developed by powerful team - Evil, ZitnyP, Bkralik & pavkriz

## Before you begin

- PHP **8.2.x** is required (`~8.2.0` in `composer.json`).
- Install [Flox](https://flox.dev/docs/install-flox/). The project environment provides PHP 8.2 and Composer; see [Tests with Flox](#tests-with-flox) for setup. Run `flox activate` from the repository root before using the local PHP and Composer commands below.
- Install [Docker](https://www.docker.com/get-started) for the Docker Compose setup described below. The test workflow runs directly in Flox.

## Installation

```bash
git clone https://github.com/HKFree/UserDB.git
cd UserDB
composer install
cp app/config/config.local.DIST.neon app/config/config.local.neon
vi app/config/config.local.neon www/.htaccess docker-compose.yml
php bin/console migrations:continue
```

## Development

### Tests with Flox

Tests run in the project's Flox environment (tested with Flox 1.19.4). The committed environment provides PHP 8.2 with the PCOV coverage driver, Composer, MariaDB 10.11.19 and LibreOffice for Apple Silicon macOS and x86_64 Linux, including CI.

Check availability with `flox --version`. If Flox is missing, install the current release:

```sh
curl -fsSL https://get.flox.dev | sh
flox --version
```

See the [installation instructions](https://flox.dev/docs/install-flox/) for platform-specific options and the [Flox in 5 minutes tutorial](https://flox.dev/docs/flox-5-minutes) for an introduction.

From the root of a fresh checkout without real credentials, use the committed environment:

```sh
flox activate -- composer install --no-interaction --prefer-dist --no-progress
flox activate -s -- vendor/bin/tester -C tests
flox activate -- vendor/bin/phpstan analyse --no-progress --memory-limit=512M
flox activate -- vendor/bin/latte-lint app
```

The test container loads `app/config/config.neon` and `tests/config.test.neon` with a synthetic environment. Mail, SMS and contract requests use recording doubles; the IDS connector, Sojka pinger, outer scanner, IGW IP checkers, MEX and InfluxDB URLs point at `http://127.0.0.1:0/`. It uses in-memory session and user storage and does not read `config.local.neon` or `USERDB_DB_*` variables.

`-s` starts MariaDB. Database tests use the restricted `userdb_test` account and database on the Flox Unix socket. The schema is built from migrations in `db/structures` and `db/basic-data`, without dummy data, and rebuilt automatically when migrations change. Database tests run serially and roll back their data after each case. A guard checks the database name and MariaDB data directory and refuses a database outside the checkout. Docker is not needed.

The suite covers:

- Container services, routing, authentication, API access and representative presenter pages and permissions.
- Permission matrices for admin tools, rights, AP/member editing, messages and grid access, with recorded side effects.
- Message forms for a whole AP with no, invalid and valid recipients.
- AP subnet rows with the ARP proxy rule, administration grid pages and TV service activation.
- Member edit form validation, saves and audit logs, IP/password handling, registration and planned membership transitions.
- Schema migrations, tag operations and constraints, account activation and ledger entries, and audit-log lookup of deleted IPs and subnets.
- Subnet calculations and lookups, address lists for bulk adding, generated passwords and API keys, downloads, SSID rendering, QR generation and user merge balances.
- Local mail capture and contract PDF conversion, including Czech text, optional fields and cleanup.

**Adding a test:** Name the file `*Test.php`, `*DatabaseTest.php` for database tests or `*PresenterTest.php` for presenter tests, and require `tests/bootstrap.php`. Nette Tester runs only `*Test.php` files; `TestNamingTest` fails for a file it would skip. Extend `Test\DatabaseTestCase` for database or presenter tests, create rows through `Test\Fixtures` and run presenters through `Test\PresenterRunner`.

PHPStan runs at **level 5** with the Nette extension and without a baseline. It checks application code, PHP entry points and tests. It does not need the test environment variables or local configuration.

The [phpstan-strict-rules](https://github.com/phpstan/phpstan-strict-rules) extension is enabled as well. Five of its rule groups are switched off in `phpstan.neon` because the current code does not pass them yet: `disallowedLooseComparison`, `booleansInConditions`, `disallowedEmpty`, `strictFunctionCalls` and `requireParentConstructorCall`. `disallowedShortTernary` is switched off for good: the short ternary stays allowed for default values such as `getenv('X') ?: 'default'`. The remaining groups keep the extension's defaults and must pass.

Methods that Kdyby Replicator adds at runtime are described in `phpstan/replicator.stub`. The only suppressed finding is one inline `@phpstan-ignore` in `pdfGenerator/www/ContractPdf.php`, where PHPStan's `proc_open` signature rejects a valid descriptor.

**Code coverage:** `flox activate -s -- scripts/userdb_test_coverage.sh` runs the suite with PCOV and writes the line coverage of the PHP files in `app`, `pdfGenerator/www` and `www/digisign-webhook` to `temp/coverage/coverage.html`; Latte templates are not measured. Pass a path ending in `.xml` to get a Clover report instead. CI runs the same script after the tests, prints the percentage in the job summary and keeps the HTML report as the `coverage-report` artifact for 14 days. No threshold is enforced, and a failure of the coverage step or of the report upload does not fail the job: it shows as a warning annotation on the run.

CI follows the [official Flox CI/CD tutorial](https://flox.dev/docs/tutorials/ci-cd): install Flox and run the same commands against the committed environment and lockfile, without FloxHub credentials.

### Local web, database and PDF with Flox

Each worktree has its own MariaDB data under `.flox/cache/mariadb`, private Unix socket, development database, encryption key, session storage and session cookie name. MariaDB opens no TCP port. Its SQL mode matches the supplied server configuration: `ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION`.

After installing Composer dependencies, run from the worktree root:

```sh
flox activate -s
php scripts/userdb_dev_setup.php
php -S 127.0.0.1:0 -t www scripts/userdb_dev_router.php
```

The OS assigns a free web port and PHP prints the URL. Repeat these commands in another worktree to run both simultaneously. The setup command applies existing migrations and demo data to `userdb_dev`; it can be repeated without resetting data. The local migration adapter replaces the historical production definer with the local account, without changing the migration files. The web uses `config.flox.neon` and the existing demo identity, preserving `config.local.neon`.

The `userdb_dev` / `dev-only` and `userdb_test` / `test-only` credentials are exclusively for these local databases. They cannot access each other's database. Local email is captured as `.eml` files under `.flox/cache/mail`; contract storage lives under `.flox/cache/storage`. Services stop when the last Flox activation exits; database data and keys remain. Inspect MariaDB with `flox services status` inside Flox and logs in `.flox/cache/logs/`.

Contract previews work directly in the local application. Flox provides LibreOffice, zip/unzip and Poppler for PDF checks. The local bootstrap enables direct conversion without a separate HTTP service or PDF port. Each conversion uses a private directory and LibreOffice profile under `.flox/cache/pdf`, cleaned up on success or failure. Templates come from this worktree, and previews are marked `LOCAL TEST`. Font substitution can differ from production; the test checks content, not pixel-identical rendering. Outside this local bootstrap, the application continues to use `PDF_GENERATOR_URL`.

PHP extensions are included and frontend assets are already committed, so no Node build is required. IDS, InfluxDB, SMS and network-check endpoints are disconnected in the local configuration, so their external data/functions are unavailable. SledovaniTV, accounting reports and Fio imports have separate Python/Perl workers and external credentials; they are not started by this local web setup. External signing and other real integrations are not part of the local PDF preview.

### Editor

Please use [editor or IDE that obeys .editorconfig settings](http://editorconfig.org/#download)

#### VSCode PHP Formatting

To activate formatting in VSCode, install extension "[php cs fixer](https://marketplace.visualstudio.com/items?itemName=junstyle.php-cs-fixer)". The extension will obey the rules set in `.vscode/settings.json` and `.php-cs-fixer.dist.php`. Formatting will be done on every filesave.

Configure the formatter to use PHP 8.2. PHP CS Fixer is included in the project's Composer development dependencies; its CLI is available through `flox activate -- vendor/bin/php-cs-fixer`.

Tests are ordinary `.php` files and fall under the shared configuration. Run the same code style check as CI:

```sh
flox activate -- vendor/bin/php-cs-fixer check --diff
```

### Run locally

Override environment variables defined in `docker-compose.yml` using `docker-compose.override.yml` when necessary. Don't forget that some settings are still present in `app/config/config.local.neon`.

```bash
docker compose build
docker compose up
docker compose exec web composer install
docker compose exec web chmod 777 -R log
docker compose exec web chmod 777 -R temp
docker compose exec web chmod 777 -R vendor/mpdf/mpdf/tmp
cp app/config/config.local.DIST.neon app/config/config.local.neon
docker compose exec web php bin/console migrations:continue
```

Now the app is up and running in Docker on host's port 10107, PhpMyAdmin on host's port 10108.
If you don't know your docker's IP, `docker-machine list` is your friend.

### Build, commit

```bash
git pull origin master
composer install
php bin/console migrations:continue
# develop your freaking cool feature
git pull origin master
git push origin master
```


## Deployment

Useful stuff to fix several Nette's gotchas: [Nedostatky Nette při přechodu ze Symfony2](https://quip.com/1DAjAVxx9gZ8)

Run `git remote add production ssh://user@userdb.hkfree.org/opt/UserDB.git` first time (replace user with your username at userdb.hkfree.org).

Run `git push production master` in order to fully deploy the app.

When something goes wrong, try to run `(cd /opt/UserDB.git; hooks/post-receive)` manually on the server.

See [git-hooks/post-receive.sample](git-hooks/post-receive.sample) for more details what happens during deployment on server side.

### Testing server

A shared testing server runs one instance for `master` and one for every open pull request from a branch of
this repository, each under its own host name: `master.<domain>` and `pr-<number>.<domain>`. An instance runs
the same way as the [local web with Flox](#local-web-database-and-pdf-with-flox): `config.flox.neon`, demo data,
mail captured to files, and every visitor signed in as the demo user. All instances sit behind a reverse proxy
that lets in the HKFree network without a password and asks everyone else for HTTP Basic; the maintainers
have the password.

1. [deploy-testing.yml](.github/workflows/deploy-testing.yml) runs on a push to `master` and when a pull
   request is opened, updated, reopened or closed. It connects to the server over SSH and sends one request:
   deploy a commit as an instance, or remove the instance of a closed pull request. Nothing is built on the
   GitHub runner. The run summary links the instance.
2. The connection uses `secrets.TESTING_SSH_KEY`, `vars.TESTING_SSH_HOST`, `vars.TESTING_SSH_USER` and the
   server's host key in `vars.TESTING_SSH_KNOWN_HOSTS`; `vars.TESTING_DOMAIN` is the domain of the instances.
   On the server the key can only run [scripts/userdb_testing_deploy.sh](scripts/userdb_testing_deploy.sh),
   installed as `/usr/local/bin/userdb-testing-deploy`.
3. The script accepts only a commit that is on a branch of this repository. It checks the commit out as a git
   worktree in `/srv/userdb-testing/instances/<instance>`, installs the Composer dependencies, **deletes the
   database of that instance and rebuilds it** from the migrations and demo data of the commit, and runs
   MariaDB and the web as systemd user services of the deploy account. The web of `pr-<number>` listens on
   `127.0.0.1:<10000 + number>`, the web of `master` on `127.0.0.1:10000`.

Things to know:

- Data entered in an instance lasts until its next deployment. Captured mail, sessions and logs of the
  instance stay.
- The server keeps at most six pull request instances. A seventh replaces the one deployed longest ago;
  re-run the last workflow run of a pull request to get its instance back.
- An instance runs the head commit of the pull request, not its merge with `master`. A branch from before
  the Flox setup of the local web (`scripts/userdb_dev_setup.php`) is refused until it is updated from
  `master`.
- GitHub does not run the workflow for a pull request that conflicts with `master`. Its instance stays as it
  was, also after the pull request is closed, until the conflict is resolved or a newer instance replaces it.
- A failed deployment stops the services of its instance; the run log shows the reason.
- The domain of the instances has to resolve every `pr-<number>` name to the server. The current server
  uses a wildcard DNS service for that, so a new instance needs no DNS change.
- A pull request from a fork is never deployed, because its code would run on the server unreviewed. To test
  it, a maintainer pushes its commits to a branch of this repository and opens a pull request from there.
- A change of the script takes effect only after the new version is installed on the server by hand.

The server needs Flox, git, curl, the reverse proxy and an unprivileged deploy account with lingering enabled
(`loginctl enable-linger <account>`) that owns `/srv/userdb-testing`. The script is installed owned by root,
and the deploy key is kept in a root-owned file that sshd reads for that account
(`AuthorizedKeysFile /etc/ssh/authorized_keys/%u`), so that deployed code cannot change either:

```
command="/usr/local/bin/userdb-testing-deploy",restrict ssh-ed25519 <public key>
```

Deployed code runs as the deploy account, so the server must not let that account reach anything that is
not meant for testing: `config.flox.neon` disconnects the external services it knows about, but for example
the Wewimo reader connects to whatever address is stored in the database. On the current server a firewall
rule rejects connections of the deploy account to private networks.

The reverse proxy maps `pr-<number>.<domain>` to the port above and obtains a certificate for a host name
only after the script has created `/srv/userdb-testing/deployed/<instance>`. It recognises the HKFree network
by the address of the connecting client (the internal addresses and the public prefixes of HKFree), never by
a forwarded header.

### Jobs to be run

Run `php www/index.php app:update_locations` regularly in order to update users' locations based on their addresses.

Crontab record example: `*/5 * * * * (docker exec userdb php www/index.php app:update_locations) 2>&1 | /usr/bin/logger -t userdb_locations`

## Making DB changes

###Creating new change-script

Run
`docker compose exec web php bin/console migrations:create s short-description-of-the-change`
and edit the change-script created.

- s = structures (applied always)
- b = basic-data (eg. lists-of-values, applied always)
- d = dummy-data (eg. testing records, applied only when `debugMode` is enabled, should not be run on production)

### Applying changes

Make sure the cache is clean by running `rm -rf temp/cache` and run
`php bin/console migrations:continue` (while paying attention to deactivated `debugMode`)
in order to apply the change-scripts to the DB configured in neon config.

#### On development or testing machines

As mentioned - when `debugMode` is enabled (as is default), dummy data are loaded.

You can run `php bin/console migrations:reset` in order to drop all tables in the database and create them from scratch running all
 change-scripts. Run it on dev/test machine in order to test that the whole schema is completely described in change-scripts. DO NOT RUN IN PRODUCTION! WILL DELETE ALL DATA!

#### On moving to nextras/migrations

Before applying migrations to a database that was not versioned before, run the following code (it pretends that all initial change-scripts has been run):
```
CREATE TABLE `migrations` (`id` int(10) UNSIGNED NOT NULL,
  `group` varchar(100) NOT NULL,
  `file` varchar(100) NOT NULL,
  `checksum` char(32) NOT NULL,
  `executed` datetime NOT NULL,
  `ready` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
INSERT INTO `migrations` (`id`, `group`, `file`, `checksum`, `executed`, `ready`) VALUES
(1, 'structures', '2016-10-23-102733-new-init-schema.sql', '107a87552ba751e059e8197a4194ae5e', '2016-10-23 22:38:06', 1),
(2, 'basic-data', '2016-10-23-110900-new-init-data.sql', 'e26dba9c81ad0a52e875c1bc0fc13863', '2016-10-23 22:38:14', 1);
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type_file` (`group`,`file`);
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
```

## Accessing API

[Open swagger.yaml interactive docs](http://petstore.swagger.io/?url=https://raw.githubusercontent.com/HKFree/UserDB/master/swagger.yaml) and try some operations.
You'll need the credentials (click Authorize in interactive docs). Create credentials in UserDB: Oblast - Zobrazit podrobnosti AP - Editovat - API klíče.

## License

- Nette: New BSD License or GPL 2.0 or 3.0 (http://nette.org/license)
- jQuery: MIT License (https://jquery.org/license)
- Adminer: Apache License 2.0 or GPL 2 (http://www.adminer.org)
- Sandbox: The Unlicense (http://unlicense.org)

## Push to production server2

## Update Path and Known Issues

Known current issue: Wewimo not working, `pear2/net_routeros` need to be fixed.

Consider updating Latte to 3.0 (?).
