<?php namespace Orbit\Controller\API\v1\Pub\LuckyDraw;
/**
 * Helpers for specific LuckyDraw Namespace
 *
 */
use Validator;
use Language;
use LuckyDraw;

class LuckyDrawHelper
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
     * Custom validator used in Orbit\Controller\API\v1\Pub\LuckyDraw namespace
     *
     */
    public function luckyDrawCustomValidator() {
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

        // Check the existance of lucky_draw id
        Validator::extend('orbit.empty.lucky_draw', function ($attribute, $value, $parameters) {
            $lucky_draw = LuckyDraw::excludeDeleted()
                                   ->where('lucky_draw_id', $value)
                                   ->first();

            if (empty($lucky_draw)) {
                return FALSE;
            }

            return TRUE;
        });
    }

    public function getValidLanguage()
    {
        return $this->valid_language;
    }
}
