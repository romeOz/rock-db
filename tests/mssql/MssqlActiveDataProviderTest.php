<?php
namespace rockunit\core\db\mssql;
use rockunit\ActiveDataProviderTest;

/**
 * @group db
 * @group mssql
 */
class MssqlActiveDataProviderTest extends ActiveDataProviderTest
{
    public $driverName = 'sqlsrv';
}
