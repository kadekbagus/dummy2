<?php namespace Orbit\Controller\API\v1\Product\Game;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\PaginationNumber;

use Game;
use Validator;
use Lang;
use DB;
use stdclass;
use Config;

class GameListAPIController extends ControllerAPI
{
    protected $allowedRoles = ['product manager', 'article publisher', 'article writer'];

    /**
     * GET Search / list game
     *
     * @author Ahmad <ahmad@dominopos.com>
     */
    public function getSearchGame()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->allowedRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $sortBy = OrbitInput::get('sortby');
            $status = OrbitInput::get('status');

            $validator = Validator::make(
                array(
                    'sortby' => $sortBy,
                    'status' => $status,
                ),
                array(
                    'sortby' => 'in:game_name,status,created_at,updated_at',
                    'status' => 'in:active,inactive',
                ),
                array(
                    'sortby.in' => 'The sort by argument you specified is not valid, the valid values are: game_name, status',
                    'status.in' => 'The sort by argument you specified is not valid, the valid values are: active, inactive',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $game = Game::select(DB::raw("
                                    {$prefix}games.game_id,
                                    {$prefix}games.game_name,
                                    {$prefix}games.status,
                                    {$prefix}games.created_at,
                                    {$prefix}games.updated_at"
                                ));

            OrbitInput::get('game_id', function($game_id) use ($game)
            {
                $game->where('game_id', $game_id);
            });

            OrbitInput::get('game_name_like', function($name) use ($game)
            {
                $game->where('game_name', 'like', "%$name%");
            });

            OrbitInput::get('status', function($status) use ($game)
            {
                $game->where('status', $status);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_product = clone $game;

            $take = PaginationNumber::parseTakeFromGet('merchant');
            $game->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $game->skip($skip);

            // Default sort by
            $sortBy = 'game_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'game_name'  => 'games.game_name',
                    'status'     => 'games.status',
                    'created_at' => 'games.created_at',
                    'updated_at' => 'games.updated_at',
                );

                if (array_key_exists($_sortBy, $sortByMapping)) {
                    $sortBy = $sortByMapping[$_sortBy];
                }
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $game->orderBy($sortBy, $sortMode);

            $totalItems = RecordCounter::create($_product)->count();
            $listOfItems = $game->get();

            $data = new stdclass();
            $data->total_records = $totalItems;
            $data->returned_records = count($listOfItems);
            $data->records = $listOfItems;

            if ($totalItems === 0) {
                $data->records = NULL;
                $this->response->message = "There is no game that matched your search criteria";
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {

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
        } catch (Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);

        return $output;
    }
}