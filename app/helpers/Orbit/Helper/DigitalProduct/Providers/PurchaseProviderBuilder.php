<?php namespace Orbit\Helper\DigitalProduct\Providers;

use Exception;
use Orbit\Helper\DigitalProduct\Providers\Ayopay\Provider as AyoPayProvider;
use Orbit\Helper\DigitalProduct\Providers\UPoint\DTUProvider as UPointDTUProvider;
use Orbit\Helper\DigitalProduct\Providers\UPoint\VoucherProvider as UPointVoucherProvider;

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
                return new AyoPayProvider($config);
                break;

            case 'upoint-dtu':
                return new UPointDTUProvider($config);
                break;

            case 'upoint-voucher':
                return new UPointVoucherProvider($config);
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
