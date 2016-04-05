<?php namespace OrbitShop\API\v1;
/**
 * Base API Controller.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Exception;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Orbit\Builder as OrbitBuilder;
use PDO;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;

abstract class ControllerAPI extends Controller
{
    /**
     * The return value (response) of Controller API.
     *
     * @var ResponseAPI
     */
    public $response = NULL;

    /**
     * Direct access to the PHP PDO object which currently hold the connection.
     *
     * @var PDO
     */
    private $pdo = NULL;

    /**
     * The HTTP response type.
     *
     * @var string
     */
    public $contentType = 'application/json';

    /**
     * Store the Authentication.
     *
     * @var OrbitShopAPI
     */
    public $api;

    /**
     * Flag whether to use authentication or not.
     *
     * @var requireAuth
     */
    public $requireAuth = TRUE;

    /**
     * Maximum number of record that should be returned from query.
     *
     * @var int
     */
    public $maxRecord = 100;

    /**
     * Default number of record that should be returned if no limit spesified.
     *
     * @var int
     */
    public $defaultNumberOfRecord = 20;

    /**
     * How long request should considered invalid in seconds.
     *
     * @var int
     */
    public $expiresTime = 60;

    /**
     * Custom headers sent to the client
     *
     * @var array
     */
    public $customHeaders = array();

    /**
     * Prettify the output.
     *
     * @var boolean
     */
    public $prettyPrintJSON = FALSE;

    /**
     * Bleeding Edge Feature that controller can return only the query for print and export purposes
     * @see #getBuilderFor()
     * @var boolean $builderOnly
     */
    protected $builderOnly = FALSE;

    /**
     * Flag for database transaction.
     *
     * @var boolean
     */
    public $useTransaction = TRUE;

    /**
     * Common method for API Controller
     */
    use CommonAPIControllerTrait;

    /**
     * Contructor
     *
     * @param string $contentType - HTTP content type that would be sent to client
     */
    public function __construct($contentType = 'application/json') {
        // default content type set to JSON
        $this->contentType = $contentType;

        // Set the default response
        $this->response = new ResponseProvider();

        // Assign the PDO object
        $this->pdo = DB::connection()->getPdo();

        $expires = Config::get('orbit.api.signature.expiration');
        if ((int)$expires > 0)
        {
            $this->expiresTime = $expires;
        }
    }

    /**
     * Static method to instantiate the object.
     *
     * @param string $contentType
     * @return ControllerAPI
     */
    public static function create($contentType = 'application/json')
    {
        return new static($contentType);
    }

    /**
     * Method to authenticate the API consumer.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @param string $input_secret_name - Default name for getting secret key input.
     * @return void
     * @thrown Exception
     */
    public function checkAuth($forbiddenUserStatus=['blocked', 'pending', 'deleted'])
    {
        // Get the api key from query string
        $clientkey = (isset($_GET['apikey']) ? $_GET['apikey'] : '');

        // Instantiate the OrbitShopAPI
        $this->api = new OrbitShopAPI($clientkey, $forbiddenUserStatus);

        // Set the request expires time
        $this->api->expiresTimeFrame = $this->expiresTime;

        // Run the signature check routine
        $this->api->checkSignature();
    }

    /**
     * Return the output of the API to the caller.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param int $httpCode - The HTTP status code response.
     * @return \OrbitShop\API\v1\ResponseProvider | string
     */
    public function render($httpCode=200)
    {
        $output = '';

        switch ($this->contentType) {
            case 'raw':
                return $this->response;
                break;

            case 'application/json':
            default:
                $json = new \stdClass();
                $json->code = $this->response->code;
                $json->status = $this->response->status;
                $json->message = $this->response->message;
                $json->data = $this->response->data;

                if ($this->prettyPrintJSON) {
                    $output = json_encode($json, JSON_PRETTY_PRINT);
                } else {
                    $output = json_encode($json);
                }

                // Allow Cross-Domain Request
                // http://enable-cors.org/index.html
                $this->customHeaders['Access-Control-Allow-Origin'] = '*';
                $this->customHeaders['Access-Control-Allow-Methods'] = 'GET, POST';
                $this->customHeaders['Access-Control-Allow-Credentials'] = 'true';

                $angularTokenName = Config::get('orbit.security.csrf.angularjs.header_name');
                $sessionHeader = Config::get('orbit.session.session_origin.header.name');
                $allowHeaders = array(
                    'Origin',
                    'Content-Type',
                    'Accept',
                    'Authorization',
                    'X-Request-With',
                    'X-Orbit-Signature',
                    'Cookie',
                    'Set-Cookie',
                    $sessionHeader,
                    'Set-' . $sessionHeader
                );
                if (! empty($angularTokenName)) {
                    $allowHeaders[] = $angularTokenName;
                }

                $this->customHeaders['Access-Control-Allow-Headers'] = implode(',', $allowHeaders);
                $this->customHeaders['Access-Control-Expose-Headers'] = implode(',', $allowHeaders);
        }

        $headers = array('Content-Type' => $this->contentType) + $this->customHeaders;

        return Response::make($output, $httpCode, $headers);
    }

    /**
     * Magic method which alled when calling undefined method handler on
     * controller.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $method - The method name
     * @param array $args - The arguments
     * @return \OrbitShop\API\v1\ResponseProvider | string
     */
    public function __call($method, $args)
    {
        $this->response->code = 404;
        $this->response->status = 'error';
        $this->response->message = 'Request URL not found';
        $this->response->data = NULL;

        return $this->render(404);
    }

    /**
     * Used for easter eggs :)
     *
     * @author Rio Astamal <me@rioastamal.net>
     */
    protected function formatInfo($info)
    {
        $do = isset($_GET['do']) ? $_GET['do'] : '';
        $date = gmdate('Y-m-d');

        if ($do !== md5('easter-eggs' . $date)) {
            return $info;
        }

        $info->message = 'Congratulation you are the gifted one! Normally only Einstein or Tesla who could open this page.';
        $info->the_team = $this->createEasterEggs();

        // Used to ouput the HTML
        $info->format = 'plain';
        $info->output = '';

        $this->prettyPrintJSON = TRUE;

        return $info;
    }

    /**
     * Bleeding edge feature that return query builder from controller
     * @param string $action controller action name
     * @return \Orbit\Builder
     * @throws Exception
     */
    public function getBuilderFor($action)
    {
        $this->builderOnly = true;
        $builder = call_user_func(array($this, $action));
        $this->builderOnly = false;

        if (! ($builder instanceof OrbitBuilder))
        {
            throw new Exception('Action do not return builder instance please make sure to check and return builder only from action', 0);
        }

        return $builder;
    }

    /**
     * Bleeding edge feature that return query builder from controller
     * @param \Illuminate\Database\Eloquent\Builder $builder
     * @param \Illuminate\Database\Eloquent\Builder $unsorted
     * @param array $options
     * @return object
     */
    public function builderObject($builder, $unsorted, $options = [])
    {
        return OrbitBuilder::create()
            ->setBuilder($builder)
            ->setUnsorted($unsorted)
            ->setOptions($options);
    }

    /**
     * Create an easter eggs :)
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return array
     */
    protected function createEasterEggs()
    {
        $data = [
            'rio' => [
                'name'      => 'Rio Astamal',
                'email'     => 'rio@dominopos.com',
                'role'      => 'Lead Developer',
                'message'   => 'Born to be root#',
                'image'     => 'http://rioastamal.net/portfolio/img/me-gray.jpg?build=13'
            ],
            'ahmad' => [
                'name'      => 'Ahmad Anshori',
                'email'     => 'ahmad@dominopos.com',
                'role'      => 'Backend Developer',
                'message'   => 'More coffee please?',
                'image'     => 'https://avatars0.githubusercontent.com/u/6212359?v=3&s=460'
            ],
            'tian' => [
                'name'      => 'Tian Lim',
                'email'     => 'tian@dominopos.com',
                'role'      => 'Backend Developer',
                'message'   => '???',
                'image'     => '#'
            ],
            'kadek' => [
                'name'      => 'Kadek Bagus',
                'email'     => 'kadek@dominopos.com',
                'role'      => 'Backend Developer',
                'message'   => 'I\'m the bad boy',
                'image'     => '#'
            ],
            'danish' => [
                'name'      => 'Danish Kalesaran',
                'email'     => 'danish@dominopos.com',
                'role'      => 'Front End Warrior',
                'message'   => 'Through rancor find serenity',
                'image'     => '#'
            ],
            'agung' => [
                'name'      => 'Agung Julisman',
                'email'     => 'agung@dominopos.com',
                'role'      => 'Front End Developer',
                'message'   => 'I love what i do.',
                'image'     => '#'
            ],
            'lanang' => [
                'name'      => 'Lanang Satrio Hutomo',
                'email'     => 'lanang@dominopos.com',
                'role'      => 'Graphic and UX Designer',
                'message'   => 'Do you wanna to watch an opera? I\'m the actor',
                'image'     => '#'
            ],
            'riko' => [
                'name'      => 'Riko Suswidiantoro',
                'email'     => 'riko@dominopos.com',
                'role'      => 'System Engineer',
                'message'   => 'Odd person, adventurer, automotive, sport, this is me',
                'image'     => '#'
            ],
        ];
        $eggs = [];

        foreach ($data as $person) {
            $tmp = new \stdClass();
            $tmp->name = $person['name'];
            $tmp->email = $person['email'];
            $tmp->role = $person['role'];
            $tmp->message = $person['message'];
            $tmp->image = $person['image'];

            $eggs[] = $tmp;
        }

        return $eggs;
    }
}
