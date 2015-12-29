<?php

/**
 * List and/or Search age ranges
 */
Route::get('/app/v1/age-range/{search}', 'IntermediateAuthController@AgeRanges_getSearchAgeRanges')
     ->where('search', '(list|search)');