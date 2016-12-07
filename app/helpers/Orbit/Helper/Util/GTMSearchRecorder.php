<?php namespace Orbit\Helper\Util;

/**
 * Save GTM search activity
 *
 * @author Ahmad <ahmad@dominopos.com>
 */

use Activity;
use Category;
use Mall;
use OrbitShop\API\v1\Helper\Input as OrbitInput;

class GTMSearchRecorder
{
    protected $type = 'search';

    protected $name = 'gtm_search';

    protected $nameLong = 'GTM Search';

    protected $moduleName = 'Search';

    protected $displayName = '';

    protected $notes = '';

    /**
     * Constructor config:
     * [
     *     'displayName', // string, eg: 'Store', 'News', etc
     *     'keywords', // string
     *     'categories', // string, category_id
     *     'location', // string, location name, eg: 'Denpasar'
     *     'sortBy', // string,
     * ]
     *
     * @param array $config
     * @return void
     */
    public function __construct($parameters = [])
    {
        $this->displayName = isset($parameters['displayName']) ? $parameters['displayName'] : $this->displayName;
        $keywords = isset($parameters['keywords']) ? $parameters['keywords'] : NULL;
        $categories = isset($parameters['categories']) ? $parameters['categories'] : NULL;
        $location = isset($parameters['location']) ? $parameters['location'] : 'All Location';
        $sortBy = isset($parameters['sortBy']) ? $parameters['sortBy'] : NULL;
        $partner = isset($parameters['partner']) ? $parameters['partner'] : NULL;

        $list_category = array();

        if (! empty($categories)) {
            if (in_array("mall", $categories)) {
                $disp = $this->displayName;
                if (strtolower($this->displayName) === 'news') {
                    $disp = 'Events';
                }
                $list_category[] = 'Mall ' . $disp;

                $key = array_search("mall", $categories);
                unset($categories[$key]);
            }

            if (! empty($categories)) {
                $category_name = Category::select("category_name")->whereIn('category_id', $categories)->get();
                if (is_object($category_name)) {
                    foreach($category_name as $val) {
                        $list_category[] = $val->category_name;
                    }
                }
            }
        } elseif ($this->displayName != 'Mall') {
            $list_category[] = 'All Category';
        }

        $partner_name = NULL;
        if (! empty($partner)) {
            $partners = Partner::select("partner_name")->where("partner_id", $partner)->first();
            if (is_object($partners)) {
                $partner_name = $partners->partner_name;
            }
        }

        $notes = array(
                'keywords' => $keywords,
                'categories' => $list_category,
                'location' => $location,
                'sortBy' => $sortBy,
                'partner' => $partner_name
            );
        $this->notes = json_encode($notes);
    }

    /**
     * @param array $parameters
     * @return GTMSearchRecorder
     */
    public static function create($parameters=[])
    {
        return new static($parameters);
    }

    /**
     * Save activity
     *
     * @param User $user
     * @return void
     */
    public function saveActivity($user)
    {
        if (is_object($user)) {
            $mall = null;
            $mallId = OrbitInput::get('mall_id', NULL);
            if (! empty($mallId)) {
                $mall = Mall::where('merchant_id', '=', $mallId)->first();
            }

            $activity = Activity::mobileci()
                ->setActivityType($this->type)
                ->setUser($user)
                ->setActivityName($this->name)
                ->setActivityNameLong($this->nameLong)
                ->setObjectDisplayName($this->displayName)
                ->setModuleName($this->moduleName)
                ->setLocation($mall)
                ->setNotes($this->notes)
                ->responseOK()
                ->save();
        }
    }
}