<?php namespace Orbit\Controller\API\v1\Merchant\Merchant;

use OrbitShop\API\v1\ResponseProvider;
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use Orbit\Helper\Util\PaginationNumber;
use BaseMerchant;
use Partner;
use Validator;
use Lang;
use DB;
use Config;
use stdclass;
use Orbit\Controller\API\v1\Merchant\Merchant\MerchantHelper;

class MerchantPartnerAPIController extends ControllerAPI
{
    protected $merchantViewRoles = ['super admin', 'merchant database admin'];

    /**
     * GET Partner list wich is affected to Store/tenant
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     */
    public function getMerchantPartner()
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
            $validRoles = $this->merchantViewRoles;
            if (! in_array(strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $merchantHelper = MerchantHelper::create();
            $merchantHelper->merchantCustomValidator();

            $sort_by = OrbitInput::get('sortby');

            $validator = Validator::make(
                array(
                    'sortby'     => $sort_by,
                ),
                array(
                    'sortby'     => 'in:partner_id,partner_name,partner_city,partner_start_date,partner_end_date,partner_created_at,partner_updated_at',
                ),
                array(
                    'sortby.in' => Lang::get('validation.orbit.empty.retailer_sortby'),
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $partners = Partner::excludeDeleted('partners')
                                ->select('partners.partner_id', 'partners.partner_name')
                                ->join('partner_affected_group', 'partner_affected_group.partner_id', '=', 'partners.partner_id')
                                ->join('affected_group_names', function($join) {
                                        $join->on('affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
                                             ->where('affected_group_names.group_type', '=', 'tenant');
                                });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_partners = clone $partners;

            $take = PaginationNumber::parseTakeFromGet('affected_group_name');
            $partners->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $partners->skip($skip);

            // Default sort by
            $sortBy = 'partner_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'partner_id'         => 'partners.partner_id',
                    'partner_name'       => 'partners.partner_name',
                    'partner_city'       => 'partners.city',
                    'partner_start_date' => 'partners.start_date',
                    'partner_end_date'   => 'partners.end_date',
                    'partner_created_at' => 'partners.created_at',
                    'partner_updated_at' => 'partners.updated_at',
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
            $partners->orderBy($sortBy, $sortMode);

            $totalPartners = RecordCounter::create($_partners)->count();
            $listOfPartners = $partners->get();

            $data = new stdclass();
            $data->total_records = $totalPartners;
            $data->returned_records = count($listOfPartners);
            $data->records = $listOfPartners;

            if ($totalPartners === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.partner');
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
