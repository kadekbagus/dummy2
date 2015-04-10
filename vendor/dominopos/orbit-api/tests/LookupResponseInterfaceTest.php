<?php
/**
 * Unit test for LookupResponseInterface.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */

class LookupResponseInterfaceTest extends PHPUnit_Framework_TestCase
{
    // The stub and mocking need a full namespace to works properly
    private $fullNamespace = 'DominoPOS\OrbitAPI\v10\LookupResponseInterface';
    
    public function testInstance()
    {
        // Stub (Bahasa: pura-pura menjadi) the interface
        $stub = $this->getMock($this->fullNamespace);
        $this->assertInstanceOf($this->fullNamespace, $stub);
    }

    public function testMethod_getClientID() {
        $stub = $this->getMock($this->fullNamespace);
        $stub->expects( $this->any() )
             ->method('getClientID')
             ->will( $this->returnValue('client123') );

        $this->assertSame($stub->getClientID(), 'client123');
    }

    public function testMethod_getClientSecretKey() {
        $stub = $this->getMock($this->fullNamespace);
        $stub->expects( $this->any() )
             ->method('getClientSecretKey')
             ->will( $this->returnValue('SomeRandomString123456') );

        $this->assertSame($stub->getClientSecretKey(), 'SomeRandomString123456');
    }

    public function testMethod_getClientStatus_with_response_OK() {
        $statusOK = 0;
        
        $stub = $this->getMock($this->fullNamespace);
        $stub->expects( $this->any() )
             ->method('getStatus')
             ->will( $this->returnValue($statusOK) );

        $this->assertSame($stub->getStatus(), DominoPOS\OrbitAPI\v10\LookupResponseInterface::LOOKUP_STATUS_OK);
    }

    public function testMethod_getClientStatus_with_response_NOT_FOUND() {
        $statusNotFound = 1;
        
        $stub = $this->getMock($this->fullNamespace);
        $stub->expects( $this->any() )
             ->method('getStatus')
             ->will( $this->returnValue($statusNotFound) );

        $this->assertSame($stub->getStatus(), DominoPOS\OrbitAPI\v10\LookupResponseInterface::LOOKUP_STATUS_NOT_FOUND);
    }

    public function testMethod_getClientStatus_with_response_ACCESS_DENIED() {
        $statusAccessDenied = 2;
        
        $stub = $this->getMock($this->fullNamespace);
        $stub->expects( $this->any() )
             ->method('getStatus')
             ->will( $this->returnValue($statusAccessDenied) );

        $this->assertSame($stub->getStatus(), DominoPOS\OrbitAPI\v10\LookupResponseInterface::LOOKUP_STATUS_ACCESS_DENIED);
    }
}
