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
    protected $returnBuilder = FALSE;

	/**
     * POST - Create New Partner
     *
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param char      `link_object_id`        (optional) - Object type. Valid value: promotion, advert.
     * @param char      `advert_link_id`        (required) - Advert link to
     * @param string    `advert_placement_id`   (required) - Status. Valid value: active, inactive, deleted.
     * @param string    `advert_name`           (optional) - name of advert
     * @param string    `link_url`              (optional) - Can be empty
     * @param datetime  `start_date`            (optional) - Start date
     * @param datetime  `end_date`              (optional) - End date
     * @param string    `notes`                 (optional) - Description
     * @param string    `status`                (optional) - active, inactive, deleted
     * @param array     `locations`             (optional) - Location of multiple mall or gtm
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function postNewPartner()
    {

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

            $partners = Partner::excludeDeleted('partners')
                        ->select(
                            'partners.partner_id',
                            'partners.partner_name',
                            DB::raw("concat({$prefix}partners.city, ', ', {$prefix}countries.name) as location"),
                            'partners.start_date',
                            'partners.end_date',
                            'partners.url',
                            'partners.status',
                            DB::raw('logo.path as logo'), // logo
                            DB::raw('info_image.path as info_image'), // info page image
                            'partners.description',
                            'partners.address',
                            'partners.city',
                            'partners.province',
                            'partners.postal_code',
                            'partners.country_id',
                            'partners.phone',
                            DB::raw('fb_url.social_media_uri as facebook_url'), // facebook url
                            'deeplinks.deeplink_url', // deeplink url
                            'partners.note',
                            'partners.contact_firstname',
                            'partners.contact_lastname',
                            'partners.contact_position',
                            'partners.contact_phone',
                            'partners.contact_email'
                        )
                        ->leftJoin('countries', 'countries.country_id', '=', 'partners.country_id')
                        ->leftJoin('media as logo', function($qLogo) {
                            $qLogo->on(DB::raw('logo.object_id'), '=', 'partners.partner_id')
                                ->on(DB::raw('logo.object_name'), '=', DB::raw("'partner'"))
                                ->on(DB::raw('logo.media_name_id'), '=', DB::raw("'partner_logo'"))
                                ->on(DB::raw('logo.media_name_long'), '=', DB::raw("'partner_logo_orig'"));
                        })
                        ->leftJoin('media as info_image', function($qInfoImage) {
                            $qInfoImage->on(DB::raw('info_image.object_id'), '=', 'partners.partner_id')
                                ->on(DB::raw('info_image.object_name'), '=', DB::raw("'partner'"))
                                ->on(DB::raw('info_image.media_name_id'), '=', DB::raw("'partner_image'"))
                                ->on(DB::raw('info_image.media_name_long'), '=', DB::raw("'partner_image_orig'"));
                        })
                        ->leftJoin('deeplinks', function($qDeepLink) {
                            $qDeepLink->on('deeplinks.object_id', '=', 'partners.partner_id')
                                ->on('deeplinks.object_type', '=', DB::raw("'partner'"))
                                ->on('deeplinks.status', '=', DB::raw("'active'"));
                        })
                        ->leftJoin('object_social_media as fb_url', function($qDeepLink) use ($prefix) {
                            $qDeepLink->on(DB::raw('fb_url.object_id'), '=', 'partners.partner_id')
                                ->on(DB::raw('fb_url.object_type'), '=', DB::raw("'partner'"))
                                ->on(DB::raw('fb_url.social_media_id'), '=', DB::raw("(
                                        SELECT sm.social_media_id
                                        FROM {$prefix}social_media as sm
                                        WHERE sm.social_media_code = 'facebook'
                                    )"));
                        });

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
}