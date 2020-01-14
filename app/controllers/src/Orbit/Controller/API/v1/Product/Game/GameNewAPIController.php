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
use Event;
use Game;

class GameNewAPIController extends ControllerAPI
{
    protected $productRoles = ['product manager'];

    /**
     * Create new game on product portal.
     *
     * @author kadek <kadek@dominopos.com>
     */
    public function postNewGame()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.newgame.postnewgame.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.newgame.postnewgame.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.newgame.postnewgame.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->productRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.newgame.postnewgame.after.authz', array($this, $user));

            $gameHelper = GameHelper::create();
            $gameHelper->gameCustomValidator();

            $game_name = OrbitInput::post('game_name');
            $slug = OrbitInput::post('slug');
            $description = OrbitInput::post('description');
            $status = OrbitInput::post('status', 'inactive');
            $seo_text = OrbitInput::post('seo_text');

            // Begin database transaction
            $this->beginTransaction();

            $validator = Validator::make(
                array(
                    'game_name'         => $game_name,
                    'slug'              => $slug,
                    'description'       => $description,
                    'status'            => $status,
                    'seo_text'          => $seo_text,
                ),
                array(
                    'game_name'         => 'required',
                    'slug'              => 'required|orbit.exist.slug',
                    'status'            => 'in:active,inactive',
                ),
                array(
                    'game_name.required'    => 'Game Name field is required',
                    'slug.orbit.exist.slug' => 'Slug already used',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.newgame.postnewgame.after.validation', array($this, $validator));

            $newGame = new Game;
            $newGame->game_name = $game_name;
            $newGame->slug = $slug;
            $newGame->description = $description;
            $newGame->status = $status;
            $newGame->seo_text = $seo_text;

            Event::fire('orbit.newgame.postnewgame.before.save', array($this, $newGame));

            $newGame->save();

            Event::fire('orbit.newgame.postnewgame.after.save', array($this, $newGame));

            $this->response->data = $newGame;

            // Commit the changes
            $this->commit();

          Event::fire('orbit.newgame.postnewgame.after.commit', array($this, $newGame));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.newgame.postnewgame.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.newgame.postnewgame.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();
        } catch (QueryException $e) {
            Event::fire('orbit.newgame.postnewgame.query.error', array($this, $e));

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
            Event::fire('orbit.newgame.postnewgame.general.exception', array($this, $e));

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
