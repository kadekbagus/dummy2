<?php
/**
 * Routes file for feedback
 */

/**
 * Post Feedback
 */
Route::post('/api/v1/pub/send-feedback', function()
{
    return Orbit\Controller\API\v1\Pub\FeedbackAPIController::create()->postSendFeedback();
});
