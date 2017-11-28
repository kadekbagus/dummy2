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

class AvailableSponsorListAPIController extends PubControllerAPI
{

    /**
     * GET - Get active sponsor list (bank)
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
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

                $sponsor = SponsorProvider::select('sponsor_providers.sponsor_provider_id as sponsor_id', 'sponsor_providers.name', DB::raw("({$image}) as image_url"))
                                       ->leftJoin('media', function ($q) use ($prefix){
                                                $q->on('media.object_id', '=', 'sponsor_providers.sponsor_provider_id')
                                                  ->on('media.media_name_long', '=', DB::raw("'sponsor_provider_logo_orig'"));
                                            })
                                       ->where('object_type', $objectType)
                                       ->where('status', 'active')
                                       ->orderBy('sponsor_providers.name', 'asc');
            } elseif ($objectType === 'credit_card') {
                $bankId = OrbitInput::get('bank_id');

                if ($validator->fails()) {
                    $errorMessage = "Bank ID is required";
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path)";
                if ($usingCdn) {
                    $image = "CASE WHEN {$prefix}media.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END";
                }

                $sponsor = SponsorCreditCard::select('sponsor_credit_cards.sponsor_credit_card_id as sponsor_id', 'sponsor_providers.name', DB::raw("({$image}) as image_url"))
                                            ->leftJoin('media', function ($q) use ($prefix){
                                                $q->on('media.object_id', '=', 'sponsor_credit_cards.sponsor_credit_card_id')
                                                  ->on('media.media_name_long', '=', DB::raw("'sponsor_provider_image_orig'"));
                                            })
                                            ->where('sponsor_credit_cards.sponsor_provider_id', $bankId)
                                            ->where('sponsor_credit_cards.status', 'active')
                                            ->orderBy('sponsor_credit_cards.name', 'asc');
            }

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

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}