<?php namespace Orbit\Controller\API\v1\Customer;
/**
 * @author Ahmad <ahmad@dominopos.com>
 * @desc Base controller used for Mobile CI Angular
 */
use OrbitShop\API\v1\ControllerAPI;
use Config;
use URL;
use Validator;
use Setting;
use DB;

class BaseAPIController extends ControllerAPI
{
    protected $user = NULL;

    /**
     * Calculate the Age
     *
     * @author Firmansyah <firmansyah@myorbit.com>
     * @param string $birth_date format date : YYYY-MM-DD
     * @return string
     */
    public function calculateAge($birth_date)
    {
        $age = date_diff(date_create($birth_date), date_create('today'))->y;

        if ($birth_date === null) {
            return null;
        }

        return $age;
    }

    // get the url for Facebook Share dummy page
    protected function getFBShareDummyPage($type, $id, $lang = null) {
        $oldRouteSessionConfigValue = Config::get('orbit.session.availability.query_string');
        Config::set('orbit.session.availability.query_string', false);

        $url = '';
        switch ($type) {
            case 'tenant':
                $url = URL::route('share-tenant', ['id' => $id, 'lang' => $lang]);
                break;
            case 'promotion':
                $url = URL::route('share-promotion', ['id' => $id, 'lang' => $lang]);
                break;
            case 'news':
                $url = URL::route('share-news', ['id' => $id, 'lang' => $lang]);
                break;
            case 'coupon':
                $url = URL::route('share-coupon', ['id' => $id, 'lang' => $lang]);
                break;
            case 'lucky-draw':
                $url = URL::route('share-lucky-draw', ['id' => $id, 'lang' => $lang]);
                break;
            case 'home':
                $url = URL::route('share-home');
                break;

            default:
                $url = '';
                break;
        }
        Config::set('orbit.session.availability.query_string', $oldRouteSessionConfigValue);

        return $url;
    }

    protected function quoteStr($str)
    {
        return DB::connection()->getPdo()->quote($str);
    }
}
