<?php

namespace Orbit\Helper\Searchable\Elasticsearch\Filters;

trait ScriptFilter
{
    /**
     * filter by using a script
     *
     * @return void
     */
    public function filterByScript($aScript, $params = [])
    {
        $scriptData = [
            'script' => $aScript
        ];
        if (!empty($params)) {
            $scriptData['params'] = $params;
        }
        $this->filter(['script' => $scriptData ]);
    }
}


