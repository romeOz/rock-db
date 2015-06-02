<?php

namespace rockunit\models;


/**
 * @property integer $id
 * @property string $title
 * @property string $content
 * @property integer $version
 */
class Document extends ActiveRecord
{
    public function optimisticLock()
    {
        return 'version';
    }
}