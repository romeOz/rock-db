<?php

namespace rockunit\validate;


use rock\db\validate\rules\Unique;
use rockunit\DatabaseTestCase;
use rockunit\models\ActiveRecord;
use rockunit\models\Order;
use rockunit\models\OrderItem;
use rockunit\models\validate\FakedValidationModel;
use rockunit\models\validate\ValidatorTestMainModel;
use rockunit\models\validate\ValidatorTestRefModel;
use rockunit\models\validate\ValidatorTestRefRulesModel;

class PostgreSQLUniqueTest extends DatabaseTestCase
{
    protected $driverName = 'pgsql';

    public function setUp()
    {
        parent::setUp();
        ActiveRecord::$connection = $this->getConnection();
    }

    public function testValidateAttributeDefault()
    {
        $m = ValidatorTestMainModel::find()->one();
        $val = new Unique();
        $val->model = $m;
        $val->attribute = 'id';
        $this->assertTrue($val->validate());

        $m = ValidatorTestRefModel::findOne(1);
        $val = new Unique();
        $val->model = $m;
        $val->attribute = 'ref';
        $this->assertFalse($val->validate());

        // array error
        $m = FakedValidationModel::createWithAttributes(['attr_arr' => ['a', 'b']]);
        $val = new Unique();
        $val->model = $m;
        $val->attribute = 'attr_arr';
        $this->assertFalse($val->validate());
    }

    public function testModelRulesFail()
    {
        $rules = [
            [
                'ref', 'unique',
            ],
        ];
        $m = new ValidatorTestRefRulesModel();
        $m->rules = $rules;
        $m->ref = 5;
        $this->assertFalse($m->save());
        $this->assertNotEmpty($m->getErrors('ref'));
        $this->assertTrue($m->save(false));

        // target attributes
        $rules = [
            [
                'ref', 'unique' => ['id'],
            ],
        ];
        $m = new ValidatorTestRefRulesModel();
        $m->rules = $rules;
        $m->ref = 6;
        $this->assertFalse($m->save());
        $this->assertNotEmpty($m->getErrors('ref'));
        $this->assertTrue($m->save(false));

        $rules = [
            [
                'id', 'unique' => ['ref', ValidatorTestRefRulesModel::className()],
            ],
        ];
        $m = new ValidatorTestMainModel();
        $m->rules = $rules;
        $m->id = 5;
        $this->assertFalse($m->save());
        $this->assertTrue($m->save(false));
    }

    public function testModelRulesSuccess()
    {
        $rules = [
            [
                'ref', 'unique',
            ],
        ];
        $m = new ValidatorTestRefRulesModel();
        $m->rules = $rules;
        $m->ref = 12121;
        $this->assertTrue($m->save());
        $this->assertEmpty($m->getErrors());

        // target attributes
        $rules = [
            [
                'ref', 'unique' => ['id'],
            ],
        ];
        $m = new ValidatorTestRefRulesModel();
        $m->rules = $rules;
        $m->ref = 8;
        $this->assertTrue($m->save());
        $this->assertEmpty($m->getErrors());

        $rules = [
            [
                'id', 'unique' => ['ref', ValidatorTestRefRulesModel::className()],
            ],
        ];
        $m = new ValidatorTestMainModel();
        $m->rules = $rules;
        $m->id = 9;
        $this->assertTrue($m->save());
    }

    public function testValidateAttributeOfNonARModel()
    {
        $m = FakedValidationModel::createWithAttributes(['attr_1' => 5, 'attr_2' => 1313]);

        $val = new Unique();
        $val->model = $m;
        $val->targetAttribute = 'ref';
        $val->targetClass  = ValidatorTestRefModel::className();
        $val->attribute = 'attr_1';
        $this->assertFalse($val->validate());
        $val->attribute = 'attr_2';
        $this->assertTrue($val->validate());
    }

    public function testValidateNonDatabaseAttribute()
    {
        $m = ValidatorTestMainModel::findOne(1);
        $val = new Unique();
        $val->model = $m;
        $val->targetAttribute = 'ref';
        $val->targetClass  = ValidatorTestRefModel::className();
        $val->attribute = 'testMainVal';
        $this->assertTrue($val->validate('testMainVal'));

        $m = ValidatorTestMainModel::findOne(1);
        $m->testMainVal = 4;
        $val = new Unique();
        $val->model = $m;
        $val->targetAttribute = 'ref';
        $val->targetClass  = ValidatorTestRefModel::className();
        $val->attribute = 'testMainVal';
        $this->assertFalse($val->validate('testMainVal'));

    }

    public function testValidateAttributeAttributeNotInTableException()
    {
        $this->setExpectedException(\rock\db\common\DbException::className());
        $m = new ValidatorTestMainModel();
        $val = new Unique();
        $val->model = $m;
        $val->attribute = 'testMainVal';
        $val->validate();
    }

    public function testValidateCompositeKeys()
    {
        // validate old record
        $m = OrderItem::findOne(['order_id' => 1, 'item_id' => 2]);
        $val = new Unique();
        $val->model = $m;
        $val->targetAttribute = ['order_id', 'item_id'];
        $val->targetClass = OrderItem::className();
        $val->attribute = 'order_id';
        $this->assertTrue($val->validate());

        $m->item_id = 1;
        $val = new Unique();
        $val->model = $m;
        $val->targetAttribute = ['order_id', 'item_id'];
        $val->targetClass = OrderItem::className();
        $val->attribute = 'order_id';
        $this->assertFalse($val->validate());

        // validate new record
        $m = new OrderItem(['order_id' => 1, 'item_id' => 2]);
        $val = new Unique();
        $val->model = $m;
        $val->targetAttribute = ['order_id', 'item_id'];
        $val->targetClass = OrderItem::className();
        $val->attribute = 'order_id';
        $this->assertFalse($val->validate());

        $m = new OrderItem(['order_id' => 10, 'item_id' => 2]);
        $val = new Unique();
        $val->model = $m;
        $val->targetAttribute = ['order_id', 'item_id'];
        $val->targetClass = OrderItem::className();
        $val->attribute = 'order_id';
        $this->assertTrue($val->validate());

        // validate old record
        $m = Order::findOne(1);
        $val = new Unique();
        $val->model = $m;
        $val->targetAttribute = ['id' => 'order_id'];
        $val->targetClass = OrderItem::className();
        $val->attribute = 'id';
        $this->assertFalse($val->validate('id'));

        $m = Order::findOne(1);
        $m->id = 2;
        $val = new Unique();
        $val->model = $m;
        $val->targetAttribute = ['id' => 'order_id'];
        $val->targetClass = OrderItem::className();
        $val->attribute = 'id';
        $this->assertFalse($val->validate());

        $m = Order::findOne(1);
        $m->id = 10;
        $val = new Unique();
        $val->model = $m;
        $val->targetAttribute = ['id' => 'order_id'];
        $val->targetClass = OrderItem::className();
        $val->attribute = 'id';
        $this->assertTrue($val->validate());

        $m = new Order(['id' => 1]);
        $val = new Unique();
        $val->model = $m;
        $val->targetAttribute = ['id' => 'order_id'];
        $val->targetClass = OrderItem::className();
        $val->attribute = 'id';
        $this->assertFalse($val->validate());

        $m = new Order(['id' => 10]);
        $val = new Unique();
        $val->model = $m;
        $val->targetAttribute = ['id' => 'order_id'];
        $val->targetClass = OrderItem::className();
        $val->attribute = 'id';
        $this->assertTrue($val->validate());
    }
}
