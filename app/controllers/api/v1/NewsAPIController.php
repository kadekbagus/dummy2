<?php
/**
 * An API controller for managing News.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;

class NewsAPIController extends ControllerAPI
{
    /**
     * POST - Create New News
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `mall_id`               (required) - Mall ID
     * @param string     `object_type`           (optional) - Object type. Valid value: promotion, news.
     * @param string     `news_name`             (required) - News name
     * @param string     `status`                (required) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string     `description`           (optional) - Description
     * @param file       `images`                (optional) - News image
     * @param datetime   `begin_date`            (optional) - Begin date. Example: 2015-04-15 00:00:00
     * @param datetime   `end_date`              (optional) - End date. Example: 2015-04-18 23:59:59
     * @param integer    `sticky_order`          (optional) - Sticky order.
     * @param string     `link_object_type`      (optional) - Link object type. Valid value: tenant, tenant_category.
     * @param array      `retailer_ids`          (optional) - Retailer IDs
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewNews()
    {
        $activity = Activity::portal()
                            ->setActivityType('create');

        $user = NULL;
        $newnews = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.news.postnewnews.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.news.postnewnews.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.news.postnewnews.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('create_news')) {
                Event::fire('orbit.news.postnewnews.authz.notallowed', array($this, $user));
                $createNewsLang = Lang::get('validation.orbit.actionlist.new_news');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $createNewsLang));
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

            Event::fire('orbit.news.postnewnews.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $mall_id = OrbitInput::post('mall_id');
            $news_name = OrbitInput::post('news_name');
            $object_type = OrbitInput::post('object_type');
            $status = OrbitInput::post('status');
            $description = OrbitInput::post('description');
            $begin_date = OrbitInput::post('begin_date');
            $end_date = OrbitInput::post('end_date');
            $sticky_order = OrbitInput::post('sticky_order');
            $link_object_type = OrbitInput::post('link_object_type');
            $retailer_ids = OrbitInput::post('retailer_ids');
            $retailer_ids = (array) $retailer_ids;

            $validator = Validator::make(
                array(
                    'mall_id'            => $mall_id,
                    'news_name'          => $news_name,
                    'object_type'        => $object_type,
                    'status'             => $status,
                    'link_object_type'   => $link_object_type,
                ),
                array(
                    'mall_id'            => 'required|numeric|orbit.empty.mall',
                    'news_name'          => 'required|max:255|orbit.exists.news_name',
                    'object_type'        => 'orbit.empty.news_object_type',
                    'status'             => 'required|orbit.empty.news_status',
                    'link_object_type'   => 'orbit.empty.link_object_type',
                )
            );

            Event::fire('orbit.news.postnewnews.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            foreach ($retailer_ids as $retailer_id_check) {
                $validator = Validator::make(
                    array(
                        'retailer_id'   => $retailer_id_check,
                    ),
                    array(
                        'retailer_id'   => 'numeric|orbit.empty.retailer',
                    )
                );

                Event::fire('orbit.news.postnewnews.before.retailervalidation', array($this, $validator));

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    OrbitShopAPI::throwInvalidArgument($errorMessage);
                }

                Event::fire('orbit.news.postnewnews.after.retailervalidation', array($this, $validator));
            }

            Event::fire('orbit.news.postnewnews.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Reformat sticky order
            $sticky_order = (string)$sticky_order === 'true' || (string)$sticky_order !== '0' ? 1 : 0;

            // save News.
            $newnews = new News();
            $newnews->mall_id = $mall_id;
            $newnews->news_name = $news_name;
            $newnews->object_type = $object_type;
            $newnews->status = $status;
            $newnews->description = $description;
            $newnews->begin_date = $begin_date;
            $newnews->end_date = $end_date;
            $newnews->sticky_order = $sticky_order;
            $newnews->link_object_type = $link_object_type;
            $newnews->created_by = $this->api->user->user_id;

            Event::fire('orbit.news.postnewnews.before.save', array($this, $newnews));

            $newnews->save();

            // save NewsMerchant.
            $newsretailers = array();
            foreach ($retailer_ids as $retailer_id) {
                $newsretailer = new NewsMerchant();
                $newsretailer->merchant_id = $retailer_id;
                $newsretailer->news_id = $newnews->news_id;
                $newsretailer->object_type = 'retailer';
                $newsretailer->save();
                $newsretailers[] = $newsretailer;
            }
            $newnews->tenants = $newsretailers;

            Event::fire('orbit.news.postnewnews.after.save', array($this, $newnews));
            $this->response->data = $newnews;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('News Created: %s', $newnews->news_name);
            $activity->setUser($user)
                    ->setActivityName('create_news')
                    ->setActivityNameLong('Create News OK')
                    ->setObject($newnews)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.news.postnewnews.after.commit', array($this, $newnews));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.news.postnewnews.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_news')
                    ->setActivityNameLong('Create News Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.news.postnewnews.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_news')
                    ->setActivityNameLong('Create News Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.news.postnewnews.query.error', array($this, $e));

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
                    ->setActivityName('create_news')
                    ->setActivityNameLong('Create News Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.news.postnewnews.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_news')
                    ->setActivityNameLong('Create News Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * POST - Update News
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `news_id`               (required) - News ID
     * @param integer    `mall_id`               (optional) - Mall ID
     * @param string     `news_name`             (optional) - News name
     * @param string     `object_type`           (optional) - Object type. Valid value: promotion, news.
     * @param string     `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string     `description`           (optional) - Description
     * @param datetime   `begin_date`            (optional) - Begin date. Example: 2015-04-15 00:00:00
     * @param datetime   `end_date`              (optional) - End date. Example: 2015-04-18 23:59:59
     * @param integer    `sticky_order`          (optional) - Sticky order.
     * @param file       `images`                (optional) - News image
     * @param string     `link_object_type`      (optional) - Link object type. Valid value: tenant, tenant_category.
     * @param string     `no_retailer`           (optional) - Flag to delete all ORID links. Valid value: Y.
     * @param array      `retailer_ids`          (optional) - Retailer IDs
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postUpdateNews()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatednews = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.news.postupdatenews.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.news.postupdatenews.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.news.postupdatenews.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('update_news')) {
                Event::fire('orbit.news.postupdatenews.authz.notallowed', array($this, $user));
                $updateNewsLang = Lang::get('validation.orbit.actionlist.update_news');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $updateNewsLang));
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

            Event::fire('orbit.news.postupdatenews.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $news_id = OrbitInput::post('news_id');
            $mall_id = OrbitInput::post('mall_id');
            $object_type = OrbitInput::post('object_type');
            $status = OrbitInput::post('status');
            $link_object_type = OrbitInput::post('link_object_type');

            $data = array(
                'news_id'          => $news_id,
                'mall_id'          => $mall_id,
                'object_type'      => $object_type,
                'status'           => $status,
                'link_object_type' => $link_object_type,
            );

            // Validate news_name only if exists in POST.
            OrbitInput::post('news_name', function($news_name) use (&$data) {
                $data['news_name'] = $news_name;
            });

            $validator = Validator::make(
                $data,
                array(
                    'news_id'          => 'required|numeric|orbit.empty.news',
                    'mall_id'          => 'numeric|orbit.empty.mall',
                    'news_name'        => 'sometimes|required|min:5|max:255|news_name_exists_but_me',
                    'object_type'      => 'orbit.empty.news_object_type',
                    'status'           => 'orbit.empty.news_status',
                    'link_object_type' => 'orbit.empty.link_object_type',
                ),
                array(
                   'news_name_exists_but_me' => Lang::get('validation.orbit.exists.news_name'),
                )
            );

            Event::fire('orbit.news.postupdatenews.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.news.postupdatenews.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $updatednews = News::with('tenants')->excludeDeleted()->where('news_id', $news_id)->first();

            // save News
            OrbitInput::post('mall_id', function($mall_id) use ($updatednews) {
                $updatednews->mall_id = $mall_id;
            });

            OrbitInput::post('news_name', function($news_name) use ($updatednews) {
                $updatednews->news_name = $news_name;
            });

            OrbitInput::post('object_type', function($object_type) use ($updatednews) {
                $updatednews->object_type = $object_type;
            });

            OrbitInput::post('status', function($status) use ($updatednews) {
                $updatednews->status = $status;
            });

            OrbitInput::post('description', function($description) use ($updatednews) {
                $updatednews->description = $description;
            });

            OrbitInput::post('begin_date', function($begin_date) use ($updatednews) {
                $updatednews->begin_date = $begin_date;
            });

            OrbitInput::post('end_date', function($end_date) use ($updatednews) {
                $updatednews->end_date = $end_date;
            });

            OrbitInput::post('sticky_order', function($sticky_order) use ($updatednews) {
                // Reformat sticky order
                $sticky_order = (string)$sticky_order === 'true' || (string)$sticky_order !== '0' ? 1 : 0;

                $updatednews->sticky_order = $sticky_order;
            });

            OrbitInput::post('link_object_type', function($link_object_type) use ($updatednews) {
                if (trim($link_object_type) === '') {
                    $link_object_type = NULL;
                }
                $updatednews->link_object_type = $link_object_type;
            });

            $updatednews->modified_by = $this->api->user->user_id;

            Event::fire('orbit.news.postupdatenews.before.save', array($this, $updatednews));

            $updatednews->save();

            // save NewsMerchant
            OrbitInput::post('no_retailer', function($no_retailer) use ($updatednews) {
                if ($no_retailer == 'Y') {
                    $deleted_retailer_ids = NewsMerchant::where('news_id', $updatednews->news_id)->get(array('merchant_id'))->toArray();
                    $updatednews->tenants()->detach($deleted_retailer_ids);
                    $updatednews->load('tenants');
                }
            });

            OrbitInput::post('retailer_ids', function($retailer_ids) use ($updatednews) {
                // validate retailer_ids
                $retailer_ids = (array) $retailer_ids;
                foreach ($retailer_ids as $retailer_id_check) {
                    $validator = Validator::make(
                        array(
                            'merchant_id'   => $retailer_id_check,
                        ),
                        array(
                            'merchant_id'   => 'orbit.empty.retailer',
                        )
                    );

                    Event::fire('orbit.news.postupdatenews.before.retailervalidation', array($this, $validator));

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        OrbitShopAPI::throwInvalidArgument($errorMessage);
                    }

                    Event::fire('orbit.news.postupdatenews.after.retailervalidation', array($this, $validator));
                }
                // sync new set of retailer ids
                $pivotData = array_fill(0, count($retailer_ids), ['object_type' => 'retailer']);
                $syncData = array_combine($retailer_ids, $pivotData);
                $updatednews->tenants()->sync($syncData);

                // reload tenants relation
                $updatednews->load('tenants');
            });

            Event::fire('orbit.news.postupdatenews.after.save', array($this, $updatednews));
            $this->response->data = $updatednews;

            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('News updated: %s', $updatednews->news_name);
            $activity->setUser($user)
                    ->setActivityName('update_news')
                    ->setActivityNameLong('Update News OK')
                    ->setObject($updatednews)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.news.postupdatenews.after.commit', array($this, $updatednews));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.news.postupdatenews.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_news')
                    ->setActivityNameLong('Update News Failed')
                    ->setObject($updatednews)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.news.postupdatenews.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_news')
                    ->setActivityNameLong('Update News Failed')
                    ->setObject($updatednews)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.news.postupdatenews.query.error', array($this, $e));

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
                    ->setActivityName('update_news')
                    ->setActivityNameLong('Update News Failed')
                    ->setObject($updatednews)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.news.postupdatenews.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_news')
                    ->setActivityNameLong('Update News Failed')
                    ->setObject($updatednews)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
        $activity->save();

        return $this->render($httpCode);

    }

    /**
     * POST - Delete News
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param integer    `news_id`                  (required) - ID of the news
     * @param string     `password`                 (required) - master password
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postDeleteNews()
    {
        $activity = Activity::portal()
                          ->setActivityType('delete');

        $user = NULL;
        $deletenews = NULL;
        try {
            $httpCode = 200;

            Event::fire('orbit.news.postdeletenews.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.news.postdeletenews.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.news.postdeletenews.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('delete_news')) {
                Event::fire('orbit.news.postdeletenews.authz.notallowed', array($this, $user));
                $deleteNewsLang = Lang::get('validation.orbit.actionlist.delete_news');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $deleteNewsLang));
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

            Event::fire('orbit.news.postdeletenews.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $news_id = OrbitInput::post('news_id');
            $password = OrbitInput::post('password');

            $validator = Validator::make(
                array(
                    'news_id'  => $news_id,
                    'password' => $password,
                ),
                array(
                    'news_id'  => 'required|numeric|orbit.empty.news',
                    'password' => 'required|orbit.masterpassword.delete',
                ),
                array(
                    'required.password'             => 'The master is password is required.',
                    'orbit.masterpassword.delete'   => 'The password is incorrect.'
                )
            );

            Event::fire('orbit.news.postdeletenews.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.news.postdeletenews.after.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            $deletenews = News::excludeDeleted()->where('news_id', $news_id)->first();
            $deletenews->status = 'deleted';
            $deletenews->modified_by = $this->api->user->user_id;

            Event::fire('orbit.news.postdeletenews.before.save', array($this, $deletenews));

            // hard delete news-merchant.
            $deletenewsretailers = NewsMerchant::where('news_id', $deletenews->news_id)->get();
            foreach ($deletenewsretailers as $deletenewsretailer) {
                $deletenewsretailer->delete();
            }

            $deletenews->save();

            Event::fire('orbit.news.postdeletenews.after.save', array($this, $deletenews));
            $this->response->data = null;
            $this->response->message = Lang::get('statuses.orbit.deleted.news');

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('News Deleted: %s', $deletenews->news_name);
            $activity->setUser($user)
                    ->setActivityName('delete_news')
                    ->setActivityNameLong('Delete News OK')
                    ->setObject($deletenews)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.news.postdeletenews.after.commit', array($this, $deletenews));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.news.postdeletenews.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_news')
                    ->setActivityNameLong('Delete News Failed')
                    ->setObject($deletenews)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.news.postdeletenews.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_news')
                    ->setActivityNameLong('Delete News Failed')
                    ->setObject($deletenews)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.news.postdeletenews.query.error', array($this, $e));

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
                    ->setActivityName('delete_news')
                    ->setActivityNameLong('Delete News Failed')
                    ->setObject($deletenews)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.news.postdeletenews.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            $this->rollBack();

            // Deletion failed Activity log
            $activity->setUser($user)
                    ->setActivityName('delete_news')
                    ->setActivityNameLong('Delete News Failed')
                    ->setObject($deletenews)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        $output = $this->render($httpCode);

        // Save the activity
        $activity->save();

        return $output;
    }

    /**
     * GET - Search News
     *
     * @author Tian <tian@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string   `with`                  (optional) - Valid value: tenants.
     * @param string   `sortby`                (optional) - column order by
     * @param string   `sortmode`              (optional) - asc or desc
     * @param integer  `take`                  (optional) - limit
     * @param integer  `skip`                  (optional) - limit offset
     * @param integer  `news_id`               (optional) - News ID
     * @param integer  `mall_id`               (optional) - Mall ID
     * @param string   `news_name`             (optional) - News name
     * @param string   `news_name_like`        (optional) - News name like
     * @param string   `object_type`           (optional) - Object type. Valid value: promotion, news.
     * @param string   `description`           (optional) - Description
     * @param string   `description_like`      (optional) - Description like
     * @param datetime `begin_date`            (optional) - Begin date. Example: 2015-04-15 00:00:00
     * @param datetime `end_date`              (optional) - End date. Example: 2015-04-18 23:59:59
     * @param integer  `sticky_order`          (optional) - Sticky order.
     * @param string   `status`                (optional) - Status. Valid value: active, inactive, pending, blocked, deleted.
     * @param string   `link_object_type`      (optional) - Link object type. Valid value: tenant, tenant_category.
     * @param integer  `retailer_id`           (optional) - Retailer IDs
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchNews()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.news.getsearchnews.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.news.getsearchnews.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.news.getsearchnews.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_news')) {
                Event::fire('orbit.news.getsearchnews.authz.notallowed', array($this, $user));
                $viewNewsLang = Lang::get('validation.orbit.actionlist.view_news');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewNewsLang));
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

            Event::fire('orbit.news.getsearchnews.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');
            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:registered_date,news_name,object_type,description,begin_date,end_date,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.news_sortby'),
                )
            );

            Event::fire('orbit.news.getsearchnews.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.news.getsearchnews.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.news.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.news.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            // Builder object
            $news = News::excludeDeleted();

            // Filter news by Ids
            OrbitInput::get('news_id', function($newsIds) use ($news)
            {
                $news->whereIn('news.news_id', $newsIds);
            });

            // Filter news by mall Ids
            OrbitInput::get('mall_id', function ($mallIds) use ($news) {
                $news->whereIn('news.mall_id', $mallIds);
            });

            // Filter news by news name
            OrbitInput::get('news_name', function($newsname) use ($news)
            {
                $news->whereIn('news.news_name', $newsname);
            });

            // Filter news by matching news name pattern
            OrbitInput::get('news_name_like', function($newsname) use ($news)
            {
                $news->where('news.news_name', 'like', "%$newsname%");
            });

            // Filter news by object type
            OrbitInput::get('object_type', function($objectTypes) use ($news)
            {
                $news->whereIn('news.object_type', $objectTypes);
            });

            // Filter news by description
            OrbitInput::get('description', function($description) use ($news)
            {
                $news->whereIn('news.description', $description);
            });

            // Filter news by matching description pattern
            OrbitInput::get('description_like', function($description) use ($news)
            {
                $news->where('news.description', 'like', "%$description%");
            });

            // Filter news by begin date
            OrbitInput::get('begin_date', function($begindate) use ($news)
            {
                $news->where('news.begin_date', '<=', $begindate);
            });

            // Filter news by end date
            OrbitInput::get('end_date', function($enddate) use ($news)
            {
                $news->where('news.end_date', '>=', $enddate);
            });

            // Filter news by sticky order
            OrbitInput::get('sticky_order', function ($stickyorder) use ($news) {
                $news->whereIn('news.sticky_order', $stickyorder);
            });

            // Filter news by status
            OrbitInput::get('status', function ($statuses) use ($news) {
                $news->whereIn('news.status', $statuses);
            });

            // Filter news by link object type
            OrbitInput::get('link_object_type', function ($linkObjectTypes) use ($news) {
                $news->whereIn('news.link_object_type', $linkObjectTypes);
            });

            // Filter news merchants by retailer id
            OrbitInput::get('retailer_id', function ($retailerIds) use ($news) {
                $news->whereHas('tenants', function($q) use ($retailerIds) {
                    $q->whereIn('merchant_id', $retailerIds);
                });
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($news) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'tenants') {
                        $news->with('tenants');
                    }
                }
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_news = clone $news;

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
            $news->take($take);

            $skip = 0;
            OrbitInput::get('skip', function($_skip) use (&$skip, $news)
            {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $news->skip($skip);

            // Default sort by
            $sortBy = 'news.news_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function($_sortBy) use (&$sortBy)
            {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'registered_date'   => 'news.created_at',
                    'news_name'         => 'news.news_name',
                    'object_type'       => 'news.object_type',
                    'description'       => 'news.description',
                    'begin_date'        => 'news.begin_date',
                    'end_date'          => 'news.end_date',
                    'status'            => 'news.status'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sortMode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $news->orderBy($sortBy, $sortMode);

            $totalNews = RecordCounter::create($_news)->count();
            $listOfNews = $news->get();

            $data = new stdclass();
            $data->total_records = $totalNews;
            $data->returned_records = count($listOfNews);
            $data->records = $listOfNews;

            if ($totalNews === 0) {
                $data->records = NULL;
                $this->response->message = Lang::get('statuses.orbit.nodata.news');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.news.getsearchnews.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.news.getsearchnews.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.news.getsearchnews.query.error', array($this, $e));

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
            Event::fire('orbit.news.getsearchnews.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.news.getsearchnews.before.render', array($this, &$output));

        return $output;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of news id
        Validator::extend('orbit.empty.news', function ($attribute, $value, $parameters) {
            $news = News::excludeDeleted()
                        ->where('news_id', $value)
                        ->first();

            if (empty($news)) {
                return FALSE;
            }

            App::instance('orbit.empty.news', $news);

            return TRUE;
        });

        // Check the existance of mall id
        Validator::extend('orbit.empty.mall', function ($attribute, $value, $parameters) {
            $mall = Retailer::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($mall)) {
                return FALSE;
            }

            App::instance('orbit.empty.mall', $mall);

            return TRUE;
        });

        // Check news name, it should not exists
        Validator::extend('orbit.exists.news_name', function ($attribute, $value, $parameters) {
            $newsName = News::excludeDeleted()
                        ->where('news_name', $value)
                        ->first();

            if (! empty($newsName)) {
                return FALSE;
            }

            App::instance('orbit.validation.news_name', $newsName);

            return TRUE;
        });

        // Check news name, it should not exists (for update)
        Validator::extend('news_name_exists_but_me', function ($attribute, $value, $parameters) {
            $news_id = trim(OrbitInput::post('news_id'));
            $news = News::excludeDeleted()
                        ->where('news_name', $value)
                        ->where('news_id', '!=', $news_id)
                        ->first();

            if (! empty($news)) {
                return FALSE;
            }

            App::instance('orbit.validation.news_name', $news);

            return TRUE;
        });

        // Check the existence of the news status
        Validator::extend('orbit.empty.news_status', function ($attribute, $value, $parameters) {
            $valid = false;
            $statuses = array('active', 'inactive', 'pending', 'blocked', 'deleted');
            foreach ($statuses as $status) {
                if($value === $status) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the news object type
        Validator::extend('orbit.empty.news_object_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $objectTypes = array('promotion', 'news');
            foreach ($objectTypes as $objectType) {
                if($value === $objectType) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existence of the link object type
        Validator::extend('orbit.empty.link_object_type', function ($attribute, $value, $parameters) {
            $valid = false;
            $linkobjecttypes = array('tenant', 'tenant_category');
            foreach ($linkobjecttypes as $linkobjecttype) {
                if($value === $linkobjecttype) $valid = $valid || TRUE;
            }

            return $valid;
        });

        // Check the existance of retailer id
        Validator::extend('orbit.empty.retailer', function ($attribute, $value, $parameters) {
            $retailer = Retailer::excludeDeleted()
                        ->where('merchant_id', $value)
                        ->first();

            if (empty($retailer)) {
                return FALSE;
            }

            App::instance('orbit.empty.retailer', $retailer);

            return TRUE;
        });

        // News deletion master password
        Validator::extend('orbit.masterpassword.delete', function ($attribute, $value, $parameters) {
            // Current Mall location
            $currentMall = Config::get('orbit.shop.id');

            // Get the master password from settings table
            $masterPassword = Setting::getMasterPasswordFor($currentMall);

            if (! is_object($masterPassword)) {
                // @Todo replace with language
                $message = 'The master password is not set.';
                ACL::throwAccessForbidden($message);
            }

            if (! Hash::check($value, $masterPassword->setting_value)) {
                $message = 'The master password is incorrect.';
                ACL::throwAccessForbidden($message);
            }

            return TRUE;
        });

    }

}
