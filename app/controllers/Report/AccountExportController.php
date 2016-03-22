<?php

namespace Report;

class AccountExportController extends \AccountAPIController {

    protected $pageTitle = 'PMP Accounts';

    public function __construct()
    {
        $this->prepareData();
    }

    public function getList()
    {
        if (\Input::get('export') == 'csv') {
            return $this->getCsv();
        };

        // Page title
        $data['pageTitle'] = $this->pageTitle;

        $data['columns'] = $this->data->columns;
        $data['rows'] = $this->data->records;

        return \View::make('report.print_friendly', $data);
    }

    public function getCsv()
    {
        $csv = ',,,,,,'.$this->pageTitle.',,,,,,';

        $csv .= "\r\n";
        $csv .= "\r\n";

        foreach ($this->data->columns as $column) {
            $csv .= $column['title'].',';
        }

        $csv .= "\r\n";

        foreach ($this->data->records as $row) {
            foreach (array_keys($this->data->columns) as $fieldName) {
                if ( ! is_array($row[$fieldName])) {
                    $csv .= $row[$fieldName];
                } else {
                    $csv .= implode('<br/>', $row[$fieldName]);
                }

                $csv .= ',';
            }

            $csv .= "\r\n";
        }

        $response = \Response::make($csv, 200);
        $response->header('Content-Type', 'text/csv');
        $response->header('Content-Disposition', 'inline; filename="myfile.csv"');

        return $response;
    }

}
