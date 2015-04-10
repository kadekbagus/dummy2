<?php
/**
 * Custom base class for unit testing Orbit Application. The purpose of this
 * class is to boot the Laravel application on setUpBeforeClass() method.
 * Current laravel default TestCase does not provide this.
 *
 * @author Rio Astamal <me@rioastamal>
 */
class OrbitTestCase extends Illuminate\Foundation\Testing\TestCase
{
    /**
     * Hold the instance of laravel App.
     */
    protected static $laravelApp;

    /**
     * Store database configuration.
     */
    protected static $dbConfig;

    /**
     * Prefix for database
     */
    protected static $dbPrefix;

    public static function createAppStatic()
    {
        $unitTesting = true;
        $testEnvironment = 'testing';

        static::$laravelApp = require __DIR__ . '/../../bootstrap/start.php';

        $connectionName = self::$laravelApp['config']['database']['default'];
        static::$dbConfig = self::$laravelApp['config']['database']['connections'][$connectionName];
        static::$dbPrefix = self::$dbConfig['prefix'];
    }

    /**
     * Creates the application.
     *
     * @return \Symfony\Component\HttpKernel\HttpKernelInterface
     */
    public function createApplication()
    {
        if (static::$laravelApp) {
            return static::$laravelApp;
        }

        $unitTesting = true;
        $testEnvironment = 'testing';

        return require __DIR__ . '/../../bootstrap/start.php';
    }
}
