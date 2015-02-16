<?php
namespace rockunit\core\db\pgsql;
use rockunit\ActiveDataProviderTest;


/**
 * @group db
 * @group pgsql
 */
class PostgreSQLActiveDataProviderTest extends ActiveDataProviderTest
{
    public $driverName = 'pgsql';
}
