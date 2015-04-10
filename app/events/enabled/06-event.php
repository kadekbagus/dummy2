<?php
/**
 * Event listener for Event related events.
 *
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:    `orbit.event.postnewevent.after.save`
 * Purpose:      Handle file upload on event creation
 *
 * @author Tian <tian@dominopos.com>
 *
 * @param EventAPIController $controller - The instance of the EventAPIController or its subclass
 * @param Event $event - Instance of object Event
 */
Event::listen('orbit.event.postnewevent.after.save', function($controller, $event)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    $_POST['event_id'] = $event->event_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('event.new')
                                   ->postUploadEventImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['event_id']);

    $event->setRelation('media', $response->data);
    $event->media = $response->data;
    $event->image = $response->data[0]->path;
});

/**
 * Listen on:    `orbit.event.postupdateevent.after.save`
 * Purpose:      Handle file upload on event update
 *
 * @author Tian <tian@dominopos.com>
 *
 * @param EventAPIController $controller - The instance of the EventAPIController or its subclass
 * @param Event $event - Instance of object Event
 */
Event::listen('orbit.event.postupdateevent.after.save', function($controller, $event)
{
    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('event.update')
                                   ->postUploadEventImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }

    $event->load('media');
    $event->image = $response->data[0]->path;
});
