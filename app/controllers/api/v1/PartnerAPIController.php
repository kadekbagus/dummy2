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
}