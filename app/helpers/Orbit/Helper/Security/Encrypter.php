<?php namespace Orbit\Helper\Security;
/**
 * Simple Encryption and Decryption class.
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @credit http://php.net/manual/en/function.mcrypt-encrypt.php#78531
 */
class Encrypter
{
    protected $securekey, $iv;

    /**
     * Constructor
     */
    public function __construct($key)
    {
        $this->securekey = $key;
        $this->iv = mcrypt_create_iv(16);
    }

    /**
     * Encrypt the payload and store the result in base64 string.
     *
     * @param string $input
     * @return string
     */
    public function encrypt($input)
    {
        return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $this->securekey, $input, MCRYPT_MODE_ECB, $this->iv));
    }

    /**
     * Decrypt the payload from base64 string.
     *
     * @param string $input
     * @return string
     */
    public function decrypt($input)
    {
        return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $this->securekey, base64_decode($input), MCRYPT_MODE_ECB, $this->iv));
    }
}
