<?php namespace Orbit\Helper\Util;
/**
 * Helpert to generate landing page NG url
 *
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 */

class LandingPageUrlGenerator
{
    /**
     * @var string eventUrl
     */
    protected $eventUrl = '/events/%s/%s';

    /**
     * @var string promotionUrl
     */
    protected $promotionUrl = '/promotions/%s/%s';

    /**
     * @var string couponUrl
     */
    protected $couponUrl = '/coupons/%s/%s';

    /**
     * @var string mallUrl
     */
    protected $mallUrl = '/malls/%s/%s';

    /**
     * @var string storeUrl
     */
    protected $storeUrl = '/stores/%s/%s';

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
     * @param array $config
     * @return url()
     */
    public static function create()
    {
        return new Static();
    }

    /**
     * @param string $objectType
     * @param string $objectId
     * @param string $objectName
     * @return url
     */
    public function generateUrl($objectType, $objectId, $objectName) {

        if ($objectType === 'event' || $objectType === 'news') {
            $objectType = 'event';
        }

        $url = '';

        switch ($objectType) {
            case 'event':
                $url = printf($this->eventUrl, $objectId, Str::slug($objectName));
                break;

            case 'promotion':
                $url = printf($this->promotionUrl, $objectId, Str::slug($objectName));
                break;

            case 'coupon':
                $url = printf($this->couponUrl, $objectId, Str::slug($objectName));
                break;

            case 'mall':
                $url = printf($this->mallUrl, $objectId, Str::slug($objectName));
                break;

            case 'store':
                $url = printf($this->storeUrl, $objectId, Str::slug($objectName));
                break;
        }

        return $url;
    }
}