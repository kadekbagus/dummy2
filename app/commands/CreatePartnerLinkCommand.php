<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CreatePartnerLinkCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'partner:create-link';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command for import partner link from json file. ';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Read the json file.
     */
    protected function readJSON($file)
    {
        if (! file_exists($file) ) {
           throw new Exception('Could not found json file.');
        }

        $conf = @json_decode(file_get_contents($file), true);
        $basefile = $basefile = basename($file);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception( sprintf('Error JSON %s: %s', $basefile, json_last_error_msg()) );
        }

        return $conf;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        try {

            $fileName = $this->option('json-file');
            $basefile = basename($fileName);

            $data = $this->readJSON($fileName);
            $partner_id = $this->option('partner-id');

            // mapping to type to object_type
            $object_type = [
                'coupons'    => 'coupon',
                'promotions' => 'promotion',
                'events'     => 'news',
                'stores'     => 'tenant',
                'malls'      => 'mall',
            ];

            // validatoin partner id is exists
            $this->registerCustomValidation();

            $validator = Validator::make(
                array(
                    'partner_id' => $partner_id,
                ),
                array(
                    'partner_id' => 'required|orbit.exist.partner_id',
                ),
                array(
                    'orbit.exist.partner_id' => 'Partner ID does not exist',
                )
            );

            // Run the validation
            if ($validator->fails()) {
                $errorMessage = $validator->messages()->first();
                throw new Exception($errorMessage);
            }

            DB::beginTransaction();

            foreach ($data as $type => $link) {
                // validation support type
                $validator = Validator::make(
                    array(
                        'type' => $type,
                    ),
                    array(
                        'type' => 'required|in:coupons,promotions,events,stores,malls',
                    ),
                    array(
                        'in' => 'Link type support is coupons, promotions, events, stores and malls',
                    )
                );

                // Run the validation
                if ($validator->fails()) {
                    $errorMessage = $validator->messages()->first();
                    throw new Exception($errorMessage);
                }

                foreach ($link as $object_id) {
                    // validation id is exists
                    // validation link is not exists
                    $validator = Validator::make(
                        array(
                            'object_id'    => $object_id,
                            'partner_link' => $object_id,
                        ),
                        array(
                            'object_id'    => 'required|orbit.exist.object_id:' . $object_type[$type],
                            'partner_link' => 'required|orbit.exist.partner_link:' . $object_type[$type] . ',' . $partner_id,
                        ),
                        array(
                            'orbit.exist.object_id' => "Object ID {$type} does not exist",
                            'orbit.exist.partner_link' => "Partner Link {$type} with id {$object_id} is already exist",
                        )
                    );

                    // Run the validation
                    if ($validator->fails()) {
                        $errorMessage = $validator->messages()->first();
                        throw new Exception($errorMessage);
                    }

                    // insert to db
                    $newpartnerlink = new ObjectPartner();
                    $newpartnerlink->partner_id = $partner_id;
                    $newpartnerlink->object_id = $object_id;
                    $newpartnerlink->object_type = $object_type[$type];
                    $newpartnerlink->save();

                    // print information
                    $this->info( sprintf('Partner id %s successfully link to %s with id %s.', $partner_id, $type, $object_id) );
                }
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            $this->error('Line #' . $e->getLine() . ': ' . $e->getMessage());
        }
    }


    protected function registerCustomValidation()
    {
        // Check the partner id is exists
        Validator::extend('orbit.exist.partner_id', function ($attribute, $value, $parameters) {
            $checkPartnerId = Partner::excludeDeleted()->where('partner_id', $value)->first();

            if (empty($checkPartnerId)) {
                return FALSE;
            }

            return TRUE;
        });


        // Check the object id is exists
        Validator::extend('orbit.exist.object_id', function ($attribute, $value, $parameters) {
            $object_id = $value;
            $object_type = $parameters[0];
            $checkObject = null;

            if ($object_type === 'coupon') {
                $checkObject = Coupon::excludeDeleted()
                                ->where('promotion_id', $object_id)
                                ->first();
            }

            if ($object_type === 'promotion') {
                $checkObject = News::excludeDeleted()
                                ->where('news_id', $object_id)
                                ->where('object_type', 'promotion')
                                ->first();
            }

            if ($object_type === 'news') {
                $checkObject = News::excludeDeleted()
                                ->where('news_id', $object_id)
                                ->where('object_type', 'news')
                                ->first();
            }

            if ($object_type === 'tenant') {
                $checkObject = Tenant::excludeDeleted()
                                ->where('merchant_id', $object_id)
                                ->first();
            }

            if ($object_type === 'mall') {
                $checkObject = Mall::excludeDeleted()
                                ->where('merchant_id', $object_id)
                                ->first();
            }

            if (empty($checkObject)) {
                return FALSE;
            }

            return TRUE;
        });

        // Check the partner link does not exists
        Validator::extend('orbit.exist.partner_link', function ($attribute, $value, $parameters) {
            $object_id = $value;
            $object_type = $parameters[0];
            $partner_id = $parameters[1];

            $checkPartnerLink = ObjectPartner::where('partner_id', $partner_id)
                                    ->where('object_id', $object_id)
                                    ->where('object_type', $object_type)
                                    ->first();

            if (! empty($checkPartnerLink)) {
                return FALSE;
            }

            return TRUE;
        });
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array();
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('json-file', null, InputOption::VALUE_REQUIRED, 'JSON file.'),
            array('partner-id', null, InputOption::VALUE_REQUIRED, 'Partner ID.'),
        );
    }

}
