<?php

namespace Report;

/**
 * Export Controller Trait
 *
 * @author Qosdil A. <qosdil@dominopos.com>
 */
trait ExportControllerTrait
{
    public function getList()
    {
        // Call a parent controller method to prepare the data
        $this->prepareData();

        // You must set $pageTitle in your controller
        $this->data->pageTitle = $this->pageTitle;

        if (\Input::get('export') == 'csv') {
            return $this->getCsv();
        };

        return \View::make('report.print_friendly', (array) $this->data);
    }

    public function getCsv()
    {
        $csv = ',,,,,,';
        $csv .= "\r\n";
        $csv .= $this->data->pageTitle;
        $csv .= ',,,,,,';
        $csv .= "\r\n";
        $csv .= ',,,,,,';
        
        $csv .= "\r\n";
        $csv .= "\r\n";

        foreach ($this->data->columns as $column) {
            $csv .= $column['title'].',';
        }

        $csv .= "\r\n";

        foreach ($this->data->records as $row) {
            foreach (array_keys($this->data->columns) as $fieldName) {
                $csv .= $this->handleRowValue($row, $fieldName);
                $csv .= ',';
            }

            $csv .= "\r\n";
        }

        $response = \Response::make($csv, 200);
        $response->header('Content-Type', 'text/csv');

        $fileName = 'orbit-export-'.str_replace(' ', '-', $this->data->pageTitle.'-'.date('D_d_M_Y_').rand(10000, 99999)).'.csv';
        $response->header('Content-Disposition', 'inline; filename="'.$fileName.'"');

        return $response;
    }

    public function handleRowValue($row, $fieldName)
    {
        $csv = '';
        if ( ! is_array($row[$fieldName])) {

            // Replace comma with dash
            $csv .= str_replace(',', ' -', $row[$fieldName]);

        } else {

            // Show array values as string with a semicolon separator
            $csv .= implode('; ', $row[$fieldName]);
        }

        return $csv;
    }
}
