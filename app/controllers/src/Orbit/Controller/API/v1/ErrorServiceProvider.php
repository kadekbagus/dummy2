<?php namespace Orbit\Controller\API\v1;

use Config;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use DominoPOS\OrbitACL\Exception\ACLUnauthenticatedException;
use DominoPOS\OrbitAPI\v10\StatusInterface;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\ServiceProvider;
use Log;
use OrbitShop\API\v1\ExceptionResponseProvider;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use Orbit\Helper\Util\CorsHeader;
use Response;
use View;

/**
 * Service provider related to Orbit error/exception.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ErrorServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Register custom exception handling, that dont catched
        // by controllers (exception before reaching controller@method).
        $this->app->error(function(Exception $e, $code) {

            Log::error($e);

            $httpCode = 500;
            $response = (new ExceptionResponseProvider($e))->toArray();

            if ($e instanceof ACLUnauthenticatedException) {
                $httpCode = 200;
            }
            else if ($e instanceof ACLForbiddenException) {
                $httpCode = 200;
            }
            else if ($e instanceof ModelNotFoundException) {
                $httpCode = 404;
                $response['code'] = 404;
            }
            else if ($e instanceof InvalidArgsException) {
                $httpCode = 200;
            }
            else {
                if (! Config::get('app.debug')) {
                    return View::make('errors.general');
                }
            }

            // Allow Cross-Domain Request
            // http://enable-cors.org/index.html
            $cors = CorsHeader::create(Config::get('orbit.security.cors', []));

            $headers['Access-Control-Allow-Origin'] = $cors->getAllowOrigin();
            $headers['Access-Control-Allow-Methods'] = $cors->getAllowMethods();
            $headers['Access-Control-Allow-Credentials'] = $cors->getAllowCredentials();

            $angularTokenName = Config::get('orbit.security.csrf.angularjs.header_name');
            $sessionHeader = Config::get('orbit.session.session_origin.header.name');
            $allowHeaders = $cors->getAllowHeaders();

            if (! empty($angularTokenName)) {
                $allowHeaders[] = $angularTokenName;
            }

            $headers['Access-Control-Allow-Headers'] = implode(',', $allowHeaders);
            $headers['Access-Control-Expose-Headers'] = implode(',', $allowHeaders);

            return Response::json($response, $httpCode, $headers);
        });
    }
}
