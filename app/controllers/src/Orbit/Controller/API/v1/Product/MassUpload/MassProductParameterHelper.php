<?php
namespace Orbit\Controller\API\v1\Product\MassUpload;

use Marketplace;
use Category;
use Exception;
use Config;


/**
 * Helper class to uniform product creation parameter from multiple sources of involve.asia datafeed
 */
class MassProductParameterHelper
{
    protected $marketplaceType = '';

    protected $offerId = '';

    protected $file = null;

    protected $params = [];

    public function __construct($marketplaceType, $file, $offerId)
    {
        $this->marketplaceType = $marketplaceType;
        $this->file = $file;
        $this->offerId = $offerId;
    }

    public static function create($marketplaceType, $file, $offerId)
    {
        return new static($marketplaceType, $file, $offerId);
    }

    public function getParams()
    {
        try {

            switch ($this->marketplaceType) {
                case 'tokopedia':
                    $tokopedia = Marketplace::where('name', 'Tokopedia')->firstOrFail();

                    $offerId = $this->offerId;
                    $trackingLinkId = Config::get('orbit.partners_api.involve_asia.tracking_link_id', '');

                    // read file
                    $values = $this->readXML($this->file);

                    $param = [];
                    foreach ($values as $value) {
                        // build marketplace data string of object
                        $websiteUrl = str_replace('OFFER-ID', $offerId, $value->tracking_url);
                        $websiteUrl = str_replace('TRACKING-LINK-ID', $trackingLinkId, $websiteUrl);

                        $marketplaceData = [
                            'id' => $tokopedia->marketplace_id,
                            'website_url' => $websiteUrl,
                            'selling_price' => (string) $value->price_after_discount,
                            'original_price' => (string) $value->price === (string) $value->price_after_discount ? '' : (string) $value->price
                        ];

                        $marketplaceData = json_encode($marketplaceData);
                        $images = (array) $value->images->i;
                        $uploadedFileData = [];
                        if (! empty($images[0])) {
                            $image = file_get_contents($images[0]);
                            // put the image on tmp dir
                            $imageFileName = 'upload-tokopedia-' . microtime() . '.jpg';
                            $tmpDir = '/tmp/' . $imageFileName;
                            file_put_contents($tmpDir, $image);
                            $uploadedFileData = [
                                'name' => $imageFileName,
                                'type' => 'image/jpeg',
                                'tmp_name' => $tmpDir,
                                'error' => 0,
                                'size' => filesize($tmpDir),
                            ];
                        }

                        $categories = [];
                        // for now map all products to 'Others' category
                        $otherCategory = Category::where('status', 'active')
                            ->where('merchant_id', '0')
                            ->where('category_name', 'Others')
                            ->firstOrFail();

                        $categories[] = $otherCategory->category_id;

                        $param['name'] = (string) $value->name;
                        $param['short_description'] = '-';
                        $param['status'] = 'inactive';
                        $param['country_id'] = 101;
                        $param['marketplaces'] = $marketplaceData;
                        $param['images'] = $uploadedFileData;
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
        $xml = simplexml_load_file($xmlFile);
        foreach($xml->i as $item)
        {
           $params[] = $item;
        }

        return $params;
    }
}