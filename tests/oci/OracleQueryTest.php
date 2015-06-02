<?php

namespace rockunit\core\db\oci;
use rockunit\QueryTest;
use rock\db\Query;


/**
 * @group db
 * @group oci
 */
class OracleQueryTest extends QueryTest
{
    protected $driverName = 'oci';

    public function testOne()
    {
        $db = $this->getConnection();
        $result = (new Query)->from('customer')->where(['[[status]]' => 2])->one($db);
        $this->assertEquals('user3', $result['name']);
        $result = (new Query)->from('customer')->where(['[[status]]' => 3])->one($db);
        $this->assertFalse($result);
    }
}