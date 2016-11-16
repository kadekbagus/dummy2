<?php namespace Orbit\Controller\API\v1\Pub\Advert;
/**
 * Helpers for specific Advert Namespace
 *
 */
use OrbitShop\API\v1\OrbitShopAPI;
use Validator;
use DB;
use Carbon\Carbon;
use App;
use Lang;
use Mall;

class AdvertHelper
{
    protected $valid_mall = NULL;

    protected $session = NULL;

    public function __construct($session = NULL)
    {
        $this->session = $session;
    }

    /**
     * Static method to instantiate the class.
     */
    public static function create($session = NULL)
    {
        return new static($session);
    }

    /**
     * Custom validator used in Orbit\Controller\API\v1\Pub\Advert namespace
     *
     */
    public function advertCustomValidator() {
        // Check mall is exists
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Mall::where('status', 'active')
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

            $this->valid_mall = $mall;
            return TRUE;
        });
    }

    public function getValidMall()
    {
        return $this->valid_mall;
    }
}
