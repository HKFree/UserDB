#HKFree UserDB
Powerful [HKFree.org](http://www.hkfree.org) user and IP management system

Developed by powerful team - Evil, ZitnyP, Bkralik & pavkriz

##Installation

```bash
git clone https://github.com/HKFree/UserDB.git
cd UserDB
composer install
cp app/config/config.local.DIST.neon app/config/config.local.neon
vi app/config/config.local.neon www/.htaccess
rm -rf temp/cache
php www/index.php migrations:continue
```

##Development

- Please use [editor or IDE that obeys .editorconfig settings](http://editorconfig.org/#download)

```bash
git pull origin master
composer install
php www/index.php migrations continue
# develop your freaking cool feature
git pull origin master
git push origin master
```

##Deployment

...

```bash
rm -rf temp/cache
php www/index.php migrations:continue --production`
```

##Making DB changes

###Creating new change-script

Run
`php www/index.php migrations:create s short-description-of-the-change`
and edit the change-script created.

- s = structures (applied always)
- b = basic-data (eg. lists-of-values, applied always)
- d = dummy-data (eg. testing records, applied only when `--production` omitted, should not be run on production)

###Applying changes

Make sure the cache is clean by running `rm -rf temp/cache` and run
`php www/index.php migrations:continue --production`
in order to apply the change-script in the DB configured in neon config.

####On development or testing machines

Omit `--production` when you want to apply dummy-data scripts too (on development or testing machine).

You can run `php www/index.php migrations:reset` in order to drop all tables in the database and create them from scratch running all
 change-scripts. Run it on dev/test machine in order to test that the whole schema is completely described in change-scripts. DO NOT RUN IN PRODUCTION! WILL DELETE ALL DATA!

####On moving to nextras/migrations

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
(2, 'basic-data', '2016-10-23-110900-new-init-data.sql', 'e902ac5947ec4eb61fdb37f7f82ee4c8', '2016-10-23 22:38:14', 1);
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `type_file` (`group`,`file`);
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;
```

##License

- Nette: New BSD License or GPL 2.0 or 3.0 (http://nette.org/license)
- jQuery: MIT License (https://jquery.org/license)
- Adminer: Apache License 2.0 or GPL 2 (http://www.adminer.org)
- Sandbox: The Unlicense (http://unlicense.org)
