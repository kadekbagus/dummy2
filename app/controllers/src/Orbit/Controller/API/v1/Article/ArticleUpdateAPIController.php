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

class ArticleUpdateAPIController extends ControllerAPI
{
    protected $articleRoles = ['article writer', 'article publisher'];


    /**
     * Create new article on article manager portal.
     *
     * @author firmansyah <firmansyah@dominopos.com>
     */
    public function postUpdateArticle()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.article.postupdatearticle.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.article.postupdatearticle.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            Event::fire('orbit.article.postupdatearticle.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->articleRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.article.postupdatearticle.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $articleHelper = ArticleHelper::create();
            $articleHelper->articleCustomValidator();

            $articleId = OrbitInput::post('article_id');
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
                    'title'            => 'required',
                    'slug'             => 'required',
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

            Event::fire('orbit.article.postupdatearticle.after.validation', array($this, $validator));

            $updatedArticle = Article::where('article_id', $articleId)->first();

            OrbitInput::post('title', function($title) use ($updatedArticle) {
                $updatedArticle->url = $title;
            });

            OrbitInput::post('slug', function($slug) use ($updatedArticle) {
                $updatedArticle->url = $slug;
            });

            OrbitInput::post('meta_title', function($metaTitle) use ($updatedArticle) {
                $updatedArticle->url = $metaTitle;
            });

            OrbitInput::post('meta_description', function($metaDescription) use ($updatedArticle) {
                $updatedArticle->url = $metaDescription;
            });

            OrbitInput::post('body', function($body) use ($updatedArticle) {
                $updatedArticle->url = $body;
            });

            OrbitInput::post('status', function($status) use ($updatedArticle) {
                $updatedArticle->url = $status;
            });

            OrbitInput::post('country_id', function($countryId) use ($updatedArticle) {
                $updatedArticle->url = $country_id;
            });


            Event::fire('orbit.article.postupdatearticle.before.save', array($this, $updatedArticle));


            $updatedArticle->save();


            OrbitInput::post('category_ids', function($categoryIds) use ($updatedArticle, $baseMerchantId) {
                // Delete old data
                $deleted_base_category = BaseMerchantCategory::where('base_merchant_id', '=', $baseMerchantId)->delete();

                // save base merchant categories
                $baseMerchantCategorys = array();
                foreach ($categoryIds as $category_id) {
                    $BaseMerchantCategory = new BaseMerchantCategory();
                    $BaseMerchantCategory->base_merchant_id = $baseMerchantId;
                    $BaseMerchantCategory->category_id = $category_id;
                    $BaseMerchantCategory->save();
                    $baseMerchantCategorys[] = $BaseMerchantCategory;
                }

                $updatedArticle->categories = $baseMerchantCategorys;
            });


            // update link to partner - base opject partner table
            OrbitInput::post('partner_ids', function($partnerIds) use ($baseMerchantId) {
                // Delete old data
                $delete_partner = BaseObjectPartner::where('object_id', '=', $baseMerchantId)->where('object_type', 'tenant');
                $delete_partner->delete(true);

                if (! empty($partnerIds)) {
                  // Insert new data
                  foreach ($partnerIds as $partnerId) {
                    if ($partnerId != "") {
                      $object_partner = new BaseObjectPartner();
                      $object_partner->object_id = $baseMerchantId;
                      $object_partner->object_type = 'tenant';
                      $object_partner->partner_id = $partnerId;
                      $object_partner->save();
                    }
                  }
                }
            });


            Event::fire('orbit.article.postupdatearticle.after.save', array($this, $updatedArticle));

            $this->response->data = $updatedArticle;

            // Commit the changes
            $this->commit();

          Event::fire('orbit.article.postupdatearticle.after.commit', array($this, $updatedArticle));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.article.postupdatearticle.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.article.postupdatearticle.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.article.postupdatearticle.query.error', array($this, $e));

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
            Event::fire('orbit.article.postupdatearticle.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    protected function registerCustomValidation()
    {
        // Check existing merchant name
        Validator::extend('orbit.exist.merchant_name_not_me', function ($attribute, $value, $parameters) {
            $baseMerchantId = $parameters[0];
            $country = $parameters[1];

            $merchant = BaseMerchant::where('name', '=', $value)
                            ->where('country_id', $country)
                            ->whereNotIn('base_merchant_id', array($baseMerchantId))
                            ->first();

            if (! empty($merchant)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the validity of URL
        Validator::extend('orbit.formaterror.url.web', function ($attribute, $value, $parameters) {
            $url = 'http://' . $value;

            $pattern = '@^((http:\/\/www\.)|(www\.)|(http:\/\/))[a-zA-Z0-9._-]+\.[a-zA-Z.]{2,5}$@';

            if (! preg_match($pattern, $url)) {
                return FALSE;
            }
            return TRUE;
        });

        // Check the validity of base merchant id
        Validator::extend('orbit.exist.base_merchant_id', function ($attribute, $value, $parameters) {
            $baseMerchant = BaseMerchant::where('base_merchant_id', $value)->first();

            if (empty($baseMerchant)) {
                return FALSE;
            }
            return TRUE;
        });
    }

}