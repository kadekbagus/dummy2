<?php
/**
 * Abstract class for implementing simple API authentication using HMAC hashing algorithm.
 *
 * @author Rio Astamal <me@riostamal.net>
 */
namespace DominoPOS\OrbitAPI\v10;

use DominoPOS\OrbitAPI\v10\Exception\APIException as OrbitAPIException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use DominoPOS\OrbitAPI\v10\LookupResponseInterface as LookupResponse;

abstract class API
{
    /**
     * Constant of API Version
     *
     * @var string
     */
    const VERSION = '1.0';

    /**
     * The currently active client ID.
     *
     * @var string
     */
    protected $clientID = '';

    /**
     * The secret key of the current Client ID.
     *
     * @var string
     */
    protected $clientSecretKey = '';
    
    /**
     * Hashing alogirithm, i.e. "md5", "sha256", "haval160,4", etc)
     *
     * @var string
     */
    protected $hashingAlgorithm = 'sha256';
    
    /**
     * Expired time for the request in seconds. This is to prevent 'replay attack'.
     * Don't set it too low to anticipate some bottleneck such as slow network,
     * 60 seconds is quite reasonable timeframe.
     *
     * @var int
     */
    protected $expiresTimeFrame = 60;

    /**
     * The HTTP header name that client should sent their signature.
     *
     * @var string
     */
    protected $httpSignatureHeader = 'X-Simple-Api-Signature';

    /**
     * The query parameter name storing the request API information.
     * 
     * @var array
     */
    protected $queryParamName = array(
        'clientid'  => 'api_clientid',
        'timestamp' => 'api_timestamp',
        'version'   => 'api_version'
    );

    /**
     * Class constructor
     */
    public function __construct($clientID)
    {
        $this->searchSecretKey($clientID);
    }

    /**
     * Abstract method for filling the credential lists. It's up to the implementor how
     * to fill up this. i.e. from database or a simple config file.
     *
     * The implementor return object that implement LookupResponse interface.
     * 
     * @return LookupResponse
     */
    abstract protected function lookupClientSecretKey($clientID);

    /**
     * Search the corresponding secret key based on it's client ID.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @param string $clientID
     * @return string|boolean false
     */
    public function searchSecretKey($clientID)
    {
        $response = $this->lookupClientSecretKey($clientID);

        // check if the response is instance of LookupResponse Interface
        if ( ($response instanceof LookupResponse) === FALSE) {
            throw new OrbitAPIException(
                Status::LOOKUP_INSTANCE_ERROR_MSG,
                Status::LOOKUP_INSTANCE_ERROR
            );
        }
            
        // check the response status
        if ($response->getStatus() !== LookupResponse::LOOKUP_STATUS_OK) {
            if ($response->getStatus() === LookupResponse::LOOKUP_STATUS_NOT_FOUND) {
                throw new OrbitAPIException(
                    Status::CLIENT_ID_NOT_FOUND_MSG,
                    Status::CLIENT_ID_NOT_FOUND
                );
            } elseif ($response->getStatus() === LookupResponse::LOOKUP_STATUS_ACCESS_DENIED) {
                throw new OrbitAPIException(
                    Status::ACCESS_DENIED_MSG,
                    Status::ACCESS_DENIED
                );
            } else {
                // unknown status
                throw new OrbitAPIException(
                    Status::LOOKUP_UNKNOWN_ERROR_MSG,
                    Status::LOOKUP_UNKNOWN_ERROR
                );
            }
        }

        // The implementor give us an 'OK' status, so let's continue
        $this->clientID = $response->getClientID();
        $this->clientSecretKey = $response->getClientSecretKey();

        // return the LookupResonseInterface instance
        return $response;
    }

    /**
     * Generate the hash using the specified algorithm.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @return string
     */
    public function generateHash()
    {
        $secretKey = $this->clientSecretKey;
        $data = $this->createSignedData();
        $hash = hash_hmac($this->hashingAlgorithm, $data, $secretKey);
                          
        return $hash;
    }

    /**
     * Generate the data that will be signed it.
     *
     * @todo
     *  - support POST method
     *  - make the REQUEST BODY that have enctype="multipart/form-data"
     *    part of the signed request.
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public static function createSignedData()
    {
        /**
         * The data that will be signed is as follow:
         *
         * -----BEGIN------
         * HTTP_VERB + "\n"
         * REQUEST_URI
         * -----END--------
         *
         * If the request is POST (application/x-www-form-urlencoded)
         * then the signed request is as follow:
         *
         * -----BEGIN------
         * HTTP_VERB + "\n"
         * REQUEST_URI + "\n\n"
         * ENCODED_POST_DATA
         * -----END--------
         *
         * As an example
         * -------------
         * The Request URL
         * http://host/test?clientid=12345&version=1.2.3&platform=linux&hello=world&timestamp=734758749
         *
         * The string that will be signed is:
         * ----------------------------------
         * GET + "\n"
         * /host/test?clientid=12345&version=1.2.3&platform=linux&hello=world&timestamp=734758749
         *
         * POST Example
         * ------------
         * Saving resource to URL
         * http://host/test?clientid=12345&version=1.2.3&platform=linux&hello=world&timestamp=734758749
         *
         * Post Data:
         * ----------
         * firstname=John&lastname=Doe&address=Unit+Testing+Street+419
         *
         * The string that will be signed is
         * ---------------------------------
         * POST + "\n"
         * /test?clientid=12345&version=1.2.3&platform=linux&hello=world&timestamp=734758749 + "\n\n"
         * firstname=John&lastname=Doe&address=Unit+Testing+Street+419
         *
         * If the request is POST (multipart/form-data)
         * then the signed request is as follow:
         *
         * -----BEGIN------
         * HTTP_VERB + "\n"
         * REQUEST_URI + "\n\n"
         * ENCODED_POST_DATA + "\n"
         * FILENAME_1 + "\n"
         * FILE_CONTENT_1 + "\n"
         * FILENAME_2 + "\n"
         * FILE_CONTENT_2 + "\n"
         * FILENAME_N + "\n"
         * FILE_CONTENT_N
         * -----END--------
         */
         $signedData  = $_SERVER['REQUEST_METHOD'] . "\n";
         $signedData .= $_SERVER['REQUEST_URI'];

         if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $signedData .= "\n\n";

            // Encode the post data using RFC1738 format
            $signedData .= http_build_query($_POST);

            // Check for files upload (multipart/form-data content type)
            if (isset($_FILES) === TRUE) {
                $uploadedFiles = static::filesToOneDimensionalArray();

                if (empty($uploadedFiles) === FALSE) {
                    // concat the filename and the content with "\n"
                    foreach ($uploadedFiles as $file) {
                        // add one new line
                        $signedData .= "\n";
                        
                        $fileName = $file['filename'];

                        // If the file size is more than 20 kilobytes, then
                        // read only the first 20 kilobytes
                        // ---------------------------------------------------
                        // Hint: I think the first 20kb is quite random enough
                        $maxBytesRead = 20480;
                        
                        if (filesize($file['tempfile']) > $maxBytesRead === TRUE) {
                            $fileContent = file_get_contents($file['tempfile'], NULL, NULL, 0, $maxBytesRead);
                        } else {
                            // read the whole bytes
                            $fileContent = file_get_contents($file['tempfile']);
                        }

                        $signedData .= $fileName . "\n" . $fileContent;
                    }
                }
            }
         }

         return $signedData;
    }

    /**
     * Check the integrity of the signature.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @return boolean
     * @throws OrbitAPIException
     */
    public function checkSignature()
    {
        if (isset($_GET[$this->queryParamName['clientid']]) === FALSE) {
            throw new OrbitAPIException(
                Status::PARAM_MISSING_CLIENT_ID_MSG,
                Status::PARAM_MISSING_CLIENT_ID
            );
        }

        if (isset($_GET[$this->queryParamName['timestamp']]) === FALSE) {
            throw new OrbitAPIException(
                Status::PARAM_MISSING_TIMESTAMP_MSG,
                Status::PARAM_MISSING_TIMESTAMP
            );
        }

        if (is_numeric($_GET[$this->queryParamName['timestamp']]) === FALSE) {
            throw new OrbitAPIException(
                Status::PARAM_MISSING_TIMESTAMP_MSG,
                Status::PARAM_MISSING_TIMESTAMP
            );
        }

        if (isset($_GET[$this->queryParamName['version']]) === FALSE) {
            throw new OrbitAPIException(
                Status::PARAM_MISSING_VERSION_API_MSG,
                Status::PARAM_MISSING_VERSION_API
            );
        }

        $signatureHeader = static::toUnderScoreHeader($this->httpSignatureHeader);

        // Append 'HTTP_' prefix to the header, so it can be read by PHP
        $signatureHeader = 'HTTP_' . $signatureHeader;
        
        if (isset($_SERVER[$signatureHeader]) === FALSE) {
            throw new OrbitAPIException(
                Status::PARAM_MISSING_SIGNATURE_MSG,
                Status::PARAM_MISSING_SIGNATURE
            );
        }
        
        $ourTime = gmdate('U');
        $ourHash = $this->generateHash();

        $userTime = abs($_GET[$this->queryParamName['timestamp']]);
        $userHash = $_SERVER[$signatureHeader];
        
        if ($ourHash !== $userHash) {
            throw new OrbitAPIException(
                Status::INVALID_SIGNATURE_MSG,
                Status::INVALID_SIGNATURE
            );
        }
        
        if ($userTime < $ourTime - $this->expiresTimeFrame) {
            throw new OrbitAPIException(
                Status::REQUEST_EXPIRED_MSG,
                Status::REQUEST_EXPIRED
            );
        }
        
        return TRUE;
    }

    /**
     * Turn the hypen based header name to PHP counterparts, so it can be accessed through
     * $_SERVER global variable.
     *
     * i.e. 'X-Foo-Bar' becomes 'X_FOO_BAR'.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @param string $headerName
     * @return string
     */
    protected function toUnderScoreHeader($headerName)
    {
        return strtoupper( str_replace('-', '_', $headerName) );
    }

    /**
     * Method overloading for reading protected object property.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return mixed
     */
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }
    }

    /**
     * Method overloading for setting protected property.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return void
     */
    public function __set($name, $value)
    {
        // Find the associated method for current property
        // i.e. a property named 'expiresTimeFrame' will makes method named
        // 'setExpiresTimeFrame()' method.
        $methodName = 'set' . ucwords($name);
        if (method_exists($this, $methodName)) {
            $this->$methodName($value);
        }
    }

    /**
     * Set the hashing alogirithm.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @param string $algo  Hasing algorithm
     * @return DominoPOS\OrbitAPI\v10\API
     */
    public function setHashingAlgorithm($algo)
    {
        $supportedAlgos = hash_algos();
        if (in_array($algo, $supportedAlgos) === FALSE) {
            throw new OrbitAPIException(
                sprintf(Status::UNSUPORTED_HASHING_ALGORITHM_MSG, $algo),
                Status::UNSUPORTED_HASHING_ALGORITHM
            );
        }

        $this->hashingAlgorithm = $algo;

        return $this;
    }

    /**
     * Set the expiry time frame from the given request.
     * The number have to be integer and positive value.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @param int $expire   Number of seconds request will expires (in seconds)
     * @return DominoPOS\OrbitAPI\v10\API
     */
    public function setExpiresTimeFrame($expire)
    {
        if (is_int($expire) === FALSE) {
            throw new OrbitAPIException(
                sprintf(Status::INVALID_ARGUMENT_MSG, 'Expiry time can not contains non integer value.'),
                Status::INVALID_ARGUMENT
            );
        }
        
        if ($expire < 0) {
            throw new OrbitAPIException(
                sprintf(Status::INVALID_ARGUMENT_MSG, 'Expiry time can not contains negative value.'),
                Status::INVALID_ARGUMENT
            );
        }
        $this->expiresTimeFrame = $expire;

        return $this;
    }

    /**
     * Set the query param name used to get information about
     * clientid, timestamp and api version.
     *
     * As example, instead of using the default `api_clientid` as the
     * parameter name in query string we can use something different.
     *
     * <code>
     * $apiObject->setQueryParamName( 'clientid' => 'api_key' );
     * </code>
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @param array $paramName  Parameter name you want to use in query string.
     * @return DominoPOS\OrbitAPI\v10\API
     */
    public function setQueryParamName($paramName)
    {
        if (is_array($paramName) === FALSE) {
            throw new OrbitAPIException(
                        sprintf(Status::INVALID_ARGUMENT_MSG, 'Argument 1 on setQueryParamName must be an array.'),
                        Status::INVALID_ARGUMENT
            );
        }
        
        $validKeys = array(
            'clientid',
            'timestamp',
            'version'
        );

        foreach ($paramName as $key => $param) {
            if (in_array($key, $validKeys) === FALSE) {
                throw new OrbitAPIException(
                    sprintf(Status::INVALID_ARGUMENT_MSG, 'Invalid parameter key name.'),
                    Status::INVALID_ARGUMENT
                );  
            } else {
                $this->queryParamName[$key] = $param;
            }
        }

        return $this;
    }

    /**
     * Change the $_FILES multi-dimensional array to one dimensional that contains
     * only file name ('filename') and the location of temporary file ('tempfile').
     *
     * Take a look at example below
     * ----------------------------
     * <code>
     * Array
     * (
     *     [pictures] => Array
     *         (
     *             [name] => Array
     *                 (
     *                     [0] => My-Vacation.png
     *                     [1] => My-Car.png
     *                 )
     *
     *             [type] => Array
     *                 (
     *                     [0] => image/png
     *                     [1] => image/png
     *                 )
     *
     *             [tmp_name] => Array
     *                 (
     *                     [0] => /tmp/phpHLf8ow
     *                     [1] => /tmp/phpjtNP7f
     *                 )
     *
     *             [error] => Array
     *                 (
     *                     [0] => 0
     *                     [1] => 0
     *                 )
     *
     *             [size] => Array
     *                 (
     *                     [0] => 18883
     *                     [1] => 9003
     *                 )
     *
     *         )
     *
     *     [doc] => Array
     *         (
     *             [name] => Array
     *                 (
     *                     [idcard] => Array
     *                         (
     *                             [insurance] => somefile.bin
     *                             [driver] => othersfile.bin
     *                         )
     *
     *                 )
     *
     *             [type] => Array
     *                 (
     *                     [idcard] => Array
     *                         (
     *                             [insurance] => application/octet-stream
     *                             [driver] => application/octet-stream
     *                         )
     *
     *                 )
     *
     *             [tmp_name] => Array
     *                 (
     *                     [idcard] => Array
     *                         (
     *                             [insurance] => /tmp/phpLpVxQZ
     *                             [driver] => /tmp/phpJ4rgzJ
     *                         )
     *
     *                 )
     *
     *             [error] => Array
     *                 (
     *                     [idcard] => Array
     *                         (
     *                             [insurance] => 0
     *                             [driver] => 0
     *                         )
     *
     *                 )
     *
     *             [size] => Array
     *                 (
     *                     [idcard] => Array
     *                         (
     *                             [insurance] => 907
     *                             [driver] => 9003
     *                         )
     *
     *                 )
     *
     *         )
     *
     * )
     *
     * // It would turn something like this:
     * // ----------------------------------
     * Array
     * (
     *        [0] => array(
     *                [filename] => My-Vacation.png,
     *                [tempfile] => /tmp/phpHLf8ow
     *            )
     *        [1] => array(
     *                [filename] => My-Car.png,
     *                [tempfile] => /tmp/phpjtNP7f
     *            )
     *        [2] => array(
     *                [filename] => somefile.bin,
     *                [tempfile] => /tmp/phpLpVxQZ
     *            )
     *        [3] => array(
     *                [filename] => othersfile.bin,
     *                [tempfile] => /tmp/phpJ4rgzJ
     *            )
     *    )
     * </code>
     *
     * ATTENTION: Please keep it mind, currently this method only traverse to maximum
     *            4 level deep of the array.
     * 
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @return array
     */
    public static function filesToOneDimensionalArray()
    {
        $result = array();
        
        foreach ($_FILES as $name => $data) {
            // Level 0
            if (is_array($data['name'])) {
                // Level 1
                foreach ($data['name'] as $key1 => $data1) {
                    if (is_array($data1)) {
                        // Level 2
                        foreach ($data1 as $key2 => $data2) {
                            if (is_array($data2)) {
                                // level 3
                                foreach ($data2 as $key3 => $data3) {
                                    if (is_array($data3)) {
                                        // we stop here,
                                        //
                                        // @todo.
                                        // We might implement recursive function to
                                        // support unlimited depth
                                    } else {
                                        $result[] = array(
                                            'filename' => $data['name'][$key1][$key2][$key3],
                                            'tempfile' => $data['tmp_name'][$key1][$key2][$key3]
                                        );
                                    }
                                }
                            } else {
                                $result[] = array(
                                    'filename' => $data['name'][$key1][$key2],
                                    'tempfile' => $data['tmp_name'][$key1][$key2]
                                );
                            }
                        } 
                    } else {
                        $result[] = array(
                            'filename' => $data['name'][$key1],
                            'tempfile' => $data['tmp_name'][$key1]
                        );
                    }
                }
            } else {
                $result[] = array(
                    'filename' => $data['name'],
                    'tempfile' => $data['tmp_name']
                );
            }
        }
        
        return $result;

    }
}
