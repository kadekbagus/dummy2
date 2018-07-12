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

    private $sourceTableName = null;

    private $itemsPerBatch = 10;

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
        $this->info("Payment Transaction Data Migration!");
        try {
            $this->info("Checking options...");
            // Check options..
            $this->sourceTableName = $this->option('source');
            $this->sourceTableName = trim($this->sourceTableName);

            if (empty($this->sourceTableName)) {
                throw new Exception('Please set the source table name.', 1);
            }

            $customBatch = $this->option('batch');
            $customBatch = trim($customBatch);

            $customBatch = ! empty($customBatch) ? (int) $customBatch : false;

            $customItemsPerBatch = $this->option('items-per-batch');
            $customItemsPerBatch = trim($customItemsPerBatch);
            $customItemsPerBatch = (int) $customItemsPerBatch;

            if ($customItemsPerBatch !== $this->itemsPerBatch && $customItemsPerBatch !== 0) {
                $this->itemsPerBatch = $customItemsPerBatch;
            }

            $totalMigrated = 0;

            $this->info("Data source: " . $this->sourceTableName);
            $this->info("---------------------------------------------");
            $this->info("Counting all data...");

            // Get all data..
            $totalData = DB::table($this->sourceTableName)->count();

            if ($totalData === 0) {
                throw new Exception('No data found!', 1);
            }

            $batches = $customBatch ? $customBatch : ceil($totalData / $this->itemsPerBatch);

            $this->info("Data count: " . $totalData);
            $this->info("Data will be processed per batch: " . $this->itemsPerBatch);
            $this->info("Batch count: " . $batches);
            $this->info("--------------------------------------------");
            $this->info("\n");

            // TODO: Should we use transaction one time? 
            // Or each batch we start new transaction?
            $this->info("Migration started...");
            if ($this->option('dry-run')) {
                $this->error("RUNNING IN DRY RUN MODE");
            }

            DB::connection()->beginTransaction();

            if (! $customBatch) {
                for($batch = 1; $batch <= $batches; $batch++) {
                    $totalMigrated += $this->migrateBatch($batch);
                }
            }
            else {
                $totalMigrated = $this->migrateBatch($customBatch);
            }

            $this->info("\n");
            if ($this->option('dry-run')) {
                $this->error("RUNNING IN DRY-RUN MODE");
                $this->error('ROLLING BACK CHANGES...');
                DB::connection()->rollback();
                $this->error('DATA ROLLED BACK. NO CHANGES WERE SAVED TO DATABASE');
            }
            else {
                DB::connection()->commit();
            }

            $this->info("Migration ended.");
            $this->info("Total migrated data: {$totalMigrated}");

        } catch (Exception $e) {
            DB::connection()->rollback();
            $this->info("Unable to complete the migration. Check log file.");
            Log::error(sprintf("Exception at %s:%s >> %s", $e->getFile(), $e->getLine(), $e->getMessage()));
        }

        $this->info('Payment Transaction Data Migration Done!');

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
            ['batch', null, InputOption::VALUE_OPTIONAL, 'Migrate specific batch number. Do not set this option to migrate all data.', null],
            ['items-per-batch', null, InputOption::VALUE_OPTIONAL, 'Number of data will be processed per batch.', null],
            ['dry-run', null, InputOption::VALUE_NONE, 'Run in dry-run mode, doesnt commit changes to DB.', null],
        ];
    }

    /**
     * Migrate by batch number.
     * 
     * @param  integer $batch [description]
     * @return [type]         [description]
     */
    private function migrateBatch($batch = 1)
    {
        $this->info("---- Migrating data Batch #{$batch} ----");

        $skip = $batch > 1 ? (($batch - 1) * $this->itemsPerBatch) : 0;

        // Select based on the page.
        $payments = DB::table($this->sourceTableName)->skip($skip)->take($this->itemsPerBatch)->orderBy('created_at', 'asc')->get();

        $migrated = 0;
        foreach($payments as $payment) {

            $this->migratePayment($payment);

            $this->info("     - PaymentID: {$payment->payment_transaction_id} migrated.");
            $migrated++;
        }

        $this->info("---- Batch #{$batch} done. ----");

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
