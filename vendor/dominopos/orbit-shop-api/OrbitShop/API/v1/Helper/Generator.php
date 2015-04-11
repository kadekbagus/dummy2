<?php namespace OrbitShop\API\v1\Helper;
/**
 * Loyalty API Helper to generate some data that used in API.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\OrbitShopAPI as API;

class Generator
{
    /**
     * Generate the Signature based on request data.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @param string $algorithm     Algorithm used to hash the signatrue, i.e.
     *                              'sha256', 'md5'
     * @param string $secretKey     The Secret Key to encrypt the data.
     * @return string
     */
    public static function genSignature($secretKey, $algorithm='sha256')
    {
        $signedData = API::createSignedData();

        $signature = hash_hmac($algorithm, $signedData, $secretKey);

        return $signature;
    }
}
