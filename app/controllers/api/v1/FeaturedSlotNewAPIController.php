<?php
/**
 * An API controller for mall location (country,city,etc).
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Helper\EloquentRecordCounter as RecordCounter;
use DominoPOS\OrbitUploader\Uploader as OrbitUploader;
use Carbon\Carbon as Carbon;

class FeaturedSlotNewAPIController extends ControllerAPI
{
    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];
    /**
     * POST - post new featured slot
     * @author shelgi <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string            `mall_country_id`               (optional) - mall country id
     * @param string            `country_id`                    (optional) - country id
     * @param string            `country_like`                  (optional) - country
     * @param string            `sort_by`                       (optional) - column order by
     * @param string            `sort_mode`                     (optional) - asc or desc
     * @param integer           `take`                          (optional) - limit
     * @param integer           `skip`                          (optional) - limit
     *
     * @return Illuminate\Support\Facades\Response
     *
     */
    public function getFeaturedCity()
    {
        try {
            $httpCode = 200;

            // Require authentication
            $this->checkAuth();

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            $featuredLocation = OrbitInput::post('featured_location');
            $advertId = OrbitInput::post('advert_id');
            $section = OrbitInput::post('section');
            $city = OrbitInput::post('city', []);
            $slot = OrbitInput::post('slot', []);
            $startDate = OrbitInput::post('start_date');
            $endDate = OrbitInput::post('end_date');
            $status = OrbitInput::post('status', 'active');

            $validator = Validator::make(
                array(
                    'advert_id' => $advertId,
                    'featured_location' => $featuredLocation,
                    'section' => $section,
                    'city' => $city,
                    'slot' => $slot,
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ),
                array(
                    'advert_id' => 'required',
                    'featured_location' => 'required',
                    'section' => 'required',
                    'city' => 'required',
                    'slot' => 'required',
                    'start_date' => 'required|date',
                    'end_date' => 'required|date'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Begin database transaction
            $this->beginTransaction();

            if (! empty($city)) {
                $totalCity = count($city);
                for ($i = 0; $i < $totalCity; $i++) {
                    $newSlot = new AdvertSlotLocation();
                    $newSlot->advert_id = $advertId;
                    $newSlot->location_id = $featuredLocation;
                    $newSlot->city = $city[$i];
                    $newSlot->slot_type = $section;
                    $newSlot->slot_number = $slot[$i];
                    $newSlot->start_date = $startDate;
                    $newSlot->end_date = $endDate;
                    $newSlot->status = $status;
                    $newSlot->save();
                }
            }

            $this->response->data = $newnews;

            // Commit the changes
            $this->commit();

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->data = null;

            Event::fire('orbit.advert.postupdateslot.after.commit', array($this, $advertId));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.mall.getsearchmallcountry.query.error', array($this, $e));

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
            Event::fire('orbit.mall.getsearchmallcountry.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.mall.getsearchmallcountry.before.render', array($this, &$output));

        return $output;
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}