<?php
namespace Orbit\Controller\API\v1\Product\MassUpload;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Controller\API\v1\Product\ProductNewAPIController;
use Orbit\Controller\API\v1\Product\MassUpload\MassProductParameterHelper as ProductParams;
use Validator;
use Exception;
use App;
use Input;

/**
 * Controller class for Mass upload product endpoint based on involve.asia datafeed file
 */
class MassUploadAPIController extends ControllerAPI
{
    public function postUploadProduct()
    {
        try {
            $httpCode = 200;
            // Require authentication
            $this->checkAuth();

            // check user
            $user = $this->api->user;

            $marketplaceType = strtolower(OrbitInput::post('marketplace_type'));
            $offerId = OrbitInput::post('offer_id');
            $file = Input::file('file');

            $validator = Validator::make(
                array(
                    'marketplace_type'  => $marketplaceType,
                    'offer_id'          => $offerId,
                    'file'              => $file
                ),
                array(
                    'marketplace_type'  => 'required|in:tokopedia',
                    'offer_id'          => 'required',
                    'file'              => 'required|max:3000'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // loop thru the file rows
            $params = ProductParams::create($marketplaceType, $file, $offerId)
                ->getParams();

            // delete uploaded file from $_FILES
            unset($_FILES['file']);

            // loop thru the params to create the product
            foreach ($params as $param) {
                // transform param into $_POST variables
                $_POST['name'] = $param['name'];
                $_POST['short_description'] = $param['short_description'];
                $_POST['status'] = $param['status'];
                $_POST['country_id'] = $param['country_id'];
                $_POST['marketplaces'][] = $param['marketplaces'];
                $_POST['categories'] = $param['categories'];
                $_FILES['images'] = $param['images'];

                App::instance('orbit.product.user', $user);

                // call new product controller
                $createResponse = ProductNewAPIController::create('raw')
                    ->callingFrom('massupload')
                    ->postNewProduct();

                // reset $_POST variables
                unset($_POST['name']);
                unset($_POST['short_description']);
                unset($_POST['status']);
                unset($_POST['country_id']);
                unset($_POST['marketplaces']);
                unset($_FILES['images']);
                unset($_POST['categories']);

                // microsleep
                usleep(300);
            }

            // done
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
        return $this->render($httpCode);
    }
}