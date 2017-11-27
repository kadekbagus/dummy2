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
use SponsorCreditCard;
use SponsorProvider;
use ObjectSponsorCreditCard;

class LinkToSponsorAPIController extends ControllerAPI
{
    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'mall customer service', 'campaign admin'];

    /**
     * GET - link to sponsor
     *
     * @author Shelgi <Shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `object_id            (required) - Campaign id (news_id, promotion_id, coupon_id)
     * @param string   `object_type          (required) - news, promotion, coupon
     *
     * @return Illuminate\Support\Facades\Response
     */

    public function getLinkToSponsor()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            // $role = $user->role;
            // $validRoles = $this->viewRoles;
            // if (! in_array( strtolower($role->role_name), $validRoles)) {
            //     $message = 'Your role are not allowed to access this resource.';
            //     ACL::throwAccessForbidden($message);
            // }

            $objectId = OrbitInput::get('object_id');
            $objectType = OrbitInput::get('object_type');

            // return all if empty object_type and object_id
            if (! empty($objectId) && ! empty($objectType)) {
                $objectSponsor = ObjectSponsor::select('sponsor_providers.*', 'object_sponsor.object_sponsor_id')
                                              ->leftJoin('sponsor_providers', 'sponsor_providers.sponsor_provider_id', '=', 'object_sponsor.sponsor_provider_id')
                                              ->where('sponsor_providers.status', 'active')
                                              ->where('object_sponsor.object_type', $objectType)
                                              ->where('object_sponsor.object_id', $objectId);

                $_objectSponsor = clone $objectSponsor;
                $objectSponsor = $objectSponsor->get();

                if (! $objectSponsor->isEmpty()) {

                    foreach ($objectSponsor as $sponsor) {
                        $sponsor->credit_cards = array();
                        if ($sponsor->object_type === 'bank') {
                            $objectCreditCard = ObjectSponsorCreditCard::select('sponsor_credit_cards.sponsor_credit_card_id')
                                                                  ->join('sponsor_credit_cards', 'sponsor_credit_cards.sponsor_credit_card_id', '=', 'object_sponsor_credit_card.sponsor_credit_card_id')
                                                                  ->where('sponsor_credit_cards.status', 'active')
                                                                  ->where('object_sponsor_credit_card.object_sponsor_id', $sponsor->object_sponsor_id)
                                                                  ->get();

                            $creditCardList = array();
                            foreach ($objectCreditCard as $objectCC) {
                                $creditCardList[] = $objectCC->sponsor_credit_card_id;
                            }

                            $creditCards = SponsorCreditCard::where('status', 'active')
                                                           ->where('sponsor_provider_id', $sponsor->sponsor_provider_id)
                                                           ->get();

                            if (! $creditCards->isEmpty()) {
                                foreach ($creditCards as $creditCard) {
                                    $creditCard->is_selected = 'N';
                                    if (in_array($creditCard->sponsor_credit_card_id, $creditCardList)) {
                                        $creditCard->is_selected = 'Y';
                                    }
                                }
                                $sponsor->credit_cards = $creditCards;
                            }
                        }
                    }
                } else {
                    $objectSponsor = SponsorProvider::with('creditCards')
                                                  ->where('status', 'active');

                    $_objectSponsor = clone $objectSponsor;
                    $objectSponsor = $objectSponsor->get();
                }
            } else {
                $objectSponsor = SponsorProvider::with('creditCards')
                                                  ->where('status', 'active');

                $_objectSponsor = clone $objectSponsor;
                $objectSponsor = $objectSponsor->get();
            }

            $totalObjectSponsor = RecordCounter::create($_objectSponsor)->count();
            $totalReturnedRecords = count($objectSponsor);

            $data = new stdclass();
            $data->total_records = $totalObjectSponsor;
            $data->returned_records = $totalReturnedRecords;
            $data->records = $objectSponsor;
            $this->response->data = $data;

        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.campaignlocations.getcampaignlocations.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.campaignlocations.getcampaignlocations.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 400;
        } catch (QueryException $e) {
            Event::fire('orbit.campaignlocations.getcampaignlocations.query.error', array($this, $e));

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
            Event::fire('orbit.campaignlocations.getcampaignlocations.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = 'null';
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.campaignlocations.getcampaignlocations.before.render', array($this, &$output));

        return $output;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }

}