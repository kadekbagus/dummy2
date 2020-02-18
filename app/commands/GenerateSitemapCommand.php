<?php
/**
 * Command to print out sitemap
 * usage: php artisan generate:sitemap > app/storage/sitemap.xml
 * use crontab to generate sitemap file daily / weekly / monthly
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use OrbitShop\API\v1\Helper\Generator;

class GenerateSitemapCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'generate:sitemap';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate sitemap file of the frontend application';

    protected $appUrl = '';

    protected $hashBang = '';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $formatOutput = FALSE;

    /**
     * country name, eg: Indonesia, Singapore, etc.
     *
     * @var string
     */
    protected $country = 0;

    /**
     * Extra query string
     *
     * @var array
     */
    protected $extraParam = [];

    protected $sitemapType = '';

    protected $priority = '0.8';

    protected $user = NULL;

    /**
     * Sleep in microsecond
     *
     * @var integer
     */
    protected $sleep = 0;

    /**
     * Url template: http://www.gotomalls.com
     *
     * @var string
     */
    protected $urlTemplate = '';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->appUrl = rtrim(Config::get('app.url'), '/');
        $this->hashBang = Config::get('orbit.sitemap.hashbang', FALSE) ? '/#!/' : '/';
        $this->urlTemplate = $this->appUrl . $this->hashBang . '%s';
        $this->sitemapType = 'all';
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        try {
            $sitemapConfig = Config::get('orbit.sitemap', null);
            if (empty($sitemapConfig)) {
                throw new Exception("Cannot find sitemap config.", 1);
            }

            // Do not save all activity for this request
            Config::set('memory:do_not_save_activity', TRUE);

            // Disable read pageview count to redis
            Config::set('orbit.page_view.source', NULL);

            $this->user = User::with('apikey')
                ->leftJoin('roles', 'users.user_role_id', '=', 'roles.role_id')
                ->where('role_name', 'Super Admin')
                ->first();

            if (! is_object($this->user)) {
                throw new Exception("Cannot find super admin user.", 1);
            }

            $this->formatOutput = $this->option('format-output');
            $this->sitemapType = $this->option('type');
            $this->sleep = $this->option('sleep');
            $this->country = $this->option('country');
            if (! empty($this->country)) {
                $this->extraParam['country'] = $this->country;
                $this->extraParam['cities'] = 0;
            }

            $xmlHeader = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
                   '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

            print($xmlHeader);
            switch ($this->sitemapType) {
                case 'all-list':
                    $this->generateAllListSitemap();
                    break;

                case 'all-detail':
                    $this->generateAllDetailSitemap();
                    break;

                case 'promotion-detail':
                    $this->generatePromotionDetailsSitemap();
                    break;

                case 'coupon-detail':
                    $this->generateCouponDetailsSitemap();
                    break;

                case 'store-detail':
                    $this->generateStoreDetailsSitemap();
                    break;

                case 'event-detail':
                    $this->generateEventDetailsSitemap();
                    break;

                case 'mall-detail':
                    $this->generateMallDetailsSitemap();
                    break;

                case 'partner-detail':
                    $this->generatePartnerDetailsSitemap();
                    break;

                case 'article-detail':
                    $this->generateArticleListSitemap();
                    break;

                case 'pulsa':
                    $this->generatePulsaOperatorDetailSitemap();
                    break;

                case 'game-voucher':
                    $this->generateGameVoucherDetailSitemap();
                    break;

                case 'misc':
                    $this->generateMiscListSitemap();
                    break;

                case 'all':
                default:
                    $this->generateMiscListSitemap();
                    $this->generateAllListSitemap();
                    $this->generateAllDetailSitemap();
                    /* Disabled, as the article will have different sitemap than the rest */
                    // $this->generateArticleListSitemap();
                    break;
            }
        } catch (\Exception $e) {
            return print_r([$e->getMessage(), $e->getLine(), $e->getFile(), $e->getTraceAsString()]);
        }

        $xmlFooter = '</urlset>';
        print($xmlFooter);
    }

    /**
     * Generate all list sitemap
     *
     * @return void
     */
    protected function generateAllListSitemap($mall_id = null, $mall_slug = null)
    {
        $listUris = Config::get('orbit.sitemap.uri_properties.list', []);
        foreach ($listUris as $key => $uri) {
            $updatedAt = time();
            if (! empty($mall_id)) {
                // from mall detail
                $mallUri = Config::get('orbit.sitemap.uri_properties.detail.mall', []);
                $listUrlTemplate = sprintf($this->urlTemplate, $mallUri['uri']) . '/%s';
                $this->urlStringPrinter(sprintf($listUrlTemplate, $mall_id, $mall_slug, $uri['uri']), date('c', $updatedAt), $uri['changefreq']);
            } else {
                $this->urlStringPrinter(sprintf($this->urlTemplate, $uri['uri']), date('c', $updatedAt), $uri['changefreq']);
            }

        }
    }

    /**
     * Generate all list sitemap
     *
     * @return void
     */
    protected function generateAllDetailSitemap()
    {
        $listUris = Config::get('orbit.sitemap.uri_properties.detail', []);
        foreach ($listUris as $key => $uri) {
            switch ($key) {
                case 'promotion':
                    $xml = $this->generatePromotionDetailsSitemap();
                    break;

                case 'event':
                    $xml = $this->generateEventDetailsSitemap();
                    break;

                case 'coupon':
                    $xml = $this->generateCouponDetailsSitemap();
                    break;

                case 'mall':
                    $xml = $this->generateMallDetailsSitemap();
                    break;

                case 'store':
                    $xml = $this->generateStoreDetailsSitemap();
                    break;

                case 'partner':
                    $xml = $this->generatePartnerDetailsSitemap();
                    break;

                case 'pulsa':
                    $xml = $this->generatePulsaOperatorDetailSitemap();
                    break;

                case 'game-voucher':
                    $xml = $this->generateGameVoucherDetailSitemap();
                    break;

                default:
                    # code...
                    break;
            }
        }

        return $xml;
    }

    /**
     * Generate all Promotion detail sitemap
     *
     * @param string $mall_id
     * @param string $mall_slug
     * @return void
     */
    protected function generatePromotionDetailsSitemap($mall_id = null, $mall_slug = null)
    {
        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.promotion', []);
        if (! empty($mall_id)) {
            $_GET['mall_id'] = $mall_id;
        }
        if (! empty($this->country)) {
            $_GET['country'] = $this->country;
        }
        $_GET['take'] = 50;
        $_GET['skip'] = 0;

        $listController = Orbit\Controller\API\v1\Pub\Promotion\PromotionListNewAPIController::create('raw')
            ->setUser($this->user)
            ->setUseScroll();

        $scroller = $listController->getSearcher();

        $response = $listController->getSearchPromotion();

        if ($this->scrollResponseCheck($response)) {
            $scrollId = $response['_scroll_id'];

            while (true) {
                // Execute a Scroll request
                $scrollResponse = $scroller->scroll(["scroll_id" => $scrollId, "scroll" => "20s"]);

                // Check to see if we got any search hits from the scroll
                if (count($scrollResponse['hits']['hits']) > 0) {
                    // If yes, Do Work Here

                    $urlTemplate = $this->urlTemplate;
                    if (! empty($mall_id)) {
                        $mallUri = Config::get('orbit.sitemap.uri_properties.detail.mall', []);
                        $urlTemplate = sprintf($urlTemplate, $mallUri['uri']) . '/%s';
                    }
                    // build the url
                    $this->detailAppender($this->scrollRecords($scrollResponse), 'promotion', $urlTemplate, $detailUri, $mall_id, $mall_slug);

                    // Get new scrollId
                    $scrollId = $scrollResponse['_scroll_id'];
                } else {
                    break;
                }
            }
        }
    }

    /**
     * Generate all Event detail sitemap
     *
     * @param string $mall_id
     * @param string $mall_slug
     * @return void
     */
    protected function generateEventDetailsSitemap($mall_id = null, $mall_slug = null)
    {
        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.event', []);
        if (! empty($mall_id)) {
            $_GET['mall_id'] = $mall_id;
        }
        if (! empty($this->country)) {
            $_GET['country'] = $this->country;
        }

        $_GET['take'] = 50;
        $_GET['skip'] = 0;

        $listController = Orbit\Controller\API\v1\Pub\News\NewsListNewAPIController::create('raw')
            ->setUser($this->user)
            ->setUseScroll();

        $scroller = $listController->getSearcher();

        $response = $listController->getSearchNews();

        if ($this->scrollResponseCheck($response)) {
            $scrollId = $response['_scroll_id'];

            while (true) {
                // Execute a Scroll request
                $scrollResponse = $scroller->scroll(["scroll_id" => $scrollId, "scroll" => "20s"]);

                // Check to see if we got any search hits from the scroll
                if (count($scrollResponse['hits']['hits']) > 0) {
                    // If yes, Do Work Here

                    $urlTemplate = $this->urlTemplate;
                    if (! empty($mall_id)) {
                        $mallUri = Config::get('orbit.sitemap.uri_properties.detail.mall', []);
                        $urlTemplate = sprintf($urlTemplate, $mallUri['uri']) . '/%s';
                    }
                    // build the url
                    $this->detailAppender($this->scrollRecords($scrollResponse), 'event', $urlTemplate, $detailUri, $mall_id, $mall_slug);

                    // Get new scrollId
                    $scrollId = $scrollResponse['_scroll_id'];
                } else {
                    break;
                }
            }
        }
    }

    /**
     * Generate all Coupon detail sitemap
     *
     * @param string $mall_id
     * @param string $mall_slug
     * @return void
     */
    protected function generateCouponDetailsSitemap($mall_id = null, $mall_slug = null)
    {
        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.coupon', []);
        if (! empty($mall_id)) {
            $_GET['mall_id'] = $mall_id;
        }
        if (! empty($this->country)) {
            $_GET['country'] = $this->country;
        }

        $listController = Orbit\Controller\API\v1\Pub\Coupon\CouponListNewAPIController::create('raw')
            ->setUser($this->user)
            ->setUseScroll();

        $scroller = $listController->getSearcher();

        $response = $listController->getCouponList();

        if ($this->scrollResponseCheck($response)) {
            $scrollId = $response['_scroll_id'];

            while (true) {
                // Execute a Scroll request
                $scrollResponse = $scroller->scroll(["scroll_id" => $scrollId, "scroll" => "20s"]);

                // Check to see if we got any search hits from the scroll
                if (count($scrollResponse['hits']['hits']) > 0) {
                    // If yes, Do Work Here

                    $urlTemplate = $this->urlTemplate;
                    if (! empty($mall_id)) {
                        $mallUri = Config::get('orbit.sitemap.uri_properties.detail.mall', []);
                        $urlTemplate = sprintf($urlTemplate, $mallUri['uri']) . '/%s';
                    }
                    // build the url
                    $this->detailAppender($this->scrollRecords($scrollResponse), 'coupon', $urlTemplate, $detailUri, $mall_id, $mall_slug);

                    // Get new scrollId
                    $scrollId = $scrollResponse['_scroll_id'];
                } else {
                    break;
                }
            }
        }
    }

    /**
     * Generate all Mall detail sitemap
     *
     * @param string $mall_id
     * @param string $mall_slug
     * @return void
     */
    protected function generateMallDetailsSitemap()
    {
        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.mall', []);

        if (! empty($this->country)) {
            $_GET['country'] = $this->country;
        }
        $_GET['take'] = 50;
        $_GET['skip'] = 0;
        $mallSkip = 0;

        $listController = Orbit\Controller\API\v1\Pub\Mall\MallListNewAPIController::create('raw')
            ->setUseScroll()
            ->setUser($this->user);

        $scroller = $listController->getSearcher();

        $response = $listController->getMallList();

        if ($this->scrollResponseCheck($response)) {
            $scrollId = $response['_scroll_id'];

            while (true) {
                // Execute a Scroll request
                $scrollResponse = $scroller->scroll(["scroll_id" => $scrollId, "scroll" => "20s"]);

                // Check to see if we got any search hits from the scroll
                if (count($scrollResponse['hits']['hits']) > 0) {
                    // build the url
                    foreach ($this->scrollRecords($scrollResponse) as $record) {
                        $updatedAt = $this->getLastPromotionUpdatedAt(strtotime($record['updated_at']));
                        $this->urlStringPrinter(sprintf(sprintf($this->urlTemplate, $detailUri['uri']), $record['merchant_id'], Str::slug($record['name'])), date('c', $updatedAt), $detailUri['changefreq']);
                    }

                    // Get new scrollId
                    $scrollId = $scrollResponse['_scroll_id'];
                } else {
                    break;
                }
            }
        }
    }

    /**
     * Generate all Store detail sitemap
     *
     * @param string $mall_id
     * @param string $mall_slug
     * @return void
     */
    protected function generateStoreDetailsSitemap($mall_id = null, $mall_slug = null)
    {
        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.store', []);
        if (! empty($mall_id)) {
            $_GET['mall_id'] = $mall_id;
        }
        if (! empty($this->country)) {
            $_GET['country'] = $this->country;
        }

        $_GET['take'] = 50;
        $_GET['skip'] = 0;
        $listController = Orbit\Controller\API\v1\Pub\Store\StoreListNewAPIController::create('raw')
            ->setUseScroll()
            ->setUser($this->user);

        $scroller = $listController->getSearcher();

        $response = $listController->getStoreList();

        if ($this->scrollResponseCheck($response)) {
            $scrollId = $response['_scroll_id'];

            while (true) {
                // Execute a Scroll request
                $scrollResponse = $scroller->scroll(["scroll_id" => $scrollId, "scroll" => "20s"]);

                // Check to see if we got any search hits from the scroll
                if (count($scrollResponse['hits']['hits']) > 0) {
                    // If yes, Do Work Here

                    $urlTemplate = $this->urlTemplate;
                    if (! empty($mall_id)) {
                        $mallUri = Config::get('orbit.sitemap.uri_properties.detail.mall', []);
                        $urlTemplate = sprintf($urlTemplate, $mallUri['uri']) . '/%s';
                    }
                    // build the url
                    $this->detailAppender($this->scrollRecords($scrollResponse), 'store', $urlTemplate, $detailUri, $mall_id, $mall_slug);

                    // Get new scrollId
                    $scrollId = $scrollResponse['_scroll_id'];
                } else {
                    break;
                }
            }
        }
    }

    /**
     * Generate all Partner detail sitemap
     *
     * @return void
     */
    protected function generatePartnerDetailsSitemap()
    {
        unset($_GET);
        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.partner', []);
        $_GET['visible'] = 'yes';
        $_GET['take'] = 50;
        $_GET['skip'] = 0;
        if (! empty($this->country)) {
            $_GET['country'] = $this->country;
        }
        $response = Orbit\Controller\API\v1\Pub\Partner\PartnerListAPIController::create('raw')->setUser($this->user)->getSearchPartner();

        if ($this->responseCheck($response)) {
            $counter = $response->data->returned_records;
            $total_records = $response->data->total_records;

            if (! empty($total_records)) {
                $this->detailAppender($response->data->records, 'partner', $this->urlTemplate, $detailUri);

                while ($counter < $response->data->total_records) {
                    $_GET['skip'] = $_GET['skip'] + $_GET['take'];
                    $response = Orbit\Controller\API\v1\Pub\Partner\PartnerListAPIController::create('raw')->setUser($this->user)->getSearchPartner();

                    $this->detailAppender($response->data->records, 'partner', $this->urlTemplate, $detailUri);

                    $counter = $counter + $response->data->returned_records;
                    usleep($this->sleep);
                }
            }
        }
    }

    /**
     * Generate all Article detail sitemap
     *
     * @param string $mall_id
     * @param string $mall_slug
     * @return void
     */
    protected function generateArticleListSitemap()
    {
        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.article', []);

        if (! empty($this->country)) {
            $_GET['country'] = $this->country;
        }

        $_GET['take'] = 50;
        $_GET['skip'] = 0;

        $listController = Orbit\Controller\API\v1\Pub\Article\ArticleListAPIController::create('raw')
            ->setUser($this->user)
            ->setUseScroll();

        $scroller = $listController->getSearcher();

        $response = $listController->getSearchArticle();

        if ($this->scrollResponseCheck($response)) {
            $scrollId = $response['_scroll_id'];

            while (true) {
                // Execute a Scroll request
                $scrollResponse = $scroller->scroll(["scroll_id" => $scrollId, "scroll" => "20s"]);

                // Check to see if we got any search hits from the scroll
                if (count($scrollResponse['hits']['hits']) > 0) {
                    // If yes, Do Work Here

                    $urlTemplate = $this->urlTemplate;

                    // build the url
                    $this->detailAppender($this->scrollRecords($scrollResponse), 'article', $urlTemplate, $detailUri, null, null);

                    // Get new scrollId
                    $scrollId = $scrollResponse['_scroll_id'];
                } else {
                    break;
                }
            }
        }
    }

    /**
     * Generate sitemap for each pulsa operator page.
     * @return void
     */
    protected function generatePulsaOperatorDetailSitemap()
    {
        // Get operator list.
        $operatorList = TelcoOperator::select('slug')->where('status', 'active')->latest()->get();

        // Append to sitemap
        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.pulsa', []);
        $this->detailAppender($operatorList, 'pulsa', $this->urlTemplate, $detailUri);
    }

    /**
     * Generate sitemap for game voucher detail page.
     * @return void
     */
    protected function generateGameVoucherDetailSitemap()
    {
        // Get game list
        $games = Game::select('slug')->active()->latest()->get();

        // Generate detail sitemap
        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.game-voucher', []);
        $this->detailAppender($games, 'game-voucher', $this->urlTemplate, $detailUri);
    }

    /**
     * Single function to append urls
     *
     * @param $xml DOMDocument
     * @return void
     */
    protected function detailAppender($records, $type, $urlTemplate, $origDetailUri, $mall_id = null, $mall_slug = null)
    {
        foreach ($records as $record) {
            $detailUri = $origDetailUri;
            $updatedAt = (! is_object($record) && isset($record['updated_at']) && ! empty($record['updated_at'])) ? strtotime($record['updated_at']) : time();

            switch ($type) {
                case 'promotion':
                case 'event':
                    $id = $record['news_id'];
                    $name = isset($record['news_name']) ? $record['news_name'] : $record['name'];

                    if ($type === 'event' && isset($record['is_having_reward']) && $record['is_having_reward'] === 'Y') {
                        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.promotional-event', []);
                    }
                    break;

                case 'coupon':
                    $id = isset($record['coupon_id']) ? $record['coupon_id'] : $record['promotion_id'];
                    $name = isset($record['coupon_name']) ? $record['coupon_name'] : $record['name'];
                    break;

                case 'article':
                    $id = $record['article_id'];
                    $slug = $record['slug'];
                    break;

                case 'store':
                    $id = $record['merchant_id'];
                    $name = $record['name'];
                    break;

                case 'partner':
                    // change to array if partner list already on elasticsearch
                    $id = $record->partner_id;
                    $name = $record->partner_name;
                    $updatedAt = strtotime($record->updated_at);
                    break;

                case 'pulsa':
                    $slug = $record->slug;
                    break;

                case 'game-voucher':
                    $slug = $record->slug;
                    break;

                default:
                    # code...
                    break;
            }

            if (! empty($mall_id)) {
                $this->urlStringPrinter(sprintf(sprintf(sprintf($urlTemplate, $mall_id, $mall_slug, $detailUri['uri']), $id, Str::slug($name))), date('c', $updatedAt), $detailUri['changefreq']);
            } else {
                if (in_array($type, ['article', 'pulsa', 'game-voucher'])) {
                    $this->urlStringPrinter(sprintf(sprintf($urlTemplate, $detailUri['uri']), $slug), date('c', $updatedAt), $detailUri['changefreq']);
                } else {
                    $this->urlStringPrinter(sprintf(sprintf($urlTemplate, $detailUri['uri']), $id, Str::slug($name)), date('c', $updatedAt), $detailUri['changefreq']);
                }

            }
        }
    }

    /**
     * Generate miscelaneous sitemap
     *
     * @param $xml DOMDocument
     * @return $xml DOMDocument
     */
    protected function generateMiscListSitemap()
    {
        $listUris = Config::get('orbit.sitemap.uri_properties.misc', []);
        foreach ($listUris as $key => $uri) {
            if ($key === 'home') {
                $this->urlStringPrinter(sprintf($this->urlTemplate, $uri['uri']), date('c', $this->getLastPromotionUpdatedAt()), $uri['changefreq']);
            } else {
                $this->urlStringPrinter(sprintf($this->urlTemplate, $uri['uri']), date('c'), $uri['changefreq']);
            }
        }
    }

    /**
     * Generate url property string
     *
     * @param $url string
     * @param $lastmod string
     * @param $changefreq string
     * @param $priority string
     * @return void
     */
    protected function urlStringPrinter($url, $lastmod, $changefreq, $priority = null)
    {
        if (! empty($this->extraParam)) {
            $queryString = http_build_query($this->extraParam);
            $url = $url . '?' . $queryString;
        }
        $priority = is_null($priority) ? $this->priority : $priority;
        $urlProp = sprintf("<url>\n<loc>%s</loc>\n<lastmod>%s</lastmod>\n<changefreq>%s</changefreq>\n<priority>%s</priority>\n</url>\n", $url, $lastmod, $changefreq, $priority);

        print $urlProp;
    }

    /**
     * Check for response code
     *
     * @return boolean
     */
    protected function responseCheck($response)
    {
        $ok = (is_object($response) && $response->code === 0 && ! is_null($response->data)) ? TRUE : FALSE;
        return $ok;
    }

    /**
     * Check response for scroll id
     *
     * @return boolean
     */
    protected function scrollResponseCheck($response)
    {
        $ok = (isset($response['_scroll_id']) && ! empty($response['_scroll_id'])) ? TRUE : FALSE;
        return $ok;
    }

    /**
     * Get records from scroller
     *
     * @return array
     */
    protected function scrollRecords($response)
    {
        $hits = $response['hits'];

        $records = [];
        foreach ($hits['hits'] as $hit) {
            $data = [];
            foreach ($hit['_source'] as $key => $value) {
                $data[$key] = $value;
            }
            $records[] = $data;
        }

        return $records;
    }

    /**
     * Get latest updated promotion updated_at in timestamp for lastmod of some urls
     *
     * @return $updatedAt double (timestamp)
     */
    protected function getLastPromotionUpdatedAt($mall_id = null)
    {
        $updatedAt = time();
        $_GET['sortby'] = 'updated_date';
        $_GET['sortmode'] = 'desc';

        $_GET['list_type'] = 'preferred';
        $_GET['take'] = 1;
        $_GET['skip'] = 0;
        if (! empty($this->country)) {
            $_GET['country'] = $this->country;
        }
        if (! empty($mall_id)) {
            $_GET['mall_id'] = $mall_id;
        }

        $response = Orbit\Controller\API\v1\Pub\Promotion\PromotionListAPIController::create('raw')->setUser($this->user)->setWithOutScore()->getSearchPromotion();

        if ($this->responseCheck($response)) {
            if (! empty($response->data->total_records)) {
                $updatedAt = (! is_object($response->data->records[0]) && isset($response->data->records[0]['updated_at']) && ! empty($response->data->records[0]['updated_at'])) ? strtotime($response->data->records[0]['updated_at']) : time();
            }
        }

        return $updatedAt;
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array();
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
                array('type', NULL, InputOption::VALUE_OPTIONAL, 'URL type.', 'all'),
                array('sleep', NULL, InputOption::VALUE_OPTIONAL, 'Sleep value.', 500000),
                array('format-output', 0, InputOption::VALUE_NONE, 'Format sitemap XML file output.'),
                array('country', 0, InputOption::VALUE_OPTIONAL, 'Filter sitemap by country ID.'),
            );
    }
}
