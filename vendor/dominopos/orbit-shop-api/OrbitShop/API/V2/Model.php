<?php namespace OrbitShop\API\V2;

use Illuminate\Database\Eloquent\Model as Eloquent;

class Model extends Eloquent
{
    public $incrementing = false;

    public function save(array $options = array())
    {
        if (! $this->exists )
        {
            $key = $this->getKey();

            if (ObjectID::isValid($key)) {
                $key = new ObjectID($key);
            } else {
                $key = ObjectID::make();
            }

            $this->setAttribute($this->getKeyName(), $key);
        }

        parent::save();
    }
}
