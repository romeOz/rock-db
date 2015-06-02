<?php
namespace rockunit\core\db\oci;

use rockunit\ActiveDataProviderTest;

/**
 * @group db
 * @group oci
 */
class OracleActiveDataProviderTest extends ActiveDataProviderTest
{
    public $driverName = 'oci';
}