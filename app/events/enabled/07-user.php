<?php
/**
 * Event listener for User related events.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Orbit\Queue\Notifier\UserUpdateNotifier as QUserUpdateNotifier;
use Orbit\Queue\Notifier\UserLoginNotifier as QUserLoginNotifier;
use Orbit\FakeJob;

/**
 * Listen on:       `orbit.user.postupdateuser.after.save`
 *   Purpose:       Handle file upload on User update
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @param UserAPIController $controller - The instance of the UserAPIController or its subclass
 * @param User $user - Instance of object User
 */
Event::listen('orbit.user.postupdateuser.after.save', function($controller, $user)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    $_POST['user_id'] = $user->user_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('user.update')
                                   ->postUploadUserImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['user_id']);

    $user->setRelation('media', $response->data);
    $user->media = $response->data;
    $user->userdetail->photo = $response->data[0]->path;

    // queue for data amazon s3
    $usingCdn = Config::get('orbit.cdn.upload_to_cdn', false);

    if ($usingCdn) {
        $bucketName = Config::get('orbit.cdn.providers.S3.bucket_name', '');
        $queueName = Config::get('orbit.cdn.queue_name', 'cdn_upload');
        $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadNewQueue';
        if ($response->data['extras']->isUpdate) {
            $queueFile = 'Orbit\\Queue\\CdnUpload\\CdnUploadUpdateQueue';
        }

        Queue::push($queueFile, [
            'object_id'     => $user->user_id,
            'media_name_id' => $response->data['extras']->mediaNameId,
            'old_path'      => $response->data['extras']->oldPath,
            'es_type'       => null,
            'es_id'         => null,
            'bucket_name'   => $bucketName
        ], $queueName);
    }
});

/**
 * Listen on:       `orbit.postlogininshop.login.done`
 *   Purpose:       Handle after user sign in on mobile
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @param LoginAPIController $controller - The instance of the LoginAPIController or its subclass
 * @param User $user - Instance of object User
 * @param User $retailer - Instance of object Retailer (could be also a mall)
 */
Event::listen('orbit.postlogininshop.login.done', function($controller, $user, $retailer)
{
    // Notify the queueing system
    Queue::push('Orbit\\Queue\\Notifier\\UserLoginNotifier', [
        'user_id' => $user->user_id,
        'retailer_id' => $retailer->merchant_id
    ]);
});

/**
 * Listen on:       `orbit.user.postupdatemembership.after.save`
 *   Purpose:       Handle events after membership being updated but not commited to database
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @param UserAPIController $controller - The instance of the UserAPIController or its subclass
 * @param User $customer - Instance of object User
 */
Event::listen('orbit.user.postupdatemembership.after.save', function($controller, $customer, $retailerId)
{
    // This event always executed when there is call to update membership
    // So we need to distinguish the call from an form interface such as CS Portal or
    // from direct API call

    // So the form need to send some flag that it wants to trigger notify
    // as an example from query string $_GET['do_notify']
    $doNotify = OrbitInput::post('orbit_api_do_notify', NULL);

    if ($doNotify !== 'yes') {
        return NULL;
    }

    // No need to run the notify if the setting value is not 'yes'
    $setting = Setting::excludeDeleted()
                      ->where('object_id', $retailerId)
                      ->where('object_type', 'merchant')
                      ->where('setting_name', 'realtime_notify_update_member')
                      ->first();

    if (! is_object($setting)) {
        Log::error('[Error] - Realtime notify update membership error.');
        return NULL;
    }

    if (trim($setting->setting_value) !== 'yes') {
        Log::info(sprintf('[INFO] - Setting value of `realtime_notify_update_member` retailer id %s is not yes.', $retailerId));
        return NULL;
    }

    $job = new FakeJob();
    $data = [
        'user_id' => $customer->user_id,
        'retailer_id' => $retailerId,
        'human_error' => TRUE
    ];

    // Notify the queueing system
    $notifier = new QUserUpdateNotifier();
    $response = $notifier->fire($job, $data);

    if ($response['status'] !== 'ok') {
        throw new Exception($response['message']);
    }
});

/**
 * Listen on:       `orbit.user.postupdatemembership.after.commit`
 *   Purpose:       Handle events after membership being updated
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @param UserAPIController $controller - The instance of the UserAPIController or its subclass
 * @param User $customer - Instance of object User
 */
Event::listen('orbit.user.postupdatemembership.after.commit', function($controller, $customer, $retailerId)
{
    // This event always executed when there is call to update membership
    // So we need to distinguish the call from an form interface such as CS Portal or
    // from direct API call

    // So the form need to send some flag that it wants to trigger notify
    // as an example from query string $_GET['do_notify']
    $doNotify = OrbitInput::post('orbit_api_do_notify', NULL);

    if ($doNotify !== 'yes') {
        return NULL;
    }

    // No need to run the notify if the setting value is not 'yes'
    $setting = Setting::excludeDeleted()
                      ->where('object_id', $retailerId)
                      ->where('object_type', 'merchant')
                      ->where('setting_name', 'notify_update_member')
                      ->first();

    if (! is_object($setting)) {
        Log::error('[Error] - Notify update membership error.');
        return NULL;
    }

    if (trim($setting->setting_value) !== 'yes') {
        Log::info(sprintf('[INFO] - Setting value of `notify_update_member` retailer id %s is not yes.', $retailerId));
        return NULL;
    }

    // Notify the queueing system
    Queue::push('Orbit\\Queue\\Notifier\\UserUpdateNotifier', [
        'user_id' => $customer->user_id,
        'retailer_id' => $retailerId
    ]);
});

/**
 * Listen on:       `orbit.user.postnewmembership.after.commit`
 *   Purpose:       Handle events after customer being created
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @param UserAPIController $controller - The instance of the UserAPIController or its subclass
 * @param User $customer - Instance of object User
 */
Event::listen('orbit.user.postnewmembership.after.commit', function($controller, $customer, $retailerId)
{
    // Send email after registration to the queue
    Queue::push('Orbit\\Queue\\RegistrationMail', [
        'user_id' => $customer->user_id,
        'mode' => 'gotomalls'],
        Config::get('orbit.registration.mobile.queue_name', 'gtm_email')
    );

    // This event always executed when there is call to update membership
    // So we need to distinguish the call from an form interface such as CS Portal or
    // from direct API call

    // So the form need to send some flag that it wants to trigger notify
    // as an example from query string $_GET['do_notify']
    $doNotify = OrbitInput::post('orbit_api_do_notify', NULL);

    if ($doNotify !== 'yes') {
        return NULL;
    }

    // No need to run the notify if the setting value is not 'yes'
    $setting = Setting::excludeDeleted()
                      ->where('object_id', $retailerId)
                      ->where('object_type', 'merchant')
                      ->where('setting_name', 'notify_new_member')
                      ->first();

    if (! is_object($setting)) {
        Log::error('[Error] - Notify update membership error.');
        return NULL;
    }

    if (trim($setting->setting_value) !== 'yes') {
        Log::info(sprintf('[INFO] - Setting value of `notify_new_member` retailer id %s is not yes.', $retailerId));
        return NULL;
    }

    // Notify the queueing system
    Queue::push('Orbit\\Queue\\Notifier\\UserUpdateNotifier', [
        'user_id' => $customer->user_id,
        'retailer_id' => $retailerId
    ]);
});


Event::listen('orbit.user.postupdateaccount.after.save', function($controller, $user)
{
    // update required 3party grab field
    Queue::push('Orbit\\Queue\\GTMRequirementFieldUpdateQueue', ['id' => $user->user_id, 'from' => 'pmp_admin_portal']);
});