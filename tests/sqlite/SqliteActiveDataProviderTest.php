<?php
namespace rockunit\sqlite;

use rockunit\db\ActiveDataProviderTest;

/**
 * @group db
 * @group sqlite
 * @group data
 */
class SqliteActiveDataProviderTest extends ActiveDataProviderTest
{
    public $driverName = 'sqlite';
}
