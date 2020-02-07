<?php
/**
 * ws_ckin.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
/**
 *  includes and requries
 */
require ("homeIncludes.php");

require(DB_TABLES . "visitRS.php");
require(DB_TABLES . "registrationRS.php");
require(DB_TABLES . "ReservationRS.php");

require (DB_TABLES . 'nameRS.php');
require (DB_TABLES . 'ItemRS.php');
require (DB_TABLES . 'ActivityRS.php');
require (DB_TABLES . 'PaymentGwRS.php');
require (DB_TABLES . 'PaymentsRS.php');
require (DB_TABLES . 'AttributeRS.php');

require CLASSES . 'CleanAddress.php';
require CLASSES . 'AuditLog.php';
require CLASSES . 'History.php';
require (CLASSES . 'CreateMarkupFromDB.php');

require (CLASSES . 'Notes.php');
require (CLASSES . 'Note.php');
require (CLASSES . 'LinkNote.php');
require (CLASSES . 'US_Holidays.php');
require (CLASSES . 'PaymentSvcs.php');
require (CLASSES . 'FinAssistance.php');
require (CLASSES . "ValueAddedTax.php");

require THIRD_PARTY . 'PHPMailer/PHPMailerAutoload.php';


require (PMT . 'GatewayConnect.php');
require (PMT . 'PaymentGateway.php');
require (PMT . 'PaymentResponse.php');
require (PMT . 'PaymentResult.php');
require (PMT . 'Receipt.php');
require (PMT . 'Invoice.php');
require (PMT . 'InvoiceLine.php');
require (PMT . 'CheckTX.php');
require (PMT . 'CashTX.php');
require (PMT . 'Transaction.php');
require (PMT . 'CreditToken.php');

require (CLASSES . 'Purchase/Item.php');

require(CLASSES . 'Purchase/RoomRate.php');
require CLASSES . 'TableLog.php';

require (MEMBER . 'Member.php');
require (MEMBER . 'IndivMember.php');
require (MEMBER . 'OrgMember.php');
require (MEMBER . "Addresses.php");
require (MEMBER . "EmergencyContact.php");

require (HOUSE . 'RoleMember.php');
require (HOUSE . 'Role.php');
require (HOUSE . 'ActivityReport.php');
require (HOUSE . 'Agent.php');
require (HOUSE . 'Attributes.php');
require (HOUSE . 'Constraint.php');
require (HOUSE . 'Doctor.php');
require (HOUSE . 'Guest.php');
require (HOUSE . 'Hospital.php');

require (HOUSE . 'HouseServices.php');
require (HOUSE . 'Patient.php');
require (HOUSE . 'PaymentManager.php');
require (HOUSE . 'PaymentChooser.php');
require (HOUSE . "psg.php");
require (HOUSE . 'RateChooser.php');
require (HOUSE . 'Registration.php');
require (HOUSE . 'Resource.php');
require (HOUSE . 'Room.php');
require (HOUSE . 'RoomChooser.php');
require (HOUSE . 'Reservation_1.php');
require (HOUSE . 'ReservationSvcs.php');
require (HOUSE . 'RegisterForm.php');
require (HOUSE . 'RegistrationForm.php');
require (HOUSE . 'TemplateForm.php');
require (HOUSE . 'ConfirmationForm.php');
require (HOUSE . 'VisitLog.php');
require (HOUSE . 'RoomLog.php');
require (HOUSE . 'Vehicle.php');
require (HOUSE . 'Visit.php');
require (HOUSE . "CurrentAccount.php");
require (HOUSE . "visitViewer.php");

require (HOUSE . 'VisitCharges.php');


$wInit = new webInit(WebPageCode::Service);

/* @var $dbh PDO */
$dbh = $wInit->dbh;

$uS = Session::getInstance();

creditIncludes($uS->PaymentGateway);

$guestAdmin = SecurityComponent::is_Authorized("guestadmin");

$c = "";

// Get our command
if (isset($_REQUEST["cmd"])) {
    $c = filter_var($_REQUEST["cmd"], FILTER_SANITIZE_STRING);
}

$idGuest = 0;
if (isset($_REQUEST["idGuest"])) {
    $idGuest = intval(filter_var($_REQUEST["idGuest"], FILTER_SANITIZE_STRING), 10);
}

$idVisit = 0;
if (isset($_REQUEST["idVisit"])) {
    $idVisit = intval(filter_var($_REQUEST["idVisit"], FILTER_SANITIZE_STRING), 10);
}


$events = array();


try {

    switch ($c) {

        case 'rmlist':

            $idResv = 0;
            if (isset($_POST['rid'])) {
                $idResv = intval(filter_var($_POST['rid'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $x = 0;
            if (isset($_POST['x'])) {
                $x = filter_var($_POST['x'], FILTER_SANITIZE_STRING);
            }

            if ($idResv > 0) {

                $resv = Reservation_1::instantiateFromIdReserv($dbh, $idResv);
                $events = ReservationSvcs::getRoomList($dbh, $resv, $x, $guestAdmin);

            } else {
                $events =  array('error'=>'Reservation Id is not set.');
            }

            break;

        case 'chgRoomList':

            $changeDate = '';
            if (isset($_POST['chgDate'])) {
                $changeDate = filter_var($_POST['chgDate'], FILTER_SANITIZE_STRING);
            }

            $rescId = 0;
            if (isset($_POST['selRescId'])) {
                $rescId = intval(filter_var($_POST['selRescId'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $span = 0;
            if (isset($_POST['span'])) {
                $span = intval(filter_var($_POST['span'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $events = HouseServices::changeRoomList($dbh, $idVisit, $span, $changeDate, $rescId);

            break;

        case 'newConstraint':

            $idResv = 0;
            if (isset($_POST['rid'])) {
                $idResv = intval(filter_var($_POST['rid'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $idResc = 0;
            if (isset($_POST['idr'])) {
                $idResc = intval(filter_var($_POST['idr'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $numGuests = 0;
            if (isset($_POST['numguests'])) {
                $numGuests = intval(filter_var($_POST['numguests'], FILTER_SANITIZE_NUMBER_INT));
            }

            $expArr = '';
            if (isset($_POST['expArr'])) {
                $expArr = filter_var($_POST['expArr'], FILTER_SANITIZE_STRING);
            }

            $expDep = '';
            if (isset($_POST['expDep'])) {
                $expDep = filter_var($_POST['expDep'], FILTER_SANITIZE_STRING);
            }

            $cbs = '';
            if (isset($_POST['cbRS'])) {
                $cbs = $_POST;
            }

            $omitSelf = TRUE;
            if (isset($_POST['omsf'])) {
                $omitSelf = filter_var($_POST['omsf'], FILTER_VALIDATE_BOOLEAN);
            }


            $events = ReservationSvcs::reviseConstraints($dbh, $idResv, $idResc, $numGuests, $expArr, $expDep, $cbs, $guestAdmin, $omitSelf);

            break;

        // Confirm Reservation Form
        case 'confrv':

            $idresv = 0;
            if (isset($_POST['rid'])) {
                $idresv = intval(filter_var($_POST['rid'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $idGuest = 0;
            if (isset($_POST['gid'])) {
                $idGuest = intval(filter_var($_POST['gid'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $amount = 0.00;
            if (isset($_POST['amt'])) {
                $amount = filter_var($_POST['amt'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            }

            $notes = '-';
            if (isset($_POST['notes'])) {
                $notes = filter_var($_POST['notes'], FILTER_SANITIZE_STRING);
            }


            $sendemail = FALSE;
            if (isset($_POST['eml'])) {
                $v = intval(filter_var($_POST['eml'], FILTER_SANITIZE_NUMBER_INT), 10);
                if ($v == 1) {
                    $sendemail = TRUE;
                }
            }

            $eaddr = '';
            if (isset($_POST['eaddr'])) {
                $eaddr = filter_var($_POST['eaddr'], FILTER_SANITIZE_STRING);
            }

            $tabIndex = false;
            if (isset($_POST['tabIndex'])) {
                $tabIndex = filter_var($_POST['tabIndex'], FILTER_SANITIZE_STRING);
            }
            
            $events = ReservationSvcs::getConfirmForm($dbh, $idresv, $idGuest, $amount, $sendemail, $notes, $eaddr, $tabIndex);
            break;

        case 'void':

            $idPayment = 0;
            if (isset($_POST['pid'])) {
                $idPayment = intval(filter_var($_POST['pid'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $bid = '';
            if (isset($_POST['bid'])) {
                $bid = filter_var($_POST['bid'], FILTER_SANITIZE_STRING);
            }

            $events = PaymentSvcs::voidFees($dbh, $idPayment, $bid);

            break;

        case 'revpmt':

            $idPayment = 0;
            if (isset($_POST['pid'])) {
                $idPayment = intval(filter_var($_POST['pid'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $bid = '';
            if (isset($_POST['bid'])) {
                $bid = filter_var($_POST['bid'], FILTER_SANITIZE_STRING);
            }

            $events = PaymentSvcs::reversalFees($dbh, $idPayment, $bid);

            break;

        case 'rtn':

            $idPayment = 0;
            if (isset($_POST['pid'])) {
                $idPayment = intval(filter_var($_POST['pid'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $bid = '';
            if (isset($_POST['bid'])) {
                $bid = filter_var($_POST['bid'], FILTER_SANITIZE_STRING);
            }

            $events = PaymentSvcs::returnPayment($dbh, $idPayment, $bid);

            break;

        case 'undoRtn':

            $idPayment = 0;
            if (isset($_POST['pid'])) {
                $idPayment = intval(filter_var($_POST['pid'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $bid = '';
            if (isset($_POST['bid'])) {
                $bid = filter_var($_POST['bid'], FILTER_SANITIZE_STRING);
            }

            $events = PaymentSvcs::undoReturnFees($dbh, $idPayment, $bid);

            break;

        case 'voidret':

            $idPayment = 0;
            if (isset($_POST['pid'])) {
                $idPayment = intval(filter_var($_POST['pid'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $bid = '';
            if (isset($_POST['bid'])) {
                $bid = filter_var($_POST['bid'], FILTER_SANITIZE_STRING);
            }

            $events = PaymentSvcs::voidReturnFees($dbh, $idPayment, $bid);

            break;

        case 'delWaive':

            $idLine = 0;
            if (isset($_POST['pid'])) {
                $idLine = intval(filter_var($_POST['pid'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $idInvoice = 0;
            if (isset($_POST['iid'])) {
                $idInvoice = intval(filter_var($_POST['iid'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $bid = '';
            if (isset($_POST['bid'])) {
                $bid = filter_var($_POST['bid'], FILTER_SANITIZE_STRING);
            }

            if ($idInvoice > 0 && $idLine > 0) {

                $invoice = new Invoice($dbh);
                $invoice->loadInvoice($dbh, $idInvoice);

                if ($invoice->deleteLine($dbh, $idLine, $bid, $uS->username)) {
                    $events = array('bid' => $bid, 'success' => 'House Payment Deleted.  ');
                }
            }

            break;

    case "getReg":

        $idRegistration = 0;
        if (isset($_REQUEST["reg"])) {
            $idRegistration = intval(filter_var($_REQUEST["reg"], FILTER_SANITIZE_STRING), 10);
        }

        if ($idRegistration > 0) {
            $reg = new Registration($dbh, 0, $idRegistration);
            $mkup = HTMLContainer::generateMarkup('div', $reg->createRegMarkup($dbh, FALSE), array('class'=>"ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox"));
            $events = array('success'=>$mkup);
        } else {
            $events = array('error'=>'Bad PSG Id.');
        }

        break;

    case "saveReg":

        $idRegistration = 0;
        if (isset($_REQUEST["reg"])) {
            $idRegistration = intval(filter_var($_REQUEST["reg"], FILTER_SANITIZE_STRING), 10);
        }

        $params = array();
        if (isset($_REQUEST['parm'])) {
            $params = $_REQUEST['parm'];
        }

        if ($idRegistration > 0) {

            $reg = new Registration($dbh, 0, $idRegistration);

            $reg->extractRegistration($dbh, $params);
            $reg->saveRegistrationRs($dbh, 0, $uS->username);
            $events = array('success'=>'Registration info saved.');
        } else {
            $events = array('error'=>'Bad Registration Id.');
        }


        break;

    case "reservMove":

        $sdelta = 0;
        if (isset($_POST['sdelta'])) {
            $sdelta = intval(filter_var($_POST['sdelta'], FILTER_SANITIZE_NUMBER_INT), 10);
        }
        $edelta = 0;
        if (isset($_POST['edelta'])) {
            $edelta = intval(filter_var($_POST['edelta'], FILTER_SANITIZE_NUMBER_INT), 10);
        }


        $events = ReservationSvcs::moveReserv($dbh, $idVisit, $sdelta, $edelta);
        break;

    case "visitMove":

        $sdelta = 0;
        if (isset($_POST['sdelta'])) {
            $sdelta = intval(filter_var($_POST['sdelta'], FILTER_SANITIZE_NUMBER_INT), 10);
        }
        $edelta = 0;
        if (isset($_POST['edelta'])) {
            $edelta = intval(filter_var($_POST['edelta'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $span = 0;
        if (isset($_POST['span'])) {
            $span = intval(filter_var($_POST['span'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $events = HouseServices::moveVisit($dbh, $idVisit, $span, $sdelta, $edelta);
        break;

    case "visitFees":
        $s = 'n';
        if (isset($_POST['action'])) {
            $s = filter_var($_POST['action'], FILTER_SANITIZE_STRING);
        }

        $cod = '';
        if (isset($_POST['ckoutdt'])) {
            $cod = filter_var($_POST['ckoutdt'], FILTER_SANITIZE_STRING);
        }

        $span = 0;
        if (isset($_POST['span'])) {
            $span = intval(filter_var($_POST['span'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $events = HouseServices::getVisitFees($dbh, $idGuest, $idVisit, $span, $guestAdmin, $s, $cod);

        break;


    case "addStay":

        $id = 0;
        if (isset($_POST["id"])) {
            $id = intval(filter_var($_POST["id"], FILTER_SANITIZE_STRING), 10);
        }

        $idVisit = 0;
        if (isset($_POST["vid"])) {
            $idVisit = intval(filter_var($_POST["vid"], FILTER_SANITIZE_STRING), 10);
        }

        $visitSpan = 0;
        if (isset($_POST["span"])) {
            $visitSpan = intval(filter_var($_POST["span"], FILTER_SANITIZE_STRING), 10);
        }

        $events = HouseServices::addVisitStay($dbh, $idVisit, $visitSpan, $id, $_POST);
        break;

    case "getincmdiag":

        $idresv = 0;
        if (isset($_GET['rid'])) {
            $idresv = intval(filter_var($_GET['rid'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $idreg = 0;
        if (isset($_GET['rgId'])) {
            $idreg = intval(filter_var($_GET['rgId'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $events = ReservationSvcs::getIncomeDialog($dbh, $idresv, $idreg);
        break;

    case "savefap":

        $post = filter_var_array($_POST, FILTER_SANITIZE_STRING);
        $events = ReservationSvcs::saveFinApp($dbh, $post);

        break;

    case 'showPayInv':

        $id = 0;
        if (isset($_POST['id'])) {
            $id = intval(filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $iid = 0;
        if (isset($_POST['iid'])) {
            $iid = intval(filter_var($_POST['iid'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $events = HouseServices::ShowPayInvoice($dbh, $id, $iid);
        break;

    case 'payInv':

        $id = 0;
        if (isset($_POST['id'])) {
            $id = intval(filter_var($_POST['id'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $data = filter_var_array($_POST, FILTER_SANITIZE_STRING);
        $events = HouseServices::payInvoice($dbh, $id, $data);

        break;

    case "saveFees":

        $span = 0;
        if (isset($_POST['span'])) {
            $span = intval(filter_var($_POST['span'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $postbackPage = '';
        if (isset($_POST['pbp'])) {
            $postbackPage = filter_var($_POST['pbp'], FILTER_SANITIZE_STRING);
        }

        $events = HouseServices::saveFees($dbh, $idVisit, $span, $guestAdmin, $_REQUEST, $postbackPage);

        break;

    // View Activity
    case 'viewActivity':

        $rid = 0;
        if (isset($_POST['rid'])) {
            $rid = intval(filter_var($_POST['rid'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $events = ReservationSvcs::viewActivity($dbh, $rid);

        break;

    // Get House payment dialog
    case 'getHPay':

        if (isset($_POST['ord'])) {

            $ordNum = intval(filter_var($_POST['ord'], FILTER_SANITIZE_NUMBER_INT), 10);
            $arrDate = '';

            $discounts = readGenLookupsPDO($dbh, 'House_Discount');
            $addnls = readGenLookupsPDO($dbh, 'Addnl_Charge');

            foreach ($discounts as $n) {
                $events['disc'][$n[0]] = $n[2];
            }

            foreach ($addnls as $a) {
                $events['addnl'][$a[0]] = $a[2];
            }

            if (isset($_POST['arrDate'])) {
                $arrDate = filter_var($_POST['arrDate'],FILTER_SANITIZE_STRING);
            }

            $vat = new ValueAddedTax($dbh);

            $events['markup'] = PaymentChooser::createHousePaymentMarkup($discounts, $addnls, $ordNum, $vat->getTaxedItemSums($ordNum, 0), $arrDate);

        } else {
            $events = array('error'=>'Visit Id is missing.  ');
        }

        break;

    // Save House payment dialog
    case 'saveHPay':

        $ord = 0;
        if (isset($_POST["ord"])) {
            $ord = intval(filter_var($_POST["ord"], FILTER_SANITIZE_STRING), 10);
        }

        $amt = 0;
        if (isset($_POST["amt"])) {
            $amt = floatval(filter_var($_POST["amt"], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
        }

        $idItem = 0;
        if (isset($_POST["item"])) {
            $idItem = floatval(filter_var($_POST["item"], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
        }

        $discount = '';
        if (isset($_POST['dsc'])) {
            $discount = filter_var($_POST['dsc'], FILTER_SANITIZE_STRING);
        }

        $addnlCharge = '';
        if (isset($_POST['chg'])) {
            $addnlCharge = filter_var($_POST['chg'], FILTER_SANITIZE_STRING);
        }

        $adjDate = '';
        if (isset($_POST['adjDate'])) {
            $adjDate = filter_var($_POST['adjDate'], FILTER_SANITIZE_STRING);
        }

        $notes = '';
        if (isset($_POST['notes'])) {
            $notes = filter_var($_POST['notes'], FILTER_SANITIZE_STRING);
        }

        $events = HouseServices::saveHousePayment($dbh, $idItem, $ord, $amt, $discount, $addnlCharge, $adjDate, $notes);

        break;

    // View PSG
    case 'viewPSG':

        $idPsg = 0;
        if (isset($_POST['psg'])) {
            $idPsg = intval(filter_var($_POST['psg'], FILTER_SANITIZE_NUMBER_INT), 10);
            $psg = new Psg($dbh, $idPsg);
            $events = array('markup'=>$psg->createEditMarkup($dbh, $uS->guestLookups[GL_TableNames::PatientRel], new Config_Lite(LABEL_FILE)));

        } else {
            $events = array('error'=>'PSG ID is missing.');
        }

        break;


    // Card on file
    case "cof":

        $postbackPage = '';
        if (isset($_POST['pbp'])) {
            $postbackPage = filter_var($_POST['pbp'], FILTER_SANITIZE_STRING);
        }

        $idGroup = 0;
        if (isset($_POST['idGrp'])) {
            $idGroup = intval(filter_var($_POST['idGrp'], FILTER_SANITIZE_STRING), 10);
        }

        $events = HouseServices::cardOnFile($dbh, $idGuest, $idGroup, $_POST, $postbackPage);

        break;

    // Card on file
    case "viewCredit":

        $idReg = 0;
        if (isset($_POST['reg'])) {
            $idReg = intval(filter_var($_POST['reg'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $pbp = '';
        if (isset($_POST['pbp'])) {
            $pbp = filter_var($_POST['pbp'], FILTER_SANITIZE_STRING);
        }

        $events = array('success'=>HouseServices::viewCreditTable($dbh, $idReg, 0), 'pbp'=>$pbp);

        break;

    case 'rvstat':

        $idReservation = 0;
        if (isset($_POST['rid'])) {
            $idReservation = intval(filter_var($_POST['rid'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $status = '';
        if (isset($_POST['stat'])) {
            $status = filter_var($_POST['stat'], FILTER_SANITIZE_STRING);
        }

        $events = ReservationSvcs::changeReservStatus($dbh, $idReservation, $status);
        break;

    case 'cedd':

        $nd = '';
        if (isset($_POST['nd'])) {
            $nd = filter_var($_POST['nd'], FILTER_SANITIZE_STRING);
        }

        $events = HouseServices::changeExpectedDepartureDate($dbh, $idGuest, $idVisit, $nd);
        break;

    case 'gtvlog':

        $wlid = 0;
        if (isset($_REQUEST["idReg"])) {
            $wlid = intval(filter_var($_REQUEST["idReg"], FILTER_SANITIZE_STRING), 10);
        }

        $events = HouseServices::visitChangeLogMarkup($dbh, $wlid);

        break;

    case 'getPrx':
        // reprint a receipt
        $idPayment = 0;
        if (isset($_POST['pid'])) {
            $idPayment = intval(filter_var($_POST['pid'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $events = PaymentSvcs::generateReceipt($dbh, $idPayment);

        break;

    case "chgPmtAmt":
        // respond to changes in payment record amount on Recent Payments tab and Guest Edit payments tab.

        $pid = 0;
        if (isset($_POST['pid'])) {
            $pid = intval(filter_var($_POST['pid'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $newAmt = 0.00;
        if (isset($_POST['amt'])) {
            $newAmt = floatval(filter_var($_POST['amt'], FILTER_SANITIZE_STRING));
        }

        if ($guestAdmin) {
            $events = HouseServices::changePaymentAmount($dbh, $pid, $newAmt);
        }

        break;

    case 'rtcalc':

        // Financial assistance rate category
        $events = RoomChooser::roomAmtCalculation($dbh, $_POST);
        break;

    case 'delResv':

        $rid = 0;
        if (isset($_POST['rid'])) {
            $rid = intval(filter_var($_POST['rid'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $events = ReservationSvcs::deleteReservation($dbh, $rid);

        break;

    default:
        $events = array("error" => "Bad Command: \"" . $c . "\"");
}

} catch (PDOException $ex) {
    $events = array("error" => "Database Error: " . $ex->getMessage() . "<br/>" . $ex->getTraceAsString());
} catch (Exception $ex) {
    $events = array("error" => "Web Server Error: " . $ex->getMessage());
}



if (is_array($events)) {

    $json = json_encode($events);

    if ($json !== FALSE) {
        echo ($json);
    } else {
        $events = array("error" => "PHP json encoding error: " . json_last_error_msg());
        echo json_encode($events);
    }

} else {
    echo $events;
}

exit();

