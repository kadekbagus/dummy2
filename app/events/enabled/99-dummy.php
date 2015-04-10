<?php
/*
|--------------------------------------------------------------------------
| Example of Event Listen
|--------------------------------------------------------------------------
|
*/
use OrbitShop\API\v1\Helper\Input as OrbitInput;

/**
 * This event listen on `orbit.dummy.gethisname.before.render`. It will
 * try to intercept the output and do some weird things. This event fired
 * on DummyAPIController::hisName();
 *
 * The URL to trigger this event are
 * '/api/v1/dummy/hisname?call=chuck'
 *
 * @author Rio Astamal <me@rioastamal.net>
 * @param ControllerAPI $controller - The instance of the ControllerAPI or its subclass
 * @param Response $rendered - The Laravel Response
 */
Event::listen('orbit.dummy.gethisname.before.render', function($controller, &$rendered)
{
    $call = OrbitInput::get('call');

    // Only proceed if the Query string contains parameter 'call' and has value
    // 'chuck'
    if ($call !== 'chuck') {
        return FALSE;
    }

    // The default output would be something like this:
    // {"first_name":"John","last_name":"Smith"}

    // We would like to intercept it and change it to
    // {"first_name":"Chuck","last_name":"Norris"}
    $chuck = new stdclass();
    $chuck->first_name = 'Chuck';
    $chuck->last_name = 'Norris';
    $controller->response->data = $chuck;

    $rendered = $controller->render();
});
