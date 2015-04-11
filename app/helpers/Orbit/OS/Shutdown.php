<?php namespace Orbit\OS;
/**
 * Simple library wrapper to shutdown or reboot a linux OS.
 *
 * @author Rio Astamal <me@rioastamal.net>
 */
use Config;
use OrbitShop\API\v1\Helper\Command;

class Shutdown
{
    /**
     * Constructor
     */
    public function __construct()
    {
        // do nothing
    }

    /**
     * Static method for instantiate the class.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return Shutdown
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Power off the machine.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return array
     */
    public function poweroff()
    {
        return $this->halt('poweroff');
    }

    /**
     * Reboot the machine.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return array
     */
    public function reboot()
    {
        return $this->halt('reboot');
    }

    /**
     * Actual routine to power off or shutting down the machine.
     *
     * @author Rio Astamal <me@rioastamal.net>
     * @return array
     */
    protected function halt($mode='poweroff')
    {
        $haltCmd = 'sudo halt -p';
        $input = '';

        $return = [];
        $return['status'] = TRUE;
        $return['message'] = 'Shutdown has been initiated.';
        $return['object'] = NULL;

        switch ($mode) {
            case 'reboot':
                $haltCmd = Config::get('orbit.security.commands.reboot');
                $return['message'] = 'Reboot has been initiated.';
                $input = 'reboot';
                break;

            case 'poweroff':
            default:
                $haltCmd = Config::get('orbit.security.commands.shutdown');
                $input = 'shutdown';
                break;
        }

        if (empty($haltCmd)) {
            $return['status'] = FALSE;
            $return['message'] = sprintf('I could not find the `%s` configuration.', $haltCmd);

            return $return;
        }

        $execCmd = Command::Factory($haltCmd)->run($input . "\n");
        if ($execCmd->getExitCode() !== 0) {
            $return['status'] = FALSE;
            $return['message'] = $return['message'] = empty($execCmd->getStderr()) ? $execCmd->getStdout() : $execCmd->getStderr();

            return $return;
        }

        return $return;
    }
}
