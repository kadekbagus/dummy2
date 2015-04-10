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
