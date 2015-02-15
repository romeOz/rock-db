<?php
namespace rockunit\cubrid;

use rockunit\db\ActiveRecordTest;

/**
 * @group db
 * @group cubrid
 */
class CubridActiveRecordTest extends ActiveRecordTest
{
    public $driverName = 'cubrid';
}
