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
use DominoPOS\OrbitUploader\Uploader as OrbitUploader;

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
     * @param char      `is_visible`            (optional) - visible on list gtm or not, default Y
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
            $url = OrbitInput::post('url');
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
            $is_visible = OrbitInput::post('is_visible', 'Y');
            $deeplink_url = OrbitInput::post('deeplink_url');
            $social_media_uri = OrbitInput::post('social_media_uri');
            $social_media_type = OrbitInput::post('social_media_type', 'facebook');
            $logo = OrbitInput::files('logo');
            $image = OrbitInput::files('image');

            $affected_group_name_id = OrbitInput::post('affected_group_name_id');

            if (is_array($affected_group_name_id)) {
                $affected_group_name_validation = $this->generate_validation_affected_group_name($affected_group_name_id);
            }

            // generate array validation image
            $logo_validation = $this->generate_validation_image('partner_logo', $logo, 'orbit.upload.partner.logo');
            $image_validation = $this->generate_validation_image('info_image_page', $image, 'orbit.upload.partner.image');

            $validation_data = [
                'partner_name'           => $partner_name,
                'start_date'             => $start_date,
                'end_date'               => $end_date,
                'status'                 => $status,
                'address'                => $address,
                'city'                   => $city,
                'country_id'             => $country_id,
                'phone'                  => $phone,
                'contact_firstname'      => $contact_firstname,
                'contact_lastname'       => $contact_lastname,
                'affected_group_name_id' => $affected_group_name_id,
            ];

            $validation_error = [
                'partner_name'           => 'required',
                'start_date'             => 'date|orbit.empty.hour_format',
                'end_date'               => 'date|orbit.empty.hour_format',
                'status'                 => 'required|in:active,inactive',
                'address'                => 'required',
                'city'                   => 'required',
                'country_id'             => 'required',
                'phone'                  => 'required',
                'contact_firstname'      => 'required',
                'contact_lastname'       => 'required',
                'affected_group_name_id' => 'array',
            ];

            $validation_error_message = [];

            // add validation image
            if (! empty($logo_validation)) {
                $validation_data += $logo_validation['data'];
                $validation_error += $logo_validation['error'];
                $validation_error_message += $logo_validation['error_message'];
            }

            if (! empty($image_validation)) {
                $validation_data += $image_validation['data'];
                $validation_error += $image_validation['error'];
                $validation_error_message += $image_validation['error_message'];
            }

            if (! empty($affected_group_name_validation)) {
                $validation_data += $affected_group_name_validation['data'];
                $validation_error += $affected_group_name_validation['error'];
            }

            $validator = Validator::make(
                $validation_data,
                $validation_error,
                $validation_error_message
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
            $newPartner->url = $url;
            $newPartner->note = $note;
            $newPartner->contact_firstname = $contact_firstname;
            $newPartner->contact_lastname = $contact_lastname;
            $newPartner->contact_position = $contact_position;
            $newPartner->contact_phone = $contact_phone;
            $newPartner->contact_email = $contact_email;
            $newPartner->start_date = (is_null($start_date)) ? '0000-00-00 00:00:00' : $start_date;
            $newPartner->end_date = (is_null($end_date)) ? '0000-00-00 00:00:00' : $end_date;
            $newPartner->status = $status;
            $newPartner->is_shown_in_filter = $is_shown_in_filter;
            $newPartner->is_visible = $is_visible;

            Event::fire('orbit.partner.postnewpartner.before.save', array($this, $newPartner));

            $newPartner->save();

            if (!empty($deeplink_url) ) {
                $newDeepLink = new DeepLink();
                $newDeepLink->object_id = $newPartner->partner_id;
                $newDeepLink->object_type = 'partner';
                $newDeepLink->deeplink_url = $deeplink_url;
                $newDeepLink->status = 'active';
                $newDeepLink->save();

                $newPartner->deeplink = $newDeepLink;
            }

            if (!empty($social_media_uri)) {
                $sosmed = SocialMedia::where('social_media_code', '=', $social_media_type)->first();

                $newObjectSocialMedia = new ObjectSocialMedia();
                $newObjectSocialMedia->object_id = $newPartner->partner_id;
                $newObjectSocialMedia->object_type = 'partner';
                $newObjectSocialMedia->social_media_id = $sosmed->social_media_id;
                $newObjectSocialMedia->social_media_uri = $social_media_uri;
                $newObjectSocialMedia->save();

                $newPartner->social_media = $newObjectSocialMedia;
            }

            if (is_array($affected_group_name_id)) {
                foreach ($affected_group_name_id as $idx => $group_name_id) {
                    $newPartnerAffectedGroup = new PartnerAffectedGroup();
                    $newPartnerAffectedGroup->affected_group_name_id = $group_name_id;
                    $newPartnerAffectedGroup->partner_id = $newPartner->partner_id;
                    $newPartnerAffectedGroup->save();
                }
            }

            Event::fire('orbit.partner.postnewpartner.after.save', array($this, $newPartner));

            Event::fire('orbit.partner.postnewpartner.after.save2', array($this, $newPartner));

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
     * POST - Update Partner
     *
     * @author firmansyah <firmansyah@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string    `partner_id`            (optional) - Id Partner
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
     * @param char      `is_visible`            (optional) - visible on list gtm or not, default Y
     *
     * @return Illuminate\Support\Facades\Response
     */

    public function postUpdatePartner()
    {
        $activity = Activity::portal()
                           ->setActivityType('update');

        $user = NULL;
        $updatedpartner = NULL;
        try {
            $httpCode=200;

            Event::fire('orbit.partner.postupdatepartner.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.partner.postupdatepartner.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.partner.postupdatepartner.before.authz', array($this, $user));

            // @Todo: Use ACL authentication instead
            $role = $user->role;
            $validRoles = $this->modifyPartnerRoles;
            if (! in_array( strtolower($role->role_name), $validRoles)) {
                $message = 'Your role are not allowed to access this resource.';
                ACL::throwAccessForbidden($message);
            }

            Event::fire('orbit.partner.postupdatepartner.after.authz', array($this, $user));

            $this->registerCustomValidation();

            $partner_id = OrbitInput::post('partner_id');
            $partner_name = OrbitInput::post('partner_name');
            $start_date = OrbitInput::post('start_date');
            $end_date = OrbitInput::post('end_date');
            $status = OrbitInput::post('status');
            $address = OrbitInput::post('address');
            $city = OrbitInput::post('city');
            $country_id = OrbitInput::post('country_id');
            $phone = OrbitInput::post('phone');
            $contact_firstname = OrbitInput::post('contact_firstname');
            $contact_lastname = OrbitInput::post('contact_lastname');
            $social_media_uri = OrbitInput::post('social_media_uri');
            $social_media_type = OrbitInput::post('social_media_type', 'facebook');
            $logo = OrbitInput::files('logo');
            $image = OrbitInput::files('image');

            $affected_group_name_id = OrbitInput::post('affected_group_name_id');

            if (is_array($affected_group_name_id)) {
                $affected_group_name_validation = $this->generate_validation_affected_group_name($affected_group_name_id);
            }

            // generate array validation image
            $logo_validation = $this->generate_validation_image('partner_logo', $logo, 'orbit.upload.partner.logo');
            $image_validation = $this->generate_validation_image('info_image_page', $image, 'orbit.upload.partner.image');

            $validation_data = [
                'partner_name'           => $partner_name,
                'partner_id'             => $partner_id,
                'start_date'             => $start_date,
                'end_date'               => $end_date,
                'status'                 => $status,
                'address'                => $address,
                'city'                   => $city,
                'country_id'             => $country_id,
                'phone'                  => $phone,
                'contact_firstname'      => $contact_firstname,
                'contact_lastname'       => $contact_lastname,
                'affected_group_name_id' => $affected_group_name_id,
            ];

            $validation_error = [
                'partner_name'           => 'required',
                'partner_id'             => 'required',
                'start_date'             => 'date|orbit.empty.hour_format',
                'end_date'               => 'date|orbit.empty.hour_format',
                'status'                 => 'required|in:active,inactive',
                'address'                => 'required',
                'city'                   => 'required',
                'country_id'             => 'required',
                'phone'                  => 'required',
                'contact_firstname'      => 'required',
                'contact_lastname'       => 'required',
                'affected_group_name_id' => 'array',
            ];

            $validation_error_message = [];

            // add validation image
            if (! empty($logo_validation)) {
                $validation_data += $logo_validation['data'];
                $validation_error += $logo_validation['error'];
                $validation_error_message += $logo_validation['error_message'];
            }

            if (! empty($image_validation)) {
                $validation_data += $image_validation['data'];
                $validation_error += $image_validation['error'];
                $validation_error_message += $image_validation['error_message'];
            }

            if (! empty($affected_group_name_validation)) {
                $validation_data += $affected_group_name_validation['data'];
                $validation_error += $affected_group_name_validation['error'];
            }

            $validator = Validator::make(
                $validation_data,
                $validation_error,
                $validation_error_message
            );

            Event::fire('orbit.partner.postupdatepartner.before.validation', array($this, $validator));

            // Begin database transaction
            $this->beginTransaction();

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.partner.postupdatepartner.after.validation', array($this, $validator));

            $prefix = DB::getTablePrefix();

            $updatedpartner = Partner::excludeDeleted()->where('partner_id', $partner_id)->first();

            // update Partner
            OrbitInput::post('partner_name', function($partner_name) use ($updatedpartner) {
                $updatedpartner->partner_name = $partner_name;
            });

            OrbitInput::post('description', function($description) use ($updatedpartner) {
                $updatedpartner->description = $description;
            });

            OrbitInput::post('address', function($address) use ($updatedpartner) {
                $updatedpartner->address = $address;
            });

            OrbitInput::post('city', function($city) use ($updatedpartner) {
                $updatedpartner->city = $city;
            });

            OrbitInput::post('province', function($province) use ($updatedpartner) {
                $updatedpartner->province = $province;
            });

            OrbitInput::post('postal_code', function($postal_code) use ($updatedpartner) {
                $updatedpartner->postal_code = $postal_code;
            });

            OrbitInput::post('country_id', function($country_id) use ($updatedpartner) {
                $updatedpartner->country_id = $country_id;
            });

            OrbitInput::post('phone', function($phone) use ($updatedpartner) {
                $updatedpartner->phone = $phone;
            });

            OrbitInput::post('url', function($url) use ($updatedpartner) {
                $updatedpartner->url = $url;
            });

            OrbitInput::post('note', function($note) use ($updatedpartner) {
                $updatedpartner->note = $note;
            });

            OrbitInput::post('contact_firstname', function($contact_firstname) use ($updatedpartner) {
                $updatedpartner->contact_firstname = $contact_firstname;
            });

            OrbitInput::post('contact_lastname', function($contact_lastname) use ($updatedpartner) {
                $updatedpartner->contact_lastname = $contact_lastname;
            });

            OrbitInput::post('contact_position', function($contact_position) use ($updatedpartner) {
                $updatedpartner->contact_position = $contact_position;
            });

            OrbitInput::post('contact_phone', function($contact_phone) use ($updatedpartner) {
                $updatedpartner->contact_phone = $contact_phone;
            });

            OrbitInput::post('contact_email', function($contact_email) use ($updatedpartner) {
                $updatedpartner->contact_email = $contact_email;
            });

            OrbitInput::post('start_date', function($start_date) use ($updatedpartner) {
                $updatedpartner->start_date = $start_date;
            });

            OrbitInput::post('end_date', function($end_date) use ($updatedpartner) {
                $updatedpartner->end_date = $end_date;
            });

            OrbitInput::post('status', function($status) use ($updatedpartner) {
                $updatedpartner->status = $status;
            });

            OrbitInput::post('is_shown_in_filter', function($is_shown_in_filter) use ($updatedpartner) {
                $updatedpartner->is_shown_in_filter = $is_shown_in_filter;
            });

            OrbitInput::post('is_visible', function($is_visible) use ($updatedpartner) {
                $updatedpartner->is_visible = $is_visible;
            });

            OrbitInput::post('deeplink_url', function($deeplink_url) use ($updatedpartner, $partner_id) {
                // Check update when exist and insert if not exist
                $deepLink = DeepLink::where('object_id', $partner_id)
                                ->where('object_type', 'partner')
                                ->where('status', 'active')
                                ->first();

                if (! empty($deepLink)) {
                    $deepLink->deeplink_url = $deeplink_url;
                    $deepLink->save();
                } else {
                    $deepLink = new DeepLink();
                    $deepLink->object_id = $partner_id;
                    $deepLink->object_type = 'partner';
                    $deepLink->deeplink_url = $deeplink_url;
                    $deepLink->status = 'active';
                    $deepLink->save();
                }
            });

            OrbitInput::post('social_media_uri', function($social_media_uri) use ($updatedpartner, $partner_id, $social_media_uri, $social_media_type) {
                // Check update when exist and insert if not exist
                $socialMedia = ObjectSocialMedia::where('object_id', $partner_id)
                                ->where('object_type', 'partner')
                                ->first();

                 if (! empty($socialMedia)) {
                    $socialMedia->social_media_uri = $social_media_uri;
                    $socialMedia->save();
                } else {
                    $sosmed = SocialMedia::where('social_media_code', '=', $social_media_type)->first();

                    $socialMedia = new ObjectSocialMedia();
                    $socialMedia->object_id = $partner_id;
                    $socialMedia->object_type = 'partner';
                    $socialMedia->social_media_id = $sosmed->social_media_id;
                    $socialMedia->social_media_uri = $social_media_uri;
                    $socialMedia->save();
                }
            });

            OrbitInput::post('affected_group_name_id', function($affected_group_name_id) use ($updatedpartner) {
                // del partner affected group
                $del_partner_affected_group = PartnerAffectedGroup::where('partner_id', $updatedpartner->partner_id)->delete();

                if (is_array($affected_group_name_id)) {
                    foreach ($affected_group_name_id as $idx => $group_name_id) {
                        if ($group_name_id !== '') {
                            $newPartnerAffectedGroup = new PartnerAffectedGroup();
                            $newPartnerAffectedGroup->affected_group_name_id = $group_name_id;
                            $newPartnerAffectedGroup->partner_id = $updatedpartner->partner_id;
                            $newPartnerAffectedGroup->save();
                        }
                    }
                }
            });

            $updatedpartner->save();

            Event::fire('orbit.partner.postupdatepartner.after.save', array($this, $updatedpartner));
            Event::fire('orbit.partner.postupdatepartner.after.save2', array($this, $updatedpartner));

            $this->response->data = $updatedpartner;


            // Commit the changes
            $this->commit();

            // Successfull Update
            $activityNotes = sprintf('Partner updated: %s', $updatedpartner->partner_name);
            $activity->setUser($user)
                    ->setActivityName('update_partner')
                    ->setActivityNameLong('Update Partner OK')
                    ->setObject($updatedpartner)
                    ->setNotes($activityNotes)
                    ->responseOK();

            Event::fire('orbit.partner.postupdatepartner.after.commit', array($this, $updatedpartner));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.partner.postupdatepartner.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_partner')
                    ->setActivityNameLong('Update Partner Failed')
                    ->setObject($updatedpartner)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.partner.postupdatepartner.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_partner')
                    ->setActivityNameLong('Update Partner Failed')
                    ->setObject($updatedpartner)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (QueryException $e) {
            Event::fire('orbit.partner.postupdatepartner.query.error', array($this, $e));

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

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_partner')
                    ->setActivityNameLong('Update Partner Failed')
                    ->setObject($updatedpartner)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        } catch (Exception $e) {
            Event::fire('orbit.partner.postupdatepartner.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = [$e->getMessage(), $e->getFile(), $e->getLine()];

            // Rollback the changes
            $this->rollBack();

            // Failed Update
            $activity->setUser($user)
                    ->setActivityName('update_partner')
                    ->setActivityNameLong('Update Partner Failed')
                    ->setObject($updatedpartner)
                    ->setNotes($e->getMessage())
                    ->responseFailed();
        }

        // Save activity
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
                    'sort_by' => 'in:partner_name,location,start_date,end_date,url,status',
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
                            'partners.description',
                            'partners.address',
                            'partners.city',
                            'partners.province',
                            'partners.postal_code',
                            'partners.country_id',
                            'countries.name as country',
                            'partners.phone',
                            DB::raw('fb_url.social_media_uri'), // facebook url
                            'deeplinks.deeplink_url', // deeplink url
                            'partners.note',
                            'partners.contact_firstname',
                            'partners.contact_lastname',
                            'partners.contact_position',
                            'partners.contact_phone',
                            'partners.contact_email',
                            'partners.is_visible',
                            'partners.is_shown_in_filter'
                        )
                        ->leftJoin('countries', 'countries.country_id', '=', 'partners.country_id')
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
            OrbitInput::get('with', function ($with) use ($partners, $prefix) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'mediaLogoOrig') {
                        $partners->with([$relation => function ($qLogo) {
                            $qLogo->select(
                                    'object_id',
                                    'file_name',
                                    'path',
                                    'metadata'
                                );
                            }]);
                    } else if ($relation === 'mediaImageOrig') {
                        $partners->with([$relation => function ($qImage) {
                            $qImage->select(
                                    'object_id',
                                    'file_name',
                                    'path',
                                    'metadata'
                                );
                            }]);
                    } else if ($relation === 'partnerAffectedGroup') {
                        $partners->with([$relation => function ($qGroupName) use ($prefix) {
                            $qGroupName->select(
                                    'partner_affected_group.partner_id',
                                    'partner_affected_group.affected_group_name_id',
                                    'group_name',
                                    DB::Raw("count({$prefix}object_partner.object_type) + count({$prefix}base_object_partner.object_type) as item_count")
                                )
                                ->join('affected_group_names', 'affected_group_names.affected_group_name_id', '=', 'partner_affected_group.affected_group_name_id')
                                ->leftJoin('object_partner', function ($qJoin) use ($prefix) {
                                    $qJoin->on('object_partner.partner_id', '=', 'partner_affected_group.partner_id')
                                        ->on('object_partner.object_type', '=', DB::raw("{$prefix}affected_group_names.group_type"));
                                })
                                ->leftJoin('base_object_partner', function ($qJoin) use ($prefix) {
                                    $qJoin->on('base_object_partner.partner_id', '=', 'partner_affected_group.partner_id')
                                        ->on('base_object_partner.object_type', '=', DB::raw("{$prefix}affected_group_names.group_type"));
                                })
                                ->groupBy('partner_id', 'group_name');
                            }]);
                    } else {
                        $partners->with($relation);
                    }
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
                    'end_date'     => 'partners.end_date',
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

        Validator::extend('orbit.file.max_size', function ($attribute, $value, $parameters) {
            $config_size = $parameters[0];
            $file_size = $value;

            if ($file_size > $config_size) {
                return false;
            }

            return true;
        });

        // Check the images, we are allowed array of images but not more than
        Validator::extend('nomore.than', function ($attribute, $value, $parameters) {
            $max_count = $parameters[0];

            if (is_array($value['name']) && count($value['name']) > $max_count) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the affected group name is exists
        Validator::extend('orbit.empty.affected_group_name', function ($attribute, $value, $parameters) {
            $affected_group_name_id = $value;

            $affected_group_name = AffectedGroupName::excludeDeleted()
                                    ->where('affected_group_name_id', $affected_group_name_id)
                                    ->first();

            if (empty($affected_group_name)) {
                return FALSE;
            }

            return TRUE;
        });
    }

    protected function generate_validation_image($image_name, $images, $config, $max_count = 1) {
        $validation = [];
        if (! empty($images)) {
            $images_properties = OrbitUploader::simplifyFilesVar($images);
            $image_config = Config::get($config);
            $image_type =  "image/" . implode(",image/", $image_config['file_type']);
            $image_units = OrbitUploader::bytesToUnits($image_config['file_size']);

            $validation['data'] = [
                $image_name => $images
            ];
            $validation['error'] = [
                $image_name => 'nomore.than:' . $max_count
            ];
            $validation['error_message'] = [
                $image_name . '.nomore.than' => Lang::get('validation.max.array', array('max' => $max_count))
            ];

            foreach ($images_properties as $idx => $image) {
                $ext = strtolower(substr(strrchr($image->name, '.'), 1));
                $idx+=1;

                $validation['data'][$image_name . '_' . $idx . '_type'] = $image->type;
                $validation['data'][$image_name . '_' . $idx . '_size'] = $image->size;

                $validation['error'][$image_name . '_' . $idx . '_type'] = 'in:' . $image_type;
                $validation['error'][$image_name . '_' . $idx . '_size'] = 'orbit.file.max_size:' . $image_config['file_size'];

                $validation['error_message'][$image_name . '_' . $idx . '_type' . '.in'] = Lang::get('validation.orbit.file.type', array('ext' => $ext));
                $validation['error_message'][$image_name . '_' . $idx . '_size' . '.orbit.file.max_size'] = ($max_count > 1) ? Lang::get('validation.orbit.file.max_size', array('size' => $image_units['newsize'], 'unit' => $image_units['unit'])) : Lang::get('validation.orbit.file.max_size_one', array('name' => ucfirst(str_replace('_', ' ', $image_name)), 'size' => $image_units['newsize'], 'unit' => $image_units['unit']));
            }
        }

        return $validation;
    }

    protected function generate_validation_affected_group_name($group_names) {
        $validation = [];

        foreach ($group_names as $idx => $group_name) {
            $idx+=1;

            $validation['data'][$group_name] = $group_name;
            $validation['error'][$group_name] = 'orbit.empty.affected_group_name';
        }

        return $validation;
    }
}