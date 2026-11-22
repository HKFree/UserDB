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

Tests run in the project's Flox environment (tested with Flox 1.19.4). The committed environment provides PHP 8.2 with the PCOV coverage driver, Composer, MariaDB 10.11.19, InfluxDB 2.7.12 and LibreOffice for Apple Silicon macOS and x86_64 Linux, including CI. PHP in the environment reads `scripts/userdb_php/php.ini`, selected through `PHPRC` on activation; the file keeps `max_input_vars` at the value of the production image.

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

The test container loads `app/config/config.neon` and `tests/config.test.neon` with a synthetic environment. Mail, SMS and contract requests use recording doubles; the IDS connector, Sojka pinger, outer scanner, IGW IP checkers and MEX URLs point at `http://127.0.0.1:0/`. InfluxDB defaults to the disconnected `influxdb://127.0.0.1:0/wewimo` DSN. It uses in-memory session and user storage and does not read `config.local.neon` or `USERDB_DB_*` variables.

`-s` starts MariaDB and InfluxDB. Database tests use the restricted `userdb_test` account and database on the Flox Unix socket. The schema is built from migrations in `db/structures` and `db/basic-data`, without dummy data, and rebuilt automatically when migrations change. Database tests run serially and roll back their data after each case. A guard checks the database name and MariaDB data directory and refuses a database outside the checkout. Docker is not needed.

`InfluxIntegrationTest` provisions a local organization, bucket and v1 database mapping, writes through the same Guzzle writer as `app:wewimo2influx`, then reads the values and field types back. Each worktree stores InfluxDB data in `.flox/cache/influxdb`; the server listens only on `127.0.0.1` on a free OS-assigned port recorded in `.flox/cache/logs/influxdb.log`. The log outlives the service, so before any setup request the test asks the server on that port for its command line (`/debug/pprof/cmdline`, no authentication) and continues only when the bolt and engine paths belong to this worktree; a healthy InfluxDB of another worktree on a stale port is left untouched, and `InfluxIntegrationTest` starts a second server to prove it. Tests use synthetic credentials and leave the sample points in place. Telemetry is disabled. Run just these tests with `flox activate -s -- vendor/bin/tester -C tests/InfluxIntegrationTest.php`. The ordinary local web remains disconnected from InfluxDB and real Wewimo devices.

The writer retains the existing `INFLUX_URL` format (`influxdb://user:password@host:port/database`), Basic authentication and the v1 `/write` endpoint. The `station` measurement, tag/field names, floating-point values and server-assigned timestamps remain compatible with the existing Grafana data.

Behaviour that differs from the former `influxdb/influxdb-php` client:

- `https+influxdb://` verifies the server certificate; the former client did not. `influxdb://` and `http+influxdb://` use plain HTTP.
- A write waits at most 5 seconds for the connection and 15 seconds in total; the former client had no limit. Redirects are not followed.
- The port defaults to 8086. Percent-encode special characters in the user name and password. A URL with a query string or fragment, `udp+influxdb://` or any other scheme is rejected when the command is created.
- A user name is sent with Basic authentication even when the password is empty.
- A station whose MAC address or interface MAC address is missing or is not a string is not written. `app:wewimo2influx` prints the error for that AP, skips its remaining stations and continues with the next AP.

The suite covers:

- Container services, routing, authentication, API access and representative presenter pages and permissions.
- Permission matrices for admin tools, rights, AP/member editing, messages and grid access, with recorded side effects.
- Message forms for a whole AP with no, invalid and valid recipients.
- AP subnet rows with the ARP proxy rule, administration grid pages and TV service activation.
- Member edit form validation, saves and audit logs, IP/password handling, registration and planned membership transitions.
- Schema migrations, tag operations and constraints, account activation and ledger entries, and audit-log lookup of deleted IPs and subnets.
- Subnet calculations and lookups, address lists for bulk adding, generated passwords and API keys, downloads, SSID rendering and QR generation.
- Local mail capture and contract PDF conversion, including Czech text, optional fields and cleanup.

**Adding a test:** Name the file `*Test.php`, `*DatabaseTest.php` for database tests or `*PresenterTest.php` for presenter tests, and require `tests/bootstrap.php`. Nette Tester runs only `*Test.php` files; `TestNamingTest` fails for a file it would skip. Extend `Test\DatabaseTestCase` for database or presenter tests, create rows through `Test\Fixtures` and run presenters through `Test\PresenterRunner`.

PHPStan runs at **level 8** with the Nette extension and without a baseline. It checks application code, PHP entry points and tests. It does not need the test environment variables or local configuration.

The [phpstan-strict-rules](https://github.com/phpstan/phpstan-strict-rules) extension is enabled as well, including `booleansInConditions`, which requires boolean expressions in conditions, and `disallowedLooseComparison`, which rejects `==` and `!=`. Use strict comparisons and normalize values according to their expected types. `disallowedShortTernary` is switched off for good: the short ternary stays allowed for default values such as `getenv('X') ?: 'default'`. The remaining groups keep the extension's defaults and must pass. This includes `disallowedEmpty`: use explicit comparisons for the expected type instead of `empty()`, preserving the meaning of zero and missing values.

A custom rule, `phpstan/Rules/NoBooleanNotRule.php`, rejects the `!` operator: write negation as an explicit comparison, `$x->isValid() === false`, `($x instanceof Y) === false`, `isset($x) === false`. Comparing with `false` keeps the meaning only for `bool`; compare other types with their actual value (`=== null`, `=== ''`, `=== []`). The rule's own test is `tests/NoBooleanNotRuleTest.php`. Latte templates are not analysed by PHPStan and are not covered.

Methods that Kdyby Replicator adds at runtime are described in `phpstan/replicator.stub`. The `phpstan/grido.stub` file gives the Nette Database data source of Grido the generic type of `Selection`, which the library's annotations leave out, so that `App\Model\DatabazovyZdrojGridu` can override it with full types. The `phpstan/tester.stub` file describes the type narrowing guaranteed by a successful `Tester\Assert::true()` call; assertion failures must throw or terminate the test. The only suppressed finding is one inline `@phpstan-ignore` in `pdfGenerator/www/ContractPdf.php`, where PHPStan's `proc_open` signature rejects a valid descriptor.

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

`scripts/userdb_worktree.sh setup` does the Composer install and the setup command in one step, and `scripts/userdb_worktree.sh web` starts the web; see [Worktrees with Orca](#worktrees-with-orca) for running both automatically.

The OS assigns a free web port and PHP prints the URL. Repeat these commands in another worktree to run both simultaneously. The setup command applies existing migrations and demo data to `userdb_dev`; it can be repeated without resetting data. The local migration adapter replaces the historical production definer with the local account, without changing the migration files. The web uses `config.flox.neon` and the existing demo identity, preserving `config.local.neon`.

Switch the signed-in demo person through "Změnit osobu" in the account menu at the end of the top bar, at `/demo-osoba`, or through the persona panel in the Tracy bar; without a selection it is member 1. The choice is stored in a cookie specific to this worktree, and the switching page works even if the application fails to start.

The [demo data catalog](docs/userdb_demo_data.md) lists the members, APs, personas and pages for each scenario.
Scenario migrations are also loaded by Docker development. Flox setup additionally generates 13,500 members
(4,500 active), 18,000 IP addresses and three years of account movements. This volume is enabled by default
and added only once. Use `USERDB_DEMO_VOLUME=0 php scripts/userdb_dev_setup.php` inside Flox to skip it when
building a fresh database; this does not remove existing volume data. Run setup without the variable to add it later.
The demo guard test builds `userdb_demo_test` with its own `userdb_demo_test` / `demo-test-only` account;
the ordinary `userdb_test` database still contains no dummy data.

The `userdb_dev` / `dev-only`, `userdb_test` / `test-only` and `userdb_e2e` / `e2e-only` credentials are exclusively for these local databases. They cannot access each other's database. Local email is captured as `.eml` files under `.flox/cache/mail`; contract storage lives under `.flox/cache/storage`. Services stop when the last Flox activation exits; database data and keys remain. Inspect MariaDB with `flox services status` inside Flox and logs in `.flox/cache/logs/`.

Contract previews work directly in the local application. Flox provides LibreOffice, zip/unzip and Poppler for PDF checks. The local bootstrap enables direct conversion without a separate HTTP service or PDF port. Each conversion uses a private directory and LibreOffice profile under `.flox/cache/pdf`, cleaned up on success or failure. Templates come from this worktree, and previews are marked `LOCAL TEST`. Font substitution can differ from production; the test checks content, not pixel-identical rendering. Outside this local bootstrap, the application continues to use `PDF_GENERATOR_URL`.

PHP extensions are included and frontend assets are already committed, so no Node build is required. IDS, InfluxDB, SMS and network-check endpoints are disconnected in the local configuration, so their external data/functions are unavailable. SledovaniTV, accounting reports and Fio imports have separate Python/Perl workers and external credentials; they are not started by this local web setup. External signing and other real integrations are not part of the local PDF preview.

### Browser tests with Playwright

`tests/e2e/` holds Playwright Test scenarios of the main processes: they click through the application in
Chromium the way a person does, across several pages and roles, including the JavaScript that the presenter
tests cannot see. Install Flox and the Composer dependencies; Node 26 is part of the Flox environment and is
shared with the [page screenshots](#screenshots-in-pull-requests). From the repository root:

```sh
scripts/userdb_e2e.sh                                   # all tests
scripts/userdb_e2e.sh specs/smoke.spec.mjs              # one file
scripts/userdb_e2e.sh -g "hledání" --headed             # any arguments of "playwright test"
```

The script activates Flox with its services, installs the npm packages and Chromium when
`tests/e2e/package-lock.json` changed (on Linux run `flox activate -- npx playwright install --with-deps chromium`
in `tests/e2e` once) and then, on every run:

1. rebuilds the database `userdb_e2e` (account `userdb_e2e` / `e2e-only`) from the migrations with the scenario
   demo data and without the volume data, and empties its e-mails, sessions and stored files under
   `.flox/cache/e2e/`;
2. starts its own web on a free port with `USERDB_LOCAL_INSTANCE=e2e`, which points the local bootstrap at that
   database and those directories;
3. runs `playwright test` in one worker and stops the web.

The development database `userdb_dev`, a running development web and their e-mails are not used or changed.
Only one run per worktree is possible at a time; a second one stops with a message. A MariaDB that was started
before `scripts/userdb_mariadb_init.sql` knew `userdb_e2e` gets the database from the script, no restart is needed.
The HTML report, traces and screenshots of failed tests and the logs of the setup and the web are in `temp/e2e/`;
open the report with `npx playwright show-report ../../temp/e2e/report` from `tests/e2e`.

The scenarios in `tests/e2e/specs/` cover: every page of the menu for four roles, the search in the top bar,
creating and editing a member with IP addresses, activation, reactivation and deactivation of internet, e-mail to
one member and to a whole AP, sending the payment schedule with its confirmation, editing an AP with the subnet overlap check, labels on the member detail, sorting and
filters of the member list, proposing and approving an honorary membership, granting a role and transferring an
unpaired payment. Each file names the demo records it changes in its first comment.

Tests import `test` and `expect` from `tests/e2e/fixtures.mjs`. It signs in a demo person through the persona
cookie (`test.use({ persona: personas.so })`, `signInAs()` to switch during a test), reads the e-mails the web
stored (`mailbox.received()`), and fails every test on an HTTP 5xx response, a Tracy error page, a browser console
error, an uncaught JavaScript exception or a request outside the local web. Such requests are blocked: Chromium
resolves no host names and Playwright aborts every request to another origin. A redirect to another origin given
by its IP address is not blocked, it only fails the test. `specs/kontrola-chyb.spec.mjs` proves that the check
fails a test. A test that provokes an error on purpose names it with `problems.allow()`. Tests share the database of the run, so each uses demo records of its
own or data it creates; the tests are not retried. Shared page helpers are in `tests/e2e/helpers.mjs`.

A test that finds a bug of the application does not fix it here: the bug gets its own issue and the test stays in
the file as `test.fixme()` with a comment linking the issue, so that the fix only has to enable it.

[e2e.yml](.github/workflows/e2e.yml) runs the same script on every push and pull request, including pull requests
from forks, since it needs no secret. A failed run keeps `temp/e2e/` and the Flox logs as the `e2e-failure`
artifact for 14 days. `BrowserToolsVersionsTest` keeps the Playwright version of `tests/e2e` and
`tests/screenshots` identical, and the Node version of the Flox environment, of `tests/screenshots/.nvmrc` and
of both packages.

### Worktrees with Orca

[Orca](https://orca.dev) runs coding agents such as Claude Code or Codex side by side, each in its own git worktree and branch. The committed `orca.yaml` makes every worktree created in Orca prepare itself, so the change can be tested and clicked through right away.

Install once:

1. Flox, see [Tests with Flox](#tests-with-flox).
2. Orca from [orca.dev/download](https://orca.dev/download), or on macOS with `brew install --cask stablyai/orca/orca`.
3. The agent you use, signed in. Orca starts the agents installed on your computer.
4. The [GitHub CLI](https://cli.github.com/), signed in with `gh auth login`. Orca reads issues and pull requests through it.

Then, for every task:

1. The first time only: **Add project**, **Clone from URL**, enter `https://github.com/HKFree/UserDB.git`. If you already have a checkout, add its folder instead.
2. Click **+** next to the project to create a workspace. Search for the GitHub issue you are going to work on, or just type a name, and choose the agent. Orca derives the branch and the directory from the name, so name the workspace with the issue number and a few ASCII words, such as `123-presun-ip`, not with the issue title: Czech diacritics do not survive in a branch name.
3. Allow the setup and default tab commands when Orca asks whether to run them. They are the two commands in `orca.yaml`. They run the code of the branch the worktree starts from, so for somebody else's branch read its diff first.
4. Orca creates the worktree and opens its tabs:
   - **Setup** installs the Composer dependencies and builds this worktree's database with demo data. It takes about half a minute, longer the first time, when Flox downloads its packages.
   - The agent starts when the setup has finished.
   - **web** waits for the setup as well, then starts the local web and opens it in a browser tab of the worktree. The web is signed in as the demo user, without a login.
5. Describe the task to the agent, check the result on the local web and review the diff before a pull request is opened.

Without Flox the **Setup** tab only prints how to install it, and the agent starts in a worktree that is not prepared. After installing Flox, run `scripts/userdb_worktree.sh setup` and then `scripts/userdb_worktree.sh web` in the worktree.

If the setup fails, Orca does not start the agent and the **web** tab keeps waiting. Fix the cause shown in the **Setup** tab and run `scripts/userdb_worktree.sh setup` again: the web then starts by itself, the agent has to be started by hand.

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

[git-hooks/post-receive.sample](git-hooks/post-receive.sample) is a copy of the hook on the production server as of 2026-11-21, with every secret value replaced by `a`. It builds the images of the web application, the PDF generator, the STV connector and the accounting reports, applies the database migrations and replaces the running containers.

### Pull request previews

[Coolify](https://coolify.io) runs a preview of `master` at `https://<domain>` and one of every open pull request
from a branch of this repository at `https://<number>.<domain>`. A preview runs the same way as the
[local web with Flox](#local-web-database-and-pdf-with-flox): `config.flox.neon`, demo data, mail captured to
files, and visitors signed in as member 1 by default. Every preview answers behind a
[sign-in form](#sign-in-form) that lets in the HKFree network without a password and asks everyone else for
one; the maintainers have the password. One sign-in is valid for all previews for 30 days.

Within a preview, `/demo-osoba` and the persona panel in the Tracy bar let each visitor choose a [demo persona](docs/userdb_demo_data.md#persony). This choice is confined to that preview and does not change the sign-in shared by the previews.

Previews include the scenarios and production-sized synthetic volume described in the
[demo data catalog](docs/userdb_demo_data.md). The setup step enables volume by default; the variable
`USERDB_DEMO_VOLUME=0` of the application in Coolify skips the generated volume while retaining the scenario
migrations.

1. Coolify learns about pushes and pull requests through its GitHub App. A push to `master` and the opening,
   update or reopening of a pull request make it build [Dockerfile.preview](Dockerfile.preview) of that commit
   and start the image as a container of its own; closing or merging the pull request removes its preview.
   Coolify keeps one comment in the pull request with the address of the preview and the state of its build.
2. The image holds the project's Flox environment, the Composer dependencies and the code of the commit. It is
   not the production image: [Dockerfile](Dockerfile) and the deployment above know nothing about previews.
3. The container runs [scripts/userdb_preview.sh](scripts/userdb_preview.sh): MariaDB, then
   `scripts/userdb_dev_setup.php`, which builds the database from the migrations and demo data of the commit,
   then the local web on port 8080 behind the sign-in form. MariaDB and the web, and with it the code of the
   pull request that answers the requests, run under an unprivileged account; the build of the image and the
   activation of Flox at the start of the container run as root of the container. The database is stored
   inside the container, so **every deployment starts from a new database**.

Things to know:

- Data entered in a preview, its captured mail, sessions and logs last until its next deployment.
- Coolify stops the preview of a pull request before it starts the new deployment, and the new one answers
  only after its database has been built: a preview is down for a while after every push. Only the preview
  of `master` keeps answering from the previous deployment until the new one is healthy.
- A preview runs the head commit of the pull request, not its merge with `master`. A branch from before
  `Dockerfile.preview` cannot be built until it is updated from `master`.
- A pull request from a fork must never be deployed, because its code would run on the server unreviewed;
  the setting *PR deployment access* below is what keeps Coolify from doing it. To test such a pull request,
  a maintainer pushes its commits to a branch of this repository and opens a pull request from there.
- `Dockerfile.preview` and the code come from the pull request. The domain, the variables and the limits are
  set once in Coolify and are the same for all previews.
- The server must not let the containers of previews reach anything that is not meant for testing:
  `config.flox.neon` disconnects the external services it knows about, but for example the Wewimo reader
  connects to whatever address is stored in the database.

The same image runs on a developer machine with Docker; on Apple Silicon add `--platform linux/amd64` to both
commands, the Flox environment has no ARM Linux packages:

```sh
docker build -f Dockerfile.preview -t userdb-preview .
docker run --rm -p 127.0.0.1:8080:8080 -e USERDB_TESTING_LOGIN_PASSWORD_HASH='<hash>' userdb-preview
```

The preview then answers at `http://localhost:8080/`; how to make the hash is shown [below](#sign-in-form).

#### Sign-in form

The form is [scripts/UserdbTestingLogin.php](scripts/UserdbTestingLogin.php). The router of a preview,
[scripts/userdb_preview_router.php](scripts/userdb_preview_router.php), asks it about every request before the
application or a file of `www/` answers:

- A visitor from an open network comes in without signing in. The proxy of Coolify connects from a private
  address and adds the address of its visitor to `X-Forwarded-For`; the form takes the last address there.
  From a connection that comes from a public address it takes that address and ignores the header. Open
  networks are therefore public ones only: a private address is where the proxy and the other containers of
  the server connect from.
- A visitor without the cookie of the form is redirected to `/testing-login` and after signing in back to the
  page asked for. A request other than GET or HEAD is refused with 403.
- A request with the cookie reaches the application unchanged, including its `Authorization` header. A client
  of the API of a preview signs in first and sends the cookie along:
  `curl --cookie-jar jar --data-urlencode "password=$PASSWORD" https://<domain>/testing-login`, then
  `curl --cookie jar --user apikey<id>:<key> https://<domain>/api/...`.
- `/testing-login` shows a signed-in visitor a button to sign out.
- Five wrong passwords from one address block that address for up to 15 minutes.
- The cookie is signed with the hash of the password, so a new password signs everyone out.
- A preview whose form is not set up answers every request with 500 and logs the reason.

The form is set up by variables of the application in Coolify:

| Variable | Meaning |
|---|---|
| `USERDB_TESTING_LOGIN_PASSWORD_HASH` | bcrypt hash of the shared password, required |
| `USERDB_TESTING_LOGIN_COOKIE_DOMAIN` | `<domain>`, so that one sign-in is valid for all previews; without it the cookie belongs to the visited host |
| `USERDB_TESTING_LOGIN_OPEN_NETWORKS` | networks let in without signing in, as `address/prefix length` separated by commas; without it everyone signs in |

```sh
php -r 'echo password_hash(rtrim(fgets(STDIN), "\n"), PASSWORD_BCRYPT), "\n";'    # reads the password, prints its hash
```

The form removes its variables from the environment of every request before the application starts, because
the Tracy bar of the local web shows the environment to every visitor. It is still part of the image, so the
code of a pull request can read the hash and can leave the form out. That is acceptable because only branches
of this repository are deployed: whoever can push here is trusted with the previews. The password guards
demo data only; use it nowhere else.

#### Setup in Coolify

Done once by the administrator of the Coolify instance (written for Coolify 4.7):

1. DNS: `<domain>` and `*.<domain>` resolve to the Coolify server.
2. **Sources**: a GitHub App with *Pull request previews* enabled, installed in the organization for this
   repository only.
3. A new resource from the repository through that GitHub App: branch `master`, build pack **Dockerfile**,
   Dockerfile location `/Dockerfile.preview`, exposed port `8080`, domain `https://<domain>`.
4. **Environment Variables**: the variables of the sign-in form in both the production and the preview set,
   each with *Runtime* only (*Build time*: not available during build) and *Interpolation*: literal, because
   a hash contains `$`. In **Advanced**, set *Build arguments* to *Managed manually in Dockerfile*, so that
   no variable ends up in the metadata of the image. Give the application no other secret: the Tracy bar
   shows every other variable to the visitors.
5. **Preview Deployments**: enabled, URL template `{{pr_id}}.{{domain}}`, *PR deployment access*:
   *Repository members only*.
6. **Resource Limits**: a memory limit, so that one preview cannot take the others down.
7. On the server itself, a firewall rule that keeps the containers away from private networks other than
   their own: Coolify has no setting for that.

The health check is part of `Dockerfile.preview`, so the one of Coolify stays disabled. When it deploys
`master`, Coolify waits for the new container to become healthy with the start period, interval and retries
written in `Dockerfile.preview`, not with those on its own health check page.

### Screenshots in pull requests

[pr-screenshots.yml](.github/workflows/pr-screenshots.yml) captures seven pages at desktop (1440×900)
and mobile (390×844, touch enabled) sizes when a pull request is opened, updated or reopened. It compares
the PR commit with its merge-base with the target branch. Both versions run on the GitHub runner with
separate Flox databases and basic demo data (`USERDB_DEMO_VOLUME=0`), signed in as member 1. The pull request
previews on Coolify are not involved. Fork and Dependabot pull requests are skipped. Branches that predate the screenshot
tools are also skipped until they include those tools.

The workflow updates one Czech comment marked `<!-- userdb-screenshots -->`, with previews of every page
and before/after/diff images for changes. Both tables are collapsed, so that the comment does not push the
discussion of the pull request away; the line above them says how many snapshots changed. A failed capture or publication replaces the previous comment with
a failure message. Closed pull requests and outdated commits do not update the comment. Artifacts expire
after 14 days; published images are retained in a separate public repository, including after the PR closes.
Image URLs use the screenshot repository's commit SHA so each URL identifies an immutable version.

The `capture` job runs the application and npm dependencies with no repository secrets and a read-only token.
The `publish` job validates the artifact, uses dependency-free Node tools and holds the image repository
deploy key. Only same-repository PRs are trusted to run this workflow: their authors can also edit the
workflow. The image checkout uses a partial clone and a sparse checkout of the current PR commit.

#### One-time setup

1. Create the public repository `HKFree/UserDB-screenshots` with a README and a `main` branch.
2. Generate a dedicated key outside the application repository:
   `ssh-keygen -t ed25519 -N '' -C userdb-pr-screenshots -f userdb-pr-screenshots`.
3. Add `userdb-pr-screenshots.pub` as a deploy key with write access in the image repository.
4. In UserDB, set the Actions secret `SCREENSHOTS_DEPLOY_KEY` to the private key and the Actions variable
   `SCREENSHOTS_REPOSITORY` to `HKFree/UserDB-screenshots`.
5. Delete the local private and public key files after storing them in GitHub.

#### Local capture

Install Flox; Node 26 is part of its environment. `tests/screenshots/.nvmrc` names the same version for the
`publish` job, which runs without Flox. From the repository root:

```sh
# Linux: playwright install --with-deps chromium
flox activate -- bash -c 'cd tests/screenshots && npm ci && npx playwright install chromium && npm test'
scripts/userdb_screenshots.sh local HEAD  # compare the current commit with itself
scripts/userdb_screenshots.sh local       # compare HEAD with its merge-base with origin/master
scripts/userdb_screenshots.sh local <base-commit> --head <head-commit>
```

Both application versions are detached worktrees of **commits**. Uncommitted application changes are not
captured. The tools and installed Node dependencies always come from the worktree containing the entry
script. Each run uses `temp/screenshots/run.XXXXXX/`, independent databases and available loopback ports.
Existing development databases and web servers are not used. On completion, failure, SIGINT or SIGTERM,
the script stops its services and removes its temporary worktrees. It keeps `output/`, including PNGs,
capture metadata, `manifest.json` and logs. The last lines report changed, new and error counts, the output
directory and each changed snapshot. No local command publishes images or comments.

CI uses the same entry point with disposable, pre-existing checkouts:
`scripts/userdb_screenshots.sh run --head <directory> --base <directory> --out <directory>`.
Omitting `--base` produces previews without a comparison. A base version without the Flox setup, a failed
base setup or a base web timeout also produces head-only previews with an enumerated reason.

#### Pages, results and limitations

Add one `{ "id": "short-ascii-id", "path": "/page/path" }` line to
[tests/screenshots/pages.json](tests/screenshots/pages.json). Both viewports are captured automatically.
Keep the list at **10 pages or fewer**: if every snapshot changes, each page needs six PNGs (base, head and
diff for both viewports). Ten pages plus the three JSON files fit within the validator's 64-file limit;
eleven pages would exceed it. The 50 MB artifact limit also applies.
An optional `mask` array contains Playwright CSS selectors for unstable regions. `maxDiffPixels` defaults
to zero; use a nonzero allowance only with a documented reason. The comparison uses pixelmatch with
`threshold: 0.1` and `includeAA: false`; different dimensions always count as a change.
The account page masks only `.grido tbody .grid-cell-datum_cas`: demo transactions use `NOW()`, so their
seconds differ between independently seeded databases. All pages use zero pixel allowance.

The manifest distinguishes `unchanged`, `changed`, `new` (base HTTP 404), `error` (head HTTP status, failed
navigation or a Tracy error page), and `base-error`. Console errors are only counted. An unavailable base
is recorded separately. Errors on individual pages do not stop other captures. The comment uses validated
paths and numeric diagnostics, never console messages or other free text from the artifact. The validator
rejects nonregular files, unexpected image paths, invalid PNG headers, dimensions above 1440×8000, more
than 64 files and artifacts above 50 MB.
The `logs/` directory of a local run is outside these limits and is never published.

Capture uses Chromium, Czech locale, Prague time, light mode, reduced motion, disabled animations, hidden
carets and a hidden Tracy toolbar. Playwright aborts directly routed requests outside the app's origin.
Chromium disables hostname resolution except for `127.0.0.1`, including redirect hops that bypass
Playwright routing. Redirects to other IP-based origins can still be reached; if navigation ends outside
the app's origin, the capture is marked as failed and a fallback image replaces the foreign page.
External maps and other remote assets addressed by hostname are unavailable.
Full-page screenshots are cropped at 8000 pixels high and 1440 pixels wide, with cropping recorded in the
manifest and comment. Mobile page content can exceed the 390-pixel viewport when the application's layout
overflows. Rendering can differ between operating systems; compare both commits on the same machine. Demo
data and date-dependent content are seeded before either capture; a run crossing midnight can still observe
a date boundary.

The Node tests include browser fixtures, artifact validation, golden Markdown files and publisher tests
against local bare Git repositories. After an intentional comment change, regenerate and review the golden
files with `UPDATE_SNAPSHOTS=1 npm test` from `tests/screenshots/`. These tests never publish to GitHub.

The same `npm test` runs `test/copy-value.test.mjs`, a browser test of the application's own `www/js/main.js`:
copying a device login and password from the IP address table (mouse selection, the copy button, keyboard and touch).
`test/message-form.test.mjs` and `test/edit-form.test.mjs` check with the real `www/css/screen.css` that the forms rendered by
`app/templates/form.latte` fit into a phone screen: the e-mail and SMS forms and the edit forms with their nested records.
`test/layout-width.test.mjs` checks in a browser, against the application's `www/css/screen.css` and `www/js/main.js`,
that the content column beside the sidebar fits the window at widths from 320 to 1700 px, also after the sidebar
was hidden and shown again, so that pages do not scroll sideways.

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
