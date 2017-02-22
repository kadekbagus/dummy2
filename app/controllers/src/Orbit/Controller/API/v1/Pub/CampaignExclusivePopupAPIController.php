<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for getting exclusive partner info
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use Partner;
use News;
use Coupon;
use Validator;
use Language;
use stdClass;
use \DB;
use Orbit\Helper\Util\PaginationNumber;
use Orbit\Controller\API\v1\Pub\News\NewsHelper;

class CampaignExclusivePopupAPIController extends PubControllerAPI
{
    protected $language = NULL;

    /**
     * GET - Get exclusive partner info
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getPartnerInfo()
    {
        $httpCode = 200;
        try{
            $language = OrbitInput::get('language', 'id');
            $ul = OrbitInput::get('ul', null);
            $campaign_id = OrbitInput::get('campaign_id', null);
            $campaign_type = OrbitInput::get('campaign_type', null);
            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'campaign_id' => $campaign_id,
                    'campaign_type' => $campaign_type,
                    'language' => $language,
                ),
                array(
                    'campaign_id' => 'required',
                    'campaign_type' => 'required|in:news,promotion,coupon',
                    'language' => 'required|orbit.empty.language_default',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $language_id = $this->language->language_id;

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';
            $prefix = DB::getTablePrefix();

            $logo = "CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) as logo_url";

            if ($usingCdn) {
                $logo = "CASE WHEN ({$prefix}media.cdn_url is null or {$prefix}media.cdn_url = '') THEN CONCAT({$this->quote($urlPrefix)}, {$prefix}media.path) ELSE {$prefix}media.cdn_url END as logo_url";
            }

            // get the first exclusive partner linked to the camapaign
            $partner = Partner::select(
                    'partners.partner_id',
                    'deeplinks.deeplink_url',
                    DB::raw("{$logo}"),
                    DB::Raw("
                        CASE WHEN ({$prefix}partner_translations.pop_up_content = '' or {$prefix}partner_translations.pop_up_content is null) THEN default_translation.pop_up_content ELSE {$prefix}partner_translations.pop_up_content END as pop_up_content
                    ")
                )
                ->leftJoin('media', function ($q) {
                    $q->on('media.object_id', '=', 'partners.partner_id');
                    $q->on('media.object_name', '=', DB::raw("'partner'"));
                    $q->on('media.media_name_long', '=', DB::raw("'partner_logo_orig'"));
                })
                ->leftJoin('object_partner', 'object_partner.partner_id', '=', 'partners.partner_id')
                ->leftJoin('deeplinks', function ($q) {
                    $q->on('deeplinks.object_id', '=', 'partners.partner_id');
                    $q->on('deeplinks.object_type', '=', DB::raw("'partner'"));
                    $q->on('deeplinks.status', '=', DB::raw("'active'"));
                })
                ->leftJoin('partner_translations', function ($q) use ($language_id) {
                    $q->on('partner_translations.partner_id', '=', 'partners.partner_id')
                      ->on('partner_translations.language_id', '=', DB::raw("{$this->quote($language_id)}"));
                })
                ->leftJoin('partner_translations as default_translation', function ($q) use ($prefix){
                    $q->on(DB::raw("default_translation.partner_id"), '=', 'partners.partner_id')
                      ->where(DB::raw("default_translation.language_id"), '=', 'languages.language_id');
                })
                ->where('object_partner.object_id', $campaign_id)
                ->where('object_partner.object_type', $campaign_type)
                ->where('partners.is_exclusive', 'Y')
                ->groupBy('partners.partner_id')
                ->first();

            $this->response->data = $partner;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';

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
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }

    public function registerCustomValidation() {
        // Check language is exists
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $lang_name = $value;

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang_name)
                            ->first();

            if (empty($language)) {
                return FALSE;
            }

            $this->language = $language;

            return TRUE;
        });
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}