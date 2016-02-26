<?php
/**
 * An API controller for managing widget templates.
 */
use OrbitShop\API\v1\ControllerAPI;
use OrbitShop\API\v1\OrbitShopAPI;
use OrbitShop\API\v1\Helper\Input as OrbitInput;
use OrbitShop\API\v1\Exception\InvalidArgsException;
use DominoPOS\OrbitACL\ACL;
use DominoPOS\OrbitACL\Exception\ACLForbiddenException;
use Illuminate\Database\QueryException;
use DominoPOS\OrbitAPI\v10\StatusInterface as Status;
use Helper\EloquentRecordCounter as RecordCounter;

class WidgetTemplateAPIController extends ControllerAPI
{
	/**
     * GET - List of Widget Templates.
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchWidgetTemplate()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.widget.getwidgettemplate.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.widget.getwidgettemplate.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.widget.getwidgettemplate.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_widget')) {
                Event::fire('orbit.widget.getwidgettemplate.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.view_widget');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.widget.getwidgettemplate.after.authz', array($this, $user));

           
            $maxRecord = (int) Config::get('orbit.pagination.max_record');
            if ($maxRecord <= 0) {
                $maxRecord = 20;
            }

            
            $perPage = (int) Config::get('orbit.pagination.per_page');
            if ($perPage <= 0) {
                $perPage = 20;
            }

            // Builder object
            $widgettemplates = WidgetTemplate::excludeDeleted();

            // Clone the query builder which still does not include the take,
            // skip, and order by
            $_widgettemplates = clone $widgettemplates;

            // Get the take args
            $take = $perPage;
            OrbitInput::get('take', function ($_take) use (&$take, $maxRecord) {
                if ($_take > $maxRecord) {
                    $_take = $maxRecord;
                }
                $take = $_take;

                if ((int)$take <= 0) {
                    $take = $maxRecord;
                }
            });
            $widgettemplates->take($take);

            $skip = 0;
            OrbitInput::get('skip', function ($_skip) use (&$skip, $widgettemplates) {
                if ($_skip < 0) {
                    $_skip = 0;
                }

                $skip = $_skip;
            });
            $widgettemplates->skip($skip);

            // Default sort by
            $sortBy = 'widget_templates.template_name';
            // Default sort mode
            $sortMode = 'asc';

            $widgettemplates->orderBy($sortBy, $sortMode);

            $totalWidgetTemplates = RecordCounter::create($_widgettemplates)->count();
            $listOfWidgetTemplates = $widgettemplates->get();

            $data = new stdclass();
            $data->total_records = $totalWidgetTemplates;
            $data->returned_records = count($listOfWidgetTemplates);
            $data->records = $listOfWidgetTemplates;

            if ($totalWidgetTemplates === 0) {
                $data->records = null;
                $this->response->message = 'No template available.';
            }

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.widget.getwidgettemplate.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.widget.getwidgettemplate.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.widget.getwidgettemplate.query.error', array($this, $e));

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
            Event::fire('orbit.widget.getwidgettemplate.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.widget.getwidgettemplate.before.render', array($this, &$output));

        return $output;
    }

    /**
     * GET - List of Setting Widget Templates.
     *
     * @author Ahmad Anshori <ahmad@dominopos.com>
     *
     * @return Illuminate\Support\Facades\Response
     */
    public function getSearchSettingWidgetTemplate()
    {
        try {
            $httpCode = 200;

            Event::fire('orbit.widget.getwidgettemplate.before.auth', array($this));

            // Require authentication
            $this->checkAuth();

            Event::fire('orbit.widget.getwidgettemplate.after.auth', array($this));

            // Try to check access control list, does this user allowed to
            // perform this action
            $user = $this->api->user;
            Event::fire('orbit.widget.getwidgettemplate.before.authz', array($this, $user));

            if (! ACL::create($user)->isAllowed('view_widget')) {
                Event::fire('orbit.widget.getwidgettemplate.authz.notallowed', array($this, $user));

                $errorMessage = Lang::get('validation.orbit.actionlist.view_widget');
                $message = Lang::get('validation.orbit.access.forbidden', array('action' => $errorMessage));

                ACL::throwAccessForbidden($message);
            }
            Event::fire('orbit.widget.getwidgettemplate.after.authz', array($this, $user));

            // Builder object
            $widgettemplates = NULL;
            $widgetTemplateSetting = NULL;
            
            $merchantId = OrbitInput::get('current_mall');

            $mall = Mall::findOrFail($merchantId);

            $mallsetting = Setting::active()
                ->where('object_id', $mall->merchant_id)
                ->where('object_type', 'merchant')
                ->get();

            foreach ($mallsetting as $currentSetting) {
                if ($currentSetting->setting_name === 'widget_template') {
                    $widgetTemplateSetting = $currentSetting;
                    $widget_template = WidgetTemplate::excludeDeleted()->where('widget_template_id', $widgetTemplateSetting->setting_value)->first();
                    if (! is_object($widget_template)) {
                    	$widgettemplates = $widget_template;
                    }
                }
            }

            $data = new stdclass();
            $data->records = $widgettemplates;

            $this->response->data = $data;
        } catch (ACLForbiddenException $e) {
            Event::fire('orbit.widget.getwidgettemplate.access.forbidden', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $this->response->data = null;
            $httpCode = 403;
        } catch (InvalidArgsException $e) {
            Event::fire('orbit.widget.getwidgettemplate.invalid.arguments', array($this, $e));

            $this->response->code = $e->getCode();
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();
            $result['total_records'] = 0;
            $result['returned_records'] = 0;
            $result['records'] = null;

            $this->response->data = $result;
            $httpCode = 403;
        } catch (QueryException $e) {
            Event::fire('orbit.widget.getwidgettemplate.query.error', array($this, $e));

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
            Event::fire('orbit.widget.getwidgettemplate.general.exception', array($this, $e));

            $this->response->code = $this->getNonZeroCode($e->getCode());
            $this->response->status = 'error';
            $this->response->message = $e->getMessage();

            if (Config::get('app.debug')) {
                $this->response->data = $e->__toString();
            } else {
                $this->response->data = null;
            }
        }

        $output = $this->render($httpCode);
        Event::fire('orbit.widget.getwidgettemplate.before.render', array($this, &$output));

        return $output;
    }
}