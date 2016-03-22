<?php

namespace Report;

class AccountExportController extends \AccountAPIController
{
    use ExportControllerTrait;

    public function __construct()
    {
        // Call a parent controller method to prepare the data
        $this->prepareData();

        // Set the page title
        $this->data->pageTitle = 'PMP Accounts';
    }

}
