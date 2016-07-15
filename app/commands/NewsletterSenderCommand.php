<?php
/**
 * Command to send an email which get from a table. The table
 * SHOULD at least have a column named 'email'.
 *
 * @author Rio Astamal <rio@dominopos.com>
 */
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\Helper\Email\MXEmailChecker;

class NewsletterSenderCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'newsletter:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send newsletter to users.';

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
        // Do while there is a record in the table
        $start = microtime(TRUE);

        $tableName = $this->option('email-table');
        $totalEmails = DB::table($tableName)->count();

        if ($totalEmails === 0) {
            $this->info(sprintf('No emails found on table %s.', $tableName));
            exit(0);
        }

        $this->mkdirLog($tableName);

        $filePlain = $this->option('body-plain');
        if (! file_exists($filePlain)) {
            throw new Exception('Email body plain text format not found. ' . $filePlain);
        }
        $fileHtml = $this->option('body-html');
        if (! file_exists($filePlain)) {
            throw new Exception('Email body html format not found. ' . $fileHtml);
        }
        $bodyPlain = file_get_contents($filePlain);
        $bodyHtml = file_get_contents($fileHtml);
        $processed = 0;
        $skip = 0;
        $limit = (int)$this->option('limit');

        while (true) {
            $row = DB::table($tableName)->skip($skip)->take($limit)->first();
            if (empty($row)) {
                $this->info(sprintf('No emails found on table %s.', $tableName));
                break;
            }

            $date = date('Y-m-d H:i:s');
            $email = trim($row->email);
            $message = 'Email successfully sent';
            $cmdPrint = 'info';

            try {
                $this->checkMX($email);
                $status = $this->sendEmailTo($email, $bodyPlain, $bodyHtml);
                $this->deleteEmail($tableName, $email);
            } catch (Exception $e) {
                $status = 0;
                $message = $e->getMessage();
                $cmdPrint = 'error';

                if ($e->getMessage() === 'MX Records not found') {
                    $this->deleteEmail($tableName, $email);
                }
            }
            $processed++;

            $stdout = sprintf('[%s] - %s: %s (%s/%s) - %s',
                $date,
                $status ? 'SUCCESS' : 'FAIL',
                $email,
                $processed,
                $totalEmails,
                $message
            );

            $this->{$cmdPrint}($stdout);

            $log = sprintf('%s, %s, %s, %s',
                $date,
                $email,
                $status ? 'SUCCESS' : 'FAIL',
                $message
            );
            $this->writeEmailLog($log);

            if ($this->option('dry-run')) {
                $skip++;
            }

            if ($limit !== -1) {
                if ($processed >= $limit) { break; }
            }

            // Sleep 1/10 seconds
            usleep(100000);
        }

        $end = microtime(TRUE);
        $this->info(sprintf('Time taken %ss', $end - $start));
    }

    /**
     * Delete particular email from table.
     *
     * @param string $tableName
     * @param string $email
     * @return void
     */
    protected function deleteEmail($tableName, $email)
    {
        if (! $this->option('dry-run')) {
            $row = DB::table($tableName)->where('email', $email)->delete();
        }
    }

    /**
     * Check MX Record validity
     *
     * @param string $email
     * @return void
     */
    protected function checkMX($email)
    {
        $mxRecords = MXEmailChecker::create($email)->check()->getMXRecords();
        if (empty($mxRecords)) {
            throw new Exception('MX Records not found');
        }
    }

    /**
     * Write the status of sending log file.
     *
     * @param string $message
     * @return void
     */
    protected function writeEmailLog($message)
    {
        if ($this->option('dry-run')) {
            return;
        }

        $tableName = $this->option('email-table');
        $filename = storage_path() . '/logs/' . $tableName . '/' . $this->option('logfile');
        file_put_contents($filename, $message . "\n", FILE_APPEND);
    }

    /**
     * Send email using swift mailer.
     *
     * @param string $addr
     * @param string $plain
     * @param string $
     * @return int
     */
    protected function sendEmailTo($to, $bodyPlain, $bodyHTML)
    {
        if ($this->option('dry-run')) {
            return 1;
        }

        $config = require $this->option('email-config');
        $subject = $this->option('subject');

        $transport = Swift_SmtpTransport::newInstance($config['server'], $config['port'], $config['ssl']);
        if (isset($config['username']) && ! empty($config['username'])) {
            $transport->setUsername($config['username']);

            if (isset($config['password']) && ! empty($config['password'])) {
                $transport->setPassword($config['password']);
            }
        }

        $mailer = Swift_Mailer::newInstance($transport);
        $message = Swift_Message::newInstance($subject)
                        ->setFrom($config['from'])
                        ->setTo($to)
                        ->setBody($bodyPlain)
                        ->addPart($bodyHTML, 'text/html');

        return $mailer->send($message);
    }


    /**
     * Create directory to store the logs.
     *
     * @param string $dirname
     * @return void
     */
    protected function mkdirLog($dirname)
    {
        $path = storage_path() . '/logs/' . $dirname;

        if (! file_exists($path)) {
            mkdir($path);
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
        );
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array(
            array('email-table', null, InputOption::VALUE_REQUIRED, 'Table to get the list of emails.', null),
            array('subject', null, InputOption::VALUE_REQUIRED, 'Subject of the email.', null),
            array('body-plain', null, InputOption::VALUE_REQUIRED, 'Email contents in plain text format.', null),
            array('body-html', null, InputOption::VALUE_REQUIRED, 'Email contents in HTML format.', null),
            // SMTP Server file
            // ----------------
            // <?php
            // return [
            //      'server' => 'localhost',
            //      'port' => '25'
            //      'ssl' => NULL,
            //      'username' => 'user',
            //      'password' => 'password',
            //      'from' => [FROM]
            // ];
            array('email-config', null, InputOption::VALUE_REQUIRED, 'Credentials to connect to SMTP.', null),
            array('logfile', null, InputOption::VALUE_OPTIONAL, 'The name of the log file.', 'email.log'),
            array('limit', null, InputOption::VALUE_OPTIONAL, 'Limit the number of emails processed.', -1),
            array('dry-run', null, InputOption::VALUE_NONE, 'Sending email to logs.', null),
            array('no-mx-check', null, InputOption::VALUE_NONE, 'Disable MX necord check.', null)
        );
    }
}
