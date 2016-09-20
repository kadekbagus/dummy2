<?php namespace Orbit\Helper\Security;
/**
 * Simple Encryption and Decryption class.
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @credit http://php.net/manual/en/function.mcrypt-encrypt.php#78531
 */
use \Exception;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;

class Encrypter
{
    /**
     * The key used to encrypt the text.
     *
     * @var string
     */
    protected $securekey, $iv;

    /**
     * Driver to use for encryption and decryption. Valid value:
     * 'mcrypt' or 'defuse-php'
     *
     * @var string
     */
    protected $driver = 'defuse-php';

    /**
     * Constructor
     */
    public function __construct($key, $driver='defuse-php')
    {
        $this->securekey = $key;
        $this->iv = mcrypt_create_iv(16, MCRYPT_DEV_URANDOM);
        $this->driver = $driver;
    }

    /**
     * Set the driver
     *
     * @param string driver
     * @return Encrypter
     */
    public function setDriver($driver)
    {
        $allowedDrivers = ['mcrypt', 'defuse-php'];
        if (! in_array($driver, $allowedDrivers)) {
            throw new Exception('Encrypter Error: Unknown driver ' . $driver);
        }

        $this->driver = $driver;

        return $this;
    }

    /**
     * Get the driver
     *
     * @return Encrypter
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * Encrypt the payload and store the result in base64 string.
     *
     * @param string $input
     * @return string
     */
    public function encrypt($input)
    {
        switch ($this->driver) {
            case 'mcrypt':
                return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $this->securekey, $input, MCRYPT_MODE_ECB, $this->iv));
                break;

            case 'defuse-php':
            default:
                $key = Key::loadFromAsciiSafeString($this->securekey);
                return Crypto::encrypt($input, $key);
                break;
        }
    }

    /**
     * Decrypt the payload from base64 string.
     *
     * @param string $input
     * @return string
     */
    public function decrypt($input)
    {
        switch ($this->driver) {
            case 'mcrypt':
                return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $this->securekey, base64_decode($input), MCRYPT_MODE_ECB, $this->iv));
                break;

            case 'defuse-php':
            default:
                $key = Key::loadFromAsciiSafeString($this->securekey);
                return Crypto::decrypt($input, $key);
                break;
        }
    }
}