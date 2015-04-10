<?php
/**
 * Unit test for StatusInterface.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */

class StatusInterfaceTest extends PHPUnit_Framework_TestCase
{
    private $fullNamespace = 'DominoPOS\OrbitAPI\v10\StatusInterface';
    
    public function testInstance()
    {
        // Mock the instance
        $stub = $this->getMock($this->fullNamespace);
        $this->assertInstanceOf($this->fullNamespace, $stub);
    }

    public function testConstant_OK()
    {
        $OK = 0;
        $this->assertSame($OK, DominoPOS\OrbitAPI\v10\StatusInterface::OK);
    }

    public function testConstant_OK_MSG()
    {
        $message = 'Request OK';
        $this->assertSame($message, DominoPOS\OrbitAPI\v10\StatusInterface::OK_MSG);
    }

    public function testConstant_UNKNOWN_ERROR()
    {
        $unknown = 1;
        $this->assertSame($unknown, DominoPOS\OrbitAPI\v10\StatusInterface::UNKNOWN_ERROR);
    }

    public function testConstant_UNKNOWN_ERROR_MSG()
    {
        $message = 'Unknown Error';
        $this->assertSame($message, DominoPOS\OrbitAPI\v10\StatusInterface::UNKNOWN_ERROR_MSG);
    }

    public function testConstant_()
    {
        $clientNotFound = 2;
        $this->assertSame($clientNotFound, DominoPOS\OrbitAPI\v10\StatusInterface::CLIENT_ID_NOT_FOUND);
    }

    public function testConstant_CLIENT_ID_NOT_FOUND()
    {
        $message = 'The client ID does not exists';
        $this->assertSame($message, DominoPOS\OrbitAPI\v10\StatusInterface::CLIENT_ID_NOT_FOUND_MSG);
    }
    
    public function testConstant_INVALID_SIGNATURE()
    {
        $invalid = 3;
        $this->assertSame($invalid, DominoPOS\OrbitAPI\v10\StatusInterface::INVALID_SIGNATURE);
    }

    public function testConstant_INVALID_SIGNATURE_MSG()
    {
        $message = 'Invalid signature';
        $this->assertSame($message, DominoPOS\OrbitAPI\v10\StatusInterface::INVALID_SIGNATURE_MSG);
    }

    public function testConstant_REQUEST_EXPIRED()
    {
        $requestExpire = 4;
        $this->assertSame($requestExpire, DominoPOS\OrbitAPI\v10\StatusInterface::REQUEST_EXPIRED);
    }

    public function testConstant_REQUEST_EXPIRED_MSG()
    {
        $message = 'Request expires';
        $this->assertSame($message, DominoPOS\OrbitAPI\v10\StatusInterface::REQUEST_EXPIRED_MSG);
    }

    public function testConstant_LOOKUP_INSTANCE_ERROR()
    {
        $instanceError = 5;
        $this->assertSame($instanceError, DominoPOS\OrbitAPI\v10\StatusInterface::LOOKUP_INSTANCE_ERROR);
    }

    public function testConstant_LOOKUP_INSTANCE_ERROR_MSG()
    {
        $message = 'The result is not instance of LookupResponse';
        $this->assertSame($message, DominoPOS\OrbitAPI\v10\StatusInterface::LOOKUP_INSTANCE_ERROR_MSG);
    }

    public function testConstant_LOOKUP_UNKNOWN_ERROR()
    {
        $lookupUnknownError = 6;
        $this->assertSame($lookupUnknownError, DominoPOS\OrbitAPI\v10\StatusInterface::LOOKUP_UNKNOWN_ERROR);
    }

    public function testConstant_LOOKUP_UNKNOWN_ERROR_MSG()
    {
        $message = 'Unknown LookupResponse status';
        $this->assertSame($message, DominoPOS\OrbitAPI\v10\StatusInterface::LOOKUP_UNKNOWN_ERROR_MSG);
    }

    public function testConstant_PARAM_MISSING_TIMESTAMP()
    {
        $missingTimestamp = 7;
        $this->assertSame($missingTimestamp, DominoPOS\OrbitAPI\v10\StatusInterface::PARAM_MISSING_TIMESTAMP);
    }

    public function testConstant_PARAM_MISSING_TIMESTAMP_MSG()
    {
        $message = 'Missing the \'timestamp\' parameter in query string';
        $this->assertSame($message, DominoPOS\OrbitAPI\v10\StatusInterface::PARAM_MISSING_TIMESTAMP_MSG);
    }

    public function testConstant_PARAM_MISSING_CLIENT_ID()
    {
        $lookupUnknownError = 8;
        $this->assertSame($lookupUnknownError, DominoPOS\OrbitAPI\v10\StatusInterface::PARAM_MISSING_CLIENT_ID);
    }

    public function testConstant_PARAM_MISSING_CLIENT_ID_MSG()
    {
        $message = 'Missing the \'clientid\' parameter in query string';
        $this->assertSame($message, DominoPOS\OrbitAPI\v10\StatusInterface::PARAM_MISSING_CLIENT_ID_MSG);
    }

    public function testConstant_PARAM_MISSING_VERSION_API()
    {
        $missingVersion = 9;
        $this->assertSame($missingVersion, DominoPOS\OrbitAPI\v10\StatusInterface::PARAM_MISSING_VERSION_API);
    }

    public function testConstant_PARAM_MISSING_VERSION_API_MSG()
    {
        $message = 'Missing the \'version\' parameter in query string';
        $this->assertSame($message, DominoPOS\OrbitAPI\v10\StatusInterface::PARAM_MISSING_VERSION_API_MSG);
    }

    public function testConstant_PARAM_MISSING_SIGNATURE()
    {
        $missingSignature = 10;
        $this->assertSame($missingSignature, DominoPOS\OrbitAPI\v10\StatusInterface::PARAM_MISSING_SIGNATURE);
    }

    public function testConstant_PARAM_MISSING_SIGNATURE_MSG()
    {
        $message = 'Missing the \'signature\' parameter in http header';
        $this->assertSame($message, DominoPOS\OrbitAPI\v10\StatusInterface::PARAM_MISSING_SIGNATURE_MSG);
    }

    public function testConstant_PARAM_INVALID_TIMESTAMP()
    {
        $missingSignature = 11;
        $this->assertSame($missingSignature, DominoPOS\OrbitAPI\v10\StatusInterface::PARAM_INVALID_TIMESTAMP);
    }

    public function testConstant_PARAM_INVALID_TIMESTAMP_MSG()
    {
        $message = 'The \'timestamp\' parameter must be in Unix timestamp';
        $this->assertSame($message, DominoPOS\OrbitAPI\v10\StatusInterface::PARAM_INVALID_TIMESTAMP_MSG);
    }

    public function testConstant_UNSUPORTED_HASHING_ALGORITHM()
    {
        $missingSignature = 12;
        $this->assertSame($missingSignature, DominoPOS\OrbitAPI\v10\StatusInterface::UNSUPORTED_HASHING_ALGORITHM);
    }

    public function testConstant_UNSUPORTED_HASHING_ALGORITHM_MSG()
    {
        $message = 'The \'%s\' algorithm is not supported';
        $this->assertSame($message, DominoPOS\OrbitAPI\v10\StatusInterface::UNSUPORTED_HASHING_ALGORITHM_MSG);
    }

    public function testConstant_ACCESS_DENIED()
    {
        $accessDenied = 13;
        $this->assertSame($accessDenied, DominoPOS\OrbitAPI\v10\StatusInterface::ACCESS_DENIED);
    }

    public function testConstant__MSG()
    {
        $message = 'You do not have permission to access the specified resource';
        $this->assertSame($message, DominoPOS\OrbitAPI\v10\StatusInterface::ACCESS_DENIED_MSG);
    }
}
