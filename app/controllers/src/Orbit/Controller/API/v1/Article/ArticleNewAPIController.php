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

use BaseMerchant;
use BaseMerchantCategory;
use BaseMerchantKeyword;
use ObjectSupportedLanguage;
use BaseObjectPartner;
use Config;
use Language;
use Keyword;
use Event;
use Category;
use ObjectBank;
use ObjectFinancialDetail;
use MerchantStorePaymentProvider;
use ProductTag;
use ProductTagObject;
use BaseMerchantProductTag;

use Article;
use ArticleLinkToObject;
use ArticleVideo;

use Orbit\Controller\API\v1\Merchant\Merchant\MerchantHelper;

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

/*
    article_id
    title
    slug
    meta_title
    meta_description
    body    mediumtext  no
    status
    created_by
    modified_by
    published_at
    created_at
    updated_at

    Link to Malls [[malls]]
    Link to Brands (Merchant) [[brands]]
    Link to Events [[events]]
    Link to Promotions [[promotions]]
    Link to Coupons [[coupons]]
    Article Body Images
    Article Body Videos (YouTube)
*/

            $title = OrbitInput::post('title');
            $slug = OrbitInput::post('slug');
            $metaTitle = OrbitInput::post('meta_title');
            $metaDescription = OrbitInput::post('meta_description');
            $body = OrbitInput::post('body');
            $status = OrbitInput::post('status');
            $countryId = OrbitInput::post('country_id');
            $publishedAt = OrbitInput::post('published_at');

            $objectNews = OrbitInput::post('object_news', []);
            $objectPromotions = OrbitInput::post('object_promotions', []);
            $objectCoupons = OrbitInput::post('object_coupons', []);
            $objectMalls = OrbitInput::post('object_malls', []);
            $objectBrands = OrbitInput::post('object_brands', []);
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
                    'country_id'       => $countryId,
                ),
                array(
                    'title'            => 'required|orbit.exist.article_name:' . $title,
                    'title'            => 'required',
                    'slug'             => 'required',
                    'meta_title'       => 'required',
                    'meta_description' => 'required',
                    'body'             => 'required',
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

            $newArticle = new BaseMerchant;
            $newArticle->title = $title;
            $newArticle->slug = $slug;
            $newArticle->meta_title = $metaTitle;
            $newArticle->meta_description = $metaDescription;
            $newArticle->body = $body;
            $newArticle->status = $status;
            $newArticle->country_id = $countryId;
            $newArticle->published_at = $publishedAt;


            if (! empty($translations) ) {
                $dataTranslations = @json_decode($translations);
                if (json_last_error() != JSON_ERROR_NONE) {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
                }

                if (! is_null($dataTranslations)) {
                    // Get english tenant description for saving to default language
                    foreach ($dataTranslations as $key => $val) {
                        // Validation language id from translation
                        $language = Language::where('language_id', '=', $key)->first();
                        if (empty($language)) {
                            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
                        }

                        if ($key === $idLanguageEnglish->language_id) {
                            $newArticle->description = $val->description;
                            $newArticle->custom_title = $val->custom_title;
                        }
                    }
                }
            }

            Event::fire('orbit.article.postnewarticle.before.save', array($this, $newArticle));

            // check mobile default language must in supported language
            if (in_array($mobile_default_language, $languages)) {
                $newArticle->mobile_default_language = $mobile_default_language;
            } else {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.mobile_default_lang'));
            }

            $newArticle->save();

            // save base merchant categories
            $baseMerchantCategorys = array();
            foreach ($categoryIds as $category_id) {
                $BaseMerchantCategory = new BaseMerchantCategory();
                $BaseMerchantCategory->article_id = $newArticle->article_id;
                $BaseMerchantCategory->category_id = $category_id;
                $BaseMerchantCategory->save();
                $baseMerchantCategorys[] = $BaseMerchantCategory;
            }
            $newArticle->categories = $baseMerchantCategorys;

            // save Keyword
            $tenantKeywords = array();
            foreach ($keywords as $keyword) {
                $keyword_id = null;

                $existKeyword = Keyword::excludeDeleted()
                    ->where('keyword', '=', $keyword)
                    ->first();

                if (empty($existKeyword)) {
                    $newKeyword = new Keyword();
                    $newKeyword->merchant_id = '0';
                    $newKeyword->keyword = $keyword;
                    $newKeyword->status = 'active';
                    $newKeyword->created_by = $user->user_id;
                    $newKeyword->modified_by = $user->user_id;
                    $newKeyword->save();

                    $keyword_id = $newKeyword->keyword_id;
                    $tenantKeywords[] = $newKeyword;
                } else {
                    $keyword_id = $existKeyword->keyword_id;
                    $tenantKeywords[] = $existKeyword;
                }

                $newKeywordObject = new BaseMerchantKeyword();
                $newKeywordObject->article_id = $newArticle->article_id;
                $newKeywordObject->keyword_id = $keyword_id;
                $newKeywordObject->save();

            }
            $newArticle->keywords = $tenantKeywords;


            // save product tag
            $tenantProductTags = array();
            foreach ($productTags as $productTag) {
                $product_tag_id = null;

                $existProductTag = ProductTag::excludeDeleted()
                    ->where('product_tag', '=', $productTag)
                    ->first();

                if (empty($existProductTag)) {
                    $newProductTag = new ProductTag();
                    $newProductTag->merchant_id = '0';
                    $newProductTag->product_tag = $productTag;
                    $newProductTag->status = 'active';
                    $newProductTag->created_by = $user->user_id;
                    $newProductTag->modified_by = $user->user_id;
                    $newProductTag->save();

                    $product_tag_id = $newProductTag->product_tag_id;
                    $tenantProductTags[] = $newProductTag;
                } else {
                    $product_tag_id = $existProductTag->product_tag_id;
                    $tenantProductTags[] = $existProductTag;
                }

                $newKeywordObject = new BaseMerchantProductTag();
                $newKeywordObject->article_id = $newArticle->article_id;
                $newKeywordObject->product_tag_id = $product_tag_id;
                $newKeywordObject->save();

            }
            $newArticle->product_tags = $tenantProductTags;

            //save to base object partner
            if (! empty($partnerIds)) {
              foreach ($partnerIds as $partnerId) {
                if ($partnerId != "") {
                  $baseObjectPartner = new BaseObjectPartner();
                  $baseObjectPartner->object_id = $newArticle->article_id;
                  $baseObjectPartner->object_type = 'tenant';
                  $baseObjectPartner->partner_id = $partnerId;
                  $baseObjectPartner->save();
                }
              }
            }

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
