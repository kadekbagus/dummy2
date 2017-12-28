<?php namespace Orbit\Controller\API\v1\Pub\Sponsor;
/**
 * An API controller for get list country of sponsor provider.
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
use SponsorProvider;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;

class CountrySponsorListAPIController extends PubControllerAPI
{

    /**
     * GET - Get list country of sponsor provider.
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string area
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getCountrySponsorList()
    {
      $httpCode = 200;
        try {

            $objectType = OrbitInput::get('object_type', null);

            $validator = Validator::make(
                array('object_type' => $objectType),
                array('object_type'   => 'required|in:bank,ewallet')
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $country = SponsorProvider::select('countries.name')
                                   ->join('countries', 'countries.country_id', '=', 'sponsor_providers.country_id')
                                   ->where('sponsor_providers.status', 'active')
                                   ->where('object_type', $objectType)
                                   ->groupBy('countries.name')
                                   ->orderBy('countries.name', 'asc');

            $_listCountry = $country;

            $take = PaginationNumber::parseTakeFromGet('category');
            $country->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $country->skip($skip);

            $listOfRec = $country->get();

            $count = count($_listCountry->get());
            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = count($listOfRec);
            $this->response->data->records = $listOfRec;
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