<?php
// Terms and conditions and Privacy Policy.
// ----------------------------------------
// In the future the content may different from one country
// to another. So it needs to passed to controller for advanced processing
//
// In Apache you should add something like configuration below to proxy
// the request to this route.
//
// --- Begin Apache Conf ---
// RedirectMatch "^/terms-and-conditions$" "/terms-and-conditions/"
// RedirectMatch "^/privacy-policy$" "/privacy-policy/"
// ProxyPass /terms-and-conditions/ http://api.gotomalls.cool/terms-and-conditions
// ProxyPass /privacy-policy/ http://api.gotomalls.cool/privacy-policy
// --- End Apache Conf ---
Route::get('terms-and-conditions', [ 'as' => 'terms_conditions', function() {
    return Page::where('object_type', 'terms_and_conditions')->firstOrFail()->content;
}]);

Route::get('privacy-policy', [ 'as' => 'terms_conditions', function() {
    return Page::where('object_type', 'privacy_policy')->firstOrFail()->content;
}]);