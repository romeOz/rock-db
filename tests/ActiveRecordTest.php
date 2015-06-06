<?php

namespace rockunit;


use rock\cache\CacheInterface;
use rock\db\ActiveQuery;
use rock\db\common\ActiveQueryInterface;
use rock\db\common\ActiveRecordInterface;
use rock\db\common\BaseActiveRecord;
use rock\db\common\DbException;
use rock\db\Connection;
use rock\db\SelectBuilder;
use rock\events\Event;
use rock\helpers\Trace;
use rockunit\common\CommonTestTrait;
use rockunit\models\ActiveRecord;
use rockunit\models\Animal;
use rockunit\models\Cat;
use rockunit\models\Category;
use rockunit\models\Customer;
use rockunit\models\CustomerRules;
use rockunit\models\Document;
use rockunit\models\Dog;
use rockunit\models\Item;
use rockunit\models\NullValues;
use rockunit\models\Order;
use rockunit\models\OrderItem;
use rockunit\models\OrderItemWithNullFK;
use rockunit\models\OrderWithNullFK;
use rockunit\models\Profile;
use rockunit\models\Type;

/**
 * @group db
 * @group mysql
 */
class ActiveRecordTest extends DatabaseTestCase
{
    use CommonTestTrait;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        static::getCache()->flush();
        static::clearRuntime();
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        static::getCache()->flush();
        static::clearRuntime();
    }

    protected function setUp()
    {
        parent::setUp();
        ActiveRecord::$connection = $this->getConnection();
        Trace::removeAll();
        Event::offAll();
    }

    public function getCustomerClass()
    {
        return Customer::className();
    }

    public function getCustomerRulesClass()
    {
        return CustomerRules::className();
    }

    public function getItemClass()
    {
        return Item::className();
    }

    public function getOrderClass()
    {
        return Order::className();
    }

    public function getOrderItemClass()
    {
        return OrderItem::className();
    }

    public function getOrderWithNullFKClass()
    {
        return OrderWithNullFK::className();
    }

    public function getOrderItemWithNullFKmClass()
    {
        return OrderItemWithNullFK::className();
    }

    /**
     * can be overridden to do things after save()
     */
    public function afterSave()
    {
    }

    public function testFind()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();

        // find one
        $result = $customerClass::find();
        $this->assertTrue($result instanceof ActiveQueryInterface);
        $customer = $result->one();
        $this->assertTrue($customer instanceof $customerClass);
        $this->assertSame($customer->id, 1);
        $this->assertSame($customer->status, 1);

        // find all
        $customers = $customerClass::find()->all();
        $this->assertEquals(3, count($customers));
        $this->assertTrue($customers[0] instanceof $customerClass);
        $this->assertSame($customers[0]->id, 1);
        $this->assertSame($customers[0]->status, 1);
        $this->assertTrue($customers[1] instanceof $customerClass);
        $this->assertTrue($customers[2] instanceof $customerClass);

        // find by a single primary key
        $customer = $customerClass::findOne(2);
        $this->assertTrue($customer instanceof $customerClass);
        $this->assertEquals('user2', $customer->name);
        $customer = $customerClass::findOne(5);
        $this->assertNull($customer);
        $customer = $customerClass::findOne(['id' => [5, 6, 1]]);
        $this->assertEquals(1, count($customer));
        $customer = $customerClass::find()->where(['id' => [5, 6, 1]])->one();
        $this->assertNotNull($customer);

        // find by column values
        $customer = $customerClass::findOne(['id' => 2, 'name' => 'user2']);
        $this->assertTrue($customer instanceof $customerClass);
        $this->assertEquals('user2', $customer->name);
        $customer = $customerClass::findOne(['id' => 2, 'name' => 'user1']);
        $this->assertNull($customer);
        $customer = $customerClass::findOne(['id' => 5]);
        $this->assertNull($customer);
        $customer = $customerClass::findOne(['name' => 'user5']);
        $this->assertNull($customer);

        // find by attributes
        $customer = $customerClass::find()->where(['name' => 'user2'])->one();
        $this->assertTrue($customer instanceof $customerClass);
        $this->assertEquals(2, $customer->id);

        // scope
        $this->assertEquals(2, count($customerClass::find()->active()->all()));
        $this->assertEquals(2, $customerClass::find()->active()->count());
    }

    public function testFindAsArray()
    {
        /** @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();

        // asArray
        $customer = $customerClass::find()->where(['id' => 2])->asArray()->one();
        $this->assertEquals([
            'id' => 2,
            'email' => 'user2@example.com',
            'name' => 'user2',
            'address' => 'address2',
            'status' => 1,
            'profile_id' => null,
        ], $customer);

        // find all asArray
        $customers = $customerClass::find()->asArray()->all();
        $this->assertEquals(3, count($customers));
        $this->assertSame($customers[0]['id'], 1);
        $this->assertSame($customers[0]['status'], 1);
        $this->assertArrayHasKey('id', $customers[0]);
        $this->assertArrayHasKey('name', $customers[0]);
        $this->assertArrayHasKey('email', $customers[0]);
        $this->assertArrayHasKey('address', $customers[0]);
        $this->assertArrayHasKey('status', $customers[0]);
        $this->assertArrayHasKey('id', $customers[1]);
        $this->assertArrayHasKey('name', $customers[1]);
        $this->assertArrayHasKey('email', $customers[1]);
        $this->assertArrayHasKey('address', $customers[1]);
        $this->assertArrayHasKey('status', $customers[1]);
        $this->assertArrayHasKey('id', $customers[2]);
        $this->assertArrayHasKey('name', $customers[2]);
        $this->assertArrayHasKey('email', $customers[2]);
        $this->assertArrayHasKey('address', $customers[2]);
        $this->assertArrayHasKey('status', $customers[2]);
    }

    public function testFindScalar()
    {
        /* @var $customerClass Customer */
        $customerClass = $this->getCustomerClass();

        // query scalar
        $customerName = $customerClass::find()->select('[[name]]')->where(['[[id]]' => 2])->scalar();
        $this->assertEquals('user2', $customerName);
        $customerStatus = $customerClass::find()->select('[[status]]')->where(['[[id]]' => 2])->scalar();
        $this->assertSame($customerStatus, 1);
        $customerName = $customerClass::find()->select('[[name]]')->where(['status' => 2])->scalar();
        $this->assertEquals('user3', $customerName);
        $customerId = $customerClass::find()->select('[[id]]')->where(['[[status]]' => 2])->scalar();
        $this->assertEquals(3, $customerId);

        $this->setExpectedException(DbException::className());
        $customerClass::find()->select('[[noname]]')->where(['[[status]]' => 2])->scalar();
    }

    public function testFindColumn()
    {
        /* @var $customerClass Customer */
        $customerClass = $this->getCustomerClass();

        $this->assertEquals(['user1', 'user2', 'user3'], $customerClass::find()->select('[[name]]')->orderBy(['[[name]]' => SORT_ASC])->column());
        $this->assertEquals(['user3', 'user2', 'user1'], $customerClass::find()->select('[[name]]')->orderBy(['[[name]]' => SORT_DESC])->column());
    }

    public function testFindIndexBy()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();

        // indexBy
        $customers = $customerClass::find()->indexBy('name')->orderBy('id')->all();
        $this->assertEquals(3, count($customers));
        $this->assertTrue($customers['user1'] instanceof $customerClass);
        $this->assertTrue($customers['user2'] instanceof $customerClass);
        $this->assertTrue($customers['user3'] instanceof $customerClass);

        // indexBy callable
        $customers = $customerClass::find()->indexBy(function ($customer) {
            return $customer->id . '-' . $customer->name;
        })->orderBy('id')->all();
        $this->assertEquals(3, count($customers));
        $this->assertTrue($customers['1-user1'] instanceof $customerClass);
        $this->assertTrue($customers['2-user2'] instanceof $customerClass);
        $this->assertTrue($customers['3-user3'] instanceof $customerClass);
    }

    public function testFindIndexByAsArray()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();

        // indexBy + asArray
        $customers = $customerClass::find()->asArray()->indexBy('name')->all();
        $this->assertEquals(3, count($customers));
        $this->assertArrayHasKey('id', $customers['user1']);
        $this->assertArrayHasKey('name', $customers['user1']);
        $this->assertArrayHasKey('email', $customers['user1']);
        $this->assertArrayHasKey('address', $customers['user1']);
        $this->assertArrayHasKey('status', $customers['user1']);
        $this->assertArrayHasKey('id', $customers['user2']);
        $this->assertArrayHasKey('name', $customers['user2']);
        $this->assertArrayHasKey('email', $customers['user2']);
        $this->assertArrayHasKey('address', $customers['user2']);
        $this->assertArrayHasKey('status', $customers['user2']);
        $this->assertArrayHasKey('id', $customers['user3']);
        $this->assertArrayHasKey('name', $customers['user3']);
        $this->assertArrayHasKey('email', $customers['user3']);
        $this->assertArrayHasKey('address', $customers['user3']);
        $this->assertArrayHasKey('status', $customers['user3']);

        // indexBy callable + asArray
        $customers = $customerClass::find()->indexBy(function ($customer) {
            return $customer['id'] . '-' . $customer['name'];
        })->asArray()->all();
        $this->assertEquals(3, count($customers));
        $this->assertArrayHasKey('id', $customers['1-user1']);
        $this->assertArrayHasKey('name', $customers['1-user1']);
        $this->assertArrayHasKey('email', $customers['1-user1']);
        $this->assertArrayHasKey('address', $customers['1-user1']);
        $this->assertArrayHasKey('status', $customers['1-user1']);
        $this->assertArrayHasKey('id', $customers['2-user2']);
        $this->assertArrayHasKey('name', $customers['2-user2']);
        $this->assertArrayHasKey('email', $customers['2-user2']);
        $this->assertArrayHasKey('address', $customers['2-user2']);
        $this->assertArrayHasKey('status', $customers['2-user2']);
        $this->assertArrayHasKey('id', $customers['3-user3']);
        $this->assertArrayHasKey('name', $customers['3-user3']);
        $this->assertArrayHasKey('email', $customers['3-user3']);
        $this->assertArrayHasKey('address', $customers['3-user3']);
        $this->assertArrayHasKey('status', $customers['3-user3']);
    }

    public function testRefresh()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();

        /** @var ActiveRecordInterface $customer */
        $customer = new $customerClass();
        $this->assertFalse($customer->refresh());

        $customer = $customerClass::findOne(1);
        $customer->name = 'to be refreshed';
        $this->assertTrue($customer->refresh());
        $this->assertEquals('user1', $customer->name);
    }

    public function testEquals()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();
        /* @var $itemClass ActiveRecordInterface */
        $itemClass = $this->getItemClass();

        /** @var ActiveRecordInterface $customerA */
        $customerA = new $customerClass();
        $customerB = new $customerClass();
        $this->assertFalse($customerA->equals($customerB));

        $customerA = new $customerClass();
        $customerB = new $itemClass();
        $this->assertFalse($customerA->equals($customerB));

        $customerA = $customerClass::findOne(1);
        $customerB = $customerClass::findOne(2);
        $this->assertFalse($customerA->equals($customerB));

        $customerB = $customerClass::findOne(1);
        $this->assertTrue($customerA->equals($customerB));

        $customerA = $customerClass::findOne(1);
        $customerB = $itemClass::findOne(1);
        $this->assertFalse($customerA->equals($customerB));
    }

    public function testFindCount()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();

        $this->assertEquals(3, $customerClass::find()->count());

        $this->assertEquals(1, $customerClass::find()->where(['id' => 1])->count());
        $this->assertEquals(2, $customerClass::find()->where(['id' => [1, 2]])->count());
        $this->assertEquals(2, $customerClass::find()->where(['id' => [1, 2]])->offset(1)->count());
        $this->assertEquals(2, $customerClass::find()->where(['id' => [1, 2]])->offset(2)->count());

        // limit should have no effect on count()
        $this->assertEquals(3, $customerClass::find()->limit(1)->count());
        $this->assertEquals(3, $customerClass::find()->limit(2)->count());
        $this->assertEquals(3, $customerClass::find()->limit(10)->count());
        $this->assertEquals(3, $customerClass::find()->offset(2)->limit(2)->count());
    }

    public function testFindLimit()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();

        // all()
        $customers = $customerClass::find()->all();
        $this->assertEquals(3, count($customers));

        $customers = $customerClass::find()->orderBy('id')->limit(1)->all();
        $this->assertEquals(1, count($customers));
        $this->assertEquals('user1', $customers[0]->name);

        $customers = $customerClass::find()->orderBy('id')->limit(1)->offset(1)->all();
        $this->assertEquals(1, count($customers));
        $this->assertEquals('user2', $customers[0]->name);

        $customers = $customerClass::find()->orderBy('id')->limit(1)->offset(2)->all();
        $this->assertEquals(1, count($customers));
        $this->assertEquals('user3', $customers[0]->name);

        $customers = $customerClass::find()->orderBy('id')->limit(2)->offset(1)->all();
        $this->assertEquals(2, count($customers));
        $this->assertEquals('user2', $customers[0]->name);
        $this->assertEquals('user3', $customers[1]->name);

        $customers = $customerClass::find()->limit(2)->offset(3)->all();
        $this->assertEquals(0, count($customers));

        // one()
        $customer = $customerClass::find()->orderBy('id')->one();
        $this->assertEquals('user1', $customer->name);

        $customer = $customerClass::find()->orderBy('id')->offset(0)->one();
        $this->assertEquals('user1', $customer->name);

        $customer = $customerClass::find()->orderBy('id')->offset(1)->one();
        $this->assertEquals('user2', $customer->name);

        $customer = $customerClass::find()->orderBy('id')->offset(2)->one();
        $this->assertEquals('user3', $customer->name);

        $customer = $customerClass::find()->offset(3)->one();
        $this->assertNull($customer);

    }

    public function testFindComplexCondition()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();

        $this->assertEquals(2, $customerClass::find()->where(['OR', ['name' => 'user1'], ['name' => 'user2']])->count());
        $this->assertEquals(2, count($customerClass::find()->where(['OR', ['name' => 'user1'], ['name' => 'user2']])->all()));

        $this->assertEquals(2, $customerClass::find()->where(['name' => ['user1', 'user2']])->count());
        $this->assertEquals(2, count($customerClass::find()->where(['name' => ['user1', 'user2']])->all()));

        $this->assertEquals(1, $customerClass::find()->where(['AND', ['name' => ['user2', 'user3']], ['BETWEEN', 'status', 2, 4]])->count());
        $this->assertEquals(1, count($customerClass::find()->where(['AND', ['name' => ['user2', 'user3']], ['BETWEEN', 'status', 2, 4]])->all()));
    }

    public function testFindNullValues()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();

        $customer = $customerClass::findOne(2);
        $customer->name = null;
        $customer->save(false);
        $this->afterSave();

        $result = $customerClass::find()->where(['name' => null])->all();
        $this->assertEquals(1, count($result));
        $this->assertEquals(2, reset($result)->primaryKey);
    }

    public function testExists()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();

        $this->assertTrue($customerClass::find()->where(['id' => 2])->exists());
        $this->assertFalse($customerClass::find()->where(['id' => 5])->exists());
        $this->assertTrue($customerClass::find()->where(['name' => 'user1'])->exists());
        $this->assertFalse($customerClass::find()->where(['name' => 'user5'])->exists());

        $this->assertTrue($customerClass::find()->where(['id' => [2, 3]])->exists());
        $this->assertTrue($customerClass::find()->where(['id' => [2, 3]])->offset(1)->exists());
        $this->assertFalse($customerClass::find()->where(['id' => [2, 3]])->offset(2)->exists());
    }

    public function testFindLazy()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();

        $customer = $customerClass::findOne(2);
        $this->assertFalse($customer->isRelationPopulated('orders'));
        $orders = $customer->orders;
        $this->assertTrue($customer->isRelationPopulated('orders'));
        $this->assertEquals(2, count($orders));
        $this->assertEquals(1, count($customer->relatedRecords));

        // unset
        unset($customer['orders']);
        $this->assertFalse($customer->isRelationPopulated('orders'));

        /* @var $customer Customer */
        $customer = $customerClass::findOne(2);
        $this->assertFalse($customer->isRelationPopulated('orders'));
        $orders = $customer->getOrders()->where(['id' => 3])->all();
        $this->assertFalse($customer->isRelationPopulated('orders'));
        $this->assertEquals(0, count($customer->relatedRecords));

        $this->assertEquals(1, count($orders));
        $this->assertEquals(3, $orders[0]->id);
    }

    public function testFindEager()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();
        /* @var $orderClass ActiveRecordInterface */
        $orderClass = $this->getOrderClass();

        $customers = $customerClass::find()->with('orders')->indexBy('id')->all();
        ksort($customers);
        $this->assertEquals(3, count($customers));
        $this->assertTrue($customers[1]->isRelationPopulated('orders'));
        $this->assertTrue($customers[2]->isRelationPopulated('orders'));
        $this->assertTrue($customers[3]->isRelationPopulated('orders'));
        $this->assertEquals(1, count($customers[1]->orders));
        $this->assertEquals(2, count($customers[2]->orders));
        $this->assertEquals(0, count($customers[3]->orders));
        // unset
        unset($customers[1]->orders);
        $this->assertFalse($customers[1]->isRelationPopulated('orders'));

        $customer = $customerClass::find()->where(['id' => 1])->with('orders')->one();
        $this->assertTrue($customer->isRelationPopulated('orders'));
        $this->assertEquals(1, count($customer->orders));
        $this->assertEquals(1, count($customer->relatedRecords));

        // multiple with() calls
        $orders = $orderClass::find()->with('customer', 'items')->all();
        $this->assertEquals(3, count($orders));
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $orders = $orderClass::find()->with('customer')->with('items')->all();
        $this->assertEquals(3, count($orders));
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
    }

    public function testFindLazyVia()
    {
        /* @var $orderClass ActiveRecordInterface */
        $orderClass = $this->getOrderClass();

        /* @var $order Order */
        $order = $orderClass::findOne(1);
        $this->assertEquals(1, $order->id);
        $this->assertEquals(2, count($order->items));
        $this->assertEquals(1, $order->items[0]->id);
        $this->assertEquals(2, $order->items[1]->id);
    }

    public function testFindLazyVia2()
    {
        /* @var $orderClass ActiveRecordInterface */
        $orderClass = $this->getOrderClass();

        /* @var $order Order */
        $order = $orderClass::findOne(1);
        $order->id = 100;
        $this->assertEquals([], $order->items);
    }

    public function testFindEagerViaRelation()
    {
        /* @var $orderClass ActiveRecordInterface */
        $orderClass = $this->getOrderClass();

        $orders = $orderClass::find()->with('items')->orderBy('id')->all();
        $this->assertEquals(3, count($orders));
        $order = $orders[0];
        $this->assertEquals(1, $order->id);
        $this->assertTrue($order->isRelationPopulated('items'));
        $this->assertEquals(2, count($order->items));
        $this->assertEquals(1, $order->items[0]->id);
        $this->assertEquals(2, $order->items[1]->id);
    }

    public function testFindNestedRelation()
    {
        /* @var $customerClass \rock\db\common\ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();

        $customers = $customerClass::find()->with('orders', 'orders.items')->indexBy('id')->all();
        ksort($customers);
        $this->assertEquals(3, count($customers));
        $this->assertTrue($customers[1]->isRelationPopulated('orders'));
        $this->assertTrue($customers[2]->isRelationPopulated('orders'));
        $this->assertTrue($customers[3]->isRelationPopulated('orders'));
        $this->assertEquals(1, count($customers[1]->orders));
        $this->assertEquals(2, count($customers[2]->orders));
        $this->assertEquals(0, count($customers[3]->orders));
        $this->assertTrue($customers[1]->orders[0]->isRelationPopulated('items'));
        $this->assertTrue($customers[2]->orders[0]->isRelationPopulated('items'));
        $this->assertTrue($customers[2]->orders[1]->isRelationPopulated('items'));
        $this->assertEquals(2, count($customers[1]->orders[0]->items));
        $this->assertEquals(3, count($customers[2]->orders[0]->items));
        $this->assertEquals(1, count($customers[2]->orders[1]->items));
    }

    /**
     * Ensure ActiveRelationTrait does preserve order of items on find via()
     * https://github.com/yiisoft/yii2/issues/1310
     */
    public function testFindEagerViaRelationPreserveOrder()
    {
        /* @var $orderClass ActiveRecordInterface */
        $orderClass = $this->getOrderClass();

        /*
        Item (name, category_id)
        Order (customer_id, created_at, total)
        OrderItem (order_id, item_id, quantity, subtotal)

        Result should be the following:

        Order 1: 1, 1325282384, 110.0
        - orderItems:
            OrderItem: 1, 1, 1, 30.0
            OrderItem: 1, 2, 2, 40.0
        - itemsInOrder:
            Item 1: 'Agile Web Application Development with Yii1.1 and PHP5', 1
            Item 2: 'Yii 1.1 Application Development Cookbook', 1

        Order 2: 2, 1325334482, 33.0
        - orderItems:
            OrderItem: 2, 3, 1, 8.0
            OrderItem: 2, 4, 1, 10.0
            OrderItem: 2, 5, 1, 15.0
        - itemsInOrder:
            Item 5: 'Cars', 2
            Item 3: 'Ice Age', 2
            Item 4: 'Toy Story', 2
        Order 3: 2, 1325502201, 40.0
        - orderItems:
            OrderItem: 3, 2, 1, 40.0
        - itemsInOrder:
            Item 3: 'Ice Age', 2
         */
        $orders = $orderClass::find()->with('itemsInOrder1')->orderBy('created_at')->all();
        $this->assertEquals(3, count($orders));

        $order = $orders[0];
        $this->assertEquals(1, $order->id);
        $this->assertTrue($order->isRelationPopulated('itemsInOrder1'));
        $this->assertEquals(2, count($order->itemsInOrder1));
        $this->assertEquals(2, $order->itemsInOrder1[0]->id);
        $this->assertEquals(1, $order->itemsInOrder1[1]->id);

        $order = $orders[1];
        $this->assertEquals(2, $order->id);
        $this->assertTrue($order->isRelationPopulated('itemsInOrder1'));
        $this->assertEquals(3, count($order->itemsInOrder1));
        $this->assertEquals(5, $order->itemsInOrder1[0]->id);
        $this->assertEquals(3, $order->itemsInOrder1[1]->id);
        $this->assertEquals(4, $order->itemsInOrder1[2]->id);

        $order = $orders[2];
        $this->assertEquals(3, $order->id);
        $this->assertTrue($order->isRelationPopulated('itemsInOrder1'));
        $this->assertEquals(1, count($order->itemsInOrder1));
        $this->assertEquals(2, $order->itemsInOrder1[0]->id);
    }

    // different order in via table
    public function testFindEagerViaRelationPreserveOrderB()
    {
        /* @var $orderClass ActiveRecordInterface */
        $orderClass = $this->getOrderClass();

        $orders = $orderClass::find()->with('itemsInOrder2')->orderBy('created_at')->all();
        $this->assertEquals(3, count($orders));

        $order = $orders[0];
        $this->assertEquals(1, $order->id);
        $this->assertTrue($order->isRelationPopulated('itemsInOrder2'));
        $this->assertEquals(2, count($order->itemsInOrder2));
        $this->assertEquals(2, $order->itemsInOrder2[0]->id);
        $this->assertEquals(1, $order->itemsInOrder2[1]->id);

        $order = $orders[1];
        $this->assertEquals(2, $order->id);
        $this->assertTrue($order->isRelationPopulated('itemsInOrder2'));
        $this->assertEquals(3, count($order->itemsInOrder2));
        $this->assertEquals(5, $order->itemsInOrder2[0]->id);
        $this->assertEquals(3, $order->itemsInOrder2[1]->id);
        $this->assertEquals(4, $order->itemsInOrder2[2]->id);

        $order = $orders[2];
        $this->assertEquals(3, $order->id);
        $this->assertTrue($order->isRelationPopulated('itemsInOrder2'));
        $this->assertEquals(1, count($order->itemsInOrder2));
        $this->assertEquals(2, $order->itemsInOrder2[0]->id);
    }

    public function testLink()
    {
        /* @var $orderClass ActiveRecordInterface */
        /* @var $itemClass ActiveRecordInterface */
        /* @var $orderItemClass ActiveRecordInterface */
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();
        $orderClass = $this->getOrderClass();
        $orderItemClass = $this->getOrderItemClass();
        $itemClass = $this->getItemClass();

        $customer = $customerClass::findOne(2);
        $this->assertEquals(2, count($customer->orders));

        // has many
        $order = new $orderClass;
        $order->total = 100;
        $this->assertTrue($order->isNewRecord);
        $customer->link('orders', $order);
        $this->afterSave();
        $this->assertEquals(3, count($customer->orders));
        $this->assertFalse($order->isNewRecord);
        $this->assertEquals(3, count($customer->getOrders()->all()));
        $this->assertEquals(2, $order->customer_id);

        // belongs to
        $order = new $orderClass;
        $order->total = 100;
        $this->assertTrue($order->isNewRecord);
        $customer = $customerClass::findOne(1);
        $this->assertNull($order->customer);
        $order->link('customer', $customer);
        $this->assertFalse($order->isNewRecord);
        $this->assertEquals(1, $order->customer_id);
        $this->assertEquals(1, $order->customer->primaryKey);

        // via model
        $order = $orderClass::findOne(1);
        $this->assertEquals(2, count($order->items));
        $this->assertEquals(2, count($order->orderItems));
        $orderItem = $orderItemClass::findOne(['order_id' => 1, 'item_id' => 3]);
        $this->assertNull($orderItem);
        $item = $itemClass::findOne(3);
        $order->link('items', $item, ['quantity' => 10, 'subtotal' => 100]);
        $this->afterSave();
        $this->assertEquals(3, count($order->items));
        $this->assertEquals(3, count($order->orderItems));
        $orderItem = $orderItemClass::findOne(['order_id' => 1, 'item_id' => 3]);
        $this->assertTrue($orderItem instanceof $orderItemClass);
        $this->assertEquals(10, $orderItem->quantity);
        $this->assertEquals(100, $orderItem->subtotal);
    }

    public function testUnlink()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();
        /* @var $orderClass ActiveRecordInterface */
        $orderClass = $this->getOrderClass();
        /* @var $orderWithNullFKClass ActiveRecordInterface */
        $orderWithNullFKClass = $this->getOrderWithNullFKClass();

        // has many without delete
        /** @var Customer $customer */
        $customer = $customerClass::findOne(2);
        $this->assertEquals(2, count($customer->ordersWithNullFK));
        $customer->unlink('ordersWithNullFK', $customer->ordersWithNullFK[1], false);

        $this->assertEquals(1, count($customer->ordersWithNullFK));
        $orderWithNullFK = $orderWithNullFKClass::findOne(3);

        $this->assertEquals(3, $orderWithNullFK->id);
        $this->assertNull($orderWithNullFK->customer_id);

        // has many with delete
        $customer = $customerClass::findOne(2);
        $this->assertEquals(2, count($customer->orders));
        $customer->unlink('orders', $customer->orders[1], true);
        $this->afterSave();

        $this->assertEquals(1, count($customer->orders));
        $this->assertNull($orderClass::findOne(3));

        // via model with delete
        /** @var Order $order */
        $order = $orderClass::findOne(2);
        $this->assertEquals(3, count($order->items));
        $this->assertEquals(3, count($order->orderItems));
        $order->unlink('items', $order->items[2], true);
        $this->afterSave();

        $this->assertEquals(2, count($order->items));
        $this->assertEquals(2, count($order->orderItems));

        // via model without delete
        $this->assertEquals(3, count($order->itemsWithNullFK));
        $order->unlink('itemsWithNullFK', $order->itemsWithNullFK[2], false);
        $this->afterSave();

        $this->assertEquals(2, count($order->itemsWithNullFK));
        $this->assertEquals(2, count($order->orderItems));
    }

    public function testUnlinkAll()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();
        /* @var $orderClass ActiveRecordInterface */
        $orderClass = $this->getOrderClass();
        /* @var $orderItemClass ActiveRecordInterface */
        $orderItemClass = $this->getOrderItemClass();
        /* @var $itemClass ActiveRecordInterface */
        $itemClass = $this->getItemClass();
        /* @var $orderWithNullFKClass ActiveRecordInterface */
        $orderWithNullFKClass = $this->getOrderWithNullFKClass();
        /* @var $orderItemsWithNullFKClass ActiveRecordInterface */
        $orderItemsWithNullFKClass = $this->getOrderItemWithNullFKmClass();

        // has many with delete
        $customer = $customerClass::findOne(2);
        $this->assertEquals(2, count($customer->orders));
        $this->assertEquals(3, $orderClass::find()->count());
        $customer->unlinkAll('orders', true);
        $this->afterSave();
        $this->assertEquals(1, $orderClass::find()->count());
        $this->assertEquals(0, count($customer->orders));

        $this->assertNull($orderClass::findOne(2));
        $this->assertNull($orderClass::findOne(3));

        // has many without delete
        $customer = $customerClass::findOne(2);
        $this->assertEquals(2, count($customer->ordersWithNullFK));
        $this->assertEquals(3, $orderWithNullFKClass::find()->count());
        $customer->unlinkAll('ordersWithNullFK', false);
        $this->afterSave();
        $this->assertEquals(0, count($customer->ordersWithNullFK));
        $this->assertEquals(3, $orderWithNullFKClass::find()->count());
        $this->assertEquals(2, $orderWithNullFKClass::find()
            ->where(['AND', ['id' => [2, 3]], ['customer_id' => null]])
            ->count());

        // via model with delete
        /* @var $order Order */
        $order = $orderClass::findOne(1);
        $this->assertEquals(2, count($order->books));
        $orderItemCount = $orderItemClass::find()->count();
        $this->assertEquals(5, $itemClass::find()->count());
        $order->unlinkAll('books', true);
        $this->afterSave();
        $this->assertEquals(5, $itemClass::find()->count());
        $this->assertEquals($orderItemCount - 2, $orderItemClass::find()->count());
        $this->assertEquals(0, count($order->books));

        // via model without delete
        $this->assertEquals(2, count($order->booksWithNullFK));
        $orderItemCount = $orderItemsWithNullFKClass::find()->count();
        $this->assertEquals(5, $itemClass::find()->count());
        $order->unlinkAll('booksWithNullFK', false);
        $this->afterSave();
        $this->assertEquals(0, count($order->booksWithNullFK));
        $this->assertEquals(2, $orderItemsWithNullFKClass::find()
            ->where(['AND', ['item_id' => [1, 2]], ['order_id' => null]])
            ->count());
        $this->assertEquals($orderItemCount, $orderItemsWithNullFKClass::find()->count());
        $this->assertEquals(5, $itemClass::find()->count());
    }

    public function testUnlinkAllAndConditionSetNull()
    {
        /* @var $customerClass \rock\db\common\BaseActiveRecord */
        $customerClass = $this->getCustomerClass();
        /* @var $orderClass \rock\db\common\BaseActiveRecord */
        $orderClass = $this->getOrderWithNullFKClass();

        // in this test all orders are owned by customer 1
        $orderClass::updateAll(['customer_id' => 1]);
        $this->afterSave();

        $customer = $customerClass::findOne(1);
        $this->assertEquals(3, count($customer->ordersWithNullFK));
        $this->assertEquals(1, count($customer->expensiveOrdersWithNullFK));
        $this->assertEquals(3, $orderClass::find()->count());
        $customer->unlinkAll('expensiveOrdersWithNullFK');
        $this->assertEquals(3, count($customer->ordersWithNullFK));
        $this->assertEquals(0, count($customer->expensiveOrdersWithNullFK));
        $this->assertEquals(3, $orderClass::find()->count());
        $customer = $customerClass::findOne(1);
        $this->assertEquals(2, count($customer->ordersWithNullFK));
        $this->assertEquals(0, count($customer->expensiveOrdersWithNullFK));
    }

    public function testUnlinkAllAndConditionDelete()
    {
        /* @var $customerClass \rock\db\common\BaseActiveRecord */
        $customerClass = $this->getCustomerClass();
        /* @var $orderClass \rock\db\common\BaseActiveRecord */
        $orderClass = $this->getOrderClass();

        // in this test all orders are owned by customer 1
        $orderClass::updateAll(['customer_id' => 1]);
        $this->afterSave();

        $customer = $customerClass::findOne(1);
        $this->assertEquals(3, count($customer->orders));
        $this->assertEquals(1, count($customer->expensiveOrders));
        $this->assertEquals(3, $orderClass::find()->count());
        $customer->unlinkAll('expensiveOrders', true);
        $this->assertEquals(3, count($customer->orders));
        $this->assertEquals(0, count($customer->expensiveOrders));
        $this->assertEquals(2, $orderClass::find()->count());
        $customer = $customerClass::findOne(1);
        $this->assertEquals(2, count($customer->orders));
        $this->assertEquals(0, count($customer->expensiveOrders));
    }

    public static $afterSaveNewRecord;
    public static $afterSaveInsert;

    public function testInsert()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();


        /** @var Customer $customer */
        $customer = new $customerClass;
        $customer->email = 'user4@example.com';
        $customer->name = 'user4';
        $customer->address = 'address4';

        $this->assertNull($customer->id);
        $this->assertTrue($customer->isNewRecord);
        static::$afterSaveNewRecord = null;
        static::$afterSaveInsert = null;

        $this->assertTrue($customer->save());
        $this->afterSave();

        $this->assertNotNull($customer->id);
        $this->assertFalse(static::$afterSaveNewRecord);
        $this->assertTrue(static::$afterSaveInsert);
        $this->assertFalse($customer->isNewRecord);
    }

    public function testExplicitPkOnAutoIncrement()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();

        $customer = new $customerClass;
        $customer->id = 1337;
        $customer->email = 'user1337@example.com';
        $customer->name = 'user1337';
        $customer->address = 'address1337';

        $this->assertTrue($customer->isNewRecord);
        $customer->save();
        $this->afterSave();

        $this->assertEquals(1337, $customer->id);
        $this->assertFalse($customer->isNewRecord);
    }

    public function testUpdate()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();

        // save
        /* @var $customer Customer */
        $customer = $customerClass::findOne(2);
        $this->assertTrue($customer instanceof $customerClass);
        $this->assertEquals('user2', $customer->name);
        $this->assertFalse($customer->isNewRecord);
        static::$afterSaveNewRecord = null;
        static::$afterSaveInsert = null;
        $this->assertEmpty($customer->dirtyAttributes);

        $customer->name = 'user2x';
        $this->assertTrue($customer->save());
        $this->afterSave();
        $this->assertEquals('user2x', $customer->name);
        $this->assertFalse($customer->isNewRecord);
        $this->assertFalse(static::$afterSaveNewRecord);
        $this->assertFalse(static::$afterSaveInsert);
        /** @var Customer $customer2 */
        $customer2 = $customerClass::findOne(2);
        $this->assertEquals('user2x', $customer2->name);

        // updateAll
        $customer = $customerClass::findOne(3);
        $this->assertEquals('user3', $customer->name);
        $ret = $customerClass::updateAll(['name' => 'temp'], ['id' => 3]);
        $this->afterSave();
        $this->assertEquals(1, $ret);
        $customer = $customerClass::findOne(3);
        $this->assertEquals('temp', $customer->name);

        $ret = $customerClass::updateAll(['name' => 'tempX']);
        $this->afterSave();
        $this->assertEquals(3, $ret);

        $ret = $customerClass::updateAll(['name' => 'temp'], ['name' => 'user6']);
        $this->afterSave();
        $this->assertEquals(0, $ret);
    }

    public function testUpdateAttributes()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();

        /* @var $customer Customer */
        $customer = $customerClass::findOne(2);
        $this->assertTrue($customer instanceof $customerClass);
        $this->assertEquals('user2', $customer->name);
        $this->assertFalse($customer->isNewRecord);
        static::$afterSaveNewRecord = null;
        static::$afterSaveInsert = null;

        $customer->updateAttributes(['name' => 'user2x']);
        $this->afterSave();
        $this->assertEquals('user2x', $customer->name);
        $this->assertFalse($customer->isNewRecord);
        $this->assertNull(static::$afterSaveNewRecord);
        $this->assertNull(static::$afterSaveInsert);
        $customer2 = $customerClass::findOne(2);
        $this->assertEquals('user2x', $customer2->name);

        $customer = $customerClass::findOne(1);
        $this->assertEquals('user1', $customer->name);
        $this->assertEquals(1, $customer->status);
        $customer->name = 'user1x';
        $customer->status = 2;
        $customer->updateAttributes(['name']);
        $this->assertEquals('user1x', $customer->name);
        $this->assertEquals(2, $customer->status);
        $customer = $customerClass::findOne(1);
        $this->assertEquals('user1x', $customer->name);
        $this->assertEquals(1, $customer->status);
    }

    public function testUpdateCounters()
    {
        /* @var $orderItemClass ActiveRecordInterface */
        $orderItemClass = $this->getOrderItemClass();

        // updateCounters
        $pk = ['order_id' => 2, 'item_id' => 4];
        $orderItem = $orderItemClass::findOne($pk);
        $this->assertEquals(1, $orderItem->quantity);
        $ret = $orderItem->updateCounters(['quantity' => -1]);
        $this->afterSave();
        $this->assertEquals(1, $ret);
        $this->assertEquals(0, $orderItem->quantity);
        $orderItem = $orderItemClass::findOne($pk);
        $this->assertEquals(0, $orderItem->quantity);

        // updateAllCounters
        $pk = ['order_id' => 1, 'item_id' => 2];
        $orderItem = $orderItemClass::findOne($pk);
        $this->assertEquals(2, $orderItem->quantity);
        $ret = $orderItemClass::updateAllCounters([
            'quantity' => 3,
            'subtotal' => -10,
        ], $pk);
        $this->afterSave();
        $this->assertEquals(1, $ret);
        $orderItem = $orderItemClass::findOne($pk);
        $this->assertEquals(5, $orderItem->quantity);
        $this->assertEquals(30, $orderItem->subtotal);
    }

    public function testDelete()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();

        // delete
        $customer = $customerClass::findOne(2);
        $this->assertTrue($customer instanceof $customerClass);
        $this->assertEquals('user2', $customer->name);
        $customer->delete();
        $this->afterSave();
        $customer = $customerClass::findOne(2);
        $this->assertNull($customer);

        // deleteAll
        $customers = $customerClass::find()->all();
        $this->assertEquals(2, count($customers));
        $ret = $customerClass::deleteAll();
        $this->afterSave();
        $this->assertEquals(2, $ret);
        $customers = $customerClass::find()->all();
        $this->assertEquals(0, count($customers));

        $ret = $customerClass::deleteAll();
        $this->afterSave();
        $this->assertEquals(0, $ret);
    }

    /**
     * Some PDO implementations(e.g. cubrid) do not support boolean values.
     * Make sure this does not affect AR layer.
     */
    public function testBooleanAttribute()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();

        /** @var Customer $customer */
        $customer = new $customerClass();
        $customer->name = 'boolean customer';
        $customer->email = 'mail@example.com';
        $customer->status = true;
        $customer->save(false);

        $customer->refresh();
        $this->assertEquals(1, $customer->status);

        $customer->status = false;
        $customer->save(false);

        $customer->refresh();
        $this->assertEquals(0, $customer->status);

        $customers = $customerClass::find()->where(['status' => true])->all();
        $this->assertEquals(2, count($customers));

        $customers = $customerClass::find()->where(['status' => false])->all();
        $this->assertEquals(1, count($customers));
    }

    /**
     * @group php
     */
    public function testAfterFind()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();
        /* @var $orderClass BaseActiveRecord */
        $orderClass = $this->getOrderClass();


        $afterFindCalls = [];
        Event::on(BaseActiveRecord::className(), BaseActiveRecord::EVENT_AFTER_FIND, function (Event $event) use (&$afterFindCalls) {
            /* @var $ar BaseActiveRecord */
            $ar = $event->owner;
            $afterFindCalls[] = [get_class($ar), $ar->getIsNewRecord(), $ar->getPrimaryKey(), $ar->isRelationPopulated('orders')];
        });

        $customer = $customerClass::findOne(1);
        $this->assertNotNull($customer);
        $this->assertEquals([[$customerClass, false, 1, false]], $afterFindCalls);

        $afterFindCalls = [];
        $customer = $customerClass::find()->where(['id' => 1])->one();
        $this->assertNotNull($customer);
        $this->assertEquals([[$customerClass, false, 1, false]], $afterFindCalls);

        $afterFindCalls = [];
        $customer = $customerClass::find()->where(['id' => 1])->all();
        $this->assertNotNull($customer);
        $this->assertEquals([[$customerClass, false, 1, false]], $afterFindCalls);

        $afterFindCalls = [];
        $customer = $customerClass::find()->where(['id' => 1])->with('orders')->all();
        $this->assertNotNull($customer);
        $this->assertSame($customer[0]->orders[0]->id, 1);
        $this->assertEquals([
            [$this->getOrderClass(), false, 1, false],
            [$customerClass, false, 1, true],
        ], $afterFindCalls);

        $afterFindCalls = [];
        //
        //        //        if ($this instanceof \rockunit\extensions\redis\ActiveRecordTest) { // TODO redis does not support orderBy() yet
        //        //            $customer = $customerClass::find()->where(['id' => [1, 2]])->with('orders')->all();
        //        //        } else {
        //        // orderBy is needed to avoid random test failure
        $customer = $customerClass::find()->where(['id' => [1, 2]])->with('orders')->orderBy('name')->all();
        //        //}
        $this->assertNotNull($customer);
        $this->assertEquals([
            [$orderClass, false, 1, false],
            [$orderClass, false, 2, false],
            [$orderClass, false, 3, false],
            [$customerClass, false, 1, true],
            [$customerClass, false, 2, true],
        ], $afterFindCalls);

        // as Array
        $afterFindCalls = [];
        $customer = $customerClass::find()->where(['id' => 1])->asArray()->one();
        $this->assertNotNull($customer);
        $this->assertEquals([[$customerClass, true, null, false]], $afterFindCalls);


        $afterFindCalls = [];
        $customer = $customerClass::find()->where(['id' => 1])->with('orders')->asArray()->all();
        $this->assertSame($customer[0]['orders'][0]['id'], 1);
        $this->assertNotNull($customer);
        $this->assertEquals([
            [$this->getOrderClass(), true, null, false],
            [$customerClass, true, null, false],
        ], $afterFindCalls);


        $afterFindCalls = [];
        $customer = $customerClass::find()->where(['id' => [1, 2]])->with('orders')->orderBy('name')->asArray()->all();
        $this->assertNotNull($customer);
        $this->assertEquals([
            [$orderClass, true, null, false],
            [$customerClass, true, null, false],
        ], $afterFindCalls);

        unset($_POST['_method']);
    }

    /**
     * @group php
     */
    public function testAfterFindViaJoinWith()
    {
        /* @var $customerClass ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();
        /* @var $orderClass BaseActiveRecord */
        $orderClass = $this->getOrderClass();

        $afterFindCalls = [];
        Event::on(BaseActiveRecord::className(), BaseActiveRecord::EVENT_AFTER_FIND, function (Event $event) use (&$afterFindCalls) {
            /* @var $ar BaseActiveRecord */
            $ar = $event->owner;
            $afterFindCalls[] = [get_class($ar), $ar->getIsNewRecord(), $ar->getPrimaryKey(), $ar->isRelationPopulated('orders')];
        });

        // joinWith
        $afterFindCalls = [];
        $selectBuilder = SelectBuilder::selects([
            $customerClass::find()->select('*'),
            [$orderClass::find()->select(['id']), true]
        ]);
        $customer = $customerClass::find()
            ->select($selectBuilder)
            ->where(['customer.id' => 1])
            ->joinWith('orders', false)
            ->asArray()
            ->asSubattributes()
            ->one();
        $this->assertNotNull($customer);
        $this->assertSame($customer['order']['id'], 1);
        $this->assertEquals([
            [$customerClass, true, null, false],
        ], $afterFindCalls);

        $afterFindCalls = [];
        $selectBuilder = SelectBuilder::selects([
            $customerClass::find()->select('*'),
            [$orderClass::find()->select(['id']), true]
        ]);
        $customer = $customerClass::find()
            ->select($selectBuilder)
            ->where(['customer.id' => 1])
            ->joinWith('orders', false)
            ->asArray()
            ->asSubattributes()
            ->all();
        $this->assertNotNull($customer);
        $this->assertSame($customer[0]['order']['id'], 1);
        $this->assertEquals([
            [$customerClass, true, null, false],
        ], $afterFindCalls);

        $afterFindCalls = [];
        $customer = $customerClass::find()
            ->where(['customer.id' => [1, 2]])
            ->joinWith('orders', false)
            ->orderBy('customer.name')
            ->asArray()
            ->asSubattributes()
            ->all();
        $this->assertNotNull($customer);
        $this->assertEquals([
            [$customerClass, true, null, false],
        ], $afterFindCalls);

        Event::off(BaseActiveRecord::className(), BaseActiveRecord::EVENT_AFTER_FIND);
    }


    public function testFindEmptyInCondition()
    {
        /* @var $customerClass \rock\db\common\ActiveRecordInterface */
        $customerClass = $this->getCustomerClass();

        $customers = $customerClass::find()->where(['id' => [1]])->all();
        $this->assertEquals(1, count($customers));

        $customers = $customerClass::find()->where(['id' => []])->all();
        $this->assertEquals(0, count($customers));

        $customers = $customerClass::find()->where(['IN', 'id', [1]])->all();
        $this->assertEquals(1, count($customers));

        $customers = $customerClass::find()->where(['IN', 'id', []])->all();
        $this->assertEquals(0, count($customers));
    }

    public function testFindEagerIndexBy()
    {
        /* @var $orderClass \rock\db\common\ActiveRecordInterface */
        $orderClass = $this->getOrderClass();

        /* @var $order Order */
        $order = $orderClass::find()->with('itemsIndexed')->where(['id' => 1])->one();
        $this->assertTrue($order->isRelationPopulated('itemsIndexed'));
        $items = $order->itemsIndexed;
        $this->assertEquals(2, count($items));
        $this->assertTrue(isset($items[1]));
        $this->assertTrue(isset($items[2]));

        /* @var $order Order */
        $order = $orderClass::find()->with('itemsIndexed')->where(['id' => 2])->one();
        $this->assertTrue($order->isRelationPopulated('itemsIndexed'));
        $items = $order->itemsIndexed;
        $this->assertEquals(3, count($items));
        $this->assertTrue(isset($items[3]));
        $this->assertTrue(isset($items[4]));
        $this->assertTrue(isset($items[5]));
    }

    public function testCache()
    {
        if (!interface_exists('\rock\cache\CacheInterface') || !class_exists('\League\Flysystem\Filesystem')) {
            $this->markTestSkipped('Rock cache not installed.');
            return;
        }

        /* @var $customerClass ActiveRecordInterface|Customer */
        $customerClass = $this->getCustomerClass();
        /* @var $orderClass BaseActiveRecord */
        $orderClass = $this->getOrderClass();

        /** @var CacheInterface $cache */
        $cache = static::getCache();
        $cache->flush();

        /* @var $connection Connection */
        $connection = $this->getConnection();
        Trace::removeAll();

        $connection->enableQueryCache = true;
        $connection->queryCache = $cache;

        $selectBuilder = SelectBuilder::selects([
            $customerClass::find()->select('*'),
            [$orderClass::find()->select(['id']), true]
        ]);
        $query = $customerClass::find()->select($selectBuilder)->where(['customer.id' => 1])->joinWith('orders', false)->asArray()->asSubattributes();
        $this->assertNotEmpty($query->one($connection));
        $this->assertFalse(Trace::getIterator('db.query')->current()['cache']);
        $customer = $query->one($connection);
        $this->assertSame($customer['order']['id'], 1);
        $this->assertTrue(Trace::getIterator('db.query')->current()['cache']);
        $this->assertNotEmpty($query->notCache()->one($connection));
        $this->assertFalse(Trace::getIterator('db.query')->current()['cache']);
        $this->assertSame(Trace::getIterator('db.query')->current()['count'], 3);

        Trace::removeAll();
        $cache->flush();

        $connection->enableQueryCache = false;
        $connection->queryCache = $cache;
        \rockunit\models\ActiveRecord::$connection = $connection;
        $customerClass::find()->with(['orders'])->asArray()->cache()->all();
        $customerClass::find()->with(['orders' => function (ActiveQuery $query) {
            $query->notCache();
        }])->asArray()->cache()->all();
        $trace = Trace::getIterator('db.query');
        $this->assertTrue($trace->current()['cache']);
        $trace->next();
        $this->assertFalse($trace->current()['cache']);

        Trace::removeAll();
        $cache->flush();

        // expire
        $connection->queryCacheExpire = 1;
        $connection->enableQueryCache = true;
        $connection->queryCache = $cache;
        \rockunit\models\ActiveRecord::$connection = $connection;
        $customerClass::find()->with(['orders'])->asArray()->all();
        sleep(3);
        $customerClass::find()->with(['orders'])->asArray()->all();
        $trace = Trace::getIterator('db.query');
        $this->assertFalse($trace->current()['cache']);
        $trace->next();
        $this->assertFalse($trace->current()['cache']);
    }

    public function testInsertWithRule()
    {
        /* @var $customerRulesClass ActiveRecordInterface */
        $customerRulesClass = $this->getCustomerRulesClass();

        // fail
        /** @var Customer $customer */
        $customer = new $customerRulesClass;
        $customer->email = 'user4@example.com';
        $customer->name = 'user4';
        $customer->address = 'address4';
        $this->assertFalse($customer->save());
        $this->assertNotEmpty($customer->getErrors());

        // success
        /** @var Customer $customer */
        $customer = new $customerRulesClass;
        //$customer->id = 5;
        $customer->email = 'user4@example.com';
        $customer->name = 4;
        $customer->address = 'address4';
        static::$afterSaveNewRecord = null;
        static::$afterSaveInsert = null;
        $this->assertTrue($customer->save());
    }

    public function testSelectBuilder()
    {
        /* @var $customerClass ActiveRecordInterface|Customer */
        $customerClass = $this->getCustomerClass();
        /* @var $orderClass BaseActiveRecord */
        $orderClass = $this->getOrderClass();
        $query = $customerClass::find()
            ->select(
                SelectBuilder::select($customerClass::find()->select(['id', 'name']), true)
                    ->select($orderClass::find()->select(['id', 'total']), 'orders', '+')
            );

        $sql = $this->replaceQuotes(
            "SELECT `customer`.`id` AS `customer.id`, `customer`.`name` AS `customer.name`, `order`.`id` AS `orders+id`, `order`.`total` AS `orders+total` FROM `customer`");
        $this->assertSame($query->getRawSql(), $sql);

    }

    public function testCustomColumns()
    {
        // find custom column
        if ($this->driverName === 'oci') {
            $customer = Customer::find()->select(['{{customer}}.*', '([[status]]*2) AS [[status2]]'])
                ->where(['name' => 'user3'])->one();
        } else {
            $customer = Customer::find()->select(['*', '([[status]]*2) AS [[status2]]'])
                ->where(['name' => 'user3'])->one();
        }
        $this->assertEquals(3, $customer->id);
        $this->assertEquals(4, $customer->status2);
    }

    public function testStatisticalFind()
    {
        // find count, sum, average, min, max, scalar
        $this->assertEquals(3, Customer::find()->count());
        $this->assertEquals(2, Customer::find()->where('[[id]]=1 OR [[id]]=2')->count());
        $this->assertEquals(6, Customer::find()->sum('[[id]]'));
        $this->assertEquals(2, Customer::find()->average('[[id]]'));
        $this->assertEquals(1, Customer::find()->min('[[id]]'));
        $this->assertEquals(3, Customer::find()->max('[[id]]'));
        $this->assertEquals(3, Customer::find()->select('COUNT(*)')->scalar());
    }

//    public function testFindScalar()
//    {
//        // query scalar
//        $customerName = Customer::find()->where(['[[id]]' => 2])->select('[[name]]')->scalar();
//        $this->assertEquals('user2', $customerName);
//    }
//
//    public function testFindColumn()
//    {
//        $this->assertEquals(['user1', 'user2', 'user3'], Customer::find()->select('[[name]]')->column());
//        $this->assertEquals(['user3', 'user2', 'user1'], Customer::find()->orderBy(['[[name]]' => SORT_DESC])->select('[[name]]')->column());
//    }

    public function testFindBySql()
    {
        // find one
        $customer = Customer::findBySql('SELECT * FROM {{customer}} ORDER BY [[id]] DESC')->one();
        $this->assertTrue($customer instanceof Customer);
        $this->assertEquals('user3', $customer->name);

        // find all
        $customers = Customer::findBySql('SELECT * FROM {{customer}}')->all();
        $this->assertEquals(3, count($customers));

        // find with parameter binding
        $customer = Customer::findBySql('SELECT * FROM {{customer}} WHERE [[id]]=:id', [':id' => 2])->one();
        $this->assertTrue($customer instanceof Customer);
        $this->assertEquals('user2', $customer->name);
    }

    /**
     * @depends testFindBySql
     *
     * @see https://github.com/yiisoft/yii2/issues/8593
     */
    public function testCountWithFindBySql()
    {
        $query = Customer::findBySql('SELECT * FROM {{customer}}');
        $this->assertEquals(3, $query->count());
        $query = Customer::findBySql('SELECT * FROM {{customer}} WHERE  [[id]]=:id', [':id' => 2]);
        $this->assertEquals(1, $query->count());
    }

    public function testFindLazyViaTable()
    {
        /* @var $order Order */
        $order = Order::findOne(1);
        $this->assertEquals(1, $order->id);
        $this->assertEquals(2, count($order->books));
        $this->assertEquals(1, $order->items[0]->id);
        $this->assertEquals(2, $order->items[1]->id);

        $order = Order::findOne(2);
        $this->assertEquals(2, $order->id);
        $this->assertEquals(0, count($order->books));

        $order = Order::find()->where(['id' => 1])->asArray()->one();
        $this->assertTrue(is_array($order));
    }

    public function testFindEagerViaTable()
    {
        $orders = Order::find()->with('books')->orderBy('id')->all();
        $this->assertEquals(3, count($orders));

        $order = $orders[0];
        $this->assertEquals(1, $order->id);
        $this->assertEquals(2, count($order->books));
        $this->assertEquals(1, $order->books[0]->id);
        $this->assertEquals(2, $order->books[1]->id);

        $order = $orders[1];
        $this->assertEquals(2, $order->id);
        $this->assertEquals(0, count($order->books));

        $order = $orders[2];
        $this->assertEquals(3, $order->id);
        $this->assertEquals(1, count($order->books));
        $this->assertEquals(2, $order->books[0]->id);

        // https://github.com/yiisoft/yii2/issues/1402
        $orders = Order::find()->with('books')->orderBy('id')->asArray()->all();
        $this->assertEquals(3, count($orders));
        $this->assertTrue(is_array($orders[0]['orderItems'][0]));

        $order = $orders[0];
        $this->assertTrue(is_array($order));
        $this->assertEquals(1, $order['id']);
        $this->assertEquals(2, count($order['books']));
        $this->assertEquals(1, $order['books'][0]['id']);
        $this->assertEquals(2, $order['books'][1]['id']);
    }

    // deeply nested table relation
    public function testDeeplyNestedTableRelation()
    {
        /* @var $customer Customer */
        $customer = Customer::findOne(1);
        $this->assertNotNull($customer);

        $items = $customer->orderItems;

        $this->assertEquals(2, count($items));
        $this->assertInstanceOf(Item::className(), $items[0]);
        $this->assertInstanceOf(Item::className(), $items[1]);
        $this->assertEquals(1, $items[0]->id);
        $this->assertEquals(2, $items[1]->id);
    }

    /**
     * https://github.com/yiisoft/yii2/issues/5341
     *
     * Issue:     Plan     1 -- * Account * -- * User
     * Our Tests: Category 1 -- * Item    * -- * Order
     */
    public function testDeeplyNestedTableRelation2()
    {
        /* @var $category Category */
        $category = Category::findOne(1);
        $this->assertNotNull($category);
        $orders = $category->orders;
        $this->assertEquals(2, count($orders));
        $this->assertInstanceOf(Order::className(), $orders[0]);
        $this->assertInstanceOf(Order::className(), $orders[1]);
        $ids = [$orders[0]->id, $orders[1]->id];
        sort($ids);
        $this->assertEquals([1, 3], $ids);

        $category = Category::findOne(2);
        $this->assertNotNull($category);
        $orders = $category->orders;
        $this->assertEquals(1, count($orders));
        $this->assertInstanceOf(Order::className(), $orders[0]);
        $this->assertEquals(2, $orders[0]->id);

    }

    public function testStoreNull()
    {
        $record = new NullValues();
        $this->assertNull($record->var1);
        $this->assertNull($record->var2);
        $this->assertNull($record->var3);
        $this->assertNull($record->stringcol);

        $record->id = 1;

        $record->var1 = 123;
        $record->var2 = 456;
        $record->var3 = 789;
        $record->stringcol = 'hello!';

        $record->save(false);
        $this->assertTrue($record->refresh());

        $this->assertEquals(123, $record->var1);
        $this->assertEquals(456, $record->var2);
        $this->assertEquals(789, $record->var3);
        $this->assertEquals('hello!', $record->stringcol);

        $record->var1 = null;
        $record->var2 = null;
        $record->var3 = null;
        $record->stringcol = null;

        $record->save(false);
        $this->assertTrue($record->refresh());

        $this->assertNull($record->var1);
        $this->assertNull($record->var2);
        $this->assertNull($record->var3);
        $this->assertNull($record->stringcol);

        $record->var1 = 0;
        $record->var2 = 0;
        $record->var3 = 0;
        $record->stringcol = '';

        $record->save(false);
        $this->assertTrue($record->refresh());

        $this->assertEquals(0, $record->var1);
        $this->assertEquals(0, $record->var2);
        $this->assertEquals(0, $record->var3);
        $this->assertEquals('', $record->stringcol);
    }

    public function testStoreEmpty()
    {
        $record = new NullValues();
        $record->id = 1;

        // this is to simulate empty html form submission
        $record->var1 = '';
        $record->var2 = '';
        $record->var3 = '';
        $record->stringcol = '';

        $record->save(false);
        $this->assertTrue($record->refresh());

        // https://github.com/yiisoft/yii2/commit/34945b0b69011bc7cab684c7f7095d837892a0d4#commitcomment-4458225
        $this->assertTrue($record->var1 === $record->var2);
        $this->assertTrue($record->var2 === $record->var3);
    }

    public function testIsPrimaryKey()
    {
        $this->assertFalse(Customer::isPrimaryKey([]));
        $this->assertTrue(Customer::isPrimaryKey(['id']));
        $this->assertFalse(Customer::isPrimaryKey(['id', 'name']));
        $this->assertFalse(Customer::isPrimaryKey(['name']));
        $this->assertFalse(Customer::isPrimaryKey(['name', 'email']));

        $this->assertFalse(OrderItem::isPrimaryKey([]));
        $this->assertFalse(OrderItem::isPrimaryKey(['order_id']));
        $this->assertFalse(OrderItem::isPrimaryKey(['item_id']));
        $this->assertFalse(OrderItem::isPrimaryKey(['quantity']));
        $this->assertFalse(OrderItem::isPrimaryKey(['quantity', 'subtotal']));
        $this->assertTrue(OrderItem::isPrimaryKey(['order_id', 'item_id']));
        $this->assertFalse(OrderItem::isPrimaryKey(['order_id', 'item_id', 'quantity']));
    }

    public function testJoinWith()
    {
        // left join and eager loading
        $orders = Order::find()->joinWith('customer')->orderBy('customer.id DESC, order.id')->all();
        $this->assertEquals(3, count($orders));
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertEquals(1, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));

        // inner join filtering and eager loading
        $orders = Order::find()->innerJoinWith([
            'customer' => function (ActiveQuery $query) {
                $query->where('{{customer}}.[[id]]=2');
            },
        ])->orderBy('order.id')->all();
        $this->assertEquals(2, count($orders));
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));

        // inner join filtering, eager loading, conditions on both primary and relation
        $orders = Order::find()->innerJoinWith([
            'customer' => function (ActiveQuery $query) {
                $query->where('{{customer}}.[[id]]=2');
            },
        ])->where(['order.id' => [1, 2]])->orderBy('order.id')->all();
        $this->assertEquals(1, count($orders));
        $this->assertEquals(2, $orders[0]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));

        // inner join filtering without eager loading
        $orders = Order::find()->innerJoinWith([
            'customer' => function (ActiveQuery $query) {
                $query->where('{{customer}}.[[id]]=2');
            },
        ], false)->orderBy('order.id')->all();
        $this->assertEquals(2, count($orders));
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertFalse($orders[0]->isRelationPopulated('customer'));
        $this->assertFalse($orders[1]->isRelationPopulated('customer'));

        // inner join filtering without eager loading, conditions on both primary and relation
        $orders = Order::find()->innerJoinWith([
            'customer' => function (ActiveQuery $query) {
                $query->where('{{customer}}.[[id]]=2');
            },
        ], false)->where(['order.id' => [1, 2]])->orderBy('order.id')->all();
        $this->assertEquals(1, count($orders));
        $this->assertEquals(2, $orders[0]->id);
        $this->assertFalse($orders[0]->isRelationPopulated('customer'));

        // join with via-relation
        $orders = Order::find()->innerJoinWith('books')->orderBy('order.id')->all();
        $this->assertEquals(2, count($orders));
        $this->assertEquals(1, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('books'));
        $this->assertTrue($orders[1]->isRelationPopulated('books'));
        $this->assertEquals(2, count($orders[0]->books));
        $this->assertEquals(1, count($orders[1]->books));

        // join with sub-relation
        $orders = Order::find()->innerJoinWith([
            'items' => function (ActiveQuery $q) {
                $q->orderBy('item.id');
            },
            'items.category' => function (ActiveQuery $q) {
                $q->where('category.id = 2');
            },
        ])->orderBy('order.id')->all();
        $this->assertEquals(1, count($orders));
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, count($orders[0]->items));
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));
        $this->assertEquals(2, $orders[0]->items[0]->category->id);

        // join with table alias
        $orders = Order::find()->joinWith([
            'customer' => function (ActiveQuery $q) {
                $q->from('customer c');
            }
        ])->orderBy('c.id DESC, order.id')->all();
        $this->assertEquals(3, count($orders));
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, $orders[1]->id);
        $this->assertEquals(1, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('customer'));
        $this->assertTrue($orders[1]->isRelationPopulated('customer'));
        $this->assertTrue($orders[2]->isRelationPopulated('customer'));

        // join with ON condition
        $orders = Order::find()->joinWith('books2')->orderBy('order.id')->all();
        $this->assertEquals(3, count($orders));
        $this->assertEquals(1, $orders[0]->id);
        $this->assertEquals(2, $orders[1]->id);
        $this->assertEquals(3, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('books2'));
        $this->assertTrue($orders[1]->isRelationPopulated('books2'));
        $this->assertTrue($orders[2]->isRelationPopulated('books2'));
        $this->assertEquals(2, count($orders[0]->books2));
        $this->assertEquals(0, count($orders[1]->books2));
        $this->assertEquals(1, count($orders[2]->books2));

        // lazy loading with ON condition
        $order = Order::findOne(1);
        $this->assertEquals(2, count($order->books2));
        $order = Order::findOne(2);
        $this->assertEquals(0, count($order->books2));
        $order = Order::findOne(3);
        $this->assertEquals(1, count($order->books2));

        // eager loading with ON condition
        $orders = Order::find()->with('books2')->all();
        $this->assertEquals(3, count($orders));
        $this->assertEquals(1, $orders[0]->id);
        $this->assertEquals(2, $orders[1]->id);
        $this->assertEquals(3, $orders[2]->id);
        $this->assertTrue($orders[0]->isRelationPopulated('books2'));
        $this->assertTrue($orders[1]->isRelationPopulated('books2'));
        $this->assertTrue($orders[2]->isRelationPopulated('books2'));
        $this->assertEquals(2, count($orders[0]->books2));
        $this->assertEquals(0, count($orders[1]->books2));
        $this->assertEquals(1, count($orders[2]->books2));

        // join with count and query
        $query = Order::find()->joinWith('customer');
        $count = $query->count();
        $this->assertEquals(3, $count);
        $orders = $query->all();
        $this->assertEquals(3, count($orders));

        // https://github.com/yiisoft/yii2/issues/2880
        $query = Order::findOne(1);
        $customer = $query->getCustomer()->joinWith([
            'orders' => function (ActiveQuery $q) {
                $q->orderBy([]);
            }
        ])->one();
        $this->assertEquals(1, $customer->id);
        $order = Order::find()->joinWith([
            'items' => function (ActiveQuery $q) {
                $q->from(['items' => 'item'])
                    ->orderBy('items.id');
            },
        ])->orderBy('order.id')->one();

        // join with sub-relation called inside Closure
        $orders = Order::find()
            ->joinWith([
                'items' => function (ActiveQuery $q) {
                    $q->orderBy('item.id');
                    $q->joinWith([
                        'category' => function (ActiveQuery $q) {
                            $q->where('category.id = 2');
                        }
                    ]);
                }
            ])
            ->orderBy('order.id')
            ->all();
        $this->assertEquals(1, count($orders));
        $this->assertTrue($orders[0]->isRelationPopulated('items'));
        $this->assertEquals(2, $orders[0]->id);
        $this->assertEquals(3, count($orders[0]->items));
        $this->assertTrue($orders[0]->items[0]->isRelationPopulated('category'));
        $this->assertEquals(2, $orders[0]->items[0]->category->id);
    }

    public function testJoinWithAndScope()
    {
        // hasOne inner join
        $customers = Customer::find()->active()->innerJoinWith('profile')->orderBy('customer.id')->all();
        $this->assertEquals(1, count($customers));
        $this->assertEquals(1, $customers[0]->id);
        $this->assertTrue($customers[0]->isRelationPopulated('profile'));

        // hasOne outer join
        $customers = Customer::find()->active()->joinWith('profile')->orderBy('customer.id')->all();
        $this->assertEquals(2, count($customers));
        $this->assertEquals(1, $customers[0]->id);
        $this->assertEquals(2, $customers[1]->id);
        $this->assertTrue($customers[0]->isRelationPopulated('profile'));
        $this->assertTrue($customers[1]->isRelationPopulated('profile'));
        $this->assertInstanceOf(Profile::className(), $customers[0]->profile);
        $this->assertNull($customers[1]->profile);

        // hasMany
        $customers = Customer::find()
            ->active()
            ->joinWith([
                'orders' => function (ActiveQuery $q) {
                    $q->orderBy('order.id');
                }
            ])
            ->orderBy('customer.id DESC, order.id')
            ->all();
        $this->assertEquals(2, count($customers));
        $this->assertEquals(2, $customers[0]->id);
        $this->assertEquals(1, $customers[1]->id);
        $this->assertTrue($customers[0]->isRelationPopulated('orders'));
        $this->assertTrue($customers[1]->isRelationPopulated('orders'));
    }

    /**
     * This query will do the same join twice, ensure duplicated JOIN gets removed
     * https://github.com/yiisoft/yii2/pull/2650
     */
    public function testJoinWithVia()
    {
        Order::getConnection()->getQueryBuilder()->separator = "\n";
        Order::find()
            ->joinWith('itemsInOrder1')
            ->joinWith([
                'items' => function (ActiveQuery $q) {
                    $q->orderBy('item.id');
                },
            ])->all();
    }

    public function testInverseOf()
    {
        // eager loading: find one and all
        $customer = Customer::find()->with('orders2')->where(['id' => 1])->one();
        $this->assertTrue($customer->orders2[0]->customer2 === $customer);
        $customers = Customer::find()->with('orders2')->where(['id' => [1, 3]])->all();
        $this->assertTrue($customers[0]->orders2[0]->customer2 === $customers[0]);
        $this->assertTrue(empty($customers[1]->orders2));
        // lazy loading
        $customer = Customer::findOne(2);
        $orders = $customer->orders2;
        $this->assertTrue(count($orders) === 2);
        $this->assertTrue($customer->orders2[0]->customer2 === $customer);
        $this->assertTrue($customer->orders2[1]->customer2 === $customer);
        // ad-hoc lazy loading
        $customer = Customer::findOne(2);
        $orders = $customer->getOrders2()->all();
        $this->assertTrue(count($orders) === 2);
        $this->assertTrue($customer->orders2[0]->customer2 === $customer);
        $this->assertTrue($customer->orders2[1]->customer2 === $customer);

        // the other way around
        $customer = Customer::find()->with('orders2')->where(['id' => 1])->asArray()->one();
        $this->assertTrue($customer['orders2'][0]['customer2']['id'] === $customer['id']);
        $customers = Customer::find()->with('orders2')->where(['id' => [1, 3]])->asArray()->all();
        $this->assertTrue($customer['orders2'][0]['customer2']['id'] === $customers[0]['id']);
        $this->assertTrue(empty($customers[1]['orders2']));

        $orders = Order::find()->with('customer2')->where(['id' => 1])->all();
        $this->assertTrue($orders[0]->customer2->orders2 === [$orders[0]]);
        $order = Order::find()->with('customer2')->where(['id' => 1])->one();
        $this->assertTrue($order->customer2->orders2 === [$order]);

        $orders = Order::find()->with('customer2')->where(['id' => 1])->asArray()->all();
        $this->assertTrue($orders[0]['customer2']['orders2'][0]['id'] === $orders[0]['id']);
        $order = Order::find()->with('customer2')->where(['id' => 1])->asArray()->one();
        $this->assertTrue($order['customer2']['orders2'][0]['id'] === $orders[0]['id']);

        $orders = Order::find()->with('customer2')->where(['id' => [1, 3]])->all();
        $this->assertTrue($orders[0]->customer2->orders2 === [$orders[0]]);
        $this->assertTrue($orders[1]->customer2->orders2 === [$orders[1]]);

        $orders = Order::find()->with('customer2')->where(['id' => [2, 3]])->orderBy('id')->all();
        $this->assertTrue($orders[0]->customer2->orders2 === $orders);
        $this->assertTrue($orders[1]->customer2->orders2 === $orders);

        $orders = Order::find()->with('customer2')->where(['id' => [2, 3]])->orderBy('id')->asArray()->all();
        $this->assertTrue($orders[0]['customer2']['orders2'][0]['id'] === $orders[0]['id']);
        $this->assertTrue($orders[0]['customer2']['orders2'][1]['id'] === $orders[1]['id']);
        $this->assertTrue($orders[1]['customer2']['orders2'][0]['id'] === $orders[0]['id']);
        $this->assertTrue($orders[1]['customer2']['orders2'][1]['id'] === $orders[1]['id']);
    }

    public function testDefaultValues()
    {
        $model = new Type();
        $model->loadDefaultValues();
        $this->assertEquals(1, $model->int_col2);
        $this->assertEquals('something', $model->char_col2);
        $this->assertEquals(1.23, $model->float_col2);
        $this->assertEquals(33.22, $model->numeric_col);
        $this->assertEquals(true, $model->bool_col2);

//        if ($this instanceof PostgreSQLActiveRecordTest) {
//            // PostgreSQL has non-standard timestamp representation
//            $this->assertEquals('12:00:00 AM 01/01/2002', $model->time);
//        } else {
        $this->assertEquals('2002-01-01 00:00:00', $model->time);
        // }

        $model = new Type();
        $model->char_col2 = 'not something';

        $model->loadDefaultValues();
        $this->assertEquals('not something', $model->char_col2);

        $model = new Type();
        $model->char_col2 = 'not something';

        $model->loadDefaultValues(false);
        $this->assertEquals('something', $model->char_col2);
    }

    public function testUnlinkAllViaTable()
    {
        /* @var $orderClass \rock\db\common\ActiveRecordInterface */
        $orderClass = $this->getOrderClass();
        /* @var $orderItemClass \rock\db\common\ActiveRecordInterface */
        $orderItemClass = $this->getOrderItemClass();
        /* @var $itemClass \rock\db\common\ActiveRecordInterface */
        $itemClass = $this->getItemClass();
        /* @var $orderItemsWithNullFKClass \rock\db\common\ActiveRecordInterface */
        $orderItemsWithNullFKClass = $this->getOrderItemWithNullFKmClass();

        // via table with delete
        /* @var $order  Order */
        $order = $orderClass::findOne(1);
        $this->assertEquals(2, count($order->booksViaTable));
        $orderItemCount = $orderItemClass::find()->count();
        $this->assertEquals(5, $itemClass::find()->count());
        $order->unlinkAll('booksViaTable', true);
        $this->afterSave();
        $this->assertEquals(5, $itemClass::find()->count());
        $this->assertEquals($orderItemCount - 2, $orderItemClass::find()->count());
        $this->assertEquals(0, count($order->booksViaTable));

        // via table without delete
        $this->assertEquals(2, count($order->booksWithNullFKViaTable));
        $orderItemCount = $orderItemsWithNullFKClass::find()->count();
        $this->assertEquals(5, $itemClass::find()->count());
        $order->unlinkAll('booksWithNullFKViaTable', false);
        $this->assertEquals(0, count($order->booksWithNullFKViaTable));
        $this->assertEquals(2, $orderItemsWithNullFKClass::find()->where(['AND', ['item_id' => [1, 2]], ['order_id' => null]])->count());
        $this->assertEquals($orderItemCount, $orderItemsWithNullFKClass::find()->count());
        $this->assertEquals(5, $itemClass::find()->count());
    }

    public function testCastValues()
    {
        $model = new Type();
        $model->int_col = 123;
        $model->int_col2 = 456;
        $model->smallint_col = 42;
        $model->char_col = '1337';
        $model->char_col2 = 'test';
        $model->char_col3 = 'test123';
        $model->float_col = 3.742;
        $model->float_col2 = 42.1337;
        $model->bool_col = true;
        $model->bool_col2 = false;
        $model->save(false);

        /* @var $model Type */
        $model = Type::find()->one();
        $this->assertSame(123, $model->int_col);
        $this->assertSame(456, $model->int_col2);
        $this->assertSame(42, $model->smallint_col);
        $this->assertSame('1337', trim($model->char_col));
        $this->assertSame('test', $model->char_col2);
        $this->assertSame('test123', $model->char_col3);
        //        $this->assertSame(1337.42, $model->float_col);
        //        $this->assertSame(42.1337, $model->float_col2);
        //        $this->assertSame(true, $model->bool_col);
        //        $this->assertSame(false, $model->bool_col2);
    }

    public function testIssues()
    {
        // https://github.com/yiisoft/yii2/issues/4938
        $category = Category::findOne(2);
        $this->assertTrue($category instanceof Category);
        $this->assertEquals(3, $category->getItems()->count());
        $this->assertEquals(1, $category->getLimitedItems()->count());
        $this->assertEquals(1, $category->getLimitedItems()->distinct(true)->count());

        // https://github.com/yiisoft/yii2/issues/3197
        $orders = Order::find()->with('orderItems')->orderBy('id')->all();
        $this->assertEquals(3, count($orders));
        $this->assertEquals(2, count($orders[0]->orderItems));
        $this->assertEquals(3, count($orders[1]->orderItems));
        $this->assertEquals(1, count($orders[2]->orderItems));
        $orders = Order::find()->with(['orderItems' => function (ActiveQuery $q) {
            $q->indexBy('item_id');
        }])->orderBy('id')->all();
        $this->assertEquals(3, count($orders));
        $this->assertEquals(2, count($orders[0]->orderItems));
        $this->assertEquals(3, count($orders[1]->orderItems));
        $this->assertEquals(1, count($orders[2]->orderItems));

        // https://github.com/yiisoft/yii2/issues/8149
        $model = new Customer();
        $model->name = 'test';
        $model->email = 'test';
        $model->save(false);
        $model->updateCounters(['status' => 1]);
        $this->assertEquals(1, $model->status);
    }

    public function testPopulateRecordCallWhenQueryingOnParentClass()
    {
        (new Cat())->save(false);
        (new Dog())->save(false);
        $animal = Animal::find()->where(['type' => Dog::className()])->one();
        $this->assertEquals('bark', $animal->getDoes());
        $animal = Animal::find()->where(['type' => Cat::className()])->one();
        $this->assertEquals('meow', $animal->getDoes());
    }

    public function testSaveEmpty()
    {
        $record = new NullValues;
        $this->assertTrue($record->save(false));
        $this->assertEquals(1, $record->id);
    }

    public function testOptimisticLock()
    {
        /* @var $record Document */
        $record = Document::findOne(1);
        $record->content = 'New Content';
        $record->save(false);
        $this->assertEquals(1, $record->version);
        $record = Document::findOne(1);
        $record->content = 'Rewrite attempt content';
        $record->version = 0;
        $this->setExpectedException(DbException::className());
        $record->save(false);
    }

    public function testTypeCast()
    {
        $connection = ActiveRecord::$connection;

        // enable type cast

        $connection->typeCast = true;

        // find one
        $customer = Customer::find()->one($connection);
        $this->assertInternalType('int', $customer->id);
        $this->assertInternalType('int', $customer->profile_id);
        $this->assertInternalType('string', $customer->name);
        $customer = Customer::find()->asArray()->one($connection);
        $this->assertInternalType('int', $customer['id']);
        $this->assertInternalType('int', $customer['profile_id']);
        $this->assertInternalType('string', $customer['name']);

        // find all
        $customer = Customer::find()->all($connection);
        $this->assertInternalType('int', $customer[0]->id);
        $this->assertInternalType('int', $customer[0]->profile_id);
        $this->assertInternalType('string', $customer[0]->name);
        $customer = Customer::find()->asArray()->all($connection);
        $this->assertInternalType('int', $customer[0]['id']);
        $this->assertInternalType('int', $customer[0]['profile_id']);
        $this->assertInternalType('string', $customer[0]['name']);

        // disable type cast

        $connection->typeCast = false;

        // find one
        $customer = Customer::find()->one($connection);
        $this->assertInternalType('string', $customer->id);
        $this->assertInternalType('string', $customer->profile_id);
        $this->assertInternalType('string', $customer->name);
        $customer = Customer::find()->asArray()->one($connection);
        $this->assertInternalType('string', $customer['id']);
        $this->assertInternalType('string', $customer['profile_id']);
        $this->assertInternalType('string', $customer['name']);

        // find all
        $customer = Customer::find()->all($connection);
        $this->assertInternalType('string', $customer[0]->id);
        $this->assertInternalType('string', $customer[0]->profile_id);
        $this->assertInternalType('string', $customer[0]->name);
        $customer = Customer::find()->asArray()->all($connection);
        $this->assertInternalType('string', $customer[0]['id']);
        $this->assertInternalType('string', $customer[0]['profile_id']);
        $this->assertInternalType('string', $customer[0]['name']);
    }
}