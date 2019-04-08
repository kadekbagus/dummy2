<?php namespace Orbit\Controller\API\v1\Pub\Pulsa;
/**
 * Helpers for specific Pulsa Namespace
 *
 */
use OrbitShop\API\v1\OrbitShopAPI;
use Validator;
use App;
use Language;
use Lang;
use Pulsa;

//TODO: refactor as many helpers using same validator

class PulsaValidator
{
    protected $valid_language = NULL;

    /**
     * Static method to instantiate the class.
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Custom validator used in Orbit\Controller\API\v1\Article namespace
     *
     */
    public function registerValidator()
    {

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

    }

    public function getValidLanguage()
    {
        return $this->valid_language;
    }


}
