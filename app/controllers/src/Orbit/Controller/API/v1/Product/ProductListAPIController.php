<?php namespace Orbit\Controller\API\v1\Product;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\PaginationNumber;

use Product;
use Validator;
use Lang;
use DB;
use stdclass;
use Config;

class ProductListAPIController extends ControllerAPI
{
    protected $allowedRoles = ['product manager', 'article publisher', 'article writer'];

    /**
     * GET Search / list Product
     *
     * @author Ahmad <ahmad@dominopos.com>
     */
    public function getSearchProduct()
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
                    'sortby' => 'in:name,status,created_at,updated_at',
                    'status' => 'in:active,inactive',
                ),
                array(
                    'sortby.in' => 'The sort by argument you specified is not valid, the valid values are: name, status',
                    'status.in' => 'The sort by argument you specified is not valid, the valid values are: active, inactive',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $product = Product::select(DB::raw("
                                    {$prefix}products.product_id,
                                    {$prefix}products.name,
                                    {$prefix}products.status,
                                    {$prefix}products.created_at,
                                    {$prefix}products.updated_at,
                                    (SELECT SUBSTRING_INDEX(GROUP_CONCAT({$prefix}base_merchants.name), ',', 2)
                                        FROM {$prefix}product_link_to_object
                                        INNER JOIN {$prefix}base_merchants ON {$prefix}base_merchants.base_merchant_id = {$prefix}product_link_to_object.object_id
                                        WHERE {$prefix}product_link_to_object.product_id = {$prefix}products.product_id
                                        AND {$prefix}product_link_to_object.object_type = 'brand') as link_to_brand"
                                ));

            OrbitInput::get('product_id', function($product_id) use ($product)
            {
                $product->where('product_id', $product_id);
            });

            OrbitInput::get('name_like', function($name) use ($product)
            {
                $product->where('products.name', 'like', "%$name%");
            });

            OrbitInput::get('country', function($country) use ($product)
            {
                $product->leftJoin('countries', 'countries.country_id', '=', 'products.country_id')
                    ->where('countries.name', $country);
            });

            OrbitInput::get('status', function($status) use ($product)
            {
                $product->where('status', $status);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_product = clone $product;

            $take = PaginationNumber::parseTakeFromGet('merchant');
            $product->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $product->skip($skip);

            // Default sort by
            $sortBy = 'name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'name'       => 'products.name',
                    'status'     => 'products.status',
                    'created_at' => 'products.created_at',
                    'updated_at' => 'products.updated_at',
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
            $product->orderBy($sortBy, $sortMode);

            $totalItems = RecordCounter::create($_product)->count();
            $listOfItems = $product->get();

            $data = new stdclass();
            $data->total_records = $totalItems;
            $data->returned_records = count($listOfItems);
            $data->records = $listOfItems;

            if ($totalItems === 0) {
                $data->records = NULL;
                $this->response->message = "There is no product that matched your search criteria";
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