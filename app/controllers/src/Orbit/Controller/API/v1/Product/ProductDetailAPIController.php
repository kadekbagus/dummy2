<?php namespace Orbit\Controller\API\v1\Product;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;

use Product;
use Lang;
use DB;
use Validator;
use Config;

class ProductDetailAPIController extends ControllerAPI
{
    protected $allowedRoles = ['product manager'];

    /**
     * GET Detail Product
     *
     * @author Ahmad <ahmad@dominopos.com>
     */
    public function getDetailProduct()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->allowedRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $productId = OrbitInput::get('product_id');

            $prefix = DB::getTablePrefix();

            $validator = Validator::make(
                array(
                    'product_id' => $productId,
                ),
                array(
                    'product_id' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $product = Product::with([
                    'media',
                    'merchants' => function ($q) use ($prefix) {
                        $q->select(DB::raw("{$prefix}base_merchants.name, base_merchant_id"), 'countries.name as country_name')
                            ->leftJoin('countries', 'base_merchants.country_id', '=', 'countries.country_id');
                    },
                    'categories',
                    'marketplaces' => function ($q) {
                        $q->addSelect('marketplaces.status')
                          ->where('marketplaces.status', '=', 'active');
                    },
                    'country',
                    'videos',
                    'product_photos' => function($q) {
                        $q->select('media_id', 'metadata', 'object_id', 'path', 'cdn_url')
                            ->where('media_name_long', 'product_photos_orig');
                    },
                ])
                ->where('product_id', $productId)
                ->firstOrFail();

            $this->response->data = $product;
        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
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
        } catch (Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);

        return $output;
    }
}