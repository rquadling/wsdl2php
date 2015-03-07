<?php

require_once __DIR__ . '/testCommon.php';

function Consume($a_SoapOptions)
{
    $o_Service = new W2IndigoServices(Null, $a_SoapOptions);

    $o_Params                                         = new W2IndigoServices_WatchlistDetails;
    $o_Params->apiKey                                 = 'BBB83EB6-7B82-458D-8A43-5AA005C467CE';
    $o_Params->clientReference                        = 'Test';
    $o_Params->watchListDetailsRequestInfo            = new W2IndigoServices_WatchlistDetailsRequestInfo;
    $o_Params->watchListDetailsRequestInfo->ProfileId = 'aa4807f8-008b-efde-00db-19951467183d';

    $o_Response = $o_Service->WatchlistDetails($o_Params);

    return array($o_Service, $o_Response);
}
