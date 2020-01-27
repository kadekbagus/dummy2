<?php namespace Orbit\Controller\API\v1\Product\Game;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Validator;
use Orbit\Controller\API\v1\Product\Game\GameHelper;

use Lang;
use Config;
use Category;
use Event;
use Game;

class GameUpdateAPIController extends ControllerAPI
{
    protected $productRoles = ['product manager'];

    /**
     * Update game on product portal.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function postUpdateGame()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.updategame.postupdategame.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.updategame.postupdategame.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.updategame.postupdategame.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->productRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.updategame.postupdategame.after.authz', array($this, $user));

            $gameHelper = GameHelper::create();
            $gameHelper->gameCustomValidator();

            $game_id = OrbitInput::post('game_id');
            $slug = OrbitInput::post('slug');
            $status = OrbitInput::post('status');

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'game_id'           => $game_id,
                    'status'            => $status,
                    'slug'              => $slug,
                ),
                array(
                    'game_id'           => 'required',
                    'status'            => 'in:active,inactive',
                    'slug'              => 'orbit.exist.slug_but_me:' . $game_id,
                ),
                array(
                    'game_id.required'             => 'Game id is required',
                    'slug.orbit.exist.slug_but_me' => 'Slug already used',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.updategame.postupdategame.after.validation', array($this, $validator));

            $updatedGame = Game::where('game_id', $game_id)->first();

            OrbitInput::post('game_name', function($game_name) use ($updatedGame) {
                $updatedGame->game_name = $game_name;
            });

            OrbitInput::post('slug', function($slug) use ($updatedGame) {
                $updatedGame->slug = $slug;
            });

            OrbitInput::post('description', function($description) use ($updatedGame) {
                $updatedGame->description = $description;
            });

            OrbitInput::post('status', function($status) use ($updatedGame) {
                $updatedGame->status = $status;
            });

            OrbitInput::post('seo_text', function($seo_text) use ($updatedGame) {
                $updatedGame->seo_text = $seo_text;
            });

            Event::fire('orbit.updategame.postupdategame.before.save', array($this, $updatedGame));

            $updatedGame->touch();
            $updatedGame->save();

            Event::fire('orbit.updategame.postupdategame.after.save', array($this, $updatedGame));

            $this->response->data = $updatedGame;

            // Commit the changes
            $this->commit();

          Event::fire('orbit.updategame.postupdategame.after.commit', array($this, $updatedGame));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.updategame.postupdategame.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.updategame.postupdategame.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.updategame.postupdategame.query.error', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;

            // Rollback the changes
            $this->rollBack();
        } catch (\Exception $e) {
            Event::fire('orbit.updategame.postupdategame.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }
}
