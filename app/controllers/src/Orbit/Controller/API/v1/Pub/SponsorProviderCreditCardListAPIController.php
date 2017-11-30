<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * API for getting sponsor provider credit card per each bank
 * @author firmansyah <firmansyah@dominopos.com>
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Controller\API\v1\Pub\News\NewsHelper;
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
use SponsorCreditCard;
use ObjectSponsorCreditCard;

class SponsorProviderCreditCardListAPIController extends PubControllerAPI
{
    /**
     * GET - Sponsored Provider Credit Card List
     *
     * @author firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string `object_id` (required) - object type of the seo text
     * @param string `object_type` (required) - object type of the seo text
     * @param string `sponsor_provider_id` (required)
     * @param string `language` (required) - id, en
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSponsorProviderCreditcardList()
    {
        $httpCode = 200;
        try {
            // $user = $this->getUser();

            $objectId = OrbitInput::get('object_id', null);
            $objectType = OrbitInput::get('object_type', null);
            $sponsorProviderId = OrbitInput::get('sponsor_provider_id', null);
            $language = OrbitInput::get('language', 'id');

            $newsHelper = NewsHelper::create();
            $newsHelper->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'object_id' => $objectId,
                    'object_type' => $objectType,
                    'sponsor_provider_id' => $sponsorProviderId,
                    'language' => $language,
                ),
                array(
                    'object_id'   => 'required',
                    'object_type'   => 'required|in:news,coupon,promotion',
                    'sponsor_provider_id'   => 'required',
                    'language' => 'required|orbit.empty.language_default',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $validLanguage = $newsHelper->getValidLanguage();

            $prefix = DB::getTablePrefix();

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            // CDN image
            $image = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path)";
            if ($usingCdn) {
                $image = "CASE WHEN {$prefix}media.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END";
            }

            $sponsorProvider = ObjectSponsor::where('object_id', $objectId)
                                            ->where('object_type', $objectType)
                                            ->where('sponsor_provider_id', $sponsorProviderId)
                                            ->first();

            $data = null;
            if (! empty($sponsorProvider)) {
                if ($sponsorProvider->is_all_credit_card === 'Y') {
                    $sponsorProviderCC = SponsorCreditCard::select('sponsor_credit_cards.name', DB::raw("{$image} as image_url"), DB::raw("CASE WHEN ({$prefix}sponsor_credit_card_translations.description = '' or {$prefix}sponsor_credit_card_translations.description is null) THEN {$prefix}sponsor_credit_cards.description ELSE {$prefix}sponsor_credit_card_translations.description END as description")
                        )
                                                        ->where('sponsor_provider_id', '=', $sponsorProvider->sponsor_provider_id);

                } elseif ($sponsorProvider->is_all_credit_card === 'N') {
                    $sponsorProviderCC = ObjectSponsorCreditCard::select('sponsor_credit_cards.name', DB::raw("{$image} as image_url"), DB::raw("CASE WHEN ({$prefix}sponsor_credit_card_translations.description = '' or {$prefix}sponsor_credit_card_translations.description is null) THEN {$prefix}sponsor_credit_cards.description ELSE {$prefix}sponsor_credit_card_translations.description END as description")
                        )
                                                            ->leftJoin('sponsor_credit_cards', 'sponsor_credit_cards.sponsor_credit_card_id', '=', 'object_sponsor_credit_card.sponsor_credit_card_id')
                                                            ->where('object_sponsor_id', $sponsorProvider->object_sponsor_id)
                                                            ;
                }

                $sponsorProviderCC->leftJoin('sponsor_credit_card_translations', function($q) use ($validLanguage){
                                        $q->on('sponsor_credit_card_translations.sponsor_credit_card_id' , '=', 'sponsor_credit_cards.sponsor_credit_card_id')
                                          ->on('sponsor_credit_card_translations.language_id', '=', DB::raw("{$this->quote($validLanguage->language_id)}"));
                                    })
                                  ->leftJoin('media', function($q){
                                                $q->on('media.object_id', '=', 'sponsor_credit_cards.sponsor_credit_card_id')
                                                  ->on('media.media_name_long', '=', DB::raw('"sponsor_provider_image_orig"'));
                                            })
                                    ->where('sponsor_credit_cards.status', 'active');

                $take = PaginationNumber::parseTakeFromGet('news');
                $sponsorProviderCC->take($take);

                $skip = PaginationNumber::parseSkipFromGet();
                $sponsorProviderCC->skip($skip);

                $listOfRec = $sponsorProviderCC->get();

                $data = new \stdclass();
                $data->returned_records = count($listOfRec);
                $data->total_records = count($listOfRec);

                $data->records = $listOfRec;
            }

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