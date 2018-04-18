<?php namespace Orbit\Controller\API\v1\Pub\Sponsor;
/**
 * An API controller for managing list of sponsor.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenExceptio;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use SponsorProvider;
use SponsorCreditCard;
use UserSponsor;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use Elasticsearch\ClientBuilder;
use Language;
use DB;
use Validator;

class AvailableSponsorListAPIController extends PubControllerAPI
{
    /**
     * create validator instance
     * @param  string $objectType object type of bank, ewallet, credit_card
     * @param  string $language   language identifier
     * @param  string $bankId     bank identifier
     * @return Validator validator instance
     */
    private function createValidator($objectType, $language, $bankId)
    {
        return Validator::make(
            array(
                'object_type' => $objectType,
                'language' => $language,
                'bank_id' => $bankId
            ),
            array(
                'object_type'   => 'required|in:bank,ewallet,credit_card',
                'bank_id'   => 'required_if|object_type,credit_card',
                'language' => 'required|orbit.empty.language_default',
            )
        );
    }

    /**
     * GET - Get active sponsor list (all)
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string area
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getAvailableSponsorList()
    {
      $httpCode = 200;
        try {
            $this->checkAuth();
            $user = $this->api->user;

            $prefix = DB::getTablePrefix();

            $objectType = OrbitInput::get('object_type', 'bank');
            $lang = OrbitInput::get('language', 'id');
            $bankId = OrbitInput::get('bank_id');
            $validator = $this->createValidator($objectType, $lang, $bankId);

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang)
                            ->first();

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            if ($objectType === 'bank' || $objectType === 'ewallet') {
                $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path)";
                if ($usingCdn) {
                    $image = "CASE WHEN {$prefix}media.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END";
                }

                $sponsor = SponsorProvider::select('sponsor_providers.sponsor_provider_id as sponsor_id', 'sponsor_providers.name', 'countries.name as country_name', DB::raw("({$image}) as image_url"))
                                       ->leftJoin('media', function ($q) use ($prefix){
                                                $q->on('media.object_id', '=', 'sponsor_providers.sponsor_provider_id')
                                                  ->on('media.media_name_long', '=', DB::raw("'sponsor_provider_logo_orig'"));
                                            })
                                       ->join('countries', 'countries.country_id', '=', 'sponsor_providers.country_id')
                                       ->where('object_type', $objectType)
                                       ->where('status', 'active')
                                       ->orderBy('sponsor_providers.name', 'asc');
            } elseif ($objectType === 'credit_card') {
                $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path)";
                if ($usingCdn) {
                    $image = "CASE WHEN {$prefix}media.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END";
                }

                $sponsor = SponsorCreditCard::select('sponsor_credit_cards.sponsor_credit_card_id as sponsor_id', 'sponsor_providers.name', 'countries.name as country_name', DB::raw("({$image}) as image_url"))
                                            ->leftJoin('media', function ($q) use ($prefix){
                                                $q->on('media.object_id', '=', 'sponsor_credit_cards.sponsor_credit_card_id')
                                                  ->on('media.media_name_long', '=', DB::raw("'sponsor_credit_card_image_orig'"));
                                            })
                                            ->join('countries', 'countries.country_id', '=', 'sponsor_providers.country_id')
                                            ->where('sponsor_credit_cards.sponsor_provider_id', $bankId)
                                            ->where('sponsor_credit_cards.status', 'active')
                                            ->orderBy('sponsor_credit_cards.name', 'asc');
            }

            // Filter by country
            OrbitInput::get('country', function($country) use ($sponsor)
            {
                $sponsor->where('countries.name', $country);
            });

            $_sponsor = $sponsor;

            $take = PaginationNumber::parseTakeFromGet('category');
            $sponsor->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $sponsor->skip($skip);

            $listSponsor = $sponsor->get();

            $role = $user->role->role_name;
            $userSponsor = array();

            if (strtolower($role) === 'consumer' && ($objectType === 'ewallet' || $objectType === 'credit_card')) {
                $userSponsor = UserSponsor::where('sponsor_type', $objectType)
                                          ->where('user_id', $user->user_id)
                                          ->lists('sponsor_id');

                foreach ($listSponsor as $list) {
                    $list->is_selected = 'N';
                    if (in_array($list->sponsor_id, $userSponsor)) {
                        $list->is_selected = 'Y';
                    }
                }
            }

            $count = count($_sponsor->get());
            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listSponsor);
            $this->response->data->records = $listSponsor;
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
        } catch (\Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        $output = $this->render($httpCode);

        return $output;
    }

    /**
     * GET - Get active sponsor list (bank)
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string area
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getAvailableSponsorCreditCard()
    {
      $httpCode = 200;
        try {
            $this->checkAuth();
            $user = $this->api->user;

            // should always check the role
            $role = $user->role->role_name;
            $userId = $user->user_id;

            $prefix = DB::getTablePrefix();
            $lang = OrbitInput::get('language', 'id');

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang)
                            ->first();

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path)";
            if ($usingCdn) {
                $image = "CASE WHEN {$prefix}media.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END";
            }

            $sponsor = SponsorProvider::select('sponsor_providers.sponsor_provider_id as bank_id', 'sponsor_providers.name', 'countries.name as country_name', DB::raw("({$image}) as image_url"))
                                   ->leftJoin('media', function ($q) use ($prefix){
                                            $q->on('media.object_id', '=', 'sponsor_providers.sponsor_provider_id')
                                              ->on('media.media_name_long', '=', DB::raw("'sponsor_provider_logo_orig'"));
                                        })
                                   ->join('countries', 'countries.country_id', '=', 'sponsor_providers.country_id')
                                   ->where('object_type', 'bank')
                                   ->where('status', 'active')
                                   ->orderBy('sponsor_providers.name', 'asc');

            // Filter by country
            OrbitInput::get('country', function($country) use ($sponsor)
            {
                $sponsor->where('countries.name', $country);
            });

            $_sponsor = $sponsor;

            $take = PaginationNumber::parseTakeFromGet('category');
            $sponsor->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $sponsor->skip($skip);

            $userSponsor = array();
            if (strtolower($role) === 'consumer') {
                $userSponsor = UserSponsor::where('user_sponsor.sponsor_type', 'credit_card')
                                          ->where('user_sponsor.user_id', $userId)
                                          ->lists('sponsor_id');
            }

            $listSponsor = $sponsor->get();
            foreach ($listSponsor as $bank) {
                $creditCards = SponsorCreditCard::select('sponsor_credit_cards.sponsor_credit_card_id as credit_card_id', 'sponsor_credit_cards.name', DB::raw("({$image}) as image_url"))
                                            ->leftJoin('media', function ($q) use ($prefix){
                                                $q->on('media.object_id', '=', 'sponsor_credit_cards.sponsor_credit_card_id')
                                                  ->on('media.media_name_long', '=', DB::raw("'sponsor_credit_card_image_orig'"));
                                            })
                                            ->where('sponsor_credit_cards.sponsor_provider_id', $bank->bank_id)
                                            ->where('sponsor_credit_cards.status', 'active')
                                            ->orderBy('sponsor_credit_cards.name', 'asc')
                                            ->get();

                $bank->credit_cards = array();
                if (! $creditCards->isEmpty()) {
                    foreach ($creditCards as $creditCard) {
                        $creditCard->is_selected = 'N';
                        if (in_array($creditCard->credit_card_id, $userSponsor)) {
                            $creditCard->is_selected = 'Y';
                        }
                    }

                    $bank->credit_cards = $creditCards;
                }
            }

            $count = count($_sponsor->get());
            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listSponsor);
            $this->response->data->records = $listSponsor;
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
        } catch (\Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        $output = $this->render($httpCode);

        return $output;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
