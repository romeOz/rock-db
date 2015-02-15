<?php
namespace rockunit\mssql;

use rockunit\db\ActiveDataProviderTest;

/**
 * @group db
 * @group mssql
 * @group data
 */
class MssqlActiveDataProviderTest extends ActiveDataProviderTest
{
    public $driverName = 'sqlsrv';
}
