<?php

Route::post('/app/v1/pub/feedback/new', ['as' => 'pub-feedback-create', 'uses' => 'IntermediatePubAuthController@Feedback\FeedbackNew_postNewFeedback']);
