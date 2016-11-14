<?php namespace Orbit\Controller\API\v1\Pub\Advert;

use OrbitShop\API\v1\ControllerAPI;
use Config;
use stdClass;

class AdvertListAPIController extends ControllerAPI
{
    public function getAdvertList()
    {
        try {
            $staticFooterImage = Config::get('orbit.statics.adverts.footer.image_url', null);
            $staticFooterUrl = Config::get('orbit.statics.adverts.footer.link_url', null);
            $staticFooterTitle = Config::get('orbit.statics.adverts.footer.title', null);

            $data = new stdClass();
            $data->link_url = $staticFooterUrl;
            $data->image_url = $staticFooterImage;
            $data->title = $staticFooterTitle;

            $this->response->data = $data;
        } catch (Exception $e) {
            $this->response->code = $e->getCode();
            $this->response->status = $e->getLine();
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getFile();
        }

        return $this->render();
    }
}
