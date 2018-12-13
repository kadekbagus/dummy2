<?php namespace Orbit\Controller\API\v1\Pub\Article;

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \URL;

use Article;

use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Activity;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use OrbitShop\API\v1\ResponseProvider;
use \Orbit\Helper\Exception\OrbitCustomException;
use Redis;
use Orbit\Controller\API\v1\Article\ArticleHelper;


class ArticleDetailAPIController extends PubControllerAPI
{

    /**
     * GET - get the article detail
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string article_id
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getArticleDetail()
    {
        $httpCode = 200;
        $this->response = new ResponseProvider();
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;

        try{
            $user = $this->getUser();

            $articleId = OrbitInput::get('article_id', null);
            $language = OrbitInput::get('language', 'id');
            $mallId = OrbitInput::get('mall_id', null);
            $country = OrbitInput::get('country', null);

            $articleHelper = ArticleHelper::create();
            $articleHelper->articleCustomValidator();
            $validator = Validator::make(
                array(
                    'article_id' => $articleId,
                    'language' => $language,
                ),
                array(
                    'article_id' => 'required',
                    'language' => 'required|orbit.empty.language_default',
                ),
                array(
                    'required' => 'News ID is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $articleHelper->getValidLanguage();

            $prefix = DB::getTablePrefix();

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $image = "CONCAT({$this->quote($urlPrefix)}, m.path)";
            if ($usingCdn) {
                $image = "CASE WHEN m.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, m.path) ELSE m.cdn_url END";
            }

            $location = $mallId;
            if (empty($location)) {
                $location = 0;
            }

            $article = Article::where('status', '!=', 'deleted')
                                ->with('objectNews')
                                ->with('objectPromotion')
                                ->with('objectCoupon')
                                ->with('objectMall')
                                ->with('objectMerchant')
                                ->with('category')
                                ->with('mediaCover')
                                ->with('mediaContent')
                                ->with('video')
                                ->where('article_id', $articleId)
                                ->first();

            $message = 'Request Ok';
            if (! is_object($article)) {
                throw new OrbitCustomException('Article that you specify is not found', News::NOT_FOUND_ERROR_CODE, NULL);
            }

            $mall = null;

            // Only campaign having status ongoing and is_started true can going to detail page
            if ($article->status == 'inactive') {
                $mallName = 'gtm';

                $customData = new \stdClass;
                $customData->type = 'article';
                $customData->location = $location;
                $customData->mall_name = $mallName;
                throw new OrbitCustomException('News is inactive', News::INACTIVE_ERROR_CODE, $customData);
            }

            $activityNotes = sprintf('Page viewed: Landing Page Article Detail Page');
            $activity->setUser($user)
                ->setActivityName('view_landing_page_article_detail')
                ->setActivityNameLong('View GoToMalls Article Detail')
                ->setObject($article)
                ->setLocation($mall)
                ->setNews($article)
                ->setModuleName('Article')
                ->setNotes($activityNotes)
                ->responseOK()
                ->save();

            // add facebook share url dummy page
            $article->facebook_share_url = SocMedAPIController::getSharedUrl('article', $article->article_id, $article->title, $country);

            $this->response->data = $article;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = $message;

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

        } catch (\Orbit\Helper\Exception\OrbitCustomException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getCustomData();
            if ($this->response->code === 4040) {
                $httpCode = 404;
            } else {
                $httpCode = 500;
            }

        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
