<?php
namespace Orbit;


use OrbitShop\API\V2\ObjectID;

class EncodedUUID {

    private static $models = [];

    public static function registerUseInModel($className)
    {
        if (!in_array($className, static::$models))
        {
            static::$models[] = $className;
        }
    }

    public static function fromTime($time)
    {
        return ObjectId::fromTime($time);
    }

    public static function make()
    {
        return ObjectID::make();
    }

    public static function makeMany($t)
    {
        $result = [];
        for($i=0;$i<$t;$i++)
        {
            $result[] = ObjectID::make();
        }

        return $result;
    }

    public static function getModelsUsing()
    {
        return static::$models;
    }
}
