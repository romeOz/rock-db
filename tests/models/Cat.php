<?php

namespace rockunit\models;
use rock\db\common\ConnectionInterface;

/**
 * Model Cat
 */
class Cat extends Animal
{
    /**
     * @inheritdoc
     * @param self $record
     * @param array $row
     */
    public static function populateRecord($record, $row, ConnectionInterface $connection = null)
    {
        parent::populateRecord($record, $row);

        $record->does = 'meow';
    }
}
