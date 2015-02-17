<?php

namespace rockunit\models;

/**
 * Model Animal
 *
 * @property integer $id
 * @property string $type
 */
class Animal extends ActiveRecord
{

    public $does;
   // public $type;

    public static function tableName()
    {
        return 'animal';
    }

    public function init()
    {
        parent::init();
        $this->type = get_called_class();
    }

    public function getDoes()
    {
        return $this->does;
    }

    /**
     *
     * @param type $row
     * @return static
     */
    public static function instantiate($row)
    {
        $class = $row['type'];
        return new $class;
    }

}
