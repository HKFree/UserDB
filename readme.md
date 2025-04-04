# HKFree UserDB
Powerful [HKFree.org](http://www.hkfree.org) user and IP management system

Developed by powerful team - Evil, ZitnyP, Bkralik & pavkriz

## Before you begin
 - install PHP 7.2
 - install https://getcomposer.org/
 - install https://www.docker.com/get-started

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

### Editor

Please use [editor or IDE that obeys .editorconfig settings](http://editorconfig.org/#download)

#### VSCode PHP Formatting

To activate formatting in VSCode, install extension "[php cs fixer](https://marketplace.visualstudio.com/items?itemName=junstyle.php-cs-fixer)". The extension will obey the rules set in `.vscode/settings.json` and `.php-cs-fixer.dist.php`. Formatting will be done on every filesave.

Windows users: install PHP (8.3) and add to PATH, install composer globally, install package php-cs-fixer globally

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

### Jobs to be run

Run `php www/index.php app:update_locations` regularly in order to update users' locations based on their addresses.

Crontab record example: `*/5 * * * * (docker exec userdb php www/index.php app:update_locations) 2>&1 | /usr/bin/logger -t userdb_locations`

## Making DB changes

###Creating new change-script

Run
`docker exec -it userdb_web_1 php bin/console migrations:create s short-description-of-the-change`
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
