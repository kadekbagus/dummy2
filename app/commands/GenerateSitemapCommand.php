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
        try {
            $sitemapConfig = Config::get('orbit.sitemap', null);
            if (empty($sitemapConfig)) {
                throw new Exception("Cannot find sitemap config.", 1);
            }

            parent::__construct();
            $this->appUrl = rtrim(Config::get('app.url'), '/');
            $this->hashBang = Config::get('orbit.sitemap.hashbang', FALSE) ? '/#!/' : '/';
            $this->urlTemplate = $this->appUrl . $this->hashBang . '%s';

            $this->user = User::with('apikey')
                ->leftJoin('roles', 'users.user_role_id', '=', 'roles.role_id')
                ->where('role_name', 'Super Admin')
                ->first();

            if (! is_object($this->user)) {
                throw new Exception("Cannot find super admin user.", 1);
            }

            $this->sitemapType = 'all';
        } catch (\Exception $e) {
            print($e->getMessage());
            die();
        }
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        try {
            $this->formatOutput = $this->option('format-output');
            $this->sitemapType = $this->option('type');
            $this->sleep = $this->option('sleep');

            $xml = new DOMDocument('1.0', 'UTF-8');
            $xml->formatOutput = $this->formatOutput;
            $xmlSitemapUrlSet = $xml->createElement('urlset');
            $xmlSitemapUrlSetAttr = $xml->createAttribute('xmlns');
            $xmlSitemapUrlSetAttr->value = 'http://www.sitemaps.org/schemas/sitemap/0.9';
            $xmlSitemapUrlSet->appendChild($xmlSitemapUrlSetAttr);
            $xml->appendChild($xmlSitemapUrlSet);

            switch ($this->sitemapType) {
                case 'all-list':
                    $returnedXML = $this->generateAllListSitemap($xml);
                    break;

                case 'all-detail':
                    $returnedXML = $this->generateAllDetailSitemap($xml);
                    break;

                case 'promotion-detail':
                    $returnedXML = $this->generatePromotionDetailsSitemap($xml);
                    break;

                case 'coupon-detail':
                    $returnedXML = $this->generateCouponDetailsSitemap($xml);
                    break;

                case 'store-detail':
                    $returnedXML = $this->generateStoreDetailsSitemap($xml);
                    break;

                case 'event-detail':
                    $returnedXML = $this->generateEventDetailsSitemap($xml);
                    break;

                case 'mall-detail':
                    $returnedXML = $this->generateMallDetailsSitemap($xml);
                    break;

                case 'partner-detail':
                    $returnedXML = $this->generatePartnerDetailsSitemap($xml);
                    break;

                case 'misc':
                    $returnedXML = $this->generateMiscListSitemap($xml);
                    break;

                case 'all':
                default:
                    $returnedXML = $this->generateMiscListSitemap($xml);
                    $returnedXML = $this->generateAllListSitemap($returnedXML);
                    $returnedXML = $this->generateAllDetailSitemap($returnedXML);
                    break;
            }
        } catch (\Exception $e) {
            return print($e->getMessage());
        }

        return print($returnedXML->saveXML());
    }

    /**
     * Generate all list sitemap
     *
     * @param $xml DOMDocument
     * @return $xml DOMDocument
     */
    protected function generateAllListSitemap($xml)
    {
        $root = $xml->firstChild;
        $listUris = Config::get('orbit.sitemap.uri_properties.list', []);
        foreach ($listUris as $uri) {
            $xmlSitemapUrl = $xml->createElement('url');
            $xmlSitemapLoc = $xml->createElement('loc', sprintf($this->urlTemplate, $uri['uri']));
            $xmlSitemapLastMod = $xml->createElement('lastmod', date('c'));
            $xmlSitemapChangeFreq = $xml->createElement('changefreq', $uri['changefreq']);
            $xmlSitemapPriority = $xml->createElement('priority', $this->priority);
            $xmlSitemapUrl->appendChild($xmlSitemapLoc);
            $xmlSitemapUrl->appendChild($xmlSitemapLastMod);
            $xmlSitemapUrl->appendChild($xmlSitemapChangeFreq);
            $xmlSitemapUrl->appendChild($xmlSitemapPriority);
            $root->appendChild($xmlSitemapUrl);
        }

        $xml->appendChild($root);

        return $xml;
    }

    /**
     * Generate all list sitemap
     *
     * @param $xml DOMDocument
     * @return $xml DOMDocument
     */
    protected function generateAllDetailSitemap($xml)
    {
        $listUris = Config::get('orbit.sitemap.uri_properties.detail', []);
        foreach ($listUris as $key => $uri) {
            switch ($key) {
                case 'promotion':
                    $xml = $this->generatePromotionDetailsSitemap($xml);
                    break;

                case 'event':
                    $xml = $this->generateEventDetailsSitemap($xml);
                    break;

                case 'coupon':
                    $xml = $this->generateCouponDetailsSitemap($xml);
                    break;

                case 'mall':
                    $xml = $this->generateMallDetailsSitemap($xml);
                    break;

                case 'store':
                    $xml = $this->generateStoreDetailsSitemap($xml);
                    break;

                case 'partner':
                    $xml = $this->generatePartnerDetailsSitemap($xml);
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
     * @param $xml DOMDocument
     * @return $xml DOMDocument
     */
    protected function generatePromotionDetailsSitemap($xml, $mall_id = null, $mall_slug = null)
    {
        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.promotion', []);
        $root = $xml->firstChild;
        if (! empty($mall_id)) {
            $_GET['mall_id'] = $mall_id;
        }
        $_GET['from_homepage'] = 'y';
        $_GET['take'] = 50;
        $_GET['skip'] = 0;
        $response = Orbit\Controller\API\v1\Pub\Promotion\PromotionListAPIController::create('raw')->setUser($this->user)->getSearchPromotion();
        $counter = $response->data->returned_records;
        $total_records = $response->data->total_records;

        if (! empty($total_records)) {
            $urlTemplate = $this->urlTemplate;
            if (! empty($mall_id)) {
                $mallUri = Config::get('orbit.sitemap.uri_properties.detail.mall', []);
                $urlTemplate = sprintf($urlTemplate, $mallUri['uri']) . '/%s';
            }

            $root = $this->detailAppender($xml, $root, $response->data->records, 'promotion', $urlTemplate, $detailUri, $mall_id, $mall_slug);

            while ($counter < $response->data->total_records) {
                $_GET['skip'] = $_GET['skip'] + $_GET['take'];
                $response = Orbit\Controller\API\v1\Pub\Promotion\PromotionListAPIController::create('raw')->setUser($this->user)->getSearchPromotion();

                $root = $this->detailAppender($xml, $root, $response->data->records, 'promotion', $urlTemplate, $detailUri, $mall_id, $mall_slug);

                $counter = $counter + $response->data->returned_records;
                usleep($this->sleep);
            }

            $xml->appendChild($root);
        }

        return $xml;
    }

    /**
     * Generate all Event detail sitemap
     *
     * @param $xml DOMDocument
     * @return $xml DOMDocument
     */
    protected function generateEventDetailsSitemap($xml, $mall_id = null, $mall_slug = null)
    {
        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.event', []);
        $root = $xml->firstChild;
        if (! empty($mall_id)) {
            $_GET['mall_id'] = $mall_id;
        }
        $_GET['from_homepage'] = 'y';
        $_GET['take'] = 50;
        $_GET['skip'] = 0;
        $response = Orbit\Controller\API\v1\Pub\News\NewsListAPIController::create('raw')->setUser($this->user)->getSearchNews();
        $counter = $response->data->returned_records;
        $total_records = $response->data->total_records;

        if (! empty($total_records)) {
            $urlTemplate = $this->urlTemplate;
            if (! empty($mall_id)) {
                $mallUri = Config::get('orbit.sitemap.uri_properties.detail.mall', []);
                $urlTemplate = sprintf($urlTemplate, $mallUri['uri']) . '/%s';
            }

            $root = $this->detailAppender($xml, $root, $response->data->records, 'event', $urlTemplate, $detailUri, $mall_id, $mall_slug);

            while ($counter < $response->data->total_records) {
                $_GET['skip'] = $_GET['skip'] + $_GET['take'];
                $response = Orbit\Controller\API\v1\Pub\News\NewsListAPIController::create('raw')->setUser($this->user)->getSearchNews();

                $root = $this->detailAppender($xml, $root, $response->data->records, 'event', $urlTemplate, $detailUri, $mall_id, $mall_slug);

                $counter = $counter + $response->data->returned_records;
                usleep($this->sleep);
            }

            $xml->appendChild($root);
        }

        return $xml;
    }

    /**
     * Generate all Coupon detail sitemap
     *
     * @param $xml DOMDocument
     * @return $xml DOMDocument
     */
    protected function generateCouponDetailsSitemap($xml, $mall_id = null, $mall_slug = null)
    {
        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.coupon', []);
        $root = $xml->firstChild;
        if (! empty($mall_id)) {
            $_GET['mall_id'] = $mall_id;
        }
        $_GET['from_homepage'] = 'y';
        $_GET['take'] = 50;
        $_GET['skip'] = 0;
        $response = Orbit\Controller\API\v1\Pub\Coupon\CouponListAPIController::create('raw')->setUser($this->user)->getCouponList();
        $counter = $response->data->returned_records;
        $total_records = $response->data->total_records;

        if (! empty($total_records)) {
            $urlTemplate = $this->urlTemplate;
            if (! empty($mall_id)) {
                $mallUri = Config::get('orbit.sitemap.uri_properties.detail.mall', []);
                $urlTemplate = sprintf($urlTemplate, $mallUri['uri']) . '/%s';
            }

            $root = $this->detailAppender($xml, $root, $response->data->records, 'coupon', $urlTemplate, $detailUri, $mall_id, $mall_slug);

            while ($counter < $response->data->total_records) {
                $_GET['skip'] = $_GET['skip'] + $_GET['take'];
                $response = Orbit\Controller\API\v1\Pub\Coupon\CouponListAPIController::create('raw')->setUser($this->user)->getCouponList();

                $root = $this->detailAppender($xml, $root, $response->data->records, 'coupon', $urlTemplate, $detailUri, $mall_id, $mall_slug);

                $counter = $counter + $response->data->returned_records;
                usleep($this->sleep);
            }

            $xml->appendChild($root);
        }

        return $xml;
    }

    /**
     * Generate all Mall detail sitemap
     *
     * @param $xml DOMDocument
     * @return $xml DOMDocument
     */
    protected function generateMallDetailsSitemap($xml)
    {
        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.mall', []);
        $root = $xml->firstChild;
        $_GET['from_homepage'] = 'y';
        $_GET['take'] = 50;
        $_GET['skip'] = 0;
        $response = Orbit\Controller\API\v1\Pub\Mall\MallListAPIController::create('raw')->setUser($this->user)->getMallList();
        $counter = $response->data->returned_records;
        $total_records = $response->data->total_records;

        if (! empty($total_records)) {
            $listUrlTemplate = sprintf($this->urlTemplate, $detailUri['uri']) . '/%s';

            foreach ($response->data->records as $record) {
                $xmlSitemapUrl = $xml->createElement('url');
                $xmlSitemapLoc = $xml->createElement('loc', sprintf(sprintf($this->urlTemplate, $detailUri['uri']), $record['id'], Str::slug($record['name'])));
                // todo: use updated_at instead after updating campaign elastic search index
                // $xmlSitemapLastMod = $xml->createElement('lastmod', date('c', strtotime($record['updated_at'])));
                $xmlSitemapLastMod = $xml->createElement('lastmod', date('c', time()));
                $xmlSitemapChangeFreq = $xml->createElement('changefreq', $detailUri['changefreq']);
                $xmlSitemapPriority = $xml->createElement('priority', $this->priority);
                $xmlSitemapUrl->appendChild($xmlSitemapLoc);
                $xmlSitemapUrl->appendChild($xmlSitemapLastMod);
                $xmlSitemapUrl->appendChild($xmlSitemapChangeFreq);
                $xmlSitemapUrl->appendChild($xmlSitemapPriority);
                $root->appendChild($xmlSitemapUrl);

                // lists inside mall
                $listUris = Config::get('orbit.sitemap.uri_properties.list', []);
                foreach ($listUris as $key => $uri) {
                    if ($key !== 'mall') {
                        $xmlSitemapUrl = $xml->createElement('url');
                        $xmlSitemapLoc = $xml->createElement('loc', sprintf($listUrlTemplate, $record['id'], Str::slug($record['name']), $uri['uri']));
                        $xmlSitemapLastMod = $xml->createElement('lastmod', date('c'));
                        $xmlSitemapChangeFreq = $xml->createElement('changefreq', $uri['changefreq']);
                        $xmlSitemapPriority = $xml->createElement('priority', $this->priority);
                        $xmlSitemapUrl->appendChild($xmlSitemapLoc);
                        $xmlSitemapUrl->appendChild($xmlSitemapLastMod);
                        $xmlSitemapUrl->appendChild($xmlSitemapChangeFreq);
                        $xmlSitemapUrl->appendChild($xmlSitemapPriority);
                        $root->appendChild($xmlSitemapUrl);
                    }
                }

                $xml->appendChild($root);
                // mall promotions
                $xml = $this->generatePromotionDetailsSitemap($xml, $record['id'], Str::slug($record['name']));
                // mall events
                $xml = $this->generateEventDetailsSitemap($xml, $record['id'], Str::slug($record['name']));
                // mall coupons
                $xml = $this->generateCouponDetailsSitemap($xml, $record['id'], Str::slug($record['name']));
                // mall stores
                $xml = $this->generateStoreDetailsSitemap($xml, $record['id'], Str::slug($record['name']));
            }

            while ($counter < $response->data->total_records) {
                $_GET['skip'] = $_GET['skip'] + $_GET['take'];
                $response = Orbit\Controller\API\v1\Pub\Mall\MallListAPIController::create('raw')->setUser($this->user)->getMallList();

                foreach ($response->data->records as $record) {
                    $xmlSitemapUrl = $xml->createElement('url');
                    $xmlSitemapLoc = $xml->createElement('loc', sprintf(sprintf($this->urlTemplate, $detailUri['uri']), $record['id'], Str::slug($record['name'])));
                    // todo: use updated_at instead after updating campaign elastic search index
                    // $xmlSitemapLastMod = $xml->createElement('lastmod', date('c', strtotime($record['updated_at'])));
                    $xmlSitemapLastMod = $xml->createElement('lastmod', date('c', time()));
                    $xmlSitemapChangeFreq = $xml->createElement('changefreq', $detailUri['changefreq']);
                    $xmlSitemapPriority = $xml->createElement('priority', $this->priority);
                    $xmlSitemapUrl->appendChild($xmlSitemapLoc);
                    $xmlSitemapUrl->appendChild($xmlSitemapLastMod);
                    $xmlSitemapUrl->appendChild($xmlSitemapChangeFreq);
                    $xmlSitemapUrl->appendChild($xmlSitemapPriority);
                    $root->appendChild($xmlSitemapUrl);

                    // lists inside mall
                    $listUris = Config::get('orbit.sitemap.uri_properties.list', []);
                    foreach ($listUris as $key => $uri) {
                        if ($key !== 'mall') {
                            $xmlSitemapUrl = $xml->createElement('url');
                            $xmlSitemapLoc = $xml->createElement('loc', sprintf($listUrlTemplate, $record['id'], Str::slug($record['name']), $uri['uri']));
                            $xmlSitemapLastMod = $xml->createElement('lastmod', date('c'));
                            $xmlSitemapChangeFreq = $xml->createElement('changefreq', $uri['changefreq']);
                            $xmlSitemapPriority = $xml->createElement('priority', $this->priority);
                            $xmlSitemapUrl->appendChild($xmlSitemapLoc);
                            $xmlSitemapUrl->appendChild($xmlSitemapLastMod);
                            $xmlSitemapUrl->appendChild($xmlSitemapChangeFreq);
                            $xmlSitemapUrl->appendChild($xmlSitemapPriority);
                            $root->appendChild($xmlSitemapUrl);
                        }
                    }

                    $xml->appendChild($root);
                    // mall promotions
                    $xml = $this->generatePromotionDetailsSitemap($xml, $record['id'], Str::slug($record['name']));
                    // mall events
                    $xml = $this->generateEventDetailsSitemap($xml, $record['id'], Str::slug($record['name']));
                    // mall coupons
                    $xml = $this->generateCouponDetailsSitemap($xml, $record['id'], Str::slug($record['name']));
                    // mall stores
                    $xml = $this->generateStoreDetailsSitemap($xml, $record['id'], Str::slug($record['name']));
                }

                $counter = $counter + $response->data->returned_records;
                usleep($this->sleep);
            }
        }

        return $xml;
    }

    /**
     * Generate all Store detail sitemap
     *
     * @param $xml DOMDocument
     * @return $xml DOMDocument
     */
    protected function generateStoreDetailsSitemap($xml, $mall_id = null, $mall_slug = null)
    {
        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.store', []);
        $root = $xml->firstChild;
        if (! empty($mall_id)) {
            $_GET['mall_id'] = $mall_id;
        }
        $_GET['from_homepage'] = 'y';
        $_GET['take'] = 50;
        $_GET['skip'] = 0;
        $response = Orbit\Controller\API\v1\Pub\StoreAPIController::create('raw')->setUser($this->user)->getStoreList();
        $counter = $response->data->returned_records;
        $total_records = $response->data->total_records;

        if (! empty($total_records)) {
            $urlTemplate = $this->urlTemplate;
            if (! empty($mall_id)) {
                $mallUri = Config::get('orbit.sitemap.uri_properties.detail.mall', []);
                $urlTemplate = sprintf($urlTemplate, $mallUri['uri']) . '/%s';
            }

            $root = $this->detailAppender($xml, $root, $response->data->records, 'store', $urlTemplate, $detailUri, $mall_id, $mall_slug);

            while ($counter < $response->data->total_records) {
                $_GET['skip'] = $_GET['skip'] + $_GET['take'];
                $response = Orbit\Controller\API\v1\Pub\StoreAPIController::create('raw')->setUser($this->user)->getStoreList();

                $root = $this->detailAppender($xml, $root, $response->data->records, 'store', $urlTemplate, $detailUri, $mall_id, $mall_slug);

                $counter = $counter + $response->data->returned_records;
                usleep($this->sleep);
            }

            $xml->appendChild($root);
        }

        return $xml;
    }

    /**
     * Generate all Partner detail sitemap
     *
     * @param $xml DOMDocument
     * @return $xml DOMDocument
     */
    protected function generatePartnerDetailsSitemap($xml)
    {
        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.partner', []);
        $root = $xml->firstChild;
        $_GET['visible'] = 'yes';
        $_GET['take'] = 50;
        $_GET['skip'] = 0;
        $response = Orbit\Controller\API\v1\Pub\Partner\PartnerListAPIController::create('raw')->setUser($this->user)->getSearchPartner();
        $counter = $response->data->returned_records;
        $total_records = $response->data->total_records;

        if (! empty($total_records)) {
            $root = $this->detailAppender($xml, $root, $response->data->records, 'partner', $this->urlTemplate, $detailUri);

            while ($counter < $response->data->total_records) {
                $_GET['skip'] = $_GET['skip'] + $_GET['take'];
                $response = Orbit\Controller\API\v1\Pub\Partner\PartnerListAPIController::create('raw')->setUser($this->user)->getSearchPartner();

                $root = $this->detailAppender($xml, $root, $response->data->records, 'partner', $this->urlTemplate, $detailUri);

                $counter = $counter + $response->data->returned_records;
                usleep($this->sleep);
            }

            $xml->appendChild($root);
        }

        return $xml;
    }

    /**
     * Single function to append urls
     *
     * @param $xml DOMDocument
     * @return $xml DOMDocument
     */
    protected function detailAppender($xml, $root, $records, $type, $urlTemplate, $detailUri, $mall_id = null, $mall_slug = null)
    {
        foreach ($records as $record) {
            // todo : just use $record['updated_at'] if store list already in elasticsearch
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
                    // change to array if store list already on elasticsearch
                    $id = $record->merchant_id;
                    $name = $record->name;
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

            $xmlSitemapUrl = $xml->createElement('url');
            if (! empty($mall_id)) {
                $xmlSitemapLoc = $xml->createElement('loc', sprintf(sprintf(sprintf($urlTemplate, $mall_id, $mall_slug, $detailUri['uri']), $id, Str::slug($name))));
            } else {
                $xmlSitemapLoc = $xml->createElement('loc', sprintf(sprintf($urlTemplate, $detailUri['uri']), $id, Str::slug($name)));
            }
            $xmlSitemapLastMod = $xml->createElement('lastmod', date('c', $updatedAt));
            $xmlSitemapChangeFreq = $xml->createElement('changefreq', $detailUri['changefreq']);
            $xmlSitemapPriority = $xml->createElement('priority', $this->priority);
            $xmlSitemapUrl->appendChild($xmlSitemapLoc);
            $xmlSitemapUrl->appendChild($xmlSitemapLastMod);
            $xmlSitemapUrl->appendChild($xmlSitemapChangeFreq);
            $xmlSitemapUrl->appendChild($xmlSitemapPriority);
            $root->appendChild($xmlSitemapUrl);
        }

        return $root;
    }

    /**
     * Generate miscelaneous sitemap
     *
     * @param $xml DOMDocument
     * @return $xml DOMDocument
     */
    protected function generateMiscListSitemap($xml)
    {
        $root = $xml->firstChild;
        $listUris = Config::get('orbit.sitemap.uri_properties.misc', []);
        foreach ($listUris as $uri) {
            $xmlSitemapUrl = $xml->createElement('url');
            $xmlSitemapLoc = $xml->createElement('loc', sprintf($this->urlTemplate, $uri['uri']));
            $xmlSitemapLastMod = $xml->createElement('lastmod', date('c'));
            $xmlSitemapChangeFreq = $xml->createElement('changefreq', $uri['changefreq']);
            $xmlSitemapPriority = $xml->createElement('priority', $this->priority);
            $xmlSitemapUrl->appendChild($xmlSitemapLoc);
            $xmlSitemapUrl->appendChild($xmlSitemapLastMod);
            $xmlSitemapUrl->appendChild($xmlSitemapChangeFreq);
            $xmlSitemapUrl->appendChild($xmlSitemapPriority);
            $root->appendChild($xmlSitemapUrl);
        }

        $xml->appendChild($root);

        return $xml;
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
