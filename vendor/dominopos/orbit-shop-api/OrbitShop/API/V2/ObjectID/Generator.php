<?php namespace OrbitShop\API\V2\ObjectID;


class Generator {

    private static $instance;

    /**
     * @var string
     */
    private $machineId;

    /**
     * @var int
     */
    private $counter;

    /**
     *
     * @return static
     */
    public static function getInstance()
    {
        if (!static::$instance)
        {
            static::$instance = new static;
        }

        return static::$instance;
    }

    public function nextId()
    {
        $this->counter = ($this->counter + 1) % 0xFFFFFF;

        $time      = pack('N', time()); // 32 BIT
        $machineId = pack('NX', $this->machineId); // 24 BIT
        $pid       = pack('v', $this->getPid()); // 16 BIT
        $counter   = pack('NX', $this->counter << 8); // 24 BIT - LEFT SIFTED AS ONE BYTE BACKUP ON PACK

        return "{$time}{$machineId}{$pid}{$counter}";
    }

    public function setMachineId($str)
    {
        $this->machineId = unpack('N', md5($str, true))[1];
    }

    protected function __construct()
    {
        $this->counter   = mt_rand(0, 0xFFFFFF);
        $this->machineId = unpack('N', md5(gethostname(), true))[1];
    }

    protected function getPid()
    {
        return getmypid() % 0xFFFF;
    }
}
