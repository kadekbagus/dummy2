<?php namespace Orbit\Helper\Util;
/**
 * Helpert to generate landing page NG url
 *
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 */

use Str;
use Mall;
use Tenant;
use NewsMerchant;
use PromotionRetailer;
use DB;

class LandingPageUrlGenerator
{
    /**
     * @var string objectId
     */
    protected $objectId = '';

    /**
     * @var string objectType
     */
    protected $objectType = '';

    /**
     * @var string objectName
     */
    protected $objectName = '';

    /**
     * @var string eventUrl
     */
    protected $eventUrl = '/events/id/name-slug';

    /**
     * @var string promotionUrl
     */
    protected $promotionUrl = '/promotions/id/name-slug';

    /**
     * @var string couponUrl
     */
    protected $couponUrl = '/coupons/id/name-slug';

    /**
     * @var string mallUrl
     */
    protected $mallUrl = '/malls/id/name-slug';

    /**
     * @var string storeUrl
     */
    protected $storeUrl = '/stores/id/name-slug';

    /**
     * @var string storeUrl
     */
    protected $promotionalEventUrl = '/promotional-events/id/name-slug';

    /**
     * @var string pulsa url
     */
    protected $pulsaUrl = '/pulsa?country=Indonesia';

    /**
     * Set the eventUrl
     *
     * @param string $eventUrl
     * @return MongoDB\Client
     */
    public function setEventUrl($eventUrl)
    {
        $this->eventUrl = $eventUrl;

        return $this;
    }

    /**
     * Set the promotionUrl
     *
     * @param string $promotionUrl
     * @return MongoDB\Client
     */
    public function setPromotionUrl($promotionUrl)
    {
        $this->promotionUrl = $promotionUrl;

        return $this;
    }

    /**
     * Set the couponUrl
     *
     * @param string $couponUrl
     * @return MongoDB\Client
     */
    public function setCouponUrl($couponUrl)
    {
        $this->couponUrl = $couponUrl;

        return $this;
    }

    /**
     * Set the mallUrl
     *
     * @param string $mallUrl
     * @return MongoDB\Client
     */
    public function setMallUrl($mallUrl)
    {
        $this->mallUrl = $mallUrl;

        return $this;
    }

    /**
     * Set the storeUrl
     *
     * @param string $storeUrl
     * @return MongoDB\Client
     */
    public function setStoreUrl($storeUrl)
    {
        $this->storeUrl = $storeUrl;

        return $this;
    }

    /**
     * @return void
     */
    public function __construct($objectType, $objectId, $objectName)
    {
        if ($objectType == '' || $objectId == '' || $objectName == '') {
            return;
        }

        $this->objectType = $objectType;
        $this->objectId = $objectId;
        $this->objectName = $objectName;
    }

    /**
     * @param string $objectType
     * @param string $objectId
     * @param string $objectName
     *
     * @return url()
     */
    public static function create($objectType='', $objectId='', $objectName='')
    {
        return new Static($objectType, $objectId, $objectName);
    }

    /**
     * @return url
     */
    public function generateUrl($showCountry=false) {

        if ($this->objectType === 'event' || $this->objectType === 'news') {
            $this->objectType = 'event';
        }

        $url = '';

        switch ($this->objectType) {
            case 'event':
                $url = str_replace(['id', 'name-slug'], [$this->objectId, Str::slug($this->objectName)], $this->eventUrl);
                break;

            case 'promotion':
                $url = str_replace(['id', 'name-slug'], [$this->objectId, Str::slug($this->objectName)], $this->promotionUrl);
                break;

            case 'coupon':
                $url = str_replace(['id', 'name-slug'], [$this->objectId, Str::slug($this->objectName)], $this->couponUrl);
                break;

            case 'mall':
                $url = str_replace(['id', 'name-slug'], [$this->objectId, Str::slug($this->objectName)], $this->mallUrl);
                break;

            case 'store':
                $url = str_replace(['id', 'name-slug'], [$this->objectId, Str::slug($this->objectName)], $this->storeUrl);
                break;
            case 'promotional-event':
                $url = str_replace(['id', 'name-slug'], [$this->objectId, Str::slug($this->objectName)], $this->promotionalEventUrl);
                break;
            case 'pulsa':
                $url = $this->pulsaUrl;
                break;
        }

        if ($showCountry) {
            $country = $this->getCountry($this->objectType, $this->objectId);
            $url = $url . '?country=' . $country;
        }

        return $url;
    }

    public function getCountry($objectType, $objectId) {
        $country = null;

        switch ($objectType) {
            case 'coupon':
                $coupon = PromotionRetailer::select(DB::raw("malls.country"))
                                            ->join('merchants', 'merchants.merchant_id', '=', 'promotion_retailer.retailer_id')
                                            ->join('merchants as malls', function ($q) {
                                                $q->on('merchants.parent_id', '=', DB::raw("malls.merchant_id"));
                                            })
                                            ->where('promotion_retailer.promotion_id', '=', $objectId)
                                            ->first();
                $country = ($coupon) ? $coupon->country : null;
                break;

            case 'mall':
                $mall = Mall::select('country')->where('merchant_id', '=', $objectId)->first();
                $country = ($mall) ? $mall->country : null;
                break;

            case 'store':
                $store = Tenant::select(DB::raw("malls.country"))
                                ->join('merchants as malls', function ($q) {
                                    $q->on('merchants.parent_id', '=', DB::raw("malls.merchant_id"));
                                })
                                ->where('merchants.merchant_id', '=', $objectId)
                                ->first();
                $country = ($store) ? $store->country : null;
                break;

            default;
                $news = NewsMerchant::select(DB::raw("malls.country"))
                                    ->join('merchants', 'merchants.merchant_id', '=', 'news_merchant.merchant_id')
                                    ->join('merchants as malls', function ($q) {
                                        $q->on('merchants.parent_id', '=', DB::raw("malls.merchant_id"));
                                    })
                                    ->where('news_merchant.news_id', '=', $objectId)
                                    ->first();
                $country = ($news) ? $news->country : null;
                break;
        }

        return $country;
    }
}
