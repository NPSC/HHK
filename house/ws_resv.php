
<?php
/**
 * ws_resv.php
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
require (DB_TABLES . 'ReportRS.php');

require CLASSES . 'CleanAddress.php';
require CLASSES . 'AuditLog.php';
require CLASSES . 'History.php';
require (CLASSES . 'CreateMarkupFromDB.php');


require (CLASSES . 'Note.php');
require (CLASSES . 'ListNotes.php');
require (CLASSES . 'ListReports.php');
require (CLASSES . 'LinkNote.php');
require (CLASSES . 'Report.php');
require (CLASSES . 'US_Holidays.php');
require (CLASSES . 'PaymentSvcs.php');
require (CLASSES . 'FinAssistance.php');
require CLASSES . 'TableLog.php';
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
require (CLASSES . "ValueAddedTax.php");
require(CLASSES . 'Purchase/RoomRate.php');

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
require (HOUSE . 'Reservation.php');
require (HOUSE . 'ReservationSvcs.php');
require (HOUSE . 'RegisterForm.php');
require (HOUSE . 'ReserveData.php');
require (HOUSE . 'RegistrationForm.php');
require (HOUSE . 'VisitLog.php');
require (HOUSE . 'RoomLog.php');
require (HOUSE . 'Vehicle.php');
require (HOUSE . 'Visit.php');
require (HOUSE . 'Family.php');
require (HOUSE . "visitViewer.php");

require (HOUSE . "CurrentAccount.php");

require (HOUSE . 'VisitCharges.php');


$wInit = new webInit(WebPageCode::Service);

/* @var $dbh PDO */
$dbh = $wInit->dbh;

$uS = Session::getInstance();
creditIncludes($uS->PaymentGateway);

$c = "";

// Get our command
if (isset($_REQUEST["cmd"])) {
    $c = filter_var($_REQUEST["cmd"], FILTER_SANITIZE_STRING);
}


$events = array();


try {

    switch ($c) {

    case "getResv":

        $resv = Reservation::reservationFactoy($dbh, $_POST);

        $events = $resv->createMarkup($dbh);

        break;


    case "saveResv":

        $resv = Reservation::reservationFactoy($dbh, $_POST);

        $newResv = $resv->save($dbh, $_POST);

        $events = $newResv->createMarkup($dbh);

        break;


    case "getCkin":

        $resv = CheckingIn::reservationFactoy($dbh, $_POST);

        $events = $resv->createMarkup($dbh);

        break;


    case 'saveCheckin':

        $resv = CheckingIn::reservationFactoy($dbh, $_POST);

        $newResv = $resv->save($dbh, $_POST);

        $events = $newResv->checkedinMarkup($dbh);

        break;


    case "addResvGuest":

        $isCheckin = FALSE;

        if (isset($_POST['isCheckin'])) {
            $isCheckin = filter_var($_POST['isCheckin'], FILTER_VALIDATE_BOOLEAN);
        }

        if ($isCheckin) {
            $resv = CheckingIn::reservationFactoy($dbh, $_POST);
        } else {
            $resv = Reservation::reservationFactoy($dbh, $_POST);
        }

        $events = $resv->addPerson($dbh);

        break;

    case 'moveResvRoom':

        $idResv = 0;
        if (isset($_POST['rid'])) {
            $idResv = intval(filter_var($_POST['rid'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $idResc = '';
        if (isset($_POST['idResc'])) {
            $idResc = filter_var($_POST['idResc'], FILTER_SANITIZE_STRING);
        }

        $resv = new ActiveReservation(new ReserveData($_POST), null, null);

        $events = $resv->changeRoom($dbh, $idResv, $idResc);

        break;


    case 'getNoteList':

        $linkType = '';
        $idLink = 0;

        if (isset($_GET['linkType'])) {
            $linkType = filter_input(INPUT_GET, 'linkType', FILTER_SANITIZE_STRING);
        }

        if (isset($_GET['linkId'])) {
            $idLink = intval(filter_input(INPUT_GET, 'linkId', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        require(CLASSES . 'DataTableServer.php');

        $events = ListNotes::loadList($dbh, $idLink, $linkType, $_GET, $uS->ConcatVisitNotes);

        break;


    case 'saveNote':

        $data = '';
        $linkType = '';
        $idLink = 0;

        if (isset($_POST['data'])) {
            $data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING);
        }

        if (isset($_POST['linkType'])) {
            $linkType = filter_input(INPUT_POST, 'linkType', FILTER_SANITIZE_STRING);
        }

        if (isset($_POST['linkId'])) {
            $idLink = intval(filter_input(INPUT_POST, 'linkId', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $events = array('idNote'=>LinkNote::save($dbh, $data, $idLink, $linkType, $uS->username, $uS->ConcatVisitNotes));

        break;


    case 'updateNoteContent':

        $data = '';
        $noteId = 0;
        $updateCount = 0;

        if (isset($_POST['data'])) {
	    $data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING);
            //$data = addcslashes(filter_input(INPUT_POST, 'data', FILTER_SANITIZE_STRING));
        }
        if (isset($_POST['idNote'])) {
            $noteId = intval(filter_input(INPUT_POST, 'idNote', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if ($noteId > 0 && $data != '') {

            $note = new Note($noteId);
            $updateCount = $note->updateContents($dbh, $data, $uS->username);
        }

        $events = array('update'=>$updateCount, 'idNote'=>$noteId);

        break;


    case 'deleteNote':

        $noteId = 0;
        $deleteCount = 0;

        if (isset($_POST['idNote'])) {
            $noteId = intval(filter_input(INPUT_POST, 'idNote', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if ($noteId > 0) {
            $note = new Note($noteId);
            $deleteCount = $note->deleteNote($dbh, $uS->userName);
        }

        $events = array('delete'=>$deleteCount, 'idNote'=>$noteId);

        break;


    case 'undoDeleteNote':

        $noteId = 0;
        $deleteCount = 0;

        if (isset($_POST['idNote'])) {
            $noteId = intval(filter_input(INPUT_POST, 'idNote', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if ($noteId > 0) {
            $note = new Note($noteId);
            $deleteCount = $note->undoDeleteNote($dbh, $uS->userName);
        }

        $events = array('delete'=>$deleteCount, 'idNote'=>$noteId);

        break;

    case 'flagNote':

        $noteId = 0;
        $flagCount = 0;

        if (isset($_POST['idNote'])) {
            $noteId = intval(filter_input(INPUT_POST, 'idNote', FILTER_SANITIZE_NUMBER_INT), 10);
        }
        
        if (isset($_POST['flag'])) {
            $flag = intval(filter_input(INPUT_POST, 'flag', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if ($noteId > 0) {
            $note = new Note($noteId);
            $flagCount = $note->flagNote($dbh, $flag, $uS->userName);
        }

        $events = array('update'=>$flagCount, 'idNote'=>$noteId, 'flag'=>$flag);

        break;

    case 'linkNote':

        $noteId = 0;
        $linkType = '';
        $idLink = 0;

        if (isset($_POST['idNote'])) {
            $noteId = intval(filter_input(INPUT_POST, 'idNote', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if (isset($_POST['linkType'])) {
            $linkType = filter_input(INPUT_POST, 'linkType', FILTER_SANITIZE_STRING);
        }

        if (isset($_POST['linkId'])) {
            $idLink = intval(filter_input(INPUT_POST, 'linkId', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $events = array('warning'=>'Link Note is not implemented.  ');

        break;

	case 'getIncidentList':

        $psgId = 0;
        $guestId = 0;
        $rId = 0;

        if (isset($_GET['psgId'])) {
            $psgId = intval(filter_input(INPUT_GET, 'psgId', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if (isset($_GET['guestId'])) {
            $guestId = intval(filter_input(INPUT_GET, 'guestId', FILTER_SANITIZE_NUMBER_INT), 10);
        }
        
        if (isset($_GET['rid'])) {
            $rid = intval(filter_input(INPUT_GET, 'rid', FILTER_SANITIZE_NUMBER_INT), 10);
            $stmt = $dbh->query("SELECT reg.idPsg FROM reservation res
JOIN registration reg on res.`idRegistration` = reg.`idRegistration`
WHERE res.`idReservation` = " . $rid . " LIMIT 1;");
			$result = $stmt->fetchAll();
			if(count($result) == 1){
				$psgId = $result[0]["idPsg"];
			}
        }

        require(CLASSES . 'DataTableServer.php');

        $events = ListReports::loadList($dbh, $guestId, $psgId, $_GET);

        break;

	case 'getincidentreport':
        
        	$idReport = 0;
            if (isset($_POST['repid'])) {
                $idReport = intval(filter_var($_POST['repid'], FILTER_SANITIZE_NUMBER_INT), 10);
            }
            
            $report = new Report($idReport);
			$report->loadReport($dbh);
			$idGuest = $report->getGuestId();
			$reportAr = $report->toArray();
            
            if(isset($_POST['print'])){
	            $stmt = $dbh->query("SELECT * from `vguest_listing` where id = $idGuest limit 1");
	            $guestAr = $stmt->fetch(PDO::FETCH_ASSOC);
	            $reportAr = $reportAr + ["guest"=>$guestAr];
            }
            
            $events = $reportAr;
        	break;
        	
    case 'saveIncident':
		
		$guestId = 0;
		$psgId = 0;
		$incidentTitle = '';
		$incidentDate = '';
		$incidentDescription = '';
		$incidentStatus = 'a';
		$incidentResolution = '';
		$resolutionDate = '';
		$signature = '';
		$signatureDate = '';
		
		if (isset($_POST['guestId'])) {
            $guestId = $_POST['guestId'];
        }
        if (isset($_POST['psgId'])) {
            $psgId = $_POST['psgId'];
        }
        if (isset($_POST['incidentTitle'])) {
            $incidentTitle = $_POST['incidentTitle'];
        }
        if (isset($_POST['incidentDate'])) {
            $incidentDate = $_POST['incidentDate'];
        }
        if (isset($_POST['incidentDescription'])) {
            $incidentDescription = $_POST['incidentDescription'];
        }
        if (isset($_POST['incidentStatus'])) {
            $incidentStatus = $_POST['incidentStatus'];
        }
        if (isset($_POST['incidentResolution'])) {
            $incidentResolution = $_POST['incidentResolution'];
        }
        if (isset($_POST['resolutionDate'])) {
            $resolutionDate = $_POST['resolutionDate'];
        }
        if (isset($_POST['signature'])) {
            $signature = $_POST['signature'];
        }
        if (isset($_POST['signatureDate'])) {
            $signatureDate = $_POST['signatureDate'];
        }
        
        $report = Report::createNew($incidentTitle, $incidentDate, $incidentDescription, $uS->username, $incidentStatus, $incidentResolution, $resolutionDate, $signature, $signatureDate, $guestId, $psgId);
		$report->saveNew($dbh);

        $events = array('status'=>'success', 'idReport'=>$report->getIdReport());

        break;


    case 'editIncident':
		$repId = 0;
		$incidentTitle = '';
		$incidentDate = '';
		$incidentDescription = '';
		$incidentStatus = 'a';
		$incidentResolution = '';
		$resolutionDate = '';
		$signature = '';
		$signatureDate = '';
		
		if (isset($_POST['repId'])) {
            $repId = $_POST['repId'];
        }
        if (isset($_POST['incidentTitle'])) {
            $incidentTitle = $_POST['incidentTitle'];
        }
        if (isset($_POST['incidentDate'])) {
            $incidentDate = $_POST['incidentDate'];
        }
        if (isset($_POST['incidentDescription'])) {
            $incidentDescription = $_POST['incidentDescription'];
        }
        if (isset($_POST['incidentStatus'])) {
            $incidentStatus = $_POST['incidentStatus'];
        }
        if (isset($_POST['incidentResolution'])) {
            $incidentResolution = $_POST['incidentResolution'];
        }
        if (isset($_POST['resolutionDate'])) {
            $resolutionDate = $_POST['resolutionDate'];
        }
        if (isset($_POST['signature'])) {
            $signature = $_POST['signature'];
        }
        if (isset($_POST['signatureDate'])) {
            $signatureDate = $_POST['signatureDate'];
        }
        
        $report = new Report($repId);
        $report->updateContents($dbh, $incidentTitle, $incidentDate, $resolutionDate, $incidentDescription, $incidentResolution,$signature, $signatureDate, $incidentStatus, $uS->username);

        $events = array('status'=>'success', 'idReport'=>$report->getIdReport(), 'incidentTitle'=>$incidentTitle, 'incidentDate'=>$incidentDate, 'incidentStatus'=>$incidentStatus);

        break;


    case 'deleteIncident':

        $repId = 0;
        $deleteCount = 0;

        if (isset($_POST['idReport'])) {
            $repId = intval(filter_input(INPUT_POST, 'idReport', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if ($repId > 0) {
            $report = new Report($repId);
            $deleteCount = $report->deleteReport($dbh, $uS->userName);
        }

        $events = array('delete'=>$deleteCount, 'idReport'=>$repId);

        break;


    case 'undoDeleteIncident':

        $repId = 0;
        $deleteCount = 0;

        if (isset($_POST['idReport'])) {
            $repId = intval(filter_input(INPUT_POST, 'idReport', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if ($repId > 0) {
            $report = new Report($repId);
            $deleteCount = $report->undoDeleteReport($dbh, $uS->userName);
        }

        $events = array('delete'=>$deleteCount, 'idReport'=>$repId);

        break;


    case 'updateAgenda':

        $events = Reservation::updateAgenda($dbh, $_POST);
        break;


    default:
        $events = array("error" => "Bad Command: \"" . $c . "\"");
}

} catch (PDOException $ex) {
    $events = array("error" => "Database Error: " . $ex->getMessage() . "<br/>" . $ex->getTraceAsString());
} catch (Exception $ex) {
    $events = array("error" => "Web Server Error: " . $ex->getMessage() . "<br/>" . $ex->getTraceAsString());
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