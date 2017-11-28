<?php
/**
 * An API controller for sponsor bank, e-wallet and credit card setup.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use Carbon\Carbon as Carbon;
use Helper\EloquentRecordCounter as RecordCounter;

class SponsorProviderAPIController extends ControllerAPI
{
    protected $viewRoles = ['super admin', 'mall admin', 'mall owner', 'campaign owner', 'campaign employee', 'campaign admin'];
    /**
     * POST - post new sponsor bank, e-wallet, credit card
     * @author kadek <kadek@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string       `sponsor_name`
     * @param string       `object_type`
     * @param string       `country_id`
     * @param string       `status`
     * @param string       `default_language_id`
     * @param JSON         `translations`
     * @param JSON         `credit_cards`
     * @param file         `logo`
     *
     * @return Illuminate\Support\Facades\Response
     *
     */
    public function postNewSponsorProvider()
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

            $this->registerCustomValidation();

            $sponsorName = OrbitInput::post('sponsor_name');
            $objectType = OrbitInput::post('object_type');
            $countryId = OrbitInput::post('country_id');
            $description = OrbitInput::post('description');
            $status = OrbitInput::post('status');
            $defaultLanguageId = OrbitInput::post('default_language_id');
            $translation = OrbitInput::post('translations');

            $validator = Validator::make(
                array(
                    'sponsor_provider_name' => $sponsorName,
                    'object_type'           => $objectType,
                    'default_language_id'   => $defaultLanguageId,
                    'status'                => $status,
                ),
                array(
                    'sponsor_provider_name' => 'required',
                    'object_type'           => 'required|in:bank,ewallet',
                    'default_language_id'   => 'required|orbit.empty.language_default',
                    'status'                => 'in:active,inactive'
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $translations = @json_decode($translation);
            if (json_last_error() != JSON_ERROR_NONE) {
                OrbitShopAPI::throwInvalidArgument('translation JSON is not valid');
            }

            $defaultDescription = null;
            if (!empty ($translations)) {
                foreach ($translations as $key => $value) {
                    if ($key === $defaultLanguageId) {
                        $defaultDescription = $value->description;
                    }
                }
            }

            $newSponsorProvider = new SponsorProvider();
            $newSponsorProvider->name = $sponsorName;
            $newSponsorProvider->object_type = $objectType;
            $newSponsorProvider->country_id = $countryId;
            $newSponsorProvider->description = $defaultDescription;
            $newSponsorProvider->status = $status;
            $newSponsorProvider->save();

            Event::fire('orbit.sponsorprovider.postnewsponsorprovider.after.save', array($this, $newSponsorProvider));

            OrbitInput::post('translations', function($translations_json_string) use ($newSponsorProvider) {
                $this->validateAndSaveTranslation($newSponsorProvider, $translations_json_string, 'create');
            });

            OrbitInput::post('credit_cards', function($credit_cards_json_string) use ($newSponsorProvider, $defaultLanguageId) {
                $this->validateAndSaveCreditCard($newSponsorProvider, $credit_cards_json_string, 'create', $defaultLanguageId);
            });

            Event::fire('orbit.sponsorprovider.postnewsponsorprovidercreditcard.after.save', array($this, $newSponsorProvider));

            // Commit the changes
            $this->commit();

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->data = $newSponsorProvider;
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
            $message = $e->getMessage();
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $message;
            $this->response->data = null;
        }

        $output = $this->render($httpCode);

        return $output;
    }

    public function postUpdateSponsorProvider()
    {

    }

    public function getSearchSponsorProvider()
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

            $sort_by = OrbitInput::get('sortby');

            $validator = Validator::make(
                array(
                    'sort_by' => $sort_by,
                ),
                array(
                    'sort_by' => 'in:sponsor_name,country,status',
                )
            );
            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

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

            $sponsorProviders = SponsorProvider::excludeDeleted('sponsor_providers')
                                               ->select('sponsor_providers.*', 'countries.name as country')
                                               ->leftJoin('countries', 'countries.country_id', '=', 'sponsor_providers.country_id');

            // Filter sponsor provider by Id
            OrbitInput::get('sponsor_provider_id', function ($sponsorProviderId) use ($sponsorProviders) {
                $sponsorProviders->where('sponsor_providers.sponsor_provider_id', $sponsorProviderId);
            });

            // Filter sponsor provider by Ids
            OrbitInput::get('sponsor_provider_ids', function ($sponsorProviderIds) use ($sponsorProviders) {
                $sponsorProviderIds = (array)$sponsorProviderIds;
                $sponsorProviders->where('sponsor_providers.sponsor_provider_id', $sponsorProviderIds);
            });

            // Filter sponsor provider by name
            OrbitInput::get('sponsor_name', function ($name) use ($sponsorProviders) {
                $sponsorProviders->where('sponsor_providers.name', $name);
            });

            // Filter sponsor provider by name like
            OrbitInput::get('sponsor_name_like', function ($name) use ($sponsorProviders) {
                $sponsorProviders->where('sponsor_providers.name', 'like', "%$name%");
            });

            // Filter sponsor provider by status
            OrbitInput::get('status', function ($status) use ($sponsorProviders) {
                $sponsorProviders->where('sponsor_providers.status', $status);
            });

            // Add new relation based on request
            OrbitInput::get('with', function ($with) use ($sponsorProviders) {
                $with = (array) $with;

                foreach ($with as $relation) {
                    if ($relation === 'media') {
                        $sponsorProviders->with('media');
                    } elseif ($relation === 'creditcards') {
                        $sponsorProviders->with('creditCards');
                    } elseif ($relation === 'translation') {
                        $sponsorProviders->with('translation');
                    }
                }
            });

            $_sponsorProviders = clone $sponsorProviders;

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
            $sponsorProviders->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $sponsorProviders) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $sponsorProviders->skip($skip);

            // Default sort by
            $sortBy = 'sponsor_providers.name';
            // Default sort mode
            $sortMode = 'asc';

            OrbitInput::get('sortby', function ($_sortBy) use (&$sortBy) {
                // Map the sortby request to the real column name
                $sortByMapping = array(
                    'sponsor_name' => 'sponsor_providers.name',
                    'country'      => 'country',
                    'status'       => 'sponsor_providers.status',
                );

                $sortBy = $sortByMapping[$_sortBy];
            });

            OrbitInput::get('sortmode', function ($_sortMode) use (&$sortMode) {
                if (strtolower($_sortMode) !== 'asc') {
                    $sortMode = 'desc';
                }
            });
            $sponsorProviders->orderBy($sortBy, $sortMode);

            $totalRec = RecordCounter::create($_sponsorProviders)->count();
            $listOfRec = $sponsorProviders->get();

            $data = new stdclass();
            $data->total_records = $totalRec;
            $data->returned_records = count($listOfRec);
            $data->records = $listOfRec;

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->data = $data;
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
            $message = $e->getMessage();
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $message;
            $this->response->data = null;
        }

        $output = $this->render($httpCode);

        return $output;
    }


    public function getSearchSponsorProviderDetail()
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

            $sponsor_provider_id = OrbitInput::get('sponsor_provider_id');

            $validator = Validator::make(
                array(
                    'sponsor_provider_id' => $sponsor_provider_id,
                ),
                array(
                    'sponsor_provider_id' => 'required',
                )
            );
            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $sponsorProviders = SponsorProvider::excludeDeleted('sponsor_providers')
                                               ->with('media', 'translation')
                                               ->select('sponsor_providers.*', 'countries.name as country')
                                               ->leftJoin('countries', 'countries.country_id', '=', 'sponsor_providers.country_id')
                                               ->where('sponsor_provider_id', '=', $sponsor_provider_id)
                                               ->first();

            if (!is_object($sponsorProviders)) {
                $errorMessage = 'Sponsor provider not found';
                OrbitShopAPI::throwInvalidArgument($errorMessage);
            }

            $sponsorCreditCard = SponsorCreditCard::with('media','translation')
                                                ->where('sponsor_provider_id','=', $sponsor_provider_id)
                                                ->get();

            $data = new stdclass();
            $data->sponsor_provider = $sponsorProviders;
            $data->credit_cards = $sponsorCreditCard;

            $this->response->code = 0;
            $this->response->status = 'success';
            $this->response->data = $data;
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
            $message = $e->getMessage();
            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $message;
            $this->response->data = null;
        }

        $output = $this->render($httpCode);

        return $output;
    }


    /**
     * @param EventModel $event
     * @param string $translations_json_string
     * @param string $scenario 'create' / 'update'
     * @throws InvalidArgsException
     */
    private function validateAndSaveCreditCard($newSponsorProvider, $credit_cards_json_string, $scenario = 'create', $defaultLanguageId)
    {
        $user = $this->api->user;

        $data = @json_decode($credit_cards_json_string);

        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument('Credit Card JSON format not valid');
        }

        $sponsor_provider_id = $newSponsorProvider->sponsor_provider_id;

        // if update delete the old data first
        if ($scenario === 'update')
        {
            $oldCreditCardData = SponsorCreditCard::select('sponsor_credit_card_id')
                                                ->where('sponsor_provider_id', '=', $sponsor_provider_id)
                                                ->get();
            $arrCreditCardId = [];
            if (!empty($oldCreditCardData)) {
                foreach ($oldCreditCardData as $key => $value) {
                    $arrCreditCardId[] = $oldCreditCardData[$key]['sponsor_credit_card_id'];
                }

                foreach ($arrCreditCardId as $key => $value) {
                    $oldCreditCardTranslation = SponsorCreditCardTranslation::where('sponsor_credit_card_id', $value);
                    $oldCreditCardTranslation->delete();
                }

                $oldCreditCard = SponsorCreditCard::where('sponsor_provider_id', '=', $sponsor_provider_id);
                $oldCreditCard->delete();
            }
        }

        $creditCards = [];
        $creditCardTranslation = [];
        $defaultDescription = null;
        foreach ($data as $key => $creditCardData)
        {
            // find description for default language
            if (!empty ($creditCardData->description)) {
                foreach ($creditCardData->description as $key => $value) {
                    if ($key === $defaultLanguageId) {
                        $defaultDescription = $value->description;
                    }
                }
            }

            // save credit card
            $newCreditCard = new SponsorCreditCard();
            $newCreditCard->name = $creditCardData->card_name;
            $newCreditCard->description = $defaultDescription;
            $newCreditCard->sponsor_provider_id = $sponsor_provider_id;
            $newCreditCard->status = 'active';
            $newCreditCard->save();
            $creditCards[] = $newCreditCard;

            // save credit card translation
            if (!empty ($creditCardData->description)) {
                foreach ($creditCardData->description as $key => $value) {
                    $newCreditCardTranslation = new SponsorCreditCardTranslation();
                    $newCreditCardTranslation->sponsor_credit_card_id = $newCreditCard->sponsor_credit_card_id;
                    $newCreditCardTranslation->language_id = $key;
                    $newCreditCardTranslation->description = $value->description;
                    $newCreditCardTranslation->save();
                    $creditCardTranslation[] = $newCreditCardTranslation;
                }
            }
        }
        $newSponsorProvider->credit_cards = $creditCards;
        $newSponsorProvider->credit_card_translations = $creditCardTranslation;
    }


    /**
     * @param EventModel $event
     * @param string $translations_json_string
     * @param string $scenario 'create' / 'update'
     * @throws InvalidArgsException
     */
    private function validateAndSaveTranslation($newSponsorProvider, $translations_json_string, $scenario = 'create')
    {
        $user = $this->api->user;

        $data = @json_decode($translations_json_string);

        if (json_last_error() != JSON_ERROR_NONE) {
            OrbitShopAPI::throwInvalidArgument('Translations JSON format not valid');
        }

        $sponsor_provider_id = $newSponsorProvider->sponsor_provider_id;

        // if update delete the old data first
        if ($scenario === 'update')
        {
            $oldTranslation = SponsorProviderTranslation::where('sponsor_provider_id', '=', $sponsor_provider_id);
            $oldTranslation->delete();
        }

        $dataTranslations = [];
        foreach ($data as $key => $translationData)
        {
            $newSponsorProviderTranslation = new SponsorProviderTranslation();
            $newSponsorProviderTranslation->language_id = $key;
            $newSponsorProviderTranslation->sponsor_provider_id = $sponsor_provider_id;
            $newSponsorProviderTranslation->description = $translationData->description;
            $newSponsorProviderTranslation->save();
            $dataTranslations[] = $newSponsorProviderTranslation;
        }
        $newSponsorProvider->translations = $dataTranslations;
    }

    protected function registerCustomValidation()
    {
        // Check the existance of id_language_default
        Validator::extend('orbit.empty.language_default', function ($attribute, $value, $parameters) {
            $language = Language::where('language_id', '=', $value)
                                    ->first();

            if (empty($language)) {
                return false;
            }

            App::instance('orbit.empty.language_default', $language);

            return true;
        });
    }
}