<?php
namespace Orbit\Controller\API\v1\Product\MassUpload;

use Marketplace;
use Category;
use Exception;


/**
 * Helper class to uniform product creation parameter from multiple sources of involve.asia datafeed
 */
class MassProductParameterHelper
{
    protected $marketplaceType = '';

    protected $file = null;

    protected $params = [];

    public function __construct($marketplaceType, $file)
    {
        $this->marketplaceType = $marketplaceType;
        $this->file = $file;
    }

    public static function create($marketplaceType, $file)
    {
        return new static($marketplaceType, $file);
    }

    public static function getParams()
    {
        try {

            switch ($this->marketplaceType) {
                case 'tokopedia':
                    // $tokopedia = Marketplace::where('name', 'Tokopedia')->firstOrFail();

                    $offerId = '100837';
                    $trackingLinkId = '102758';

                    // read file
                    $values = $this->readXML($this->file);

                    $param = [];
                    foreach ($values as $value) {
                        // build marketplace data string of object
                        $websiteUrl = str_replace('OFFER-ID', $offerId, $values['tracking_url']);
                        $websiteUrl = str_replace('TRACKING-LINK-ID', $trackingLinkId, $websiteUrl);

                        $marketplaceData = [
                            'id' => $tokopedia->marketplace_id,
                            'website_url' => $websiteUrl,
                            'selling_price' => $values['price_after_discount'],
                            'original_price' => $values['price']
                        ];

                        $marketplaceData = json_encode($marketplaceData);

                        $image = file_get_contents($values['images/i/0']);

                        $categories = [];
                        // for now map all products to 'Others' category
                        $otherCategory = Category::where('status', 'active')
                            ->where('merchant_id', '0')
                            ->where('category_name', 'Others')
                            ->firstOrFail();

                        $categories[] = $otherCategory->category_id;

                        $param['name'] = $values['name'];
                        $param['short_description'] = '-';
                        $param['status'] = 'inactive';
                        $param['country_id'] = 101;
                        $param['marketplaces'] = $marketplaceData;
                        $param['images'] = $image;
                        $param['categories'] = $categories;

                        $this->params[] = $param;
                    }

                    break;

                default:
                    throw new Exception("Unknown marketplace type", 1);
                    break;
            }
            return $this->params;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    private function readCSV($csvFile)
    {
        $file_handle = fopen($csvFile, 'r');
        while (!feof($file_handle) ) {
            $line_of_text[] = fgetcsv($file_handle, 0);
        }
        fclose($file_handle);
        return $line_of_text;
    }

    private function readXML($xmlFile)
    {
        $params = [];
        $xml = simplexml_load_string($xmlString);
        foreach($xml->products->item as $item)
        {
           $param = array();

           foreach($item as $key => $value)
           {
                $param[(string)$key] = $value;
           }

           $params[] = $param;
        }

        return $params;
    }
}