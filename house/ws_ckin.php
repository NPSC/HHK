<?php
use HHK\Common;
use HHK\Exception\UnexpectedValueException;
use HHK\sec\WebInit;
use HHK\SysConst\WebPageCode;
use HHK\sec\SecurityComponent;
use HHK\sec\Session;
use HHK\House\Reservation\Reservation_1;
use HHK\House\Reservation\ReservationSvcs;
use HHK\House\HouseServices;
use HHK\Payment\PaymentSvcs;
use HHK\Payment\Invoice\Invoice;
use HHK\House\Registration;
use HHK\HTMLControls\HTMLContainer;
use HHK\Purchase\ValueAddedTax;
use HHK\Purchase\PaymentChooser;
use HHK\SysConst\GLTableNames;
use HHK\House\PSG;
use HHK\House\Room\RoomChooser;
use HHK\sec\Labels;
use HHK\Document\Document;

/**
 * ws_ckin.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
/**
 *  includes and requries
 */
require ("homeIncludes.php");

$wInit = new WebInit(WebPageCode::Service);

/* @var $dbh PDO */
$dbh = $wInit->dbh;

$uS = Session::getInstance();

$debugMode = ($uS->mode == "dev");

$guestAdmin = SecurityComponent::is_Authorized("guestadmin");

$c = "";

// Get our command
if (isset($_REQUEST["cmd"])) {
    $c = htmlspecialchars($_REQUEST["cmd"]);
}

$idGuest = 0;
if (isset($_REQUEST["idGuest"])) {
    $idGuest = intval(filter_var($_REQUEST["idGuest"], FILTER_SANITIZE_NUMBER_INT), 10);
}

$idVisit = 0;
if (isset($_REQUEST["idVisit"])) {
    $idVisit = intval(filter_var($_REQUEST["idVisit"], FILTER_SANITIZE_NUMBER_INT), 10);
}


$events = [];


try {

    switch ($c) {

        case 'rmlist':

            $idResv = 0;
            if (isset($_POST['rid'])) {
                $idResv = intval(filter_var($_POST['rid'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $x = 0;
            if (isset($_POST['x'])) {
                $x = filter_var($_POST['x'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            if ($idResv > 0) {

                $resv = Reservation_1::instantiateFromIdReserv($dbh, $idResv);
                $events = ReservationSvcs::getRoomList($dbh, $resv, $x, $guestAdmin);

            } else {
                $events = ['error' => 'Reservation Id is not set.'];
            }

            break;

        case 'chgRoomList':

            $changeDate = '';
            if (isset($_POST['chgDate'])) {
                $changeDate = filter_var($_POST['chgDate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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

        case 'showChangeRooms':

            $span = 0;
            if (isset($_POST['span'])) {
                $span = intval(filter_var($_POST['span'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $events = HouseServices::showChangeRooms($dbh, $idGuest, $idVisit, $span, $guestAdmin);

            break;

        case 'doChangeRooms':

            $span = 0;
            if (isset($_POST['span'])) {
                $span = intval(filter_var($_POST['span'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $idRoom = 0;
            if (isset($_POST['idRoom'])) {
                $idRoom = intval(filter_var($_POST['idRoom'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $useDefaultRate = FALSE;
            if (isset($_POST['useDefault'])) {
                $useDefaultRate = filter_var($_POST['useDefault'], FILTER_VALIDATE_BOOLEAN);
            }

            $changeDate = '';
            if (isset($_POST['changeDate'])) {
                $changeDate = filter_var($_POST['changeDate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $replaceRoom = '';
            if (isset($_POST['replaceRoom'])) {
                $replaceRoom = filter_var($_POST['replaceRoom'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $events = HouseServices::changeRooms($dbh, $idVisit, $span, $idRoom, $replaceRoom, $useDefaultRate, $changeDate);

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
                $expArr = filter_var($_POST['expArr'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $expDep = '';
            if (isset($_POST['expDep'])) {
                $expDep = filter_var($_POST['expDep'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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

            $notes = '';
            if (isset($_POST['notes'])) {
                $notes = filter_var($_POST['notes'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
                $eaddr = filter_var($_POST['eaddr'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $ccAddr = '';
            if (isset($_POST['ccAddr'])) {
                $ccAddr = filter_var($_POST['ccAddr'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $tabIndex = false;
            if (isset($_POST['tabIndex'])) {
                $tabIndex = filter_var($_POST['tabIndex'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $events = ReservationSvcs::getConfirmForm($dbh, $idresv, $idGuest, $amount, $sendemail, $notes, $eaddr, $tabIndex,$ccAddr);
            break;

        case 'saveRegForm':

            $guestId = intval(filter_input(INPUT_POST, 'guestId', FILTER_SANITIZE_NUMBER_INT), 10);
            $psgId = intval(filter_input(INPUT_POST, 'psgId', FILTER_SANITIZE_NUMBER_INT), 10);
            $idVisit = intval(filter_input(INPUT_POST, 'idVisit', FILTER_SANITIZE_NUMBER_INT), 10);
            $idResv = intval(filter_input(INPUT_POST, 'idResv', FILTER_SANITIZE_NUMBER_INT), 10);
            $docSignatures = json_decode(filter_input(INPUT_POST, "docSignatures", FILTER_UNSAFE_RAW), true);
            $uuid = filter_input(INPUT_POST, "uuid", FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            $formCode = filter_input(INPUT_POST, "formCode", FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $docTitle = ($idVisit > 0 ? "Visit " . $idVisit : Labels::getString("GuestEdit", "reservationTitle", "Reservation") . " " . $idResv) . " Registration Form";

            $regForms = $uS->regFormObjs;

            if(isset($uS->regFormObjs[$uuid]) && is_array($uS->regFormObjs[$uuid])){
                //find this form
                foreach($uS->regFormObjs[$uuid] as $doc){
                    if($doc["tabIndex"] === $formCode){
                        $docContents = HTMLContainer::generateMarkup('div', $doc['doc'], ['class' => 'PrintArea']);
                        break;
                    }
                }

                if(isset($docContents)){
                    $document = Document::createNew($docTitle, "text/html", $docContents, $uS->username, "reg");

                    $document->setAbstract(json_encode(['idVisit'=>$idVisit, 'idResv'=>$idResv]));
                    $document->setUserData(json_encode($docSignatures));

                    $document->saveNew($dbh);

                    if($document->linkNew($dbh, $guestId, $psgId) > 0){
                        $regformObjs = $uS->regformObjs;
                        unset($regformObjs[$uuid]);
                        $uS->regformObjs = $regformObjs;
                        $events = ["idDoc"=> $document->getIdDocument()];
                    }else{
                        $events = ["error" => "Unable to save Registration Form"];
                    }
                }else{
                    $events = ['error'=>'Unable to save Registration Form: Document not found'];
                }
            }else{
                $events = ['error' =>'Unable to save Registration Form: Document UUID does not match, please reload the page and try again.'];
            }
            break;

        case 'void':

            $idPayment = 0;
            if (isset($_POST['pid'])) {
                $idPayment = intval(filter_var($_POST['pid'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $bid = '';
            if (isset($_POST['bid'])) {
                $bid = filter_var($_POST['bid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
                $bid = filter_var($_POST['bid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
                $bid = filter_var($_POST['bid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
                $bid = filter_var($_POST['bid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
                $bid = filter_var($_POST['bid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
                $bid = filter_var($_POST['bid'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            if ($idInvoice > 0 && $idLine > 0) {

                $invoice = new Invoice($dbh);
                $invoice->loadInvoice($dbh, $idInvoice);

                if ($invoice->deleteLine($dbh, $idLine, $uS->username)) {
                    $events = ['bid' => $bid, 'success' => 'House Payment Deleted.  '];
                }
            }

            break;

    case "getReg":

        $idRegistration = 0;
        if (isset($_REQUEST["reg"])) {
            $idRegistration = intval(filter_var($_REQUEST["reg"], FILTER_SANITIZE_FULL_SPECIAL_CHARS), 10);
        }

        if ($idRegistration > 0) {
            $reg = new Registration($dbh, 0, $idRegistration);
            $mkup = HTMLContainer::generateMarkup('div', $reg->createRegMarkup($dbh, FALSE), ['class'=>"ui-widget ui-widget-content ui-corner-all hhk-panel hhk-tdbox"]);
            $events = ['success'=>$mkup];
        } else {
            $events = ['error'=>'Bad PSG Id.'];
        }

        break;

    case "saveReg":

        $idRegistration = 0;
        if (isset($_REQUEST["reg"])) {
            $idRegistration = intval(filter_var($_REQUEST["reg"], FILTER_SANITIZE_FULL_SPECIAL_CHARS), 10);
        }

        if ($idRegistration > 0) {

            $reg = new Registration($dbh, 0, $idRegistration);
            $reg->extractDialog();

            $reg->saveRegistrationRs($dbh, 0, $uS->username);
            $events = ['success'=>'Registration info saved.'];
        } else {
            $events = ['error'=>'Bad Registration Id.'];
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
            $s = htmlspecialchars($_POST['action']);
        }

        $cod = [];
        if (isset($_POST['ckoutdt'])) {

        	$cod = filter_var_array($_POST['ckoutdt'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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
            $id = intval(filter_var($_POST["id"], FILTER_SANITIZE_FULL_SPECIAL_CHARS), 10);
        }

        $idVisit = 0;
        if (isset($_POST["vid"])) {
            $idVisit = intval(filter_var($_POST["vid"], FILTER_SANITIZE_FULL_SPECIAL_CHARS), 10);
        }

        $visitSpan = 0;
        if (isset($_POST["span"])) {
            $visitSpan = intval(filter_var($_POST["span"], FILTER_SANITIZE_FULL_SPECIAL_CHARS), 10);
        }

        //$events = HouseServices::addVisitStay($dbh, $idVisit, $visitSpan, $id, $_POST);
        $events = ['error'=>'HouseServices::addVisitStay is Deprecated'];
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

        $post = filter_var_array($_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
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

        $data = filter_var_array($_POST, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $events = HouseServices::payInvoice($dbh, $id, $data);

        break;

    case "saveFees":

        $span = 0;
        if (isset($_POST['span'])) {
            $span = intval(filter_var($_POST['span'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $postbackPage = '';
        if (isset($_POST['pbp'])) {
            $postbackPage = filter_var($_POST['pbp'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $events = HouseServices::saveFees($dbh, $idVisit, $span, $_POST, $postbackPage);

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

            $discounts = Common::readGenLookupsPDO($dbh, 'House_Discount');
            $addnls = Common::readGenLookupsPDO($dbh, 'Addnl_Charge');

            foreach ($discounts as $n) {
                $events['disc'][$n[0]] = $n[2];
            }

            foreach ($addnls as $a) {
                $events['addnl'][$a[0]] = $a[2];
            }

            if (isset($_POST['arrDate'])) {
                $arrDate = filter_var($_POST['arrDate'],FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            }

            $vat = new ValueAddedTax($dbh);

            $events['markup'] = PaymentChooser::createHousePaymentMarkup($discounts, $addnls, $ordNum, $vat->getTaxedItemSums($ordNum, 0), $arrDate);

        } else {
            $events = ['error' => 'Visit Id is missing.  '];
        }

        break;

    // Save House payment dialog
    case 'saveHPay':

        $ord = 0;
        if (isset($_POST["ord"])) {
            $ord = intval(filter_var($_POST["ord"], FILTER_SANITIZE_FULL_SPECIAL_CHARS), 10);
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
            $discount = filter_var($_POST['dsc'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $addnlCharge = '';
        if (isset($_POST['chg'])) {
            $addnlCharge = filter_var($_POST['chg'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $adjDate = '';
        if (isset($_POST['adjDate'])) {
            $adjDate = filter_var($_POST['adjDate'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $notes = '';
        if (isset($_POST['notes'])) {
            $notes = filter_var($_POST['notes'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $events = HouseServices::saveHousePayment($dbh, $idItem, $ord, $amt, $discount, $addnlCharge, $adjDate, $notes);

        break;

    // View PSG
    case 'viewPSG':

        $idPsg = 0;
        if (isset($_POST['psg'])) {
            $idPsg = intval(filter_var($_POST['psg'], FILTER_SANITIZE_NUMBER_INT), 10);
            $psg = new PSG($dbh, $idPsg);
            $events = ['markup' => $psg->createEditMarkup($dbh, $uS->guestLookups[GLTableNames::PatientRel], new Labels())];

        } else {
            $events = ['error' => 'PSG ID is missing.'];
        }

        break;


    // Card on file
    case "cof":

        $postbackPage = '';
        if (isset($_POST['pbp'])) {
            $postbackPage = filter_var($_POST['pbp'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $index = '';
        if (isset($_POST['index'])) {
            $index = filter_var($_POST['index'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $idGroup = 0;
        if (isset($_POST['idGrp'])) {
            $idGroup = intval(filter_var($_POST['idGrp'], FILTER_SANITIZE_FULL_SPECIAL_CHARS), 10);
        }

        $events = HouseServices::cardOnFile($dbh, $idGuest, $idGroup, $_POST, $postbackPage, $index);

        break;

    // Card on file
    // case "viewCredit":

    //     $idReg = 0;
    //     if (isset($_POST['reg'])) {
    //         $idReg = intval(filter_var($_POST['reg'], FILTER_SANITIZE_NUMBER_INT), 10);
    //     }

    //     $pbp = '';
    //     if (isset($_POST['pbp'])) {
    //         $pbp = filter_var($_POST['pbp'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    //     }

    //     //$events = array('success'=>HouseServices::viewCreditTable($dbh, $idReg, 0), 'pbp'=>$pbp);

    //     break;

    case 'rvstat':

        $idReservation = 0;
        if (isset($_POST['rid'])) {
            $idReservation = intval(filter_var($_POST['rid'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $status = '';
        if (isset($_POST['stat'])) {
            $status = filter_var($_POST['stat'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $events = ReservationSvcs::changeReservStatus($dbh, $idReservation, $status);
        break;

    case 'cedd':

        $nd = '';
        if (isset($_POST['nd'])) {
            $nd = filter_var($_POST['nd'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $events = HouseServices::changeExpectedDepartureDate($dbh, $idGuest, $idVisit, $nd);
        break;

    case 'gtvlog':

        $wlid = 0;
        if (isset($_REQUEST["idReg"])) {
            $wlid = intval(filter_var($_REQUEST["idReg"], FILTER_SANITIZE_FULL_SPECIAL_CHARS), 10);
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

    case 'emailReceipt':
        
        $idPayment = 0;
        if (isset($_POST['paymentId'])) {
            $idPayment = intval(filter_var($_POST['paymentId'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $emailAddress = "";
        if (isset($_POST['emailAddress'])) {
            $emailAddress = filter_var($_POST['emailAddress'], FILTER_SANITIZE_EMAIL);
        }

        $receiptAr = PaymentSvcs::generateReceipt($dbh, $idPayment);

        if(isset($receiptAr["receipt"], $receiptAr["invoice"]) && $receiptAr["invoice"] instanceof Invoice){
            $events = PaymentSvcs::sendReceiptEmail($dbh, $receiptAr["receipt"], $receiptAr["invoice"], $emailAddress);
        }

        

        break;

    case 'downloadReceipt':
        // reprint a receipt
        $idPayment = 0;
        if (isset($_GET['paymentId'])) {
            $idPayment = intval(filter_var($_GET['paymentId'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        try{
            PaymentSvcs::downloadReceipt($dbh, $idPayment);
        }catch(\Exception $e){
            $events = ["error"=>$e->getMessage()];
        }

        break;

    case "chgPmtAmt":
        // respond to changes in payment record amount on Recent Payments tab and Guest Edit payments tab.

        $pid = 0;
        if (isset($_POST['pid'])) {
            $pid = intval(filter_var($_POST['pid'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $newAmt = 0.00;
        if (isset($_POST['amt'])) {
            $newAmt = floatval(filter_var($_POST['amt'], FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        }

        if ($guestAdmin) {
            //$events = HouseServices::changePaymentAmount($dbh, $pid, $newAmt);
            $events = ['error' => 'HouseServices::changePaymentAmount is Deprecated'];
        }

        break;

    case 'rtcalc':

        // Financial assistance rate category
        $events = RoomChooser::roomAmtCalculation($dbh, $_POST);
        break;

    default:
        $events = ["error" => "Bad Command: \"" . $c . "\""];
}

//make receipt copy
if(isset($events['receipt']) && $uS->merchantReceipt == true){
    $events['receipt'] = HTMLContainer::generateMarkup('div',
        HTMLContainer::generateMarkup('div', $events['receipt'] . HTMLContainer::generateMarkup('div', 'Customer Copy', ['style' => 'text-align:center;']), ['style' => 'margin-right: 15px; width: 100%;'])
        . HTMLContainer::generateMarkup('div', $events['receipt'] . HTMLContainer::generateMarkup('div', 'Merchant Copy', ['style' => 'text-align: center']), ['style' => 'margin-left: 15px; width: 100%;'])
        ,
            ['style' => 'display: flex; min-width: 100%;', 'data-merchCopy' => '1']);
}

} catch (\PDOException $ex) {
    $events = ["error" => "Database Error: " . $ex->getMessage() . ($debugMode ? $ex->getTraceAsString() : "")];
} catch(UnexpectedValueException $ex){
    $events = ["error" => $ex->getMessage() . ($debugMode ? $ex->getTraceAsString() : "")];
} catch (\Exception $ex) {
    $events = ["error" => "Web Server Error: " . $ex->getMessage() . ($debugMode ? $ex->getTraceAsString() : "")];
}



if (is_array($events)) {

    setHeaders($events);
    $json = json_encode($events);

    if ($json !== FALSE) {
        echo ($json);
    } else {
        $events = ["error" => "PHP json encoding error: " . json_last_error_msg()];
        echo json_encode($events);
    }

} else {
    echo $events;
}

function setHeaders($events){
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false && is_array($events)) {
        header("Content-Type: application/json"); //set content type to json if page expects json and return value is an array
    }
}

exit();

