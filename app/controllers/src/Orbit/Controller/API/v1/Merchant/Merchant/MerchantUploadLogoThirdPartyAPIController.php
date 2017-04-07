<?php namespace Orbit\Controller\API\v1\Merchant\Merchant;

use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitUploader\UploaderConfig;
use DominoPOS\OrbitUploader\UploaderMessage;
use DominoPOS\OrbitUploader\Uploader;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Event;
use App;
use Config;
use Lang;
use Validator;
use BaseMerchant;
use Str;
use Media;
use Orbit\Controller\API\v1\Merchant\Merchant\MerchantHelper;

class MerchantUploadLogoThirdPartyAPIController extends ControllerAPI
{
    protected $merchantViewRoles = ['super admin', 'merchant database admin'];
    protected $calledFrom = 'default';

    /**
     * Upload base merchant logo 3rd party.
     *
     * @author firmansyah <firmansyah@dominopos.com>
     */
    public function postUploadMerchantLogoThirdParty()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.upload.postuploadbasemerchantlogothirdparty.before.auth', array($this));

            // Require authentication
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->checkAuth();

                Event::fire('orbit.upload.postuploadbasemerchantlogothirdparty.after.auth', array($this));

                // Try to check access control list, does this merchant allowed to
                // perform this action
                $user = $this->api->user;
                Event::fire('orbit.upload.postuploadbasemerchantlogothirdparty.before.authz', array($this, $user));

                // @Todo: Use ACL authentication instead
                $role = $user->role;
                $validRoles = $this->merchantViewRoles;
                if (! in_array(strtolower($role->role_name), $validRoles)) {
                    $message = 'Your role are not allowed to access this resource.';
                    ACL::throwAccessForbidden($message);
                }
                Event::fire('orbit.upload.postuploadbasemerchantlogothirdparty.after.authz', array($this, $user));
            } else {
                $user = App::make('orbit.upload.user');
            }

            // Load the orbit configuration for merchant upload logo
            $uploadLogoConfig = Config::get('orbit.upload.base_store.logo_third_party');
            $elementName = $uploadLogoConfig['name'];

            $merchantHelper = MerchantHelper::create();
            $merchantHelper->merchantCustomValidator();

            // Application input
            $merchant_id = OrbitInput::post('merchant_id');
            $object_type = OrbitInput::post('object_type');
            $images = OrbitInput::files($elementName);
            $messages = array(
                'nomore.than.one' => Lang::get('validation.max.array', array(
                    'max' => 1
                ))
            );

            $validator = Validator::make(
                array(
                    'merchant_id' => $merchant_id,
                    $elementName  => $images,
                ),
                array(
                    'merchant_id'   => 'required',
                    $elementName    => 'required|nomore.than.one',
                ),
                $messages
            );

            Event::fire('orbit.upload.postuploadbasemerchantlogothirdparty.before.validation', array($this, $validator));

            // Begin database transaction
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->beginTransaction();
            }

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }
            Event::fire('orbit.upload.postuploadbasemerchantlogothirdparty.after.validation', array($this, $validator));

            // We already had Merchant instance on the RegisterCustomValidation
            // get it from there no need to re-query the database
            $merchant = BaseMerchant::where('base_merchant_id', '=', $merchant_id)->first();

            // Callback to rename the file, we will format it as follow
            // [MERCHANT_ID]-[MERCHANT_NAME_SLUG]
            $renameFile = function($uploader, &$file, $dir) use ($merchant)
            {
                $merchant_id = $merchant->base_merchant_id;
                $slug = Str::slug($merchant->name);
                $file['new']->name = sprintf('%s-%s-%s', $merchant_id, $slug, time());
            };

            $message = new UploaderMessage([]);
            $config = new UploaderConfig($uploadLogoConfig);
            $config->setConfig('before_saving', $renameFile);

            // Create the uploader object
            $uploader = new Uploader($config, $message);

            Event::fire('orbit.upload.postuploadbasemerchantlogothirdparty.before.save', array($this, $merchant, $uploader));

            // Begin uploading the files
            $uploaded = $uploader->upload($images);

            $object_name = 'base_merchant';
            $media_name_id = 'base_merchant_logo_grab';

            // Delete old merchant logo
            $pastMedia = Media::where('object_id', $merchant->base_merchant_id)
                              ->where('object_name', $object_name)
                              ->where('media_name_id', $media_name_id);

            // Delete each files
            $oldMediaFiles = $pastMedia->get();
            foreach ($oldMediaFiles as $oldMedia) {
                // No need to check the return status, just delete and forget
                @unlink($oldMedia->realpath);
            }

            // Delete from database
            if (count($oldMediaFiles) > 0) {
                $pastMedia->delete();
            }

            // Save the files metadata
            $object = array(
                'id'            => $merchant->base_merchant_id,
                'name'          => $object_name,
                'media_name_id' => $media_name_id,
                'modified_by'   => $user->user_id
            );

            $mediaList = $this->saveMetadata($object, $uploaded);

            Event::fire('orbit.upload.postuploadbasemerchantlogothirdparty.after.save', array($this, $merchant, $uploader));

            $this->response->data = $mediaList;
            $this->response->message = Lang::get('statuses.orbit.uploaded.retailer.logo');

            // Commit the changes
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->commit();
            }

            Event::fire('orbit.upload.postuploadbasemerchantlogothirdparty.after.commit', array($this, $merchant, $uploader));
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.upload.postuploadbasemerchantlogothirdparty.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->rollBack();
            }
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.upload.postuploadbasemerchantlogothirdparty.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;

            // Rollback the changes
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->rollBack();
            }
        } catch (QueryException $e) {
            Event::fire('orbit.upload.postuploadbasemerchantlogothirdparty.query.error', array($this, $e));

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
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->rollBack();
            }
        } catch (Exception $e) {
            Event::fire('orbit.upload.postuploadbasemerchantlogothirdparty.general.exception', array($this, $e));

            $this->response->code = Status::UNKNOWN_ERROR;
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;

            // Rollback the changes
            if (! $this->calledFrom('merchant.new, merchant.update')) {
                $this->rollBack();
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.upload.postuploadbasemerchantlogothirdparty.before.render', array($this, $output));

        return $output;
    }

    public function calledFrom($list)
    {
        if (! is_array($list))
        {
            $list = explode(',', (string)$list);
            $list = array_map('trim', $list);
        }

        return in_array($this->calledFrom, $list);
    }

    public function setCalledFrom($from)
    {
        $this->calledFrom = $from;

        return $this;
    }

    public function saveMetadata($object, $metadata)
    {
        $result = array();

        foreach ($metadata as $i=>$file) {
            // Save original file meta data into Media table
            $media = new Media();
            $media->object_id = $object['id'];
            $media->object_name = $object['name'];
            $media->media_name_id = $object['media_name_id'];
            $media->media_name_long = sprintf('%s_orig', $object['media_name_id']);
            $media->file_name = $file['file_name'];
            $media->file_extension = $file['file_ext'];
            $media->file_size = $file['file_size'];
            $media->mime_type = $file['mime_type'];
            $media->path = $file['path'];
            $media->realpath = $file['realpath'];
            $media->metadata = 'order-' . $i;
            $media->modified_by = $object['modified_by'];
            $media->save();
            $result[] = $media;

            // Save the cropped, resized and scaled if any
            foreach (array('resized', 'cropped', 'scaled') as $variant) {
                // Save each profile
                foreach ($file[$variant] as $profile=>$finfo) {
                    $media = new Media();
                    $media->object_id = $object['id'];
                    $media->object_name = $object['name'];
                    $media->media_name_id = $object['media_name_id'];
                    $media->media_name_long = sprintf('%s_%s_%s', $object['media_name_id'], $variant, $profile);
                    $media->file_name = $finfo['file_name'];
                    $media->file_extension = $file['file_ext'];
                    $media->file_size = $finfo['file_size'];
                    $media->mime_type = $file['mime_type'];
                    $media->path = $finfo['path'];
                    $media->realpath = $finfo['realpath'];
                    $media->metadata = 'order-' . $i;
                    $media->modified_by = $object['modified_by'];
                    $media->save();
                    $result[] = $media;
                }
            }
        }

        return $result;
    }
}