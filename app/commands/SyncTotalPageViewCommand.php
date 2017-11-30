<?php
/**
 * Update single detail page view total counter based on object page views count
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Orbit\FakeJob;
use Orbit\Queue\Elasticsearch\ESCouponUpdateQueue;
use Orbit\Queue\Elasticsearch\ESPromotionUpdateQueue;
use Orbit\Queue\Elasticsearch\ESNewsUpdateQueue;
use Orbit\Queue\Elasticsearch\ESMallUpdateQueue;
use Orbit\Queue\Elasticsearch\ESStoreUpdateQueue;

class SyncTotalPageViewCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'sync:total-page-view';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update single detail page view total counter based on object page views count.';

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
            $objectId = trim($this->option('object-id'));
            $objectType = strtolower(trim($this->option('object-type')));

            if (empty($objectId)) {
                throw new Exception("object-id is required.", 1);
            }

            if (empty($objectType)) {
                throw new Exception("object-type is required.", 1);
            }

            $acceptedTypes = ['tenant', 'mall', 'news', 'promotion', 'coupon'];
            if (! in_array($objectType, $acceptedTypes)) {
                throw new Exception(sprintf("Invalid object-type, value(%s).", implode(',', $acceptedTypes)), 1);
            }

            // get object page view count for object id and type
            $objectViewCounts = ObjectPageView::select('location_id', DB::raw('count(object_page_view_id) as total'))
                ->where('object_type', $objectType)
                ->where('object_id', $objectId)
                ->groupBy('location_id')
                ->get();

            // loop through object view counts
            foreach ($objectViewCounts as $viewCounts) {
                $totalObjectViews = TotalObjectPageView::where('object_type', $objectType)
                    ->where('object_id', $objectId)
                    ->where('location_id', $viewCounts->location_id)
                    ->first();

                if (! is_object($totalObjectViews)) {
                    // create new if not found
                    $totalObjectViews = new TotalObjectPageView();
                    $totalObjectViews->object_id = $objectId;
                    $totalObjectViews->object_type = $objectType;
                    $totalObjectViews->location_id = $viewCounts->location_id;
                }

                $totalObjectViews->total_view = $viewCounts->total;
                $totalObjectViews->save();

                // sync to ElasticSearch
                $fakeJob = new FakeJob();

                switch ($objectType) {
                    case 'tenant':
                        $prefix = DB::getTablePrefix();
                        $baseMerchant = BaseMerchant::select(DB::raw("{$prefix}base_merchants.name as name, {$prefix}countries.name as country_name"))
                            ->join('countries', 'countries.country_id', '=', 'base_merchants.country_id')
                            ->where('base_merchant_id' , $objectId)
                            ->first();

                        $data = [
                            'name'    => $baseMerchant->name,
                            'country' => $baseMerchant->country_name
                        ];
                        $esQueue = new ESStoreUpdateQueue();
                        break;

                    case 'mall':
                        $data = [
                            'mall_id' => $objectId
                        ];

                        $esQueue = new ESMallUpdateQueue();
                        break;

                    case 'coupon':
                        $data = [
                            'coupon_id' => $objectId
                        ];
                        $esQueue = new ESCouponUpdateQueue();
                        break;

                    case 'news':
                        $data = [
                            'news_id' => $objectId
                        ];

                        $esQueue = new ESNewsUpdateQueue();
                        break;

                    case 'promotion':
                        $data = [
                            'news_id' => $objectId
                        ];

                        $esQueue = new ESPromotionUpdateQueue();
                        break;

                    default:
                        # code...
                        break;
                }

                $esResponse = NULL;
                if (! empty($data)) {
                    $response = $esQueue->fire($fakeJob, $data);
                    $esResponse = 'SUCCESS';
                    if ($response['status'] === 'fail') {
                        $esResponse = 'FAILED';
                    }
                }

                $fakeJob->delete();

                $this->info(sprintf('Total Page View Synced For ID: %s;ES Status: %s;Type: %s;Loc: %s;Count: %s', $objectId, $esResponse, $objectType, empty($viewCounts->location_id) ? 'GTM' : $viewCounts->location_id, $viewCounts->total));
            }
        } catch (Exception $e) {
            $this->error(sprintf('Total Page View Synced Failed For ID: %s;Type: %s;Error: %s;Line: %s;', $objectId, $objectType, $e->getMessage(), $e->getLine()));
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
            array('object-id', null, InputOption::VALUE_REQUIRED, 'Object ID', null),
            array('object-type', null, InputOption::VALUE_REQUIRED, 'Object type of the page (tenant,mall,news,promotion,coupon)', null),
        );
    }
}
