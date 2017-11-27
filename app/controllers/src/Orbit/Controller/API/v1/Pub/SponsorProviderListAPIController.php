<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * API for getting sponsor provider ()
 * @author firmansyah <firmansyah@dominopos.com>
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\OrbitShopAPI;
use Validator;
use stdClass;
use Page;
use Mall;
use DB;
use Orbit\Helper\Util\PaginationNumber;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use ObjectSponsor;

class SponsorProviderListAPIController extends PubControllerAPI
{
    /**
     * GET - Sponsored Provider List
     *
     * @author firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string `object_id` (required) - object type of the seo text
     * @param string `object_type` (required) - object type of the seo text
     * @param string `language` (required) - id, en
     * @param string `country` (required) - id, en
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSponsorProviderList()
    {
        $httpCode = 200;
        try {
            $user = $this->getUser();

            $objectId = OrbitInput::get('object_id', null);
            $objectType = OrbitInput::get('object_type', null);
            $language = OrbitInput::get('language', 'id');
            $country = OrbitInput::get('country', null);

            $validator = Validator::make(
                array(
                    'object_id' => $objectId,
                    'object_type' => $objectType,
                    'language' => $language,
                ),
                array(
                    'object_type'   => 'required|in:news,coupon,promotion',
                    'object_id'   => 'required',
                    'language'   => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $prefix = DB::getTablePrefix();

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            // check payment method / wallet operators
            $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path)";
            if ($usingCdn) {
                $image = "CASE WHEN {$prefix}media.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END";
            }

            $sponsorProvider = ObjectSponsor::select('sponsor_providers.name', 'languages.name as name_language', 'sponsor_provider_translations.description', 'sponsor_providers.object_type', DB::raw("{$image} as image_url"))
                                            // Get name bank/ewallet
                                            ->join('sponsor_providers', function($q){
                                                    $q->on('sponsor_providers.sponsor_provider_id', '=', 'object_sponsor.sponsor_provider_id')
                                                      ->on('sponsor_providers.status', '=', DB::raw("'active'"));
                                              })
                                            // Get media of bank/ewallet
                                            ->leftJoin('media', function($q){
                                                    $q->on('media.object_id', '=', 'sponsor_providers.sponsor_provider_id')
                                                      ->on('media.media_name_long', '=', DB::raw('"sponsor_provider_logo_orig"'));
                                              })
                                            // Get translation
                                            ->leftJoin('sponsor_provider_translations', 'sponsor_provider_translations.sponsor_provider_id', '=', 'sponsor_providers.sponsor_provider_id')
                                            ->leftJoin('languages', 'languages.language_id', '=', 'sponsor_provider_translations.language_id')
                                            // TODO : Get default language, where by language, filter by country
                                            ->where('object_sponsor.object_id', $objectId)
                                            ->where('object_sponsor.object_type', $objectType)
                                            ->orderBy('sponsor_providers.object_type', 'asc')
                                            ->orderBy('sponsor_providers.name', 'asc');

            $_sponsorProvider = clone($sponsorProvider);

            $take = PaginationNumber::parseTakeFromGet('news');
            $sponsorProvider->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $sponsorProvider->skip($skip);

            $listOfRec = $sponsorProvider->get();

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = RecordCounter::create($_sponsorProvider)->count();

            $data->records = $listOfRec;

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
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