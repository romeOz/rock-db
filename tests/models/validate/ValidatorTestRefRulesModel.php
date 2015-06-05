<?php

namespace rockunit\models\validate;

use rockunit\models\ActiveRecord;

class ValidatorTestRefRulesModel extends ActiveRecord
{
    public $test_val = 2;
    public $test_val_fail = 99;

    public $rules = [];
    public function rules()
    {
        if (!empty($this->rules)) {
            return $this->rules;
        }
        return [
            [
                'ref', 'unique',
            ],
        ];
    }

    public static function tableName()
    {
        return 'validator_ref';
    }

    public function getMain()
    {
        return $this->hasOne(ValidatorTestMainModel::className(), ['id' => 'ref']);
    }
}
