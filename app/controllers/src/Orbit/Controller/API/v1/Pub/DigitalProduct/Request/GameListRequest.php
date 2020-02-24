<?php namespace Orbit\Controller\API\v1\Pub\DigitalProduct\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * Game List Request
 *
 * @author Zamroni <zamroni@gotomalls.com>
 */
class GameListRequest extends ValidateRequest
{
    /**
     * Allowed roles to access this request.
     * @var array
     */
    protected $roles = ['guest', 'consumer'];

    /**
     * Get validation rules.
     *
     * @return array array of rules for this request.
     */
    public function rules()
    {
        return [
            'skip' => 'integer',
            'take' => 'integer',
            'sortby' => 'in:game_id,game_name',
            'sortmode' => 'in:asc,desc',
        ];
    }
}
