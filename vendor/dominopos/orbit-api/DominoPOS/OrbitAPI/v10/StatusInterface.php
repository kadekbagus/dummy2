<?php
/**
 * The interface for storing status code and it's response message.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
namespace DominoPOS\OrbitAPI\v10;

interface StatusInterface {
    const OK = 0;
    const OK_MSG = 'Request OK';

    const UNKNOWN_ERROR = 1;
    const UNKNOWN_ERROR_MSG = 'Unknown Error';
    
    const CLIENT_ID_NOT_FOUND = 2;
    const CLIENT_ID_NOT_FOUND_MSG = 'The client ID does not exists';

    const INVALID_SIGNATURE = 3;
    const INVALID_SIGNATURE_MSG = 'Invalid signature';

    const REQUEST_EXPIRED = 4;
    const REQUEST_EXPIRED_MSG = 'Request expires';

    const LOOKUP_INSTANCE_ERROR = 5;
    const LOOKUP_INSTANCE_ERROR_MSG = 'The result is not instance of LookupResponse';

    const LOOKUP_UNKNOWN_ERROR = 6;
    const LOOKUP_UNKNOWN_ERROR_MSG = 'Unknown LookupResponse status';

    const PARAM_MISSING_TIMESTAMP = 7;
    const PARAM_MISSING_TIMESTAMP_MSG = 'Missing the \'timestamp\' parameter in query string';

    const PARAM_MISSING_CLIENT_ID = 8;
    const PARAM_MISSING_CLIENT_ID_MSG = 'Missing the \'clientid\' parameter in query string';

    const PARAM_MISSING_VERSION_API = 9;
    const PARAM_MISSING_VERSION_API_MSG = 'Missing the \'version\' parameter in query string';

    const PARAM_MISSING_SIGNATURE = 10;
    const PARAM_MISSING_SIGNATURE_MSG = 'Missing the \'signature\' parameter in http header';
    
    const PARAM_INVALID_TIMESTAMP = 11;
    const PARAM_INVALID_TIMESTAMP_MSG = 'The \'timestamp\' parameter must be in Unix timestamp';

    const UNSUPORTED_HASHING_ALGORITHM = 12;
    const UNSUPORTED_HASHING_ALGORITHM_MSG = 'The \'%s\' algorithm is not supported';

    const ACCESS_DENIED = 13;
    const ACCESS_DENIED_MSG = 'You do not have permission to access the specified resource';

    const INVALID_ARGUMENT = 14;
    const INVALID_ARGUMENT_MSG = 'Invalid argument given. %s';
}
