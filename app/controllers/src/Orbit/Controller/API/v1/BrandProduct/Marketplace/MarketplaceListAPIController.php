<?php namespace Orbit\Controller\API\v1\BrandProduct\Marketplace;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\PaginationNumber;

use App;
use Marketplace;
use Validator;
use Lang;
use DB;
use stdclass;
use Config;

class MarketplaceListAPIController extends ControllerAPI
{
    protected $allowedRoles = ['product manager'];

    /**
     * GET Search / list Marketplace
     *
     * @author Ahmad <ahmad@dominopos.com>
     */
    public function getSearchMarketplace()
    {
        try {
            $httpCode = 200;

            $user = App::make('currentUser');
            $userId = $user->bpp_user_id;
            $brandId = $user->base_merchant_id;

            $sortBy = OrbitInput::get('sortby');
            $status = OrbitInput::get('status');

            $validator = Validator::make(
                array(
                    'sortby' => $sortBy,
                    'status' => $status,
                ),
                array(
                    'sortby' => 'in:name,website_url,country_name,status,created_at,updated_at',
                    'status' => 'in:active,inactive',
                ),
                array(
                    'sortby.in' => 'The sort by argument you specified is not valid, the valid values are: name, website_url, country_name, status',
                    'status.in' => 'The sort by argument you specified is not valid, the valid values are: active, inactive',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $marketplace = Marketplace::select(DB::raw("
                                    {$prefix}marketplaces.marketplace_id,
                                    {$prefix}marketplaces.name,
                                    {$prefix}marketplaces.website_url,
                                    {$prefix}countries.name as country_name,
                                    {$prefix}marketplaces.status,
                                    {$prefix}marketplaces.created_at,
                                    {$prefix}marketplaces.updated_at"
                                ))
                                ->join('countries', 'marketplaces.country_id', '=', 'countries.country_id');

            OrbitInput::get('marketplace_id', function($marketplaceId) use ($marketplace)
            {
                $marketplace->where('marketplaces.marketplace_id', $marketplaceId);
            });

            OrbitInput::get('name_like', function($name) use ($marketplace)
            {
                $marketplace->where('marketplaces.name', 'like', "%$name%");
            });

            OrbitInput::get('status', function($status) use ($marketplace)
            {
                $marketplace->where('marketplaces.status', $status);
            });

            OrbitInput::get('country_id', function($country_id) use ($marketplace)
            {
                $marketplace->where('marketplaces.country_id', $country_id);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_marketplace = clone $marketplace;

            $take = PaginationNumber::parseTakeFromGet('merchant');
            $marketplace->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $marketplace->skip($skip);

            // Default sort by
            $sortBy = 'name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'name' => 'marketplaces.name',
                    'website_url' => 'website_url',
                    'country_name' => 'country_name',
                    'status' => 'marketplaces.status',
                    'created_at' => 'marketplaces.created_at',
                    'updated_at' => 'marketplaces.updated_at',
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
            $marketplace->orderBy($sortBy, $sortMode);

            $totalItems = RecordCounter::create($_marketplace)->count();
            $listOfItems = $marketplace->get();

            $data = new stdclass();
            $data->total_records = $totalItems;
            $data->returned_records = count($listOfItems);
            $data->records = $listOfItems;

            if ($totalItems === 0) {
                $data->records = NULL;
                $this->response->message = "There is no marketplace that matched your search criteria";
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