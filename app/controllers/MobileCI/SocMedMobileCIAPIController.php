<?php namespace MobileCI;

/**
 * An API controller for managing Social Media Share Page.
 */
use Log;
use SocMed\Facebook;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \View;
use \Lang;
use \Language;
use \MerchantLanguage;
use \Validator;
use \Config;
use \Retailer;
use \Product;
use \Promotion;
use \Coupon;
use \News;
use \CartCoupon;
use \IssuedCoupon;
use Carbon\Carbon as Carbon;
use \stdclass;
use \Exception;
use \DB;
use \Activity;
use \LuckyDraw;
use URL;
use PDO;
use Response;
use OrbitShop\API\v1\Helper\Generator;
use Event;
use \Mall;
use \Tenant;
use Redirect;

class SocMedMobileCIAPIController extends BaseCIController
{
    /**
     * GET - FB Tenant Share dummy page
     *
     * @param string    `id`          (required)
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getTenantDetailView()
    {
        $id = OrbitInput::get('id');
        $mall = $this->getRetailerInfo();

        $tenant = Tenant::with(
                    'media',
                    'mediaLogoOrig'
                )
                ->active()
                ->where('merchant_id', $id)
                ->firstOrFail();

        $data = new stdclass();
        $data->url = URL::route('share-tenant', array('id' => $tenant->merchant_id));
        $data->title = $tenant->name;
        $data->description = $tenant->description;
        $data->mall = $mall;

        if (count($tenant->mediaLogoOrig) > 0) {
            if (! empty($tenant->mediaLogoOrig[0]->path)) {
                $data->image_dimension = $this->getImageDimension($tenant->mediaLogoOrig[0]->path);
                $data->image_url = $tenant->mediaLogoOrig[0]->path;
            } else {
                $data->image_dimension = NULL;
                $data->image_url = NULL;
            }
        } else {
            $data->image_dimension = NULL;
            $data->image_url = NULL;
        }

        return View::make('mobile-ci.templates.fb-sharer', compact('data'));
    }

    /**
     * GET - FB Tenant Share dummy page
     *
     * @param string    `id`          (required)
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getHomeView()
    {
        $id = OrbitInput::get('id');
        $mall = $this->getRetailerInfo('mediaLogoOrig');

        $data = new stdclass();
        $data->url = URL::route('share-home');
        $data->title = $mall->name;
        $data->description = $mall->description;
        $data->mall = $mall;

        if (count($mall->mediaLogoOrig) > 0) {
            if (! empty($mall->mediaLogoOrig[0]->path)) {
                $data->image_dimension = $this->getImageDimension($mall->mediaLogoOrig[0]->path);
                $data->image_url = $mall->mediaLogoOrig[0]->path;
            } else {
                $data->image_dimension = NULL;
                $data->image_url = NULL;
            }
        } else {
            $data->image_dimension = NULL;
            $data->image_url = NULL;
        }

        return View::make('mobile-ci.templates.fb-sharer', compact('data'));
    }

    /**
     * GET - FB Promotion Share dummy page
     *
     * @param string    `id`          (required)
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getPromotionDetailView()
    {       
        $id = OrbitInput::get('id');
        $mall = $this->getRetailerInfo();

        $promotion = News::with('media')
                ->active()
                ->where('object_type', 'promotion')
                ->where('news_id', $id)
                ->firstOrFail();

        $data = new stdclass();
        $data->url = URL::route('share-promotion', array('id' => $promotion->news_id));
        $data->title = $promotion->news_name;
        $data->description = $promotion->description;
        $data->mall = $mall;

        $promotionTranslation = \NewsTranslation::excludeDeleted()
            ->where('news_id', $promotion->news_id)
            ->first();

        if (! empty($promotionTranslation)) {
            $media = $promotionTranslation->find($promotionTranslation->news_translation_id)
                ->media_orig()
                ->first();

            if (isset($media->path)) {
                $promotion->image = $media->path;
            } else {
                // back to default image if in the content multilanguage not have image
                // check the system language
                $defaultLanguage = $this->getDefaultLanguage($mall);
                if ($defaultLanguage !== NULL) {
                    $contentDefaultLanguage = \NewsTranslation::excludeDeleted()
                        ->where('news_id', $promotion->news_id)
                        ->where('merchant_language_id', $defaultLanguage->language_id)
                        ->first();

                    // get default image
                    $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->news_translation_id)
                        ->media_orig()
                        ->first();

                    if (isset($mediaDefaultLanguage->path)) {
                        $promotion->image = $mediaDefaultLanguage->path;
                    }
                }
            }
        }

        if (empty($promotion->image)) {
            $data->image_url = NULL;
        } else {
            $data->image_url = $promotion->image;
        }

        $data->image_dimension = $this->getImageDimension($promotion->image);

        return View::make('mobile-ci.templates.fb-sharer', compact('data'));
    }

    /**
     * GET - FB News Share dummy page
     *
     * @param string    `id`          (required)
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getNewsDetailView()
    {       
        $id = OrbitInput::get('id');
        $mall = $this->getRetailerInfo();

        $promotion = News::with('media')
                ->active()
                ->where('object_type', 'news')
                ->where('news_id', $id)
                ->firstOrFail();

        $data = new stdclass();
        $data->url = URL::route('share-news', array('id' => $promotion->news_id));
        $data->title = $promotion->news_name;
        $data->description = $promotion->description;
        $data->mall = $mall;

        $promotionTranslation = \NewsTranslation::excludeDeleted()
            ->where('news_id', $promotion->news_id)
            ->first();

        if (! empty($promotionTranslation)) {
            $media = $promotionTranslation->find($promotionTranslation->news_translation_id)
                ->media_orig()
                ->first();

            if (isset($media->path)) {
                $promotion->image = $media->path;
            } else {
                // back to default image if in the content multilanguage not have image
                // check the system language
                $defaultLanguage = $this->getDefaultLanguage($mall);
                if ($defaultLanguage !== NULL) {
                    $contentDefaultLanguage = \NewsTranslation::excludeDeleted()
                        ->where('news_id', $promotion->news_id)
                        ->where('merchant_language_id', $defaultLanguage->language_id)
                        ->first();

                    // get default image
                    $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->news_translation_id)
                        ->media_orig()
                        ->first();

                    if (isset($mediaDefaultLanguage->path)) {
                        $promotion->image = $mediaDefaultLanguage->path;
                    }
                }
            }
        }

        if (empty($promotion->image)) {
            $data->image_url = NULL;
        } else {
            $data->image_url = $promotion->image;
        }

        $data->image_dimension = $this->getImageDimension($promotion->image);

        return View::make('mobile-ci.templates.fb-sharer', compact('data'));
    }

    /**
     * GET - FB Coupon Share dummy page
     *
     * @param string    `id`          (required)
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getCouponDetailView()
    {       
        $id = OrbitInput::get('id');
        $mall = $this->getRetailerInfo();

        $coupon = Coupon::with('media')
                ->active()
                ->where('promotion_id', $id)
                ->firstOrFail();

        $data = new stdclass();
        $data->url = URL::route('share-coupon', array('id' => $coupon->promotion_id));
        $data->title = $coupon->promotion_name;
        $data->description = $coupon->description;
        $data->mall = $mall;

        $couponTranslation = \CouponTranslation::excludeDeleted()
            ->where('promotion_id', $coupon->promotion_id)
            ->first();

        if (! empty($couponTranslation)) {
            $media = $couponTranslation->find($couponTranslation->coupon_translation_id)
                ->media_orig()
                ->first();

            if (isset($media->path)) {
                $coupon->image = $media->path;
            } else {
                // back to default image if in the content multilanguage not have image
                // check the system language
                $defaultLanguage = $this->getDefaultLanguage($mall);
                if ($defaultLanguage !== NULL) {
                    $contentDefaultLanguage = \CouponTranslation::excludeDeleted()
                        ->where('promotion_id', $coupon->promotion_id)
                        ->where('merchant_language_id', $defaultLanguage->language_id)
                        ->first();

                    // get default image
                    $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->coupon_translation_id)
                        ->media_orig()
                        ->first();

                    if (isset($mediaDefaultLanguage->path)) {
                        $coupon->image = $mediaDefaultLanguage->path;
                    }
                }
            }
        }

        if (empty($coupon->image)) {
            $data->image_url = NULL;
        } else {
            if (strpos($coupon->image, 'default_coupon.png') > 0) {
                $data->image_url = NULL;
            } else {
                $data->image_url = $coupon->image;
            }
        }

        $data->image_dimension = $this->getImageDimension($coupon->image);

        return View::make('mobile-ci.templates.fb-sharer', compact('data'));
    }

    /**
     * GET - FB Lucky Draw Share dummy page
     *
     * @param string    `id`          (required)
     *
     * @return Illuminate\View\View
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
    public function getLuckyDrawDetailView()
    {       
        $id = OrbitInput::get('id');
        $mall = $this->getRetailerInfo();

        $luckydraw = LuckyDraw::with('media')
                ->active()
                ->where('lucky_draw_id', $id)
                ->firstOrFail();

        $data = new stdclass();
        $data->url = URL::route('share-lucky-draw', array('id' => $luckydraw->lucky_draw_id));
        $data->title = $luckydraw->lucky_draw_name;
        $data->description = $luckydraw->description;
        $data->mall = $mall;

        $luckyDrawTranslation = \LuckyDrawTranslation::excludeDeleted()
            ->where('lucky_draw_id', $luckydraw->lucky_draw_id)
            ->first();

        if (! empty($luckyDrawTranslation)) {
            $media = $luckyDrawTranslation->find($luckyDrawTranslation->lucky_draw_translation_id)
                ->media_orig()
                ->first();

            if (isset($media->path)) {
                $luckydraw->image = $media->path;
            } else {
                // back to default image if in the content multilanguage not have image
                // check the system language
                $defaultLanguage = $this->getDefaultLanguage($mall);
                if ($defaultLanguage !== NULL) {
                    $contentDefaultLanguage = \LuckyDrawTranslation::excludeDeleted()
                        ->where('lucky_draw_id', $luckydraw->lucky_draw_id)
                        ->where('merchant_language_id', $defaultLanguage->merchant_language_id)
                        ->first();

                    // get default image
                    $mediaDefaultLanguage = $contentDefaultLanguage->find($contentDefaultLanguage->lucky_draw_translation_id)
                        ->media_orig()
                        ->first();

                    if (isset($mediaDefaultLanguage->path)) {
                        $luckydraw->image = $mediaDefaultLanguage->path;
                    }
                }
            }
        }

        if (empty($luckydraw->image)) {
            $data->image_url = NULL;
        } else {
            $data->image_url = $luckydraw->image;
        }

        $data->image_dimension = $this->getImageDimension($luckydraw->image);

        return View::make('mobile-ci.templates.fb-sharer', compact('data'));
    }

    private function getImageDimension($url = '') {
        try {
            if(empty($url)) {
                return NULL;
            }

            list($width, $height) = getimagesize($url);
                
            $dimension = [$width, $height];

            return $dimension;
        } catch (Exception $e) {
            return NULL;
        }
    }

    /**
     * Returns an appropriate MerchantLanguage (if any) that the user wants and the mall supports.
     *
     * @param \Mall $mall the mall
     * @return \MerchantLanguage the language or null if a matching one is not found.
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     */
    private function getDefaultLanguage($mall)
    {
        $language = \Language::where('name', '=', $mall->mobile_default_language)->first();
        if(isset($language) && count($language) > 0){
            $defaultLanguage = \MerchantLanguage::excludeDeleted()
                ->where('merchant_id', '=', $mall->merchant_id)
                ->where('language_id', '=', $language->language_id)
                ->first();

            if ($defaultLanguage !== null) {
                return $defaultLanguage;
            }
        }

        // above methods did not result in any selected language, use mall default
        return null;
    }
}
