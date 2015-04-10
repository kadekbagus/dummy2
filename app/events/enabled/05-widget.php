<?php
/**
 * Event listener for Widget related events.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * Listen on:       `orbit.wiget.postnewwidget.after.save`
 *   Purpose:       Handle file upload on wiget creation
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @param WidgetAPIController $controller - The instance of the
 *                                          WidgetAPIController or its subclass
 * @param Widget $widget - Instance of object Widget
 */
Event::listen('orbit.widget.postnewwidget.after.save', function($controller, $widget)
{
    // No need to upload if the animation are set to NOT "none"
    $animation = OrbitInput::post('animation');
    if ($animation !== 'none') {
        return;
    }

    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    $_POST['widget_id'] = $widget->widget_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('widget.new')
                                   ->postUploadWidgetImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['widget_id']);

    $widget->setRelation('media', $response->data);
    $widget->media = $response->data;
});

/**
 * Listen on:       `orbit.wiget.postupdatewidget.after.save`
 *   Purpose:       Handle file upload on wiget creation
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @param WidgetAPIController $controller - The instance of the
 *                                          WidgetAPIController or its subclass
 * @param Widget $widget - Instance of object Widget
 */
Event::listen('orbit.widget.postupdatewidget.after.save', function($controller, $widget)
{
    // No need to upload if the animation are set to NOT "none"
    $animation = OrbitInput::post('animation');
    if ($animation !== 'none') {
        return;
    }

    $files = OrbitInput::files('images');
    if (! $files) {
        return;
    }

    $_POST['widget_id'] = $widget->widget_id;
    $response = UploadAPIController::create('raw')
                                   ->setCalledFrom('widget.update')
                                   ->postUploadWidgetImage();

    if ($response->code !== 0)
    {
        throw new \Exception($response->message, $response->code);
    }
    unset($_POST['widget_id']);

    $widget->setRelation('media', $response->data);
    $widget->media = $response->data;
});
