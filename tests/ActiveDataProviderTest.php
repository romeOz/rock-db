<?php

namespace rockunit;

use rock\base\BaseException;

use rock\data\DataProviderException;
use rock\db\ActiveQuery;
use rock\db\ActiveDataProvider;
use rock\data\ArrayDataProvider;
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
        unset($_GET['page']);
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        unset($_GET['page']);
    }


    /**
     * @dataProvider providerQuery
     * @param int $page
     * @param string $name
     */
    public function testQuery($page, $name)
    {
        $provider = new ActiveDataProvider(
            [
                'query' => (new Query())->setConnection($this->getConnection(false))->from('customer'),
                'pagination' => ['limit' => 1, 'sort' => SORT_DESC, 'page' => $page]
            ]
        );

        $this->assertSame(1, count($provider->getModels()));
        $this->assertEquals($name, $provider->getModels()[0]['name']);
        $this->assertNotEmpty($provider->getPagination()->toArray());
        $this->assertSame(3, $provider->getTotalCount());
        $this->assertSame(1, count($provider->getKeys()));
    }

    public function providerQuery()
    {
        return [
            [1, 'user3'],
            [2, 'user2']
        ];
    }

    public function testActiveQuery()
    {
        // as Array
        $provider = new ActiveDataProvider(
            [
                'query' => Customer::find()->asArray(),
                'pagination' => ['limit' => 2, 'sort' => SORT_DESC]
            ]
        );

        $this->assertSame(2, count($provider->getModels()));
        $this->assertNotEmpty($provider->getPagination()->toArray());
        $this->assertNotEmpty($provider->getPagination()['pageLast']);
        $this->assertNotEmpty($provider->getPagination()['pageLast']);
        $this->assertTrue(isset($provider->getPagination()['pageLast']));
        $this->assertSame(3, $provider->getTotalCount());
        $this->assertSame(2, count($provider->getKeys()));
        $result = $provider->getModels()[0];
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('name', $result);

        // as models
        $provider = new ActiveDataProvider(
            [
                'query' => Customer::find()->with('profile'),
                'pagination' => ['limit' => 2, 'sort' => SORT_DESC]
            ]
        );

        $this->assertSame(2, count($provider->getModels()));
        $this->assertNotEmpty($provider->getPagination()->toArray());
        $this->assertSame(3, $provider->getTotalCount());
        $this->assertSame(2, count($provider->getKeys()));
        $result = $provider->getModels()[0];
        $this->assertNotEmpty($result->id);
        $this->assertNotEmpty($result->name);
        $this->assertNotEmpty($result->profile);
    }

    public function testActiveRelation()
    {
        $customer = Customer::find()
            ->innerJoinWith([
                'orders' => function (ActiveQuery $query) {
                    return $query->orderBy(['{{order}}.[[created_at]]' => SORT_DESC]);
                },
            ]);

        $provider = new ActiveDataProvider([
            'query' => $customer,
        ]);
        $this->assertNotEmpty($provider->getModels());
    }

    /**
     * @dataProvider providerArray
     * @param int $page
     * @param int $count
     */
    public function testArray($page, $count)
    {
        $_GET['page'] = $page;
        $provider = new ArrayDataProvider(
            [
                'allModels' => (new Query())->from('customer')->all($this->getConnection(false)),
                'pagination' => ['limit' => 2, 'sort' => SORT_ASC]
            ]
        );

        $this->assertSame($count, count($provider->getModels()));
        $this->assertNotEmpty($provider->getPagination()->toArray());
        $this->assertSame($page, $provider->getPagination()->getPageCurrent());
        $this->assertSame(3, $provider->getTotalCount());
        $this->assertSame($count, count($provider->getKeys()));
    }

    public function providerArray()
    {
        return [
            [1, 2],
            [2, 1]
        ];
    }

    public function testGetLink()
    {
        $provider = new ActiveDataProvider(
            [
                'connection' => $this->getConnection(false),
                'query' => (new Query())->from('customer'),
                'pagination' => ['limit' => 2, 'sort' => SORT_DESC]
            ]
        );
        $provider->getModels();
        $expected = [
            'self' => '/?limit=2',
            'first' => '/?limit=2',
            'prev' => '/?limit=2',
            'next' => '/?page=1&limit=2',
            'last' => '/?page=1&limit=2',
        ];
        $this->assertSame($expected, $provider->getPagination()->getLinks());

        // as array
        $provider = new arrayDataProvider(
            [
                'allModels' => (new Query())->from('customer')->all($this->getConnection(false)),
                'pagination' => ['limit' => 2, 'sort' => SORT_DESC]
            ]
        );
        $provider->getModels();
        $this->assertSame($expected, $provider->getPagination()->getLinks());
    }

    public function testSetPropertyThrowException()
    {
        $this->setExpectedException(BaseException::className());
        $provider = new arrayDataProvider(
            [
                'allModels' => (new Query())->from('customer')->all($this->getConnection(false)),
                'pagination' => ['limit' => 2, 'sort' => SORT_DESC]
            ]
        );
        $provider->getModels();
        $provider->getPagination()['pageLast'] = 5;
    }

    public function testUnsetPropertyThrowException()
    {
        $this->setExpectedException(DataProviderException::className());
        $provider = new arrayDataProvider(
            [
                'allModels' => (new Query())->from('customer')->all($this->getConnection(false)),
                'pagination' => ['limit' => 2, 'sort' => SORT_DESC]
            ]
        );
        $provider->getModels();
        unset($provider->getPagination()['pageLast']);
    }
}