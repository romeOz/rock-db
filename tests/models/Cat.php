<?php

namespace rockunit\models;

/**
 * Model Cat
 */
class Cat extends Animal
{
    /**
     *
     * @param self $record
     * @param array $row
     */
    public static function populateRecord($record, $row, $connection = null)
    {
        parent::populateRecord($record, $row);

        $record->does = 'meow';
    }
}
