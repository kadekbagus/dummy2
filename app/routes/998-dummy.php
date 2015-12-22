<?php
/**
 * A dummy route file
 */
Route::get('/api/v1/dummy/hisname', function()
{
    return DummyAPIController::create()->hisname();
});

Route::get('/api/v1/dummy/hisname/auth', function()
{
    return DummyAPIController::create()->hisnameAuth();
});

Route::get('/api/v1/dummy/hisname/authz', function()
{
    return DummyAPIController::create()->hisNameAuthz();
});

Route::post('/api/v1/dummy/myname', function()
{
    return DummyAPIController::create()->myName();
});

Route::post('/api/v1/dummy/myname/auth', function()
{
    return DummyAPIController::create()->myNameAuth();
});

Route::post('/api/v1/dummy/myname/authz', function()
{
    return DummyAPIController::create()->myNameAuthz();
});

Route::post('/api/v1/dummy/user/new', function()
{
    return DummyAPIController::create()->postRegisterUserAuthz();
});

Route::get('/customer/account', function() {
  return View::make('mobile-ci.account', array('page_title' => 'Akun'));
});

Route::get('/customer/google', function() {
  // get data from input
    $code = Input::get( 'code' );

    // get google service
    $googleService = OAuth::consumer( 'Google' );

    // check if code is valid

    // if code is provided get user data and sign in
    if ( !empty( $code ) ) {

        // This was a callback request from google, get the token
        $token = $googleService->requestAccessToken( $code );

        // Send a request with it
        $result = json_decode( $googleService->request( 'https://www.googleapis.com/oauth2/v1/userinfo' ), true );

        $message = 'Your unique Google user id is: ' . $result['id'] . ' and your name is ' . $result['name'];
        echo $message. "<br/>";

        //Var_dump
        //display whole array().
        dd($result);

    }
    // if not ask for permission first
    else {
        // get googleService authorization
        $url = $googleService->getAuthorizationUri();

        // return to google login url
        return Redirect::to( (string)$url );
        //dd($url);
    }
});
