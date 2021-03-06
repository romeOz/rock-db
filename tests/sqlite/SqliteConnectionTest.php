<?php
namespace rockunit\sqlite;

use rock\base\Alias;
use rock\db\Connection;
use rock\db\Transaction;
use rock\helpers\FileHelper;
use rock\helpers\Instance;
use rockunit\ConnectionTest;
use rockunit\models\ActiveRecord;
use rockunit\models\Customer;

/**
 * @group db
 * @group sqlite
 */
class SqliteConnectionTest extends ConnectionTest
{
    protected $driverName = 'sqlite';

    public function testConstruct()
    {
        $connection = $this->getConnection(false);
        $params = $this->database;

        $this->assertEquals($params['dsn'], $connection->dsn);
    }

    public function testQuoteValue()
    {
        $connection = $this->getConnection(false);
        $this->assertEquals(123, $connection->quoteValue(123));
        $this->assertEquals("'string'", $connection->quoteValue('string'));
        $this->assertEquals("'It''s interesting'", $connection->quoteValue("It's interesting"));
    }

    public function testQuoteTableName()
    {
        $connection = $this->getConnection(false);
        $this->assertEquals("`table`", $connection->quoteTableName('table'));
        $this->assertEquals("`schema`.`table`", $connection->quoteTableName('schema.table'));
        $this->assertEquals('{{table}}', $connection->quoteTableName('{{table}}'));
        $this->assertEquals('(table)', $connection->quoteTableName('(table)'));
    }

    public function testQuoteColumnName()
    {
        $connection = $this->getConnection(false);
        $this->assertEquals('`column`', $connection->quoteColumnName('column'));
        $this->assertEquals("`table`.`column`", $connection->quoteColumnName('table.column'));
        $this->assertEquals('[[column]]', $connection->quoteColumnName('[[column]]'));
        $this->assertEquals('{{column}}', $connection->quoteColumnName('{{column}}'));
        $this->assertEquals('(column)', $connection->quoteColumnName('(column)'));
    }

    public function testTransactionIsolation()
    {
        $connection = $this->getConnection(true);

        $transaction = $connection->beginTransaction(Transaction::READ_UNCOMMITTED);
        $transaction->rollBack();

        $transaction = $connection->beginTransaction(Transaction::SERIALIZABLE);
        $transaction->rollBack();
    }

    public function testMasterSlave()
    {
        $counts = [[0, 2], [1, 2], [2, 2]];

        foreach ($counts as $count) {
            list($masterCount, $slaveCount) = $count;

            $db = $this->prepareMasterSlave($masterCount, $slaveCount);

            $this->assertTrue($db->getSlave() instanceof Connection);
            $this->assertTrue($db->getSlave()->isActive);
            $this->assertFalse($db->isActive);

            // test SELECT uses slave
            $this->assertEquals(2, $db->createCommand('SELECT COUNT(*) FROM profile')->queryScalar());
            $this->assertFalse($db->isActive);

            // test UPDATE uses master
            $db->createCommand("UPDATE profile SET description='test' WHERE id=1")->execute();
            $this->assertTrue($db->isActive);
            $this->assertNotEquals('test', $db->createCommand("SELECT description FROM profile WHERE id=1")->queryScalar());
            $result = $db->useMaster(function (Connection $db) {
                return $db->createCommand("SELECT description FROM profile WHERE id=1")->queryScalar();
            });
            $this->assertEquals('test', $result);

            // test ActiveRecord read/write split
            ActiveRecord::$connection = $db = $this->prepareMasterSlave($masterCount, $slaveCount);
            $this->assertFalse($db->isActive);

            $customer = Customer::findOne(1);
            $this->assertTrue($customer instanceof Customer);
            $this->assertEquals('user1', $customer->name);
            $this->assertFalse($db->isActive);

            $customer->name = 'test';
            $customer->save();
            $this->assertTrue($db->isActive);
            $customer = Customer::findOne(1);
            $this->assertTrue($customer instanceof Customer);
            $this->assertEquals('user1', $customer->name);
            $result = $db->useMaster(function () {
                return Customer::findOne(1)->name;
            });
            $this->assertEquals('test', $result);
        }
    }

    /**
     * @param integer $masterCount
     * @param integer $slaveCount
     * @return Connection
     */
    protected function prepareMasterSlave($masterCount, $slaveCount)
    {
        $databases = self::getParam('databases');
        $fixture = $databases[$this->driverName]['fixture'];
        $basePath = Alias::getAlias('@rockunit/runtime');
        FileHelper::createDirectory($basePath);

        $config = [
            'class' => Connection::className(),
            'dsn' => "sqlite:$basePath/rocktest.sq3",
        ];
        $this->prepareDatabase($config, $fixture)->close();

        for ($i = 0; $i < $masterCount; ++$i) {
            $master = ['dsn' => "sqlite:$basePath/rocktest_master{$i}.sq3"];
            $db = $this->prepareDatabase($master, $fixture);
            $db->close();
            $config['masters'][] = $master;
        }

        for ($i = 0; $i < $slaveCount; ++$i) {
            $slave = ['dsn' => "sqlite:$basePath/rocktest_slave{$i}.sq3"];
            $db = $this->prepareDatabase($slave, $fixture);
            $db->close();
            $config['slaves'][] = $slave;
        }

        return Instance::ensure($config);
    }
}
