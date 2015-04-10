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
    /**
     * POST - Create New Category
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `merchant_id`           (required) - Merchant ID
     * @param string     `category_name`         (required) - Category name
     * @param integer    `category_level`        (required) - Category Level. Valid value: 1 to 5.
     * @param string     `status`                (required) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param integer    `category_order`        (optional) - Category order
     * @param string     `description`           (optional) - Description
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

            if (! ACL::create($user)->isAllowed('create_category')) {
                Event::fire('orbit.category.postnewcategory.authz.notallowed', array($this, $user));
                $createCategoryLang = Lang::get('validation.orbit.actionlist.new_category');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createCategoryLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.category.postnewcategory.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $merchant_id = OrbitInput::post('merchant_id');
            $category_name = OrbitInput::post('category_name');
            $category_level = OrbitInput::post('category_level');
            $category_order = OrbitInput::post('category_order');
            $description = OrbitInput::post('description');
            $status = OrbitInput::post('status');

            $validator = Validator::make(
                array(
                    'merchant_id'    => $merchant_id,
                    'category_name'  => $category_name,
                    'category_level' => $category_level,
                    'status'         => $status,
                ),
                array(
                    'merchant_id'    => 'required|numeric|orbit.empty.merchant',
                    'category_name'  => 'required|orbit.exists.category_name:'.$merchant_id,
                    'category_level' => 'required|numeric|between:1,5',
                    'status'         => 'required|orbit.empty.category_status',
                )
            );

            Event::fire('orbit.category.postnewcategory.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Begin database transaction
            $this->beginTransaction();

            $newcategory = new Category();
            $newcategory->merchant_id = $merchant_id;
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
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `category_id`           (required) - Category ID
     * @param integer    `merchant_id`           (optional) - Merchant ID
     * @param string     `category_name`         (optional) - Category name
     * @param integer    `category_level`        (optional) - Category level. Valid value: 1 to 5.
     * @param integer    `category_order`        (optional) - Category order
     * @param string     `description`           (optional) - Description
     * @param string     `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
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

            if (! ACL::create($user)->isAllowed('update_category')) {
                Event::fire('orbit.category.postupdatecategory.authz.notallowed', array($this, $user));
                $updateCategoryLang = Lang::get('validation.orbit.actionlist.update_category');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateCategoryLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.category.postupdatecategory.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $category_id = OrbitInput::post('category_id');
            $merchant_id = OrbitInput::post('merchant_id');
            $category_name = OrbitInput::post('category_name');
            $category_level = OrbitInput::post('category_level');
            $category_order = OrbitInput::post('category_order');
            $description = OrbitInput::post('description');
            $status = OrbitInput::post('status');

            $validator = Validator::make(
                array(
                    'category_id'       => $category_id,
                    'merchant_id'       => $merchant_id,
                    'category_name'     => $category_name,
                    'category_level'    => $category_level,
                    'status'            => $status,
                ),
                array(
                    'category_id'       => 'required|numeric|orbit.empty.category',
                    'merchant_id'       => 'numeric|orbit.empty.merchant',
                    'category_name'     => 'category_name_exists_but_me:'.$category_id.','.$merchant_id,
                    'category_level'    => 'numeric|between:1,5',
                    'status'            => 'orbit.empty.category_status',
                ),
                array(
                   'category_name_exists_but_me' => Lang::get('validation.orbit.exists.category_name'),
                )
            );

            Event::fire('orbit.category.postupdatecategory.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.category.postupdatecategory.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $updatedcategory = Category::excludeDeleted()->allowedForUser($user)->where('category_id', $category_id)->first();

            OrbitInput::post('merchant_id', function($merchant_id) use ($updatedcategory) {
                if (! (trim($merchant_id) === '')) {
                    $updatedcategory->merchant_id = $merchant_id;
                }
            });

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

            if (! ACL::create($user)->isAllowed('delete_category')) {
                Event::fire('orbit.category.postdeletecategory.authz.notallowed', array($this, $user));
                $deleteCategoryLang = Lang::get('validation.orbit.actionlist.delete_category');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteCategoryLang));
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
                    'category_id' => 'required|numeric|orbit.empty.category|orbit.exists.have_product_category',
                )
            );

            Event::fire('orbit.category.postdeletecategory.before.validation', array($this, $validator));

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

            // Begin database transaction
            $this->beginTransaction();

            $deletecategory = Category::excludeDeleted()->allowedForUser($user)->where('category_id', $category_id)->first();
            $deletecategory->status = 'deleted';
            $deletecategory->modified_by = $this->api->user->user_id;

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
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchCategory()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.category.getsearchcategory.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.category.getsearchcategory.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.category.getsearchcategory.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_category')) {
                Event::fire('orbit.category.getsearchcategory.authz.notallowed', array($this, $user));
                $viewCategoryLang = Lang::get('validation.orbit.actionlist.view_category');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewCategoryLang));
                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.category.getsearchcategory.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,category_name,category_level,category_order,description,status',
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
            $categories = Category::excludeDeleted()->allowedForUser($user);

            // Filter category by Ids
            OrbitInput::get('category_id', function($categoryIds) use ($categories)
            {
                $categories->whereIn('categories.category_id', $categoryIds);
            });

            // Filter category by merchant Ids
            OrbitInput::get('merchant_id', function ($merchantIds) use ($categories) {
                $categories->whereIn('categories.merchant_id', $merchantIds);
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

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_categories = clone $categories;

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

            // Default sort by
            $sortBy = 'categories.category_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'   => 'categories.created_at',
                    'category_name'     => 'categories.category_name',
                    'category_level'    => 'categories.category_level',
                    'category_order'    => 'categories.category_order',
                    'description'       => 'categories.description',
                    'status'            => 'categories.status'
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
        // Check the existance of category id
        Validator::extend('orbit.empty.category', function ($attribute, $value, $parameters) {
            $category = Category::excludeDeleted()
                        ->where('category_id', $value)
                        ->first();

            if (empty($category)) {
                return FALSE;
            }

            App::instance('orbit.empty.category', $category);

            return TRUE;
        });

        // Check the existance of merchant id
        Validator::extend('orbit.empty.merchant', function ($attribute, $value, $parameters) {
            $merchant = Merchant::excludeDeleted()
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
            $merchant_id = $parameters[0];
            $categoryName = Category::excludeDeleted()
                        ->where('category_name', $value)
                        ->where('merchant_id', $merchant_id)
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
            $merchant_id = trim($parameters[1]);

            // if merchant_id not being updated, then get merchant_id from db.
            if ($merchant_id === '') {
                $category = Category::where('category_id', $category_id)->first();
                $merchant_id = $category->merchant_id;
            }

            $category = Category::excludeDeleted()
                        ->where('category_name', $value)
                        ->where('category_id', '!=', $category_id)
                        ->where('merchant_id', $merchant_id)
                        ->first();

            if (! empty($category)) {
                return FALSE;
            }

            App::instance('orbit.validation.category', $category);

            return TRUE;
        });

        // Check if category have linked to product / promotion / coupon.
        Validator::extend('orbit.exists.have_product_category', function ($attribute, $value, $parameters) {

            // check category if exists in products.
            $productcategory = Product::excludeDeleted()
                ->where(function ($query) use ($value) {
                    $query->where('category_id1', $value)
                        ->orWhere('category_id2', $value)
                        ->orWhere('category_id3', $value)
                        ->orWhere('category_id4', $value)
                        ->orWhere('category_id5', $value);
                })
                ->first();
            if (! empty($productcategory)) {
                return FALSE;
            }

            // check category if exists in promotions.
            $promotioncategory = Promotion::excludeDeleted()
                ->whereHas('promotionrule', function($query) use ($value) {
                    $query->where('discount_object_type', 'family')
                        ->where(function ($q) use ($value) {
                            $q->where('discount_object_id1', $value)
                                ->orWhere('discount_object_id2', $value)
                                ->orWhere('discount_object_id3', $value)
                                ->orWhere('discount_object_id4', $value)
                                ->orWhere('discount_object_id5', $value);
                    });
                })
                ->first();
            if (! empty($promotioncategory)) {
                return FALSE;
            }

            // check category if exists in coupons.
            $couponcategory = Coupon::excludeDeleted()
                ->whereHas('couponrule', function($query) use ($value) {
                    $query->where(function ($query) use ($value) {
                        $query
                        ->where(function ($query) use ($value) {
                            $query->where('discount_object_type', 'family')
                                  ->where(function ($query) use ($value) {
                                    $query->where('discount_object_id1', $value)
                                        ->orWhere('discount_object_id2', $value)
                                        ->orWhere('discount_object_id3', $value)
                                        ->orWhere('discount_object_id4', $value)
                                        ->orWhere('discount_object_id5', $value);
                            });
                        })
                        ->orWhere(function ($query) use ($value) {
                            $query->where('rule_object_type', 'family')
                                  ->where(function ($query) use ($value) {
                                    $query->where('rule_object_id1', $value)
                                        ->orWhere('rule_object_id2', $value)
                                        ->orWhere('rule_object_id3', $value)
                                        ->orWhere('rule_object_id4', $value)
                                        ->orWhere('rule_object_id5', $value);
                            });
                        });
                    });
                })
                ->first();
            if (! empty($couponcategory)) {
                return FALSE;
            }

            // check category if exists in events.
            $eventcategory = EventModel::excludeDeleted()
                ->where('link_object_type', 'family')
                ->where(function ($query) use ($value) {
                    $query->where('link_object_id1', $value)
                        ->orWhere('link_object_id2', $value)
                        ->orWhere('link_object_id3', $value)
                        ->orWhere('link_object_id4', $value)
                        ->orWhere('link_object_id5', $value);
                })
                ->first();
            if (! empty($eventcategory)) {
                return FALSE;
            }

            App::instance('orbit.exists.have_product_category', $productcategory);

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
}