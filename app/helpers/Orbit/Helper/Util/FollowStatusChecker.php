<?php namespace Orbit\Helper\Util;
/**
 * Helpert to check follow status
 *
 * @author Shelgi Prasetyo <shelgi@dominopos.com>
 */

use Config;
use Orbit\Helper\MongoDB\Client as MongoClient;
use BaseStore;
use BaseMerchant;
use Tenant;
use DB;
use Country;

class FollowStatusChecker
{
    /**
     * @var string userId
     */
    protected $userId = '';

    /**
     * @var string mallId
     */
    protected $mallId = '';

    /**
     * @var array post city data
     */
    protected $city = [];

    /**
     * @var array post country data
     */
    protected $country = '';

    /**
     * @var string objectId
     */
    protected $objectId = '';

    /**
     * @var string objectType
     */
    protected $objectType = '';

    /**
     * @var string merchantId
     */
    protected $merchantId = '';

    /**
     * @var string baseMerchantId
     */
    protected $baseMerchantId = '';

     /**
     * @var string mongoClient
     */
    protected $mongoClient = '';

    /**
     * @return void
     */
    public function __construct()
    {
        $mongoConfig = Config::get('database.mongodb');
        $this->mongoClient = MongoClient::create($mongoConfig);
    }

    /**
     * @return imageUrl
     */
    public static function create()
    {
        return new Static();
    }

    /**
     * Set the userId
     *
     * @param string $userId
     * @return MongoDB\Client
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Set the merchantId
     *
     * @param string $merchantId
     * @return MongoDB\Client
     */
    public function setMerchantId($merchantId)
    {
        $this->merchantId = $merchantId;

        return $this;
    }

    /**
     * Set the baseMerchantId
     *
     * @param string $baseMerchantId
     * @return MongoDB\Client
     */
    public function setBaseMerchantId($baseMerchantId)
    {
        $this->baseMerchantId = $baseMerchantId;

        return $this;
    }

    /**
     * Set the mallId
     *
     * @param string $mallId
     * @return MongoDB\Client
     */
    public function setMallId($mallId)
    {
        $this->mallId = $mallId;

        return $this;
    }

    /**
     * Set the city
     *
     * @param array $city
     * @return MongoDB\Client
     */
    public function setCity(array $city=[])
    {
        $this->city = $city;

        return $this;
    }

    /**
     * Set the country
     *
     * @param string $country
     * @return MongoDB\Client
     */
    public function setCountry($country)
    {
        $this->country = $country;

        return $this;
    }

    /**
     * Set the objectId
     *
     * @param string $objectId
     * @return MongoDB\Client
     */
    public function setObjectId($objectId)
    {
        $this->objectId = $objectId;

        return $this;
    }

    /**
     * Set the objectType
     *
     * @param string $objectType
     * @return MongoDB\Client
     */
    public function setObjectType($objectType)
    {
        $this->objectType = $objectType;

        return $this;
    }

    /**
     * Get follow status
     * return object_id
     */
    public function getFollowStatus() {
        if ($this->objectType === 'mall') {
            $queryString = [
                'object_type' => 'mall',
                'user_id'     => $this->userId
            ];

            if (! empty($this->object_id)) {
                $queryString['object_id'] = $this->object_id; // for single mall
            }

            if (! empty($this->city)) {
                $queryString['city'] = $this->city;

            }

            $response = $this->mongoClient
                            ->setQueryString($queryString)
                            ->setEndPoint('user-follows')
                            ->request('GET');

            $followMall = [];
            if (! empty($response->data->records)) {
                foreach ($response->data->records as $mall) {
                    $followMall[] = $mall->object_id;
                }
            }

            return $followMall;

        } else { // store
            $queryString = [
                'object_type' => 'store',
                'user_id'     => $this->userId
            ];

            $followStore = [];
            $followStoreAgg = [];

            // store list in mall level
            if (! empty($this->mallId)) {
                $queryString['mall_id'] = $this->mall_id;

                $response = $this->mongoClient
                                ->setQueryString($queryString)
                                ->setEndPoint('user-follows')
                                ->request('GET');

                if (! empty($response->data->records)) {
                    foreach ($response->data->records as $stores) {
                        $followStore[] = $stores->base_merchant_id;
                    }
                }

                return $followStore;
            }

            // store list in gtm level
            if (! empty($this->city)) {
                $queryString['city'] = $this->city;
            }

            $response = $this->mongoClient
                                ->setQueryString($queryString)
                                ->setEndPoint('follow-store-aggregate')
                                ->request('GET');

            if (empty($response->data->records)) {
                return $followStore;
            }

            $followBaseMerchantId = [];
            foreach ($response->data->records as $stores) {
                $followBaseMerchantId[] = $stores->_id;
                $followStoreAgg[$stores->_id] = $stores->count;
            }

            $prefix = DB::getTablePrefix();

            $tenants = Tenant::select('base_merchants.base_merchant_id', DB::raw("count({$prefix}base_merchants.base_merchant_id) as total_store"))
                            ->join(DB::raw("{$prefix}merchants as oms"), DB::raw('oms.merchant_id'), '=', 'merchants.parent_id')
                            ->leftJoin('base_stores', 'base_stores.base_store_id', '=', 'merchants.merchant_id')
                            ->join('base_merchants', 'base_merchants.base_merchant_id', '=', 'base_stores.base_merchant_id')
                            ->whereIn('base_merchants.base_merchant_id', $followBaseMerchantId)
                            ->where('merchants.status', 'active')
                            ->where(DB::raw('oms.status'), '=', 'active')
                            ->groupBy('base_merchants.base_merchant_id');

            if (! empty($this->city)) {
                $queryString['city'] = $this->city;
                $tenants = $tenants->whereIn(DB::raw('oms.city'), $this->city);
            }

            $tenants = $tenants->get();
            foreach ($tenants as $tenant) {
                if ((int) $tenant->total_store === (int) $followStoreAgg[$tenant->base_merchant_id]) {
                    $followStore[] = $tenant->base_merchant_id;
                }
            }

            return $followStore;
        }
    }
}