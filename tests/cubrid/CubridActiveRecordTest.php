<?php
namespace rockunit\cubrid;

use rockunit\ActiveRecordTest;

/**
 * @group db
 * @group cubrid
 */
class CubridActiveRecordTest extends ActiveRecordTest
{
    public $driverName = 'cubrid';
}
