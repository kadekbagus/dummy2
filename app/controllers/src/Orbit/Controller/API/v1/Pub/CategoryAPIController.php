<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing mall geo location.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenExceptio;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use Mall;
use Category;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use Elasticsearch\ClientBuilder;
use Language;
use DB;

class CategoryAPIController extends PubControllerAPI
{

    /**
     * GET - check if mall inside map area
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string area
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCategoryList()
    {
      $httpCode = 200;
        try {


            $usingDemo = Config::get('orbit.is_demo', FALSE);
            $sort_by = OrbitInput::get('sortby', 'category_name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $prefix = DB::getTablePrefix();

            $merchant_id = OrbitInput::get('merchant_id', 0);
            $lang = OrbitInput::get('language', 'id');

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang)
                            ->first();

            $categories = Category::select('categories.category_id','category_translations.category_name', 'categories.icon_name', DB::raw("media.path AS image_path"))
                                ->join('category_translations', 'category_translations.category_id', '=', 'categories.category_id')
                                ->leftJoin(DB::raw("(SELECT *
                                                    FROM {$prefix}media
                                                    WHERE object_name = 'category'
                                                        AND media_name_id = 'category_image'
                                                        AND media_name_long = 'category_image_orig') as media"),
                                        DB::raw("media.object_id"), '=', 'categories.category_id')
                                ->where('category_translations.merchant_language_id', '=', $language->language_id)
                                ->where('categories.merchant_id', $merchant_id)
                                ->excludeDeleted('categories');

            $_categories = clone $categories;

            OrbitInput::get('sortby', function($_sortBy) use (&$sort_by)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'name'           => 'categories.category_name',
                    'created_date'   => 'categories.created_at',
                    'category_order' => 'categories.category_order',
                    'icon_name'      => 'categories.icon_name'
                );

                $sort_by = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sort_mode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sort_mode = 'desc';
                }
            });
            $categories->orderBy($sort_by, $sort_mode);

            $take = PaginationNumber::parseTakeFromGet('category');
            $categories->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $categories->skip($skip);

            $listcategories = $categories->get();
            $count = count($_categories->get());

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listcategories);
            $this->response->data->records = $listcategories;
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
        } catch (\Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        $output = $this->render($httpCode);

        return $output;
    }
}