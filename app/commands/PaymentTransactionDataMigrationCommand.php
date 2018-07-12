<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class PaymentTransactionDataMigrationCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'payment-transaction:migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate Payment Transaction data from old table structure to the new one.';

    private $dryRun = false;

    private $defaultItemsPerBatch = 15;

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
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $this->info("[ PAYMENT TRANSACTION DATA MIGRATION ]");
        try {

            $options = $this->option();
            $this->dryRun = $options['dry-run'];

            if ($this->dryRun) {
                $this->error("*** RUNNING IN DRY-RUN MODE ***");
            }
            $this->info("");

            if (! $options['source']) {
                throw new Exception('Source table name is required!');
            }

            $options['items-per-batch']     = (int) $options['items-per-batch'];
            $options['payment-id']          = trim($options['payment-id']);

            if (empty($options['payment-id'])) {
                if (! $options['items-per-batch']) {
                    $options['items-per-batch'] = $this->defaultItemsPerBatch;
                }
            }

            $this->info("Starting migration...");
            $this->info("---------------------------------------------------");

            DB::connection()->beginTransaction();

            $totalMigrated = 0;
            if (! empty($options['payment-id'])) {
                $totalMigrated = $this->migrateSinglePayment($options['source'], $options['payment-id']);
            }
            else {
                $totalMigrated = $this->migrateBatchPayment($options['source'], $options);
            }

            $this->info("");
            $this->info("All data migrated successfully!");
            $this->info("Total migrated: {$totalMigrated}");

            if ($this->dryRun) {
                DB::connection()->rollback();
            }
            else {
                DB::connection()->commit();
            }

        } catch (Exception $e) {
            DB::connection()->rollback();
            $this->error("Unable to complete the migration.");
            $this->error($e->getMessage());
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['source', null, InputOption::VALUE_REQUIRED, 'Table name which contains the old payment transaction data.', null],
            ['payment-id', null, InputOption::VALUE_OPTIONAL, 'Payment ID that will be migrated. If set, it will ignore option batch.', null],
            ['items-per-batch', null, InputOption::VALUE_OPTIONAL, 'Number of data will be processed per batch.', null],
            ['dry-run', null, InputOption::VALUE_NONE, 'Run in DRY-RUN mode, doesnt commit changes to DB.', null],
        ];
    }

    /**
     * Migrate a single payment.
     * 
     * @param  [type] $source    [description]
     * @param  [type] $paymentId [description]
     * @return [type]            [description]
     */
    public function migrateSinglePayment($source, $paymentId)
    {
        // $this->info("Migrating payment {$paymentId}...");

        $oldPayment = DB::table($source)->where('payment_transaction_id', $paymentId)->first();

        if (! empty($oldPayment)) {

            $this->migratePayment($oldPayment);

            $this->info("Payment {$paymentId} migrated successfully!");

            $this->info("---------------------------------------------------");

            return 1;
        }

        $this->error("Payment {$paymentId} not found in table {$source}!");
    }

    private function migrateBatchPayment($source, $options)
    {
        $this->info("Counting all records...");

        $totalRecords = DB::table($source)->count();

        if ($totalRecords === 0) {
            $this->error("Whoops! No records found in table {$source}!");
            return 0;
        }
        else {

            $this->info("Found {$totalRecords} records in table {$source}...");
            $this->info("Calculating batches...");

            $batches = ceil($totalRecords / $options['items-per-batch']);

            $this->info("Migration will be splitted into {$batches} batches...");
            $this->info("");
            $this->info("---------------------------------------------------");

            $totalMigrated = 0;
            for($batch = 1; $batch <= $batches; $batch++) {
                $totalMigrated += $this->migrateBatch($source, $batch, $options['items-per-batch']);
            }

            return $totalMigrated;
        }
    }

    /**
     * Migrate by batch number.
     * 
     * @param  integer $batch [description]
     * @return [type]         [description]
     */
    private function migrateBatch($source, $batch = 1, $itemsPerBatch = 15)
    {
        $this->info("Migrating data Batch #{$batch}...");

        $skip = $batch > 1 ? (($batch - 1) * $itemsPerBatch) : 0;

        $payments = DB::table($source)->skip($skip)->take($itemsPerBatch)->orderBy('created_at', 'asc')->get();

        $migrated = 0;
        foreach($payments as $payment) {

            $this->migratePayment($payment);

            $this->info("[BATCH {$batch}]: Payment {$payment->payment_transaction_id} migrated successfully.");
            $migrated++;
        }

        $this->info("Migration Batch #{$batch} done.");
        $this->info("-------------------------------------------------------");

        return $migrated;
    }

    /**
     * Store new payment transaction record.
     * 
     * @param  [type] $payment     [description]
     * @param  [type] &$newPayment [description]
     * @return [type]              [description]
     */
    private function migratePayment($payment)
    {
        $newPayment = PaymentTransaction::find($payment->payment_transaction_id);
        if (empty($newPayment)) {
            $newPayment = new PaymentTransaction;
        }

        $newPayment->payment_transaction_id             = $payment->payment_transaction_id;
        $newPayment->external_payment_transaction_id    = $payment->external_payment_transaction_id;
        $newPayment->user_email                         = $payment->user_email;
        $newPayment->user_name                          = $payment->user_name;
        $newPayment->user_id                            = $payment->user_id;
        $newPayment->phone                              = $payment->phone;
        $newPayment->country_id                         = $payment->country_id;
        $newPayment->payment_provider_id                = $payment->payment_provider_id;
        $newPayment->payment_method                     = $payment->payment_method;
        $newPayment->amount                             = $payment->amount;
        $newPayment->currency                           = $payment->currency;
        $newPayment->status                             = $payment->status;
        $newPayment->timezone_name                      = $payment->timezone_name;
        $newPayment->provider_response_code             = $payment->provider_response_code;
        $newPayment->provider_response_message          = $payment->provider_response_message;
        $newPayment->notes                              = $payment->notes;
        $newPayment->post_data                          = $payment->post_data;
        $newPayment->responded_at                       = $payment->responded_at;
        $newPayment->created_at                         = $payment->created_at;
        $newPayment->updated_at                         = $payment->updated_at;

        $newPayment->save();

        // Migrate the details...
        $this->migratePaymentDetail($payment);

        // If it is midtrans, then migrate specific informations..
        if ($payment->payment_method === 'midtrans') {
            $this->migrateMidtransInfo($payment);
        }
    }

    /**
     * Migrate into payment transaction detail.
     * 
     * @param  PaymentTransaction $payment [description]
     * @return [type]                      [description]
     */
    private function migratePaymentDetail($payment)
    {
        $paymentDetail = PaymentTransactionDetail::where('payment_transaction_id', $payment->payment_transaction_id)->first();

        if (empty($paymentDetail)) {
            $paymentDetail = new PaymentTransactionDetail;
        }

        $paymentDetail->payment_transaction_id = $payment->payment_transaction_id;
        $paymentDetail->object_id              = $payment->object_id;
        $paymentDetail->object_type            = $payment->object_type;
        $paymentDetail->object_name            = $payment->object_name;
        $paymentDetail->currency               = $payment->currency;
        $paymentDetail->price                  = $payment->amount;
        $paymentDetail->quantity               = 1;
        $paymentDetail->created_at             = $payment->created_at;
        $paymentDetail->updated_at             = $payment->updated_at;

        $paymentDetail->save();

        $this->migratePaymentDetailNormalPaypro($payment, $paymentDetail);
    }

    /**
     * Migrate payment informations related to normal/paypro.
     * 
     * @param  PaymentTransaction       $payment       [description]
     * @param  PaymentTransactionDetail $paymentDetail [description]
     * @return [type]                                  [description]
     */
    private function migratePaymentDetailNormalPaypro($payment, PaymentTransactionDetail $paymentDetail)
    {
        $paymentDetailNormalPaypro = PaymentTransactionDetailNormalPaypro::where('payment_transaction_detail_id')->first();

        if (empty($paymentDetailNormalPaypro)) {
            $paymentDetailNormalPaypro = new PaymentTransactionDetailNormalPaypro;
        }

        $paymentDetailNormalPaypro->payment_transaction_detail_id = $paymentDetail->payment_transaction_detail_id;
        $paymentDetailNormalPaypro->merchant_id = $payment->merchant_id;
        $paymentDetailNormalPaypro->merchant_name = $payment->merchant_name;
        $paymentDetailNormalPaypro->store_id = $payment->store_id;
        $paymentDetailNormalPaypro->store_name = $payment->store_name;
        $paymentDetailNormalPaypro->building_id = $payment->building_id;
        $paymentDetailNormalPaypro->building_name = $payment->building_name;
        $paymentDetailNormalPaypro->mdr = $payment->mdr;
        $paymentDetailNormalPaypro->default_mdr = $payment->default_mdr;
        $paymentDetailNormalPaypro->transaction_amount_minus_mdr = $payment->transaction_amount_minus_mdr;
        $paymentDetailNormalPaypro->gtm_bank_id = $payment->gtm_bank_id;
        $paymentDetailNormalPaypro->gtm_bank_account_name = $payment->gtm_bank_account_name;
        $paymentDetailNormalPaypro->gtm_bank_account_number = $payment->gtm_bank_account_number;
        $paymentDetailNormalPaypro->gtm_bank_name = $payment->gtm_bank_name;
        $paymentDetailNormalPaypro->gtm_swift_code = $payment->gtm_swift_code;
        $paymentDetailNormalPaypro->gtm_bank_address = $payment->gtm_bank_address;
        $paymentDetailNormalPaypro->provider_received_due_date = $payment->provider_received_due_date;
        $paymentDetailNormalPaypro->received_status = $payment->received_status;
        $paymentDetailNormalPaypro->provider_received_date = $payment->provider_received_date;
        $paymentDetailNormalPaypro->amount_received = $payment->amount_received;
        $paymentDetailNormalPaypro->merchant_pay_due_date = $payment->merchant_pay_due_date;
        $paymentDetailNormalPaypro->payment_status = $payment->payment_status;
        $paymentDetailNormalPaypro->merchant_pay_date = $payment->merchant_pay_date;
        $paymentDetailNormalPaypro->amount_paid = $payment->amount_paid;
        $paymentDetailNormalPaypro->merchant_bank_id = $payment->merchant_bank_id;
        $paymentDetailNormalPaypro->merchant_bank_account_name = $payment->merchant_bank_account_name;
        $paymentDetailNormalPaypro->merchant_bank_account_number = $payment->merchant_bank_account_number;
        $paymentDetailNormalPaypro->merchant_bank_name = $payment->merchant_bank_name;
        $paymentDetailNormalPaypro->merchant_swift_code = $payment->merchant_swift_code;
        $paymentDetailNormalPaypro->merchant_bank_address = $payment->merchant_bank_address;
        $paymentDetailNormalPaypro->provider_transaction_mdr_amount = $payment->provider_transaction_mdr_amount;
        $paymentDetailNormalPaypro->provider_mdr_commission_percentage = $payment->provider_mdr_commission_percentage;
        $paymentDetailNormalPaypro->provider_mdr_commission_amount = $payment->provider_mdr_commission_amount;
        $paymentDetailNormalPaypro->provider_mdr_invoice_sent_date = $payment->provider_mdr_invoice_sent_date;
        $paymentDetailNormalPaypro->provider_mdr_payment_due_date = $payment->provider_mdr_payment_due_date;
        $paymentDetailNormalPaypro->provider_mdr_commission_status = $payment->provider_mdr_commission_status;
        $paymentDetailNormalPaypro->provider_mdr_payment_date = $payment->provider_mdr_payment_date;
        $paymentDetailNormalPaypro->provider_mdr_commission_amount_paid = $payment->provider_mdr_commission_amount_paid;
        $paymentDetailNormalPaypro->provider_mdr_gtm_bank_id = $payment->provider_mdr_gtm_bank_id;
        $paymentDetailNormalPaypro->provider_mdr_gtm_bank_account_name = $payment->provider_mdr_gtm_bank_account_name;
        $paymentDetailNormalPaypro->provider_mdr_gtm_bank_account_number = $payment->provider_mdr_gtm_bank_account_number;
        $paymentDetailNormalPaypro->provider_mdr_gtm_bank_name = $payment->provider_mdr_gtm_bank_name;
        $paymentDetailNormalPaypro->provider_mdr_tax_percentage_commision = $payment->provider_mdr_tax_percentage_commision;
        $paymentDetailNormalPaypro->provider_mdr_tax_amount_commision = $payment->provider_mdr_tax_amount_commision;
        $paymentDetailNormalPaypro->commission_fixed_amount = $payment->commission_fixed_amount;
        $paymentDetailNormalPaypro->commission_transaction_percentage = $payment->commission_transaction_percentage;
        $paymentDetailNormalPaypro->commission_merchant_amount = $payment->commission_merchant_amount;
        $paymentDetailNormalPaypro->commission_invoice_sent_date = $payment->commission_invoice_sent_date;
        $paymentDetailNormalPaypro->commission_payment_due_date = $payment->commission_payment_due_date;
        $paymentDetailNormalPaypro->commission_merchant_payment_status = $payment->commission_merchant_payment_status;
        $paymentDetailNormalPaypro->commission_payment_invoice_payment_date = $payment->commission_payment_invoice_payment_date;
        $paymentDetailNormalPaypro->commission_amount_paid = $payment->commission_amount_paid;
        $paymentDetailNormalPaypro->commission_gtm_bank_id = $payment->commission_gtm_bank_id;
        $paymentDetailNormalPaypro->commission_gtm_bank_account_name = $payment->commission_gtm_bank_account_name;
        $paymentDetailNormalPaypro->commission_gtm_bank_account_number = $payment->commission_gtm_bank_account_number;
        $paymentDetailNormalPaypro->commission_gtm_bank_name = $payment->commission_gtm_bank_name;
        $paymentDetailNormalPaypro->commission_tax_percentage_commision = $payment->commission_tax_percentage_commision;
        $paymentDetailNormalPaypro->commission_tax_amount_commision = $payment->commission_tax_amount_commision;
        $paymentDetailNormalPaypro->provider_mdr_commission_amount_net = $payment->provider_mdr_commission_amount_net;
        $paymentDetailNormalPaypro->commission_merchant_amount_net = $payment->commission_merchant_amount_net;
        $paymentDetailNormalPaypro->created_at = $payment->created_at;
        $paymentDetailNormalPaypro->updated_at = $payment->updated_at;

        $paymentDetailNormalPaypro->save();
    }

    /**
     * Migrate information related to midtrans.
     * 
     * @param  PaymentTransaction $payment [description]
     * @return [type]                      [description]
     */
    private function migrateMidtransInfo($payment)
    {
        $paymentMidtrans = PaymentMidtrans::where('payment_transaction_id', $payment->payment_transaction_id)->first();

        if (empty($paymentMidtrans)) {
            $paymentMidtrans = new PaymentMidtrans;
        }

        $paymentMidtrans->payment_transaction_id = $payment->payment_transaction_id;
        $paymentMidtrans->payment_midtrans_info = $payment->payment_midtrans_info;

        $paymentMidtrans->save();
    }

}
