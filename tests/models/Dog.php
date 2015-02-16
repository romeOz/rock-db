<?php

namespace rockunit\models;

/**
 * Model Dog
 */
class Dog extends Animal
{

    /**
     *
     * @param self $record
     * @param array $row
     */
    public static function populateRecord($record, $row, $connection = null)
    {
        parent::populateRecord($record, $row);

        $record->does = 'bark';
    }
}