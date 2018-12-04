<?php namespace Orbit\Controller\API\v1\Article;

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
use Orbit\Controller\API\v1\Article\ArticleHelper;

class ArticleListAPIController extends ControllerAPI
{
    protected $articleRoles = ['article writer', 'article publisher'];

    /**
     * GET Search / list Article
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function getSearchArticle()
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
            $validRoles = $this->articleRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $articleHelper = ArticleHelper::create();
            $articleHelper->merchantCustomValidator();

            $sort_by = OrbitInput::get('sortby');

            $validator = Validator::make(
                array(
                    'sortby' => $sort_by,
                ),
                array(
                    'sortby' => 'in:title,created_at',
                ),
                array(
                    'sortby.in' => 'The sort by argument you specified is not valid, the valid values are: title, created_at',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $article = Article::select(
                                        'base_articles.base_article_id',
                                        'base_articles.country_id',
                                        'countries.name as country_name',
                                        'base_articles.name',
                                        DB::raw("(CASE
                                            WHEN COUNT({$prefix}pre_exports.object_id) > 0 THEN 'in_progress'
                                            ELSE
                                                CASE WHEN ({$prefix}media.path IS NULL or {$prefix}media.path = '') or
                                                        ({$prefix}base_articles.phone IS NULL or {$prefix}base_articles.phone = '') or
                                                        ({$prefix}base_articles.email IS NULL or {$prefix}base_articles.email = '') or
                                                        ({$prefix}base_articles.mobile_default_language IS NULL or {$prefix}base_articles.mobile_default_language = '')
                                                    THEN 'not_available'
                                                ELSE 'available'
                                                END
                                            END) as export_status"),
                                        DB::raw("(SELECT count(base_store_id) FROM {$prefix}base_stores WHERE base_article_id = {$prefix}base_articles.base_article_id) as location_count"),
                                        'base_articles.status'
                                    )
                                    ->leftJoin('media', function ($q){
                                        $q->on('media.object_id', '=', 'base_articles.base_article_id')
                                          ->on('media.media_name_id', '=', DB::raw("'base_merchant_logo_grab'"));
                                        $q->on('media.media_name_long', '=', DB::raw("'base_merchant_logo_grab_orig'"));
                                    })
                                    ->leftJoin('pre_exports', function ($q){
                                        $q->on('pre_exports.object_id', '=', 'base_articles.base_article_id')
                                          ->on('pre_exports.object_type', '=', DB::raw("'merchant'"));
                                    })
                                    ->leftJoin('countries', 'base_articles.country_id', '=', 'countries.country_id')
                                    ->excludeDeleted('base_merchants');

            OrbitInput::get('article_id', function($data) use ($article)
            {
                $article->whereIn('articles.article_id', $data);
            });

            // Filter merchant by name
            OrbitInput::get('name', function($name) use ($article)
            {
                $article->whereIn('base_articles.name', $name);
            });

            // Filter merchant by matching name pattern
            OrbitInput::get('name_like', function($name) use ($article)
            {
                $article->where('base_articles.name', 'like', "%$name%");
            });

            // Filter by country
            OrbitInput::get('country', function($country) use ($article) {
                $article->where('base_articles.country_id', $country);
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($article) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'partners') {
                        $article->with('partners');
                    }
                }
            });

            $article->groupBy('base_articles.base_article_id');

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_merchants = clone $article;

            $take = PaginationNumber::parseTakeFromGet('merchant');
            $article->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $article->skip($skip);

            // Default sort by
            $sortBy = 'base_articles.name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'merchant_name' => 'base_articles.name',
                    'location_number' => 'location_count',
                    'status' => 'base_articles.status'
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
            $article->orderBy($sortBy, $sortMode);

            $totalMerchants = RecordCounter::create($_merchants)->count();
            $listOfArticles = $article->get();

            $data = new stdclass();
            $data->total_records = $totalMerchants;
            $data->returned_records = count($listOfArticles);
            $data->records = $listOfArticles;

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
