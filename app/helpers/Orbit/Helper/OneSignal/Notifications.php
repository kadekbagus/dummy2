<?php namespace Orbit\Helper\OneSignal;
/**
 * OneSignal Notification helper
 *
 * @author Shelgi <shelgi@dominopos.com>
 */

class Notifications
{
    const NOTIFICATIONS_LIMIT = 50;

    protected $api;

    public function __construct(OneSignal $api)
    {
        $this->api = $api;
    }

    /**
     * Get information about all notifications.
     *
     * Application authentication key and ID must be set.
     *
     * @param int $limit  How many notifications to return (max 50)
     * @param int $offset Results offset (results are sorted by ID)
     *
     * @return array
     */
    public function getAll($limit = self::NOTIFICATIONS_LIMIT, $offset = 0)
    {
        $query = [
            'limit' => max(1, min(self::NOTIFICATIONS_LIMIT, $limit)),
            'offset' => max(0, $offset),
            'app_id' => $this->api->getConfig('app_id'),
        ];

        return $this->api->request('GET', '/notifications?'.http_build_query($query), [
            'Authorization' => 'Basic '.$this->api->getConfig('api_key'),
        ]);
    }

    /**
     * Get information about notification with provided ID.
     *
     * Application authentication key and ID must be set.
     *
     * @param string $id Notification ID
     *
     * @return array
     */
    public function getOne($id)
    {
        $url = '/notifications/'.$id.'?app_id='.$this->api->getConfig('app_id');

        return $this->api->request('GET', $url, [
            'Authorization' => 'Basic '.$this->api->getConfig('api_key'),
        ]);
    }

    /**
     * Send new notification with provided data.
     *
     * Application authentication key and ID must be set.
     *
     * @param array $data
     *
     * @return array
     */
    public function add(array $data)
    {
        $data['app_id'] = $this->api->getConfig('app_id');
        return $this->api->request('POST', '/notifications', [
            'Authorization' => 'Basic '.$this->api->getConfig('api_key'),
        ], $data);
    }

}
