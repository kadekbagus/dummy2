<?php namespace Orbit\Controller\API\v1\Merchant\Merchant;

use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\PaginationNumber;
use BaseMerchant;
use Country;
use Validator;
use Lang;
use DB;
use Config;
use stdclass;
use Orbit\Controller\API\v1\Merchant\Merchant\MerchantHelper;

class MerchantListAPIController extends ControllerAPI
{
    protected $merchantViewRoles = ['super admin', 'merchant database admin'];

    /**
     * GET Search Base Merchant
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getSearchMerchant()
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
            $validRoles = $this->merchantViewRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $merchantHelper = MerchantHelper::create();
            $merchantHelper->merchantCustomValidator();

            $sort_by = OrbitInput::get('sortby');

            $validator = Validator::make(
                array(
                    'sortby' => $sort_by,
                ),
                array(
                    'sortby' => 'in:merchant_name,location_number',
                ),
                array(
                    'sortby.in' => 'The sort by argument you specified is not valid, the valid values are: merchant_name, location_number',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $merchants = BaseMerchant::select(
                                        'base_merchants.base_merchant_id',
                                        'base_merchants.country_id',
                                        'countries.name as country_name',
                                        'base_merchants.name',
                                        'base_merchants.disable_ads',
                                        'base_merchants.disable_ymal',
                                        DB::raw("(CASE
                                            WHEN COUNT({$prefix}pre_exports.object_id) > 0 THEN 'in_progress'
                                            ELSE
                                                CASE WHEN ({$prefix}media.path IS NULL or {$prefix}media.path = '') or
                                                        ({$prefix}base_merchants.phone IS NULL or {$prefix}base_merchants.phone = '') or
                                                        ({$prefix}base_merchants.email IS NULL or {$prefix}base_merchants.email = '') or
                                                        ({$prefix}base_merchants.mobile_default_language IS NULL or {$prefix}base_merchants.mobile_default_language = '')
                                                    THEN 'not_available'
                                                ELSE 'available'
                                                END
                                            END) as export_status"),
                                        DB::raw("(SELECT count(base_store_id) FROM {$prefix}base_stores WHERE base_merchant_id = {$prefix}base_merchants.base_merchant_id) as location_count"),
                                        'base_merchants.status'
                                    )
                                    ->leftJoin('media', function ($q){
                                        $q->on('media.object_id', '=', 'base_merchants.base_merchant_id')
                                          ->on('media.media_name_id', '=', DB::raw("'base_merchant_logo_grab'"));
                                        $q->on('media.media_name_long', '=', DB::raw("'base_merchant_logo_grab_orig'"));
                                    })
                                    ->leftJoin('pre_exports', function ($q){
                                        $q->on('pre_exports.object_id', '=', 'base_merchants.base_merchant_id')
                                          ->on('pre_exports.object_type', '=', DB::raw("'merchant'"));
                                    })
                                    ->leftJoin('countries', 'base_merchants.country_id', '=', 'countries.country_id')
                                    ->excludeDeleted('base_merchants');

            OrbitInput::get('merchant_id', function($data) use ($merchants)
            {
                $merchants->whereIn('merchants.merchant_id', $data);
            });

            // Filter merchant by name
            OrbitInput::get('name', function($name) use ($merchants)
            {
                $merchants->whereIn('base_merchants.name', $name);
            });

            // Filter merchant by matching name pattern
            OrbitInput::get('name_like', function($name) use ($merchants)
            {
                $merchants->where('base_merchants.name', 'like', "%$name%");
            });

            // Filter by country
            OrbitInput::get('country', function($country) use ($merchants) {
                $merchants->where('base_merchants.country_id', $country);
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($merchants) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'partners') {
                        $merchants->with('partners');
                    }
                }
            });

            $_merchantActiveInactive = clone $merchants;

            $merchants->groupBy('base_merchants.base_merchant_id');

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_merchants = clone $merchants;

            $take = PaginationNumber::parseTakeFromGet('merchant');
            $merchants->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $merchants->skip($skip);

            // Default sort by
            $sortBy = 'base_merchants.name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'merchant_name' => 'base_merchants.name',
                    'location_number' => 'location_count',
                    'status' => 'base_merchants.status'
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
            $merchants->orderBy($sortBy, $sortMode);

            $totalMerchants = RecordCounter::create($_merchants)->count();
            $listOfMerchants = $merchants->get();

            // Get total active inactive stores
            $totalActiveStore = 0;
            $totalInactiveStore = 0;

            if ($totalMerchants > 0) {
                $totalActiveInactiveStore = $_merchantActiveInactive
                                                ->select(DB::raw("count({$prefix}base_merchants.base_merchant_id) as total "), 'base_merchants.status')
                                                ->groupBy('base_merchants.status')
                                                ->get();

                if (count($totalActiveInactiveStore) > 0) {
                    foreach ($totalActiveInactiveStore as $key => $value) {
                        if ($value->status == 'active') {
                            $totalActiveStore = $value->total;
                        } elseif ($value->status == 'inactive') {
                            $totalInactiveStore = $value->total;
                        }
                    }
                }
            }

            $data = new stdclass();
            $data->total_records = $totalMerchants;
            $data->returned_records = count($listOfMerchants);
            $data->total_active_stores = $totalActiveStore;
            $data->total_inactive_stores = $totalInactiveStore;
            $data->records = $listOfMerchants;

            if ($totalMerchants === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.merchant');
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
