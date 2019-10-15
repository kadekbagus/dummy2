<?php

use Carbon\Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Orbit\Notifications\Pulsa\Subscription\PulsaPriceListNotification;

use Orbit\Controller\API\v1\Pub\Coupon\CouponListNewAPIController;
use Orbit\Controller\API\v1\Pub\News\NewsListNewAPIController;

/**
 * @author Budi <budi@dominopos.com>
 */
class SendPulsaPriceListCommand extends Command {

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'pulsa:send-customer-email-pricelist';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to send email of pulsa pricelist to customers/GTM User.';

    protected $user = null;

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
        $this->info("Getting list of customers...");
        $userList = UserExtended::select('users.user_id', 'users.user_email', 'users.user_firstname', 'users.user_lastname', 'pulsa_email_subscription')
            ->join('users', 'extended_users.user_id', '=', 'users.user_id')
            ->where('pulsa_email_subscription', 'yes');

        if ($this->option('users')) {
            $userIds = str_replace(' ', '', $this->option('users'));
            $userList->whereIn('extended_users.user_id', explode(',', $userIds));
        }

        $campaigns = $this->getCampaigns();

        $sent = 0;
        $userList->chunk($this->option('chunk'), function($users) use (&$sent, $campaigns) {
            $this->info("Sending email to {$users->count()} customers...");

            foreach($users as $user) {
                (new PulsaPriceListNotification($user, $campaigns))->send();
            }

            $sent += $users->count();
        });

        if ($sent > 0) {
            $this->info("Emails are on the way!");
        }
        else {
            $this->info("Looks like no one has subscribed yet. :/");
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
            array('users', null, InputOption::VALUE_OPTIONAL, "Send to specific user_id(s) (separated by comma).", ''),
            array('chunk', null, InputOption::VALUE_OPTIONAL, 'Number of customer to fetch per query.', 20),
        );
    }

    /**
     * Set user using random (last) customer, as a requirement for getting campaign list.
     *
     * @return  void
     */
    private function setUser()
    {
        $this->user = User::with(['role' => function($query) {
                $query->where('role_name', 'Consumer');
            }])
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Get campaign list.
     *
     * @return array
     */
    private function getCampaigns()
    {
        $this->setUser();

        $maxCampaignToDisplay = Config::get('orbit.subscription.max_campaign_to_display', 4);

        $_GET['skip'] = 0;
        $_GET['take'] = $maxCampaignToDisplay;

        return [
            'couponListUrl' => $this->generateLandingPageUrl('coupons'),
            'coupons' => $this->getCoupons(),

            'eventListUrl' => $this->generateLandingPageUrl('events'),
            'events' => $this->getEvents(),
        ];
    }

    /**
     * Get coupon list.
     *
     * @return array
     */
    private function getCoupons()
    {
        $campaignList = CouponListNewAPIController::create('raw')
            ->setUser($this->user)
            ->getCouponList();

        if ($campaignList->code !== 0) {
            return [];
        }

        $campaignListArray = [];
        foreach($campaignList->data->records as $campaign) {
            $campaignListArray[] = [
                'id' => $campaign['coupon_id'],
                'name' => $campaign['coupon_name'],
                'image_url' => $campaign['image_url'],
                'store_names' => $this->flattenStoreNames($campaign),
                'price_old' => 'Rp ' . number_format($campaign['price_old'], 0, '', ','),
                'price_selling' => 'Rp ' . number_format($campaign['price_selling'], 0, '', ','),
                'detail_url' => $this->generateCampaignUrl('coupons', $campaign['coupon_id'], $campaign['coupon_name']),
            ];
        }

        return count($campaignListArray) > 0
            ? array_chunk($campaignListArray, 2, true)
            : $campaignListArray;
    }

    /**
     * Flatten store names.
     *
     * @return string
     */
    private function flattenStoreNames($campaign)
    {
        $storeNames = '';
        if (isset($campaign['link_to_tenant']) && is_array($campaign['link_to_tenant'])) {
            $storeNames = $campaign['link_to_tenant'][0]['name'];
        }

        return $storeNames;
    }

    /**
     * Get event list.
     *
     * @return array
     */
    private function getEvents()
    {
        // $_GET['is_hot_event'] = 'yes';

        $campaignList = NewsListNewAPIController::create('raw')
            ->setUser($this->user)
            ->getSearchNews();

        if ($campaignList->code !== 0) {
            return [];
        }

        $campaignListArray = [];
        foreach($campaignList->data->records as $campaign) {
            $campaignListArray[] = [
                'id' => $campaign['news_id'],
                'name' => $campaign['news_name'],
                'image_url' => $campaign['image_url'],
                'location' => $this->flattenLocation($campaign),
                'is_hot_event' => isset($campaign['is_hot_event']) && $campaign['is_hot_event'] === 'yes',
                'detail_url' => $this->generateCampaignUrl('events', $campaign['news_id'], $campaign['news_name']),
            ];
        }

        return count($campaignListArray) > 0
            ? array_chunk($campaignListArray, 2, true)
            : $campaignListArray;
    }

    /**
     * Flatten event locations.
     *
     * @return string
     */
    private function flattenLocation($campaign)
    {
        $locationName = '';

        if (isset($campaign['link_to_tenant']) && is_array($campaign['link_to_tenant'])) {
            $locationName = $campaign['link_to_tenant'][0]['name'];
        }

        return $locationName;
    }

    /**
     * Generate full landing page url.
     *
     * @param  string $objectType [description]
     * @param  array  $utmParams  [description]
     * @return string
     */
    private function generateLandingPageUrl($objectType = '', $utmParams = [])
    {
        $utmStringParams = '?country=0';
        foreach($utmParams as $utmKey => $utmValue) {
            $utmStringParams .= "&{$utmKey}={$utmValue}";
        }

        return Config::get('orbit.base_landing_page_url', 'https://www.gotomalls.com')
            . "/{$objectType}{$utmStringParams}";
    }

    private function generateCampaignUrl($objectType = '', $campaignId, $campaignName)
    {
        $format = "/{$objectType}/%s/%s";
        return Config::get('orbit.base_landing_page_url', 'https://www.gotomalls.com')
            . sprintf($format, $campaignId, Str::slug($campaignName));
    }
}
