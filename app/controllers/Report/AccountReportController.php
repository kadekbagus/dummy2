<?php

namespace Report;

class AccountReportController extends \AccountAPIController {

    public function getPrintAccount()
    {
        $this->prepareData();

        // Page title
        $data['pageTitle'] = 'PMP Accounts';

        $data['columns'] = $this->data->columns;
        $data['rows'] = $this->data->records;

        return \View::make('report.print_friendly', $data);
    }

}
