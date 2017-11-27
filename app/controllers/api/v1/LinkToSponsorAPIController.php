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

            $sponsorProviders = SponsorProvider::where('status', 'active');

            $_sponsorProviders = clone $sponsorProviders;
            $sponsorProviders = $sponsorProviders->get();

            if (! empty($objectId) && ! empty($objectType)) {
                $objectSponsors = ObjectSponsor::select('sponsor_providers.*', 'object_sponsor.is_all_credit_card', 'object_sponsor.object_sponsor_id')
                                              ->leftJoin('sponsor_providers', 'sponsor_providers.sponsor_provider_id', '=', 'object_sponsor.sponsor_provider_id')
                                              ->where('sponsor_providers.status', 'active')
                                              ->where('object_sponsor.object_type', $objectType)
                                              ->where('object_sponsor.object_id', $objectId)
                                              ->get();

                $selectedSponsor = array();
                $isAllCreditCard = array();
                $haveObjectSponsor = array();
                foreach ($objectSponsors as $objectSponsor) {
                    $selectedSponsor[] = $objectSponsor->sponsor_provider_id;
                    $haveObjectSponsor[$objectSponsor->sponsor_provider_id] = $objectSponsor->object_sponsor_id;
                    if ($objectSponsor->is_all_credit_card === 'Y') {
                        $isAllCreditCard[] = $objectSponsor->sponsor_provider_id;
                    }
                }

                foreach ($sponsorProviders as $sponsorProvider) {
                    $sponsorProvider->is_selected = 'N';
                    if (in_array($sponsorProvider->sponsor_provider_id, $selectedSponsor)) {
                        $sponsorProvider->is_selected = 'Y';
                    }

                    $sponsorProvider->is_all_credit_card = 'N';
                    if (in_array($sponsorProvider->sponsor_provider_id, $isAllCreditCard)) {
                        $sponsorProvider->is_all_credit_card = 'Y';
                    }

                    $sponsorProvider->credit_cards = array();
                    if ($sponsorProvider->object_type === 'bank') {
                        $creditCardList = array();
                        if (! empty($haveObjectSponsor[$sponsorProvider->sponsor_provider_id])) {
                            $objectCreditCard = ObjectSponsorCreditCard::select('sponsor_credit_cards.sponsor_credit_card_id')
                                                              ->join('sponsor_credit_cards', 'sponsor_credit_cards.sponsor_credit_card_id', '=', 'object_sponsor_credit_card.sponsor_credit_card_id')
                                                              ->where('sponsor_credit_cards.status', 'active')
                                                              ->where('object_sponsor_credit_card.object_sponsor_id', $haveObjectSponsor[$sponsorProvider->sponsor_provider_id])
                                                              ->get();

                            foreach ($objectCreditCard as $objectCC) {
                                $creditCardList[] = $objectCC->sponsor_credit_card_id;
                            }
                        }

                        $creditCards = SponsorCreditCard::where('status', 'active')
                                                       ->where('sponsor_provider_id', $sponsorProvider->sponsor_provider_id)
                                                       ->get();

                        if (! $creditCards->isEmpty()) {
                            foreach ($creditCards as $creditCard) {
                                $creditCard->is_selected = 'N';
                                if (in_array($creditCard->sponsor_credit_card_id, $creditCardList)) {
                                    $creditCard->is_selected = 'Y';
                                }
                            }
                            $sponsorProvider->credit_cards = $creditCards;
                        }
                    }
                }
            } else {
                foreach ($sponsorProviders as $sponsorProvider) {
                    $sponsorProvider->is_selected = 'N';
                    $sponsorProvider->is_all_credit_card = 'N';

                    $sponsorProvider->credit_cards = array();
                    if ($sponsorProvider->object_type === 'bank') {
                        $creditCards = SponsorCreditCard::where('status', 'active')
                                                       ->where('sponsor_provider_id', $sponsorProvider->sponsor_provider_id)
                                                       ->get();

                        if (! $creditCards->isEmpty()) {
                            foreach ($creditCards as $creditCard) {
                                $creditCard->is_selected = 'N';
                            }
                            $sponsorProvider->credit_cards = $creditCards;
                        }
                    }
                }
            }

            $totalObjectSponsor = RecordCounter::create($_sponsorProviders)->count();
            $totalReturnedRecords = count($sponsorProviders);

            $data = new stdclass();
            $data->total_records = $totalObjectSponsor;
            $data->returned_records = $totalReturnedRecords;
            $data->records = $sponsorProviders;
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