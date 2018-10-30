<?php

use Carbon\Carbon;
use Illuminate\Console\Command;
use Orbit\Notifications\Payment\BeforeExpiredPaymentNotification;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author Budi <budi@dominopos.com>
 */
class SendEmailBeforePaymentExpiredCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'payment-transaction:email-before-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to send reminder via email before the payment/transaction expired.';

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
        $transactionIds = trim($this->option('transaction-id'));

        $selectedTransactions = [];
        if (! empty($transactionIds)) {
            $selectedTransactions = explode(',', $transactionIds);

            // if (count($selectedTransactions) > 0) {
                // $this->info("List of transaction ID that will be processed");
                // foreach($selectedTransactions as $transactionId) {
                    // $this->info("--  {$transactionId}");
                // }

                // $this->info("Getting SELECTED transactions...");
            // }
        }
        else {
            // $this->info("No transaction ID is supplied.");
            // $this->info("Getting ALL pending transactions...");
        }

        $this->processTransactions($selectedTransactions);
    }

    public function processTransactions($transactionIds = [])
    {
        $payments = PaymentTransaction::with(['details', 'user', 'midtrans'])
                                        ->where('status', PaymentTransaction::STATUS_PENDING)
                                        // Limit the date so it doesn't process old pending transactions...
                                        ->where(DB::raw("DATE(created_at)"), '>=', Carbon::now()->subDays(1)->format('Y-m-d'))
                                        ->oldest();

        // Only send reminder to selected transaction if requested.
        if (count($transactionIds) > 0) {
            $payments->whereIn('payment_transaction_id', $transactionIds);
        }

        $payments = $payments->get();

        // Send reminder...
        if ($payments->count() > 0) {
            // $this->info("Processing {$payments->count()} transaction(s)...");

            $index = 0;
            foreach($payments as $payment) {
                try {
                    $index++;
                    $payment->user->notify(new BeforeExpiredPaymentNotification($payment));
                    $this->info("#{$index} Sending reminder for Transaction: {$payment->payment_transaction_id}... OK");
                } catch (Exception $e) {
                    $this->error("#{$index} Sending reminder for Transaction: {$payment->payment_transaction_id}... FAIL");
                }
            }
        }
        else {
            $this->info("No pending transaction is found.");
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
        return array(
            array('transaction-id', null, InputOption::VALUE_OPTIONAL, 'Transaction IDs that will be processed.', null),
        );
    }
}
