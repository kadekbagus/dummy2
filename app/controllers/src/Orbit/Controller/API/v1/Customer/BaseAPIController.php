<?php namespace Orbit\Controller\API\v1\Customer;
/**
 * @author Ahmad <ahmad@dominopos.com>
 * @desc Base controller used for Mobile CI Angular
 */
use OrbitShop\API\v1\ControllerAPI;

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
}
