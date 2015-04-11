<?php namespace OrbitShop\API\v1;
/**
 * OrbitShop Base API
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use DominoPOS\OrbitAPI\v10\API;
use DominoPOS\OrbitAPI\v10\Exception\APIException as OrbitAPIException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use OrbitShop\API\v1\OrbitShopLookupResponse;
use Illuminate\Support\Facades\Config;

class OrbitShopAPI extends API
{
    /**
     * The user ID who's associated with this key
     *
     * @var int
     */
    protected $userId = NULL;

    /**
     * Save the user instance.
     *
     * @var User
     */
    protected $user;

    /**
     * The Signature header name
     *
     * @var string
     */
    protected $httpSignatureHeader = 'X-Orbit-Signature';

    /**
     * The query parameter name that API should accept on.
     *
     * @var array
     */
    protected $queryParamName = array(
        'clientid'  => 'apikey',
        'timestamp' => 'apitimestamp',
        'version'   => 'apiver'
    );

    /**
     * Class constructor
     */
    public function __construct($clientID)
    {
        parent::__construct($clientID);
    }

    /**
     * Search the secret key based on the client ID.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @return OrbitShopLookupResponse
     */
    protected function lookupClientSecretKey($clientID)
    {
        $response = new OrbitShopLookupResponse($clientID);
        $this->userId = $response->getUserId();
        $this->user = $response->getUser();

        return $response;
    }

    /**
     * Get the user ID that associated with this key.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @return int
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * Get the User object based on the user ID.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @return \User|NULL
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Check the integrity of the signature.
     *
     * @author Rio Astamal <me@rioastamal.net>
     *
     * @return boolean
     * @throws OrbitAPIException
     */
    public function checkSignature()
    {
        if (isset($_GET[$this->queryParamName['clientid']]) === FALSE) {
            throw new OrbitAPIException(
                Status::PARAM_MISSING_CLIENT_ID_MSG,
                Status::PARAM_MISSING_CLIENT_ID
            );
        }

        if (isset($_GET[$this->queryParamName['timestamp']]) === FALSE) {
            throw new OrbitAPIException(
                Status::PARAM_MISSING_TIMESTAMP_MSG,
                Status::PARAM_MISSING_TIMESTAMP
            );
        }

        if (is_numeric($_GET[$this->queryParamName['timestamp']]) === FALSE) {
            throw new OrbitAPIException(
                Status::PARAM_MISSING_TIMESTAMP_MSG,
                Status::PARAM_MISSING_TIMESTAMP
            );
        }

        $signatureHeader = static::toUnderScoreHeader($this->httpSignatureHeader);

        // Append 'HTTP_' prefix to the header, so it can be read by PHP
        $signatureHeader = 'HTTP_' . $signatureHeader;

        if (isset($_SERVER[$signatureHeader]) === FALSE) {
            throw new OrbitAPIException(
                Status::PARAM_MISSING_SIGNATURE_MSG,
                Status::PARAM_MISSING_SIGNATURE
            );
        }

        $ourTime = gmdate('U');
        $ourHash = $this->generateHash();

        $userTime = abs($_GET[$this->queryParamName['timestamp']]);
        $userHash = $_SERVER[$signatureHeader];

        if ($ourHash !== $userHash) {
            throw new OrbitAPIException(
                Status::INVALID_SIGNATURE_MSG,
                Status::INVALID_SIGNATURE
            );
        }

        if ($userTime < $ourTime - $this->expiresTimeFrame) {
            throw new OrbitAPIException(
                Status::REQUEST_EXPIRED_MSG,
                Status::REQUEST_EXPIRED
            );
        }

        return TRUE;
    }

    /**
     * Clear the lookup response cache.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $clientID - The client API Key
     * @return void
     */
    public static function clearLookupCache($clientID)
    {
        Config::set('orbitapi.lookup.response.' . $clientID, NULL);
    }

    /**
     * Static function to throw invalid argument (missing parameter).
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param string $message - Forbidden access message
     * @return void
     * @throw DominoPOS\OrbitACL\Exception\ACLForbiddenException
     */
    public static function throwInvalidArgument($message=NULL)
    {
        throw new Exception\InvalidArgsException($message);
    }
}
