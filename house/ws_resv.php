<?php
use HHK\sec\WebInit;
use HHK\SysConst\WebPageCode;
use HHK\sec\Session;
use HHK\House\Reservation\Reservation;
use HHK\House\Reservation\CheckingIn;
use HHK\House\Reservation\ActiveReservation;
use HHK\House\ReserveData\ReserveData;
use HHK\House\PSG;
use HHK\Note\ListNotes;
use HHK\Note\LinkNote;
use HHK\Note\Note;
use HHK\Incident\ListReports;
use HHK\Incident\Report;
use HHK\House\Hospital\{Hospital, HospitalStay};

/**
 * ws_resv.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
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


$c = "";

// Get our command
if (isset($_REQUEST["cmd"])) {
    $c = htmlspecialchars($_REQUEST["cmd"]);
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

        $events = $newResv->checkedinMarkup($dbh);

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


    case 'delResv':

        $resv = Reservation::reservationFactoy($dbh, $_POST);

        $events = $resv->delete($dbh, $_POST);

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
            $idResc = filter_var($_POST['idResc'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        $resv = new ActiveReservation(new ReserveData($_POST), null, null);

        $events = $resv->changeRoom($dbh, $idResv, $idResc);

        break;

    case 'viewHS':

    	$idHs = 0;
    	if (isset($_POST['idhs'])) {
    		$idHs = intval(filter_input(INPUT_POST, 'idhs', FILTER_SANITIZE_NUMBER_INT), 10);
    	}

    	$hArray = Hospital::createReferralMarkup($dbh, new HospitalStay($dbh, 0, $idHs), FALSE);

    	$events = array('success'=>$hArray['div'], 'title'=>$hArray['title']);

    	break;

    case 'saveHS':

    	$idHs = 0;
    	if (isset($_POST['idhs'])) {
    		$idHs = intval(filter_input(INPUT_POST, 'idhs', FILTER_SANITIZE_NUMBER_INT), 10);
    	}
    	$idVisit = 0;
    	if (isset($_POST['idv'])) {
    		$idVisit = intval(filter_input(INPUT_POST, 'idv', FILTER_SANITIZE_NUMBER_INT), 10);
    	}

    	if ($idHs > 0 && $idVisit > 0) {

    		$hstay = new HospitalStay($dbh, 0, $idHs, FALSE);

    		$newHsId = Hospital::saveReferralMarkup($dbh, new PSG($dbh, 0, $hstay->getIdPatient()), $hstay, $_POST);

    		if ($newHsId != $idHs) {
    			// Update visit and reservation
    			$dbh->exec("call updt_visit_hospstay($idVisit, $newHsId);");
    		}

    		$events = array('success'=>'Hospital Saved');

    	} else {
    		$events = array('error'=>'Missing ids. ');
    	}

    	break;

    case 'getNoteList':

        $linkType = '';
        $idLink = 0;

        if (isset($_GET['linkType'])) {
            $linkType = filter_input(INPUT_GET, 'linkType');
        }

        if (isset($_GET['linkId'])) {
            $idLink = intval(filter_input(INPUT_GET, 'linkId', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $events = ListNotes::loadList($dbh, $idLink, $linkType, $_GET, $uS->ConcatVisitNotes);

        break;


    case 'saveNote':

        $data = '';
        $noteCategory = '';
        $linkType = '';
        $idLink = 0;

        if (isset($_POST['data'])) {
            $data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        if(isset($_POST['noteCategory'])){
            $noteCategory = filter_input(INPUT_POST, 'noteCategory', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        if (isset($_POST['linkType'])) {
            $linkType = filter_input(INPUT_POST, 'linkType', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        if (isset($_POST['linkId'])) {
            $idLink = intval(filter_input(INPUT_POST, 'linkId', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $events = array('idNote'=>LinkNote::save($dbh, $data, $idLink, $linkType, $noteCategory, $uS->username, $uS->ConcatVisitNotes));

        break;


    case 'updateNoteContent':

        $data = '';
        $noteCategory = '';
        $noteId = 0;
        $updateCount = 0;

        if (isset($_POST['data'])) {
	       $data = filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        if(isset($_POST['noteCategory'])){
            $noteCategory = filter_input(INPUT_POST, 'noteCategory', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        if (isset($_POST['idNote'])) {
            $noteId = intval(filter_input(INPUT_POST, 'idNote', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if ($noteId > 0 && $data != '') {

            $note = new Note($noteId);
            $updateCount = $note->updateContents($dbh, $data, $noteCategory, $uS->username);
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
            $linkType = filter_input(INPUT_POST, 'linkType', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        if (isset($_POST['linkId'])) {
            $idLink = intval(filter_input(INPUT_POST, 'linkId', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $events = array('warning'=>'Link Note is not implemented.  ');

        break;

	case 'getIncidentList':

        $psgId = 0;
        $guestId = 0;


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

        //require(CLASSES . 'DataTableServer.php');

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
	            $reportAr['description'] = nl2br($reportAr['description']);
	            $reportAr['resolution'] = nl2br($reportAr['resolution']);
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

} catch (NotFoundException $e){
    $events = array("error" => $e->getMessage());
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
?>