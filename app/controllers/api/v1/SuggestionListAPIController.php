<?php

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenExceptio;
use Illuminate\Database\QueryException;
use Elasticsearch\ClientBuilder;

/**
 * Event List API for Article Portal
 */
class SuggestionListAPIController extends ControllerAPI
{
    protected $allowedRoles = ['super admin', 'article writer', 'article publisher'];

    public function getSuggestionList()
    {
        $httpCode = 200;
        $keyword = null;
        $user = null;
        try {
            $this->checkAuth();
            $user = $this->api->user;
            // role based auth
            $role = $user->role;
            if (! in_array(strtolower($role->role_name), $this->allowedRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $take = OrbitInput::get('take', 5);
            $text = OrbitInput::get('text', '');
            $linkType = OrbitInput::get('link_type', '');
            $host = Config::get('orbit.elasticsearch');
            $language = OrbitInput::get('language', 'id');
            $mallCountries = OrbitInput::get('country', null);
            $mallCities = OrbitInput::get('cities', null);

            $validator = Validator::make(
                array(
                    'link_type' => $linkType,
                ),
                array(
                    'link_type' => 'in:event,promotion,coupon,mall,store',
                ),
                array(
                    'link_type.in' => 'The sort by argument you specified is not valid, the valid values are: event, promotion, coupon, mall, store',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $client = ClientBuilder::create() // Instantiate a new ClientBuilder
                    ->setHosts($host['hosts']) // Set the hosts
                    ->build();

            if (empty($mallCountries)) {
                $mallCountries = Mall::where('status', 'active')->groupBy('country')->lists('country');
            }

            if (empty($mallCities)) {
                $mallCities = Mall::where('status', 'active')->groupBy('city')->lists('city');
            }

            switch ($mallCountries) {
                case 'Indonesia':
                    $language = 'id';
                    break;
                case 'Singapore':
                    $language = 'en';
                    break;
                default:
                    $language = 'id';
                    break;
            }

            $field = 'suggest_' . $language;
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

            $esPrefix = Config::get('orbit.elasticsearch.indices_prefix');
            $countSuggestion = count($suggestionIndex);
            $suggestion = '';
            switch ($linkType) {
                case 'event':
                    $suggestion = $esPrefix . 'news_suggestions';
                    break;
                case 'promotion':
                    $suggestion = $esPrefix . 'promotion_suggestions';
                    break;
                case 'coupon':
                    $suggestion = $esPrefix . 'coupon_suggestions';
                    break;
                case 'mall':
                    $suggestion = $esPrefix . 'mall_suggestions';
                    break;
                case 'store':
                    $suggestion = $esPrefix . 'store_suggestions';
                    break;

                default:
                    $i = 1;
                    foreach ($suggestionIndex as $suggest) {
                        $suggestPrefix = $esPrefix . $suggest;
                        if ($i != $countSuggestion) {
                            $suggestPrefix = $suggestPrefix . ',';
                        }
                        $suggestion .= $suggestPrefix;
                        $i++;
                    }
                    break;
            }

            $esParam = [
                'index'  => $suggestion,
                'body'   => json_encode($body)
            ];
            $response = $client->suggest($esParam);

            $listSuggestion = [];
            if (isset($response['gtm_suggestions'])) {
                $listSuggestion = $response['gtm_suggestions'][0]['options'];
            }

            $this->response->data = new stdClass();
            $this->response->data->total_records = count($listSuggestion);
            $this->response->data->returned_records = count($listSuggestion);
            $this->response->data->records = $listSuggestion;
        } catch (ACLForbiddenException $e) {
            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = [$e->getFile(), $e->getLine()];
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
            $this->response->data = [$e->getFile(), $e->getLine()];
            $httpCode = 500;

        } catch (Exception $e) {
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = [$e->getFile(), $e->getLine()];
            $httpCode = 500;
        }

        return $this->render($httpCode);
    }
}
