<?php

namespace rockunit;

use rock\base\BaseException;
use rock\db\ActiveDataProvider;
use rock\db\DbException;
use rock\db\Query;
use rockunit\models\ActiveRecord;
use rockunit\models\Customer;

/**
 * @group db
 * @group mysql
 */
class ActiveDataProviderTest extends DatabaseTestCase
{
    protected function setUp()
    {
        parent::setUp();
        ActiveRecord::$connection = $this->getConnection(false);
    }

    public function testActiveQuery()
    {
        // as Array
        $provider = new ActiveDataProvider(
            [
                'query' => Customer::find()->asArray(),
                'only' => ['id', 'name'],
                'pagination' => ['limit' => 2, 'sort' => SORT_DESC]
            ]
        );

        $this->assertSame(2, count($provider->get()));
        $this->assertNotEmpty($provider->getPagination()->toArray());
        $this->assertNotEmpty($provider->getPagination()['pageLast']);
        $this->assertNotEmpty($provider->getPagination()['pageLast']);
        $this->assertTrue(isset($provider->getPagination()['pageLast']));
        $this->assertSame(3, $provider->getTotalCount());
        $this->assertSame(2, count($provider->getKeys()));
        // to Array
        $result = $provider->toArray()[0];
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertSame(2, count($result));
        $this->assertSame(2, count($provider->getKeys()));

        // as ActiveRecord
        $provider = new ActiveDataProvider(
            [
                'query' => Customer::find()->with('profile'),
                'only' => ['id', 'name'],
                'exclude' => ['id'],
                'expand' => ['profile'],
                'pagination' => ['limit' => 2, 'sort' => SORT_DESC]
            ]
        );
        $this->assertSame(2, count($provider->get()));
        $this->assertNotEmpty($provider->getPagination()->toArray());
        $this->assertSame(3, $provider->getTotalCount());
        $this->assertSame(2, count($provider->getKeys()));

        // to Array
        $result = $provider->toArray()[0];
        $this->assertArrayNotHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('profile', $result);
        $this->assertNotEmpty($result['profile']);
        $this->assertSame(2, count($result));
        $this->assertSame(2, count($provider->getKeys()));

        // one + ActiveRecord
        $provider = new ActiveDataProvider(
            [
                'query' => Customer::find()->with('profile')->one(),
                'only' => ['id', 'name'],
                'exclude' => ['id'],
                'expand' => ['profile'],
            ]
        );
        $result = $provider->toArray();
        $this->assertArrayNotHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('profile', $result);
        $this->assertNotEmpty($result['profile']);
        $this->assertSame(count($result), 2);
        $this->assertEquals(['name', 'profile'], $provider->getKeys());
    }


    public function testQuery()
    {
        $provider = new ActiveDataProvider(
            [
                'query' => (new Query())->setConnection($this->getConnection(false))->from('customer'),
                'only' => ['id', 'name'],
                'pagination' => ['limit' => 2, 'sort' => SORT_DESC, 'page' => 0]
            ]
        );

        // get
        $this->assertSame(2, count($provider->get()));
        $this->assertNotEmpty($provider->getPagination()->toArray());
        $this->assertSame(3, $provider->getTotalCount());
        $this->assertSame(2, count($provider->getKeys()));

        // to Array
        $result = $provider->toArray()[0];
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertSame(2, count($result));
        $this->assertSame(2, count($provider->getKeys()));
    }

    public function testArray()
    {
        $provider = new ActiveDataProvider(
            [
                'array' => (new Query())->from('customer')->all($this->getConnection(false)),
                'only' => ['id', 'name'],
                'pagination' => ['limit' => 2, 'sort' => SORT_DESC]
            ]
        );

        // get
        $this->assertSame(2, count($provider->get()));
        $this->assertNotEmpty($provider->getPagination()->toArray());
        $this->assertSame(3, $provider->getTotalCount());
        $this->assertSame(2, count($provider->getKeys()));

        // to Array
        $result = $provider->toArray()[0];
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertSame(2, count($result));
        $this->assertSame(2, count($provider->getKeys()));

        $provider = new ActiveDataProvider(
            [
                'query' => (new Query())->from('customer')->one($this->getConnection(false)),
                'only' => ['id', 'name'],
            ]
        );

        $result = $provider->toArray();
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);
        $this->assertSame(2, count($result));
        $this->assertContains('id', $provider->getKeys());
        $this->assertContains('name', $provider->getKeys());
    }

    public function testGetLink()
    {

        $provider = new ActiveDataProvider(
            [
                'array' => (new Query())->from('customer')->all($this->getConnection(false)),
                'only' => ['id', 'name'],
                'pagination' => ['limit' => 2, 'sort' => SORT_DESC]
            ]
        );
        $expected = [
            'self' => '/',
            'first' => '/',
            'prev' => '/',
            'next' => '/?page=1',
            'last' => '/?page=1',
        ];
        $this->assertSame($expected, $provider->getPagination()->getLinks());
    }

    public function testSetPropertyThrowException()
    {
        $this->setExpectedException(BaseException::className());
        $provider = new ActiveDataProvider(
            [
                'array' => (new Query())->from('customer')->all($this->getConnection(false)),
                'only' => ['id', 'name'],
                'pagination' => ['limit' => 2, 'sort' => SORT_DESC]
            ]
        );
        $provider->getPagination()['pageLast'] = 5;
    }

    public function testUnsetPropertyThrowException()
    {
        $this->setExpectedException(DbException::className());
        $provider = new ActiveDataProvider(
            [
                'array' => (new Query())->from('customer')->all($this->getConnection(false)),
                'only' => ['id', 'name'],
                'pagination' => ['limit' => 2, 'sort' => SORT_DESC]
            ]
        );
        unset($provider->getPagination()['pageLast']);
    }
}