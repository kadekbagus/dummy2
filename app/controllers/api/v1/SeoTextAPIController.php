<?php
/**
 * An API controller for managing seo text.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;

class SeoTextAPIController extends ControllerAPI
{
    protected $modifySeoTextRoles = ['super admin', 'mall admin', 'mall owner'];

    /**
     * POST - Create New SeoText
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string     `object_type`           (required) - object_type
     * @param string     `status`                (required) - Status. Valid value: active, inactive
     * @param JSON       `translations`          (required) - contain title and description for each language
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewSeoText()
    {
        try {
            $httpCode = 200;
            // Require authentication
            $this->checkAuth();
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->modifySeoTextRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $country_id = OrbitInput::post('country_id', '0');
            $object_type = OrbitInput::post('object_type');
            $status = OrbitInput::post('status', 'active');
            $translations = OrbitInput::post('translations');

            $validator = Validator::make(
                array(
                    'object_type' => $object_type,
                    'status' => $status,
                ),
                array(
                    'object_type' => 'required|in:seo_promotion_list,seo_coupon_list,seo_event_list,seo_store_list,seo_mall_list,seo_homepage',
                    'status' => 'in:active,inactive',
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $result = $this->validateAndSaveTranslations($country_id, $object_type, $translations, $status, 'create');

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';
            $this->response->data = $result;

            // Commit the changes
            $this->commit();

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();
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

            // Rollback the changes
            $this->rollBack();
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    /**
     * POST - Update SeoText
     *
     * @author firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string     `object_type`           (required) - object_type
     * @param string     `status`                (required) - Status. Valid value: active, inactive
     * @param JSON       `translations`          (required) - contain title and description for each language
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateSeoText()
    {
        try {
            $httpCode = 200;
            // Require authentication
            $this->checkAuth();
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;

            $validRoles = $this->modifySeoTextRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $country_id = OrbitInput::post('country_id', '0');
            $object_type = OrbitInput::post('object_type');
            $status = OrbitInput::post('status', 'active');
            $translations = OrbitInput::post('translations');

            $validator = Validator::make(
                array(
                    'object_type' => $object_type,
                    'status' => $status,
                ),
                array(
                    'object_type' => 'required|in:seo_promotion_list,seo_coupon_list,seo_event_list,seo_store_list,seo_mall_list,seo_homepage',
                    'status' => 'in:active,inactive',
                )
            );

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $result = $this->validateAndSaveTranslations($country_id, $object_type, $translations, $status, 'update');

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';
            $this->response->data = $result;

            // Commit the changes
            $this->commit();

        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();
        } catch (InvalidArgsException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
            // Rollback the changes
            $this->rollBack();
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

            // Rollback the changes
            $this->rollBack();
        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();
        }

        return $this->render($httpCode);
    }

    /**
     * GET - Listing SeoText
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string     `object_type`    (optional) - object_type
     * @param string     `status`         (optional) - Status. Valid value: active, inactive
     * @param string     `language`       (optional) - language code like en,id,jp,etc
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchSeoText()
    {

        try {
            $httpCode = 200;
            // Require authentication
            $this->checkAuth();
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->modifySeoTextRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $object_type = OrbitInput::post('object_type');

            $validator = Validator::make(
                array(
                    'object_type' => $object_type,
                ),
                array(
                    'object_type' => 'in:seo_promotion_list,seo_coupon_list,seo_event_list,seo_store_list,seo_mall_list,seo_homepage',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $seo_texts = Page::select('pages_id', 'title', 'content as description', 'object_type', 'language', 'status')
                              ->where('object_type', 'like', '%seo_%');

            OrbitInput::get('status', function($status) use ($seo_texts) {
                $seo_texts->where('pages.status', '=', $status);
            });

            OrbitInput::get('object_type', function($object_type) use ($seo_texts) {
                $seo_texts->where('pages.object_type', '=', $object_type);
            });

            OrbitInput::get('language', function($language) use ($seo_texts) {
                $seo_texts->where('pages.language', '=', $language);
            });

            $_seo_texts = clone $seo_texts;

            $list_seo_texts = $seo_texts->get();
            $count = RecordCounter::create($_seo_texts)->count();

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($list_seo_texts);
            $this->response->data->records = $list_seo_texts;

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
            $this->response->data = null;
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
        }

        return $this->render($httpCode);
    }


    private function validateAndSaveTranslations($country_id, $object_type, $translations, $status, $operation)
    {
        $valid_fields = ['title', 'description'];
        $data = @json_decode($translations);
        $page = [];
        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
        }

        if ($operation == 'update') {
            // delete all value and them insert all new value
            $existing_translation = Page::where('country_id', '=', '0')
                         ->where('object_type', '=', $object_type)
                         ->delete();
        }

        foreach ($data as $language_id => $translations) {
            $language = Language::where('status', '=', 'active')
                ->where('language_id', '=', $language_id)
                ->first();
            if (empty($language)) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.merchant_language'));
            }

            if ($translations === null) {
                // deleting, verify exists
            } else {

                foreach ($translations as $field => $value) {
                    if (!in_array($field, $valid_fields, TRUE)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.key'));
                    }
                    if ($value !== null && !is_string($value)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.value'));
                    }
                }

                // Insert every single seo per language_id
                $new_page = new Page();
                $new_page->country_id = $country_id;
                $new_page->object_type = $object_type;
                $new_page->language = $language->name;
                $new_page->title = $translations->title;
                $new_page->content = $translations->description;
                $new_page->status =$status;
                $new_page->save();
                $page[] = $new_page;
            }
        }

        return $page;
    }
}