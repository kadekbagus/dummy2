<?php namespace Orbit\Helper\Util;

/**
 * Save GTM search activity
 *
 * @author Ahmad <ahmad@dominopos.com>
 */

use Activity;
use Category;

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

        $category_name = NULL;
        if ($this->displayName != 'Mall') {
            $category_name = 'All Category';
        }

        if (! empty($categories)) {
            if ($categories === 'mall') {
                $disp = $this->displayName;
                if (strtolower($this->displayName) === 'news') {
                    $disp = 'Events';
                }
                $category_name =  'Mall ' . $disp;
            } else {
                $category = Category::where('category_id', $categories)->first();
                if (is_object($category)) {
                    $category_name = $category->category_name;
                }
            }
        }

        $notes = array(
                'keywords' => $keywords,
                'categories' => $category_name,
                'location' => $location,
                'sortBy' => $sortBy
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
            $activity = Activity::mobileci()
                ->setActivityType($this->type)
                ->setUser($user)
                ->setActivityName($this->name)
                ->setActivityNameLong($this->nameLong)
                ->setObjectDisplayName($this->displayName)
                ->setModuleName($this->moduleName)
                ->setNotes($this->notes)
                ->responseOK()
                ->save();
        }
    }
}