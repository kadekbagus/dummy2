<?php namespace Orbit\Controller\API\v1\Product\Game;
/**
 * Helpers for specific Game Namespace
 *
 */
use OrbitShop\API\v1\OrbitShopAPI;
use Validator;
use App;
use Lang;
use Game;

class GameHelper
{

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
    public function gameCustomValidator()
    {
        // Check existing slug
        Validator::extend('orbit.exist.slug', function ($attribute, $value, $parameters) {
            $game = Game::where('slug', '=', $value)
                        ->first();

            if (! empty($game)) {
                return FALSE;
            }

            return TRUE;
        });

        Validator::extend('orbit.exist.slug_but_me', function ($attribute, $value, $parameters) {
            $game_id = $parameters[0];
            $game = Game::where('slug', '=', $value)
                        ->where('game_id', '!=', $game_id)
                        ->first();

            if (! empty($game)) {
                return FALSE;
            }

            return TRUE;
        });

    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}