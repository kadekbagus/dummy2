<?php namespace Orbit\Helper\Elasticsearch;
/**
 * Helper for simplify getting index name and index type
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
class IndexNameBuilder
{
    /**
     * Elasticsearch config
     *
     * @var array
     */
    protected $config = [];

    /**
     * Class constructor
     *
     * @param array $esConfig Elasticsearch configuration of Orbit
     * @return void
     */
    public function __construct($esConfig)
    {
        $this->config = $esConfig;
    }

    /**
     * @param array $esConfig Elasticsearch configuration of Orbit
     * @return IndexNameBuilder
     */
    public static function create($esConfig)
    {
        return new static($esConfig);
    }

    /**
     * Get index name
     *
     * @string string $index Name of the index
     * @return string
     */
    public function getIndexName($index)
    {
        return $this->config['indices'][$index]['index'];
    }

    /**
     * Get index type name
     */
    public function getTypeName($type)
    {
        return $this->config['indices'][$type]['type'];
    }

    /**
     * @param array $esConfig
     * @return IndexNameBuilder
     */
    public function setConfig($esConfig)
    {
        $this->config = $esConfig;

        return $this;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return string
     */
    public function getIndexPrefix()
    {
        return $this->config['indices_prefix'];
    }

    /**
     * Get index prefix and its name combined.
     *
     * @param string $index Name of the index
     * @return string
     */
    public function getIndexPrefixAndName($index)
    {
        return $this->getIndexPrefix() . $this->getIndexName($index);
    }
}