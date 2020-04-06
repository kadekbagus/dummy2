<?php

namespace Orbit\Controller\API\v1\Pub\BrandProduct\Request;

use Orbit\Helper\Request\ValidateRequest;

/**
 * List request.
 *
 * @author Budi <budi@gotomalls.com>
 */
class ListRequest extends ValidateRequest
{
    protected $roles = ['guest', 'consumer'];

    protected $useScroll = false;

    protected $scrollDuration = '20s';

    public function rules()
    {
        return [
            'skip' => 'required|integer',
            'take' => 'required|integer',
            'category_id' => 'sometimes|array',
            'cities' => 'sometimes|array',
            'store_id' => 'sometimes',
            'except_id' => 'sometimes|required',
        ];
    }

    public function isSuggestion()
    {
        return $this->has('except_id');
    }

    public function setScrollDuration($duration = '20s')
    {
        $this->scrollDuration = $duration;
    }

    public function getScrollDuration()
    {
        return $this->scrollDuration;
    }

    public function setUseScroll($useScroll = false)
    {
        $this->useScroll = $useScroll;
    }

    public function useScrolling()
    {
        return $this->useScroll;
    }
}
