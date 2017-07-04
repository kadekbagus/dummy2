<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing pages with multilanguage.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Config;
use Page;
use stdClass;
use Orbit\Helper\Util\PaginationNumber;
use DB;
use Validator;
use Activity;

class PageAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;

    /**
     * GET - get spesific page with spesific language
     *
     * @author Firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string country
     * @param string object_type
     * @param string language
     *
     * @return Illuminate\Support\Facades\Response
     */

    public function getPage()
    {
        $httpCode = 200;
        $activity = Activity::mobileci()->setActivityType('view');
        $user = null;

        try {
            $user = $this->getUser();
            $country = OrbitInput::get('country');
            $object_type = OrbitInput::get('object_type');
            $language = OrbitInput::get('language');

            $validator = Validator::make(
                array(
                    'country' => $country,
                    'object_type' => $object_type,
                    'language' => $language,
                ),
                array(
                    'country' => 'required',
                    'object_type' => 'required|in:advertise_with_us,about_us,feedback',
                    'language' => 'required',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $page = Page::select('pages.content', 'pages.language')
                        ->join('countries', 'pages.country_id', '=', 'countries.country_id')
                        ->where('countries.name' , $country)
                        ->where('pages.object_type' , $object_type)
                        ->where('pages.language' , $language)
                        ->get();

            if ($object_type === 'about_us') {
                $fileName = Config::get('about_us.counter_file', storage_path() . '/about_us_counter.json');

                $data = $this->readJSON($fileName);
                $page[0]->users = empty($data['users']) ? 0 : $data['users'];
                $page[0]->page_views = empty($data['page_views']) ? 0 : $data['page_views'];
                $page[0]->stores = empty($data['stores']) ? 0 : $data['stores'];
                $page[0]->merchants = empty($data['merchants']) ? 0 : $data['merchants'];
            }

            switch ($object_type) {
                case 'advertise_with_us' :
                        $notes = 'Page viewed: View Advertise With Us';
                        $actName = 'view_advertise';
                        $actNameLong = 'View Advertise';
                        break;

                case 'feedback' :
                        $notes = 'Page viewed: View Feedback';
                        $actName = 'view_feedback';
                        $actNameLong = 'View Feedback';
                        break;

                default :
                        $notes = 'Page viewed: View About Us';
                        $actName = 'view_about_us';
                        $actNameLong = 'View About Us';
            }

            if (empty($skip)) {
                $activityNotes = sprintf($notes);
                $activity->setUser($user)
                    ->setActivityName($actName)
                    ->setActivityNameLong($actNameLong)
                    ->setObject(null)
                    ->setLocation('GTM')
                    ->setModuleName('Application')
                    ->setNotes($activityNotes)
                    ->responseOK()
                    ->save();
            }

            $count = count($page);

            $this->response->data = new stdClass();
            $this->response->data->total_records = $count;
            $this->response->data->returned_records = $count;
            $this->response->data->records = $page;
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
        } catch (Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        $output = $this->render($httpCode);

        return $output;
    }

    /**
     * Read the json file.
     */
    protected function readJSON($file)
    {
        if (! file_exists($file) ) {
           throw new Exception('Could not found json file.');
        }

        $json = file_get_contents($file);
        return $this->readJSONString($json);
    }

    /**
     * Read JSON from string
     *
     * @return string|mixed
     */
    protected function readJSONString($json)
    {
        $conf = @json_decode($json, TRUE);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception( sprintf('Error parsing JSON: %s', json_last_error_msg()) );
        }

        return $conf;
    }

}
