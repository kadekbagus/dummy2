<?php namespace Orbit\Helper\Net\Wordpress;
/**
 * Fetch Wordpress Posts prodused by WP-Rest API
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use Exception;
use stdClass;
use Orbit\Helper\Net\HttpFetcher\FactoryFetcher;

class PostFetcher
{
    const ERR_PARSE_WP_JSON = 100;

    /**
     * Base URL of the blog
     *
     * @var
     */
    protected $config = [
        'driver' => 'curl',
        'base_blog_url' => NULL,
        'take' => 6,
        'default_image_url' => NULL
    ];

    /**
     * HTTP client object
     *
     * @var HttpFetcher
     */
    protected $fetcher = NULL;

    /**
     * WP-Rest API v2 path
     *
     * @var string
     */
    protected $wpApipath = '/wp-json/wp/v2';

    /**
     * @param array $baseUrlBlog
     * @return void
     */
    public function __construct(array $config=[])
    {
        $this->config = $config + $this->config;
        $this->setHttpFetcherInstance();
    }

    /**
     * @param string $outputType json|array
     * @return PostFetcher
     */
    public function getPosts($outputType='array')
    {
        // WP-Rest API v2 arguments
        $args = [
            'per_page' => $this->config['take']
        ];

        $wpApiUrl = rtrim($this->config['base_blog_url'], '/');
        $wpApiUrl .= $this->wpApipath . '/posts';
        $fetchResult = $this->fetcher->getUrl($wpApiUrl, $args);

        switch ($outputType) {
            case 'json':
                return json_encode($this->transformDataFromWp($fetchResult));
                break;

            case 'array':
            default:
                return $this->transformDataFromWp($fetchResult);
                break;
        }
    }

    /**
     * Return the Http client object
     *
     * @return Orbit\Helper\Net\HttpFetcher\FetcherInterface
     */
    public function getHttpFetcherInstance()
    {
        return $this->fetcher;
    }

    /**
     * @param string $json Posts in JSON
     * @return array
     */
    protected function transformDataFromWp($json)
    {
        if (is_null($postsData = json_decode($json))) {
            throw new Exception('Failed to decode JSON from Wordpress', static::ERR_PARSE_WP_JSON);
        }

        if (count($postsData) === 0) {
            return [];
        }

        $result = [];
        foreach ($postsData as $i=>$post) {
            $tmp = new stdClass();
            $tmp->post_url = $post->link;
            $tmp->image_url = $this->getImageFromPost($post);
            $tmp->title = $post->title->rendered;
            $tmp->content = strip_tags($post->excerpt->rendered);
            $tmp->post_date = $post->date;

            $result[] = $tmp;
        }

        return $result;
    }

    /**
     * Parse the image from the post or return the alternative if empty.
     *
     * @param object $post
     * @return string
     */
    protected function getImageFromPost($post)
    {
        if (! property_exists($post, 'better_featured_image')) {
            return $this->config['default_image_url'];
        }

        if (! is_object($post->better_featured_image)) {
            return $this->config['default_image_url'];
        }

        if (! property_exists($post->better_featured_image, 'media_details')) {
            return $this->config['default_image_url'];
        }

        if (! is_object($post->better_featured_image->media_details)) {
            return $this->config['default_image_url'];
        }

        if (! property_exists($post->better_featured_image->media_details, 'sizes')) {
            return $this->config['default_image_url'];
        }

        if (! is_object($post->better_featured_image->media_details->sizes)) {
            return $this->config['default_image_url'];
        }

        if (! property_exists($post->better_featured_image->media_details->sizes, 'thumbnail')) {
            return $this->config['default_image_url'];
        }

        return $post->better_featured_image->media_details->sizes->thumbnail->source_url;
    }

    /**
     * Set the Http client based on the driver
     *
     * @return Orbit\Helper\Net\HttpFetcher\FetcherInterface;
     */
    protected function setHttpFetcherInstance()
    {
        $driver = $this->config['driver'];
        switch ($driver) {
            case 'curl':
            case 'fake':
                $this->fetcher = FactoryFetcher::create($driver)->getInstance();
                break;

            default:
                throw new Exception('Unknown driver passed to PostFetcher');
        }
    }
}