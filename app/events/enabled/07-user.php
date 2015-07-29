<?php
/**
 * Event listener for User related events.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

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
    unset($_POST['merchant_id']);

    $user->setRelation('media', $response->data);
    $user->media = $response->data;
    $user->userdetail->photo = $response->data[0]->path;
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
 * Listen on:       `orbit.user.postupdatemembership.after.commit`
 *   Purpose:       Handle events after membership being updated
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @param LoginAPIController $controller - The instance of the LoginAPIController or its subclass
 * @param User $customer - Instance of object User
 */
Event::listen('orbit.user.postupdatemembership.after.commit', function($controller, $customer)
{
    // @Todo the Retailer object should comes from parameter
    $retailerId = App::make('orbitSetting')->getSetting('current_retailer', 0);

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
 * @param LoginAPIController $controller - The instance of the LoginAPIController or its subclass
 * @param User $customer - Instance of object User
 */
Event::listen('orbit.user.postnewmembership.after.commit', function($controller, $customer)
{
    // @Todo the Retailer object should comes from parameter
    $retailerId = App::make('orbitSetting')->getSetting('current_retailer', 0);

    // Notify the queueing system
    Queue::push('Orbit\\Queue\\Notifier\\UserUpdateNotifier', [
        'user_id' => $customer->user_id,
        'retailer_id' => $retailerId
    ]);
});