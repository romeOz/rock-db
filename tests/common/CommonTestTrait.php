<?php

namespace rockunit\common;


use League\Flysystem\Adapter\Local;
use rock\cache\CacheFile;
use rock\file\FileManager;
use rock\helpers\FileHelper;

trait CommonTestTrait
{
    protected static function clearRuntime()
    {
        $runtime = ROCKUNIT_RUNTIME;
        FileHelper::deleteDirectory($runtime);
    }

    protected static function sort($value)
    {
        ksort($value);
        return $value;
    }


    /**
     * @param array $config
     * @return \rock\cache\CacheInterface
     */
    protected static function getCache(array $config = [])
    {
        if (empty($config)) {
            $config = [
                'adapter' => new FileManager([
                    'adapter' => new Local(ROCKUNIT_RUNTIME),
                ])
            ];
        }
        return new CacheFile($config);
    }
} 