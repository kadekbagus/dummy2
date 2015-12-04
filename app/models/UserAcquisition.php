<?php

/**
 * @property string user_id
 * @property string acquirer_id
 * @property string user_acquisition_id
 */
class UserAcquisition extends Eloquent {
    /**
     * UserAcquisition Model relates a user to a specific mall / merchant.
     *
     * This is created when the local box does not have the record for the user and asks
     * the cloud for the user info. The cloud server stores the relation of the user and
     * the mall / merchant so it can send user-specific data to the mall/merchant's box.
     *
     * @author William
     */

    protected $table = 'user_acquisitions';

    protected $primaryKey = 'user_acquisition_id';

    public function user()
    {
        return $this->belongsTo('User', 'user_id', 'user_id')->excludeDeleted();
    }

    /**
     * If SymmetricDS is configured, this forces a reload of the box's copy of the user's data.
     *
     * This is called after saving a new UserAcquisition so we don't have to pass all the data over the web.
     *
     * See: SymmetricDS User Guide section 5.1.5 (Manage > Nodes > Send)
     *
     * @param bool $reloadUserData Reload user data (users, user_details, apikeys). user_personal_interests always reloaded.
     */
    public function forceBoxReloadUserData($reloadUserData = true)
    {
        $connections = Config::get('database.connections', array());
        if (!isset($connections['symmetric'])) {
            return;
        }

        $conn = DB::connection('symmetric');
        $symPrefix = $conn->getTablePrefix();
        $defaultConnection = DB::connection();
        $orbPrefix = $defaultConnection->getTablePrefix();
        $quotedId = $defaultConnection->getPdo()->quote($this->user_id, PDO::PARAM_STR);

        $insertQuery = "INSERT INTO ${symPrefix}data (node_list, table_name, event_type, row_data, trigger_hist_id, channel_id, create_time)
        SELECT :acquirer_node_id , t.source_table_name, :event_type, :condition, th.trigger_hist_id, t.channel_id, CURRENT_TIMESTAMP
        FROM ${symPrefix}trigger t INNER JOIN ${symPrefix}trigger_hist th ON (th.trigger_hist_id = (SELECT MAX(trigger_hist_id) FROM ${symPrefix}trigger_hist WHERE trigger_id = t.trigger_id))
        WHERE t.source_table_name = :source_table_name AND t.sync_on_update = 1
        ";
        $params = [
            'acquirer_node_id' => $this->acquirer_id,
            'event_type' => 'R',  // RELOAD
            'condition' => 'user_id = ' . $quotedId
        ];

        if ($reloadUserData) {
            // RELOAD: users
            $params['source_table_name'] = $orbPrefix . 'users';
            $conn->insert($insertQuery, $params);

            // RELOAD: user_details
            $params['source_table_name'] = $orbPrefix . 'user_details';
            $conn->insert($insertQuery, $params);

            // RELOAD: apikey
            $params['source_table_name'] = $orbPrefix . 'apikeys';
            $conn->insert($insertQuery, $params);
        }

        // RELOAD: user personal interest
        $params['source_table_name'] = $orbPrefix . 'user_personal_interest';
        $conn->insert($insertQuery, $params);
    }
}
