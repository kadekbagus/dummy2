<?php namespace Orbit\Controller\API\v1\Article;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Validator;
use Orbit\Controller\API\v1\Article\ArticleHelper;
use DB;
use Cache;

use Lang;
use Config;
use Category;
use Event;
use Tenant;
use Article;
use ArticleLinkToObject;
use ArticleVideo;
use ArticleCity;

class ArticleUpdateAPIController extends ControllerAPI
{
    protected $articleRoles = ['article writer', 'article publisher'];

    /**
     * Update article on article manager portal.
     *
     * @author Firmansyah <firmansyah@dominopos.com>
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

            $articleHelper = ArticleHelper::create();
            $articleHelper->articleCustomValidator();
            $prefix = DB::getTablePrefix();

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
            $objectProducts = OrbitInput::post('object_products', []);
            $categories = OrbitInput::post('categories', []);
            $videos = OrbitInput::post('videos', []);
            $cities = OrbitInput::post('cities', []);

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
                    'published_at'     => $publishedAt,
                ),
                array(
                    'title'            => 'required|orbit.exist.title_not_me:' . $articleId,
                    'slug'             => 'required|orbit.exist.slug_not_me:' . $articleId,
                    'meta_title'       => 'required',
                    'meta_description' => 'required',
                    'body'             => 'required',
                    'status'           => 'required',
                    'country_id'       => 'required',
                    'published_at'     => 'required',
                ),
                array(
                    'orbit.exist.title_not_me' => 'Title is already exist',
                    'orbit.exist.slug_not_me' => 'Slug is already exist',
               )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.article.postupdatearticle.after.validation', array($this, $validator));

            $updatedArticle = Article::where('article_id', $articleId)->first();

            OrbitInput::post('title', function($title) use ($updatedArticle) {
                $updatedArticle->title = $title;
            });

            OrbitInput::post('slug', function($slug) use ($updatedArticle) {
                $updatedArticle->slug = $slug;
            });

            OrbitInput::post('meta_title', function($metaTitle) use ($updatedArticle) {
                $updatedArticle->meta_title = $metaTitle;
            });

            OrbitInput::post('meta_description', function($metaDescription) use ($updatedArticle) {
                $updatedArticle->meta_description = $metaDescription;
            });

            OrbitInput::post('body', function($body) use ($updatedArticle) {
                $updatedArticle->body = $body;
            });

            OrbitInput::post('status', function($status) use ($updatedArticle) {
                $updatedArticle->status = $status;
            });

            OrbitInput::post('country_id', function($countryId) use ($updatedArticle) {
                $updatedArticle->country_id = $countryId;
            });

            OrbitInput::post('published_at', function($publishedAt) use ($updatedArticle) {
                $updatedArticle->published_at = $publishedAt;
            });

            Event::fire('orbit.article.postupdatearticle.before.save', array($this, $updatedArticle));

            $updatedArticle->modified_by = $user->user_id;
            $updatedArticle->touch();

            $updatedArticle->save();

            // save article cities
            OrbitInput::post('cities', function($cities) use ($updatedArticle, $articleId) {
                $deletedOldData = ArticleCity::where('article_id', '=', $articleId)->delete();

                $city = array();
                foreach ($cities as $mall_city_id) {
                    $saveCities = new ArticleCity();
                    $saveCities->article_id = $articleId;
                    $saveCities->mall_city_id = $mall_city_id;
                    $saveCities->save();
                    $city[] = $saveCities;
                }
                $updatedArticle->cities = $city;
            });


            // save article object
            OrbitInput::post('object_news', function($objectNews) use ($updatedArticle, $articleId) {
                $deletedOldData = ArticleLinkToObject::where('article_id', '=', $articleId)
                                                     ->where('object_type', '=', 'news')
                                                     ->delete();

                $news = array();
                foreach ($objectNews as $newsId) {
                    $saveObjectNews = new ArticleLinkToObject();
                    $saveObjectNews->article_id = $articleId;
                    $saveObjectNews->object_id = $newsId;
                    $saveObjectNews->object_type = 'news';
                    $saveObjectNews->save();
                    $news[] = $saveObjectNews;
                }
                $updatedArticle->object_news = $news;
            });

            OrbitInput::post('object_promotions', function($objectPromotions) use ($updatedArticle, $articleId) {
                $deletedOldData = ArticleLinkToObject::where('article_id', '=', $articleId)
                                                     ->where('object_type', '=', 'promotion')
                                                     ->delete();

                $promotion = array();
                foreach ($objectPromotions as $promotionId) {
                    $saveObjectPromotion = new ArticleLinkToObject();
                    $saveObjectPromotion->article_id = $articleId;
                    $saveObjectPromotion->object_id = $promotionId;
                    $saveObjectPromotion->object_type = 'promotion';
                    $saveObjectPromotion->save();
                    $promotion[] = $saveObjectPromotion;
                }
                $updatedArticle->object_promotion = $promotion;
            });

            OrbitInput::post('object_coupons', function($objectCoupons) use ($updatedArticle, $articleId) {
                $deletedOldData = ArticleLinkToObject::where('article_id', '=', $articleId)
                                                     ->where('object_type', '=', 'coupon')
                                                     ->delete();

                $coupon = array();
                foreach ($objectCoupons as $couponId) {
                    $saveObjectPromotion = new ArticleLinkToObject();
                    $saveObjectPromotion->article_id = $articleId;
                    $saveObjectPromotion->object_id = $couponId;
                    $saveObjectPromotion->object_type = 'coupon';
                    $saveObjectPromotion->save();
                    $coupon[] = $saveObjectPromotion;
                }
                $updatedArticle->object_coupon = $coupon;
            });

            OrbitInput::post('object_malls', function($objectMalls) use ($updatedArticle, $articleId) {
                $deletedOldData = ArticleLinkToObject::where('article_id', '=', $articleId)
                                                     ->where('object_type', '=', 'mall')
                                                     ->delete();

                $mall = array();
                foreach ($objectMalls as $mallId) {
                    $saveObjectMall = new ArticleLinkToObject();
                    $saveObjectMall->article_id = $articleId;
                    $saveObjectMall->object_id = $mallId;
                    $saveObjectMall->object_type = 'mall';
                    $saveObjectMall->save();
                    $mall[] = $saveObjectMall;
                }
                $updatedArticle->object_mall = $mall;
            });

            OrbitInput::post('object_products', function($objectProducts) use ($updatedArticle, $articleId) {
                $deletedOldData = ArticleLinkToObject::where('article_id', '=', $articleId)
                                                     ->where('object_type', '=', 'product')
                                                     ->delete();

                $product = array();
                foreach ($objectProducts as $productId) {
                    $saveObjectProduct = new ArticleLinkToObject();
                    $saveObjectProduct->article_id = $articleId;
                    $saveObjectProduct->object_id = $productId;
                    $saveObjectProduct->object_type = 'product';
                    $saveObjectProduct->save();
                    $product[] = $saveObjectProduct;
                }
                $updatedArticle->object_product = $product;
            });

            OrbitInput::post('object_articles', function($objectArticles) use ($updatedArticle, $articleId) {
                $deletedOldData = ArticleLinkToObject::where('article_id', '=', $articleId)
                                                     ->where('object_type', '=', 'article')
                                                     ->delete();

                $articles = array();
                foreach ($objectArticles as $linkedArticleId) {
                    $saveObjectArticle = new ArticleLinkToObject();
                    $saveObjectArticle->article_id = $articleId;
                    $saveObjectArticle->object_id = $linkedArticleId;
                    $saveObjectArticle->object_type = 'article';
                    $saveObjectArticle->save();
                    $articles[] = $saveObjectArticle;
                }
                $updatedArticle->object_article = $articles;
            });

            OrbitInput::post('object_partners', function($objectPartners) use ($updatedArticle, $articleId) {
                $deletedOldData = ArticleLinkToObject::where('article_id', '=', $articleId)
                                                     ->where('object_type', '=', 'partner')
                                                     ->delete();

                $partners = array();
                foreach ($objectPartners as $linkedPartnerId) {
                    $saveObjectPartner = new ArticleLinkToObject();
                    $saveObjectPartner->article_id = $articleId;
                    $saveObjectPartner->object_id = $linkedPartnerId;
                    $saveObjectPartner->object_type = 'partner';
                    $saveObjectPartner->save();
                    $partners[] = $saveObjectPartner;
                }
                $updatedArticle->object_partner = $partners;
            });

            OrbitInput::post('object_merchants', function($objectMerchants) use ($updatedArticle, $articleId, $prefix, $countryId) {
                $deletedOldData = ArticleLinkToObject::where('article_id', '=', $articleId)
                                                     ->where('object_type', '=', 'merchant')
                                                     ->delete();

                $merchant = array();
                foreach ($objectMerchants as $merchantName) {
                    $store = Tenant::select('merchants.merchant_id','merchants.name')
                                    ->join(DB::raw("(
                                        select merchant_id, name, status, parent_id, city,
                                               province, country_id, address_line1, operating_hours
                                        from {$prefix}merchants
                                        where status = 'active'
                                            and object_type = 'mall'
                                        ) as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')

                                    ->whereRaw("{$prefix}merchants.status = 'active'")
                                    ->whereRaw("oms.status = 'active'")
                                    ->where('merchants.name', '=', $merchantName)
                                    ->whereRaw("oms.country_id = '{$countryId}'")
                                    ->orderBy('merchants.created_at', 'asc')
                                    ->first();


                    if (! empty($store)) {
                        $saveObjectMerchant = new ArticleLinkToObject();
                        $saveObjectMerchant->article_id = $articleId;
                        $saveObjectMerchant->object_id = $store->merchant_id;
                        $saveObjectMerchant->object_type = 'merchant';
                        $saveObjectMerchant->save();
                        $merchant[] = $saveObjectMerchant;
                    }
                }
                $updatedArticle->object_merchant = $merchant;
            });

            OrbitInput::post('categories', function($categories) use ($updatedArticle, $articleId) {
                $deletedOldData = ArticleLinkToObject::where('article_id', '=', $articleId)
                                                     ->where('object_type', '=', 'category')
                                                     ->delete();

                $category = array();
                foreach ($categories as $categoryId) {
                    $saveObjectCategories = new ArticleLinkToObject();
                    $saveObjectCategories->article_id = $articleId;
                    $saveObjectCategories->object_id = $categoryId;
                    $saveObjectCategories->object_type = 'category';
                    $saveObjectCategories->save();
                    $category[] = $saveObjectCategories;
                }
                $updatedArticle->category = $category;
            });


            // save video
            OrbitInput::post('videos', function($videos) use ($updatedArticle, $articleId) {
                $deletedOldData = ArticleVideo::where('article_id', '=', $articleId)->delete();

                $video = array();
                foreach ($videos as $keyVid => $youtubeVideoId) {
                    $counter = $keyVid + 1;
                    $saveVideo = new ArticleVideo();
                    $saveVideo->article_id = $articleId;
                    $saveVideo->video_id = $youtubeVideoId;
                    $saveVideo->tag_name = 'video_' . $counter;
                    $saveVideo->save();
                    $video[] = $saveVideo;
                }

                $updatedArticle->videos = $video;
            });

            // Remove all key in Redis articles
            if (Config::get('orbit.cache.ng_redis_enabled', FALSE)) {
                $redis = Cache::getRedis();
                $keyName = array('article','home');
                foreach ($keyName as $value) {
                    $keys = $redis->keys("*$value*");
                    if (! empty($keys)) {
                        foreach ($keys as $key) {
                            $redis->del($key);
                        }
                    }
                }
            }

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

}
