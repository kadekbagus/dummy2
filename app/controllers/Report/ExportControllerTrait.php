<?php

namespace Report;

use Carbon\Carbon;

/**
 * Export Controller Trait
 *
 * @author Qosdil A. <qosdil@dominopos.com>
 * @todo Fix custom value handling.
 */
trait ExportControllerTrait
{
    protected $summary;
    protected $timezone = 'UTC';

    public function getList()
    {
        // Call a parent controller method to prepare the data
        $this->prepareData();

        // You must set $pageTitle in your controller
        $this->data->pageTitle = $this->pageTitle;

        // Make the summary data (top-left)
        $this->data->summary = $this->makeSummary();

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

        if ($this->data->summary) {
            foreach ($this->data->summary as $field => $value) {
                $csv .= $field.','.$value;
                $csv .= "\r\n";
            }

            $csv .= "\r\n";            
        }

        foreach ($this->data->columns as $column) {
            $csv .= $column['title'].',';
        }

        $csv .= "\r\n";

        foreach ($this->data->records as $row) {
            foreach (array_keys($this->data->columns) as $fieldName) {
                $csv .= $this->handleCsvRowValue($row, $fieldName);
                $csv .= ',';
            }

            $csv .= "\r\n";
        }

        $response = \Response::make($csv, 200);
        $response->header('Content-Type', 'text/csv');

        $date = Carbon::now()->setTimezone($this->timezone)->format('D_d_M_Y_Hi');
        $fileName = 'orbit-export-'.str_replace(' ', '-', $this->data->pageTitle.'-'.$date).'.csv';
        $response->header('Content-Disposition', 'inline; filename="'.$fileName.'"');

        return $response;
    }

    protected function handleCsvRowValue($row, $fieldName)
    {
        return '"'.$row[$fieldName].'"';
    }

    protected function makeSummary()
    {
        return [];
    }
}
