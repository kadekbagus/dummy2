<?php
/**
 * An API controller for managing Campaign Locations.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class CampaignLocationAPIController extends ControllerAPI
{
    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'mall customer service', 'campaign admin'];

    /**
     * GET - Get Tenant And Mall (Locations) per Campaign
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `campaign_id            (required) - Campaign id (news_id, promotion_id, coupon_id)
     * @param string   `campaign_type          (required) - news, promotion, coupon
     *
     * @return Illuminate\Support\Facades\Response
     */

    public function getCampaignLocations()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.CampaignLocations.gettenantcampaignsummary.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.CampaignLocations.gettenantcampaignsummary.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.CampaignLocations.gettenantcampaignsummary.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            // $role = $user->role;
            // $validRoles = $this->viewRoles;
            // if (! in_array( strtolower($role->role_name), $validRoles)) {
            //     $message = 'Your role are not allowed to access this resource.';
            //     ACL::throwAccessForbidden($message);
            // }

            Event::fire('orbit.CampaignLocations.gettenantcampaignsummary.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $campaign_id = OrbitInput::get('campaign_id');
            $campaign_type = OrbitInput::get('campaign_type');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'campaign_id' => $campaign_id,
                    'campaign_type' => $campaign_type,
                ),
                array(
                    'campaign_id' => 'required',
                    'campaign_type' => 'required',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.campaignreportgeneral_sortby'),
                )
            );

            Event::fire('orbit.CampaignLocations.gettenantcampaignsummary.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.CampaignLocations.gettenantcampaignsummary.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.coupon.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }

            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.coupon.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $tablePrefix = DB::getTablePrefix();

            if ($campaign_type == 'news' || $campaign_type == 'promotion') {
                $campaignLocations = NewsMerchant::select('merchants.merchant_id', 'merchants.name', 'news_merchant.object_type', DB::raw("
                        (
                            SELECT
                            IF({$tablePrefix}news_merchant.object_type = 'retailer', CONCAT(om.name,' at ', pm.name), CONCAT('Mall at ', om.name) )
                            FROM {$tablePrefix}news_merchant
                            inner join {$tablePrefix}merchants om on om.merchant_id = {$tablePrefix}news_merchant.merchant_id
                            inner join {$tablePrefix}merchants pm on om.parent_id = pm.merchant_id
                            where 1=1
                            and {$tablePrefix}news_merchant.news_id = {$this->quote($campaign_id)}
                            and {$tablePrefix}news_merchant.merchant_id = `{$tablePrefix}merchants`.`merchant_id`
                        ) as campaign_location_names
                    "))
                    ->join('merchants', 'news_merchant.merchant_id', '=', 'merchants.merchant_id')
                    ->where('news_id', $campaign_id);
            } elseif ($campaign_type == 'coupon') {
                $campaignLocations =  PromotionRetailer::select('merchants.merchant_id', 'merchants.name', 'promotion_retailer.object_type', DB::raw("
                        (
                            SELECT
                            IF({$tablePrefix}promotion_retailer.object_type = 'mall', CONCAT(om.name,' at ', pm.name), CONCAT('Mall at ', om.name) )
                            FROM {$tablePrefix}promotion_retailer
                            inner join {$tablePrefix}merchants om on om.merchant_id = {$tablePrefix}promotion_retailer.retailer_id
                            inner join {$tablePrefix}merchants pm on om.parent_id = pm.merchant_id
                            where 1=1
                            and {$tablePrefix}promotion_retailer.promotion_id = {$this->quote($campaign_id)}
                            and {$tablePrefix}promotion_retailer.retailer_id = `{$tablePrefix}merchants`.`merchant_id`
                        ) as campaign_location_names
                    "))
                    ->join('merchants', 'promotion_retailer.retailer_id', '=', 'merchants.merchant_id')
                    ->where('promotion_id', $campaign_id);
            }

            $_campaignLocations = clone $campaignLocations;

            // Get the take args
            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $campaignLocations->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $campaignLocations)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }
                $skip = $_skip;
            });
            $campaignLocations->skip($skip);

            $listOfCampaignLocations = $campaignLocations->get();

            $totalCampaignLocations = RecordCounter::create($_campaignLocations)->count();
            $totalReturnedRecords = count($listOfCampaignLocations);

            $data = new stdclass();
            $data->total_records = $totalCampaignLocations;
            $data->returned_records = $totalCampaignLocations - $totalReturnedRecords;
            $data->remaining_records = count($listOfCampaignLocations);
            $data->records = $listOfCampaignLocations;

            if ($totalCampaignLocations === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.news');
            }

            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.CampaignLocations.gettenantcampaignsummary.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.CampaignLocations.gettenantcampaignsummary.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 400;
        } catch (QueryException $e) {
            Event::fire('orbit.CampaignLocations.gettenantcampaignsummary.query.error', array($this, $e));

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
            Event::fire('orbit.CampaignLocations.gettenantcampaignsummary.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = 'null';
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.campaignlocations.gettenantcampaignsummary.before.render', array($this, &$output));

        return $output;
    }


    protected function registerCustomValidation()
    {
        $user = $this->api->user;
        // Check the existance of mall id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) use ($user){
            $mall = Mall::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.empty.mall', $mall);

            return TRUE;
        });
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}