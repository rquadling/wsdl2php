<?php

require_once __DIR__.'/testCommon.php';

function Consume($a_SoapOptions)
{
    /**
     * Create the service.
     */
    $o_Service = new SpaService(null, $a_SoapOptions);

    /**
     * Create the parameters - starting with just the instances.
     */
    $o_Params = new SpaService_SayHello();

    /**
     * Populate the parameter instances with the appropriate data.
     */
    $o_Params->name = 'Richard Quadling';

    /**
     * Get the response.
     */
    $o_SoapFault = null;
    $o_Exception = null;
    $o_Response = null;
    try {
        $o_Response = $o_Service->SayHello($o_Params);
    } catch (SoapFault $o_SoapFault) {
    } catch (Exception $o_Exception) {
    }

    /**
     * Return the service, the response and any exceptions.
     */

    return array($o_Service, $o_Response, $o_SoapFault, $o_Exception);
}
