<?php
/**
 * Event listener for Membership related events.
 *
 * @author Tian <tian@dominopos.com>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:    `orbit.membership.postnewmembership.after.save`
 * Purpose:      Handle file upload on membership creation
 *
 * @param MembershipAPIController $controller - The instance of the MembershipAPIController or its subclass
 * @param Membership $membership - Instance of object Membership
 */
Event::listen('orbit.membership.postnewmembership.after.save', function($controller, $membership)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    // This will be used on UploadAPIController
    App::instance('orbit.upload.user', $controller->api->user);

    $_POST['membership_id'] = $membership->membership_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('membership.new')
                                   ->postUploadMembershipImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['membership_id']);

    $membership->setRelation('media', $response->data);
    $membership->media = $response->data;
});

/**
 * Listen on:       `orbit.membership.postupdatemembership.after.save`
 *   Purpose:       Handle file upload on membership update
 *
 * @param MembershipAPIController $controller - The instance of the MembershipAPIController or its subclass
 * @param Membership $membership - Instance of object Membership
 */
Event::listen('orbit.membership.postupdatemembership.after.save', function($controller, $membership)
{
    $images = OrbitInput::files('images');

    if (! empty($images)) {
        $_POST['membership_id'] = $membership->membership_id;

        // This will be used on UploadAPIController
        App::instance('orbit.upload.user', $controller->api->user);

        $response = UploadAPIController::create('raw')
                                       ->setCalledFrom('membership.update')
                                       ->postUploadMembershipImage();

        if ($response->code !== 0)
        {
            throw new \Exception($response->message, $response->code);
        }

        $membership->load('media');
    }

});
