<?php namespace Orbit\Controller\API\v1\Article;
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
use Article;

class ArticleHelper
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
    public function articleCustomValidator()
    {
        // Check existing article title
        Validator::extend('orbit.exist.title', function ($attribute, $value, $parameters) {
            $article = Article::where('title', '=', $value)
                            ->first();

            if (! empty($article)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check existing article title
        Validator::extend('orbit.exist.slug', function ($attribute, $value, $parameters) {
            $article = Article::where('slug', '=', $value)
                            ->first();

            if (! empty($article)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check existing article title
        Validator::extend('orbit.exist.title_not_me', function ($attribute, $value, $parameters) {
            $articleId = $parameters[0];

            $article = Article::where('title', '=', $value)
                            ->where('article_id', '!=', $articleId)
                            ->first();

            if (! empty($article)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check existing article slug
        Validator::extend('orbit.exist.slug_not_me', function ($attribute, $value, $parameters) {
            $articleId = $parameters[0];

            $article = Article::where('slug', '=', $value)
                            ->where('article_id', '!=', $articleId)
                            ->first();

            if (! empty($article)) {
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