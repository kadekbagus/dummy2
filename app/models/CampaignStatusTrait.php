<?php
/**
 * Traits for storing common method that used by models campaign which has 'campaign_status'
 * column.
 *
 * @author Irianto <irianto@dominopos.com>
 */
trait CampaignStatusTrait
{
    /**
     * Method to append dot table after a table name. Used on every scope.
     *
     * @author Irianto <irianto@dominopos.com>
     * @param string $table
     * @return string
     */
    protected function appendDotTable($table=NULL)
    {
        if (! empty($table)) {
            // Append the dot using custom table name
            $table .= '.';
        }

        return $table;
    }

   /**
     * Scope to join with campaign status
     *
     * @author Irianto <irianto@dominopos.com>
     * @param Illuminate\Database\Query\Builder $query
     * @param string $table Table name
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeJoinCampaignStatus($query, $table=NULL)
    {
        return $query->leftJoin('campaign_status', 'campaign_status.campaign_status_id', '=', $this->appendDotTable($table) . 'campaign_status_id');
    }

   /**
     * Scope to filter records based on status field. Only return records which
     * had value 'not started'.
     *
     * @author Irianto <irianto@dominopos.com>
     * @param Illuminate\Database\Query\Builder $query
     * @param string $table Table name
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeNotStarted($query, $table=NULL)
    {
        return $query->joinCampaignStatus($table)
                ->where('campaign_status.campaign_status_name', 'not started');
    }

   /**
     * Scope to filter records based on status field. Only return records which
     * had value 'ongoing'.
     *
     * @author Irianto <irianto@dominopos.com>
     * @param Illuminate\Database\Query\Builder $query
     * @param string $table Table name
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeOngoing($query, $table=NULL)
    {
        return $query->joinCampaignStatus($table)
                ->where('campaign_status.campaign_status_name', 'ongoing');
    }

   /**
     * Scope to filter records based on status field. Only return records which
     * had value 'paused'.
     *
     * @author Irianto <irianto@dominopos.com>
     * @param Illuminate\Database\Query\Builder $query
     * @param string $table Table name
     * @return Illuminate\Database\Query\Builder
     */
    public function scopePaused($query, $table=NULL)
    {
        return $query->joinCampaignStatus($table)
                ->where('campaign_status.campaign_status_name', 'paused');
    }

   /**
     * Scope to filter records based on status field. Only return records which
     * had value 'stopped'.
     *
     * @author Irianto <irianto@dominopos.com>
     * @param Illuminate\Database\Query\Builder $query
     * @param string $table Table name
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeStopped($query, $table=NULL)
    {
        return $query->joinCampaignStatus($table)
                ->where('campaign_status.campaign_status_name', 'stopped');
    }

   /**
     * Scope to filter records based on status field. Only return records which
     * had value 'expired'.
     *
     * @author Irianto <irianto@dominopos.com>
     * @param Illuminate\Database\Query\Builder $query
     * @param string $table Table name
     * @return Illuminate\Database\Query\Builder
     */
    public function scopeExpired($query, $table=NULL)
    {
        return $query->joinCampaignStatus($table)
                ->where('campaign_status.campaign_status_name', 'expired');
    }

}
