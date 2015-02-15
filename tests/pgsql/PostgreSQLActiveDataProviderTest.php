<?php
namespace rockunit\pgsql;

use rockunit\db\ActiveDataProviderTest;

/**
 * @group db
 * @group pgsql
 * @group data
 */
class PostgreSQLActiveDataProviderTest extends ActiveDataProviderTest
{
    public $driverName = 'pgsql';
}
