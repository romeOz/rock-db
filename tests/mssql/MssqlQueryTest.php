<?php

namespace rockunit\mssql;

use rockunit\QueryTest;

/**
 * @group db
 * @group mssql
 */
class MssqlQueryTest extends QueryTest
{
    protected $driverName = 'sqlsrv';
}
