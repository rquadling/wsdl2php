<?php

require_once __DIR__ . '/testCommon.php';

function Consume($a_SoapOptions)
{
    $o_Service = new W2IndigoServices(null, $a_SoapOptions);

    $o_Params                                                        = new W2IndigoServices_PEPDeskCheck;
    $o_Params->apiKey                                                = 'BBB83EB6-7B82-458D-8A43-5AA005C467CE';
    $o_Params->clientReference                                       = 'Test';
    $o_Params->pepDeskCheckRequestInfo                               = new W2IndigoServices_PEPDeskCheckRequestInfo;
    $o_Params->pepDeskCheckRequestInfo->BirthDay                     = 1;
    $o_Params->pepDeskCheckRequestInfo->BirthMonth                   = 10;
    $o_Params->pepDeskCheckRequestInfo->BirthYear                    = 1967;
    $o_Params->pepDeskCheckRequestInfo->DateOfBirthMatchingThreshold = 0;
    $o_Params->pepDeskCheckRequestInfo->NameMatchingThreshold        = 0;
    $o_Params->pepDeskCheckRequestInfo->SearchQuery                  = 'Richard Quadling';

    /**
     * Get the response.
     */
    $o_SoapFault = null;
    $o_Exception = null;
    $o_Response  = null;
    try {
        $o_Response = $o_Service->PEPDeskCheck($o_Params);
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
