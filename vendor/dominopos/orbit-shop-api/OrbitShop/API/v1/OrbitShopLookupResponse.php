<?php namespace OrbitShop\API\v1;
/**
 * OrbitShop LookupResposne interface implementation.
 * This is used to lookup the client id and secret key.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use DominoPOS\OrbitAPI\v10\LookupResponseInterface;
use Illuminate\Support\Facades\Config;
use Apikey;

class OrbitShopLookupResponse implements LookupResponseInterface
{
    /**
     * Data storing the clint id and secret keys
     *
     * @var array
     */
    protected $data = array(
        'client_id'     => '',
        'secret_key'    => '',
        'user_id'       => NULL,
        'user'          => NULL,
    );

    /**
     * Status lookup.
     *
     * @var int
     */
    protected $statusLookup;

    /**
     * Class constructor.
     *
     * @throw An Orion\SimpleAPI\APIException if $clienID not found
     */
    public function __construct($clientID)
    {
        $this->statusLookup = static::LOOKUP_STATUS_OK;

        // Search the client ID that stored in database
        $apiKeys = static::getApiKeys($clientID);

        if (empty($apiKeys) === TRUE) {
            $this->statusLookup = static::LOOKUP_STATUS_NOT_FOUND;
        } else {
            if ($apiKeys->status == 'blocked') {
                $this->statusLookup = static::LOOKUP_STATUS_ACCESS_DENIED;
            } else {
                $this->data['client_id'] = $clientID;
                $this->data['secret_key'] = $apiKeys->api_secret_key;

                // If the user's property is object, we can assume that
                // The user is found
                if (is_object($apiKeys->user)) {
                    $denied = array('blocked', 'pending', 'deleted');
                    if (in_array($apiKeys->user->status, $denied)) {
                        $this->statusLookup = static::LOOKUP_STATUS_ACCESS_DENIED;
                    } else {
                        $this->data['user_id'] = $apiKeys->user_id;
                        $this->data['user'] = $apiKeys->user;
                    }
                } else {
                    // The user status probably deleted
                    $this->statusLookup = static::LOOKUP_STATUS_ACCESS_DENIED;
                }
            }
        }
    }

    public function getClientID()
    {
        return $this->data['client_id'];
    }

    public function getClientSecretKey()
    {
        return $this->data['secret_key'];
    }

    public function getStatus()
    {
        return $this->statusLookup;
    }

    public function getUserId()
    {
        return $this->data['user_id'];
    }

    public function getUser()
    {
        return $this->data['user'];
    }

    /**
     * Search the corresponding client key 1) from cache 2) from database.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @paramm string $clientID - The client API Key
     * @return User
     */
    public static function getApiKeys($clientID)
    {
        $result = Config::get('orbitapi.lookup.response.' . $clientID);
        if (is_object($result)) {
            return $result;
        }

        $result = Apikey::with(array('user'))
                        ->excludeDeleted()
                        ->where('api_key', '=', $clientID)
                        ->first();
        Config::set('orbitapi.lookup.response.' . $clientID, $result);

        return $result;
    }
}
