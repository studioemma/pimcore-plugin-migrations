Pimcore Migrations
==================

Pimcore only generates migrations for its own updates. They support import and
export of class definitions and other, but its all done via the gui. Pimcore
has no 'real' migrations support built in.

Install the plugin
------------------

To use the migrations make sure the files and folders found here are placed
inside your pimcore project inside the folder `plugins\Migrations`.

TODO: composer

install
-------

Pimcore migrations comes with a built in cli pimcore installer for convenience.

The installer takes the same arguments as the web based installer but it also
enables this `Migrations` plugin when the installation is completed. This
enables us to extend automation.

To run the installer you can execute `php plugins/Migrations/cli/console.php install`.

example:

~~~
$ php plugins/Migrations/cli/console.php install --help
Usage:
  install [options]

Options:
      --db-adapter=DB-ADAPTER          what adapter to use (Mysqli or Pdo_Mysql)
      --db-host=DB-HOST                db host or socket
      --db-port[=DB-PORT]              db port [default: 3306]
      --db-username=DB-USERNAME        db username
      --db-password=DB-PASSWORD        db password
      --db-database=DB-DATABASE        db database name
      --admin-username=ADMIN-USERNAME  admin username
      --admin-password=ADMIN-PASSWORD  admin password
  -h, --help                           Display this help message
  -q, --quiet                          Do not output any message
  -V, --version                        Display this application version
      --ansi                           Force ANSI output
      --no-ansi                        Disable ANSI output
  -n, --no-interaction                 Do not ask any interactive question
  -v|vv|vvv, --verbose                 Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
 Migrations installer
~~~

to install:

~~~
$ php plugins/Migrations/cli/console.php install \
    --db-adapter Pdo_Mysql \
    --db-host mysql \
    --db-username root \
    --db-password toor \
    --db-database pimcore \
    --admin-username admin \
    --admin-password admin
successfully installed pimcore
~~~

migrate
-------

The migrations can be run up and down and if needed you can specify a version.
When a version is specified the tool will automatically detect the direction,
but currently the direction is still mandatory.

Note: This migration tool can run 'system' updates as well. These migrations
only go up. When you run the migrations down, the system migrations will stay
in place because you usually will keep the pimcore version at that revision.

TODO:

- force down (also take the system migrations)
- fix visualisation issue in down direction

When you have used the cli installer the `Migrations` plugin will be enabled
automatically. If you bring in this plugin at a later stage in your project you
will have to enable the plugin before you can run any migrations. To enable the
plugin goto 'menu extras' (currently hart icon) -> 'Extensions' and make sure
you enable the plugin with id `Migrations`.

example run:

~~~
$ php plugins/Migrations/cli/console.php migrate --help
Usage:
  migrate <direction> [<to>]

Arguments:
  direction             The migration direction up/down
  to                    up to what migration version

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Help:
 Migrations
~~~

migrate up or down:

~~~
$ php plugins/Migrations/cli/console.php migrate up
Running migrations up
migrations run from 0 to 5
~~~

migrate to version (example with direction up but detected down):

~~~
$ php plugins/Migrations/cli/console.php migrate up 3
Running migrations up
migrations run from 5 to 3
~~~

Create a migration
------------------

The migration files must be located in your website folder. To be exact you
must place the migrations in `website/lib/Website/Migrations`. You must name
your migrations like `<number>-<ClassName>.php`, so for example
`001-FirstMigration.php`.

TODO:

- add migration skeleton generator

The classname used inside the migration file must be prefixed with
`PMigration_<ClassName>`. For example `PMigration_FirstMigration`.

In theory the migration only must implement `\Migrations\Migration` to work.
But you probably want to extend `\Migrations\Migration\AbstractMigration`
because there are some nice extra's there.

If you are doing system migrations your migration file must also implement
`\Migrations\Migration\SystemMigration`.

example migration:

~~~
<?php

use Migrations\Migration\AbstractMigration;

class PMigration_FirstMigration extends AbstractMigration
{
    public function up()
    {
        // do something
    }

    public function down()
    {
        // do the reverse
    }
}
~~~

example system migration:

~~~
<?php

use Migrations\Migration\AbstractMigration;

class PMigration_PimcoreRevision3689 extends AbstractMigration
    implements \Migrations\SystemMigration
{
    public function getStartRevision()
    {
        return 3675;
    }

    public function getEndRevision()
    {
        return 3689;
    }

    public function up()
    {
        $this->db->query("ALTER TABLE `classificationstore_relations` ADD INDEX `groupId` (`groupId`);");

        $this->db->query("ALTER TABLE `classificationstore_collectionrelations` ADD INDEX `colId` (`colId`);");
    }

    public function down()
    {
        // down will not be executed but must exist
    }
}
~~~
