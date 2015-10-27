<?php 
namespace Orbit\Helper\Asset;

use App;
use Exception;

class Stylesheet
{
	/**
     * Default stylesheet filename
     *
     * @var string
     */
	protected $default_stylesheet = 'main.css';

	/**
     * Static method to instantiate the class.
     *
     * @return Stylesheet
     */
    public static function create()
    {	
		return new static();
    }

	/**
     * Get the stylesheet file
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     */
	public function getMallCss()
	{
		if (! App::make('orbitSetting')->getSetting('current_retailer') || App::make('orbitSetting')->getSetting('current_retailer') === '-') {
	        throw new Exception ('You have to setup current retailer first on Admin Portal.');
	    }

	    $mallId = App::make('orbitSetting')->getSetting('current_retailer');

	    $path_to_stylesheet = public_path() . '/mobile-ci/stylesheet';

	    $filename_by_id = $mallId . '.css';
	    $file_by_id = $path_to_stylesheet . '/' . $filename_by_id;

	    $domain = $_SERVER['HTTP_HOST'];
	    $filename_by_domain = $domain . '.css';
	    $file_by_domain = $path_to_stylesheet . '/' . $filename_by_domain;

	    if (file_exists($file_by_id)) {
	    	return $filename_by_id;
	    } elseif (file_exists($file_by_domain)) {
	    	return $filename_by_domain;
	    }

	    return $this->default_stylesheet;
	}
}
