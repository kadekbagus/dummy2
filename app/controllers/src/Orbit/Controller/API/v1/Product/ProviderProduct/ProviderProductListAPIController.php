<?php namespace Orbit\Controller\API\v1\Product\ProviderProduct;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\PaginationNumber;

use ProviderProduct;
use Validator;
use Lang;
use DB;
use stdclass;
use Config;

class ProviderProductListAPIController extends ControllerAPI
{
    protected $allowedRoles = ['product manager', 'article publisher', 'article writer'];

    /**
     * GET Search / list game
     *
     * @author Ahmad <ahmad@dominopos.com>
     */
    public function getSearchProviderProduct()
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

            $sortBy = OrbitInput::get('sortby');
            $status = OrbitInput::get('status');

            $validator = Validator::make(
                array(
                    'sortby' => $sortBy,
                    'status' => $status,
                ),
                array(
                    'sortby' => 'in:provider_product_name,provider_name,product_type,price,status,created_at,updated_at',
                    'status' => 'in:active,inactive',
                ),
                array(
                    'sortby.in' => 'The sort by argument you specified is not valid, the valid values are: provider_product_name,provider_name,product_type,price,status,created_at,updated_at',
                    'status.in' => 'The sort by argument you specified is not valid, the valid values are: active, inactive',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $provider_product = ProviderProduct::select(DB::raw("
                                    {$prefix}provider_products.provider_product_id,
                                    {$prefix}provider_products.provider_product_name,
                                    {$prefix}provider_products.provider_name,
                                    {$prefix}provider_products.product_type,
                                    {$prefix}provider_products.price,
                                    {$prefix}provider_products.created_at,
                                    {$prefix}provider_products.updated_at"
                                ));

            OrbitInput::get('provider_product_id', function($provider_product_id) use ($provider_product)
            {
                $provider_product->where('provider_product_id', $provider_product_id);
            });

            OrbitInput::get('game_name_like', function($name) use ($provider_product)
            {
                $provider_product->where('game_name', 'like', "%$name%");
            });

            OrbitInput::get('status', function($status) use ($provider_product)
            {
                $provider_product->where('status', $status);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_product = clone $provider_product;

            $take = PaginationNumber::parseTakeFromGet('merchant');
            $provider_product->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $provider_product->skip($skip);

            // Default sort by
            $sortBy = 'provider_product_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'provider_product_name'  => 'provider_products.provider_product_name',
                    'provider_name'          => 'provider_products.provider_name',
                    'product_type'           => 'provider_products.product_type',
                    'price'                  => 'provider_products.price',
                    'status'                 => 'provider_products.status',
                    'created_at'             => 'provider_products.created_at',
                    'updated_at'             => 'provider_products.updated_at',
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $provider_product->orderBy($sortBy, $sortMode);

            $totalItems = RecordCounter::create($_product)->count();
            $listOfItems = $provider_product->get();

            $data = new stdclass();
            $data->total_records = $totalItems;
            $data->returned_records = count($listOfItems);
            $data->records = $listOfItems;

            if ($totalItems === 0) {
                $data->records = NULL;
                $this->response->message = "There is no provider product that matched your search criteria";
            }

            $this->response->data = $data;
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