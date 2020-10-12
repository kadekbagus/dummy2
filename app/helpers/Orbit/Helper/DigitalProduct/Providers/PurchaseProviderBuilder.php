<?php namespace Orbit\Helper\DigitalProduct\Providers;

use Exception;

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

    public static function create($providerId)
    {
        return new static($providerId);
    }

    public function build($config = [])
    {
        switch ($this->providerId) {
            case 'ayopay':
                return new AyoPay\Provider($config);
                break;

            case 'upoint-dtu':
                return new UPoint\DTUProvider($config);
                break;

            case 'upoint-voucher':
                return new UPoint\VoucherProvider($config);
                break;

            case 'woodoos':
                return new Woodoos\Provider($config);
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
