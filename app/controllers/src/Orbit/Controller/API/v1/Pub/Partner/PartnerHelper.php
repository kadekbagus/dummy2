<?php namespace Orbit\Controller\API\v1\Pub\Partner;
/**
 * Helpers for specific LuckyDraw Namespace
 *
 */
use Validator;
use Language;

class PartnerHelper
{
    protected $valid_language = NULL;

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
    }

    public function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

    public function getValidLanguage()
    {
        return $this->valid_language;
    }
}