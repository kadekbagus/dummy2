<?php

use Laracasts\TestDummy\Factory;

class TestCase extends Illuminate\Foundation\Testing\TestCase {

	protected static $registered;

	protected static $directories;

	/**
	 * Creates the application.
	 *
	 * @return \Symfony\Component\HttpKernel\HttpKernelInterface
	 */
	public function createApplication()
	{
		$unitTesting = true;

		$testEnvironment = 'testing';

		return require __DIR__.'/../../bootstrap/start.php';
	}

    public function setUp()
    {
        parent::setUp();

        $this->useTruncate = true;
    }

	public function tearDown()
	{
        // Truncate all tables, except migrations
        if($this->useTruncate) {
            $tables = DB::select('SHOW TABLES');
            $tables_in_database = 'Tables_in_' . DB::getDatabaseName();
            foreach ($tables as $table) {
                if ($table->$tables_in_database !== 'migrations') {
                    $tableName = str_replace(DB::getTablePrefix(), '', $table->$tables_in_database);
                    DB::table($tableName)->truncate();
                }
            }
        }

		unset($_GET);
		unset($_POST);
		$_GET = array();
		$_POST = array();

		unset($_SERVER['HTTP_X_ORBIT_SIGNATURE'],
			$_SERVER['REQUEST_METHOD'],
			$_SERVER['REQUEST_URI']
		);


		parent::tearDown();
	}

	public static function setupBeforeClass()
	{
		$vendor = __DIR__ . '/../../vendor/';

		static::$directories = array(
			$vendor . 'fzaninotto/faker/src',
			$vendor . 'laracasts/testdummy/src'
		);

		if (! static::$registered) {
			static::$registered = spl_autoload_register(array('TestCase', 'loadTestLibrary'));
		}

		Factory::$factoriesPath = __DIR__ . '/factories';
	}

	public static function loadTestLibrary($className)
	{
		$className = ltrim($className, '\\');
		$fileName  = '';
		$namespace = '';

	    if ($lastNsPos = strripos($className, '\\')) {
	        $namespace = substr($className, 0, $lastNsPos);
	        $className = substr($className, $lastNsPos + 1);
	        $fileName = str_replace('\\', DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;
	    }

		foreach (static::$directories as $directory) {
			if (file_exists($path = $directory . DIRECTORY_SEPARATOR . $className . '.php')) {
				require_once $path;

				return true;
			}

			if (file_exists($path = $directory . DIRECTORY_SEPARATOR . $fileName . $className . '.php')) {
				require_once $path;

				return true;
			}
		}

	    return false;
	}

}

/**
 * Filter an array using keys instead of values.
 *
 * @param  array    $array
 * @param  callable $callback
 * @return array
 */
function filter_array_keys(array $array, $callback)
{
	$matchedKeys = array_filter(array_keys($array), $callback);

	return array_intersect_key($array, array_flip($matchedKeys));
}
