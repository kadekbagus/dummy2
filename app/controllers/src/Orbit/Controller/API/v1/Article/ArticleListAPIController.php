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

use Article;
use Validator;
use Lang;

use DB;
use Config;
use stdclass;
use Orbit\Controller\API\v1\Article\ArticleHelper;
use Carbon\Carbon;

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
            // $articleHelper->merchantCustomValidator();

            $sort_by = OrbitInput::get('sortby');
            $isSuggestion = OrbitInput::get('is_suggestion', 'N');
            $country = OrbitInput::get('country');

            $validator = Validator::make(
                array(
                    'sortby' => $sort_by,
                ),
                array(
                    'sortby' => 'in:title,published_at,created_at,updated_at,status',
                ),
                array(
                    'sortby.in' => 'The sort by argument you specified is not valid, the valid values are: title,published_at,created_at,status',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            if ($isSuggestion === 'Y') {
                $article = Article::select(DB::raw("
                                    {$prefix}articles.article_id,
                                    {$prefix}articles.slug,
                                    {$prefix}articles.title,
                                    {$prefix}countries.name as country_name
                                "))
                                ->join('countries', 'articles.country_id', '=', 'countries.country_id')
                                ->where('articles.status', 'active')
                                ->where('published_at', '<=', Carbon::now('Asia/Jakarta'));

                if (! empty($country)) {
                    $article->where('countries.name', $country);
                }
            }
            else {
                $article = Article::select(DB::raw("{$prefix}articles.*, {$prefix}countries.name as country_name"))
                                    ->join('countries', 'articles.country_id', '=', 'countries.country_id')
                                    ->where('articles.status', '!=', 'deleted')
                                    ->with('objectNews')
                                    ->with('objectPromotion')
                                    ->with('objectCoupon')
                                    ->with('objectMall')
                                    ->with('objectMerchant')
                                    ->with('objectProduct')
                                    ->with('objectArticle')
                                    ->with('objectPartner')
                                    ->with('category')
                                    ->with('mediaCover')
                                    ->with('mediaContent')
                                    ->with('video')
                                    ->with('cities');
            }

            OrbitInput::get('article_id', function($article_id) use ($article, $isSuggestion)
            {
                if ($isSuggestion === 'Y') {
                    $article->whereNotIn('article_id', [$article_id]);
                }
                else {
                    $article->where('article_id', $article_id);
                }
            });

            // Filter merchant by name
            OrbitInput::get('title', function($title) use ($article)
            {
                $article->where('title', $title);
            });

            // Filter merchant by matching name pattern
            OrbitInput::get('title_like', function($title) use ($article)
            {
                $article->where('title', 'like', "%{$title}%");
            });

            if ($role->role_name == 'Article Writer') {
                $article->where('created_by', $user->user_id);
            }

            $article->groupBy('article_id');

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_articles = clone $article;

            $take = PaginationNumber::parseTakeFromGet('merchant');
            $article->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $article->skip($skip);

            // Default sort by
            $sortBy = 'title';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'title' => 'title',
                    'published_at' => 'published_at',
                    'created_at' => 'articles.created_at',
                    'updated_at' => 'articles.updated_at',
                    'status' => 'articles.status',
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

            $totalArticles = RecordCounter::create($_articles)->count();
            $listOfArticles = $article->get();

            $data = new stdclass();
            $data->total_records = $totalArticles;
            $data->returned_records = count($listOfArticles);
            $data->records = $listOfArticles;

            if ($totalArticles === 0) {
                $data->records = NULL;
                $this->response->message = "There is no article that matched your search criteria";
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
