<?php namespace OrbitShop\API\v1;
/**
 * Base response provider for Controller API.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
class ResponseProvider
{
    /**
     * The status code.
     *
     * @var int
     */
    protected $code;

    /**
     * Status of the response.
     *
     * @var string
     */
    protected $status;

    /**
     * The full message of the response.
     *
     * @var string
     */
    protected $message;

    /**
     * The response data.
     *
     * @var mixed
     */
    protected $data;

    public function __construct()
    {
        // Set the default value for the response.
        $this->code     = 0;
        $this->status   = 'success';
        $this->message  = 'Request OK';
        $this->data     = NULL;
    }

    /**
     * @param int $code - The response code of the API
     * @return OrbitShop\API\v1\ResponseProvider
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @param string $status - The response status of the API, valid status are:
     *                         'success' or 'error'.
     * @return OrbitShop\API\v1
     */
    public function setStatus($status)
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @param string $message - The response message of the API
     * @return OrbitShop\API\v1
     */
    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @param mixed $data - The response data of the API.
     * @return OrbitShop\API\v1
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Magic method to get the protected attribute.
     *
     * @param string $attribute
     * @return mixed;
     */
    public function __get($attribute)
    {
        if (property_exists($this, $attribute)) {
            return $this->$attribute;
        }

        throw new \Exception ('Trying to get of unknown property from class ' . __CLASS__ . '.');
    }

    /**
     * Magic method to set the protected attribute.
     *
     * @param string $attribute
     * @return mixed;
     */
    public function __set($attribute, $value)
    {
        if (! property_exists($this, $attribute)) {
            throw new \Exception ('Trying to get of unknown property from class ' . __CLASS__ . '.');
        }

        $method = 'set' . ucfirst($attribute);
        if (method_exists($this, $method)) {
            return $this->$method($value);
        }
    }

    /**
     * Return response as array.
     * @return array
     */
    public function toArray()
    {
        return [
            'code' => $this->code,
            'status' => $this->status,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
