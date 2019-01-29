<?php
/**
 * Command to get sepulsa voucher list
 * @author Ahmad Anshori <ahmad@dominopos.com>
 */

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\FakeJob;
use Orbit\Helper\Sepulsa\API\VoucherList;

class GetSepulsaVoucherListCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'sepulsa:voucher-list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Obtain sepulsa voucher list';

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
        try {
            $config = Config::get('orbit.partners_api.sepulsa');
            // display current config
            if ($this->option('get-current-config')) {
                return var_dump($config);
            }
            $take = empty($this->option('take')) ? 100 : $this->option('take');
            $response = VoucherList::create($config)->getList('', $take, [], $page=1);

            if (isset($response->result->data) && ! empty($response->result->data)) {
                if (empty($this->option('output')) || $this->option('output') === 'json') {
                    $output = json_encode($response->result->data);
                    if ($this->option('min')) {
                        $outputArr = [];
                        foreach ($response->result->data as $record) {
                            $newRecord = new stdClass();
                            $newRecord->token = $record->token;
                            $newRecord->title = $record->title;
                            $newRecord->merchant_name = $record->merchant_name;
                            $outputArr[] = $newRecord;
                        }
                        $output = json_encode($outputArr);
                    }
                    if (empty ($this->option('email-to'))) {
                        $this->info($output);
                    } else {
                        $filename = sprintf('/tmp/sepulsa-voucher-list-%s.json', date('Ymd-His'));
                        $fileIO = fopen($filename, 'w+');
                        fputs($fileIO, $output);
                        fclose($fileIO);
                        $this->sendMail($filename);
                    }
                } elseif ($this->option('output') === 'csv') {
                    $filename = sprintf('/tmp/sepulsa-voucher-list-%s.csv', date('Ymd-His'));
                    $fileIO = fopen($filename, 'w+');
                    if ($this->option('min')) {
                        $csvTitle = fputcsv($fileIO, ['token', 'title', 'merchant_name']);
                    } else {
                        $csvTitle = fputcsv($fileIO, array_keys((array) $response->result->data[0]));
                    }
                    foreach ($response->result->data as $record) {
                        if ($this->option('min')) {
                            $newRecord = new stdClass();
                            $newRecord->token = $record->token;
                            $newRecord->title = $record->title;
                            $newRecord->merchant_name = $record->merchant_name;
                            $record = $newRecord;
                        }
                        $record = (array) $record;
                        fputcsv($fileIO, $record);
                    }
                    fclose($fileIO);
                    $csvFile = file($filename);
                    if (empty ($this->option('email-to'))) {
                        // output
                        foreach ($csvFile as $line) {
                            $this->info($line);
                        }
                    } else {
                        $this->sendMail($filename);
                    }
                } else {
                    $this->error('Unsupported output');
                }
            } else {
                $this->info('No result.');
            }
        } catch (\Exception $e) {
            $this->error(print_r([$e->getMessage(), $e->getFile(), $e->getLine()]));
        }
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
            array('output', null, InputOption::VALUE_OPTIONAL, 'Set the output (json or csv).', null),
            array('email-to', null, InputOption::VALUE_OPTIONAL, 'Send output to email(s) separated by comma.', null),
            array('take', null, InputOption::VALUE_OPTIONAL, 'Set the take parameter. Default: 100', null),
            array('get-current-config', null, InputOption::VALUE_NONE, 'Return currently used sepulsa config.', null),
            array('min', null, InputOption::VALUE_NONE, 'Output only voucher token, voucher title and merchant name.', null),
        );
    }

    /**
     * Fake response
     *
     * @param boolean $dryRun
     */
    protected function sendMail($data)
    {
        Mail::send('emails.sepulsa-voucher-list.html', [], function($message) use ($data)
        {
            $from = 'no-reply@gotomalls.com';
            $emails = explode(',', $this->option('email-to'));

            $message->from($from, 'Gotomalls Robot');
            $message->subject('Sepulsa Voucher List');
            $message->to($emails);
            $message->attach($data);
        });

        $this->info('Mail Sent.');
    }

}
