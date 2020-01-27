<?php namespace Orbit\Helper\DigitalProduct\Providers;

use Exception;
use Orbit\Helper\DigitalProduct\Providers\Ayopay\Provider as AyoPayProvider;

/**
 * Purchase Provider Builder that build correct purchase provider class based on the provider id.
 *
 * @author Budi <budi@gotomalls.com>
 */
class PurchaseProviderBuilder
{
    protected $providerId = '';

    function __construct($providerId = '')
    {
        $this->providerId = $providerId;
    }

    public function build($config = [])
    {
        switch ($this->providerId) {
            case 'ayopay':
                return new AyoPayProvider($config);
                break;

            // case 'unipin':
            //     return new UniPinProvider($config);
            //     break;

            default:
                throw new Exception('Unknown provider id!');
                break;
        }
    }
}
