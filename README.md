skMySQLCachePlugin
==================

This symfony plugin contains the MySQL cache adapter class.



Configuration
-------------

Set the cache class to `skMySQLCache` in your `factories.yml`. You can either use an existing Doctrine MySQL connection configured in your `databases.yml`, or specify connection details.

The available parameters are as follows:

* All parameters available to `sfCache`
* When using a Doctrine connection:
    * `database` — Doctrine connection name as specified in your `databases.yml`
* When specifying connection details:
    * `dsn` — PDO-compatible DSN string
    * `username`
    * `password`
* `table` — Table name for the cache (defaults to `cache`)

Example configuration:

    view_cache:
      class:        skMySQLCache
      param:
        database:   doctrine
        table:      viewcache



Database Setup
--------------

The following SQL will create the cache table, make sure to use the same table name you specified in your `factories.yml` above:

    CREATE TABLE `cache` (
      `key` varchar(255) NOT NULL DEFAULT '',
      `data` longtext,
      `timeout` bigint(20) unsigned NOT NULL,
      `last_modified` bigint(20) unsigned NOT NULL,
      PRIMARY KEY (`key`),
      KEY `key` (`key`,`timeout`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

We're using ints for the timestamps because, for what we need, they're faster than `datetime` or `timestamp`.



License and Attribution
-----------------------

skMySQLCachePlugin by [Studio Skylab](http://www.studioskylab.com)

This code is released under the [Creative Commons Attribution-ShareAlike 3.0 License](http://creativecommons.org/licenses/by-sa/3.0/).