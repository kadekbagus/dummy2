<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for get suggestion list
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenExceptio;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use Config;
use Mall;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use Elasticsearch\ClientBuilder;
use Language;
use DB;
use Tenant;
use Cache;
use BrandProduct;
use Log;

class SuggestionAPIController extends PubControllerAPI
{
    /**
     * GET - Suggestion list
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param int take
     * @param string text
     * @param string language
     * @param string country
     * @param string cities
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSuggestionList()
    {
      $httpCode = 200;
        try {
            $take = OrbitInput::get('take', 5);
            $text = OrbitInput::get('text', '');
            $host = Config::get('orbit.elasticsearch');
            $language = OrbitInput::get('language', 'id');
            $mallCountries = OrbitInput::get('country', null);
            $mallCities = OrbitInput::get('cities', []);
            $mallId = OrbitInput::get('mall_id', null);

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            // If parsing mall_id thats mean we search suggestion in mall level, and even no parsing that is common searching suggestion
            $field = 'suggest_' . $language;
            if (!empty($mallId)) {
                $body = array(
                                'gtm_suggestions' => array(
                                    'text' => $text,
                                    'completion' => array(
                                        'size' => $take,
                                        'field' => $field,
                                        'context' => array(
                                            'mall_id' => $mallId
                                        )
                                    )
                                )
                            );

                $suggestionIndex = Config::get('orbit.elasticsearch.suggestion_mall_level_indices');
            } else {
                if (empty($mallCountries)) {
                    $mallCountries = Mall::where('status', 'active')->groupBy('country')->lists('country');
                }

                if (empty($mallCities)) {
                    $mallCities = Mall::where('status', 'active')->groupBy('city')->lists('city');
                }

                $body = [
                    'gtm_suggestions' => [
                        'text' => $text,
                        'completion' => [
                            'size' => $take,
                            'field' => $field,
                            'context' => [
                                'country' => $mallCountries,
                                'city' => $mallCities,
                            ]
                        ]
                    ]
                ];

                OrbitInput::get('country', function($country) use (&$body)
                {
                    if (! empty($country) || $country != '') {
                        $body['gtm_suggestions']['completion']['context']['country'] = $country;
                    }
                });

                OrbitInput::get('cities', function($cities) use (&$body)
                {
                    if (! empty($cities) || $cities != '') {
                        $body['gtm_suggestions']['completion']['context']['city'] = $cities;
                    }
                });

                $suggestionIndex = Config::get('orbit.elasticsearch.suggestion_indices');
            }

            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $suggestion = array_map(function($indexName) use ($esPrefix) {
                return $esPrefix . $indexName;
            }, $suggestionIndex);

            $esParam = [
                'index'  => implode(',', $suggestion),
                'body'   => json_encode($body)
            ];

            $response = $client->suggest($esParam);

            $listSuggestion = [];
            if (isset($response['gtm_suggestions'])) {
                $listSuggestion = $response['gtm_suggestions'][0]['options'];
                if (! empty($mallId)) {
                    foreach ($listSuggestion as &$itemSuggestion) {
                        if ($itemSuggestion['payload']['type'] == 'store') {
                            $baseID = $itemSuggestion['payload']['id'];
                            $storeName = $itemSuggestion['text'];
                            $mallStore = Tenant::where('name', $storeName)
                                ->where('status', 'active')
                                ->where('object_type', 'tenant')
                                ->where('parent_id', $mallId)
                                ->first();

                            if (is_object($mallStore)) {
                                $itemSuggestion['payload']['id'] = $mallStore->merchant_id;
                            }
                        }
                    }
                }
            }

            $this->response->data = new stdClass();
            $this->response->data->total_records = count($listSuggestion);
            $this->response->data->returned_records = count($listSuggestion);
            $this->response->data->records = $listSuggestion;
        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (\Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        $output = $this->render($httpCode);

        return $output;
    }
}