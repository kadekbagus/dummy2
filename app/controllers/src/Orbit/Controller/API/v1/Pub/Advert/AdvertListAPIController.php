<?php namespace Orbit\Controller\API\v1\Pub\Advert;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use stdClass;
use Orbit\Controller\API\v1\Pub\Advert\AdvertTopBanner;
use Orbit\Controller\API\v1\Pub\Advert\AdvertFooterBanner;

class AdvertListAPIController extends ControllerAPI
{
    public function getAdvertList()
    {
        try {
            $advert = new stdClass();
            $advert_type = OrbitInput::get('advert_type', 'top');

            if ($advert_type === 'top') {
               $topBanner = AdvertTopBanner::create();
               $advert = $topBanner->getAdvertTopBanner();
            }
            if ($advert_type === 'footer') {
               $footerBanner = AdvertFooterBanner::create();
               $advert = $topBanner->getAdvertFooterBanner();
            }

            $this->response->data = $advert;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = $e->getLine();
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getFile();
        }

        return $this->render();
    }
}