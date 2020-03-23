<?php namespace Orbit\Controller\API\v1\Pub\Product;
/**
 * Helpers for specific Product Namespace
 *
 */
use OrbitShop\API\v1\OrbitShopAPI;
use Validator;
use Category;
use App;
use Language;
use Lang;
use Product;
use ProductLinkToObject;
use BaseStore;

class ProductHelper
{
    /**
     * Static method to instantiate the class.
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Custom validator used in Orbit\Controller\API\v1\Pub\Product namespace
     *
     */
    public function productCustomValidator()
    {
        // Check existing article name
        Validator::extend('orbit.exist.store_id', function ($attribute, $value, $parameters) {
            $product = BaseStore::where('base_store_id', '=', $value)
                            ->first();

            if (empty($product)) {
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
    }
}