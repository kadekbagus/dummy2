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

    protected $languageName = 'id';

    protected $languageId = '';

    protected $formatOutput = FALSE;

    protected $sitemapType = '';

    protected $priority = '0.8';

    protected $user = NULL;

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
        $this->languageId = $language = Language::where('status', '=', 'active')
            ->where('name', $this->languageName)
            ->first()
            ->language_id;

        $this->user = User::with('apikey')
            ->leftJoin('roles', 'users.user_role_id', '=', 'roles.role_id')
            ->where('role_name', 'Super Admin')
            ->first();
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

                case 'all':
                default:
                    $returnedXML = $this->generateMiscListSitemap($xml);
                    $returnedXML = $this->generateAllListSitemap($returnedXML);
                    $returnedXML = $this->generateAllDetailSitemap($returnedXML);
                    break;
            }
        } catch (\Exception $e) {
            print_r([$e->getMessage(), $e->getLine()]);
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
    protected function generatePromotionDetailsSitemap($xml)
    {
        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.promotion', []);
        $root = $xml->firstChild;
        $_GET['from_homepage'] = 'y';
        $_GET['take'] = 50;
        $_GET['skip'] = 0;
        $response = Orbit\Controller\API\v1\Pub\Promotion\PromotionListAPIController::create('raw')->setUser($this->user)->getSearchPromotion();
        $counter = $response->data->returned_records;
        $total_records = $response->data->total_records;

        foreach ($response->data->records as $record) {
            $xmlSitemapUrl = $xml->createElement('url');
            $xmlSitemapLoc = $xml->createElement('loc', sprintf(sprintf($this->urlTemplate, $detailUri['uri']), $record['news_id'], Str::slug($record['news_name'])));
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
        }

        while ($counter < $response->data->total_records) {
            $_GET['skip'] = $_GET['skip'] + $_GET['take'];
            $response = Orbit\Controller\API\v1\Pub\Promotion\PromotionListAPIController::create('raw')->setUser($this->user)->getSearchPromotion();

            foreach ($response->data->records as $record) {
                $xmlSitemapUrl = $xml->createElement('url');
                $xmlSitemapLoc = $xml->createElement('loc', sprintf(sprintf($this->urlTemplate, $detailUri['uri']), $record['news_id'], Str::slug($record['news_name'])));
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
            }

            $counter = $counter + $response->data->returned_records;
        }

        $xml->appendChild($root);

        return $xml;
    }

    /**
     * Generate all Event detail sitemap
     *
     * @param $xml DOMDocument
     * @return $xml DOMDocument
     */
    protected function generateEventDetailsSitemap($xml)
    {
        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.event', []);
        $root = $xml->firstChild;
        $_GET['from_homepage'] = 'y';
        $_GET['take'] = 50;
        $_GET['skip'] = 0;
        $response = Orbit\Controller\API\v1\Pub\News\NewsListAPIController::create('raw')->setUser($this->user)->getSearchNews();
        $counter = $response->data->returned_records;
        $total_records = $response->data->total_records;

        foreach ($response->data->records as $record) {
            $xmlSitemapUrl = $xml->createElement('url');
            $xmlSitemapLoc = $xml->createElement('loc', sprintf(sprintf($this->urlTemplate, $detailUri['uri']), $record['news_id'], Str::slug($record['news_name'])));
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
        }

        while ($counter < $response->data->total_records) {
            $_GET['skip'] = $_GET['skip'] + $_GET['take'];
            $response = Orbit\Controller\API\v1\Pub\News\NewsListAPIController::create('raw')->setUser($this->user)->getSearchNews();

            foreach ($response->data->records as $record) {
                $xmlSitemapUrl = $xml->createElement('url');
                $xmlSitemapLoc = $xml->createElement('loc', sprintf(sprintf($this->urlTemplate, $detailUri['uri']), $record['news_id'], Str::slug($record['news_name'])));
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
            }

            $counter = $counter + $response->data->returned_records;
        }

        $xml->appendChild($root);

        return $xml;
    }

    /**
     * Generate all Coupon detail sitemap
     *
     * @param $xml DOMDocument
     * @return $xml DOMDocument
     */
    protected function generateCouponDetailsSitemap($xml)
    {
        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.coupon', []);
        $root = $xml->firstChild;
        $_GET['from_homepage'] = 'y';
        $_GET['take'] = 50;
        $_GET['skip'] = 0;
        $response = Orbit\Controller\API\v1\Pub\Coupon\CouponListAPIController::create('raw')->setUser($this->user)->getCouponList();
        $counter = $response->data->returned_records;
        $total_records = $response->data->total_records;

        foreach ($response->data->records as $record) {
            $xmlSitemapUrl = $xml->createElement('url');
            $xmlSitemapLoc = $xml->createElement('loc', sprintf(sprintf($this->urlTemplate, $detailUri['uri']), $record['coupon_id'], Str::slug($record['coupon_name'])));
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
        }

        while ($counter < $response->data->total_records) {
            $_GET['skip'] = $_GET['skip'] + $_GET['take'];
            $response = Orbit\Controller\API\v1\Pub\Coupon\CouponListAPIController::create('raw')->setUser($this->user)->getCouponList();

            foreach ($response->data->records as $record) {
                $xmlSitemapUrl = $xml->createElement('url');
                $xmlSitemapLoc = $xml->createElement('loc', sprintf(sprintf($this->urlTemplate, $detailUri['uri']), $record['coupon_id'], Str::slug($record['coupon_name'])));
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
            }

            $counter = $counter + $response->data->returned_records;
        }

        $xml->appendChild($root);

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
            }

            $counter = $counter + $response->data->returned_records;
        }

        $xml->appendChild($root);

        return $xml;
    }

    /**
     * Generate all Store detail sitemap
     *
     * @param $xml DOMDocument
     * @return $xml DOMDocument
     */
    protected function generateStoreDetailsSitemap($xml)
    {
        $detailUri = Config::get('orbit.sitemap.uri_properties.detail.store', []);
        $root = $xml->firstChild;
        $_GET['from_homepage'] = 'y';
        $_GET['take'] = 50;
        $_GET['skip'] = 0;
        $response = Orbit\Controller\API\v1\Pub\StoreAPIController::create('raw')->setUser($this->user)->getStoreList();
        $counter = $response->data->returned_records;
        $total_records = $response->data->total_records;

        // todo: change access to record property to array after elastic search for store list is done
        foreach ($response->data->records as $record) {
            $xmlSitemapUrl = $xml->createElement('url');
            $xmlSitemapLoc = $xml->createElement('loc', sprintf(sprintf($this->urlTemplate, $detailUri['uri']), $record->merchant_id, Str::slug($record->name)));
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
        }

        while ($counter < $response->data->total_records) {
            $_GET['skip'] = $_GET['skip'] + $_GET['take'];
            $response = Orbit\Controller\API\v1\Pub\StoreAPIController::create('raw')->setUser($this->user)->getStoreList();

            foreach ($response->data->records as $record) {
                $xmlSitemapUrl = $xml->createElement('url');
                $xmlSitemapLoc = $xml->createElement('loc', sprintf(sprintf($this->urlTemplate, $detailUri['uri']), $record->merchant_id, Str::slug($record->name)));
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
            }

            $counter = $counter + $response->data->returned_records;
        }

        $xml->appendChild($root);

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
        $_GET['from_homepage'] = 'y';
        $_GET['visible'] = 'yes';
        $_GET['take'] = 50;
        $_GET['skip'] = 0;
        $response = Orbit\Controller\API\v1\Pub\Partner\PartnerListAPIController::create('raw')->setUser($this->user)->getSearchPartner();
        $counter = $response->data->returned_records;
        $total_records = $response->data->total_records;

        // todo: change access to record property to array after elastic search for partner list is done
        foreach ($response->data->records as $record) {
            $xmlSitemapUrl = $xml->createElement('url');
            $xmlSitemapLoc = $xml->createElement('loc', sprintf(sprintf($this->urlTemplate, $detailUri['uri']), $record->partner_id, Str::slug($record->partner_name)));
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
        }

        while ($counter < $response->data->total_records) {
            $_GET['skip'] = $_GET['skip'] + $_GET['take'];
            $response = Orbit\Controller\API\v1\Pub\Partner\PartnerListAPIController::create('raw')->setUser($this->user)->getSearchPartner();

            foreach ($response->data->records as $record) {
                $xmlSitemapUrl = $xml->createElement('url');
                $xmlSitemapLoc = $xml->createElement('loc', sprintf(sprintf($this->urlTemplate, $detailUri['uri']), $record->partner_id, Str::slug($record->partner_name)));
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
            }

            $counter = $counter + $response->data->returned_records;
        }

        $xml->appendChild($root);

        return $xml;
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
                array('type', NULL, InputOption::VALUE_OPTIONAL, 'URL type', 'all'),
                array('format-output', 0, InputOption::VALUE_NONE, 'Format sitemap XML file output.'),
            );
    }
}
