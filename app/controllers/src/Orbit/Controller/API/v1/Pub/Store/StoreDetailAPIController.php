<?php namespace Orbit\Controller\API\v1\Pub\Store;
/**
 * An API controller for get all detail store in all mall, group by name.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use Mall;
use BaseMerchant;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Language;
use Activity;
use Lang;
use Tenant;

class StoreDetailAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    protected $store = NULL;
    protected $withoutScore = FALSE;

    /**
     * GET - get all detail store in all mall, group by name
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getStoreDetail()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;
        $mall = NULL;

        try {
            $user = $this->getUser();

            // Call validation from store helper
            $storeHelper = StoreHelper::create();
            $storeHelper->registerCustomValidation();

            $merchantid = OrbitInput::get('merchant_id');
            $language = OrbitInput::get('language', 'id');
            $mallId = OrbitInput::get('mall_id', null);

            $this->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'merchantid' => $merchantid,
                    'language' => $language,
                ),
                array(
                    'merchantid' => 'required',
                    'language' => 'required|orbit.empty.language_default',
                ),
                array(
                    'required' => 'Merchant id is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $this->valid_language;

            $prefix = DB::getTablePrefix();
            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as path";
            if ($usingCdn) {
                $image = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as path";
            }

            $store = Tenant::select(
                                'merchants.merchant_id',
                                'merchants.name',
                                'merchants.name as mall_name',
                                'merchants.description as mall_description',
                                DB::raw("CASE WHEN ({$prefix}total_object_page_views.total_view IS NULL OR {$prefix}total_object_page_views.total_view = '') THEN 0 ELSE {$prefix}total_object_page_views.total_view END as total_view"),
                                DB::Raw("CASE WHEN (
                                                select mt.description
                                                from {$prefix}merchant_translations mt
                                                where mt.merchant_id = {$prefix}merchants.merchant_id
                                                    and mt.merchant_language_id = {$this->quote($valid_language->language_id)}
                                            ) = ''
                                            THEN (
                                                select mt.description
                                                from {$prefix}merchant_translations mt
                                                where mt.merchant_id = {$prefix}merchants.merchant_id
                                                    and mt.merchant_language_id = {$prefix}languages.language_id
                                            )
                                            ELSE (
                                                select mt.description
                                                from {$prefix}merchant_translations mt
                                                where mt.merchant_id = {$prefix}merchants.merchant_id
                                                    and mt.merchant_language_id = {$this->quote($valid_language->language_id)}
                                            )
                                        END as description
                                    "),
                                'merchants.url'
                            )
                ->with(['categories' => function ($q) use ($valid_language, $prefix) {
                        $q->select(
                                DB::Raw("
                                        CASE WHEN (
                                                    SELECT ct.category_name
                                                    FROM {$prefix}category_translations ct
                                                        WHERE ct.status = 'active'
                                                            and ct.merchant_language_id = {$this->quote($valid_language->language_id)}
                                                            and ct.category_id = {$prefix}categories.category_id
                                                    ) != ''
                                            THEN (
                                                    SELECT ct.category_name
                                                    FROM {$prefix}category_translations ct
                                                    WHERE ct.status = 'active'
                                                        and ct.merchant_language_id = {$this->quote($valid_language->language_id)}
                                                        and category_id = {$prefix}categories.category_id
                                                    )
                                            ELSE {$prefix}categories.category_name
                                        END AS category_name
                                    ")
                            )
                            ->groupBy('categories.category_id')
                            ->orderBy('category_name')
                            ;
                    }, 'mediaLogo' => function ($q) use ($image) {
                        $q->select(
                                DB::raw("{$image}"),
                                'media.object_id'
                            );
                    }, 'mediaImageOrig' => function ($q) use ($image) {
                        $q->select(
                                DB::raw("{$image}"),
                                'media.object_id'
                            );
                    }, 'mediaImageCroppedDefault' => function ($q) use ($image) {
                        $q->select(
                                DB::raw("{$image}"),
                                'media.object_id'
                            );
                    },  'keywords' => function ($q) {
                        $q->addSelect('keyword', 'object_id');
                    }])
                ->join(DB::raw("(select merchant_id, country_id, status, parent_id from {$prefix}merchants where object_type = 'mall') as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                ->join('languages', 'languages.name', '=', 'merchants.mobile_default_language')
                ->where('merchants.status', 'active')
                ->whereRaw("oms.status = 'active'");

            $storeInfo = Tenant::select('merchants.name')
                            ->where('merchant_id', $merchantid)
                            ->first();
            if (! is_object($storeInfo)) {
                throw new OrbitCustomException('Unable to find store.', Tenant::NOT_FOUND_ERROR_CODE, NULL);
            }

            if (! is_null($mallId)) {
                $store->leftJoin('total_object_page_views', function ($q) use ($mallId){
                            $q->on('total_object_page_views.object_id', '=', 'merchants.merchant_id')
                                ->on('total_object_page_views.object_type', '=', DB::raw("'tenant'"))
                                ->on('total_object_page_views.location_id', '=', DB::raw("'{$mallId}'"));
                        })
                      ->where('merchants.name', $storeInfo->name)
                      ->where('merchants.parent_id', $mallId);

                $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $mallId)
                        ->first();
            } else {
                $store->leftJoin('base_merchants', function ($q) {
                            $q->on('base_merchants.name', '=', 'merchants.name')
                              ->on('base_merchants.country_id', '=', DB::raw("oms.country_id"));
                        })
                      ->leftJoin('total_object_page_views', function ($q) {
                            $q->on('total_object_page_views.object_id', '=', 'base_merchants.base_merchant_id')
                                ->on('total_object_page_views.object_type', '=', DB::raw("'tenant'"))
                                ->on('total_object_page_views.location_id', '=', DB::raw("'0'"));
                        })
                      ->where('merchants.merchant_id', $merchantid);
            }

            $store = $store->orderBy('merchants.created_at', 'asc')
                ->first();

            if (is_object($mall)) {
                $activityNotes = sprintf('Page viewed: View mall store detail page');
                $activity->setUser($user)
                    ->setActivityName('view_mall_store_detail')
                    ->setActivityNameLong('View mall store detail')
                    ->setObject($store)
                    ->setLocation($mall)
                    ->setModuleName('Store')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            } else {
                $activityNotes = sprintf('Page viewed: Landing Page Store Detail Page');
                $activity->setUser($user)
                    ->setActivityName('view_landing_page_store_detail')
                    ->setActivityNameLong('View GoToMalls Store Detail')
                    ->setObject($store)
                    ->setLocation($mall)
                    ->setModuleName('Store')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            $this->response->data = $store;
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
            $httpCode = 500;
        }

        $output = $this->render($httpCode);

        return $output;
    }

    protected function registerCustomValidation() {
        // Check language is exists
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $lang_name = $value;

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang_name)
                            ->first();

            if (empty($language)) {
                return FALSE;
            }

            $this->valid_language = $language;
            return TRUE;
        });

        // Check store is exists
        Validator::extend('orbit.empty.tenant', function ($attribute, $value, $parameters) {
            $store = Tenant::where('status', 'active')
                            ->where('merchant_id', $value)
                            ->first();

            if (empty($store)) {
                return FALSE;
            }

            $this->store = $store;
            return TRUE;
        });
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}