<?php

class CampaignHistoryAction extends Eloquent
{
    /**
     * CampaignHistoryAction Model
     *
     * @author Shelgi <shelgi@dominopos.com>
     */

    protected $table = 'campaign_history_actions';

    protected $primaryKey = 'campaign_history_action_id';

    public function campaignHistories()
    {
        return $this->belongsTo('CampaignHistories', 'campaign_history_action_id', 'campaign_history_action_id');
    }

    public static function getIdFromAction($action)
    {
        return CampaignHistoryAction::where('action_name', '=', $action)->pluck('campaign_history_action_id');
    }

}