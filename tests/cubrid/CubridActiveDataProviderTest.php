<?php
namespace rockunit\cubrid;

use rockunit\db\ActiveDataProviderTest;

/**
 * @group db
 * @group cubrid
 * @group data
 */
class CubridActiveDataProviderTest extends ActiveDataProviderTest
{
    public $driverName = 'cubrid';
}
