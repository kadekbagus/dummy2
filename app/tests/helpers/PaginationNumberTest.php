<?php
/**
 * Unit test for PaginationNumber class.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Orbit\Helper\Util\PaginationNumber as Pagination;

class PaginationTest extends OrbitTestCase
{
    protected $config = [
        'per_page'      => 'x.pagination.%s.per_page',
        'max_record'    => 'x.pagination.%s.max_record'
    ];
    protected $fallbackConfig = [
        'per_page'      => 'x.pagination.per_page',
        'max_record'    => 'x.pagination.max_record',
    ];

    public function testInstance()
    {
        $pg = Pagination::create('mytest');
        $this->assertInstanceOf('Orbit\Helper\Util\PaginationNumber', $pg);
    }

    public function testSetPerPageValueConfigNotExists()
    {
        $pg = Pagination::create('mytest', $this->config)
                        ->setDefaultPerPageConfig($this->fallbackConfig['per_page']);

        $defaultPerPage = $pg->perPage;
        $perPage = $pg->setPerPage()->perPage;

        $this->assertSame($defaultPerPage, $perPage);
    }

    public function testSetPerPageValueConfigExists()
    {
        $listname = 'mytest';
        $expect = 45;
        $perPageConfig = sprintf($this->config['per_page'], $listname);
        Config::set($perPageConfig, $expect);

        $pg = Pagination::create($listname, $this->config);
        $perPage = $pg->setPerPage($expect)->perPage;
        $this->assertSame($expect, $perPage);
    }

    public function testSetPerPageValueConfigExistsMoreThanMaxRecordFallbackToMaxRecord()
    {
        $listname = 'mytest';
        $expect = 50;
        $perPageOverflow = 99;
        $perPageConfig = sprintf($this->config['per_page'], $listname);
        Config::set($perPageConfig, $expect);

        $pg = Pagination::create($listname, $this->config);
        $perPage = $pg->setPerPage($perPageOverflow)->perPage;
        $this->assertSame($expect, $perPage);
    }

    public function testSetPerPageValueConfigNotExistsFallbackToConfigValue()
    {
        $listname = 'mytest';
        $expect = 49;
        $perPageConfig = $this->fallbackConfig['per_page'];

        Config::set($perPageConfig, $expect);

        $pg = Pagination::create($listname, $this->config);
        $pg->setDefaultPerPageConfig($perPageConfig);

        $perPage = $pg->setPerPage($expect)->perPage;
        $this->assertSame($expect, $perPage);
    }

    public function testSetMaxRecordValueConfigNotExists()
    {
        $pg = Pagination::create('mytest', $this->config)
                        ->setDefaultMaxRecordConfig($this->fallbackConfig['max_record']);

        $defaultMaxRecord = $pg->maxRecord;
        $maxRecord = $pg->setMaxRecord()->maxRecord;

        $this->assertSame($defaultMaxRecord, $maxRecord);
    }

    public function testSetMaxRecordValueConfigExists()
    {
        $listname = 'mytest';
        $expect = 222;
        $maxRecordConfig = $this->fallbackConfig['max_record'];
        Config::set($maxRecordConfig, $expect);

        $pg = Pagination::create($listname, $this->config)
                        ->setDefaultMaxRecordConfig($maxRecordConfig);
        $maxRecord = $pg->setMaxRecord()->maxRecord;
        $this->assertSame($expect, $maxRecord);
    }

    public function testSetMaxRecordValueConfigNotExistsFallbackToConfigValue()
    {
        $listname = 'mytest';
        $expect = 333;
        $maxRecordConfig = $this->fallbackConfig['max_record'];
        Config::set($maxRecordConfig, $expect);

        $pg = Pagination::create($listname, $this->config)
                        ->setDefaultMaxRecordConfig($maxRecordConfig);
        $maxRecord = $pg->setMaxRecord()->maxRecord;
        $this->assertSame($expect, $maxRecord);
    }
}
