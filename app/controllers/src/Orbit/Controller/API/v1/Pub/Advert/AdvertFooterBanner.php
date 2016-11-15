<?php namespace Orbit\Controller\API\v1\Pub\Advert;

use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Advert;
use AdvertLinkType;
use AdvertLocation;
use AdvertPlacement;

class AdvertFooterBanner
{

    public function __construct()
    {
    }

    /**
     * Static method to instantiate the class.
     */
    public static function create()
    {
        return new static();
    }

    public function getAdvertFooterBanner()
    {
        try {
            $mall_id = OrbitInput::get('mall_id', null);

            $topBanner = Advert::excludeDeleted()
                            ->get();

        } catch (Exception $e) {
            $this->response->message = $e->getMessage();
        }

        return $topBanner;
    }
}