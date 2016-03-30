<?php

namespace Report;

class AccountExportController extends \AccountAPIController
{
    use ExportControllerTrait;

    protected $pageTitle = 'PMP Accounts List';

    protected function handleCsvRowValue($row, $fieldName)
    {
        $csv = '"';
        
        if ($fieldName == 'tenants') {
            $tenantNames = [];
            foreach ($row['tenants'] as $tenant) {
                $tenantNames[] = $tenant['name'];
            }

            $csv .= implode(', ', $tenantNames);
        } elseif ($fieldName == 'city') {
            $csv .= $row['city'].', '.$row['country_name'];
        } else {
            $csv .= $row[$fieldName];
        }

        return $csv.'"';
    }

    protected function makeSummary()
    {
        $summary = ['Total Accounts' => count($this->data->records)];

        if (\Input::get('account_name')) {
            $summary['Filtered by account name'] = \Input::get('account_name');
        }

        if (\Input::get('company_name')) {
            $summary['Filtered by company name'] = \Input::get('company_name');
        }

        if (\Input::get('location')) {
            $summary['Filtered by location'] = \Input::get('location');
        }

        if (\Input::get('status')) {
            $summary['Filtered by status'] = \Input::get('status');     
        }

        if (\Input::get('creation_date_from') && \Input::get('creation_date_to')) {
            $summary['Filtered by creation date'] = \Input::get('creation_date_from').' - '.\Input::get('creation_date_to');
        }

        if (\Input::get('sortby') && \Input::get('sortmode')) {
            $summary['Sorted by'] = $this->listColumns[\Input::get('sortby')]['title'].' ('.\Input::get('sortmode').')';
        }

        return $summary;
    }

}
