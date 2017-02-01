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

class SuggestionAPIController extends PubControllerAPI
{
    /**
     * GET - suggestion list
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
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

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            // get all country and city name in mall
            $mallCountries = Mall::where('status', 'active')->groupBy('country')->lists('country');
            $mallCities = Mall::where('status', 'active')->groupBy('city')->lists('city');

            $field = 'suggest_' . $language;
            $body = array('gtm_suggestions' => array('text' => $text, 'completion' => array('size' => $take, 'field' => $field, 'context' => array('country' => $mallCountries, 'city' => $mallCities))));

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

            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $suggestionIndex = Config::get('orbit.elasticsearch.suggestion_indices');
            $countSuggestion = count($suggestionIndex);
            $suggestion = '';
            $i = 1;
            foreach ($suggestionIndex as $suggest) {
                $suggestPrefix = $esPrefix . $suggest;
                if ($i != $countSuggestion) {
                    $suggestPrefix = $suggestPrefix . ',';
                }
                $suggestion .= $suggestPrefix;
                $i++;
            }

            $esParam = [
                'index'  => $suggestion,
                'body'   => json_encode($body)
            ];
            $response = $client->suggest($esParam);

            $listSuggestion = $response['gtm_suggestions'][0]['options'];

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