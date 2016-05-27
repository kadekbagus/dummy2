<?php
/**
 * An API controller for managing Category.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class CategoryAPIController extends ControllerAPI
{
    protected $valid_default_lang = '';
    protected $valid_lang = '';
    protected $valid_category = '';
    protected $update_valid_category = '';
    /**
     * POST - Create New Category
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Tian <tian@dominopos.com>
     * @author Irianto Pratama <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string     `category_name`            (required) - Category name
     * @param integer    `category_level`           (optional) - Category level.
     * @param integer    `category_order`           (optional) - Category order
     * @param string     `description`              (optional) - Description
     * @param string     `status`                   (required) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param integer    `default_language`      (required) - ID language default
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewCategory()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newcategory = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.category.postnewcategory.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.category.postnewcategory.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.category.postnewcategory.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('create_category')) {
                Event::fire('orbit.category.postnewcategory.authz.notallowed', array($this, $user));
                $createCategoryLang = Lang::get('validation.orbit.actionlist.new_category');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createCategoryLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.category.postnewcategory.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $category_name = OrbitInput::post('category_name');
            $category_level = OrbitInput::post('category_level');
            $category_order = OrbitInput::post('category_order');
            $description = OrbitInput::post('description');
            $status = OrbitInput::post('status');
            $default_language = OrbitInput::post('default_language');

            $validator = Validator::make(
                array(
                    'category_name'    => $category_name,
                    'category_level'   => $category_level,
                    'category_order'   => $category_order,
                    'status'           => $status,
                    'default_language' => $default_language,
                ),
                array(
                    'category_name'    => 'required|orbit.exists.category_name',
                    'category_level'   => 'numeric',
                    'category_order'   => 'numeric',
                    'status'           => 'required|orbit.empty.category_status',
                    'default_language' => 'required|orbit.empty.default_en',
                )
            );

            Event::fire('orbit.category.postnewcategory.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.category.postnewcategory.after.validation', array($this, $validator));

            $language = $this->valid_default_lang;

            $newcategory = new Category();
            $newcategory->merchant_id = 0;
            $newcategory->category_name = $category_name;
            $newcategory->category_level = $category_level;
            $newcategory->category_order = $category_order;
            $newcategory->description = $description;
            $newcategory->status = $status;
            $newcategory->created_by = $this->api->user->user_id;
            $newcategory->modified_by = $this->api->user->user_id;

            Event::fire('orbit.category.postnewcategory.before.save', array($this, $newcategory));

            $newcategory->save();

            Event::fire('orbit.category.postnewcategory.after.save', array($this, $newcategory));

            // @author Irianto Pratama <irianto@dominopos.com>
            $default_translation = [
                $language->language_id => [
                    'category_name' => $newcategory->category_name,
                    'description' => $newcategory->description
                ]
            ];
            $this->validateAndSaveTranslations($newcategory, json_encode($default_translation), 'create');

            OrbitInput::post('translations', function($translation_json_string) use ($newcategory) {
                $this->validateAndSaveTranslations($newcategory, $translation_json_string, 'create');
            });

            $this->response->data = $newcategory;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Category Created: %s', $newcategory->category_name);
            $activity->setUser($user)
                    ->setActivityName('create_category')
                    ->setActivityNameLong('Create Category OK')
                    ->setObject($newcategory)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.category.postnewcategory.after.commit', array($this, $newcategory));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.category.postnewcategory.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_category')
                    ->setActivityNameLong('Create Category Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.category.postnewcategory.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_category')
                    ->setActivityNameLong('Create Category Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.category.postnewcategory.query.error', array($this, $e));

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

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_category')
                    ->setActivityNameLong('Create Category Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.category.postnewcategory.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_category')
                    ->setActivityNameLong('Create Category Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update Category
     *
     * @author <Kadek> <kadek@dominopos.com>
     * @author <Tian> <tian@dominopos.com>
     * @author <Irianto Pratama> <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `category_id`           (required) - Category ID
     * @param string     `category_name`         (optional) - Category name
     * @param integer    `category_level`        (optional) - Category level.
     * @param integer    `category_order`        (optional) - Category order
     * @param string     `description`           (optional) - Description
     * @param string     `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param integer    `default_language`   (required) - ID language default
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateCategory()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatedcategory = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.category.postupdatecategory.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.category.postupdatecategory.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.category.postupdatecategory.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('update_category')) {
                Event::fire('orbit.category.postupdatecategory.authz.notallowed', array($this, $user));
                $updateCategoryLang = Lang::get('validation.orbit.actionlist.update_category');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateCategoryLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.category.postupdatecategory.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $category_id = OrbitInput::post('category_id');
            $category_name = OrbitInput::post('category_name');
            $category_level = OrbitInput::post('category_level');
            $category_order = OrbitInput::post('category_order');
            $description = OrbitInput::post('description');
            $status = OrbitInput::post('status');
            $default_language = OrbitInput::post('default_language');

            $validator = Validator::make(
                array(
                    'category_id'      => $category_id,
                    'category_name'    => $category_name,
                    'category_level'   => $category_level,
                    'category_order'   => $category_order,
                    'status'           => $status,
                    'default_language' => $default_language,
                ),
                array(
                    'category_id'      => 'required|orbit.empty.category',
                    'category_name'    => 'category_name_exists_but_me:'.$category_id,
                    'category_level'   => 'numeric',
                    'category_order'   => 'numeric',
                    'status'           => 'orbit.empty.category_status',
                    'default_language' => 'required|orbit.empty.default_en',
                ),
                array(
                   'category_name_exists_but_me' => Lang::get('validation.orbit.exists.category_name'),
                )
            );

            Event::fire('orbit.category.postupdatecategory.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.category.postupdatecategory.after.validation', array($this, $validator));

            $language = $this->valid_default_lang;

            $updatedcategory = Category::excludeDeleted()->allowedForUser($user)->where('category_id', $category_id)->first();

            OrbitInput::post('category_name', function($category_name) use ($updatedcategory) {
                $updatedcategory->category_name = $category_name;
            });

            OrbitInput::post('category_level', function($category_level) use ($updatedcategory) {
                $updatedcategory->category_level = $category_level;
            });

            OrbitInput::post('category_order', function($category_order) use ($updatedcategory) {
                $updatedcategory->category_order = $category_order;
            });

            OrbitInput::post('description', function($description) use ($updatedcategory) {
                $updatedcategory->description = $description;
            });

            OrbitInput::post('status', function($status) use ($updatedcategory) {
                $updatedcategory->status = $status;
            });

            // @author Irianto Pratama <irianto@dominopos.com>
            $default_translation = [
                $language->language_id => [
                    'category_name' => $updatedcategory->category_name,
                    'description' => $updatedcategory->description
                ]
            ];
            $this->validateAndSaveTranslations($updatedcategory, json_encode($default_translation), 'update');

            OrbitInput::post('translations', function($translation_json_string) use ($updatedcategory) {
                $this->validateAndSaveTranslations($updatedcategory, $translation_json_string, 'update');
            });

            $updatedcategory->modified_by = $this->api->user->user_id;

            Event::fire('orbit.category.postupdatecategory.before.save', array($this, $updatedcategory));

            $updatedcategory->save();

            Event::fire('orbit.category.postupdatecategory.after.save', array($this, $updatedcategory));
            $this->response->data = $updatedcategory;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Category updated: %s', $updatedcategory->category_name);
            $activity->setUser($user)
                    ->setActivityName('update_category')
                    ->setActivityNameLong('Update Category OK')
                    ->setObject($updatedcategory)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.category.postupdatecategory.after.commit', array($this, $updatedcategory));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.category.postupdatecategory.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_category')
                    ->setActivityNameLong('Update Category Failed')
                    ->setObject($updatedcategory)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.category.postupdatecategory.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_category')
                    ->setActivityNameLong('Update Category Failed')
                    ->setObject($updatedcategory)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.category.postupdatecategory.query.error', array($this, $e));

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

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_category')
                    ->setActivityNameLong('Update Category Failed')
                    ->setObject($updatedcategory)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.category.postupdatecategory.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_category')
                    ->setActivityNameLong('Update Category Failed')
                    ->setObject($updatedcategory)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);

    }

    /**
     * POST - Delete Category
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `category_id`                  (required) - ID of the category
     * @param string     `is_validation`                (optional) - Valid value: Y. Flag to validate only when deleting category.
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteCategory()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $deletecategory = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.category.postdeletecategory.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.category.postdeletecategory.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.category.postdeletecategory.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('delete_category')) {
                Event::fire('orbit.category.postdeletecategory.authz.notallowed', array($this, $user));
                $deleteCategoryLang = Lang::get('validation.orbit.actionlist.delete_category');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteCategoryLang));
                ACL::throwAccessForbidden($message);
            }
*/

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = ['super admin', 'mall admin', 'mall owner'];
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.category.postdeletecategory.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $category_id = OrbitInput::post('category_id');
            $is_validation = OrbitInput::post('is_validation');

            $validator = Validator::make(
                array(
                    'category_id' => $category_id,
                ),
                array(
                    'category_id' => 'required|orbit.empty.category',
                )
            );

            Event::fire('orbit.category.postdeletecategory.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            } elseif ($is_validation === 'Y') { // the deletion request is only for validation
                $this->response->code = 0;
                $this->response->status = 'success';
                $this->response->message = 'Request OK';
                $this->response->data = NULL;

                return $this->render($httpCode);
            }

            Event::fire('orbit.category.postdeletecategory.after.validation', array($this, $validator));

            $deletecategory = Category::excludeDeleted()->allowedForUser($user)->where('category_id', $category_id)->first();

            // check link tenant category
            $link_category = CategoryMerchant::leftJoin('categories', 'categories.category_id', '=', 'category_merchant.category_id')
                                ->leftJoin('merchants', 'merchants.merchant_id', '=', 'category_merchant.merchant_id')
                                ->where('categories.status', '!=', 'deleted')
                                ->where('merchants.status', '!=', 'deleted')
                                ->where('category_merchant.category_id', $category_id)
                                ->first();
            if (count($link_category) > 0) {
                $errorMessage = Lang::get('validation.orbit.exists.link_category', ['link' => 'tenants']);
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $deletecategory->status = 'deleted';
            $deletecategory->modified_by = $this->api->user->user_id;

            foreach ($deletecategory->translations as $translation) {
                $translation->modified_by = $this->api->user->user_id;
                $translation->delete();
            }

            Event::fire('orbit.category.postdeletecategory.before.save', array($this, $deletecategory));

            $deletecategory->save();

            Event::fire('orbit.category.postdeletecategory.after.save', array($this, $deletecategory));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.category');

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('Category Deleted: %s', $deletecategory->category_name);
            $activity->setUser($user)
                    ->setActivityName('delete_category')
                    ->setActivityNameLong('Delete Category OK')
                    ->setObject($deletecategory)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.category.postdeletecategory.after.commit', array($this, $deletecategory));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.category.postdeletecategory.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_category')
                    ->setActivityNameLong('Delete Category Failed')
                    ->setObject($deletecategory)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.category.postdeletecategory.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_category')
                    ->setActivityNameLong('Delete Category Failed')
                    ->setObject($deletecategory)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.category.postdeletecategory.query.error', array($this, $e));

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

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_category')
                    ->setActivityNameLong('Delete Category Failed')
                    ->setObject($deletecategory)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.category.postdeletecategory.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_category')
                    ->setActivityNameLong('Delete Category Failed')
                    ->setObject($deletecategory)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.category.postdeletecategory.before.render', array($this, $output));

        // Save the activity
        $activity->save();

        return $output;
    }

    /**
     * GET - Search Category
     *
     * @author Kadek <kadek@dominopos.com>
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `sortby`                (optional) - column order by. Valid value: registered_date, category_name, category_level, category_order, description, status.
     * @param string   `sortmode`              (optional) - asc or desc
     * @param array    `category_id`           (optional) - Category ID
     * @param array    `merchant_id`           (optional) - Merchant ID
     * @param array    `category_name`         (optional) - Category name
     * @param string   `category_name_like`    (optional) - Category name like
     * @param array    `category_level`        (optional) - Category level. Valid value: 1 to 5.
     * @param array    `category_order`        (optional) - Category order
     * @param array    `description`           (optional) - Description
     * @param string   `description_like`      (optional) - Description like
     * @param array    `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @param integer  `skip`                  (optional) - limit offset
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchCategory()
    {
        // flag for limit the query result
        // TODO : should be change in the future
        $limit = FALSE;
        try {
            $httpCode = 200;

            Event::fire('orbit.category.getsearchcategory.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.category.getsearchcategory.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');

            // TODO : change this into something else
            $limited = OrbitInput::get('limited');

            if ($limited === 'yes') {
                $limit = TRUE;
            }

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,category_name,category_level,category_order,description,status,translation_category_name',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.category_sortby'),
                )
            );

            Event::fire('orbit.category.getsearchcategory.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.category.getsearchcategory.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.product_category.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.product_category.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            // if flag limit is true then show only category_id and category_name to make the frontend life easier
            // TODO : remove this with something like is_all_retailer on orbit-shop
            if ($limit) {
                $categories = Category::select('categories.category_id','category_name')->excludeDeleted('categories');
            } else {
                $categories = Category::excludeDeleted('categories');
            }

            // Filter category by Ids
            OrbitInput::get('category_id', function($categoryIds) use ($categories)
            {
                $categories->whereIn('categories.category_id', $categoryIds);
            });

            // Filter category by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($categories) {
                $categories->whereIn('categories.merchant_id', (array)$merchantIds);
            });

            // Filter category by category name
            OrbitInput::get('category_name', function($categoryname) use ($categories)
            {
                $categories->whereIn('categories.category_name', $categoryname);
            });

            // Filter category by matching category name pattern
            OrbitInput::get('category_name_like', function($categoryname) use ($categories)
            {
                $categories->where('categories.category_name', 'like', "%$categoryname%");
            });

            // Filter category by category level
            OrbitInput::get('category_level', function($categoryLevels) use ($categories)
            {
                $categories->whereIn('categories.category_level', $categoryLevels);
            });

            // Filter category by category order
            OrbitInput::get('category_order', function($categoryOrders) use ($categories)
            {
                $categories->whereIn('categories.category_order', $categoryOrders);
            });

            // Filter category by description
            OrbitInput::get('description', function($description) use ($categories)
            {
                $categories->whereIn('categories.description', $description);
            });

            // Filter category by matching description pattern
            OrbitInput::get('description_like', function($description) use ($categories)
            {
                $categories->where('categories.description', 'like', "%$description%");
            });

            // Filter category by status
            OrbitInput::get('status', function ($status) use ($categories) {
                $categories->whereIn('categories.status', $status);
            });

            OrbitInput::get('with', function ($with) use ($categories) {
                if (!is_array($with)) {
                    $with = [$with];
                }
                foreach ($with as $rel) {
                    if (in_array($rel, ['translations'])) {
                        $categories->with($rel);
                    }
                }
            });

            // filter by language id
            OrbitInput::get('language_id', function($language_id) use ($categories) {
                $prefix = DB::getTablePrefix();

                $categories->selectRaw("{$prefix}categories.*");
                $categories->leftJoin('category_translations', 'category_translations.category_id', '=', 'categories.category_id');
                $categories->where('category_translations.merchant_language_id', $language_id);
            });

            // Filter category by matching category name pattern
            OrbitInput::get('translation_category_name_like', function($categoryname) use ($categories)
            {
                $categories->where('category_translations.category_name', 'like', "%$categoryname%");
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_categories = clone $categories;

            // if limit is true show all records
            // TODO : replace this with something else in the future
            if (!$limit) {
                // Get the take args
                $take = $perPage;
                OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;

                    if ((int)$take <= 0) {
                        $take = $maxRecord;
                    }
                });
                $categories->take($take);

                $skip = 0;
                OrbitInput::get('skip', function($_skip) use (&$skip, $categories)
                {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                });
                $categories->skip($skip);
            }

            // Default sort by
            $sortBy = 'categories.category_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'           => 'categories.created_at',
                    'category_name'             => 'categories.category_name',
                    'category_level'            => 'categories.category_level',
                    'category_order'            => 'categories.category_order',
                    'description'               => 'categories.description',
                    'status'                    => 'categories.status',
                    'translation_category_name' => 'category_translations.category_name'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $categories->orderBy($sortBy, $sortMode);

            // @TODO: quick solving.
            // also sort by name when level is being sorted.
            if ($sortBy === 'categories.category_level') {
                $categories->orderBy('categories.category_name', 'asc');
            }

            $totalCategories = RecordCounter::create($_categories)->count();
            $listOfCategories = $categories->get();

            $data = new stdclass();
            $data->total_records = $totalCategories;
            $data->returned_records = count($listOfCategories);
            $data->records = $listOfCategories;

            if ($totalCategories === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.categories');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.category.getsearchcategory.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.category.getsearchcategory.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.category.getsearchcategory.query.error', array($this, $e));

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
            Event::fire('orbit.category.getsearchcategory.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.category.getsearchcategory.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of default_language
        Validator::extend('orbit.empty.default_en', function ($attribute, $value, $parameters) {
            $lang = Language::excludeDeleted()
                        ->where('name', $value)
                        ->first();

            if (empty($lang) || $value !== 'en') {
                return FALSE;
            }

            $this->valid_default_lang = $lang;

            return TRUE;
        });

        // Check the existance of language
        Validator::extend('orbit.empty.language', function ($attribute, $value, $parameters) {
            $lang = Language::excludeDeleted()
                        ->where('name', $value)
                        ->first();

            if (empty($lang)) {
                return FALSE;
            }

            $this->valid_lang = $lang;

            return TRUE;
        });

        // Check the existance of category id
        Validator::extend('orbit.empty.category', function ($attribute, $value, $parameters) {
            $category = Category::excludeDeleted()
                        ->where('category_id', $value)
                        ->first();

            if (empty($category)) {
                return FALSE;
            }

            $this->valid_category = $category;

            return TRUE;
        });

        // Check the existance of merchant id
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) {
            $merchant = Mall::excludeDeleted()
                        ->isMall()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($merchant)) {
                return FALSE;
            }

            App::instance('orbit.empty.merchant', $merchant);

            return TRUE;
        });

        // Check category name, it should not exists
        Validator::extend('orbit.exists.category_name', function ($attribute, $value, $parameters) {
            $categoryName = Category::excludeDeleted()
                        ->where('category_name', $value)
                        ->first();

            if (! empty($categoryName)) {
                return FALSE;
            }

            App::instance('orbit.validation.category_name', $categoryName);

            return TRUE;
        });

        // Check category name, it should not exists (for update)
        Validator::extend('category_name_exists_but_me', function ($attribute, $value, $parameters) {
            $category_id = trim($parameters[0]);

            $category = Category::excludeDeleted()
                        ->where('category_name', $value)
                        ->where('category_id', '!=', $category_id)
                        ->first();

            if (! empty($category)) {
                return FALSE;
            }

            $this->update_valid_category = $category;

            return TRUE;
        });

        // Check the existence of the category status
        Validator::extend('orbit.empty.category_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'pending', 'blocked', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

    }

    /**
     * @param Category $category
     * @param string $translations_json_string
     * @param string $scenario 'create' / 'update'
     * @throws InvalidArgsException
     */
    private function validateAndSaveTranslations($category, $translations_json_string, $scenario = 'create')
    {
        /*
         * JSON structure: object with keys = merchant_language_id and values = ProductTranslation object or null
         *
         * Having a value of null means deleting the translation
         *
         * where CategoryTranslation object is object with keys:
         *   category_name, description
         *
         * No requirement for including fields. If field not included it means not updated. If field included with
         * value null it means set to null (use main language content instead).
         */

        $valid_fields = ['category_name', 'description'];
        $user = $this->api->user;
        $operations = [];

        $data = @json_decode($translations_json_string);
        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
        }
        foreach ($data as $language_id => $translations) {
            $language = Language::excludeDeleted()
                ->where('language_id', '=', $language_id)
                ->first();
            if (empty($language)) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.language'));
            }
            $existing_translation = CategoryTranslation::excludeDeleted()
                ->where('category_id', '=', $category->category_id)
                ->where('merchant_language_id', '=', $language_id)
                ->first();
            if ($translations === null) {
                // deleting, verify exists
                if (empty($existing_translation)) {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.language'));
                }
                $operations[] = ['delete', $existing_translation];
            } else {
                foreach ($translations as $field => $value) {
                    if (!in_array($field, $valid_fields, TRUE)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.key'));
                    }
                    if ($value !== null && !is_string($value)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.value'));
                    }
                }
                if (empty($existing_translation)) {
                    if (! empty(trim($translations->category_name))) {
                        $category_translation = CategoryTranslation::excludeDeleted()
                                                    ->where('merchant_language_id', '=', $language_id)
                                                    ->where('category_name', '=', $translations->category_name)
                                                    ->first();
                        if (! empty($category_translation)) {
                            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.exists.category_name'));
                        }
                    }
                    $operations[] = ['create', $language_id, $translations];
                } else {
                    if (! empty(trim($translations->category_name))) {
                        $category_translation_but_not_me = CategoryTranslation::excludeDeleted()
                                                    ->where('merchant_language_id', '=', $language_id)
                                                    ->where('category_id', '!=', $category->category_id)
                                                    ->where('category_name', '=', $translations->category_name)
                                                    ->first();
                        if (! empty($category_translation_but_not_me)) {
                            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.exists.category_name'));
                        }
                    }
                    $operations[] = ['update', $existing_translation, $translations];
                }
            }
        }

        foreach ($operations as $operation) {
            $op = $operation[0];
            if ($op === 'create') {
                $new_translation = new CategoryTranslation();
                $new_translation->category_id = $category->category_id;
                $new_translation->merchant_language_id = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $new_translation->{$field} = $value;
                }
                $new_translation->created_by = $this->api->user->user_id;
                $new_translation->modified_by = $this->api->user->user_id;
                $new_translation->save();

                $category->setRelation('translation_'. $new_translation->merchant_language_id, $new_translation);
            }
            elseif ($op === 'update') {
                /** @var CategoryTranslation $existing_translation */
                $existing_translation = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $existing_translation->{$field} = $value;
                }
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->save();

                $category->setRelation('translation_'. $existing_translation->merchant_language_id, $existing_translation);
            }
            elseif ($op === 'delete') {
                /** @var CategoryTranslation $existing_translation */
                $existing_translation = $operation[1];
                $existing_translation->modified_by = $this->api->user->user_id;
                $existing_translation->delete();
            }
        }
    }
}
