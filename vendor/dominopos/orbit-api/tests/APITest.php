<?php
/**
 * Unit test for API implementation.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */

class APITest extends PHPUnit_Framework_TestCase
{
    private $apiNamespace = 'DominoPOS\OrbitAPI\v10\API';
    private $lookupResponseNamespace = 'DominoPOS\OrbitAPI\v10\LookupResponseInterface';
    private $exceptionNamespace = 'DominoPOS\OrbitAPI\v10\Exception\APIException';

    private $dummyClientID = 'client123';
    private $dummyClientSecretKey = 'SomeRandomString098765432ZXCVBNM';
    private $lookupStatusOK = 0;
    private $lookupStatusNotFound = 1;
    private $lookupStatusAccessDenied = 2;

    private $apiStubObject = NULL;
    private $lookupStubObject = NULL;

    public function setUp()
    {
        // ---------------- STUBING LookupResponseInterface ---------------------- //
        
        // Stub the implementation of LookupResponseInterface
        $this->lookupStubObject = $this->getMock($this->lookupResponseNamespace);

        // Stub the getClientID() method, so it will return our dummyClientID
        $this->lookupStubObject->expects( $this->any() )
                               ->method('getClientID')
                               ->will( $this->returnValue($this->dummyClientID) );

        // Stub the getClienSecretKey() method, so it will return our dummyClientSecretKey
        $this->lookupStubObject->expects( $this->any() )
                               ->method('getClientSecretKey')
                               ->will( $this->returnValue($this->dummyClientSecretKey) );

        // Stub the getStatus() method, so it will return OK
        $this->lookupStubObject->expects( $this->any() )
                               ->method('getStatus')
                               ->will( $this->returnValue($this->lookupStatusOK) );
        
        // --------------------------- STUBING API ------------------------------ //

        // Stub the implementation of abstract class API
        $this->apiStubObject = $this->getMockBuilder($this->apiNamespace)
                                    ->disableOriginalConstructor()
                                    ->setConstructorArgs(array($this->dummyClientID))
                                    ->getMockForAbstractClass();

        // The constructor calling method searchSecretKey(string $clientID)
        // which also calling the abstract method lookupClientSecretKey(string $clientID)
        // so we need to stub the lookupClientSecretKey() method first
        $this->apiStubObject->expects( $this->any() )
                            ->method('lookupClientSecretKey')
                            ->will( $this->returnValue($this->lookupStubObject) );

        // Now we're ready to call the constructor since the lookupClientSecretKey has right
        // return value. This call make initialization value of object properties such
        // as clientID property and clientSecretKeyProperty
        $this->apiStubObject->searchSecretKey($this->dummyClientID);
    }

    public function tearDown()
    {
        $this->lookupStubObject = NULL;
        $this->apiStubObject = NULL;
    }
    
    public function testInstance()
    {
        $this->assertInstanceOf($this->apiNamespace, $this->apiStubObject);
    }
    

    public function testMethod_searchSecretKey_status_OK()
    {
        // The method searchSecretKey() return the LookupResponseInterface instance
        $secretKey = $this->apiStubObject->searchSecretKey($this->dummyClientID)->getClientSecretKey();
        $this->assertSame($secretKey, $this->dummyClientSecretKey);
    }

    public function testMethod_searchSecretKey_status_NOT_FOUND()
    {
        // ---------------------- RE STUBING --------------------------------- //
        // We're re-stubbing since we need to reimplement the getStatus() method
        // on LookupResponseInterface. We're returning the NOT FOUND response
        // status here.
        
        // Stub the implementation of LookupResponseInterface
        $this->lookupStubObject = $this->getMock($this->lookupResponseNamespace);

        // Stub the getClientID() method, so it will return our dummyClientID
        $this->lookupStubObject->expects( $this->any() )
                               ->method('getClientID')
                               ->will( $this->returnValue($this->dummyClientID) );

        // Stub the getClienSecretKey() method, so it will return our dummyClientSecretKey
        $this->lookupStubObject->expects( $this->any() )
                               ->method('getClientSecretKey')
                               ->will( $this->returnValue($this->dummyClientSecretKey) );

        // Stub the getStatus() method, so it will return Not Found
        $this->lookupStubObject->expects( $this->any() )
                               ->method('getStatus')
                               ->will( $this->returnValue($this->lookupStatusNotFound) );

        // Stub the implementation of abstract class API
        $this->apiStubObject = $this->getMockBuilder($this->apiNamespace)
                                    ->disableOriginalConstructor()
                                    ->setConstructorArgs(array($this->dummyClientID))
                                    ->getMockForAbstractClass();

        // The constructor calling method searchSecretKey(string $clientID)
        // which also calling the abstract method lookupClientSecretKey(string $clientID)
        // so we need to stub the lookupClientSecretKey() method first
        $this->apiStubObject->expects( $this->any() )
                            ->method('lookupClientSecretKey')
                            ->will( $this->returnValue($this->lookupStubObject) );

        // Expect the searchSecretKey() to throw the APIException
        $this->setExpectedException($this->exceptionNamespace,
                                     DominoPOS\OrbitAPI\v10\StatusInterface::CLIENT_ID_NOT_FOUND_MSG,
                                     DominoPOS\OrbitAPI\v10\StatusInterface::CLIENT_ID_NOT_FOUND);
        $secretKey = $this->apiStubObject->searchSecretKey($this->dummyClientID);
    }

   public function testMethod_searchSecretKey_status_ACCESS_DENIED()
   {
        // ---------------------- RE STUBING --------------------------------- //
        // We're re-stubbing since we need to reimplement the getStatus() method
        // on LookupResponseInterface. We're returning the ACCESS_DENIED response
        // status here.
        
        // Stub the implementation of LookupResponseInterface
        $this->lookupStubObject = $this->getMock($this->lookupResponseNamespace);

        // Stub the getClientID() method, so it will return our dummyClientID
        $this->lookupStubObject->expects( $this->any() )
                               ->method('getClientID')
                               ->will( $this->returnValue($this->dummyClientID) );

        // Stub the getClienSecretKey() method, so it will return our dummyClientSecretKey
        $this->lookupStubObject->expects( $this->any() )
                               ->method('getClientSecretKey')
                               ->will( $this->returnValue($this->dummyClientSecretKey) );

        // Stub the getStatus() method, so it will return Not Found
        $this->lookupStubObject->expects( $this->any() )
                               ->method('getStatus')
                               ->will( $this->returnValue($this->lookupStatusAccessDenied) );

        // Stub the implementation of abstract class API
        $this->apiStubObject = $this->getMockBuilder($this->apiNamespace)
                                    ->disableOriginalConstructor()
                                    ->setConstructorArgs(array($this->dummyClientID))
                                    ->getMockForAbstractClass();

        // The constructor calling method searchSecretKey(string $clientID)
        // which also calling the abstract method lookupClientSecretKey(string $clientID)
        // so we need to stub the lookupClientSecretKey() method first
        $this->apiStubObject->expects( $this->any() )
                            ->method('lookupClientSecretKey')
                            ->will( $this->returnValue($this->lookupStubObject) );

        // Expect the searchSecretKey() to throw the APIException
        $this->setExpectedException($this->exceptionNamespace,
                                     DominoPOS\OrbitAPI\v10\StatusInterface::ACCESS_DENIED_MSG,
                                     DominoPOS\OrbitAPI\v10\StatusInterface::ACCESS_DENIED);
        $secretKey = $this->apiStubObject->searchSecretKey($this->dummyClientID);
    }

    public function testProperty_ClientID_via_DirectAccess() {
        $clientID = $this->dummyClientID;
        $this->assertSame($clientID, $this->apiStubObject->clientID);
    }

    public function testProperty_ClientSecretKey_via_DirectAccess() {
        $secretKey = $this->dummyClientSecretKey;
        $this->assertSame($secretKey, $this->apiStubObject->clientSecretKey);
    }

    public function testMethod_setHashingAlgorithm_SuppertedAlgo() {
        $this->apiStubObject->setHashingAlgorithm('md5');

        // read the protected 'hashingAgorithm' property that has been set
        $this->assertSame('md5', $this->apiStubObject->hashingAlgorithm);
    }

    public function testSetProperty_hashingAlgorithm_via_DirectAccess() {
        $this->apiStubObject->hashingAlgorithm = 'crc32';

        // read the protected 'hashingAgorithm' property that has been set
        $this->assertSame('crc32', $this->apiStubObject->hashingAlgorithm);
    }

    public function testMethod_setHashingAlgorithm_UnSuppertedAlgo()
    {
        $unknownHash = 'super_duper_md5';
        $errorMessage = sprintf(
                                DominoPOS\OrbitAPI\v10\StatusInterface::UNSUPORTED_HASHING_ALGORITHM_MSG,
                                $unknownHash
        );
                        
        // Unsupported algorithm should throw an exception
        $this->setExpectedException($this->exceptionNamespace,
                                     $errorMessage,
                                     DominoPOS\OrbitAPI\v10\StatusInterface::UNSUPORTED_HASHING_ALGORITHM);
                                     
        $this->apiStubObject->hashingAlgorithm = $unknownHash;
    }

    public function testMethod_setExpiresTimeFrame()
    {
        $this->apiStubObject->setExpiresTimeFrame(30);

        // read the protected 'expiresTimeFrame' property that has been set
        $this->assertSame(30, $this->apiStubObject->expiresTimeFrame);
    }

    public function testSetProperty_expiresTimeFrame_via_DirectAccess()
    {
        $this->apiStubObject->expiresTimeFrame = 45;

        // read the protected 'expiresTimeFrame' property that has been set
        $this->assertSame(45, $this->apiStubObject->expiresTimeFrame);
    }

    public function testMethod_setExpiresTimeFrame_NegativeValue()
    {
        $negativeValueMessage = 'Expiry time can not contains negative value.';
        $errorMessage = sprintf(
                                DominoPOS\OrbitAPI\v10\StatusInterface::INVALID_ARGUMENT_MSG,
                                $negativeValueMessage
        );
        
        // Unsupported timeframe value should throw an exception
        $this->setExpectedException($this->exceptionNamespace,
                                     $errorMessage,
                                     DominoPOS\OrbitAPI\v10\StatusInterface::INVALID_ARGUMENT);
                                     
        $this->apiStubObject->expiresTimeFrame = -20;
    }

    public function testMethod_setExpiresTimeFrame_NonInteger()
    {
        $negativeValueMessage = 'Expiry time can not contains non integer value.';
        $errorMessage = sprintf(
                                DominoPOS\OrbitAPI\v10\StatusInterface::INVALID_ARGUMENT_MSG,
                                $negativeValueMessage
        );
        
        // Unsupported timeframe value should throw an exception
        $this->setExpectedException($this->exceptionNamespace,
                                     $errorMessage,
                                     DominoPOS\OrbitAPI\v10\StatusInterface::INVALID_ARGUMENT);
                                     
        $this->apiStubObject->expiresTimeFrame = 'Hello World';
    }

    public function testMethod_setQueryParamName_ClientID()
    {
        $this->apiStubObject->setQueryParamName(array('clientid' =>'xapi_clientid'));

        // read the protected property 'queryParamName' with key 'clientid'
        $this->assertSame('xapi_clientid', $this->apiStubObject->queryParamName['clientid']);

        // the number of items in queryParamName should be 3
        $this->assertSame(3, count($this->apiStubObject->queryParamName ) );
    }

    public function testSetPropery_queryParamName_clientid_via_DirectAccess() {
        $this->apiStubObject->queryParamName = array('clientid' => 'Xclientid');

        // read the protected property 'queryParamName' with key 'clientid'
        $this->assertSame('Xclientid', $this->apiStubObject->queryParamName['clientid']);

        // the number of items in queryParamName should be 3
        $this->assertSame(3, count($this->apiStubObject->queryParamName ) );
    }

    public function testMethod_setQueryParamName_Timestamp()
    {
        $this->apiStubObject->setQueryParamName(array('timestamp' => 'xapi_timestamp'));

        // read the protected property 'queryParamName' with key 'timestamp'
        $this->assertSame('xapi_timestamp', $this->apiStubObject->queryParamName['timestamp']);

        // the number of items in queryParamName should be 3
        $this->assertSame(3, count($this->apiStubObject->queryParamName ) );
    }

    public function testSetPropery_queryParamName_timestamp_via_DirectAccess()
    {
        $this->apiStubObject->queryParamName = array('timestamp' => 'Xtimestamp');

        // read the protected property 'queryParamName' with key 'timestamp'
        $this->assertSame('Xtimestamp', $this->apiStubObject->queryParamName['timestamp']);

        // the number of items in queryParamName should be 3
        $this->assertSame(3, count($this->apiStubObject->queryParamName ) );
    }

    public function testMethod_setQueryParamName_Version()
    {
        $this->apiStubObject->setQueryParamName(array('version' => 'xapi_version'));

        // read the protected property 'queryParamName' with key 'version'
        $this->assertSame('xapi_version', $this->apiStubObject->queryParamName['version']);

        // the number of items in queryParamName should be 3
        $this->assertSame(3, count($this->apiStubObject->queryParamName ) );
    }

    public function testSetPropery_queryParamName_version_via_DirectAccess()
    {
        $this->apiStubObject->queryParamName = array('version' => 'Xversion');

        // read the protected property 'queryParamName' with key 'timestamp'
        $this->assertSame('Xversion', $this->apiStubObject->queryParamName['version']);

        // the number of items in queryParamName should be 3
        $this->assertSame(3, count($this->apiStubObject->queryParamName ) );
    }

    public function testMethod_setQueryParamName_MultiValue()
    {
        $this->apiStubObject->setQueryParamName(array(
                'clientid'  => '_apikey',
                'version'   => '_apiversion',
                'timestamp' => '_apitimestamp',
        ));

        // read each value in protected property 'queryParamName'
        $this->assertSame('_apikey', $this->apiStubObject->queryParamName['clientid']);
        $this->assertSame('_apitimestamp', $this->apiStubObject->queryParamName['timestamp']);
        $this->assertSame('_apiversion', $this->apiStubObject->queryParamName['version']);

        // the number of items in queryParamName should be 3
        $this->assertSame(3, count($this->apiStubObject->queryParamName ) );
    }

    public function testSetPropery_queryParamName_MultiValue_via_DirectAccess()
    {
        $this->apiStubObject->queryParamName = array(
                'clientid'  => 'Xapikey',
                'version'   => 'Xapiversion',
                'timestamp' => 'Xapitimestamp',
        );

        // read each value in protected property 'queryParamName'
        $this->assertSame('Xapikey', $this->apiStubObject->queryParamName['clientid']);
        $this->assertSame('Xapitimestamp', $this->apiStubObject->queryParamName['timestamp']);
        $this->assertSame('Xapiversion', $this->apiStubObject->queryParamName['version']);

        // the number of items in queryParamName should be 3
        $this->assertSame(3, count($this->apiStubObject->queryParamName ) );
    }

    public function testSetPropery_queryParamName_keyNotValid()
    {
        $unknownIndexMessage = 'Invalid parameter key name.';
        $errorMessage = sprintf(
                                DominoPOS\OrbitAPI\v10\StatusInterface::INVALID_ARGUMENT_MSG,
                                $unknownIndexMessage
        );
        
        // Unknown key index for queryParamName
        $this->setExpectedException($this->exceptionNamespace,
                                     $errorMessage,
                                     DominoPOS\OrbitAPI\v10\StatusInterface::INVALID_ARGUMENT);

        $this->apiStubObject->queryParamName = array('xxxyyyzzz' => 'Xclientid');
    }

    public function testSetPropery_queryParamName_parameterNotArray()
    {
        $unknownIndexMessage = 'Argument 1 on setQueryParamName must be an array.';
        $errorMessage = sprintf(
                                DominoPOS\OrbitAPI\v10\StatusInterface::INVALID_ARGUMENT_MSG,
                                $unknownIndexMessage
        );
        
        // Unknown key index for queryParamName
        $this->setExpectedException($this->exceptionNamespace,
                                     $errorMessage,
                                     DominoPOS\OrbitAPI\v10\StatusInterface::INVALID_ARGUMENT);

        $this->apiStubObject->queryParamName = 'This is not array';
    }

    public function testCheckSignature_missingClientId()
    {
        $errorMessage = DominoPOS\OrbitAPI\v10\StatusInterface::PARAM_MISSING_CLIENT_ID_MSG;
        $errorCode = DominoPOS\OrbitAPI\v10\StatusInterface::PARAM_MISSING_CLIENT_ID;

        // The clientid in QUERY String is missing
        $this->setExpectedException($this->exceptionNamespace,
                                    $errorMessage,
                                    $errorCode);

        $this->apiStubObject->checkSignature();
    }

    public function testCheckSignature_missingTimestamp()
    {
        $_GET['api_clientid'] = 'client123';
        $errorMessage = DominoPOS\OrbitAPI\v10\StatusInterface::PARAM_MISSING_TIMESTAMP_MSG;
        $errorCode = DominoPOS\OrbitAPI\v10\StatusInterface::PARAM_MISSING_TIMESTAMP;

        // The clientid in QUERY String is missing
        $this->setExpectedException($this->exceptionNamespace,
                                    $errorMessage,
                                    $errorCode);

        $this->apiStubObject->checkSignature();
    }

    public function testCheckSignature_missingVersion()
    {
        $_GET['api_clientid'] = 'client123';
        $_GET['api_timestamp'] = '938473298';
        $errorMessage = DominoPOS\OrbitAPI\v10\StatusInterface::PARAM_MISSING_VERSION_API_MSG;
        $errorCode = DominoPOS\OrbitAPI\v10\StatusInterface::PARAM_MISSING_VERSION_API;

        // The clientid in QUERY String is missing
        $this->setExpectedException($this->exceptionNamespace,
                                    $errorMessage,
                                    $errorCode);

        $this->apiStubObject->checkSignature();
    }

    public function testCheckSignature_missingSignatureHeader()
    {
        $_GET['api_clientid'] = 'client123';
        $_GET['api_timestamp'] = '938473298';
        $_GET['api_version'] = '1.0.0';
        $errorMessage = DominoPOS\OrbitAPI\v10\StatusInterface::PARAM_MISSING_SIGNATURE_MSG;
        $errorCode = DominoPOS\OrbitAPI\v10\StatusInterface::PARAM_MISSING_SIGNATURE;

        // The clientid in QUERY String is missing
        $this->setExpectedException($this->exceptionNamespace,
                                    $errorMessage,
                                    $errorCode);

        $this->apiStubObject->checkSignature();
    }

    public function testMethod_checkSignature_NormalScenario_GET_Method()
    {
        // Simulate the GET request
        // ------------------------
        // Assume we're accessing resource below:
        // http://foo.bar/user/10/view?api_key=client123&api_time=[TIMESTAMP]&api_ver=1.0.0

        // set request method to GET
        unset($_GET);
        $time = gmdate('U');
        
        $_GET['api_clientid'] = 'client123';
        $_GET['api_timestamp'] = $time;
        $_GET['api_version'] = '1.0.0';
        
        $httpVerb = $_SERVER['REQUEST_METHOD'] = 'GET';
        $requestUri = $_SERVER['REQUEST_URI'] = '/user/10/view?' . http_build_query($_GET);

        $algorithm = 'sha256';
        $secretKey = $this->dummyClientSecretKey;
        $signedData = $httpVerb . "\n" . $requestUri;
        $signature = hash_hmac($algorithm, $signedData, $secretKey);

        // Send the signature to the API
        $_SERVER['HTTP_X_SIMPLE_API_SIGNATURE'] = $signature;
        
        $this->assertTrue($this->apiStubObject->checkSignature());
    }

    public function testMethod_checkSignature_NormalScenario_GET_Method_Tiger1924_Algorithm()
    {
        // Simulate the GET request
        // ------------------------
        // Assume we're accessing resource below:
        // http://foo.bar/user/10/view?api_key=client123&api_time=[TIMESTAMP]&api_ver=1.0.0

        // set request method to GET
        unset($_GET);
        $time = gmdate('U');

        $_GET['api_clientid'] = 'client123';
        $_GET['api_timestamp'] = $time;
        $_GET['api_version'] = '1.0.0';
        
        $httpVerb = $_SERVER['REQUEST_METHOD'] = 'GET';
        $requestUri = $_SERVER['REQUEST_URI'] = '/user/10/view?' . http_build_query($_GET);

        $algorithm = 'tiger192,4';
        $secretKey = $this->dummyClientSecretKey;
        $signedData = $httpVerb . "\n" . $requestUri;
        $signature = hash_hmac($algorithm, $signedData, $secretKey);

        // Send the signature to the API
        $_SERVER['HTTP_X_SIMPLE_API_SIGNATURE'] = $signature;

        $this->apiStubObject->hashingAlgorithm = $algorithm;
        $this->assertTrue($this->apiStubObject->checkSignature());
    }

    public function testMethod_checkSignature_NormalScenario_POST_Method()
    {
        // Simulate the POST request
        // ------------------------
        // Assume we're accessing resource below:
        // http://foo.bar/user/10/view?api_key=client123&api_time=[TIMESTAMP]&api_ver=1.0.0
        //
        // Data:
        // -----
        // firstname=John&lastname=Doe&address=Unit+Testing+Street+419

        unset($_GET);
        $time = gmdate('U');

        $_GET['api_clientid'] = 'client123';
        $_GET['api_timestamp'] = $time;
        $_GET['api_version'] = '1.0.0';
        
        $httpVerb = $_SERVER['REQUEST_METHOD'] = 'POST';
        $requestUri = $_SERVER['REQUEST_URI'] = '/user/10/view?' . http_build_query($_GET);
        $_POST = array(
            'firstname' => 'John',
            'lastname' => 'Doe',
            'address' => 'Unit Testing Street 419',
        );
        $postData = http_build_query($_POST);

        $algorithm = 'sha256';
        $secretKey = $this->dummyClientSecretKey;
        
        $signedData = $httpVerb . "\n" . $requestUri . "\n\n" . $postData;
        $signature = hash_hmac($algorithm, $signedData, $secretKey);

        $this->apiStubObject->hashingAlgorithm = $algorithm;
        $apiSignature = $this->apiStubObject->generateHash();

        // Send the signature to the API
        $_SERVER['HTTP_X_SIMPLE_API_SIGNATURE'] = $signature;

        $this->apiStubObject->hashingAlgorithm = $algorithm;
        $this->assertTrue($this->apiStubObject->checkSignature());
    }

    public function testMethod_checkSignature_NormalScenario_POST_Method_MultipartFormData_Simple()
    {
        // Simulate the POST request
        // ------------------------
        // Assume we're accessing resource below:
        // http://foo.bar/upload/?api_key=client123&api_time=[TIMESTAMP]&api_ver=1.0.0
        //
        // Data:
        // -----
        // ------WebKitFormBoundaryyTyRGSjbSei8ZtnL
        // Content-Disposition: form-data; name="photodesc"
        //
        // Smoke in the water
        // ------WebKitFormBoundaryyTyRGSjbSei8ZtnL
        // Content-Disposition: form-data; name="user"
        // 
        // johndoe
        // ------WebKitFormBoundaryyTyRGSjbSei8ZtnL
        // Content-Disposition: form-data; name="galleryid"
        //
        // 10
        // ------WebKitFormBoundaryyTyRGSjbSei8ZtnL
        // Content-Disposition: form-data; name="picture"; filename="RANDOM_FILE_NAME"
        // Content-Type: image/png
        //
        // SOME_RANDOM_DATA
        // ------WebKitFormBoundaryyTyRGSjbSei8ZtnL--

        unset($_GET);
        unset($_POST);
        unset($_FILES);
        $time = gmdate('U');

        $_GET['api_clientid'] = 'client123';
        $_GET['api_timestamp'] = $time;
        $_GET['api_version'] = '1.0.0';
        
        $httpVerb = $_SERVER['REQUEST_METHOD'] = 'POST';
        $requestUri = $_SERVER['REQUEST_URI'] = '/upload/?' . http_build_query($_GET);

        // Simulate the post data
        $_POST = array(
            'photodesc' => 'Smoke in the water',
            'user' => 'johndoe',
            'galleryid' => '10'
        );
        $postData = http_build_query($_POST);

        // Simulate the the upload files data, so the SimpleAPI can read the file
        // and make the signed code from it.

        // create the temporary file
        $tmpFilename = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'PHPUnit-' . time() . '.tmp';

        // generate random data
        $getRandomData = function($length, $split=50) {
            $data = '';
            
            for ($i=0; $i<$length; $i++) {
                // random ASCII chars
                $data .= chr(mt_rand(20, 150));

                // split the data down with new line
                if (strlen($data) % $split == 0) {
                    $data .= "\n";
                }
            }
            
            return $data;
        };
        $randomData = $getRandomData(450, 50);
        file_put_contents($tmpFilename, $randomData);

        // Simulate the FILES for 1 file. We only simulate two things here:
        // `name` and `tmp_name` since only those two information used to signed the
        // uploaded files
        $_FILES = array(
            'picture' => array(
                'name'      => 'foo.png',
                'tmp_name'  => $tmpFilename
            )
        );

        $algorithm = 'sha256';
        $secretKey = $this->dummyClientSecretKey;

        // For upload data we need to concat it with it's name
        $uploadData = 'foo.png' . "\n" . $randomData;
        
        $signedData =   $httpVerb . "\n" .
                        $requestUri . "\n\n" .
                        $postData . "\n" .
                        $uploadData;
        $signature = hash_hmac($algorithm, $signedData, $secretKey);

        // Send the signature to the API
        $_SERVER['HTTP_X_SIMPLE_API_SIGNATURE'] = $signature;

        $this->apiStubObject->hashingAlgorithm = $algorithm;
        $this->assertTrue($this->apiStubObject->checkSignature());

        // delete the temporary file
        unlink($tmpFilename);
    }

   public function testMethod_checkSignature_NormalScenario_POST_Method_MultipartFormData_ComplexArray()
   {
        // Simulate the POST request
        // ------------------------
        // Assume we're accessing resource below:
        // http://foo.bar/upload/?api_key=client123&api_time=[TIMESTAMP]&api_ver=1.0.0
        //
        // Data:
        // -----
        // ------WebKitFormBoundaryyTyRGSjbSei8ZtnL
        // Content-Disposition: form-data; name="photodesc"
        //
        // Smoke in the water
        // ------WebKitFormBoundaryyTyRGSjbSei8ZtnL
        // Content-Disposition: form-data; name="user"
        // 
        // johndoe
        // ------WebKitFormBoundaryyTyRGSjbSei8ZtnL
        // Content-Disposition: form-data; name="galleryid"
        //
        // 10
        // ------WebKitFormBoundaryyTyRGSjbSei8ZtnL
        // Content-Disposition: form-data; name="picture"; filename="RANDOM_FILE_NAME"
        // Content-Type: image/png
        //
        // SOME_RANDOM_DATA
        // ------WebKitFormBoundaryyTyRGSjbSei8ZtnL--

        unset($_GET);
        unset($_POST);
        unset($_FILES);
        $time = gmdate('U');

        $_GET['api_clientid'] = 'client123';
        $_GET['api_timestamp'] = $time;
        $_GET['api_version'] = '1.0.0';
        
        $httpVerb = $_SERVER['REQUEST_METHOD'] = 'POST';
        $requestUri = $_SERVER['REQUEST_URI'] = '/upload/?' . http_build_query($_GET);

        // Simulate the post data
        $_POST = array(
            'photodesc' => 'Smoke in the water',
            'user' => 'johndoe',
            'galleryid' => '10'
        );
        $postData = http_build_query($_POST);

        // Simulate the the upload files data, so the SimpleAPI can read the file
        // and make the signed code from it.

        // generate random data
        $getRandomData = function($length, $split=50) {
            $data = '';
            
            for ($i=0; $i<$length; $i++) {
                // random ASCII chars
                $data .= chr(mt_rand(20, 150));

                // split the data down with new line
                if (strlen($data) % $split == 0) {
                    $data .= "\n";
                }
            }
            
            return $data;
        };


        // create the temporary file
        $fakeFile = array(
                        'file1' => array(
                            'filename'  => 'mypicture1.jpg',
                            'temppath'  => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'PHPUnit-picture1' . time() . '.tmp',
                            'content'   => $getRandomData(450, 50)
                        ),
                        
                        'file2' => array(
                            'filename' => 'mypicture2.jpg',
                            'temppath' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'PHPUnit-picture2' . time() . '.tmp',
                            'content'   => $getRandomData(487, 80)
                        ),

                        'file3' => array(
                            'filename' => 'idcard-driver.png',
                            'temppath' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'PHPUnit-drivercard' . time() . '.tmp',
                            'content'   => $getRandomData(487, 80)
                        ),

                        'file4' => array(
                            'filename' => 'idcard-insurance.png',
                            'temppath' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'PHPUnit-insureancecard' . time() . '.tmp',
                            'content'   => $getRandomData(487, 80)
                        ),

        );

        // Simulate the FILES for 4 files. We only simulate two things here:
        // `name` and `tmp_name` since only those two information used to signed the
        // uploaded files.
        //
        // We're simulating multi file uploads with name pictures[] and doc[idcard][]
        $_FILES = array(
            'pictures'  => array(
                'name'      => array(
                                $fakeFile['file1']['filename'],
                                $fakeFile['file2']['filename']
                ),
                'tmp_name'  => array(
                                $fakeFile['file1']['temppath'],
                                $fakeFile['file2']['temppath']
                ),
            ),
            'doc'       => array(
                'name'      => array(
                                'idcard' => array(
                                            $fakeFile['file3']['filename'],
                                            $fakeFile['file4']['filename']
                                )
                ),
                'tmp_name'  => array(
                                'idcard' => array(
                                            $fakeFile['file3']['temppath'],
                                            $fakeFile['file4']['temppath']
                                )
                ),
            ),
        );

        $algorithm = 'sha256';
        $secretKey = $this->dummyClientSecretKey;

        // Construct the upload data that will be signed
        $uploadedData = '';
        foreach ($fakeFile as $fake) {
            // Write the random data to temporary file
            // So, the actual API implementation can read it
            file_put_contents($fake['temppath'], $fake['content']);

            $uploadedData .= "\n";
            $uploadedData .= $fake['filename'] . "\n" . $fake['content'];
        }
        
        $signedData =   $httpVerb . "\n" .
                        $requestUri . "\n\n" .
                        $postData .
                        $uploadedData;
        $signature = hash_hmac($algorithm, $signedData, $secretKey);

        // Send the signature to the API
        $_SERVER['HTTP_X_SIMPLE_API_SIGNATURE'] = $signature;

        $this->apiStubObject->hashingAlgorithm = $algorithm;
        $this->assertTrue($this->apiStubObject->checkSignature());

        // delete the temporary file
        foreach ($fakeFile as $fake) {
            $tmpFilename = $fake['temppath'];
            
            unlink($tmpFilename);
        }
    }
}
