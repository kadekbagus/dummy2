<?php

// post feedback
Route::post('/api/v1/pub/send-feedback', ['as' => 'pub-send-feedback', 'uses' => 'IntermediatePubAuthController@Feedback_postSendFeedback']);