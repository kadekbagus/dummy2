<?php namespace Orbit\Controller\API\v1\Pub\Store;
/**
 * Helpers for specific LuckyDraw Namespace
 *
 */
use Validator;
use Language;
use Tenant;

class StoreHelper
{
    protected $valid_language = NULL;
    protected $store = NULL;
    protected $withoutScore = FALSE;

    /**
     * Static method to instantiate the class.
     */
    public static function create()
    {
        return new static();
    }

    public function registerCustomValidation() {
        // Check language is exists
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $lang_name = $value;

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang_name)
                            ->first();

            if (empty($language)) {
                return FALSE;
            }

            $this->valid_language = $language;
            return TRUE;
        });

        // Check store is exists
        Validator::extend('orbit.empty.tenant', function ($attribute, $value, $parameters) {
            $store = Tenant::where('status', 'active')
                            ->where('merchant_id', $value)
                            ->first();

            if (empty($store)) {
                return FALSE;
            }

            $this->store = $store;
            return TRUE;
        });
    }

    public function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    public function getLocation($prefix, $location, $query, $ul, $distance, $userLocationCookieName)
    {
        $query = $query->join('merchants as mp', function($q) use ($prefix) {
                                $q->on(DB::raw("mp.merchant_id"), '=', DB::raw("{$prefix}merchants.parent_id"));
                                $q->on(DB::raw("mp.object_type"), '=', DB::raw("'mall'"));
                                $q->on(DB::raw("{$prefix}merchants.status"), '=', DB::raw("'active'"));
                            });

                if ($location === 'mylocation') {
                    if (! empty($ul)) {
                        $position = explode("|", $ul);
                        $lon = $position[0];
                        $lat = $position[1];
                    } else {
                        // get lon lat from cookie
                        $userLocationCookieArray = isset($_COOKIE[$userLocationCookieName]) ? explode('|', $_COOKIE[$userLocationCookieName]) : NULL;
                        if (! is_null($userLocationCookieArray) && isset($userLocationCookieArray[0]) && isset($userLocationCookieArray[1])) {
                            $lon = $userLocationCookieArray[0];
                            $lat = $userLocationCookieArray[1];
                        }
                    }

                    if (!empty($lon) && !empty($lat)) {
                        $query = $query->addSelect(DB::raw("6371 * acos( cos( radians({$lat}) ) * cos( radians( x({$prefix}merchant_geofences.position) ) ) * cos( radians( y({$prefix}merchant_geofences.position) ) - radians({$lon}) ) + sin( radians({$lat}) ) * sin( radians( x({$prefix}merchant_geofences.position) ) ) ) AS distance"))
                                        ->join('merchant_geofences', function ($q) use($prefix) {
                                                $q->on('merchant_geofences.merchant_id', '=', DB::raw("CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN {$prefix}merchants.parent_id ELSE {$prefix}merchants.merchant_id END"));
                                        });
                    }
                    $query = $query->havingRaw("distance <= {$distance}");
                } else {
                    $query = $query->where(DB::raw("(CASE WHEN {$prefix}merchants.object_type = 'tenant' THEN mp.city ELSE {$prefix}merchants.city END)"), $location);
                }

        return $query;
    }

    /**
     * Force $withScore value to FALSE, ignoring previously set value
     * @param $bool boolean
     */
    public function setWithOutScore()
    {
        $this->withoutScore = TRUE;

        return $this;
    }
}
