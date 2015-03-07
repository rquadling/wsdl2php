<?php

require_once __DIR__ . '/testCommon.php';

function Consume($a_SoapOptions)
{
    /**
     * Create the service.
     */
    $o_Service = new SforceService(null, $a_SoapOptions);

    /**
     * Create the parameters - starting with just the instances.
     */
    $o_Params = new SforceService_login;

    /**
     * Populate the parameter instances with the appropriate data.
     */
    $o_Params->username = 'xxxxxxx@yyyyyyy.com';
    $o_Params->password = 'zzzzzzz';

    /**
     * Get the response.
     */
    $o_SoapFault = null;
    $o_Exception = null;
    $o_Response  = null;
    try {
        $o_Response = $o_Service->login($o_Params);
    }
    catch (SoapFault $o_SoapFault) {
    }
    catch (Exception $o_Exception) {
    }

    /**
     * Return the service, the response and any exceptions.
     */

    return array($o_Service, $o_Response, $o_SoapFault, $o_Exception);
}
