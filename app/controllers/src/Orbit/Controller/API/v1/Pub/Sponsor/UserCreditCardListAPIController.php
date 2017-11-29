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
use UserSponsor;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use Elasticsearch\ClientBuilder;
use Language;
use DB;

class UserCreditCardListAPIController extends PubControllerAPI
{

    /**
     * GET - Get user credit card list
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string area
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getUserCreditCard()
    {
      $httpCode = 200;
        try {
            $this->checkAuth();
            $user = $this->api->user;

            // should always check the role
            $role = $user->role->role_name;
            if (strtolower($role) !== 'consumer') {
                $message = 'You have to login to continue';
                OrbitShopAPI::throwInvalidArgument($message);
            }

            $prefix = DB::getTablePrefix();
            $lang = OrbitInput::get('language', 'id');

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang)
                            ->first();

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $imageBank = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path)";
            $imageCC = "CONCAT({$this->quote($urlPrefix)}, credit_card_media.path)";
            if ($usingCdn) {
                $imageBank = "CASE WHEN {$prefix}media.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END";
                $imageCC = "CASE WHEN credit_card_media.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, credit_card_media.path) ELSE credit_card_media.cdn_url END";
            }

            $userId = $user->user_id;

            $userSponsor = UserSponsor::select('sponsor_providers.sponsor_provider_id as bank_id',
                                               'sponsor_providers.name as bank_name',
                                               DB::raw("{$imageBank} as bank_image"),
                                               'sponsor_credit_cards.sponsor_credit_card_id as credit_card_id',
                                               'sponsor_credit_cards.name as credit_card_name',
                                               DB::raw("{$imageBank} as credit_card_image")
                                        )
                                      ->join('sponsor_credit_cards', 'sponsor_credit_cards.sponsor_credit_card_id', '=', 'user_sponsor.sponsor_id')
                                      ->join('sponsor_providers', 'sponsor_providers.sponsor_provider_id', '=', 'sponsor_credit_cards.sponsor_provider_id')
                                      ->leftJoin('media', function ($q) use ($prefix){
                                            $q->on('media.object_id', '=', 'sponsor_providers.sponsor_provider_id')
                                              ->on('media.media_name_long', '=', DB::raw("'sponsor_provider_logo_orig'"));
                                        })
                                      ->leftJoin('media as credit_card_media', function ($q) use ($prefix){
                                            $q->on(DB::raw("credit_card_media.object_id"), '=', 'sponsor_credit_cards.sponsor_credit_card_id')
                                              ->on(DB::raw("credit_card_media.media_name_long"), '=', DB::raw("'sponsor_provider_image_orig'"));
                                        })
                                      ->where('user_sponsor.sponsor_type', 'credit_card')
                                      ->where('sponsor_credit_cards.status', 'active')
                                      ->where('sponsor_providers.status', 'active')
                                      ->where('user_sponsor.user_id', $userId)
                                      ->orderBy('bank_name', 'asc')
                                      ->orderBy('credit_card_name', 'asc');

            $_userSponsor = $userSponsor;

            $take = PaginationNumber::parseTakeFromGet('category');
            $userSponsor->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $userSponsor->skip($skip);

            $listUserSponsor = $userSponsor->get();

            $count = count($_userSponsor->get());
            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listUserSponsor);
            $this->response->data->records = $listUserSponsor;
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