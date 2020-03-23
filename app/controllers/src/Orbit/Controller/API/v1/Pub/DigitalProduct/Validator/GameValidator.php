<?php

namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Validator;

use App;
use Game;

/**
 * List of custom validator related to Game
 *
 * @author Budi <budi@gotomalls.com>
 */
class GameValidator
{
    /**
     * Validate that Game with slug $slug is exists.
     *
     * @param  [type] $attributes       [description]
     * @param  [type] $digitalProductId [description]
     * @param  [type] $parameters       [description]
     * @return [type]                   [description]
     */
    public function exists($attributes, $slug, $parameters)
    {
        $game = Game::where('slug', $slug)->active()->first();

        if (! empty($game)) {
            App::instance('game', $game);
        }
        else {
            App::instance('game', null);
        }

        return ! empty($game);
    }
}
