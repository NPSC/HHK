<?php
use HHK\Exception\NotFoundException;
use HHK\Exception\SmsException;
use HHK\Exception\ValidationException;
use HHK\Exception\UnexpectedValueException;
use HHK\House\Hospital\{Hospital, HospitalStay};
use HHK\House\PSG;
use HHK\House\Reservation\ActiveReservation;
use HHK\House\Reservation\CheckingIn;
use HHK\House\Reservation\Reservation;
use HHK\House\ReserveData\ReserveData;
use HHK\House\Vehicle;
use HHK\House\Visit\Visit;
use HHK\HTMLControls\HTMLContainer;
use HHK\Incident\ListReports;
use HHK\Incident\IncidentReport;
use HHK\Member\Address\Phones;
use HHK\Member\IndivMember;
use HHK\Note\LinkNote;
use HHK\Note\ListNotes;
use HHK\Note\Note;
use HHK\Notification\SMS\SimpleTexting\Campaign;
use HHK\Notification\SMS\SimpleTexting\Contact;
use HHK\Notification\SMS\SimpleTexting\Contacts;
use HHK\Notification\SMS\SimpleTexting\Message;
use HHK\Notification\SMS\SimpleTexting\Messages;
use HHK\sec\SecurityComponent;
use HHK\sec\Session;
use HHK\sec\WebInit;
use HHK\SysConst\GLTableNames;
use HHK\SysConst\MemBasis;
use HHK\SysConst\PhonePurpose;
use HHK\SysConst\WebPageCode;
use HHK\TableLog\NotificationLog;

/**
 * ws_resv.php
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

$inputData = $_REQUEST;    // page received data, initially from $_REQUEST
$command = '';     // web service ciommand
$events = [];       // output data.


// check for posted application/json data in a POST request
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false   && strtoupper($_SERVER['REQUEST_METHOD']) == 'POST') {

    // Get the posted data
    $inputJson = file_get_contents('php://input');
    if ($inputJson === false) {
        //failure
        $events = ["error" => "posted data input failure."];
    }

    // Decode the json posted data
    $inputEncoded = json_decode($inputJson, true);
    if ($inputEncoded === null) {
        //failure
        $events = ["error" => "posted data input failure."];
    }

    $inputData = [];

    // convert string representations of arrays into an array
    try {
        $inputData = parseKeysToArray($inputEncoded);
        
    } catch (UnexpectedValueException $ex) {
        $events = ["error" => "posted data input failure."];    //failure
        $json = json_encode($events);

        if ($json !== FALSE) {
            echo $json;
        } else {
            $events = ["error" => "PHP json encoding error: " . json_last_error_msg()];
            echo json_encode($events);
        }
    }
}


// Check the webservice command
if (isset($inputData['cmd'])) {
    $command = htmlspecialchars($inputData['cmd']);
}




try {

    switch ($command) {

    case "getResv":

        $resv = Reservation::reservationFactoy($dbh, $inputData);

        $events = $resv->createMarkup($dbh);

        break;


    case "saveResv":

        $resv = Reservation::reservationFactoy($dbh, $inputData);

        $newResv = $resv->save($dbh);

        $events = $newResv->checkedinMarkup($dbh);

        break;


    case "getCkin":

        $resv = CheckingIn::reservationFactoy($dbh, $inputData);

        $events = $resv->createMarkup($dbh);

        break;


    case 'saveCheckin':

        $resv = CheckingIn::reservationFactoy($dbh, $inputData);

        $newResv = $resv->save($dbh);

        $events = $newResv->checkedinMarkup($dbh);

        break;


    case 'delResv':


        $resv = Reservation::reservationFactoy($dbh, $inputData);

        $events = $resv->delete($dbh);

        break;


    case "addResvGuest":

        $isCheckin = FALSE; 

        if (isset($_POST['isCheckin'])) {
            $isCheckin = filter_var($_POST['isCheckin'], FILTER_VALIDATE_BOOLEAN);
        }

        if ($isCheckin) {
            $resv = CheckingIn::reservationFactoy($dbh, $inputData);
        } else {
            $resv = Reservation::reservationFactoy($dbh, $inputData);
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

        $resv = new ActiveReservation(new ReserveData($inputData), null, null);

        $events = $resv->changeRoom($dbh, $idResv, $idResc);

        break;

    case 'viewHS':

    	$idHs = 0;
    	if (isset($_POST['idhs'])) {
    		$idHs = intval(filter_input(INPUT_POST, 'idhs', FILTER_SANITIZE_NUMBER_INT), 10);
    	}

    	$hArray = Hospital::createReferralMarkup($dbh, new HospitalStay($dbh, 0, $idHs), FALSE);

    	$events = ['success' => $hArray['div'], 'title' => $hArray['title']];

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

    		$hstay = new HospitalStay($dbh, 0, $idHs);

    		$newHsId = Hospital::saveReferralMarkup($dbh, new PSG($dbh, 0, $hstay->getIdPatient()), $hstay, $_POST);

    		if ($newHsId != $idHs) {
    			// Update visit and reservation
    			$dbh->exec("call updt_visit_hospstay($idVisit, $newHsId);");
    		}

    		$events = ['success' => 'Hospital Saved', 'newHsId' => $newHsId];

    	} else {
    		$events = ['error' => 'Missing ids. '];
    	}

    	break;

        case 'viewVeh':

            $idV = 0;
            if (isset($_POST['idV'])) {
                $idV = intval(filter_input(INPUT_POST, 'idV', FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $vehStmt = $dbh->prepare("select idReservation, idRegistration from visit where idVisit = :idv limit 1");
            $vehStmt->execute([':idv' => $idV]);
            $row = $vehStmt->fetch(PDO::FETCH_ASSOC);

            $mkup = Vehicle::createVehicleMarkup($dbh, $row["idRegistration"], $row["idReservation"], false);

            $events = ['success' => $mkup, 'title' => "Edit Vehicles"];

            break;

        case 'saveVeh':

            $idV = 0;
            if (isset($_POST['idV'])) {
                $idV = intval(filter_input(INPUT_POST, 'idV', FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $vehStmt = $dbh->prepare("select idReservation, idRegistration from visit where idVisit = :idv limit 1");
            $vehStmt->execute([':idv' => $idV]);
            $row = $vehStmt->fetch(PDO::FETCH_ASSOC);

            try {
                $events = ["success"=> Vehicle::saveVehicle($dbh, $row["idRegistration"], $row["idReservation"])];
            }catch(\Exception $e){
                $events = ["error" => "Error saving vehicle: " . $e->getMessage()];
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
            $data = base64_decode(filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
            $data = filter_var($data, FILTER_SANITIZE_FULL_SPECIAL_CHARS); //sanitize decoded data
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

        if (isset($_POST['guestId'])) {
            $idGuest = intval(filter_input(INPUT_POST, 'guestId', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        $events = ['idNote' => LinkNote::save($dbh, $data, $idLink, $linkType, $noteCategory, $uS->username, $uS->ConcatVisitNotes)];

        break;


    case 'updateNoteContent':

        $data = '';
        $noteCategory = '';
        $noteId = 0;
        $updateCount = 0;

        if (isset($_POST['data'])) {
	       $data = base64_decode(filter_input(INPUT_POST, 'data', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
           $data = filter_var($data, FILTER_SANITIZE_FULL_SPECIAL_CHARS); //sanitize decoded data
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

        $events = ['update' => $updateCount, 'idNote' => $noteId];

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

        $events = ['delete' => $deleteCount, 'idNote' => $noteId];

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

        $events = ['delete' => $deleteCount, 'idNote' => $noteId];

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

        $events = ['update' => $flagCount, 'idNote' => $noteId, 'flag' => $flag];

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

        $events = ['warning' => 'Link Note is not implemented.  '];

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

        $events = ListReports::loadList($dbh, $guestId, $psgId, $_GET);

        break;

	case 'getincidentreport':

        	$idReport = 0;
            if (isset($_POST['repid'])) {
                $idReport = intval(filter_var($_POST['repid'], FILTER_SANITIZE_NUMBER_INT), 10);
            }

            $report = new IncidentReport($idReport);
			$report->loadReport($dbh);
			$idGuest = $report->getGuestId();
			$reportAr = $report->toArray();

            //user can edit?
            $reportAr["userCanEdit"] = $report->userCanEdit();

            if(isset($_POST['print'])){
	            $stmt = $dbh->query("SELECT * from `vguest_listing` where id = $idGuest limit 1");
	            $guestAr = $stmt->fetch(\PDO::FETCH_ASSOC);
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

        $report = IncidentReport::createNew($incidentTitle, $incidentDate, $incidentDescription, $uS->username, $incidentStatus, $incidentResolution, $resolutionDate, $signature, $signatureDate, $guestId, $psgId);
		$report->saveNew($dbh);

        $events = ['status' => 'success', 'idReport' => $report->getIdReport()];

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

        $report = new IncidentReport($repId);
        $report->updateContents($dbh, $incidentTitle, $incidentDate, $resolutionDate, $incidentDescription, $incidentResolution,$signature, $signatureDate, $incidentStatus, $uS->username);

        $events = ['status' => 'success', 'idReport' => $report->getIdReport(), 'incidentTitle' => $incidentTitle, 'incidentDate' => $incidentDate, 'incidentStatus' => $incidentStatus];

        break;


    case 'deleteIncident':

        $repId = 0;
        $deleteCount = 0;

        if (isset($_POST['idReport'])) {
            $repId = intval(filter_input(INPUT_POST, 'idReport', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if ($repId > 0) {
            $report = new IncidentReport($repId);
            $deleteCount = $report->deleteReport($dbh, $uS->userName);
        }

        $events = ['delete' => $deleteCount, 'idReport' => $repId];

        break;


    case 'undoDeleteIncident':

        $repId = 0;
        $deleteCount = 0;

        if (isset($_POST['idReport'])) {
            $repId = intval(filter_input(INPUT_POST, 'idReport', FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if ($repId > 0) {
            $report = new IncidentReport($repId);
            $deleteCount = $report->undoDeleteReport($dbh, $uS->userName);
        }

        $events = ['delete' => $deleteCount, 'idReport' => $repId];

        break;


    case 'updateAgenda':

        $events = Reservation::updateAgenda($dbh, $_POST);
        break;

    case 'getVisitMsgsDialog':

        $idVisit = intval(filter_input(INPUT_GET, 'idVisit', FILTER_SANITIZE_NUMBER_INT));
        $idSpan = intval(filter_input(INPUT_GET, 'idSpan', FILTER_SANITIZE_NUMBER_INT));

        if($idVisit > 0 && $idSpan >= 0){
            $messages = new Messages($dbh);

            //$events = ['mkup' => $messages->getVisitMessagesMkup($idVisit, $idSpan)];
            $events = $messages->getVisitGuestsData($idVisit, $idSpan);
        }else{
            throw new NotFoundException("Visit ID not found");
        }

        break;

    case 'getResvMsgsDialog':

        $idResv = intval(filter_input(INPUT_GET, 'idResv', FILTER_SANITIZE_NUMBER_INT));

        if($idResv > 0){
            $messages = new Messages($dbh);

            $events = $messages->getResvGuestsData($idResv);
        }else{
            throw new NotFoundException("Reservation ID not found");
        }

        break;

    case 'getCampaignMsgsDialog':
        $status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $messages = new Messages($dbh);

        $events = $messages->getCampaignGuestsData($status);

        break;

    case 'getGuestMsgsDialog':
        $idName = intval(filter_input(INPUT_GET, 'idName', FILTER_SANITIZE_NUMBER_INT));

        $messages = new Messages($dbh);

        $events = $messages->getGuestData($idName);

        break;

    case 'loadMsgs':
        $idName = intval(filter_input(INPUT_GET, 'idName', FILTER_SANITIZE_NUMBER_INT));

        if($idName > 0){
            $messages = new Messages($dbh);

            $events = $messages->getMessages($idName);
        }else{
            throw new NotFoundException("idName not found");
        }

        break;

    case 'sendMsg':
        $idName = intval(filter_input(INPUT_POST, 'idName', FILTER_SANITIZE_NUMBER_INT));
        $msgText = filter_input(INPUT_POST, 'msgText', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if ($idName > 0) {
            $uS = Session::getInstance();
            $name = new IndivMember($dbh, MemBasis::Indivual, $idName);
            $phones = new Phones($dbh, $name, $uS->nameLookups[GLTableNames::PhonePurpose]);
            $cell = $phones->get_Data(PhonePurpose::Cell);

            if (strlen($cell["Unformatted_Phone"]) <= 10) {
                //upsert contact before send
                $contact = new Contact($dbh, true);
                $contact->upsert($cell["Unformatted_Phone"], $name->get_nameRS()->Name_First->getStoredVal(), $name->get_nameRS()->Name_Last->getStoredVal());
                try {
                    $msg = new Message($dbh, $cell["Unformatted_Phone"], $msgText);
                    $events = $msg->sendMessage();
                    NotificationLog::logSMS($dbh, $uS->smsProvider, $uS->username, $cell["Unformatted_Phone"], $uS->smsFrom, "Message sent successfully", ["msgText" => $msgText]);
                }catch(SmsException $e){
                    NotificationLog::logSMS($dbh, $uS->smsProvider, $uS->username, $cell["Unformatted_Phone"], $uS->smsFrom, "Failed to send message: " . $e->getMessage(), ["msgText" => $msgText]);
                    throw $e;
                }
            }else{
                throw new ValidationException("Cell Number Invalid");
            }
        }else{
            throw new NotFoundException("idName not found");
        }

        break;

    case 'sendVisitMsg':
        $idVisit = intval(filter_input(INPUT_POST, 'idVisit', FILTER_SANITIZE_NUMBER_INT));
        $idSpan = intval(filter_input(INPUT_POST, 'idSpan', FILTER_SANITIZE_NUMBER_INT));
        $msgText = filter_input(INPUT_POST, 'msgText', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $messages = new Messages($dbh, true);

        $events = $messages->sendVisitMessage($idVisit, $idSpan, $msgText);

        break;

        case 'sendResvMsg':
            $idResv = intval(filter_input(INPUT_POST, 'idResv', FILTER_SANITIZE_NUMBER_INT));
            $msgText = filter_input(INPUT_POST, 'msgText', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            $messages = new Messages($dbh, true);

            $events = $messages->sendResvMessage($idResv, $msgText);

            break;

    case 'sendCampaign':
        $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $msgText = filter_input(INPUT_POST, 'msgText', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $batchId = filter_input(INPUT_POST, 'batchId', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $campaignListId = filter_input(INPUT_POST, 'campaignListId', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $campaignListName = filter_input(INPUT_POST, 'campaignListName', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $campaign = new Campaign($dbh, $msgText, $msgText);
            if ($status) {
                $events = $campaign->prepareAndSendCampaign($status);
            }else if($batchId && $campaignListId && $campaignListName){
                $events = $campaign->checkBatchAndSendCampaign($batchId, $campaignListId, $campaignListName);
            }

        break;
    case 'loadContacts':

        $status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $contacts = new Contacts($dbh);

        if($status){
            $events = $contacts->fetchContacts($status);
        }else{
            $events = $contacts->fetchContacts();
        }

        break;

    case 'syncContacts':
        $status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        $contacts = new Contacts($dbh, true);

        $events = $contacts->syncContacts($status);

        break;

    default:
        $events = ["error" => "Bad Command: \"" . $command . "\""];
}

} catch (NotFoundException | ValidationException | SmsException $e){
    $events = ["error" => $e->getMessage()];
} catch (PDOException $ex) {
    $events = ["error" => "Database Error: " . $ex->getMessage() . ($debugMode ? $ex->getTraceAsString() : "")];
} catch (Exception $ex) {
    $events = ["error" => "Web Server Error: " . $ex->getMessage() . ($debugMode ? $ex->getTraceAsString() : "")];
}


//make receipt copy
if(isset($events['receiptMarkup']) && $uS->merchantReceipt == true){
    $events['receiptMarkup'] = HTMLContainer::generateMarkup('div',
        HTMLContainer::generateMarkup('div', $events['receiptMarkup'] . HTMLContainer::generateMarkup('div', 'Customer Copy', ['style' => 'text-align:center;']), ['style' => 'margin-right: 15px; width: 100%;'])
        . HTMLContainer::generateMarkup('div', $events['receiptMarkup'] . HTMLContainer::generateMarkup('div', 'Merchant Copy', ['style' => 'text-align: center']), ['style' => 'margin-left: 15px; width: 100%;'])
        ,
            ['style' => 'display: flex; min-width: 100%;', 'data-merchCopy' => '1']);
}


if (is_array($events)) {

    $json = json_encode($events);

    if ($json !== FALSE) {
        echo $json;
    } else {
        $events = ["error" => "PHP json encoding error: " . json_last_error_msg()];
        echo json_encode($events);
    }

} else {
    echo $events;
}

exit();