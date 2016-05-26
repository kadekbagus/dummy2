<?php namespace Orbit\Controller\API\v1\Customer;

/**
 * @author Ahmad <ahmad@dominopos.com>
 * @desc Controller for Tenant specific requests for Mobile CI Angular
 */
use Orbit\Controller\API\v1\Customer\BaseAPIController;
use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use DominoPOS\OrbitSession\Session;
use DominoPOS\OrbitSession\SessionConfig;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \Carbon\Carbon as Carbon;
use \Validator;
use Tenant;
use Mall;
use App;

class TenantCIAPIController extends BaseAPIController
{
    protected $validRoles = ['super admin', 'consumer', 'guest'];
    protected $mall_id = NULL;

    public function getTenantList ()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();

        try{
            $this->checkAuth();
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->validRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $this->mall_id = OrbitInput::get('mall_id', NULL);

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'mall_id' => $this->mall_id,
                ),
                array(
                    'mall_id' => 'required|orbit.empty.mall',
                )
            );
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $tenants = Tenant::
            with(
            [
                'categories' => function($q) {
                    $q->select('category_name');
                    $q->where('categories.status', 'active');
                    $q->orderBy('category_name', 'asc');
                }
            ])
            ->select(
                'merchants.merchant_id',
                'name',
                'floor',
                'unit',
                'media.path as logo',
                'merchant_social_media.social_media_uri as facebook_like_url',
                DB::raw('CASE WHEN news_merch.news_counter > 0 THEN "true" ELSE "false" END as news_flag'),
                DB::raw('CASE WHEN promo_merch.promotion_counter > 0 THEN "true" ELSE "false" END as promotion_flag'),
                DB::raw('CASE WHEN coupon_merch.coupon_counter > 0 THEN "true" ELSE "false" END as coupon_flag')
            )
            ->leftJoin('media', function ($join) {
                $join->on('media.object_id', '=', 'merchants.merchant_id')
                    ->where('media_name_long', '=', 'retailer_logo_orig');
            })
            ->leftJoin('category_merchant', function ($join) {
                $join->on('category_merchant.merchant_id', '=', 'merchants.merchant_id');
            })
            ->leftJoin('categories', function ($join) {
                $join->on('category_merchant.category_id', '=', 'categories.category_id');
            })
            ->leftJoin('merchant_social_media', function ($join) {
                $join->on('merchant_social_media.merchant_id', '=', 'merchants.merchant_id');
            })
            ->leftJoin('social_media', function ($join) {
                $join->on('social_media.social_media_id', '=', 'merchant_social_media.social_media_id');
                $join->where('social_media_code', '=', 'facebook');
            })
            // news badge
            ->leftJoin(DB::raw("(
                    SELECT {$prefix}merchants.merchant_id, count({$prefix}news.news_id) as news_counter
                    from {$prefix}news
                    LEFT JOIN {$prefix}news_merchant on {$prefix}news_merchant.news_id = {$prefix}news.news_id
                    LEFT JOIN {$prefix}merchants on {$prefix}news_merchant.merchant_id = {$prefix}merchants.merchant_id
                    WHERE {$prefix}news_merchant.object_type = 'retailer'
                    AND {$prefix}news.object_type = 'news'
                    AND {$prefix}news.status = 'active'
                    AND {$prefix}merchants.parent_id = '{$this->mall_id}'
                    GROUP BY {$prefix}merchants.merchant_id
            ) as news_merch"), DB::raw('news_merch.merchant_id'), '=', 'merchants.merchant_id')
            // promotion badge
            ->leftJoin(DB::raw("(
                    SELECT {$prefix}merchants.merchant_id, count({$prefix}news.news_id) as promotion_counter
                    from {$prefix}news
                    LEFT JOIN {$prefix}news_merchant on {$prefix}news_merchant.news_id = {$prefix}news.news_id
                    LEFT JOIN {$prefix}merchants on {$prefix}news_merchant.merchant_id = {$prefix}merchants.merchant_id
                    WHERE {$prefix}news_merchant.object_type = 'retailer'
                    AND {$prefix}news.object_type = 'promotion'
                    AND {$prefix}news.status = 'active'
                    AND {$prefix}merchants.parent_id = '{$this->mall_id}'
                    GROUP BY {$prefix}merchants.merchant_id
            ) as promo_merch"), DB::raw('promo_merch.merchant_id'), '=', 'merchants.merchant_id')
            // coupon badge
            ->leftJoin(DB::raw("(
                    SELECT {$prefix}merchants.merchant_id, count({$prefix}promotions.promotion_id) as coupon_counter
                    from {$prefix}promotions
                    LEFT JOIN {$prefix}promotion_retailer on {$prefix}promotion_retailer.promotion_id = {$prefix}promotions.promotion_id
                    LEFT JOIN {$prefix}merchants on {$prefix}promotion_retailer.retailer_id = {$prefix}merchants.merchant_id
                    WHERE {$prefix}promotion_retailer.object_type = 'tenant'
                    AND {$prefix}promotions.is_coupon = 'Y'
                    AND {$prefix}promotions.status = 'active'
                    AND {$prefix}merchants.parent_id = '{$this->mall_id}'
                    GROUP BY {$prefix}merchants.merchant_id
            ) as coupon_merch"), DB::raw('coupon_merch.merchant_id'), '=', 'merchants.merchant_id')
            ->active('merchants')
            ->where('parent_id', $this->mall_id);
            // ->groupBy('merchants.merchant_id');

            $_tenants = clone($tenants);

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.retailer.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.retailer.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $tenants->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $tenants)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $tenants->skip($skip);

            $tenants = $tenants->get();

            $data = new \stdclass();
            $data->records = $tenants;
            $data->returned_records = count($tenants);
            $data->total_records = RecordCounter::create($_tenants)->count();

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Success';
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
            $this->response->data = [$e->getFile(), $e->getLine(), $e->getMessage()];
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    public function getTenantItem ($id)
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();

        try{
            $tenant = Tenant::with('categories')
                ->where('merchant_id', $id)
                ->first();

            $this->response->data = $this->itemTransformer($tenant);
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Fetch OK';
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
            $this->response->data = [$e->getFile(), $e->getLine(), $e->getMessage];
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Check the existance of merchant id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.empty.mall', $mall);

            return TRUE;
        });
    }
}
