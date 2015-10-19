Object Relational Mapping (ORM) for PHP
=======================

Independent fork by [Yii2 Database 2.0.4](https://github.com/yiisoft/yii2).

[![Latest Stable Version](https://poser.pugx.org/romeOz/rock-db/v/stable.svg)](https://packagist.org/packages/romeOz/rock-db)
[![Total Downloads](https://poser.pugx.org/romeOz/rock-db/downloads.svg)](https://packagist.org/packages/romeOz/rock-db)
[![Build Status](https://travis-ci.org/romeOz/rock-db.svg?branch=master)](https://travis-ci.org/romeOz/rock-db)
[![HHVM Status](http://hhvm.h4cc.de/badge/romeoz/rock-db.svg)](http://hhvm.h4cc.de/package/romeoz/rock-db)
[![Coverage Status](https://coveralls.io/repos/romeOz/rock-db/badge.svg?branch=master)](https://coveralls.io/r/romeOz/rock-db?branch=master)
[![License](https://poser.pugx.org/romeOz/rock-db/license.svg)](https://packagist.org/packages/romeOz/rock-db)

Features
-------------------

 * Supports the following databases out of box:
    - [MySQL](http://www.mysql.com/)
    - [MariaDB](https://mariadb.com/)
    - [SQLite](http://sqlite.org/)
    - [PostgreSQL](http://www.postgresql.org/)
    - [CUBRID](http://www.cubrid.org/): version 9.3 or higher.
    - [Oracle](http://www.oracle.com/us/products/database/overview/index.html)
    - [MSSQL](https://www.microsoft.com/en-us/sqlserver/default.aspx): version 2008 or higher.
 * Query Builder/DBAL/DAO: Querying the database using a simple abstraction layer
 * Active Record: The Active Record ORM, retrieving and manipulating records, and defining relations
 * Migrations
 * Behaviors (SluggableBehavior, TimestampBehavior,...)
 * Data Provider
 * **Validation and Sanitization rules for AR (Model)**
 * **Caching queries**
 * **Standalone module/component for [Rock Framework](https://github.com/romeOz/rock)**
 
> Bolded features are different from [Yii2 Database](https://github.com/yiisoft/yii2).

Installation
-------------------

From the Command Line:

`composer require romeoz/rock-db`

In your composer.json:

```json
{
    "require": {
        "romeoz/rock-db": "*"
    }
}
```

Quick Start
-------------------

####Query Builder

```php
$rows = (new \rock\db\Query())
    ->select('id, name')
    ->from('users')
    ->limit(10)
    ->all();
```

####Active Record

```php
// find
$users = Users::find()
    ->where(['status' => Users::STATUS_ACTIVE])
    ->orderBy('id')
    ->all();
    
// insert
$users = new Users();
$users ->name = 'Tom';
$users ->save();    
```

Documentation
-------------------

* [Basic](https://github.com/yiisoft/yii2/blob/master/docs/guide/db-dao.md): Connecting to a database, basic queries, transactions, and schema manipulation
* [Query Builder](https://github.com/yiisoft/yii2/blob/master/docs/guide/db-query-builder.md)
* [Active Record](https://github.com/yiisoft/yii2/blob/master/docs/guide/db-active-record.md)
* [Migrations](https://github.com/yiisoft/yii2/blob/master/docs/guide/db-migrations.md): Apply version control to your databases in a team development environment
* [Data Providers](https://github.com/romeOz/rock-db/blob/master/docs/data-provider.md)

Requirements
-------------------

 * **PHP 5.4+**
 * For validation rules a model required [Rock Validate](https://github.com/romeOz/rock-validate): `composer require romeoz/rock-validate`
 * For sanitization rules a model required [Rock Sanitize](https://github.com/romeOz/rock-sanitize): `composer require romeoz/rock-sanitize`
 * For using behaviors a model required [Rock Behaviors](https://github.com/romeOz/rock-behaviors): `composer require romeoz/rock-behaviors`
 * For using Data Provider required [Rock Data Provider](https://github.com/romeOz/rock-dataprovider/): `composer require romeoz/rock-dataprovider`
 * For caching queries required [Rock Cache](https://github.com/romeOz/rock-behaviors): `composer require romeoz/rock-cache`

>All unbolded dependencies is optional

License
-------------------

The Object Relational Mapping (ORM) is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).