<?php namespace Orbit\Helper\Util;
/**
 * Helpert to generate landing page NG url
 *
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 */

use Str;

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
    public function generateUrl() {

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
        }

        return $url;
    }
}