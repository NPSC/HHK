<?php
/**
 * ReservationSvcs.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of ReservationSvcs
 *
 * @author Eric
 */

class ReservationSvcs {


    public static function viewActivity(\PDO $dbh, $idResv) {

        if ($idResv < 1) {
            return array('error'=>'Reservation not defined.  ');
        }

        return array('activity'=>  ActivityReport::reservLog($dbh, '', '', $idResv));
    }

    public static function getCurrentReservations(\PDO $dbh, $idResv, $id, $idPsg, \DateTime $startDT, \DateTime $endDT) {

        $rows = array();

        if ($idPsg > 0 && $id > 0) {
            // look for both
            $stmt = $dbh->query("select * from vresv_guest "
                    . "where (idPsg = $idPsg or idGuest = $id) and idReservation != $idResv and "
                . "Date(Arrival_Date) < DATE('".$endDT->format('Y-m-d') . "') and Date(Departure_Date) > DATE('".$startDT->format('Y-m-d') . "')");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } else if ($idPsg > 0) {

            $stmt = $dbh->query("select * from vresv_guest where idPsg = $idPsg and idReservation != $idResv and "
                . "Date(Arrival_Date) < DATE('".$endDT->format('Y-m-d') . "') and Date(Departure_Date) > DATE('".$startDT->format('Y-m-d') . "')");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } else if ($id > 0) {

            $stmt = $dbh->query("select * from vresv_guest where idGuest= $id and idReservation != $idResv and "
                . "Date(Arrival_Date) < DATE('".$endDT->format('Y-m-d') . "') and Date(Departure_Date) > DATE('".$startDT->format('Y-m-d') . "')");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $rows;
    }

    public static function getConfirmForm(\PDO $dbh, $idReservation, $idGuest, $amount, $sendEmail = FALSE, $notes = '', $emailAddr = '') {

        if ($idReservation == 0) {
            return array('error'=>'Bad reservation Id: ' . $idReservation);
        }

        require(HOUSE . 'TemplateForm.php');
        require(HOUSE . 'ConfirmationForm.php');

        $uS = Session::getInstance();
        $dataArray = array();

        $reserv = Reservation_1::instantiateFromIdReserv($dbh, $idReservation);

        if ($idGuest == 0) {
            $idGuest = $reserv->getIdGuest();
        }

        $guest = new Guest($dbh, '', $idGuest);

        $confirmForm = new ConfirmationForm('confirmation.txt');

        $formNotes = $confirmForm->createNotes($notes, !$sendEmail);

        $form = $confirmForm->createForm($confirmForm->makeReplacements($reserv, $guest, $amount, $formNotes));

        if ($emailAddr == '') {
            $emAddr = $guest->getEmailsObj()->get_data($guest->getEmailsObj()->get_preferredCode());
            $emailAddr = $emAddr["Email"];
        }

        if ($sendEmail) {

            if ($emailAddr != '') {

                $config = new Config_Lite(ciCFG_FILE);

                $mail = prepareEmail($config);
                $mail->From = $config->getString('guest_email', 'FromAddress', '');
                $mail->FromName = $uS->siteName;
                $mail->addAddress(filter_var($emailAddr, FILTER_SANITIZE_EMAIL));     // Add a recipient
                $mail->addReplyTo($config->getString('guest_email', 'ReplyTo', ''));

                $bccs = explode(',', $config->getString('guest_email', 'BccAddress', ''));
                foreach ($bccs as $bcc) {
                    if ($bcc != ''){
                        $mail->addBCC(filter_var($bcc, FILTER_SANITIZE_EMAIL));
                    }
                }

                $mail->isHTML(true);

                $mail->Subject = htmlspecialchars_decode($uS->siteName, ENT_QUOTES) . ' Reservation Confirmation';
                $mail->msgHTML($form);


                if($mail->send()) {

                    // Make a note in the reservation.
                    $reserv->setNotes('Confirmation Email sent to ' . $emailAddr . ' with the following as a Note: ' . str_replace('\n', ' ', $notes), $uS->username);
                    $reserv->saveReservation($dbh, $reserv->getIdRegistration(), $uS->username);

                    $dataArray['mesg'] = "Email sent.  ";

                } else {
                    $dataArray['mesg'] = "Email failed!  " . $mail->ErrorInfo;
                }

            } else {
                $dataArray['mesg'] = "Guest email address is blank.  ";
            }

        } else {

            $dataArray['confrv'] = utf8_decode($form);
            $dataArray['email'] = $emailAddr;
        }

        return $dataArray;
    }

    public static function generateCkinDoc(\PDO $dbh, $idReservation = 0, $idVisit = 0, $span = 0, $logoURL = '', $notes = '') {

        $uS = Session::getInstance();

        $instructFileName = REL_BASE_DIR . 'conf'. DS . 'agreement.txt';


        if ($uS->RegForm == 1) {

            $doc = RegisterForm::prepareRegForm($dbh, $idVisit, $span, $idReservation, $instructFileName);
            $sty = RegisterForm::getStyling();

        } else if ($uS->RegForm == 2) {

            // IMD and St. Mary's

            $roomTitle = '';
            $additionalGuests = array();
            $priGuest = new Guest($dbh, '', 0);
            $idHospitalStay = 0;
            $idRegistration = 0;
            $patientName = '';
            $hospitalName = '';
            $cardName = '';
            $cardNumber = '';
            $cardType = '';
            $expectedPayType = '';
            $todaysDate = '';


            if ($idVisit > 0) {

                // arrival date and time
                $stmtv = $dbh->prepare("Select idPrimaryGuest, idHospital_stay, idRegistration from visit where idVisit = :idv");
                $stmtv->execute(array(':idv'=>$idVisit));
                $rows = $stmtv->fetchAll(PDO::FETCH_NUM);

                if (count($rows) == 0) {
                    return array('doc'=>'Error - Visit not found.', 'style'=>' ');
                }

                $todaysDate = date('M j, Y');
                $idHospitalStay = $rows[0][1];
                $idRegistration = $rows[0][2];
                $idPG = $rows[0][0];
                $priGuest = new Guest($dbh, '', $idPG);

                // Get additional Guests
                $query = "select * from vadditional_guests where Status in ('" . VisitStatus::Active . "','" . VisitStatus::CheckedOut . "') and idVisit = :idv LIMIT 6";
                $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
                $stmt->bindValue(':idv', $idVisit, PDO::PARAM_INT);
                $stmt->execute();
                $stays = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $ids = array();

                // List each additional guest.
                foreach ($stays as $s) {

                    $roomTitle = $s['Title'];

                    if (isset($ids[$s['idName']])) {
                        // Dump dups.
                        continue;
                    }

                    $ids[$s['idName']] = 1;

                    if ($s['idName'] != $idPG) {

                        $guest = new Guest($dbh, '', $s['idName']);
                        $guest->setPatientRelationshipCode($s['Relationship_Code']);
                        $guest->setCheckinDate($s['Checkin_Date']);
                        $guest->setExpectedCheckOut($s['Expected_Co_Date']);
                        $additionalGuests[] = $guest;

                    } else {

                        $priGuest->setCheckinDate($s['Checkin_Date']);
                        $priGuest->setExpectedCheckOut($s['Expected_Co_Date']);

                    }
                }

            } else if ($idReservation > 0) {

                // Room title
                $resv = Reservation_1::instantiateFromIdReserv($dbh, $idReservation);

                $roomTitle = $resv->getRoomTitle($dbh);
                $idHospitalStay = $resv->getIdHospitalStay();
                $idRegistration = $resv->getIdRegistration();
                $notes = $resv->getCheckinNotes();

                if (isset($uS->nameLookups[GL_TableNames::PayType][$resv->getExpectedPayType()])) {
                    $expectedPayType = $uS->nameLookups[GL_TableNames::PayType][$resv->getExpectedPayType()][1];
                }

                // Guests
                $guestIds = self::getReservGuests($dbh, $idReservation);

                foreach ($guestIds as $id => $stat) {

                    if ($stat == '1') {

                        $priGuest = new Guest($dbh, '', $id);
                        $priGuest->setCheckinDate($resv->getExpectedArrival());

                    } else {

                        $guest = new Guest($dbh, '', $id);
                        $guest->setCheckinDate($resv->getExpectedArrival());
                        $additionalGuests[] = $guest;
                    }
                }

            } else {
                return array('doc'=>'Error - Parameters are not specified.', 'style'=>' ');
            }

            // Patient and Hospital
            if ($idHospitalStay > 0) {

                $stmt = $dbh->query("Select n.Name_Full, n.BirthDate, case when ha.Description = '' then ha.`Title` else ha.Description end as `Assoc`, case when hh.Description = '' then hh.`Title` else hh.Description end as `Hosp`
    from
        hospital_stay hs
            left join
        `name` n ON hs.idPatient = n.idName
            left join
        hospital ha ON hs.idAssociation = ha.idHospital
            left join
        hospital hh ON hs.idHospital = hh.idHospital
    where
        hs.idHospital_stay = $idHospitalStay");

                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rows as $r) {
                    $patientName = $r['Name_Full'] . ($r['BirthDate'] == '' ? '' : ' (' . date('M j, Y', strtotime($r['BirthDate'])) . ')');
                    $hospitalName = ($r['Assoc'] == '' || $r['Assoc'] == '(None)' ? $r['Hosp'] : $r['Assoc'] . ' / ' . $r['Hosp']);
                }

            }


            // Set the billing guest
            $billingGuest = new Guest($dbh, '', 0);

            // payment info
            if ($idRegistration > 0) {

                $t = new Guest_TokenRS();

                $t->idRegistration->setStoredVal($idRegistration);
                $rows = EditRS::select($dbh, $t, array($t->idRegistration));

                foreach ($rows as $r) {

                    $t = new Guest_TokenRS();
                    EditRS::loadRow($r, $t);

                    // Grab the primary guest, otherwise, use one of the others.
                    if ($t->idGuest->getStoredVal() == $priGuest->getIdName()) {

                        $billingGuest = $priGuest;
                        $cardName = $t->CardHolderName->getStoredVal();
                        $cardNumber = $t->MaskedAccount->getStoredVal();
                        $cardType = $t->CardType->getStoredVal();
                        break;

                    } else if ($t->idGuest->getStoredVal() != 0) {

                        $billingGuest = new Guest($dbh, '', $t->idGuest->getStoredVal());
                        $cardName = $t->CardHolderName->getStoredVal();
                        $cardNumber = $t->MaskedAccount->getStoredVal();
                        $cardType = $t->CardType->getStoredVal();
                    }
                }
            }

            // Show the room on the registration form?
            if ($uS->RegFormNoRm) {
                $roomTitle = '';
            }

            $regdoc = new RegistrationForm();
            $logoWidth = 114;

            $doc = $regdoc->getDocument($dbh,
                    $priGuest,
                    $billingGuest,
                    $additionalGuests,
                    $patientName,
                    $hospitalName,
                    $roomTitle,
                    $cardName,
                    $cardType,
                    $cardNumber,
                    $logoURL,
                    $logoWidth,
                    $instructFileName,
                    $expectedPayType,
                    $notes,
                    $todaysDate
                );
            $sty = $regdoc->getStyle();

        } else {
            return array('doc'=>'Error - Registration Form # is not defined in the system configuration table.', 'style'=>' ');
        }

        return array('doc'=>$doc, 'style'=>$sty);

    }

    public static function getReservGuests(\PDO $dbh, $idReservation) {

        $idResv = intval($idReservation, 10);
        $ids = array();

        $stmt = $dbh->query("Select * from reservation_guest where idReservation = $idResv");

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ids[$r['idGuest']] = $r['Primary_Guest'];
        }

        return $ids;

    }

    public static function moveResvAway(\PDO $dbh, DateTime $firstArrival, DateTime $lastDepart, $idResource, $uname) {

        // Move other reservations to alternative rooms?
        $rRows = Reservation_1::findReservations($dbh, $firstArrival->format('Y-m-d H:i:s'), $lastDepart->format('Y-m-d H:i:s'), $idResource);

        $reply = '';

        if (count($rRows) > 0) {

            // Move reservations to other rooms.
            foreach ($rRows as $r) {

                $resv = Reservation_1::instantiateFromIdReserv($dbh, $r[0]);
                if ($resv->getStatus() != ReservationStatus::Staying && $resv->getStatus() != ReservationStatus::Checkedout) {
                    $resv->move($dbh, 0, 0, $uname, TRUE);
                    $reply .= $resv->getResultMessage();
                }

            }
        }

        return $reply;

    }

    public static function moveReserv(\PDO $dbh, $idReservation, $startDelta, $endDelta) {

        $uS = Session::getInstance();
        $dataArray = array();

        if (SecurityComponent::is_Authorized('guestadmin') === FALSE) {
            return array("error" => "User not authorized to move reservations.");
        }

        if ($idReservation == 0) {
            return array("error" => "Reservation not specified.");
        }

        if ($startDelta == 0 && $endDelta == 0) {
            return array("error" => "Reservation not moved.");
        }

        if (abs($endDelta) > ($uS->CalViewWeeks * 7) || abs($startDelta) > ($uS->CalViewWeeks * 7)) {
            return array("error" => 'Move refused, change too large: Start Delta = ' . $startDelta . ', End Delta = ' . $endDelta);
        }

        // save the reservation info
        $reserv = Reservation_1::instantiateFromIdReserv($dbh, $idReservation);
        $worked = $reserv->move($dbh, $startDelta, $endDelta, $uS->username);
        $reply = $reserv->getResultMessage();

        if ($worked) {

            // Return checked in guests markup?
            if ($reserv->getStatus() == ReservationStatus::Committed) {
                $dataArray['reservs'] = 'y';
            } else if ($reserv->getStatus() == ReservationStatus::UnCommitted && $uS->ShowUncfrmdStatusTab) {
                $dataArray['unreserv'] = 'y';
            } else if ($reserv->getStatus() == ReservationStatus::Waitlist) {
                $dataArray['waitlist'] = 'y';
            }
        }

        $dataArray["success"] = $reply;
        return $dataArray;

    }

    public static function getIncomeDialog(\PDO $dbh, $rid, $rgId) {

        $dataArray = array();
        $cat = Default_Settings::Rate_Category;

        if ($rid > 0) {
            $resv = Reservation_1::instantiateFromIdReserv($dbh, $rid);
            $cat = ($resv->getRoomRateCategory() != '' ? $resv->getRoomRateCategory() : Default_Settings::Rate_Category);
            $rgId = $resv->getIdRegistration();
        }

        $finApp = new FinAssistance($dbh, $rgId, $cat);

        $dataArray['incomeDiag'] = HTMLContainer::generateMarkup(
                'form', HTMLContainer::generateMarkup('div', $finApp->createIncomeDialog()),
                array('id'=>'formf'));

        $dataArray['rstat'] = $finApp->isApproved();
        $dataArray['rcat'] = $finApp->getFaCategory();

        return $dataArray;
    }


    public static function saveFinApp(\PDO $dbh, $post) {

        $uS = Session::getInstance();

        $idReserv = 0;
        $idRegistration = 0;
        $reserv = NULL;

        if (isset($post['rgId'])) {
            $idRegistration = intval(filter_var($post['rgId'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if (isset($post['rid'])) {
            $idReserv = intval(filter_var($post['rid'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        if ($idReserv > 0) {
            $reserv = Reservation_1::instantiateFromIdReserv($dbh, $idReserv);
            $idRegistration = $reserv->getIdRegistration();
        }

        if ($idRegistration < 1) {
            return array('error'=>'The Registration is not defined.  ');
        }

        $finApp = new FinAssistance($dbh, $idRegistration);

        $faCategory = '';
        $faStat = '';
        $reason = '';
        $notes = '';
        $faStatDate = '';

        if (isset($post['txtFaIncome'])) {
            $income = filter_var(str_replace(',', '', $post['txtFaIncome']),FILTER_SANITIZE_NUMBER_INT);
            $finApp->setMontylyIncome($income);
        }

        if (isset($post['txtFaSize'])) {
            $size = intval(filter_var($post['txtFaSize'],FILTER_SANITIZE_NUMBER_INT), 10);
            $finApp->setHhSize($size);
        }

        // FA Category
        if (isset($post['hdnRateCat'])) {
            $faCategory = filter_var($post['hdnRateCat'], FILTER_SANITIZE_STRING);
        }

        if (isset($post['SelFaStatus']) && $post['SelFaStatus'] != '') {
            $faStat = filter_var($post['SelFaStatus'], FILTER_SANITIZE_STRING);
        }

        if (isset($post['txtFaStatusDate']) && $post['txtFaStatusDate'] != '') {
            $faDT = setTimeZone($uS, filter_var($post['txtFaStatusDate'], FILTER_SANITIZE_STRING));
            $faStatDate = $faDT->format('Y-m-d');
        }

        // Reason text
        if (isset($post['txtFaReason'])) {
            $reason = filter_var($post['txtFaReason'], FILTER_SANITIZE_STRING);
        }

        // Notes
        if (isset($post['txtFaNotes'])) {
            $notes = filter_var($post['txtFaNotes'], FILTER_SANITIZE_STRING);
        }

        // Save Fin App dialog.
        $finApp->saveDialogMarkup($dbh, $faStat, $faCategory, $reason, $faStatDate, $notes, $uS->username);


        if (is_null($reserv) === FALSE && isset($post['fadjAmount']) && isset($post['txtFaFixedRate'])) {

            $rateAdj = intval(filter_var($post['fadjAmount'], FILTER_SANITIZE_NUMBER_INT), 10);
            $assignedRate = filter_var($post['txtFaFixedRate'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);


            // update the reservation.
            if ($finApp->isApproved()) {

                $reserv->setRateAdjust($rateAdj);
                $reserv->setRoomRateCategory($finApp->getFaCategory());
                $reserv->setFixedRoomRate($assignedRate);

            } else {

                $reserv->setRateAdjust(0);
                $reserv->setRoomRateCategory(RoomRateCategorys::FlatRateCategory);
                $reserv->setFixedRoomRate(0);
            }

            $reserv->saveReservation($dbh, $reserv->getIdRegistration(), $uS->username);

        }

        $dataArray['rstat'] = $finApp->isApproved();
        $dataArray['rcat'] = $finApp->getFaCategory();

        return $dataArray;
    }

    public static function changeReservStatus(\PDO $dbh, $idReservation, $status) {

        if ($idReservation == 0 || $status == '') {
            return array('error'=>'Bad input parameters: Reservation Id = ' . $idReservation . ', new Status = ' . $status);
        }

        $uS = Session::getInstance();

        $resv = Reservation_1::instantiateFromIdReserv($dbh, $idReservation);
        $oldStatus = $resv->getStatus();

        if ($oldStatus == ReservationStatus::Waitlist && ($status == ReservationStatus::Committed || $status == ReservationStatus::UnCommitted)) {
            return array('error'=>'Cannot change from Waitlist to Confirmed or Unconfirmed.');
        }

        if ($status == ReservationStatus::Waitlist) {
            $resv->setIdResource(0);
        }

        $resv->setStatus($status);

        $resv->saveReservation($dbh, $resv->getIdRegistration(), $uS->username);

        $dataArray['success'] = 'Reservation status changed to ' . $uS->guestLookups['ReservStatus'][$status][1];


        if ($oldStatus == ReservationStatus::Committed || $status == ReservationStatus::Committed) {
            $dataArray['reservs'] = 'y';
        }

        if ($oldStatus == ReservationStatus::UnCommitted || $status == ReservationStatus::UnCommitted) {
            $dataArray['unreserv'] = 'y';
        }

        if ($oldStatus == ReservationStatus::Waitlist || $status == ReservationStatus::Waitlist) {
            $dataArray['waitlist'] = 'y';
        }

        return $dataArray;
    }

    public static function getRoomList(\PDO $dbh, Reservation_1 $resv, $eid, $isAuthorized, $numGuests = 0) {

        if ($numGuests <= 0) {
            $numGuests = $resv->getNumberGuests();
        }

        if ($isAuthorized) {
            $resv->findGradedResources($dbh, $resv->getExpectedArrival(), $resv->getExpectedDeparture(), $numGuests, array('room','rmtroom','part'), TRUE);
        } else {
            $resv->findResources($dbh, $resv->getExpectedArrival(), $resv->getExpectedDeparture(), $numGuests, array('room','rmtroom','part'), TRUE);
        }

        $resources = array();
        $errorMessage = '';

        // Load available resources
        foreach ($resv->getAvailableResources() as $r) {
            $resources[$r->getIdResource()] = array($r->getIdResource(), $r->getTitle(), $r->optGroup);
        }

        // add waitlist option to the top of the list
        $resources[0] = array(0, '-None-', '');


        // Selected resource
        $idResourceChosen = $resv->getIdResource();

        if ($resv->getStatus() == ReservationStatus::Waitlist) {

            $idResourceChosen = 0;

        } else if (($resv->getStatus() == ReservationStatus::Committed || $resv->getStatus() == ReservationStatus::UnCommitted) && isset($resources[$resv->getIdResource()])) {

            $myResc = $resources[$resv->getIdResource()];

            if (isset($myResc[2]) && $myResc[2] != '') {
                $errorMessage = $myResc[2];
            }

        } else {

            $untestedRescs = $resv->getUntestedResources();
            if (isset($untestedRescs[$resv->getIdResource()])) {
                $errorMessage = 'Room ' . $resv->getRoomTitle($dbh) . ' is not suitable.';
            } else {
                $errorMessage = 'Room ' . $resv->getRoomTitle($dbh) . ' may be too small.';
            }
        }

        // Adjust size of selector
        $selSize = count($resources);
        if ($selSize > 8) {
            $selSize = 8;
        } else if ($selSize < 4) {
            $selSize = 4;
        }

        $dataArray['ctrl'] = HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($resources, $idResourceChosen), array('name'=>'selResource'));
        $dataArray['container'] = HTMLContainer::generateMarkup('div',
            HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($resources, $idResourceChosen), array('id'=>'selRoom', 'size'=>$selSize))
                , array('id'=>'pudiv', 'class'=>"ui-widget ui-widget-content ui-helper-clearfix ui-corner-all", 'style'=>"font-size:0.9em;position: absolute; z-index: 1; display: block;"));  // top:".$y."px; left:".$xa."px;
        $dataArray['eid'] = $eid;
        $dataArray['msg'] = $errorMessage;
        $dataArray['rid'] = $resv->getIdReservation();

        return $dataArray;
    }

    public static function reviseConstraints(\PDO $dbh, $idResv, $idResc, $numGuests, $expArr, $expDep, $cbs, $isAuthorized = FALSE, $omitSelf = TRUE) {

        $resv = Reservation_1::instantiateFromIdReserv($dbh, $idResv);

        $resv->setIdResource($idResc);
        $resv->setExpectedArrival($expArr);
        $resv->setExpectedDeparture($expDep);

        $resv->saveConstraints($dbh, $cbs);

        $roomChooser = new RoomChooser($dbh, $resv, $numGuests, new DateTime($expArr), new DateTime($expDep));
        $roomChooser->findResources($dbh, $isAuthorized, $omitSelf, $numGuests);

        $resOptions = $roomChooser->makeRoomSelectorOptions();
        $errorMessage = $roomChooser->getRoomSelectionError($dbh, $resOptions);

        //$results = ReservationSvcs::getRoomList($dbh, $resv, '', $isAuthorized, $numGuests);
        return array('rooms'=>$roomChooser->makeRoomsArray(), 'selectr'=>$roomChooser->makeRoomSelector($resOptions, $idResc), 'idResource' => $idResc, 'msg'=>$errorMessage);

    }


    public static function deleteReservation(\PDO $dbh, $rid) {

        // Get labels
        $labels = new Config_Lite(LABEL_FILE);
        $uS = Session::getInstance();

        if ($rid > 0) {

            $resv = Reservation_1::instantiateFromIdReserv($dbh, $rid);

            if ($resv->getStatus() == ReservationStatus::Staying || $resv->getStatus() == ReservationStatus::Checkedout) {

                $dataArray['warning'] = $labels->getString('guestEdit', 'reservationTitle', 'Reservation') . ' status "' . $resv->getStatusTitle() . '" cannot be deleted';

            } else {
                // Okay to delete
                $resv->deleteMe($dbh, $uS->username);

                $dataArray['result'] = $labels->getString('guestEdit', 'reservationTitle', 'Reservation') . ' Deleted.';
            }

        } else {
            $dataArray['warning'] = $labels->getString('guestEdit', 'reservationTitle', 'Reservation') . ' Id is not valid.  ';
        }

        return $dataArray;
    }
}
