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
    protected $viewPartnerRoles = ['super admin', 'mall admin', 'mall owner', 'article writer', 'article publisher'];
    protected $modifyPartnerRoles = ['super admin', 'mall admin', 'mall owner'];
    protected $returnBuilder = FALSE;
    protected $defaultLanguage = 'en';

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
            $social_media = OrbitInput::post('social_media', '');
            $logo = OrbitInput::files('logo');
            $image = OrbitInput::files('image');
            $is_exclusive = OrbitInput::post('is_exclusive', 'N');
            $pop_up_content = OrbitInput::post('pop_up_content');
            $token = OrbitInput::post('token');
            $translations = OrbitInput::post('translations');
            $supported_languages = OrbitInput::post('supported_languages', []);
            $mobile_default_language = OrbitInput::post('mobile_default_language');
            $partner_categories = OrbitInput::post('categories', []);
            $meta_title = OrbitInput::post('meta_title');
            $meta_description = OrbitInput::post('meta_description');
            $working_hours = OrbitInput::post('working_hours');
            $custom_photo_section_title = OrbitInput::post('custom_photo_section_title');
            $button_color = OrbitInput::post('button_color');
            $button_text_color = OrbitInput::post('button_text_color');
            $video_id_1 = OrbitInput::post('video_id_1');
            $video_id_2 = OrbitInput::post('video_id_2');
            $video_id_3 = OrbitInput::post('video_id_3');
            $video_id_4 = OrbitInput::post('video_id_4');
            $video_id_5 = OrbitInput::post('video_id_5');
            $video_id_6 = OrbitInput::post('video_id_6');
            $banners = OrbitInput::post('banners', '');

            $affected_group_name_id = OrbitInput::post('affected_group_name_id');

            if (is_array($affected_group_name_id)) {
                $affected_group_name_validation = $this->generate_validation_affected_group_name($affected_group_name_id);
            }

            // generate array validation image
            $logo_validation = $this->generate_validation_image('partner_logo', $logo, 'orbit.upload.partner.logo');
            $image_validation = $this->generate_validation_image('info_image_page', $image, 'orbit.upload.partner.image');

            $validation_data = [
                'partner_name'            => $partner_name,
                'start_date'              => $start_date,
                'end_date'                => $end_date,
                'status'                  => $status,
                'country_id'              => $country_id,
                'contact_firstname'       => $contact_firstname,
                'contact_lastname'        => $contact_lastname,
                'affected_group_name_id'  => $affected_group_name_id,
                'is_exclusive'            => $is_exclusive,
                'supported_languages'     => $supported_languages,
                'mobile_default_language' => $mobile_default_language,
                'token'                   => $token,
            ];

            $validation_error = [
                'partner_name'            => 'required',
                'start_date'              => 'date|orbit.empty.hour_format',
                'end_date'                => 'date|orbit.empty.hour_format',
                'status'                  => 'required|in:active,inactive',
                'country_id'              => 'required',
                'contact_firstname'       => 'required',
                'contact_lastname'        => 'required',
                'affected_group_name_id'  => 'array',
                'is_exclusive'            => 'in:Y,N',
                'supported_languages'     => 'required|array|orbit.empty.language',
                'mobile_default_language' => 'required|orbit.empty.mobile_default_lang:' . implode(',', $supported_languages) . '|orbit.empty.language_default',
                'token'                   => 'orbit.duplicate.token:' . $is_exclusive,
            ];

            $validation_error_message = [
                'orbit.duplicate.token' => 'Token is already used by another partner'
            ];

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

            if (strtoupper($is_exclusive) === 'Y') {
                $validation_data += ['pop_up_content' => $pop_up_content];
                $validation_data += ['token' => $token];
                $validation_error += ['pop_up_content' => 'required'];
                $validation_error += ['token' => 'required'];
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

            // Check for english content
            $dataTranslations = @json_decode($translations);
            if (json_last_error() != JSON_ERROR_NONE) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
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
            $newPartner->is_exclusive = $is_exclusive;
            $newPartner->mobile_default_language = $this->defaultLanguage;
            $newPartner->meta_title = $meta_title;
            $newPartner->meta_description = $meta_description;
            $newPartner->working_hours = $working_hours;
            $newPartner->custom_photo_section_title = $custom_photo_section_title;
            $newPartner->button_color = $button_color;
            $newPartner->button_text_color = $button_text_color;
            $newPartner->video_id_1 = $video_id_1;
            $newPartner->video_id_2 = $video_id_2;
            $newPartner->video_id_3 = $video_id_3;
            $newPartner->video_id_4 = $video_id_4;
            $newPartner->video_id_5 = $video_id_5;
            $newPartner->video_id_6 = $video_id_6;

            if (strtoupper($is_exclusive) === 'Y') {
                $newPartner->pop_up_content = $pop_up_content;
                $newPartner->token = $token;
            }

            Event::fire('orbit.partner.postnewpartner.before.save', array($this, $newPartner));

            $newPartner->save();

            OrbitInput::post('translations', function($translation_json_string) use ($newPartner) {
                $this->validateAndSaveTranslations($newPartner, $translation_json_string, 'create');
            });

            if (! empty($deeplink_url)) {
                $newDeepLink = new DeepLink();
                $newDeepLink->object_id = $newPartner->partner_id;
                $newDeepLink->object_type = 'partner';
                $newDeepLink->deeplink_url = $deeplink_url;
                $newDeepLink->status = 'active';
                $newDeepLink->save();

                $newPartner->deeplink = $newDeepLink;
            }

            if (! empty($supported_languages)) {
                foreach ($supported_languages as $supported_language) {
                    $newSupportedLanguage = new ObjectSupportedLanguage();
                    $newSupportedLanguage->object_id = $newPartner->partner_id;
                    $newSupportedLanguage->object_type = 'partner';
                    $newSupportedLanguage->language_id = $supported_language;
                    $newSupportedLanguage->save();
                }
                $newPartner->load('supportedLanguages.language');
            }

            $social_media = json_decode($social_media, true);
            $partnerSocialMedia = [];
            if (! empty($social_media)) {
                $socialMediaList = SocialMedia::get();
                $newPartner->social_media = [];
                foreach($socialMediaList as $socialMedia) {
                    $socialMediaCode = $socialMedia->social_media_code;
                    if (isset($social_media[$socialMediaCode]) && ! empty($social_media[$socialMediaCode])) {
                        $newObjectSocialMedia = new ObjectSocialMedia();
                        $newObjectSocialMedia->object_id = $newPartner->partner_id;
                        $newObjectSocialMedia->object_type = 'partner';
                        $newObjectSocialMedia->social_media_id = $socialMedia->social_media_id;
                        $newObjectSocialMedia->social_media_uri = $social_media[$socialMediaCode];
                        $newObjectSocialMedia->save();

                        $partnerSocialMedia[$socialMediaCode] = $newObjectSocialMedia;
                    }
                }
            }

            if (is_array($affected_group_name_id)) {
                foreach ($affected_group_name_id as $idx => $group_name_id) {
                    $newPartnerAffectedGroup = new PartnerAffectedGroup();
                    $newPartnerAffectedGroup->affected_group_name_id = $group_name_id;
                    $newPartnerAffectedGroup->partner_id = $newPartner->partner_id;
                    $newPartnerAffectedGroup->save();
                }
            }

            // Attach categories to partner...
            foreach($partner_categories as $category) {
                $newPartnerCategory = new PartnerCategory;
                $newPartnerCategory->partner_id = $newPartner->partner_id;
                $newPartnerCategory->category_id = $category;
                $newPartnerCategory->save();
            }

            $banners = json_decode($banners, true);
            $partnerBannersData = [];
            if (! empty($banners)) {
                foreach($banners as $bannerIndex => $banner) {
                    $isOutbound = isset($banner['is_outbound']) && $banner['is_outbound'] === 'Y' ? 'Y' : 'N';

                    $fileInputKey = "banners_image_{$bannerIndex}";
                    $banner['banner_id'] = ! isset($banner['banner_id']) ? '' : $banner['banner_id'];
                    $event = 'orbit.partner.postupdatepartnerbanner.after.save';

                    $partnerBanner = new PartnerBanner;
                    $partnerBanner->partner_id = $newPartner->partner_id;
                    $partnerBanner->is_outbound = $isOutbound;
                    $partnerBanner->link_url = $banner['link_url'];
                    $partnerBanner->save();
                    $keptBanners[] = $partnerBanner->partner_banner_id;

                    $partnerBannersData[] = $partnerBanner;

                    if (Input::hasFile($fileInputKey)) {
                        Event::fire($event, array($this, $newPartner, $partnerBanner, $bannerIndex));
                    }
                }
            }

            Event::fire('orbit.partner.postnewpartner.after.save', array($this, $newPartner));

            Event::fire('orbit.partner.postnewpartner.after.save2', array($this, $newPartner));

            $newPartner->partner_banners = $partnerBannersData;
            $newPartner->social_media = $partnerSocialMedia;
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
            $jsonBanners = OrbitInput::post('banners', '');

            $affected_group_name_id = OrbitInput::post('affected_group_name_id');

            $is_exclusive = OrbitInput::post('is_exclusive', 'N');
            $token = OrbitInput::post('token');
            $pop_up_content = OrbitInput::post('pop_up_content');
            $translations = OrbitInput::post('translations');
            $supported_languages = OrbitInput::post('supported_languages', []);
            $mobile_default_language = OrbitInput::post('mobile_default_language');

            if (is_array($affected_group_name_id)) {
                $affected_group_name_validation = $this->generate_validation_affected_group_name($affected_group_name_id);
            }

            // generate array validation image
            $logo_validation = $this->generate_validation_image('partner_logo', $logo, 'orbit.upload.partner.logo');
            $image_validation = $this->generate_validation_image('info_image_page', $image, 'orbit.upload.partner.image');

            $validation_data = [
                'partner_name'            => $partner_name,
                'partner_id'              => $partner_id,
                'start_date'              => $start_date,
                'end_date'                => $end_date,
                'status'                  => $status,
                'country_id'              => $country_id,
                'contact_firstname'       => $contact_firstname,
                'contact_lastname'        => $contact_lastname,
                'affected_group_name_id'  => $affected_group_name_id,
                'is_exclusive'            => $is_exclusive,
                'supported_languages'     => $supported_languages,
                'mobile_default_language' => $mobile_default_language,
                'token'                   => $token,
            ];

            $validation_error = [
                'partner_name'            => 'required',
                'partner_id'              => 'required',
                'start_date'              => 'date|orbit.empty.hour_format',
                'end_date'                => 'date|orbit.empty.hour_format',
                'status'                  => 'required|in:active,inactive|orbit.exists.partner_linked_to_active_campaign:' . $partner_id,
                'country_id'              => 'required',
                'contact_firstname'       => 'required',
                'contact_lastname'        => 'required',
                'affected_group_name_id'  => 'array',
                'is_exclusive'            => 'in:Y,N|orbit.empty.exclusive_campaign_link:' . $partner_id,
                'supported_languages'     => 'required|array|orbit.empty.language',
                'mobile_default_language' => 'required|orbit.empty.mobile_default_lang:' . implode(',', $supported_languages) . '|orbit.empty.language_default',
                'token'                   => 'orbit.duplicate.token:' . $is_exclusive . ',' . $partner_id,
            ];

            $validation_error_message = [
                'orbit.empty.exclusive_campaign_link' => 'Unable to uncheck Exclusive Partner. There are exclusive campaigns linked to this partner.',
                'orbit.duplicate.token' => 'Token is already used by another partner.',
                'orbit.exists.partner_linked_to_active_campaign' => 'Partner status cannot be set to inactive, because it is linked to one or more active campaigns.'
            ] ;

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

            // Check for english content
            $jsonTranslations = @json_decode($translations);
            if (json_last_error() != JSON_ERROR_NONE) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
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

            OrbitInput::post('is_exclusive', function($is_exclusive) use ($updatedpartner) {
                $updatedpartner->is_exclusive = $is_exclusive;
            });

            OrbitInput::post('token', function($token) use ($updatedpartner) {
                $updatedpartner->token = $token;
            });

            OrbitInput::post('pop_up_content', function($pop_up_content) use ($updatedpartner) {
                $updatedpartner->pop_up_content = $pop_up_content;
            });

            OrbitInput::post('categories', function($categories) use ($updatedpartner) {
                // Delete old categories...
                PartnerCategory::where('partner_id', $updatedpartner->partner_id)->delete();

                // Attach new categories...
                foreach($categories as $category) {
                    $newPartnerCategory = new PartnerCategory;
                    $newPartnerCategory->partner_id = $updatedpartner->partner_id;
                    $newPartnerCategory->category_id = $category;
                    $newPartnerCategory->save();
                }
            });

            $partnerSocialMedia = [];
            OrbitInput::post('social_media', function($social_media) use ($updatedpartner, &$partnerSocialMedia) {
                ObjectSocialMedia::where('object_id', $updatedpartner->partner_id)->where('object_type', 'partner')->delete();

                $social_media = json_decode($social_media, true);
                $socialMediaList = SocialMedia::get();
                foreach($socialMediaList as $socialMedia) {
                    $socialMediaCode = $socialMedia->social_media_code;
                    if (isset($social_media[$socialMediaCode]) && ! empty($social_media[$socialMediaCode])) {
                        $newObjectSocialMedia = new ObjectSocialMedia();
                        $newObjectSocialMedia->object_id = $updatedpartner->partner_id;
                        $newObjectSocialMedia->object_type = 'partner';
                        $newObjectSocialMedia->social_media_id = $socialMedia->social_media_id;
                        $newObjectSocialMedia->social_media_uri = $social_media[$socialMediaCode];
                        $newObjectSocialMedia->save();

                        $partnerSocialMedia[] = $newObjectSocialMedia;
                    }
                }
            });

            OrbitInput::post('meta_title', function($meta_title) use ($updatedpartner) {
                $updatedpartner->meta_title = $meta_title;
            });

            OrbitInput::post('meta_description', function($meta_description) use ($updatedpartner) {
                $updatedpartner->meta_description = $meta_description;
            });

            OrbitInput::post('custom_photo_section_title', function($custom_photo_section_title) use ($updatedpartner) {
                $updatedpartner->custom_photo_section_title = $custom_photo_section_title;
            });

            OrbitInput::post('working_hours', function($working_hours) use ($updatedpartner) {
                $updatedpartner->working_hours = $working_hours;
            });

            OrbitInput::post('button_color', function($button_color) use ($updatedpartner) {
                $updatedpartner->button_color = $button_color;
            });

            OrbitInput::post('button_text_color', function($button_text_color) use ($updatedpartner) {
                $updatedpartner->button_text_color = $button_text_color;
            });

            OrbitInput::post('video_id_1', function($video_id_1) use ($updatedpartner) {
                $updatedpartner->video_id_1 = $video_id_1;
            });

            OrbitInput::post('video_id_2', function($video_id_2) use ($updatedpartner) {
                $updatedpartner->video_id_2 = $video_id_2;
            });

            OrbitInput::post('video_id_3', function($video_id_3) use ($updatedpartner) {
                $updatedpartner->video_id_3 = $video_id_3;
            });

            OrbitInput::post('video_id_4', function($video_id_4) use ($updatedpartner) {
                $updatedpartner->video_id_4 = $video_id_4;
            });

            OrbitInput::post('video_id_5', function($video_id_5) use ($updatedpartner) {
                $updatedpartner->video_id_5 = $video_id_5;
            });

            OrbitInput::post('video_id_6', function($video_id_6) use ($updatedpartner) {
                $updatedpartner->video_id_6 = $video_id_6;
            });

            OrbitInput::post('translations', function($translation_json_string) use ($updatedpartner) {
                $this->validateAndSaveTranslations($updatedpartner, $translation_json_string, 'update');
            });

            OrbitInput::post('mobile_default_language', function($mobile_default_language) use ($updatedpartner) {
                $updatedpartner->mobile_default_language = $this->defaultLanguage;
            });

            OrbitInput::post('supported_languages', function($supported_languages) use ($updatedpartner, $partner_id) {
                // check all supported languages
                $all_partner_languages = ObjectSupportedLanguage::where('object_id', $partner_id)
                    ->where('object_type', 'partner')
                    ->where('status', 'active')
                    ->get()
                    ->lists('language_id');

                $unlinked_language_ids = array_diff($all_partner_languages, $supported_languages);
                $added_language_ids = array_diff($supported_languages, $all_partner_languages);

                // Insert added languages
                foreach ($added_language_ids as $added_language) {
                    $new_supported_language = new ObjectSupportedLanguage();
                    $new_supported_language->object_id = $partner_id;
                    $new_supported_language->object_type = 'partner';
                    $new_supported_language->language_id = $added_language;
                    $new_supported_language->save();
                }

                if (! empty($unlinked_language_ids)) {
                    // check for languages that has translation before unlink
                    $partner_translation = PartnerTranslation::whereIn('language_id', $unlinked_language_ids)
                        ->where('partner_id', $partner_id)
                        ->where('status', 'active')
                        ->get();

                    if ($partner_translation->count() !== 0) {
                        $errorMessage = 'Cannot unlink supported language: %s';
                        OrbitShopAPI::throwInvalidArgument(sprintf($errorMessage, $partner_translation[0]->language->name_long));
                    }

                    // unlink languages
                    $unlinked_languages = ObjectSupportedLanguage::whereIn('language_id', $unlinked_language_ids)
                        ->where('object_type', 'partner')
                        ->where('status', 'active')
                        ->get();

                    foreach($unlinked_languages as $unlinked_language) {
                        $unlinked_language->delete();
                    }
                }

                $updatedpartner->load('supportedLanguages.language');
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

            $banners = json_decode($jsonBanners, true);
            $partnerBannersData = [];
            $keptBanners = [];
            if (! empty($banners)) {
                $oldBannerIds = PartnerBanner::where('partner_id', $updatedpartner->partner_id)->lists('partner_banner_id');
                foreach($banners as $bannerIndex => $banner) {

                    $isOutbound = isset($banner['is_outbound']) && $banner['is_outbound'] === 'Y' ? 'Y' : 'N';

                    $fileInputKey = "banners_image_{$bannerIndex}";
                    $event = 'orbit.partner.postupdatepartnerbanner.after.save';

                    $banner['banner_id'] = ! isset($banner['banner_id']) ? '' : $banner['banner_id'];

                    // If banner id is not empty, then it should be kept or update as needed.
                    if (! empty($banner['banner_id'])) {
                        $partnerBanner = PartnerBanner::where('partner_banner_id', $banner['banner_id'])->first();
                        $partnerBanner->is_outbound = $isOutbound;
                        $partnerBanner->link_url = $banner['link_url'];
                        $partnerBanner->save();
                        $keptBanners[] = $banner['banner_id'];
                    }
                    // If banner id is empty, assume it is a new banner item that need to be stored.
                    else if (empty($banner['banner_id'])) {
                        $partnerBanner = new PartnerBanner;
                        $partnerBanner->partner_id = $updatedpartner->partner_id;
                        $partnerBanner->is_outbound = $isOutbound;
                        $partnerBanner->link_url = $banner['link_url'];
                        $partnerBanner->save();
                        $keptBanners[] = $partnerBanner->partner_banner_id;
                    }

                    $partnerBannersData[] = $partnerBanner;

                    if (Input::hasFile($fileInputKey)) {
                        Event::fire($event, array($this, $updatedpartner, $partnerBanner, $bannerIndex));
                    }
                }
            }

            // @todo delete cdn files...
            if (empty($banners)) {
                // if no banners being sent, then assume remove all the banners.
                $shouldBeDeletedBanners = PartnerBanner::with(['media'])->where('partner_id', $updatedpartner->partner_id)->get();
            }
            else if (! empty($keptBanners)) {
                // Otherwise, only delete the ones that not sent by frontend.
                $shouldBeDeletedBanners = PartnerBanner::with(['media'])
                    ->whereNotIn('partner_banner_id', $keptBanners)
                    ->where('partner_id', $updatedpartner->partner_id)
                    ->get();
            }

            foreach ($shouldBeDeletedBanners as $banner) {
                foreach($banner->media as $media) {
                    @unlink($media->realpath);
                    $media->delete();
                }

                $banner->delete();
            }

            $updatedpartner->setUpdatedAt($updatedpartner->freshTimestamp());
            $updatedpartner->save();

            Event::fire('orbit.partner.postupdatepartner.after.save', array($this, $updatedpartner));
            Event::fire('orbit.partner.postupdatepartner.after.save2', array($this, $updatedpartner));

            $updatedpartner->social_media = $partnerSocialMedia;
            $updatedpartner->partner_banners = $partnerBannersData;
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
                            'partners.is_shown_in_filter',
                            'partners.is_exclusive',
                            'partners.token',
                            'partners.pop_up_content',
                            'partners.mobile_default_language',
                            'partners.meta_title',
                            'partners.meta_description',
                            'partners.working_hours',
                            'partners.custom_photo_section_title',
                            'partners.button_color',
                            'partners.button_text_color',
                            'partners.video_id_1',
                            'partners.video_id_2',
                            'partners.video_id_3',
                            'partners.video_id_4',
                            'partners.video_id_5',
                            'partners.video_id_6',
                            DB::raw("
                            CASE WHEN (
                                    SELECT COUNT(object_partner_id)
                                    FROM {$prefix}object_partner op
                                    LEFT JOIN {$prefix}promotions p ON p.promotion_id = op.object_id AND op.object_type = 'coupon'
                                    LEFT JOIN {$prefix}news n ON n.news_id = op.object_id AND op.object_type IN ('news', 'promotion')
                                    WHERE op.object_type IN ('promotion', 'news', 'coupon')
                                        AND op.partner_id = {$prefix}partners.partner_id
                                        AND (p.is_exclusive = 'Y' OR n.is_exclusive = 'Y')
                                    GROUP BY {$prefix}partners.partner_id
                                    ) > 0
                                THEN 'Y'
                                ELSE 'N'
                            END AS linked_to_campaign
                            ")
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

            // Filter by status
            OrbitInput::get('status', function($status) use ($partners)
            {
                $partners->where('partners.status', $status);
            });

            //Filter by country_id
            OrbitInput::get('country_id', function($countryId) use ($partners)
            {
                $partners->where('partners.country_id', $countryId);
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
                    } elseif ($relation === 'translations') {
                        $partners->with('translations');
                    } elseif ($relation === 'supportedLanguages') {
                        $partners->with('supportedLanguages');
                    } elseif ($relation === 'supportedLanguages.language') {
                        $partners->with(['supportedLanguages' => function($q) {
                                $q->with('language')
                                    ->where('object_supported_language.status', 'active');
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
                            $qGroupName->select(DB::raw("
                                        `subQuery`.`partner_id`,
                                        `subQuery`.`affected_group_name_id`,
                                        `subQuery`.`group_name`,
                                        SUM(if (`subQuery`.campaign_status = 'ongoing' and `subQuery`.is_started = 'true', 1, 0)) AS item_count
                                    "))
                                ->leftJoin(
                                    DB::raw("
                                        (SELECT
                                            `{$prefix}partner_affected_group`.`partner_affected_group_id`,
                                            `{$prefix}partner_affected_group`.`partner_id`,
                                            `{$prefix}partner_affected_group`.`affected_group_name_id`,
                                            `{$prefix}object_partner`.`object_id`,
                                            `group_name`,
                                            `group_type`,
                                            CASE
                                                WHEN {$prefix}campaign_status.campaign_status_name = 'expired' THEN {$prefix}campaign_status.campaign_status_name
                                                ELSE (
                                                  CASE
                                                    WHEN
                                                        {$prefix}object_partner.object_type = 'news'
                                                            OR {$prefix}object_partner.object_type = 'promotion'
                                                    THEN
                                                        CASE
                                                            WHEN
                                                                {$prefix}news.end_date < (SELECT
                                                                        MIN(CONVERT_TZ(UTC_TIMESTAMP(),
                                                                                    '+00:00',
                                                                                    ot.timezone_name))
                                                                    FROM
                                                                        {$prefix}news_merchant onm
                                                                            LEFT JOIN
                                                                        {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                                                            LEFT JOIN
                                                                        {$prefix}merchants oms ON oms.merchant_id = om.parent_id
                                                                            LEFT JOIN
                                                                        {$prefix}timezones ot ON ot.timezone_id = (CASE
                                                                            WHEN om.object_type = 'tenant' THEN oms.timezone_id
                                                                            ELSE om.timezone_id
                                                                        END)
                                                                    WHERE
                                                                        onm.news_id = {$prefix}news.news_id)
                                                            THEN
                                                                'expired'
                                                            ELSE {$prefix}campaign_status.campaign_status_name
                                                        END
                                                    WHEN
                                                        {$prefix}object_partner.object_type = 'coupon'
                                                    THEN
                                                        CASE
                                                            WHEN
                                                                {$prefix}promotions.end_date < (SELECT
                                                                        MIN(CONVERT_TZ(UTC_TIMESTAMP(),
                                                                                    '+00:00',
                                                                                    ot.timezone_name))
                                                                    FROM
                                                                        {$prefix}promotion_retailer opt
                                                                            LEFT JOIN
                                                                        {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                                                            LEFT JOIN
                                                                        {$prefix}merchants oms ON oms.merchant_id = om.parent_id
                                                                            LEFT JOIN
                                                                        {$prefix}timezones ot ON ot.timezone_id = (CASE
                                                                            WHEN om.object_type = 'tenant' THEN oms.timezone_id
                                                                            ELSE om.timezone_id
                                                                        END)
                                                                    WHERE
                                                                        opt.promotion_id = {$prefix}promotions.promotion_id)
                                                            THEN
                                                                'expired'
                                                            ELSE {$prefix}campaign_status.campaign_status_name
                                                        END
                                                    WHEN
                                                        {$prefix}object_partner.object_type = 'mall' AND {$prefix}merchants.status = 'active'
                                                    THEN
                                                        'ongoing'
                                                    WHEN
                                                        {$prefix}base_object_partner.object_type = 'tenant'
                                                    THEN
                                                        'ongoing'
                                                END)
                                            END AS campaign_status,

                                            CASE
                                                WHEN
                                                    {$prefix}object_partner.object_type = 'news'
                                                        OR {$prefix}object_partner.object_type = 'promotion'
                                                THEN
                                                    CASE WHEN (SELECT count(onm.merchant_id)
                                                                FROM {$prefix}news_merchant onm
                                                                    LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                                                    LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                WHERE onm.news_id = {$prefix}news.news_id
                                                                AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}news.begin_date and {$prefix}news.end_date) > 0
                                                    THEN 'true' ELSE 'false' END
                                                WHEN
                                                    {$prefix}object_partner.object_type = 'coupon'
                                                THEN
                                                    CASE WHEN (SELECT count(opt.promotion_retailer_id)
                                                                FROM {$prefix}promotion_retailer opt
                                                                    LEFT JOIN {$prefix}merchants om ON om.merchant_id = opt.retailer_id
                                                                    LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                                    LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                                WHERE opt.promotion_id = {$prefix}promotions.promotion_id
                                                                AND CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name) between {$prefix}promotions.begin_date and {$prefix}promotions.end_date) > 0
                                                    THEN 'true' ELSE 'false' END
                                                WHEN
                                                    {$prefix}object_partner.object_type = 'mall' AND {$prefix}merchants.status = 'active'
                                                THEN
                                                    'true'
                                                WHEN
                                                    {$prefix}base_object_partner.object_type = 'tenant'
                                                THEN
                                                    'true'
                                            END AS is_started
                                        FROM
                                            `{$prefix}partner_affected_group`
                                                INNER JOIN
                                            `{$prefix}affected_group_names` ON `{$prefix}affected_group_names`.`affected_group_name_id` = `{$prefix}partner_affected_group`.`affected_group_name_id`
                                                LEFT JOIN
                                            `{$prefix}object_partner` ON `{$prefix}object_partner`.`partner_id` = `{$prefix}partner_affected_group`.`partner_id`
                                                AND `{$prefix}object_partner`.`object_type` = {$prefix}affected_group_names.group_type
                                                AND `{$prefix}object_partner`.`object_type` != 'tenant'
                                                LEFT JOIN
                                            `{$prefix}base_object_partner` ON `{$prefix}base_object_partner`.`partner_id` = `{$prefix}partner_affected_group`.`partner_id`
                                                AND `{$prefix}base_object_partner`.`object_type` = {$prefix}affected_group_names.group_type
                                                LEFT JOIN
                                            `{$prefix}news` ON `{$prefix}object_partner`.`object_id` = `{$prefix}news`.`news_id`
                                                LEFT JOIN
                                            `{$prefix}promotions` ON `{$prefix}object_partner`.`object_id` = `{$prefix}promotions`.`promotion_id`
                                                LEFT JOIN
                                            `{$prefix}merchants` ON `{$prefix}object_partner`.`object_id` = `{$prefix}merchants`.`merchant_id`
                                                AND {$prefix}merchants.object_type = 'mall'
                                                LEFT JOIN
                                            `{$prefix}base_merchants` ON `{$prefix}base_object_partner`.`object_id` = `{$prefix}base_merchants`.`base_merchant_id`
                                                LEFT JOIN
                                            `{$prefix}campaign_status`
                                                ON
                                                `{$prefix}campaign_status`.`campaign_status_id` = `{$prefix}news`.`campaign_status_id`
                                                OR
                                                `{$prefix}campaign_status`.`campaign_status_id` = `{$prefix}promotions`.`campaign_status_id`
                                        ) as subQuery
                                    "), DB::raw('subQuery.partner_affected_group_id'), '=', 'partner_affected_group.partner_affected_group_id')
                                ->groupBy(DB::raw("subQuery.partner_id"), DB::raw("subQuery.group_name"));
                            }]);
                    }
                    else if ($relation === 'banners') {
                        $partners->with(['banners.media']);
                    }
                    else if ($relation === 'categories') {
                        $partners->with(['categories' => function($category) use ($prefix) {
                            $category->select(DB::raw("{$prefix}categories.category_id"), 'category_name');
                        }]);
                    }
                    else if ($relation === 'social_media') {
                        $partners->with(['social_media' => function($socialMedia) use ($prefix) {
                            $socialMedia->select(DB::raw("{$prefix}social_media.social_media_code as social_media"), 'social_media_uri');
                        }]);
                    }
                    else {
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

        // Check the affected language name is exists
        Validator::extend('orbit.empty.language', function ($attribute, $value, $parameters) {
            $values = (array) $value;

            foreach ($values as $language_id) {
                $language = Language::where('language_id', $language_id)
                    ->where('status', 'active')
                    ->first();

                if (empty($language)) {
                    return FALSE;
                }
            }

            return TRUE;
        });

        // Check the affected default language name is exists
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {

            $language = Language::where('language_id', $value)
                ->where('status', 'active')
                ->first();

            if (empty($language)) {
                return FALSE;
            }

            $this->defaultLanguage = $language->name;

            return TRUE;
        });

        // Check the affected default language name is exists
        Validator::extend('orbit.empty.mobile_default_lang', function ($attribute, $value, $parameters) {

            if (! in_array($value, $parameters)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check token is already taken or not
        Validator::extend('orbit.duplicate.token', function ($attribute, $value, $parameters) {
            $isExclusive = $parameters[0];
            if ($isExclusive === 'Y') {
                $token = Partner::where('token', $value)
                    ->where('status', '!=', 'deleted');

                if (! empty($parameters[1])){
                    $token = $token->where('partner_id', '!=', $parameters[1]);
                }

                $token = $token->first();

                if (! empty($token)) {
                    return FALSE;
                }
            }
            return TRUE;
        });

        Validator::extend('orbit.exists.partner_linked_to_active_campaign', function ($attribute, $value, $parameters) {
            $activeCampaignFlag = false;

            if ($value === 'inactive') {
                $now = Carbon::now('Asia/Jakarta'); // now with jakarta timezone

                $prefix = DB::getTablePrefix();
                $linkedNews = ObjectPartner::leftJoin('news', function($q) use($prefix) {
                        $q->on('object_partner.object_id', '=', 'news.news_id')
                            ->on(DB::raw("{$prefix}object_partner.object_type"), DB::raw('IN'), DB::raw("('news', 'promotion')"));
                    })
                    ->leftjoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                    ->where('partner_id', $parameters[0])
                    ->whereRaw("(CASE WHEN {$prefix}news.end_date < '{$now}' THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) NOT IN ('not started', 'stopped', 'expired')")
                    ->first();

                if (is_object($linkedNews)) {
                    return FALSE;
                }

                $linkedCoupon = ObjectPartner::leftJoin('promotions', function($q) use($prefix) {
                        $q->on('object_partner.object_id', '=', 'promotions.promotion_id')
                            ->on(DB::raw("{$prefix}object_partner.object_type"), '=', DB::raw("'coupon'"));
                    })
                    ->leftjoin('campaign_status', 'campaign_status.campaign_status_id', '=', 'promotions.campaign_status_id')
                    ->where('partner_id', $parameters[0])
                    ->whereRaw("(CASE WHEN {$prefix}promotions.end_date < '{$now}' THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) NOT IN ('not started', 'stopped', 'expired')")
                    ->first();

                if (is_object($linkedCoupon)) {
                    return FALSE;
                }
            }

            return TRUE;
        });

        // Check if the partner is already linked to an exclusive campaign
        Validator::extend('orbit.empty.exclusive_campaign_link', function ($attribute, $value, $parameters) {
            $campaignExclusiveFlag = false;

            $partner = Partner::find($parameters[0]);

            if ($value === 'N' && $partner->is_exclusive === 'Y') {
                $prefix = DB::getTablePrefix();
                $linkedCampaigns = ObjectPartner::select(
                        DB::raw("
                            CASE WHEN {$prefix}object_partner.object_type = 'news' OR {$prefix}object_partner.object_type = 'promotion'
                                THEN {$prefix}news.news_id
                                ELSE {$prefix}promotions.promotion_id
                            END AS campaign_id
                            "),
                        DB::raw("
                            CASE WHEN {$prefix}object_partner.object_type = 'news' OR {$prefix}object_partner.object_type = 'promotion'
                                THEN {$prefix}news.is_exclusive
                                ELSE {$prefix}promotions.is_exclusive
                            END AS is_exclusive
                            ")
                    )
                    ->leftJoin('news', function($q) use($prefix) {
                        $q->on('object_partner.object_id', '=', 'news.news_id')
                            ->on(DB::raw("{$prefix}object_partner.object_type"), DB::raw('IN'), DB::raw("('news', 'promotion')"));
                    })
                    ->leftJoin('promotions', function($q) {
                        $q->on('object_partner.object_id', '=', 'promotions.promotion_id')
                            ->on('object_partner.object_type', '=', DB::raw("'coupon'"));
                    })
                    ->where('partner_id', $parameters[0])
                    ->groupBy('object_partner.object_id', 'object_partner.object_type')
                    ->get();

                foreach ($linkedCampaigns as $linkedCampaign) {
                    if ($linkedCampaign->is_exclusive === 'Y') {
                        $campaignExclusiveFlag = true;
                        break;
                    }
                }

                if ($campaignExclusiveFlag) {
                    return FALSE;
                }
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

    /**
     * @param Partner $partner
     * @param string $translations_json_string
     * @param string $scenario 'create' / 'update'
     * @throws InvalidArgsException
     */
    private function validateAndSaveTranslations($partner, $translations_json_string, $scenario = 'create')
    {
        /*
         * JSON structure: object with keys = merchant_language_id and values = ProductTranslation object or null
         *
         * Having a value of null means deleting the translation
         *
         * where PartnerTranslation object is object with keys:
         *   description, pop_up_content
         *
         * No requirement for including fields. If field not included it means not updated. If field included with
         * value null it means set to null (use main language content instead).
         */

        $valid_fields = ['description', 'pop_up_content', 'meta_title', 'meta_description'];
        $user = $this->api->user;
        $operations = [];

        $data = @json_decode($translations_json_string);
        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.jsonerror.field.format', ['field' => 'translations']));
        }

        // translate for mall
        foreach ($data as $language_id => $translations) {
            $language = Language::where('language_id', '=', $language_id)->first();

            if (empty($language)) {
                OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.language'));
            }

            $existing_translation = PartnerTranslation::excludeDeleted()
                ->where('partner_id', '=', $partner->partner_id)
                ->where('language_id', '=', $language_id)
                ->first();

            if ($translations === null) {
                // deleting, verify exists
                if (empty($existing_translation)) {
                    OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.empty.language'));
                }

                $operations[] = ['delete', $existing_translation];
            } else {
                foreach ($translations as $field => $value) {
                    if (!in_array($field, $valid_fields, true)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.key'));
                    }

                    if ($value !== null && !is_string($value)) {
                        OrbitShopAPI::throwInvalidArgument(Lang::get('validation.orbit.formaterror.translation.value'));
                    }
                }

                if (empty($existing_translation)) {
                    $operations[] = ['create', $language_id, $translations];
                } else {
                    $operations[] = ['update', $existing_translation, $translations];
                }
            }
        }

        foreach ($operations as $operation) {
            $op = $operation[0];

            if ($op === 'create') {

                // for translation per mall
                $new_partnertranslation = new PartnerTranslation();
                $new_partnertranslation->partner_id = $partner->partner_id;
                $new_partnertranslation->language_id = $operation[1];
                $new_partnertranslation->status = $partner->status;
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $new_partnertranslation->{$field} = $value;
                }
                $new_partnertranslation->save();

                $partner->setRelation('translation_'. $new_partnertranslation->language_id, $new_partnertranslation);
            }
            elseif ($op === 'update') {

                /** @var PartnerTranslation $existing_translation */
                $existing_translation = $operation[1];
                $data = $operation[2];
                foreach ($data as $field => $value) {
                    $existing_translation->{$field} = $value;
                }
                $existing_translation->save();

                $partner->setRelation('translation_'. $existing_translation->language_id, $existing_translation);

            }
            elseif ($op === 'delete') {
                /** @var PartnerTranslation $existing_translation */
                $existing_translation = $operation[1];
                $existing_translation->delete();
            }
        }

    }
}
