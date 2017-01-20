<?php
/**
 * Command to generate sitemap file
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use \Config;
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
     * Url template: http://www.gotomalls.com/#!/%s
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

                case 'misc':
                    $this->generateMiscListSitemap();
                    break;

                case 'all':
                default:
                    $this->generateMiscListSitemap();
                    $this->generateAllListSitemap();
                    $this->generateAllDetailSitemap();

                    break;
            }
        } catch (\Exception $e) {
            return print_r([$e->getMessage(), $e->getLine(), $e->getFile()]);
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
            $response = null;
            $_GET['sortby'] = 'updated_date';
            $_GET['sortmode'] = 'desc';
            $_GET['list_type'] = 'preferred';
            $_GET['from_homepage'] = 'y';
            $_GET['take'] = 1;
            $_GET['skip'] = 0;
            if (! empty($mall_id)) {
                $_GET['mall_id'] = $mall_id;
            }

            switch ($key) {
                case 'promotion':
                    $response = Orbit\Controller\API\v1\Pub\Promotion\PromotionListAPIController::create('raw')->setUser($this->user)->setWithOutScore()->getSearchPromotion();
                    break;

                case 'event':
                    $response = Orbit\Controller\API\v1\Pub\News\NewsListAPIController::create('raw')->setUser($this->user)->setWithOutScore()->getSearchNews();
                    break;

                case 'coupon':
                    $response = Orbit\Controller\API\v1\Pub\Coupon\CouponListAPIController::create('raw')->setUser($this->user)->setWithOutScore()->getCouponList();
                    break;

                case 'store':
                    // todo: add setWithOutScore() if store list query already pointing to ES
                    $response = Orbit\Controller\API\v1\Pub\StoreAPIController::create('raw')->setUser($this->user)->getStoreList();
                    break;

                case 'mall':
                    if (! empty($mall_id)) {
                        $response = null;
                    } else {
                        $response = Orbit\Controller\API\v1\Pub\Mall\MallListAPIController::create('raw')->setUser($this->user)->setWithOutScore()->getMallList();
                    }
                    break;

                default:
                    # code...
                    break;
            }

            if ($this->responseCheck($response)) {
                if (! empty($response->data->total_records)) {
                    $updatedAt = (isset($response->data->records[0]) && ! is_object($response->data->records[0]) && isset($response->data->records[0]['updated_at']) && ! empty($response->data->records[0]['updated_at'])) ? strtotime($response->data->records[0]['updated_at']) : time();
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
        $_GET['from_homepage'] = 'y';
        $_GET['take'] = 50;
        $_GET['skip'] = 0;
        $response = Orbit\Controller\API\v1\Pub\Promotion\PromotionListAPIController::create('raw')->setUser($this->user)->getSearchPromotion();

        if ($this->responseCheck($response)) {
            $counter = $response->data->returned_records;
            $total_records = $response->data->total_records;

            if (! empty($total_records)) {
                $urlTemplate = $this->urlTemplate;
                if (! empty($mall_id)) {
                    $mallUri = Config::get('orbit.sitemap.uri_properties.detail.mall', []);
                    $urlTemplate = sprintf($urlTemplate, $mallUri['uri']) . '/%s';
                }

                $this->detailAppender($response->data->records, 'promotion', $urlTemplate, $detailUri, $mall_id, $mall_slug);

                while ($counter < $response->data->total_records) {
                    $_GET['skip'] = $_GET['skip'] + $_GET['take'];
                    $response = Orbit\Controller\API\v1\Pub\Promotion\PromotionListAPIController::create('raw')->setUser($this->user)->getSearchPromotion();

                    $this->detailAppender($response->data->records, 'promotion', $urlTemplate, $detailUri, $mall_id, $mall_slug);

                    $counter = $counter + $response->data->returned_records;
                    usleep($this->sleep);
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
        $_GET['from_homepage'] = 'y';
        $_GET['take'] = 50;
        $_GET['skip'] = 0;
        $response = Orbit\Controller\API\v1\Pub\News\NewsListAPIController::create('raw')->setUser($this->user)->getSearchNews();

        if ($this->responseCheck($response)) {
            $counter = $response->data->returned_records;
            $total_records = $response->data->total_records;

            if (! empty($total_records)) {
                $urlTemplate = $this->urlTemplate;
                if (! empty($mall_id)) {
                    $mallUri = Config::get('orbit.sitemap.uri_properties.detail.mall', []);
                    $urlTemplate = sprintf($urlTemplate, $mallUri['uri']) . '/%s';
                }

                $this->detailAppender($response->data->records, 'event', $urlTemplate, $detailUri, $mall_id, $mall_slug);

                while ($counter < $response->data->total_records) {
                    $_GET['skip'] = $_GET['skip'] + $_GET['take'];
                    $response = Orbit\Controller\API\v1\Pub\News\NewsListAPIController::create('raw')->setUser($this->user)->getSearchNews();

                    $this->detailAppender($response->data->records, 'event', $urlTemplate, $detailUri, $mall_id, $mall_slug);

                    $counter = $counter + $response->data->returned_records;
                    usleep($this->sleep);
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
        $_GET['from_homepage'] = 'y';
        $_GET['take'] = 50;
        $_GET['skip'] = 0;
        $response = Orbit\Controller\API\v1\Pub\Coupon\CouponListAPIController::create('raw')->setUser($this->user)->getCouponList();

        if ($this->responseCheck($response)) {
            $counter = $response->data->returned_records;
            $total_records = $response->data->total_records;

            if (! empty($total_records)) {
                $urlTemplate = $this->urlTemplate;
                if (! empty($mall_id)) {
                    $mallUri = Config::get('orbit.sitemap.uri_properties.detail.mall', []);
                    $urlTemplate = sprintf($urlTemplate, $mallUri['uri']) . '/%s';
                }

                $this->detailAppender($response->data->records, 'coupon', $urlTemplate, $detailUri, $mall_id, $mall_slug);

                while ($counter < $response->data->total_records) {
                    $_GET['skip'] = $_GET['skip'] + $_GET['take'];
                    $response = Orbit\Controller\API\v1\Pub\Coupon\CouponListAPIController::create('raw')->setUser($this->user)->getCouponList();

                    $this->detailAppender($response->data->records, 'coupon', $urlTemplate, $detailUri, $mall_id, $mall_slug);

                    $counter = $counter + $response->data->returned_records;
                    usleep($this->sleep);
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
        $_GET['from_homepage'] = 'y';
        $_GET['take'] = 50;
        $_GET['skip'] = 0;
        $mallSkip = 0;
        $response = Orbit\Controller\API\v1\Pub\Mall\MallListAPIController::create('raw')->setUser($this->user)->getMallList();

        if ($this->responseCheck($response)) {
            $counter = $response->data->returned_records;
            $total_records = $response->data->total_records;
            $listUris = Config::get('orbit.sitemap.uri_properties.list', []);

            if (! empty($total_records)) {
                $listUrlTemplate = sprintf($this->urlTemplate, $detailUri['uri']) . '/%s';

                foreach ($response->data->records as $record) {
                    $updatedAt = $this->getLastPromotionUpdatedAt($record['id']);
                    $this->urlStringPrinter(sprintf(sprintf($this->urlTemplate, $detailUri['uri']), $record['id'], Str::slug($record['name'])), date('c', $updatedAt), $detailUri['changefreq']);

                    $this->generateAllListSitemap($record['id'], Str::slug($record['name']));

                    // mall promotions
                    $this->generatePromotionDetailsSitemap($record['id'], Str::slug($record['name']));
                    // mall events
                    $this->generateEventDetailsSitemap($record['id'], Str::slug($record['name']));
                    // mall coupons
                    $this->generateCouponDetailsSitemap($record['id'], Str::slug($record['name']));
                    // mall stores
                    $this->generateStoreDetailsSitemap($record['id'], Str::slug($record['name']));
                }

                while ($counter < $response->data->total_records) {
                    unset($_GET);
                    $_GET['from_homepage'] = 'y';
                    $_GET['take'] = 50;
                    $mallSkip = $mallSkip + $_GET['take'];
                    $_GET['skip'] = $mallSkip;
                    $response = Orbit\Controller\API\v1\Pub\Mall\MallListAPIController::create('raw')->setUser($this->user)->getMallList();

                    if ($this->responseCheck($response)) {
                        foreach ($response->data->records as $record) {
                            $updatedAt = $this->getLastPromotionUpdatedAt($record['id']);
                            $this->urlStringPrinter(sprintf(sprintf($this->urlTemplate, $detailUri['uri']), $record['id'], Str::slug($record['name'])), date('c', $updatedAt), $detailUri['changefreq']);

                            $this->generateAllListSitemap($record['id'], Str::slug($record['name']));

                            // mall promotions
                            $this->generatePromotionDetailsSitemap($record['id'], Str::slug($record['name']));
                            // mall events
                            $this->generateEventDetailsSitemap($record['id'], Str::slug($record['name']));
                            // mall coupons
                            $this->generateCouponDetailsSitemap($record['id'], Str::slug($record['name']));
                            // mall stores
                            $this->generateStoreDetailsSitemap($record['id'], Str::slug($record['name']));
                        }
                    }

                    $counter = $counter + $response->data->returned_records;
                    usleep($this->sleep);
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
        $_GET['from_homepage'] = 'y';
        $_GET['take'] = 50;
        $_GET['skip'] = 0;
        $response = Orbit\Controller\API\v1\Pub\StoreAPIController::create('raw')->setUser($this->user)->getStoreList();

        if ($this->responseCheck($response)) {
            $counter = $response->data->returned_records;
            $total_records = $response->data->total_records;

            if (! empty($total_records)) {
                $urlTemplate = $this->urlTemplate;
                if (! empty($mall_id)) {
                    $mallUri = Config::get('orbit.sitemap.uri_properties.detail.mall', []);
                    $urlTemplate = sprintf($urlTemplate, $mallUri['uri']) . '/%s';
                }

                $this->detailAppender($response->data->records, 'store', $urlTemplate, $detailUri, $mall_id, $mall_slug);

                while ($counter < $response->data->total_records) {
                    $_GET['skip'] = $_GET['skip'] + $_GET['take'];
                    $response = Orbit\Controller\API\v1\Pub\StoreAPIController::create('raw')->setUser($this->user)->getStoreList();

                    $this->detailAppender($response->data->records, 'store', $urlTemplate, $detailUri, $mall_id, $mall_slug);

                    $counter = $counter + $response->data->returned_records;
                    usleep($this->sleep);
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
     * Single function to append urls
     *
     * @param $xml DOMDocument
     * @return void
     */
    protected function detailAppender($records, $type, $urlTemplate, $detailUri, $mall_id = null, $mall_slug = null)
    {
        foreach ($records as $record) {
            $updatedAt = (! is_object($record) && isset($record['updated_at']) && ! empty($record['updated_at'])) ? strtotime($record['updated_at']) : time();

            switch ($type) {
                case 'promotion':
                case 'event':
                    $id = $record['news_id'];
                    $name = $record['news_name'];
                    break;

                case 'coupon':
                    $id = $record['coupon_id'];
                    $name = $record['coupon_name'];
                    break;

                case 'store':
                    if (is_object($record)) {
                        // remove this and use array instead if store already in elasticsearch
                        $id = $record->merchant_id;
                        $name = $record->name;
                        $updatedAt = strtotime($record->updated_at);
                    } elseif(is_array($record)) {
                        $id = $record['merchant_id'];
                        $name = $record['name'];
                    }
                    break;

                case 'partner':
                    // change to array if partner list already on elasticsearch
                    $id = $record->partner_id;
                    $name = $record->partner_name;
                    $updatedAt = strtotime($record->updated_at);
                    break;

                default:
                    # code...
                    break;
            }

            if (! empty($mall_id)) {
                $this->urlStringPrinter(sprintf(sprintf(sprintf($urlTemplate, $mall_id, $mall_slug, $detailUri['uri']), $id, Str::slug($name))), date('c', $updatedAt), $detailUri['changefreq']);
            } else {
                $this->urlStringPrinter(sprintf(sprintf($urlTemplate, $detailUri['uri']), $id, Str::slug($name)), date('c', $updatedAt), $detailUri['changefreq']);
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
        $ok = (is_object($response) && $response->code === 0) ? TRUE : FALSE;
        return $ok;
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
        $_GET['from_homepage'] = 'y';
        $_GET['list_type'] = 'preferred';
        $_GET['take'] = 1;
        $_GET['skip'] = 0;
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
            );
    }
}
