<?php namespace Report;
/**
 * Base Intermediate Controller for all controller which need authentication.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use IntermediateAuthBrowserController;
use TenantAPIController;
use View;
use Config;
use Retailer;

class DataPrinterController extends IntermediateAuthBrowserController
{
    /**
     * Get list of tenant in printer friendly view.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Response
     */
    public function getTenantListPrintView()
    {
        $tenants = TenantAPIController::create('raw')->getSearchTenant();

        if ($tenants->code === 0) {
            $this->viewData['pageTitle'] = 'Report List of Tenant';
            $this->viewData['date'] = date('D, d/m/Y');
            $this->viewData['tenants'] = $tenants->data->records;
            $this->viewData['total_tenants'] = $tenants->data->total_records;
            $this->viewData['rowCounter'] = 0;
            $this->viewData['currentRetailer'] = $this->getCurrentRetailer();
            $this->viewData['me'] = $this;

            return View::make('printer/list-tenant-view', $this->viewData);
        }

        if (Config::get('app.debug') === FALSE) {
            return View::make('errors/500', $data);
        }

        return print_r($tenants, TRUE);
    }

    /**
     * Get current retailer (mall)
     *
     * @author Rio Astamal <me@rioastamla.net>
     * @return Retailer
     */
    public function getCurrentRetailer()
    {
        $current = Config::get('orbit.shop.id');
        $retailer = Retailer::find($current);

        return $retailer;
    }

    /**
     * Concat the list of collection.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @param Collection|Array $collection
     * @param string $attribute You want to get
     * @param string $separator Separator for concat result
     * @return String
     */
    public function concatCollection($collection, $attribute, $separator=', ')
    {
        $result = [];

        foreach ($collection as $item) {
            $result[] = $item->{$attribute};
        }

        return implode($separator, $result);
    }
}
