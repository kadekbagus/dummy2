<?php

namespace Orbit\Controller\API\v1\BrandProduct\Product;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Validator;

use Lang;
use Config;
use Event;
use BaseMerchant;
use BrandProduct;
use DB;
use Exception;
use App;

class ProductNewAPIController extends ControllerAPI
{

    /**
     * Create new product on brand product portal.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function postNewProduct()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');
            $userId = $user->bpp_user_id;
            $brandId = $user->base_merchant_id;

            $productName = OrbitInput::post('product_name');
            $productDescription = OrbitInput::post('product_description');
            $tnc = OrbitInput::post('tnc');
            $status = OrbitInput::post('status', 'inactive');
            $maxReservationTime = OrbitInput::post('max_reservation_time', 48);

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'product_name'        => $productName,
                    'status'              => $status,
                ),
                array(
                    'product_name'        => 'required',
                    'status'              => 'in:active,inactive',
                ),
                array(
                    'product_name.required' => 'Product Name field is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.newbrandproduct.postnewproduct.after.validation', array($this, $validator));

            $newBrandProduct = new BrandProduct;
            $newBrandProduct->brand_id = $brandId;
            $newBrandProduct->product_name = $productName;
            $newBrandProduct->product_description = $productDescription;
            $newBrandProduct->tnc = $tnc;
            $newBrandProduct->status = $status;
            $newBrandProduct->max_reservation_time = $maxReservationTime;
            $newBrandProduct->created_by = $userId;

            Event::fire('orbit.newbrandproduct.postnewproduct.before.save', array($this, $newBrandProduct));

            $newBrandProduct->save();

            Event::fire('orbit.newbrandproduct.postnewproduct.after.save', array($this, $newBrandProduct));

            $this->response->data = $newBrandProduct;

            // Commit the changes
            $this->commit();

            Event::fire('orbit.newbrandproduct.postnewproduct.after.commit', array($this, $newBrandProduct));
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();
        } catch (\Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

}
