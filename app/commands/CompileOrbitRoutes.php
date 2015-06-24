<?php
/**
 * Compile all registered routes into single file for better performance.
 * All files inside app/routes will be scanned concatenate into one file.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use OrbitShop\API\v1\Helper\RecursiveFileIterator;

class CompileOrbitRoutes extends Command
{
    /**
     * Name of the compiled file.
     *
     * @var string
     */
    const COMPILED_ROUTES = 'orbit-compiled-routes.php';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'routes:compile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compile all routes into single file for better performance.';

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
        $output = $this->option('output');

        if (empty($output)) {
            // Default to app/routes directories with file name `orbit-compiled-routes.php`
            $output = app_path() . DIRECTORY_SEPARATOR . 'routes/orbit-compiled-routes.php';
        }
        $dirname = dirname($output);

        // Callback which returns only 'php' extension
        $onlyPHPExt = function($file, $fullPath)
        {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                return TRUE;
            }

            return FALSE;
        };

        // Write some header to the compiled file
        file_put_contents($output, $this->getCompiledTemplateHeader());

        $recursiveIterator = RecursiveFileIterator::create($dirname)
                                ->setCallbackMatcher($onlyPHPExt)
                                ->includeFullPath();

        foreach ($recursiveIterator->get() as $file) {

            if (basename($file) === static::COMPILED_ROUTES) {
                continue;
            }

            $this->info('Compiling file ' . $file . '...');
            $content = trim(file_get_contents($file));

            /* Remove the '<?php and ?> */
            $removedTag = preg_replace('/^<\?php(.*)(\?>)?$/s', '$1', $content);
            $content = "\n\n";
            $content .= "// ------------------------------------------------- \n";
            $content .= "// $file \n";
            $content .= "// ------------------------------------------------- \n\n";
            $content .= $removedTag . "\n\n";

            file_put_contents($output, $content, FILE_APPEND);
        }

        $this->info('All routes has been compiled to ' . $output);
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
            array('output', null, InputOption::VALUE_OPTIONAL, 'Output directory of the compiled routes file.', null),
        );
    }

    /**
     * Template of the compiled header.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return string
     */
    protected function getCompiledTemplateHeader()
    {
        $date = gmdate('Y-m-d H:i:s');
        return <<<TEMPLATE
<?php
/**
 * Orbit compiled headers. Generated on {$date} UTC
 */
TEMPLATE;
    }

}
