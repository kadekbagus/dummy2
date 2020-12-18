<?php
namespace Orbit\Controller\API\v1\Product\MassUpload;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Controller\API\v1\Product\ProductNewAPIController;
use Orbit\Controller\API\v1\Product\MassUpload\MassProductParameterHelper as ProductParams;
use Validator;
use Exception;

/**
 * Controller class for Mass upload product endpoint based on involve.asia datafeed file
 */
class MassUploadAPIController extends ControllerAPI
{
    public function postUploadProduct()
    {
        try {
            // check user
            $user = $this->api->user;

            $marketplaceType = OrbitInput::post('marketplace_type');
            $file = OrbitInput::files('file');

            $validator = Validator::make(
                array(
                    'marketplace_type'  => $marketplaceType,
                    'file'              => $file
                ),
                array(
                    'marketplaceType'   => 'required,in:tokopedia',
                    'file'              => 'required|mimes:csv,xml|max:3000'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // loop thru the file rows
            $params = ProductParams::create($marketplaceType, $file)
                ->getParams();

            // loop thru the params to create the product
            foreach ($params as $param) {
                // transform param into $_POST variables
                $_POST['name'] = $param['name'];
                $_POST['short_description'] = $param['short_description'];
                $_POST['status'] = $param['status'];
                $_POST['country_id'] = $param['country_id'];
                $_POST['marketplaces'][] = $param['marketplaces'];
                $_POST['images'][] = $param['images'];
                $_POST['categories'][] = $param['categories'];

                // call new product controller
                $createResponse = ProductNewAPIController::create('raw')
                    ->postNewProduct();

                // delete $_POST variables
                unset($_POST['name']);
                unset($_POST['short_description']);
                unset($_POST['status']);
                unset($_POST['country_id']);
                unset($_POST['marketplaces']);
                unset($_POST['images']);
                unset($_POST['categories']);

                // microsleep
                usleep(1000);
            }

            // done
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}