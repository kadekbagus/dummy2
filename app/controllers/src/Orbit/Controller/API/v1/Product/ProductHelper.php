<?php namespace Orbit\Controller\API\v1\Product;
/**
 * Helpers for specific Article Namespace
 *
 */
use OrbitShop\API\v1\OrbitShopAPI;
use Validator;
use Category;
use App;
use Language;
use Lang;
use Product;

class ProductHelper
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
     * Custom validator used in Orbit\Controller\API\v1\Product namespace
     *
     */
    public function productCustomValidator()
    {
        // Check existing article name
        Validator::extend('orbit.exist.name', function ($attribute, $value, $parameters) {
            $product = Product::where('name', '=', $value)
                            ->first();

            if (! empty($product)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the validity of URL
        Validator::extend('orbit.formaterror.url.web', function ($attribute, $value, $parameters) {
            $url = 'http://' . $value;

            $pattern = '@^((http:\/\/www\.)|(www\.)|(http:\/\/))[a-zA-Z0-9._-]+\.[a-zA-Z.]{2,5}$@';

            if (! preg_match($pattern, $url)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the existance of category id
        Validator::extend('orbit.empty.category', function ($attribute, $value, $parameters) {
            $category = Category::excludeDeleted()
                                ->where('category_id', $value)
                                ->first();

            if (empty($category)) {
                return FALSE;
            }

            App::instance('orbit.empty.category', $category);

            return TRUE;
        });

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