<?php

namespace rockunit\mssql;

use rockunit\db\ActiveRecordTest;

/**
 * @group db
 * @group mssql
 */
class MssqlActiveRecordTest extends ActiveRecordTest
{
    protected $driverName = 'sqlsrv';
}
