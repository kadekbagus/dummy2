<?php namespace Orbit\Controller\API\v1\Pub;
/**
 * An API controller for managing mall geo location.
 */
use OrbitShop\API\v1\PubControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\ACL\Exception\ACLForbiddenExceptio;
use Illuminate\Database\QueryException;
use Text\Util\LineChecker;
use Config;
use stdClass;

class LandingPageAPIController extends PubControllerAPI
{
    /**
     * GET - get icon list for landing page
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string area
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getIconList()
    {
        $httpCode = 200;
        try {
            $icons = Config::get('dynamic-listing.icons', null);
            $listIcons = array();

            if (! empty($icons)) {
                $listIcons = array_filter($icons, function($val) {
                    return $val['status'] == 'active';
                });
            }

            $this->response->data = new stdClass();
            $this->response->data->total_records = count($icons);
            $this->response->data->returned_records = count($listIcons);
            $this->response->data->records = $listIcons;
        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        $output = $this->render($httpCode);

        return $output;
    }

    /**
     * GET - get icon list for slideshow (enas)
     *
     * @author Shelgi Prasetyo <shelgi@dominopos.com>
     *
     * List of API Parameters
     * ----------------------
     * @param string area
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSlideShow()
    {
        $httpCode = 200;
        try {
            $slideshow = Config::get('dynamic-listing.slideshow', null);
            $maxSlide = 10;
            $slide = array();
            $slide_fix = array();
            $random = array();

            if (! empty($slideshow)) {
                //check slideshow pic no_random is true or not, if true split to another array
                $slide_random = array();
                foreach ($slideshow as $sf) {
                    if($sf['not_random'] === 1) {
                        array_push($slide_fix, $sf);
                    } else {
                        array_push($slide_random, $sf);
                    }
                }

                $maxSlide = $maxSlide - count($slide_fix);

                if (! empty($slide_random)) {
                    if (count($slide_random) < $maxSlide) {
                        $maxSlide = count($slide_random);
                    }
                    $slides = array();
                    $listSlide = array_rand($slide_random, $maxSlide);
                    foreach ($listSlide as $key => $value) {
                        array_push($slides, $slide_random[$value]);
                    }

                    $keys = array_keys($slides);
                    shuffle($keys);
                    foreach ($keys as $key) {
                        array_push($random, $slides[$key]);
                    }
                }
            }

            $slide = array_merge($slide_fix, $random);

            $this->response->data = new stdClass();
            $this->response->data->total_records = count($slideshow);
            $this->response->data->returned_records = count($slide);
            $this->response->data->records = $slide;
        } catch (ACLForbiddenException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {

            $this->response->code = $e->getCode();
            $this->response->status = 'error';

            // Only shows full query error when we are in debug mode
            if (Config::get('app.debug')) {
                $this->response->message = $e->getMessage();
            } else {
                $this->response->message = Lang::get('validation.orbit.queryerror');
            }
            $this->response->data = null;
            $httpCode = 500;
        } catch (Exception $e) {

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 500;
        }

        $output = $this->render($httpCode);

        return $output;
    }
}