<?php
namespace Orbit\Helper\Exception;

/**
 * Orbit Custom Exception
 *
 * how to use:
 * eg: throw new OrbitCustomException($errorMessage, LuckyDraw::LUCKY_DRAW_MAX_NUMBER_REACHED_ERROR_CODE, $customData);
 * and
 * catch (\Orbit\Helper\Exception\OrbitCustomException $e)
 *
 * @author Ahmad <ahmad@dominopos.com>
 */
class OrbitCustomException extends \Exception {
    protected $customData;

    public function __construct($message, $code = 0, $customData = NULL)
    {
        $this->customData = $customData;

        parent::__construct($message, $code);
    }

    /**
     * customData getter, use $e->getCustomData() to retrieve inside catch block
     */
    public function getCustomData()
    {
        return $this->customData;
    }
}
