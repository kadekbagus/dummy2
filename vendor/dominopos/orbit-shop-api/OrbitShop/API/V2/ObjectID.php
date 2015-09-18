<?php namespace OrbitShop\API\V2;


use JsonSerializable;
use OrbitShop\API\V2\ObjectID\Generator;
use OrbitShop\API\V2\ObjectID\InvalidException;

class ObjectID implements JsonSerializable {

    const CHARS_D64 = "-0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ_abcdefghijklmnopqrstuvwxyz|";
    const CHARS_B64 = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=";

    /**
     * @var string
     */
    private $data;

    /**
     * @return static
     */
    public static function make()
    {
        return new static;
    }

    /**
     * @param string|int $time
     * @return static
     */
    public static function fromTime($time)
    {
        if (is_numeric($time))
        {
            $time = (int) $time;
        } else {
            $time = strtotime((string) $time);
        }

        return new static(bin2hex(pack('NNXvNX', $time, 0, 0, 0)));
    }

    /**
     * @param string $id
     * @throws InvalidException
     */
    public function __construct($id = NULL)
    {
        if ($id && static::isHex($id))
        {
            $this->data = hex2bin($id);

            return;
        }

        if ($id && static::isd64($id))
        {
            $this->data = $this->d64_decode_tr($id);

            return;
        }

        if ($id)
        {
            throw new InvalidException;
        }

        $this->data = $this->generate();
    }

    /**
     * @return string
     */
    public function hex()
    {
        return bin2hex($this->data);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->d64();
    }

    public function generationTime()
    {
        return unpack('N', $this->data)[1];
    }

    public static function isValid($str)
    {
        if (! is_string($str) ) return false;

        return $this->isHex($str) || $this->isd64($str);
    }

    /**
     * @param $str
     * @return bool
     */
    private static function isHex($str)
    {
        return preg_match('/\\A[0-9a-f]{24}\\z/i', $str) ? true : false;
    }


    /**
     * @return string
     */
    private function generate()
    {
        return Generator::getInstance()->nextId();
    }

    /**
     * @param $str
     * @return bool
     */
    private static function isd64($str)
    {
        return preg_match('!\\A[0-9A-Za-z_\\|-]{16}\\z!i', $str) ? true : false;
    }

    /**
     * @return string
     */
    public function d64()
    {
        return $this->d64_encode_tr($this->data);
    }

    /**
     * @param string $enc
     * @return string
     */
    private function d64_encode_tr($enc)
    {
        return rtrim(strtr(base64_encode($enc), static::CHARS_B64, static::CHARS_D64), '|');
    }

    /**
     * @param string $enc
     * @return string
     */
    private function d64_decode_tr($enc)
    {
        return base64_decode(strtr($enc, static::CHARS_D64, static::CHARS_B64), true);
    }

    /**
     * {@inheritDoc}
     */
    function jsonSerialize()
    {
        return $this->__toString();
    }
}
