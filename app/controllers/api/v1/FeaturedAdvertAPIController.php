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

class FeaturedAdvertAPIController extends ControllerAPI
{
    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];
    /**
     * GET - featured advert list
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
    public function getFeaturedList()
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

            $featuredLocation = OrbitInput::get('featured_location');
            $section = OrbitInput::get('section');

            $validator = Validator::make(
                array(
                    'featured_location' => $featuredLocation,
                    'section'           => $section,
                ),
                array(
                    'featured_location' => 'required',
                    'section'           => 'required'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            // Get the maximum record
            $maxRecord = (int) Config::get('orbit.pagination.mall_country.max_record');
            if ($maxRecord <= 0) {
                // Fallback
                $maxRecord = (int) Config::get('orbit.pagination.max_record');
                if ($maxRecord <= 0) {
                    $maxRecord = 20;
                }
            }
            // Get default per page (take)
            $perPage = (int) Config::get('orbit.pagination.mall_country.per_page');
            if ($perPage <= 0) {
                // Fallback
                $perPage = (int) Config::get('orbit.pagination.per_page');
                if ($perPage <= 0) {
                    $perPage = 20;
                }
            }

            if ($section === 'event') {
                $section = 'news';
            }

            $timezone = 'Asia/Jakarta'; // now with jakarta timezone
            $timestamp = date("Y-m-d H:i:s");
            $date = Carbon::createFromFormat('Y-m-d H:i:s', $timestamp, 'UTC');
            $dateNow = $date->setTimezone('Asia/Jakarta')->toDateTimeString();

            $advert = Advert::select('adverts.advert_id', 'adverts.advert_name')
                            ->join('advert_link_types', 'advert_link_types.advert_link_type_id', '=', 'adverts.advert_link_type_id')
                            ->join('advert_placements', 'advert_placements.advert_placement_id', '=', 'adverts.advert_placement_id')
                            ->leftJoin('advert_locations', 'advert_locations.advert_id', '=', 'adverts.advert_id')
                            ->where('adverts.status', 'active')
                            ->where('adverts.start_date', '<=', $dateNow)
                            ->where('adverts.end_date', '>=', $dateNow)
                            ->where('advert_placements.placement_type', 'featured_list')
                            ->where('advert_link_types.advert_type', $section)
                            ->where('advert_locations.location_id', $featuredLocation)
                            ->groupBy('adverts.advert_id');

            // Filter advert by name
            OrbitInput::get('name_like', function ($nameLike) use ($advert) {
                $advert->where('adverts.advert_name', 'like', "%$nameLike%");
            });

            // Filter advert by advert_id
            OrbitInput::get('advert_id', function ($advertId) use ($advert) {
                $advert->where('adverts.advert_id', $advertId);
            });

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_advert = clone $advert;

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
            $advert->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $advert->skip($skip);

            // Default sort by
            $sortBy = 'adverts.advert_name';
            // Default sort mode
            $sortMode = 'asc';
            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'name' => 'adverts.advert_name'
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $advert->orderBy($sortBy, $sortMode);

            $totalAdvert = RecordCounter::create($_advert)->count();
            $listAdvert = $advert->get();

            $data = new stdclass();
            $data->total_records = $totalAdvert;
            $data->returned_records = count($listAdvert);
            $data->records = $listAdvert;

            $this->response->data = $data;
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
}