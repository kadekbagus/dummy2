<?php

use Carbon\Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Orbit\Notifications\Payment\BeforeExpiredPaymentNotification;
use Orbit\Notifications\Pulsa\ReminderPaymentNotification as PulsaReminderPaymentNotification;
use Orbit\Notifications\DigitalProduct\ReminderPaymentNotification as DigitalProductReminderPaymentNotification;

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
        $withinDays = $this->option('within-days');

        $selectedTransactions = [];
        if (! empty($transactionIds)) {
            $selectedTransactions = explode(',', $transactionIds);
        }

        $this->processTransactions($selectedTransactions, $withinDays);
    }

    /**
     * Process pending transactions.
     *
     * @param  array  $transactionIds [description]
     * @return [type]                 [description]
     */
    public function processTransactions($transactionIds = [], $withinDays)
    {
        $payments = PaymentTransaction::with(['details.pulsa', 'details.digital_product', 'details.provider_product', 'user', 'midtrans'])
                                        ->where('status', PaymentTransaction::STATUS_PENDING)
                                        // Limit the date so command will not process old pending transactions...
                                        ->where(DB::raw("DATE(created_at)"), '>=', Carbon::now()->subDays($withinDays)->format('Y-m-d'))
                                        ->oldest();

        // Only send reminder to selected transaction if requested.
        if (count($transactionIds) > 0) {
            $payments->whereIn('payment_transaction_id', $transactionIds);
        }

        $payments = $payments->get();

        // Send reminder...
        if ($payments->count() > 0) {
            $number = 0;
            foreach($payments as $payment) {
                try {
                    $number++;
                    if ($payment->forPulsa()) {
                        $payment->user->notify(new PulsaReminderPaymentNotification($payment));
                    }
                    else if ($payment->forDigitalProduct()) {
                        $payment->user->notify(new DigitalProductReminderPaymentNotification($payment));
                    }
                    else {
                        $payment->user->notify(new BeforeExpiredPaymentNotification($payment));
                    }

                    $this->info("#{$number} Sending reminder for Transaction: {$payment->payment_transaction_id}... OK");
                } catch (Exception $e) {
                    $this->error("#{$number} Sending reminder for Transaction: {$payment->payment_transaction_id}... FAIL");
                }
            }
        }
        else {
            $this->info("No pending transaction is found in the last {$withinDays} days.");
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
            array('transaction-id', null, InputOption::VALUE_OPTIONAL, 'Transaction IDs that will be processed, separated by comma. If no value provided, it will process all pending transactions.', ''),
            array('within-days', null, InputOption::VALUE_OPTIONAL, 'Number of the last {x} days of transactions that will be processed. It is useful for bypassing old pending transactions.', 3),
        );
    }
}
