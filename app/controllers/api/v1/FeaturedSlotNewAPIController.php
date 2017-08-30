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
    public function postNewFeaturedSlot()
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
            $slots = OrbitInput::post('slot', []);
            $startDate = OrbitInput::post('start_date');
            $endDate = OrbitInput::post('end_date');
            $status = OrbitInput::post('status', 'active');

            $validator = Validator::make(
                array(
                    'advert_id' => $advertId,
                    'featured_location' => $featuredLocation,
                    'section' => $section,
                    'slot' => $slots,
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ),
                array(
                    'advert_id' => 'required',
                    'featured_location' => 'required',
                    'section' => 'required',
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

            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateNow = $date->setTimezone('Asia/Jakarta')->toDateTimeString();

            // Begin database transaction
            $this->beginTransaction();

            if (! empty($slots)) {
                $slot = @json_decode($slots);
                if (json_last_error() != JSON_ERROR_NONE) {
                    OrbitShopAPI::throwInvalidArgument('JSON not valid');
                }

                foreach ($slot as $data) {
                    $newSlot = AdvertSlotLocation::where('advert_slot_locations.slot_type', $section)
                                            ->where('advert_slot_locations.location_id', $featuredLocation)
                                            ->where('advert_slot_locations.city', $data->city)
                                            ->where('advert_slot_locations.slot_number', $data->slot)
                                            ->where('advert_slot_locations.end_date', '>=', $dateNow)
                                            ->first();

                    if (is_object($newSlot)) {
                        // check advert slot was taken or not
                        $checkSlot = AdvertSlotLocation::select('adverts.advert_id', 'adverts.advert_name')
                                            ->join('adverts', 'adverts.advert_id', '=', 'advert_slot_locations.advert_id')
                                            ->where('adverts.status', 'active')
                                            ->where('advert_slot_locations.status', 'active')
                                            ->where('advert_slot_locations.slot_type', $section)
                                            ->where('advert_slot_locations.location_id', $featuredLocation)
                                            ->where('advert_slot_locations.city', $data->city)
                                            ->where('advert_slot_locations.slot_number', $data->slot)
                                            ->where('adverts.end_date', '>=', $dateNow)
                                            ->where('advert_slot_locations.end_date', '>=', $dateNow)
                                            ->where('adverts.advert_id', '!=', $advertId)
                                            ->first();

                        if (is_object($checkSlot)) {
                            $locationName = 'GTM';
                            if ($featuredLocation != '0') {
                                $mall = Mall::where('merchant_id', $featuredLocation)->first();
                                $locationName = $mall->name;
                            }

                            $errorMessage = $section . " slot number " . $data->slot . " in " . $locationName . " is already taken by advert '" . $checkSlot->advert_name . "'";
                            OrbitShopAPI::throwInvalidArgument($errorMessage);
                        }
                    } else {
                        $newSlot = new AdvertSlotLocation();
                    }

                    $newSlot->advert_id = $advertId;
                    $newSlot->location_id = $featuredLocation;
                    $newSlot->country_id = $data->country_id;
                    $newSlot->city = $data->city;
                    $newSlot->slot_type = $section;
                    $newSlot->slot_number = $data->slot;
                    $newSlot->start_date = $startDate;
                    $newSlot->end_date = $endDate;
                    $newSlot->status = $status;
                    $newSlot->save();

                    $listNewSlot[] = $newSlot;
                }
            }

            // Commit the changes
            $this->commit();

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->data = $listNewSlot;

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