<?php
namespace Orbit\Controller\API\v1\Product;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Event;
use Validator;
use Lang;
use Config;
use Category;
use stdclass;

class CategoryListAPIController extends ControllerAPI
{
    /**
     * GET - Search Category
     *
     * @author Kadek <kadek@dominopos.com>
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sortby`                (optional) - column order by. Valid value: registered_date, category_name, category_level, category_order, description, status.
     * @param string   `sortmode`              (optional) - asc or desc
     * @param array    `category_id`           (optional) - Category ID
     * @param array    `merchant_id`           (optional) - Merchant ID
     * @param array    `category_name`         (optional) - Category name
     * @param string   `category_name_like`    (optional) - Category name like
     * @param array    `category_level`        (optional) - Category level. Valid value: 1 to 5.
     * @param array    `category_order`        (optional) - Category order
     * @param array    `description`           (optional) - Description
     * @param string   `description_like`      (optional) - Description like
     * @param array    `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @param integer  `skip`                  (optional) - limit offset
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchCategory()
    {
        // flag for limit the query result
        // TODO : should be change in the future
        $limit = FALSE;
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');

            // TODO : change this into something else
            $limited = OrbitInput::get('limited');

            if ($limited === 'yes') {
                $limit = TRUE;
            }

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,category_name,category_level,category_order,description,status,translation_category_name',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.category_sortby'),
                )
            );

            Event::fire('orbit.category.getsearchcategory.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.category.getsearchcategory.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.product_category.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.product_category.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $merchant_id = OrbitInput::get('merchant_id', 0);

            // Builder object
            // if flag limit is true then show only category_id and category_name to make the frontend life easier
            // TODO : remove this with something like is_all_retailer on orbit-shop
            if ($limit) {
                $categories = Category::select('categories.category_id','category_name')->where('merchant_id', $merchant_id)->excludeDeleted('categories');
            } else {
                $categories = Category::where('merchant_id', $merchant_id)->excludeDeleted('categories');
            }

            // Filter category by Ids
            OrbitInput::get('category_id', function($categoryIds) use ($categories)
            {
                $categories->whereIn('categories.category_id', $categoryIds);
            });

            // Filter category by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($categories) {
                $categories->whereIn('categories.merchant_id', (array)$merchantIds);
            });

            // Filter category by category name
            OrbitInput::get('category_name', function($categoryname) use ($categories)
            {
                $categories->whereIn('categories.category_name', $categoryname);
            });

            // Filter category by matching category name pattern
            OrbitInput::get('category_name_like', function($categoryname) use ($categories)
            {
                $categories->where('categories.category_name', 'like', "%$categoryname%");
            });

            // Filter category by category level
            OrbitInput::get('category_level', function($categoryLevels) use ($categories)
            {
                $categories->whereIn('categories.category_level', $categoryLevels);
            });

            // Filter category by category order
            OrbitInput::get('category_order', function($categoryOrders) use ($categories)
            {
                $categories->whereIn('categories.category_order', $categoryOrders);
            });

            // Filter category by description
            OrbitInput::get('description', function($description) use ($categories)
            {
                $categories->whereIn('categories.description', $description);
            });

            // Filter category by matching description pattern
            OrbitInput::get('description_like', function($description) use ($categories)
            {
                $categories->where('categories.description', 'like', "%$description%");
            });

            // Filter category by status
            OrbitInput::get('status', function ($status) use ($categories) {
                $categories->whereIn('categories.status', $status);
            });

            OrbitInput::get('with', function ($with) use ($categories) {
                if (!is_array($with)) {
                    $with = [$with];
                }
                foreach ($with as $rel) {
                    if (in_array($rel, ['translations'])) {
                        $categories->with($rel);
                    }
                }
            });

            // filter by language id
            OrbitInput::get('language_id', function($language_id) use ($categories) {
                $prefix = DB::getTablePrefix();

                $categories->selectRaw("{$prefix}categories.*")
                           ->leftJoin('category_translations', 'category_translations.category_id', '=', 'categories.category_id')
                           ->where('category_translations.merchant_language_id', $language_id)
                           ->where('category_translations.category_name', '!=', '');
            });

            // Filter category by matching category name pattern
            OrbitInput::get('translation_category_name_like', function($categoryname) use ($categories)
            {
                $categories->where('category_translations.category_name', 'like', "%$categoryname%");
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_categories = clone $categories;

            // if limit is true show all records
            // TODO : replace this with something else in the future
            if (!$limit) {
                // Get the take args
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
                $categories->take($take);

                $skip = 0;
                OrbitInput::get('skip', function($_skip) use (&$skip, $categories)
                {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                });
                $categories->skip($skip);
            }

            // Default sort by
            $sortBy = 'categories.category_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'           => 'categories.created_at',
                    'category_name'             => 'categories.category_name',
                    'category_level'            => 'categories.category_level',
                    'category_order'            => 'categories.category_order',
                    'description'               => 'categories.description',
                    'status'                    => 'categories.status',
                    'translation_category_name' => 'category_translations.category_name'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $categories->orderBy($sortBy, $sortMode);

            // @TODO: quick solving.
            // also sort by name when level is being sorted.
            if ($sortBy === 'categories.category_level') {
                $categories->orderBy('categories.category_name', 'asc');
            }

            $totalCategories = RecordCounter::create($_categories)->count();
            $listOfCategories = $categories->get();

            $data = new stdclass();
            $data->total_records = $totalCategories;
            $data->returned_records = count($listOfCategories);
            $data->records = $listOfCategories;

            if ($totalCategories === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.categories');
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


    protected function registerCustomValidation()
    {
        // Check the existance of default_language
        Validator::extend('orbit.empty.default_en', function ($attribute, $value, $parameters) {
            $lang = Language::excludeDeleted()
                        ->where('name', $value)
                        ->first();

            if (empty($lang) || $value !== 'en') {
                return FALSE;
            }

            $this->valid_default_lang = $lang;

            return TRUE;
        });

        // Check the existance of language
        Validator::extend('orbit.empty.language', function ($attribute, $value, $parameters) {
            $lang = Language::excludeDeleted()
                        ->where('name', $value)
                        ->first();

            if (empty($lang)) {
                return FALSE;
            }

            $this->valid_lang = $lang;

            return TRUE;
        });

        // Check the existance of category id
        Validator::extend('orbit.empty.category', function ($attribute, $value, $parameters) {
            $category = Category::excludeDeleted()
                        ->where('category_id', $value)
                        ->first();

            if (empty($category)) {
                return FALSE;
            }

            $this->valid_category = $category;

            return TRUE;
        });

        // Check the existance of merchant id
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) {
            $merchant = Mall::excludeDeleted()
                        ->isMall()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return TRUE;
        });

        // Check category name, it should not exists
        Validator::extend('orbit.exists.category_name', function ($attribute, $value, $parameters) {
            $categoryName = Category::excludeDeleted()
                        ->where('category_name', $value)
                        ->where('merchant_id', '0')
                        ->first();

            if (! empty($categoryName)) {
                return FALSE;
            }

            App::instance('orbit.validation.category_name', $categoryName);

            return TRUE;
        });

        // Check category name, it should not exists (for update)
        Validator::extend('category_name_exists_but_me', function ($attribute, $value, $parameters) {
            $category_id = trim($parameters[0]);

            $category = Category::excludeDeleted()
                        ->where('category_name', $value)
                        ->where('category_id', '!=', $category_id)
                        ->where('merchant_id', '0')
                        ->first();

            if (! empty($category)) {
                return FALSE;
            }

            $this->update_valid_category = $category;

            return TRUE;
        });

        // Check the existence of the category status
        Validator::extend('orbit.empty.category_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'pending', 'blocked', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

    }
}