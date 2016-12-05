<?php
/**
 * An API controller for managing Advert.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Helper\EloquentRecordCounter as RecordCounter;
use \Carbon\Carbon as Carbon;
use \Orbit\Helper\Exception\OrbitCustomException;

class PartnerAPIController extends ControllerAPI
{
    protected $viewPartnerRoles = ['super admin', 'mall admin', 'mall owner'];
    protected $modifyPartnerRoles = ['super admin', 'mall admin', 'mall owner'];
    protected $returnBuilder = FALSE;

    /**
     * POST - Create New Partner
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `partner_name`          (optional) - name of partner
     * @param string    `description`           (optional) - description
     * @param string    `city`                  (optional) - city
     * @param string    `province`              (optional) - province
     * @param string    `postal_code`           (optional) - postal_code
     * @param string    `country_id`            (optional) - country_id
     * @param string    `phone`                 (optional) - phone
     * @param string    `url`                   (optional) - url
     * @param string    `note`                  (optional) - note
     * @param string    `contact_firstname`     (optional) - contact_firstname
     * @param string    `contact_lastname`      (optional) - contact_lastname
     * @param string    `contact_position`      (optional) - contact_position
     * @param string    `contact_phone`         (optional) - contact_phone
     * @param string    `contact_email`         (optional) - contact_email
     * @param datetime  `start_date`            (optional) - start date
     * @param datetime  `end_date`              (optional) - end date
     * @param string    `status`                (optional) - active, inactive
     * @param char      `is_shown_in_filter`    (optional) - shown in filter GTM or not, default Y
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewPartner()
    {
        $activity = Activity::portal()
                    ->setActivityType('create');

        $user = NULL;
        $newpartner = NULL;

        try {
            $httpCode = 200;

            Event::fire('orbit.partner.postnewpartner.before.auth', array($this));

            $this->checkAuth();

            Event::fire('orbit.partner.postnewpartner.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.partner.postnewpartner.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->modifyPartnerRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.partner.postnewpartner.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $partner_name = OrbitInput::post('partner_name');
            $description = OrbitInput::post('description');
            $address = OrbitInput::post('address');
            $city = OrbitInput::post('city');
            $province = OrbitInput::post('province');
            $postal_code = OrbitInput::post('postal_code');
            $country_id = OrbitInput::post('country_id');
            $phone = OrbitInput::post('phone');
            $partner_url = OrbitInput::post('partner_url');
            $note = OrbitInput::post('note');
            $contact_firstname = OrbitInput::post('contact_firstname');
            $contact_lastname = OrbitInput::post('contact_lastname');
            $contact_position = OrbitInput::post('contact_position');
            $contact_phone = OrbitInput::post('contact_phone');
            $contact_email = OrbitInput::post('contact_email');
            $start_date = OrbitInput::post('start_date');
            $end_date = OrbitInput::post('end_date');
            $status = OrbitInput::post('status');
            $is_shown_in_filter = OrbitInput::post('is_shown_in_filter', 'Y');
            $deeplink_url = OrbitInput::post('deeplink_url');
            $social_media_id = OrbitInput::post('social_media_id');
            $social_media_uri = OrbitInput::post('social_media_uri');

            $validator = Validator::make(
                array(
                    'partner_name'        => $partner_name,
                    'start_date'          => $start_date,
                    'end_date'            => $end_date,
                    'status'              => $status,
                    'address'             => $address,
                    'city'                => $city,
                    'country_id'          => $country_id,
                    'phone'               => $phone,
                    'contact_firstname'   => $contact_firstname,
                    'contact_lastname'    => $contact_lastname,
                ),
                array(
                    'partner_name'        => 'required',
                    'start_date'          => 'required|date|orbit.empty.hour_format',
                    'end_date'            => 'required|date|orbit.empty.hour_format',
                    'status'              => 'required|in:active,inactive',
                    'address'             => 'required',
                    'city'                => 'required',
                    'country_id'          => 'required',
                    'phone'               => 'required',
                    'contact_firstname'   => 'required',
                    'contact_lastname'    => 'required',
                )
            );

            Event::fire('orbit.partner.postnewpartner.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.partner.postnewpartner.after.validation', array($this, $validator));

            $newPartner = new Partner();
            $newPartner->partner_name = $partner_name;
            $newPartner->description = $description;
            $newPartner->address = $address;
            $newPartner->city = $city;
            $newPartner->province = $province;
            $newPartner->postal_code = $postal_code;
            $newPartner->country_id = $country_id;
            $newPartner->phone = $phone;
            $newPartner->url = $partner_url;
            $newPartner->note = $note;
            $newPartner->contact_firstname = $contact_firstname;
            $newPartner->contact_lastname = $contact_lastname;
            $newPartner->contact_position = $contact_position;
            $newPartner->contact_phone = $contact_phone;
            $newPartner->contact_email = $contact_email;
            $newPartner->start_date = $start_date;
            $newPartner->end_date = $end_date;
            $newPartner->status = $status;
            $newPartner->is_shown_in_filter = $is_shown_in_filter;

            Event::fire('orbit.partner.postnewpartner.before.save', array($this, $newPartner));

            $newPartner->save();

            if (!empty($deeplink_url) ) {
                $newDeepLink = new DeepLink();
                $newDeepLink->object_id = $newPartner->partner_id;
                $newDeepLink->object_type = 'partner';
                $newDeepLink->deeplink_url = $deeplink_url;
                $newDeepLink->status = 'active';
                $newDeepLink->save();
            }

            if (!empty($social_media_id) && !empty($social_media_uri)) {
                $newObjectSocialMedia = new ObjectSocialMedia();
                $newObjectSocialMedia->object_id = $newPartner->partner_id;
                $newObjectSocialMedia->object_type = 'partner';
                $newObjectSocialMedia->social_media_id = $social_media_id;
                $newObjectSocialMedia->social_media_uri = $social_media_uri;
                $newObjectSocialMedia->save();
            }

            Event::fire('orbit.partner.postnewpartner.after.save', array($this, $newPartner));

            $this->response->data = $newPartner;

            // Commit the changes
            $this->commit();

            // Successfull Creation
            $activityNotes = sprintf('partner Created: %s', $newPartner->partner_name);
            $activity->setUser($user)
                    ->setActivityName('create_partner')
                    ->setActivityNameLong('Create partner OK')
                    ->setObject($newPartner)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.partner.postnewpartner.after.commit', array($this, $newPartner));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.partner.postnewpartner.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_partner')
                    ->setActivityNameLong('Create partner Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.partner.postnewpartner.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_partner')
                    ->setActivityNameLong('Create partner Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.partner.postnewpartner.query.error', array($this, $e));

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

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_partner')
                    ->setActivityNameLong('Create partner Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.partner.postnewpartner.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = $e->getLine();

            // Rollback the changes
            $this->rollBack();

            // Creation failed Activity log
            $activity->setUser($user)
                    ->setActivityName('create_partner')
                    ->setActivityNameLong('Create partner Failed')
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save the activity
        $activity->save();

        return $this->render($httpCode);
    }

    /**
     * GET - Search Partner
     *
     * @author Irianto <irianto@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string            `sort_by`                       (optional) - column order by
     * @param string            `sort_mode`                     (optional) - asc or desc
     * @param integer           `take`                          (optional) - limit
     * @param integer           `skip`                          (optional) - limit offset
     * @param string|array      `with`                          (optional) - Relation which need to be included
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchPartner()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.partner.getsearchpartner.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.partner.getsearchpartner.after.auth', array($this));

            // Try to check access control list, does this mall allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.partner.getsearchpartner.before.authz', array($this, $user));

/*
            if (! ACL::create($user)->isAllowed('view_mall')) {
                Event::fire('orbit.partner.getsearchpartner.authz.notallowed', array($this, $user));
                $viewUserLang = Lang::get('validation.orbit.actionlist.view_mall');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $viewUserLang));
                ACL::throwAccessForbidden($message);
            }
*/
            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->viewPartnerRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.partner.getsearchpartner.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $sort_by = OrbitInput::get('sortby');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:partner_id,partner_name,location,start_date,end_date,url,status',
                ),
                array(
                    'in' => Lang::get('validation.orbit.empty.partner_sortby'),
                )
            );

            Event::fire('orbit.partner.getsearchpartner.before.validation', array($this, $validator));

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            Event::fire('orbit.partner.getsearchpartner.after.validation', array($this, $validator));

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.partner.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.partner.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            $prefix = DB::getTablePrefix();

            $partners = Partner::excludeDeleted()
                        ->select(
                            'partner_id',
                            'partner_name',
                            DB::raw("concat({$prefix}partners.city, ', ', {$prefix}countries.name) as location"),
                            'start_date',
                            'end_date',
                            'url',
                            'status'
                        )
                        ->leftJoin('countries', 'countries.country_id', '=', 'partners.country_id');

            // Filter partner by Ids
            OrbitInput::get('partner_id', function ($partnerIds) use ($partners) {
                $partners->whereIn('partners.partner_id', $partnerIds);
            });

            // Filter partner by name
            OrbitInput::get('partner_name', function ($partner_name) use ($partners) {
                $partners->where('partners.partner_name', $partner_name);
            });

            // Filter partner by name like
            OrbitInput::get('partner_name_like', function ($partner_name) use ($partners) {
                $partners->where('partners.partner_name', 'like', "%{$partner_name}%");
            });

            // Filter by start date from
            OrbitInput::get('start_date_from', function($start_date) use ($partners)
            {
                $partners->where('partners.start_date', '>=', $start_date);
            });

            // Filter by start date to
            OrbitInput::get('start_date_to', function($end_date) use ($partners)
            {
                $partners->where('partners.start_date', '<=', $end_date);
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($partners) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    $partners->with($relation);
                }
            });


            $_partners = clone $partners;

            // if not printing / exporting data then do pagination.
            if (! $this->returnBuilder) {
                // Get the take args
                $take = $perPage;
                OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                    if ($_take > $maxRecord) {
                        $_take = $maxRecord;
                    }
                    $take = $_take;

                    if ((int)$take <= 0) {
                        $take = $maxRecord;
                    }
                });
                $partners->take($take);

                $skip = 0;
                OrbitInput::get('skip', function ($_skip) use (&$skip, $partners) {
                    if ($_skip < 0) {
                        $_skip = 0;
                    }

                    $skip = $_skip;
                });
                $partners->skip($skip);
            }

            // Default sort by
            $sortBy = 'partners.partner_name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'partner_name' => 'partners.partner_name',
                    'location'     => 'location',
                    'start_date'   => 'partners.start_date',
                    'end_Date'     => 'partners.end_date',
                    'url'          => 'partners.url',
                    'status'       => 'partners.status',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $partners->orderBy($sortBy, $sortMode);

            // Return the instance of Query Builder
            if ($this->returnBuilder) {
                return ['builder' => $partners, 'count' => RecordCounter::create($_partners)->count()];
            }

            $totalRec = RecordCounter::create($_partners)->count();
            $listOfRec = $partners->get();

            $data = new stdclass();
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            if ($totalRec === 0) {
                $data->records = null;
                $this->response->message = Lang::get('statuses.orbit.nodata.partner');
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.partner.getsearchpartner.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.partner.getsearchpartner.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.partner.getsearchpartner.query.error', array($this, $e));

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
            Event::fire('orbit.partner.getsearchpartner.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
        }
        $output = $this->render($httpCode);
        Event::fire('orbit.partner.getsearchpartner.before.render', array($this, &$output));

        return $output;
    }

    public function setReturnBuilder($bool)
    {
        $this->returnBuilder = $bool;

        return $this;
    }

    protected function registerCustomValidation()
    {

        // Validate the time format for over 23 hour
        Validator::extend('orbit.empty.hour_format', function ($attribute, $value, $parameters) {
            // explode the format Y-m-d H:i:s
            $dateTimeExplode = explode(' ', $value);
            // explode the format H:i:s
            $timeExplode = explode(':', $dateTimeExplode[1]);
            // get the Hour format
            if($timeExplode[0] > 23){
                return false;
            }

            return true;
        });
    }
}