<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for get suggestion mall level list
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
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use Elasticsearch\ClientBuilder;
use DB;

class SuggestionMallLevelAPIController extends PubControllerAPI
{
    /**
     * GET - suggestion mall level list
     *
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
    public function getSuggestionMallLevelList()
    {
      $httpCode = 200;
        try {
            $take = OrbitInput::get('take', 5);
            $text = OrbitInput::get('text', 'bandung');
            $host = Config::get('orbit.elasticsearch');
            $language = OrbitInput::get('language', 'id');
            $mallId = OrbitInput::get('mall_id', null);

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            $field = 'suggest_' . $language;
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

            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $suggestionIndex = Config::get('orbit.elasticsearch.suggestion_mall_level_indices');
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