<?php

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class CampaingPageViewMigrationCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'campaignpageview:migration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migration old data to table object_page_views.';

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
        $dryRun = $this->option('dry-run');
        $prefix = DB::getTablePrefix();
        $take = 50;
        $skip = 0;
        $total_view = 1;

        if ($dryRun) {
            $this->info('[DRY RUN MODE - Not Insert on DB] ');
        }

        do {
            $news_promotions = CampaignPageView::select(
                                    'news.news_name',
                                    'news.object_type',
                                    'campaign_page_views.campaign_id',
                                    'campaign_page_views.user_id',
                                    'campaign_page_views.location_id',
                                    'campaign_page_views.activity_id',
                                    DB::raw("
                                        CASE WHEN {$prefix}campaign_status.campaign_status_name = 'expired'
                                        THEN {$prefix}campaign_status.campaign_status_name
                                        ELSE (CASE WHEN {$prefix}news.end_date < (SELECT min(CONVERT_TZ(UTC_TIMESTAMP(), '+00:00', ot.timezone_name))
                                                        FROM {$prefix}news_merchant onm
                                                            LEFT JOIN {$prefix}merchants om ON om.merchant_id = onm.merchant_id
                                                            LEFT JOIN {$prefix}merchants oms on oms.merchant_id = om.parent_id
                                                            LEFT JOIN {$prefix}timezones ot ON ot.timezone_id = (CASE WHEN om.object_type = 'tenant' THEN oms.timezone_id ELSE om.timezone_id END)
                                                        WHERE onm.news_id = {$prefix}news.news_id)
                                       THEN 'expired' ELSE {$prefix}campaign_status.campaign_status_name END) END AS campaign_status
                                    "))
                                    ->join('news', 'news.news_id', '=', 'campaign_page_views.campaign_id')
                                    ->join('campaign_status', 'campaign_status.campaign_status_id', '=', 'news.campaign_status_id')
                                    ->havingRaw("campaign_status not in ('expired', 'stopped')")
                                    ->take($take)
                                    ->skip($skip)
                                    ->get();

            $skip = $take + $skip;
            foreach ($news_promotions as $key => $news_promotion) {
                $object_page_view = new ObjectPageView();
                $object_page_view->object_type = $news_promotion->object_type;
                $object_page_view->object_id = $news_promotion->news_id;
                $object_page_view->user_id = $news_promotion->user_id;
                $object_page_view->location_id = $news_promotion->location_id;
                $object_page_view->activity_id = $news_promotion->activity_id;

                if (! $dryRun) {
                    $object_page_view->save();
                }
                $this->info(sprintf("Insert campaign %s, object_type %s, status %s", $news_promotion->news_name, $news_promotion->object_type, $news_promotion->campaign_status));

                $total_object_page_view = TotalObjectPageView::where('object_type', $news_promotion->object_type)
                                            ->where('object_id', $news_promotion->object_id)
                                            ->where('location_id', $news_promotion->location_id)
                                            ->first();

                if (! empty($total_object_page_view)) {
                    $total_object_page_view->total_view = $total_view++;

                    if (! $dryRun) {
                        $total_object_page_view->save();
                    }

                    $this->info(sprintf("Update total campaign view %s, campaign name %s, object_type %s, status %s", $total_view, $news_promotion->news_name, $news_promotion->object_type, $news_promotion->campaign_status));
                } else {
                    $new_total_object_page_view = new TotalObjectPageView();
                    $new_total_object_page_view->object_type = $news_promotion->object_type;
                    $new_total_object_page_view->object_id = $news_promotion->news_id;
                    $new_total_object_page_view->location_id = $news_promotion->location_id;
                    $new_total_object_page_view->total_view = $news_promotion->total_view;

                    if (! $dryRun) {
                        $new_total_object_page_view->save();
                    }

                    $this->info(sprintf("Inser total campaign view %s, campaign name %s, object_type %s, status %s", $total_view, $news_promotion->news_name, $news_promotion->object_type, $news_promotion->campaign_status));
                }
            }

            if (count($news_promotions) > 1) {
                $this->info(sprintf("Done Insert %s data", $skip));
            }
        } while (count($news_promotions) > 1);
        $this->info("Done");
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
            array('dry-run', null, InputOption::VALUE_NONE, 'Dry run, do not Insert to object_page_views.', null),
        );
    }

}
