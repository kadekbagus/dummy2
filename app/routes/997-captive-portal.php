<?php
/*
|--------------------------------------------------------------------------
| Apple Captive Portal Routing
|--------------------------------------------------------------------------
|
| Handle Apple captive portal behaviour, when user already logged in all
| request to these particular domain should return HTTP 200 OK response. But
| if user not logged in yet then it should return HTTP 302 Found, which
| return our mobile login page.
|
| @author Rio Astamal <me@rioastamal.net>
|
*/
$appleCaptiveDomains = [
    '17.149.160.87',
    '17.172.224.81',
    'apple.com',
    '{all}.apple.com', // wildcard apple.com sub domains
    'airport.us',
    '{all}.airport.us',
    'appleiphonecell.com',
    '{all}.appleiphonecell.com',
    'ibook.info',
    '{all}.ibook.info',
    'itools.info',
    '{all}.itools.info',
    'thinkdifferent.us',
    '{all}.thinkdifferent.us'
];

// I don't have any clue about the capabilities of Laravel routing when
// handling source which comes as array, so I just loop it.
foreach ($appleCaptiveDomains as $appleDomain) {
    Route::group(array('domain' => $appleDomain), function()
    {
        // Does the user already logged in?
        try {
            // Just return something as long as it 200 OK
            // Catch all URL
            Route::get('{all}', function() {
                // It should throws exception if session error or does not exist
                $firewall = new Net\Security\Firewall();
                $return = $firewall->isMacLoggedIn($_SERVER['REMOTE_ADDR']);
                $host = $_SERVER['HTTP_HOST'];

                if ($return['status'] !== TRUE) {
                    return Redirect::to('http://orbit.box/?from_captive=yes&e=' . urlencode($return['message']) . '&from=' . $host);
                }

                // We need to return exactly as this one below or the captive would
                // not working
                return "<HTML><HEAD><TITLE>Success</TITLE></HEAD><BODY>Success</BODY></HTML>";
            })->where('all', '.*');
        } catch (Exception $e) {
            // If we goes here then most likely the user has not logged in yet
            // So we need to redirect them to our login page, and this one
            // automatically trigger the Captive Portal on Apple Device

            // Catch all URL
            Route::get('{all}', function() use ($e) {
                return Redirect::to('http://orbit.box/?from_captive=yes&e=' . urlencode($e->getMessage()));
            })->where('all', '.*');
        }
    });
}
