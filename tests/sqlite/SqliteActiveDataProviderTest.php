<?php
namespace rockunit\core\db\sqlite;
use rockunit\ActiveDataProviderTest;

/**
 * @group db
 * @group sqlite
 */
class SqliteActiveDataProviderTest extends ActiveDataProviderTest
{
    public $driverName = 'sqlite';
}
