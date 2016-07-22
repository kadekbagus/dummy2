<?php

// Get supported account type
Route::get('/app/v1/account-type/list', 'IntermediateAuthController@AccountType_getSearchAccountType');