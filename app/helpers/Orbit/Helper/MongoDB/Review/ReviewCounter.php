<?php namespace Orbit\Helper\MongoDB\Review;
/**
 * Helper to get review counters and averages
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use Orbit\Helper\MongoDB\Client as MongoClient;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use Exception;
use Country;

class ReviewCounter
{
    /**
     * @var string | array of object id - required
     */
    protected $objectId = null;

    /**
     * @var string object type - required
     */
    protected $objectType = null;

    /**
     * @var array mongoDB config
     */
    protected $mongoConfig = [];

    /**
     * @var string node endpoint for review-counters
     */
    protected $counterEndpoint = 'review-counters';

    /**
     * @var string node endpoint for mall-review-counters
     */
    protected $mallCounterEndpoint = 'mall-review-counters';

    /**
     * @var Mall mall object
     */
    protected $mall = null;

    /**
     * @var array of location_id
     */
    protected $locationIds = [];

    /**
     * int Returned value of average
     */
    protected $average = null;

    /**
     * int Returned value of counter
     */
    protected $counter = null;

    /**
     * array Returned value from mongoDB
     */
    protected $response = [];

    /**
     * @var int decimal number
     */
    protected $decimalNumber = 1;

    /**
     * @param array mongodb config
     */
    public function __construct($mongoConfig=[])
    {
        $this->mongoConfig = $mongoConfig;
    }

    /**
     * @param array mongodb config
     * @return Orbit\Helper\MongoDB\Review\ReviewCounter
     */
    public static function create($mongoConfig=[])
    {
        return new static($mongoConfig);
    }

    /**
     * @param string object id
     * @return Orbit\Helper\MongoDB\Review\ReviewCounter
     */
    public function setObjectId($objectId)
    {
        $this->objectId = $objectId;
        return $this;
    }

    /**
     * @param string object type
     * @return Orbit\Helper\MongoDB\Review\ReviewCounter
     */
    public function setObjectType($objectType)
    {
        $this->objectType = $objectType;
        return $this;
    }

    /**
     * @param Mall mall object
     * @return Orbit\Helper\MongoDB\Review\ReviewCounter
     */
    public function setMall($mall)
    {
        $this->mall = $mall;
        return $this;
    }

    /**
     * @param array
     * @return Orbit\Helper\MongoDB\Review\ReviewCounter
     */
    public function setLocationIds($locationIds)
    {
        $this->locationIds = $locationIds;
        return $this;
    }

    /**
     * @param array cities - passed from OrbitShop\API\v1\Helper\Input
     * @param array country - passed from OrbitShop\API\v1\Helper\Input
     * @return Orbit\Helper\MongoDB\Review\ReviewCounter
     */
    public function request()
    {
        try {
            if (empty($this->objectId)) {
                throw new Exception("ReviewCounter: Object ID is not set", 1);
            }
            if (empty($this->objectType)) {
                throw new Exception("ReviewCounter: Object Type is not set", 1);
            }
            if (empty($this->mongoConfig)) {
                throw new Exception("ReviewCounter: Mongo Config is not set", 1);
            }

            $reviewQueryParams = [
                'object_id' => $this->objectId,
                'object_type' => $this->objectType,
            ];

            if (is_object($this->mall)) {
                // Mall Level
                if (! is_a($this->mall, 'Mall')) {
                    throw new Exception("ReviewCounter: Mall is not valid", 1);
                }
                $counterEndpoint = $this->mallCounterEndpoint;
                $reviewQueryParams['location_id'] = $this->mall->merchant_id;
            } else {
                if (! empty($this->locationIds)) {
                    // Location list
                    $counterEndpoint = $this->mallCounterEndpoint;
                    $reviewQueryParams['location_id'] = $this->locationIds;
                } else {
                    // GTM Level
                    $counterEndpoint = $this->counterEndpoint;
                    $cityFilters = OrbitInput::get('cities', null);
                    $countryFilter = OrbitInput::get('country', null);

                    if (! empty($cityFilters)) $reviewQueryParams['cities'] = $cityFilters;
                    if (! empty($countryFilter)) {
                        $country = Country::where('name', $countryFilter)->first();
                        if (is_object($country)) $reviewQueryParams['country_id'] = $country->country_id;
                    }
                }
            }

            $reviewCounterResponse = \Orbit\Helper\MongoDB\Client::create($this->mongoConfig)
                ->setQueryString($reviewQueryParams)
                ->setEndPoint($counterEndpoint)
                ->request('GET');

            $this->response = $reviewCounterResponse;

            if ($reviewCounterResponse->data->total_records > 0) {
                $sumAverage = 0;
                $sumCounter = 0;
                foreach ($reviewCounterResponse->data->records as $record) {
                    $sumAverage = $sumAverage + ($record->average * $record->counter);
                    $sumCounter = $sumCounter + $record->counter;
                }
                if ($sumCounter > 0) {
                    $this->average = $sumAverage / $sumCounter;
                    $this->counter = $sumCounter;
                }
            }

        } catch (Exception $e) {

        }

        return $this;
    }

    /**
     * @return array
     */
    public function get()
    {
        $this->average = is_null($this->average) ? $this->average : $this->formatNumber($this->average);
        return ['average' => $this->average, 'counter' => $this->counter];
    }

    /**
     * @return int | null
     */
    public function getAverage()
    {
        $this->average = is_null($this->average) ? $this->average : $this->formatNumber($this->average);
        return $this->average;
    }

    /**
     * @return int | null
     */
    public function getCounter()
    {
        return $this->counter;
    }

    /**
     * @return array
     */
    public function getResponse()
    {
        if (isset($this->response->data->records)) {
            foreach ($this->response->data->records as &$record) {
                // format average number
                $record->average = is_null($record->average) ? $record->average : $this->formatNumber($record->average);
            }
        }
        return $this->response;
    }

    protected function formatNumber($number)
    {
        // multiply by decimal number
        $multiplier = pow(10, $this->decimalNumber);
        // round them up
        $roundedNumber = ceil(($number * $multiplier));
        // get the rounded number
        $number = number_format(($roundedNumber/$multiplier), $this->decimalNumber, ".", " ");
        return $number;
    }
}