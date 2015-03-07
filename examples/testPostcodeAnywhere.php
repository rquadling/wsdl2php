<?php

require_once __DIR__ . '/testCommon.php';

function Consume($a_SoapOptions)
{
    /**
     * Create the service.
     */
    $o_Service = new PostcodeAnywhere(Null, $a_SoapOptions);

    /**
     * Create the parameters - starting with just the instances.
     */
    $o_Params = new PostcodeAnywhere_CleansePlus_Interactive_Cleanse_v1_00;

    /**
     * Populate the parameter instances with the appropriate data.
     */
    $o_Params->Address = array(
        '1 St Giles High St, London'
    );
    $o_Params->Key = 'MH98-MT82-JK59-ZY91';

    /**
     * Get the response.
     */
    $o_SoapFault = Null;
    $o_Exception = Null;
    $o_Response  = Null;
    try{
		$o_Response = $o_Service->CleansePlus_Interactive_Cleanse_v1_00($o_Params);
    }

    /**
     * Catch any exceptions and pass them back to the caller.
     */
    catch(SoapFault $o_SoapFault){}
    catch(Exception $o_Exception){}
 
 	/**
 	 * Return the service, the response and any exceptions.
 	 */
    return array($o_Service, $o_Response, $o_SoapFault, $o_Exception);
}
