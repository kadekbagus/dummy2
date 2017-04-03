<?php namespace Orbit\Controller\API\v1\Pub\PromotionalEvent;

/**
 * @author firmansyah <firmansyah@dominopos.com>
 * @desc Controller for get sign up background image of promotional event
 */

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use \URL;
use Language;
use Validator;
use Activity;
use Mall;
use Partner;
use News;
use Orbit\Controller\API\v1\Pub\SocMedAPIController;
use \Orbit\Helper\Exception\OrbitCustomException;
use Orbit\Helper\PromotionalEvent\PromotionalEventProcessor;

class PromotionalEventBackgroundAPIController extends PubControllerAPI
{
     public function getPromotionalEventBackground()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = NULL;

        try{
            $user = $this->getUser();
            $newsId = OrbitInput::get('news_id', null);
            $language = OrbitInput::get('language', 'id');

            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'news_id' => $newsId,
                    'language' => $language,
                ),
                array(
                    'news_id' => 'required',
                    'language' => 'required|orbit.empty.language_default',
                ),
                array(
                    'required' => 'News ID is required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $this->getValidLanguage();

            $prefix = DB::getTablePrefix();

            $usingCdn = Config::get('orbit.cdn.enable_cdn', FALSE);
            $defaultUrlPrefix = Config::get('orbit.cdn.providers.default.url_prefix', '');
            $urlPrefix = ($defaultUrlPrefix != '') ? $defaultUrlPrefix . '/' : '';

            $image = "CONCAT({$this->quote($urlPrefix)}, m.path)";
            if ($usingCdn) {
                $image = "CASE WHEN m.cdn_url IS NULL THEN CONCAT({$this->quote($urlPrefix)}, m.path) ELSE m.cdn_url END";
            }

            $promotionalEvent = News::select(
                            'news.news_id as news_id',
                            'reward_details.reward_detail_id',
                            DB::Raw("
                                CASE WHEN (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'reward_signup_bg_desktop_orig'
                                    AND m.object_id = {$prefix}reward_detail_translations.reward_detail_translation_id) is null
                                THEN
                                    (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'reward_signup_bg_desktop_orig'
                                    AND m.object_id = default_translation.reward_detail_translation_id)
                                ELSE
                                    (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'reward_signup_bg_desktop_orig'
                                    AND m.object_id = {$prefix}reward_detail_translations.reward_detail_translation_id)
                                END AS promotional_event_desktop_media_path
                            "),
                            DB::Raw("
                                CASE WHEN (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'reward_signup_bg_mobile_orig'
                                    AND m.object_id = {$prefix}reward_detail_translations.reward_detail_translation_id) is null
                                THEN
                                    (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'reward_signup_bg_mobile_orig'
                                    AND m.object_id = default_translation.reward_detail_translation_id)
                                ELSE
                                    (SELECT {$image}
                                    FROM orb_media m
                                    WHERE m.media_name_long = 'reward_signup_bg_mobile_orig'
                                    AND m.object_id = {$prefix}reward_detail_translations.reward_detail_translation_id)
                                END AS promotional_event_mobile_media_path
                            ")
                        )
                        ->join('campaign_account', 'campaign_account.user_id', '=', 'news.created_by')
                        ->join('languages', 'languages.name', '=', 'campaign_account.mobile_default_language')

                        //Join for get sign up background translations
                        ->join('reward_details', 'news.news_id', '=', 'reward_details.object_id')

                        ->leftJoin('reward_detail_translations', function ($q) use ($valid_language) {
                            $q->on('reward_detail_translations.reward_detail_id', '=', 'reward_details.reward_detail_id')
                              ->on('reward_detail_translations.language_id', '=', DB::raw("{$this->quote($valid_language->language_id)}"));
                        })
                        ->leftJoin('reward_detail_translations as default_translation', function ($q) use ($prefix){
                            $q->on(DB::raw("default_translation.reward_detail_id"), '=', 'reward_detail_translations.reward_detail_id')
                              ->on(DB::raw("default_translation.language_id"), '=', 'languages.language_id');
                        })
                        ->where('news.news_id', $newsId)
                        ->first();

            $message = 'Request Ok';
            if (! is_object($promotionalEvent)) {
                OrbitShopAPI::throwInvalidArgument('Promotional event background that you specify is not found');
            }

            $this->response->data = $promotionalEvent;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = $message;

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

        } catch (\Orbit\Helper\Exception\OrbitCustomException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
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

    protected function registerCustomValidation()
    {
        // Check language is exists
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $lang_name = $value;

            $language = Language::where('status', '=', 'active')
                            ->where('name', $lang_name)
                            ->first();

            if (empty($language)) {
                return FALSE;
            }

            $this->valid_language = $language;
            return TRUE;
        });
    }

    public function getValidLanguage()
    {
        return $this->valid_language;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }


}