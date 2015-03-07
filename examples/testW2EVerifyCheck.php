<?php

require_once __DIR__ . '/testCommon.php';

function Consume($a_SoapOptions)
{
    $o_Service = new W2IndigoServices(null, $a_SoapOptions);

    $o_Params                                          = new W2IndigoServices_EVerifyCheck;
    $o_Params->apiKey                                  = 'BBB83EB6-7B82-458D-8A43-5AA005C467CE';
    $o_Params->clientReference                         = 'Test';
    $o_Params->everifyCheckRequestInfo                 = new W2IndigoServices_EVerifyCheckRequestInfo;
    $o_Params->everifyCheckRequestInfo->DateOfBirth    = '1967-10-01';
    $o_Params->everifyCheckRequestInfo->Firstname      = 'Richard';
    $o_Params->everifyCheckRequestInfo->Gender         = 'M';
    $o_Params->everifyCheckRequestInfo->Postcode       = 'EX1 2SW';
    $o_Params->everifyCheckRequestInfo->PropertyNumber = 15;
    $o_Params->everifyCheckRequestInfo->Surname        = 'Quadling';

    /**
     * Get the response.
     */
    $o_SoapFault = null;
    $o_Exception = null;
    $o_Response  = null;
    try {
        $o_Response = $o_Service->EVerifyCheck($o_Params);
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
