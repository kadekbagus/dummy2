<?php

/**
 * List and/or Search Timezone
 */
Route::get('/app/v1/timezone/{search}', 'IntermediateAuthController@Timezone_getTimezone')
     ->where('search', '(list|search)');