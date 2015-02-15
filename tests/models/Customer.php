<?php
namespace rockunit\models;

use rock\db\ActiveQuery;
use rockunit\ActiveRecordTest;

/**
 * Class Customer
 *
 * @property integer $id
 * @property string $name
 * @property string $email
 * @property string $address
 * @property integer $status
 *
 * @method CustomerQuery findBySql($sql, $params = []) static
 */
class Customer extends ActiveRecord
{
    const STATUS_ACTIVE = 1;
    const STATUS_INACTIVE = 2;

    public $status2;

    public static function tableName()
    {
        return 'customer';
    }

    public function getProfile()
    {
        return $this->hasOne(Profile::className(), ['id' => 'profile_id']);
    }

    public function getOrders()
    {
        return $this->hasMany(Order::className(), ['customer_id' => 'id'])->orderBy(Order::tableName() .'.id');
    }

    public function getExpensiveOrders()
    {
        return $this->hasMany(Order::className(), ['customer_id' => 'id'])->andWhere('total > 50')->orderBy('id');
    }

    public function getExpensiveOrdersWithNullFK()
    {
        return $this->hasMany(OrderWithNullFK::className(), ['customer_id' => 'id'])->andWhere('total > 50')->orderBy('id');
    }

    public function getOrdersWithNullFK()
    {
        return $this->hasMany(OrderWithNullFK::className(), ['customer_id' => 'id'])->orderBy('id');
    }

    public function getOrders2()
    {
        return $this->hasMany(Order::className(), ['customer_id' => 'id'])->inverseOf('customer2')->orderBy('id');
    }

    // deeply nested table relation
    public function getOrderItems()
    {
        /* @var $rel ActiveQuery */
        $rel = $this->hasMany(Item::className(), ['id' => 'item_id']);

        return $rel->viaTable('order_item', ['order_id' => 'id'], function (ActiveQuery $q) {
            $q->viaTable('order', ['customer_id' => 'id']);
        })->orderBy('id');
    }

    public function afterSave($insert, $changedAttributes)
    {
        ActiveRecordTest::$afterSaveInsert = $insert;
        ActiveRecordTest::$afterSaveNewRecord = $this->isNewRecord;
        parent::afterSave($insert, $changedAttributes);
    }

    /**
     * @inheritdoc
     * @return CustomerQuery
     */
    public static function find()
    {
        return new CustomerQuery(get_called_class());
    }
}
