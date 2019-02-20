<?php
/**
 * imservice.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2019 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ('functions/commonFunc.php');
require ('classes/config/Lite.php');

require ('classes/PDOdata.php');
require ('classes/Exception_hk/Hk_Exception.php');
require 'classes/SiteConfig.php';
require ('classes/SysConst.php');

require ('classes/tables/HouseRS.php');
require ('classes/tables/PaymentGwRS.php');
require ('classes/tables/PaymentsRS.php');

require ('classes/sec/sessionClass.php');
require ('classes/sec/UserClass.php');
require ('classes/sec/ChallengeGenerator.php');
require ('classes/sec/Login.php');
require ('classes/sec/SecurityComponent.php');
require ('classes/sec/ScriptAuthClass.php');
require ('classes/sec/webInit.php');

require ('classes/Payment/GatewayConnect.php');
require ('classes/MercPay/Gateway.php');
require ('classes/MercPay/MercuryHCClient.php');
require ('classes/Payment/PaymentGateway.php');
require ('classes/Payment/Payments.php');
require ('classes/Payment/CreditToken.php');
require ('classes/Payment/Transaction.php');
require ('classes/Payment/Invoice.php');
require ('classes/Payment/InvoiceLine.php');
require ('classes/Payment/Receipt.php');

require ('classes/PaymentSvcs.php');


$uS = Session::getInstance();

try {
    $login = new Login();
    $config = $login->initializeSession('conf/site.cfg');
} catch (Exception $ex) {
    http_response_code(500);
    exit ("<h3>" . $ex->getMessage());
}


// Override user credentials
$dbh = initPDO(TRUE);

$user = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
$pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';

$u = new UserClass();
$password = md5(md5($pass) . $login->getChallengeVar());

if ($u->_checkLogin($dbh, addslashes($user), $password, FALSE) === FALSE) {

    header('WWW-Authenticate: Basic realm="Hospitality HouseKeeper"');
    header('HTTP/1.0 401 Unauthorized');
    die ("Not authorized");

}

try {
    $wInit = new webInit(WebPageCode::Service, FALSE);
} catch (Exception $ex) {
    $uS->destroy();
    header('WWW-Authenticate: Basic realm="Hospitality HouseKeeper"');
    header('HTTP/1.0 401 Unauthorized');
    die ("Not authorized");
}



// Grab the data
//$inputJSON =  '{  "ResponseDateTime": "12/14/2017 11:58:06 PM",  "PaymentTransactionID": "c1ed2480fe4847eca58547eeb073b7a6",  "TransactionAction": "Sale",  "IsMultiAR": "false",  "TransactionType": "CreditCard",  "SaveCardOnFile": "false",  "CardHolderFirstName": null,  "CardHolderLastName": null,  "CardHolderName": null,  "CardLastFourDigits": "************1111",  "CardType": "VISA",  "ExpDate": "12/26",  "AuthorizationCode": "9B5A7F",  "RoutingNumber": null,  "AccountNumberLastFourDigits": null,  "CheckNumber": null,  "CheckingAccountHolderFirstName": null,  "CheckingAccountHolderLastName": null,  "DriversLicense": null,  "CheckingAccountType": null,  "CheckState": null,  "SaveBankAccountOnFile": null,  "ReasonCode": null,  "ReasonCodeDescription": null,  "AddressVerificationResponseCode": null,  "AddressVerificationResponseDescription": null,  "CardVerificationResponseCode": null,  "CardVerificationResponseDescription": null,  "ResponseCode": "000",  "ResponseMessage": "APPROVAL",  "CurrentTransactionStatusCode": "C",  "OriginalTransactionStatusCode": "C",  "CurrentTransactionStatusDescription": "Approved",  "OriginalTransactionStatusDescription": "Approved",  "AuthorizationText": "I AGREE TO PAY THE ABOVE AMOUNT ACCORDING TO MY CARD HOLDER AGREEMENT.",  "TransactionServiceFee": null,  "FreeFormTextResponse": null,  "MarketSegment": null,  "HasCheckImage": null,  "InstallmentSequenceNumber": null,  "InstallmentCount": null,  "LineItemNumber": null,  "ReturnCheckFee": null,  "EMVApplicationIdentifier": null,  "EMVTerminalVerificationResults": null,  "EMVIssuerApplicationData": null,  "EMVTransactionStatusInformation": null,  "EMVApplicationResponseCode": null,  "RequestAmount": "1.00",  "Amount": "50.00",  "IsPartiallyApproved": "false",  "PartialApprovalAmount": null,  "Outlet": "JSONPOSTING-1-1",  "OutletDescription": null,  "Alias": null,  "PatientID": null,  "PatientMedicalRecordNumber": null,  "TransactionCode": null,  "TransactionDescription": null,  "PatientFirstName": null,  "PatientLastName": null,  "PatientMiddleName": null,  "PatientBirthDate": null,  "PatientServiceBeginDate": null,  "PatientServiceEndDate": null,  "PatientAddress1": null,  "PatientAddress2": null,  "PatientCity": null,  "PatientState": null,  "PatientZip": null,  "PatientCountry": "US",  "PatientPhoneNumber": null,  "GuarantorID": null,  "GuarantorFirstName": null,  "GuarantorLastName": null,  "AccountHolderEmail": null,  "AdditionalCode1": null,  "AdditionalCode1Description": null,  "AdditionalCode2": null,  "AdditionalCode2Description": null,  "AdditionalCode3": null,  "AdditionalCode3Description": null,  "AdditionalCode4": null,  "AdditionalCode4Description": null,  "AdditionalCode5": null,  "AdditionalCode5Description": null,  "AdditionalCode6": null,  "AdditionalCode6Description": null,  "AdditionalInfo1": null,  "AdditionalInfo2": null,  "AdditionalInfo3": null,  "AdditionalInfo4": null,  "AdditionalInfo5": null,  "AdditionalInfo6": null,  "EstimatedAmount": null,  "SaveOnFileTransactionID": null,  "AccountHolderAddress1": null,  "AccountHolderAddress2": null,  "AccountHolderCity": null,  "AccountHolderState": null,  "AccountHolderZip": null,  "AccountHolderCountry": null,  "AccountHolderPhoneNumber": null,  "EstimateID": null,  "PaymentPlanID": null,  "StatementID": null,  "CardInputMode": null,  "CardPresentStatus": "PresentManualKey",  "WorkflowStatus": "Complete",  "SingleSignOnToken": "OTJiZDA0YmEtNzM1Ny00ZGNhLThiNjgtZmM5",  "MerchantID": "JSONPOSTING",  "StoreID": "1",  "TerminalID": "1",  "UserID": "user@account",  "OriginalTransactionID": "c1ed2480fe4847eca58547eeb073b7a6"}';

$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, TRUE); //convert JSON into array


// dump payment plan messages.
if (isset($data['PaymentPlanTransactionType'])) {
     http_response_code(200);
     exit();
}


// Find Remote IP Address
if (filter_has_var(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR')) {
    $remoteIp = filter_input(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR', FILTER_VALIDATE_IP);
} else {
    $remoteIp = filter_input(INPUT_SERVER, 'REMOTE_ADDR', FILTER_VALIDATE_IP);
}


// log the data
try {
    Gateway::saveGwTx($dbh, '', json_encode(array('user'=>addslashes($user), 'remote IP'=>$remoteIp)), $inputJSON, 'Webhook');
} catch(Exception $ex) {
    // Do Nothing
}


// Deal with it
try {
    $error = PaymentSvcs::processWebhook($dbh, $data);
} catch (Exception $ex) {
    $error = TRUE;
}

if($error) {
    http_response_code(500);
} else {
    http_response_code(200);
}

exit();
