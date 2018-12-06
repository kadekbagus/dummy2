<?php namespace Orbit\Controller\API\v1\Article;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Validator;

use Lang;
use Config;
use Category;
use Event;

use Article;
use ArticleLinkToObject;
use ArticleVideo;

use Orbit\Controller\API\v1\Article\ArticleHelper;

class ArticleNewAPIController extends ControllerAPI
{
    protected $articleRoles = ['article writer', 'article publisher'];

    /**
     * Create new article on article manager portal.
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    public function postNewArticle()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.article.postnewarticle.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.article.postnewarticle.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.article.postnewarticle.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->articleRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.article.postnewarticle.after.authz', array($this, $user));

            $articleHelper = ArticleHelper::create();
            $articleHelper->articleCustomValidator();

            $title = OrbitInput::post('title');
            $slug = OrbitInput::post('slug');
            $metaTitle = OrbitInput::post('meta_title');
            $metaDescription = OrbitInput::post('meta_description');
            $body = OrbitInput::post('body');
            $status = OrbitInput::post('status', 'inactive');
            $countryId = OrbitInput::post('country_id');
            $publishedAt = OrbitInput::post('published_at');

            $objectNews = OrbitInput::post('object_news', []);
            $objectPromotions = OrbitInput::post('object_promotions', []);
            $objectCoupons = OrbitInput::post('object_coupons', []);
            $objectMalls = OrbitInput::post('object_malls', []);
            $objectMerchants = OrbitInput::post('object_merchants', []);
            $categories = OrbitInput::post('categories', []);
            $videos = OrbitInput::post('videos', []);

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'title'            => $title,
                    'slug'             => $slug,
                    'meta_title'       => $metaTitle,
                    'meta_description' => $metaDescription,
                    'body'             => $body,
                    'status'           => $status,
                    'country_id'       => $countryId,
                ),
                array(
                    'title'            => 'required|orbit.exist.title:' . $title,
                    'slug'             => 'required|orbit.exist.slug:' . $slug,
                    'meta_title'       => 'required',
                    'meta_description' => 'required',
                    'body'             => 'required',
                    'status'           => 'required',
                    'country_id'       => 'required',
                ),
               //  array(
               //      'orbit.exist.article_name' => 'Article is already exist',
               // )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // validate category_ids
            if (isset($categories) && count($categories) > 0) {
                foreach ($categories as $category_id_check) {
                    $validator = Validator::make(
                        array(
                            'category_id'   => $category_id_check,
                        ),
                        array(
                            'category_id'   => 'orbit.empty.category',
                        )
                    );

                    Event::fire('orbit.article.postnewarticle.before.categoryvalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.article.postnewarticle.after.categoryvalidation', array($this, $validator));
                }
            }

            Event::fire('orbit.article.postnewarticle.after.validation', array($this, $validator));

            $newArticle = new Article;
            $newArticle->title = $title;
            $newArticle->slug = $slug;
            $newArticle->meta_title = $metaTitle;
            $newArticle->meta_description = $metaDescription;
            $newArticle->body = $body;
            $newArticle->status = $status;
            $newArticle->country_id = $countryId;
            $newArticle->published_at = $publishedAt;

            Event::fire('orbit.article.postnewarticle.before.save', array($this, $newArticle));

            $newArticle->save();


            // save article object
            $news = array();
            foreach ($objectNews as $newsId) {
                $saveObjectNews = new ArticleLinkToObject();
                $saveObjectNews->article_id = $newArticle->article_id;
                $saveObjectNews->object_id = $newsId;
                $saveObjectNews->object_type = 'news';
                $saveObjectNews->save();
                $news[] = $saveObjectNews;
            }
            $newArticle->object_news = $news;

            $promotion = array();
            foreach ($objectPromotions as $promotionId) {
                $saveObjectPromotion = new ArticleLinkToObject();
                $saveObjectPromotion->article_id = $newArticle->article_id;
                $saveObjectPromotion->object_id = $promotionId;
                $saveObjectPromotion->object_type = 'promotion';
                $saveObjectPromotion->save();
                $promotion[] = $saveObjectPromotion;
            }
            $newArticle->object_promotion = $promotion;

            $coupon = array();
            foreach ($objectCoupons as $couponId) {
                $saveObjectPromotion = new ArticleLinkToObject();
                $saveObjectPromotion->article_id = $newArticle->article_id;
                $saveObjectPromotion->object_id = $couponId;
                $saveObjectPromotion->object_type = 'coupon';
                $saveObjectPromotion->save();
                $coupon[] = $saveObjectPromotion;
            }
            $newArticle->object_coupon = $coupon;

            $mall = array();
            foreach ($objectMalls as $mallId) {
                $saveObjectMall = new ArticleLinkToObject();
                $saveObjectMall->article_id = $newArticle->article_id;
                $saveObjectMall->object_id = $mallId;
                $saveObjectMall->object_type = 'mall';
                $saveObjectMall->save();
                $mall[] = $saveObjectMall;
            }
            $newArticle->object_mall = $mall;

            $merchant = array();
            foreach ($objectMerchants as $merchantId) {
                $saveObjectMerchant = new ArticleLinkToObject();
                $saveObjectMerchant->article_id = $newArticle->article_id;
                $saveObjectMerchant->object_id = $merchantId;
                $saveObjectMerchant->object_type = 'merchant';
                $saveObjectMerchant->save();
                $merchant[] = $saveObjectMerchant;
            }
            $newArticle->object_news = $news;

            $category = array();
            foreach ($categories as $categoryId) {
                $saveObjectCategories = new ArticleLinkToObject();
                $saveObjectCategories->article_id = $newArticle->article_id;
                $saveObjectCategories->object_id = $categoryId;
                $saveObjectCategories->object_type = 'category';
                $saveObjectCategories->save();
                $category[] = $saveObjectCategories;
            }
            $newArticle->object_news = $category;

            // save article video
            $video = array();
            foreach ($videos as $video) {
                $saveVideo = new ArticleLinkToObject();
                $saveVideo->article_id = $newArticle->article_id;
                $saveVideo->video_id = $video->video_id;
                $saveVideo->tag_name = $video->tag_name;
                $saveVideo->save();
                $video[] = $saveVideo;
            }
            $newArticle->videos = $video;


            Event::fire('orbit.article.postnewarticle.after.save', array($this, $newArticle));

            $this->response->data = $newArticle;

            // Commit the changes
            $this->commit();

          Event::fire('orbit.article.postnewarticle.after.commit', array($this, $newArticle));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.article.postnewarticle.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.article.postnewarticle.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.article.postnewarticle.query.error', array($this, $e));

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

            // Rollback the changes
            $this->rollBack();
        } catch (Exception $e) {
            Event::fire('orbit.article.postnewarticle.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

}
