<?php

namespace rockunit\models;

/**
 * DefaultPk
 *
 * @property integer $id
 */
class DefaultPk extends ActiveRecord
{
    public static function tableName()
    {
        return 'default_pk';
    }
}