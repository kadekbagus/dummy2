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

class UserEWalletListAPIController extends PubControllerAPI
{

    /**
     * GET - Get user e-wallet list
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
    public function getUserEWallet()
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

            $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path)";
            if ($usingCdn) {
                $image = "CASE WHEN {$prefix}media.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END";
            }

            $userId = $user->user_id;

            $userSponsor = UserSponsor::select('sponsor_providers.sponsor_provider_id as ewallet_id',
                                               'sponsor_providers.name as ewallet_name',
                                               'countries.name as country_name',
                                               DB::raw("{$image} as ewallet_image")
                                        )
                                      ->join('sponsor_providers', 'sponsor_providers.sponsor_provider_id', '=', 'user_sponsor.sponsor_id')
                                      ->leftJoin('media', function ($q) use ($prefix){
                                            $q->on('media.object_id', '=', 'sponsor_providers.sponsor_provider_id')
                                              ->on('media.media_name_long', '=', DB::raw("'sponsor_provider_logo_orig'"));
                                        })
                                      ->join('countries', 'countries.country_id', '=', 'sponsor_providers.country_id')
                                      ->where('user_sponsor.sponsor_type', 'ewallet')
                                      ->where('sponsor_providers.status', 'active')
                                      ->where('user_sponsor.user_id', $userId)
                                      ->orderBy('ewallet_name', 'asc');

            // Filter by country
            OrbitInput::get('country', function($country) use ($userSponsor)
            {
                $userSponsor->where('countries.name', $country);
            });

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