<?php namespace Arrays\Util;
/**
 * Check for duplicate content inside an one dimensional array.
 *
 * @author Rio Astamal <me@rioastmal.net>
 */
class DuplicateChecker
{
    /**
     * The array
     *
     * @var array
     */
    protected $data = array();

    /**
     * Constructor
     *
     * @author Rio Astamal <me@rioastamla.net>
     * @param array $data
     * @return void
     */
    public function __construct($data)
    {
        $this->setData($data);
    }

    /**
     * Static method to instantiate the class
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param array $values
     * @return DuplicateChecker
     */
    public static function create($data)
    {
        return new static ($data);
    }

    /**
     * Set the value of data property
     *
     * @author Rio Astamal <me@rioastamal>
     * @param array $data
     * @return DuplicateChecker
     */
    public function setData(array $data)
    {
        $this->data = $data;
    }

    /**
     * Check the duplicate value.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return boolean
     */
    public function hasDuplicate()
    {
        // If the normal count() bigger than unique count than it has
        // duplicate value
        return count($this->data) > count(array_unique($this->data)) ? TRUE : FALSE;
    }
}
