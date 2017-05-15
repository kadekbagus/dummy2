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

            $this->registerCustomValidation();

            $country_id = OrbitInput::post('country_id', '0');
            $object_type = OrbitInput::post('object_type');
            $language = OrbitInput::post('language');
            $content = OrbitInput::post('content');
            $status = OrbitInput::post('status', 'active');
            $translations = OrbitInput::post('translations');

            $validator = Validator::make(
                array(
                    'object_type' => $object_type,
                    'language' => $language,
                    'content' => $content,
                    'status' => $status,
                ),
                array(
                    'object_type' => 'required',
                    'language' => 'required|orbit.empty.language',
                    'content' => 'required',
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

            $new_page = new Page();
            $new_page->country_id = $country_id;
            $new_page->object_type = $object_type;
            $new_page->language = $language;
            $new_page->content = $content;
            $new_page->status =$status;
            $new_page->save();

            $this->response->data = $new_page;

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
}