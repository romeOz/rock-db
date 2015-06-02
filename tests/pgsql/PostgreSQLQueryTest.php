<?php

namespace rockunit\pgsql;

use rock\db\Query;
use rockunit\QueryTest;

/**
 * @group db
 * @group pgsql
 */
class PostgreSQLQueryTest extends QueryTest
{
    public $driverName = 'pgsql';

    public function testBooleanValues()
    {
        $db = $this->getConnection();
        $command = $db->createCommand();
        $command->batchInsert('bool_values',
            ['bool_col'], [
                [true],
                [false],
            ]
        )->execute();

        $this->assertEquals(1, (new Query())->from('bool_values')->where('bool_col = TRUE')->count('*', $db));
        $this->assertEquals(1, (new Query())->from('bool_values')->where('bool_col = FALSE')->count('*', $db));
        $this->assertEquals(2, (new Query())->from('bool_values')->where('bool_col IN (TRUE, FALSE)')->count('*', $db));

        $this->assertEquals(1, (new Query())->from('bool_values')->where(['bool_col' => true])->count('*', $db));
        $this->assertEquals(1, (new Query())->from('bool_values')->where(['bool_col' => false])->count('*', $db));
        $this->assertEquals(2, (new Query())->from('bool_values')->where(['bool_col' => [true, false]])->count('*', $db));

        $this->assertEquals(1, (new Query())->from('bool_values')->where('bool_col = :bool_col', ['bool_col' => true])->count('*', $db));
        $this->assertEquals(1, (new Query())->from('bool_values')->where('bool_col = :bool_col', ['bool_col' => false])->count('*', $db));
    }

    public function testTypeCast()
    {
        $connection = $this->getConnection();

        // disable type cast

        $connection->typeCast = false;

        // find one
        $customer = (new Query)->from('customer')->one($connection);
        $this->assertInternalType('int', $customer['id']);
        $this->assertInternalType('int', $customer['profile_id']);
        $this->assertInternalType('string', $customer['name']);

        // find all
        $customer = (new Query)->from('customer')->all($connection);
        $this->assertInternalType('int', $customer[0]['id']);
        $this->assertInternalType('int', $customer[0]['profile_id']);
        $this->assertInternalType('string', $customer[0]['name']);
    }
}