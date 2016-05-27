<?php namespace Orbit;
/**
 * Class for reading and storing settings.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Config;
use App;
use Setting as DBSetting;
use User;

class Setting
{
    /**
     * Base path of the application.
     *
     * @var string
     */
    protected $basePath = '';

    /**
     * Config path of the application.
     *
     * @var string
     */
    protected $configPath = '';

    /**
     * Store the settings
     *
     * @var array
     */
    protected $settings = [];

    /**
     * Flag for determined whether the global db call has been made.
     *
     * @var boolean
     */
    protected $loaded = FALSE;

    /**
     * User object.
     *
     * @var User
     */
    protected $user = NULL;

    /**
     * Current domain pattern which determine current mall/retailer.
     *
     * @var string
     */
    public $domainPattern = 'dom:%s';

    /**
     * Constructor
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    public function __construct()
    {
        if (! defined('DS')) {
            define('DS', DIRECTORY_SEPARATOR);
        }

        // Base path without trailing slash
        $this->basePath = realpath(__DIR__ . DS . '..' . DS . '..' . DS . '..');

        // Config path with trailing slash
        $this->configPath = $this->basePath . DS . 'app' . DS . 'config';
    }

    /**
     * Static method to instantiate the class.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Setting
     */
    public static function create()
    {
            return new static();
    }

    /**
     * Setting initialization, we do some checking here.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Setting
     * @throws Exception
     */
    public function init(User $user=NULL)
    {
        // Laravel main app configuration
        $appFile = $this->configPath . DS . 'app.php';

        // Laravel database configuration
        $dbFile = $this->configPath . DS . 'database.php';

        // Laravel mail configuration
        $mailFile = $this->configPath . DS . 'mail.php';

        // Orbit configuration
        $orbitFile = $this->configPath . DS . 'orbit.php';

        // Check if the file are exists and readable
        foreach ([$appFile, $dbFile, $orbitFile] as $file) {
            if (! file_exists($file) and ! is_readable($file)) {
                throw new \Exception(sprintf('Could not find "%s" file.', $file));
            }
        }

        // Do not do further checking if this is running from console
        if (App::runningInConsole()) {
            return $this;
        }

        // Check the application secret key, make sure developer change it first
        $defaultKey = 'YourSecretKey!!!';
        if (Config::get('app.key') === $defaultKey) {
            throw new \Exception('Please change "app.key" setting on app.php file .');
        }


        if (Config::get('app.aliases.Eloquent') != 'Orbit\Database\ModelWithObjectID') {
            throw new \Exception('Eloquent should be an Orbit\Database\ModelWithObjectID');
        };

        if ($user === NULL) {
            // Create dummy user
            $this->user = new \stdclass();
            $this->user->user_id = 0;
        }

        // Load settings from database
        $this->loadSettingsFromDB();

        // There is already config for setting current merchant via Config object.
        // Make sure all the codes does not break by overriding the value
        $currentDomain = sprintf($this->domainPattern, $_SERVER['HTTP_HOST']);
        $currentRetailer = $this->getSetting($currentDomain, 0);

        if ($currentRetailer) {
            // There is already config for setting current merchant via Config object.
            // Make sure all the codes does not break by overriding the value
            Config::set('orbit.shop.id', $currentRetailer);
            $this->settings['current_retailer'] = $currentRetailer;
        } else {
            $this->settings['current_retailer'] = '-';
        }

        return $this;
    }

    protected function loadSettingsFromDB()
    {
        // Get all settings which does not have object_id or its object id is 0
        // This mostly means global settings not object specific settings
        $me = $this;
        $settings = DBSetting::where(function($query) use ($me) {
            $query->where('object_id', 0);
            $query->orWhere('object_id', NULL);
            $query->orWhere(function($q) use ($me) {
                // Get the current mall/retailer by query settings which has name
                // dom:DOMAIN_NAME, the value should be the ID of the mall/retailer (from merchants table)
                $currentDomain = sprintf($me->domainPattern, $_SERVER['HTTP_HOST']);

                $q->where('setting_name', $currentDomain);
            });
        })->active()->orderBy('created_at', 'desc')->get();

        foreach ($settings as $setting) {
            $this->settings[$setting->setting_name] = $setting->setting_value;
        }

        // Set the flag to true
        $this->loaded = TRUE;
    }

    /**
     * Get setting by name.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $configName
     * @param mixed $setting - The default value
     * @return mixed
     */
    public function getSetting($configName, $default=NULL)
    {
        if (isset($this->settings[$configName])) {
            $default = $this->settings[$configName];
        }

        return $default;
    }

    /**
     * Set setting by name
     *
     * @param string $configName
     * @param mixed $configValue
     * @return Setting
     */
    public function setSetting($configName, $configValue)
    {
        $this->settings[$configName] = $configValue;

        return $this;
    }

    /**
     * Magic method to read properties.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return mixed|NULL
     */
    public function __get($key)
    {
        if (property_exists($this, $key))
        {
            return $this->{$key};
        }

        return NULL;
    }
}
