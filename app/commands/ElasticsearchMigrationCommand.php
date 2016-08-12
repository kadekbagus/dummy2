<?php
/**
 * Command to do migration on Elasticsearch index.
 *
 * @author Rio Astamal <rio@dominopos.com>
 * @todo Implements rollback feature
 */
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Elasticsearch\ClientBuilder;

class ElasticsearchMigrationCommand extends Command
{
    protected $elasticDataDir = '';
    protected $fileExt = 'esm';
    protected $es = NULL;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'esmigrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elasticsearch index migrations';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->elasticDataDir = app_path() . '/database/elasticsearch-migrations';
        $this->es = ClientBuilder::create()->setHosts(Config::get('orbit.elasticsearch.hosts'))->build();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        $mode = $this->option('mode');

        switch ($mode) {
            case 'create':
                $fname = $this->option('filename');
                if (empty($fname)) {
                    throw new Exception('A file name must be given using --filename=name_of_the_file.');
                }
                $now = date('Y_m_d_His');
                $fname = $now . '_' . $fname;
                $fullpathMigration = $this->elasticDataDir . '/migrations' . '/' . $fname . '.' . $this->fileExt;
                $fullpathRollback = $this->elasticDataDir . '/rollback' . '/' . $fname . '.' . $this->fileExt;

                $this->info('Migration file: ' . $fullpathMigration);
                touch($fullpathMigration);
                $this->info('Rollback file: ' . $fullpathRollback);
                touch($fullpathRollback);
                break;

            case 'migrate':
            default:
                $mode = 'migrate';
                if ($this->option('rollback')) {
                    $mode = 'rollback';
                }

                $files = $this->getMigrationFiles($mode);

                if (count($files) === 0) {
                    $this->info('Nothing to ' . $mode . '.');
                    return;
                }

                $this->esMigrate($files, $mode);
                break;
        }
    }

    /**
     * Run the real migration.
     *
     * @param array $files List of files
     * @param string $directory Name of directory containing files to migrate
     * @return void
     */
    protected function esMigrate($files, $mode='migrate')
    {
        // Default set to migrate
        $directory = 'migrations';
        $suffixInfo = 'Migrated:';

        if ($mode === 'rollback') {
            $directory = 'rollback';
            $suffixInfo = 'Rollback:';
        }

        foreach ($files as $file) {
            $success = FALSE;
            $fullpath = $this->elasticDataDir . '/' . $directory . '/' . $file;
            $origJson = file_get_contents($fullpath);
            $json = json_decode($origJson, TRUE);   // associative array

            if ($this->option('dry-run')) {
                $this->info(sprintf("[DRY RUN] %s %s", $suffixInfo, $file));
                readfile($fullpath);
                echo "\n";
                continue;
            }

            $indexPrefix = Config::get('elasticsearch.indices_prefix');
            $params = [
                'index' => $indexPrefix . $json['index'],
                'body' => $json['es_data']
            ];
            switch ($json['action']) {
                case 'create_index':
                    $response = $this->es->indices()->create($params);
                    if (isset($response['acknowledged']) && isset($response['acknowledged'])) {
                        $success = TRUE;
                    }
                    break;

                case 'delete_index':
                    $response = $this->es->indices()->delete(['index' => $json['index']]);
                    if (isset($response['acknowledged']) && isset($response['acknowledged'])) {
                        $success = TRUE;
                    }
                    break;

                case 'update_setting':
                    $response = $this->es->indices()->putSettings($params);
                    if (isset($response['acknowledged']) && isset($response['acknowledged'])) {
                        $success = TRUE;
                    }
                    break;

                case 'update_mapping':
                    $response = $this->es->indices()->putMapping($params);
                    if (isset($response['acknowledged']) && isset($response['acknowledged'])) {
                        $success = TRUE;
                    }
                    break;

                default:
                    $this->error(sprintf('Unknown action "%s" on json document.', $json['action']));
                    break;
            }

            if ($mode === 'rollback') {
                unlink($this->elasticDataDir . '/migrated/' . $file);
            } else {
                $this->writeMigratedFile($file);
            }

            if (! $success) { continue; }
            $this->info(sprintf('%s %s', $suffixInfo, $file));
        }
    }

    /**
     * Get list of files need to migrate.
     *
     * @param string $mode
     * @return array
     */
    protected function getMigrationFiles($mode='migrate')
    {
        $onlyName = function($file) {
            return pathinfo($file, PATHINFO_BASENAME);
        };

        $env = App::environment();

        if ($mode === 'rollback') {
            $rolledbackFiles = array_map($onlyName, glob($this->elasticDataDir . '/' . '/migrated/' . $env . '/*.esm'));

            // Reverse the order, because rollback should happens from newest to oldest
            rsort($rolledbackFiles);

            return $rolledbackFiles;
        }

        $migrationsDir = array_map($onlyName, glob($this->elasticDataDir . '/migrations/*.esm'));
        $migratedDir = array_map($onlyName, glob($this->elasticDataDir . '/migrated/' . $env . '/*.esm'));

        return array_diff($migrationsDir, $migratedDir);
    }

    /**
     * Write the contents to the migrated file.
     *
     * @param string $file Name of the file.
     * @return boolean
     */
    protected function writeMigratedFile($file)
    {
        $env = App::environment();

        // Get the migrated directory for current working environment
        $migratedDir = $this->elasticDataDir . '/migrated/' . $env;

        if (! file_exists($migratedDir)) {
            @mkdir($migratedDir, 0755, TRUE);
        }

        // Copy the contents of what is inside /rollback/$file to the
        // migrated. We can use function copy() also.
        $rollbackFile = $this->elasticDataDir . '/rollback/' . $file;
        $migratedFile = $migratedDir . '/' . $file;

        return copy($rollbackFile, $migratedFile);
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
            array('mode', null, InputOption::VALUE_OPTIONAL, 'Run mode, `migrate` or `create`.', 'migrate'),
            array('filename', null, InputOption::VALUE_OPTIONAL, 'Name of the migration file, e.g: create_malls_index_mall_type.', null),
            array('dry-run', null, InputOption::VALUE_NONE, 'Run in dry-run mode, no data will be sent to Elasticsearch.', null),
            array('rollback', null, InputOption::VALUE_NONE, 'Do a rollback of the migrated indices.', null),
        );
    }
}