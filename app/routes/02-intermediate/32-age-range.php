<?php

/**
 * List and/or Search age ranges
 */
Route::get('/app/v1/age-range/{search}', 'IntermediateAuthController@AgeRange_getSearchAgeRanges')
     ->where('search', '(list|search)');