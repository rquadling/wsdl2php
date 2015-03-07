<?php
 
require_once __DIR__ . '/testCommon.php';
 
function Consume($a_SoapOptions)
{
    /**
     * Create the service.
     */
    $o_Service = new ServiceTicketApi(Null, $a_SoapOptions);
 
    /**
     * Create the parameters - starting with just the instances.
     */
    $o_Params                = new ServiceTicketApi_AddServiceTicketViaCompanyId;
    $o_Params->credentials   = new ServiceTicketApi_ApiCredentials;
    $o_Params->serviceTicket = new ServiceTicketApi_ServiceTicket;
 
    /**
     * Populate the parameter instances with the appropriate data.
     */
    $o_Params->companyId                            = 'Unitrends';
    $o_Params->credentials->CompanyId               = 'Unitrends';
    $o_Params->credentials->IntegratorLoginId       = 'admin2';
    $o_Params->credentials->IntegratorPassword      = 'xxxxxxxx';
    $o_Params->serviceTicket->TicketNumber          = 0;
    $o_Params->serviceTicket->Summary               = 'Test ServiceTicket';
    $o_Params->serviceTicket->DateReq               = '0001-01-01T00:00:00';
    $o_Params->serviceTicket->SendingSrServiceRecid = 0;
    $o_Params->serviceTicket->SubBillingMethodId    = 'Actual';
    $o_Params->serviceTicket->SubBillingAmount      = 0.00;
    $o_Params->serviceTicket->SubDateAccepted       = '0001-01-01T00:00:00';
    $o_Params->serviceTicket->SubDateAcceptedUtc    = '0001-01-01T00:00:00';
    $o_Params->serviceTicket->BudgetHours           = 0.00; // decimal
    $o_Params->serviceTicket->SkipCallback          = FALSE; // boolean
    $o_Params->serviceTicket->Approved              = FALSE; // boolean
    $o_Params->serviceTicket->ClosedFlag            = FALSE; // boolean
    $o_Params->serviceTicket->Board                 = 'Professional Services'; // string
    $o_Params->serviceTicket->Status                = 'N'; // NsnStatusId
    $o_Params->serviceTicket->Priority              = 'Priority 4 - Scheduled Maintenance';
    $o_Params->serviceTicket->ProcessNotifications  = FALSE;
 
    /**
     * Get the response.
     */
    $o_SoapFault = Null;
    $o_Exception = Null;
    $o_Response  = Null;
    try{
        $o_Response  = $o_Service->AddServiceTicketViaCompanyId($o_Params);
    }
    catch(SoapFault $o_SoapFault){}
    catch(Exception $o_Exception){}
 
    return array($o_Service, $o_Response, $o_SoapFault, $o_Exception);
}
