<?php namespace Orbit\Controller\API\v1\Pub\Partner;

/**
 * @author Ahmad <ahmad@dominopos.com>
 * @desc Controller for Partner list and search in landing page
 */

use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use Helper\EloquentRecordCounter as RecordCounter;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use \Config;
use \Exception;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use \DB;
use Partner;
use Orbit\Controller\API\v1\Pub\Partner\PartnerHelper;
use Validator;
use Orbit\Helper\Util\PaginationNumber;
use Orbit\Helper\Database\Cache as OrbitDBCache;

class PartnerListAPIController extends PubControllerAPI
{
    protected $valid_language = NULL;
    /**
     * GET - get partner list, and also provide for searching
     *
     * @author Ahmad <ahmad@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string sortby
     * @param string sortmode
     * @param string take
     * @param string skip
     * @param string keyword
     * @param string filter_name
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchPartner()
    {
        $httpCode = 200;
        $keyword = null;
        $user = null;

        try{
            $user = $this->getUser();

            $sort_by = OrbitInput::get('sortby', 'name');
            $sort_mode = OrbitInput::get('sortmode','asc');
            $language = OrbitInput::get('language', 'id');
            $no_total_records = OrbitInput::get('no_total_records', null);

            // search by key word or filter or sort by flag
            $searchFlag = FALSE;

            $partnerHelper = PartnerHelper::create();
            $partnerHelper->registerCustomValidation();
            $validator = Validator::make(
                array(
                    'language' => $language,
                    'sortby'   => $sort_by,
                ),
                array(
                    'language' => 'required|orbit.empty.language_default',
                    'sortby'   => 'in:name,created_date',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $valid_language = $partnerHelper->getValidLanguage();
            $prefix = DB::getTablePrefix();

            $partners = Partner::select(
                    'partner_id',
                    'partner_name',
                    'description',
                    'is_shown_in_filter',
                    'path as logo_url'
                )
                ->leftJoin('media', function ($q) {
                    $q->on('media.object_id', '=', 'partners.partner_id');
                    $q->on('media.media_name_long', '=', DB::raw("'partner_logo_orig'"));
                })
                ->where('partners.status', 'active')
                ->where('partners.is_visible', 'y');

            OrbitInput::get('shown_in_filter', function($shown_in_filter) use ($partners)
            {
                $shown_in_filter = ($shown_in_filter === 'yes' ? 'Y' : 'N');
                $partners->where('partners.is_shown_in_filter', $shown_in_filter);
            });

            // Map the sortby request to the real column name
            $sortByMapping = array(
                'name'          => 'partner_name',
                'created_date'  => 'created_at'
            );

            $sort_by = $sortByMapping[$sort_by];

            OrbitInput::get('sortmode', function($_sortMode) use (&$sort_mode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sort_mode = 'desc';
                }
            });

            OrbitInput::get('sortmode', function($_sortMode) use (&$sort_mode)
            {
                if (strtolower($_sortMode) !== 'asc') {
                    $sort_mode = 'desc';
                }
            });

            $partners->orderBy($sort_by, $sort_mode);

            $totalRec = 0;
            // Set defaul 0 when get variable no_total_records = yes
            if ($no_total_records !== 'yes') {
                $_partners = clone($partners);

                $recordCounter = RecordCounter::create($_partners);
                OrbitDBCache::create(Config::get('orbit.cache.database', []))->remember($recordCounter->getQueryBuilder());

                $totalRec = $recordCounter->count();
            }

            // Cache the result of database calls
            OrbitDBCache::create(Config::get('orbit.cache.database', []))->remember($partners);

            $take = PaginationNumber::parseTakeFromGet('partner');
            $partners->take($take);

            $skip = PaginationNumber::parseSkipFromGet();
            $partners->skip($skip);

            $listOfRec = $partners->get();

            $data = new \stdclass();
            $data->returned_records = count($listOfRec);
            $data->total_records = $totalRec;

            $data->records = $listOfRec;

            $this->response->data = $data;
            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->message = 'Request Ok';

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

        return $this->render($httpCode);
    }

    protected function quote($arg)
    {
        return DB::connection()->getPdo()->quote($arg);
    }
}
