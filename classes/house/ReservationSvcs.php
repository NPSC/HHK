<?php
/**
 * ReservationSvcs.php
 *
 *
 * @category  House
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
 *   */

/**
 * Description of ReservationSvcs
 *
 * @author Eric
 */

class ReservationSvcs {

    public static function getResv(\PDO $dbh, $idReserv, $id, $role, $chosenIdPsg, $idPrefix, $isAuthorized = FALSE, $patientStaying = FALSE) {

        $uS = Session::getInstance();
        $static = FALSE;
        $dataArray = array();

        $numGuests = 1;

        // Get labels
        $labels = new Config_Lite(LABEL_FILE);


        // Check for reserv id = 0, new resv or pick up old?
        if ($idReserv == 0 && $id > 0 && $chosenIdPsg > 0) {

            $dataArray = self::reservationChooser($dbh, $id, $chosenIdPsg, $uS->guestLookups['ReservStatus'], $labels, $uS->ResvEarlyArrDays);

            if (count($dataArray) > 0) {
                return $dataArray;
            }
        }

        // Flag to force a new reservation.
        if ($idReserv == -1) {
            $idReserv = 0;
        }

        $resv = Reservation_1::instantiateFromIdReserv($dbh, $idReserv);

        // handle Immediate reservations - generted by checkin
        if ($resv->getStatus() == ReservationStatus::Imediate) {
            return array('error'=>'Use the <a href="CheckIn.php?rid=' . $resv->getIdReservation() . '">Check In page</a> for this guest.');
        }

        // Flag an old reservation
        if ($resv->getStatus() == ReservationStatus::Checkedout || $resv->getStatus() == ReservationStatus::Staying) {
            $dataArray['static'] = 'y';
            $static = TRUE;
        }

        if ($resv->isRemoved()) {
            // Turn off the Save Button...
            $dataArray['static'] = 'y';
        }


        // patient?
        if ($role == 'p') {

            if ($id > 0) {

                // patient member defined.
                $patient = new Patient($dbh, 'h_', $id);
                $patientPsg = $patient->getPatientPsg();

                if ($patientPsg->getIdPsg() > 0) {

                    if ($resv->isNew() === FALSE) {

                        // Verify PSG
                        if ($resv->getIdPsg($dbh) != $patientPsg->getIdPsg()) {
                            // PSG mis-match.
                            return array('error'=>'Patient does not belong to this guest\'s Patient Support Group');
                        }
                    }

                    $hospitalStay = new HospitalStay($dbh, $patient->getIdName());
                    $dataArray['hosp'] = Hospital::createReferralMarkup($dbh, $hospitalStay);
                    $dataArray['idPsg'] = $patientPsg->getIdPsg();
                }

            } else if ($chosenIdPsg > 0) {

                $psg = new Psg($dbh, $chosenIdPsg);
                $patient = new Patient($dbh, 'h_', $psg->getIdPatient());

            } else {
                $patient = new Patient($dbh, 'h_', $id);
            }

            if ($resv->isNew() === FALSE) {

                $guests = self::getReservGuests($dbh, $resv->getIdReservation());
                $dataArray['numGuests'] = count($guests);

            } else if ($patientStaying) {

                $dataArray['numGuests'] = 2;
            }

            $dataArray['patStay'] = $patientStaying;
            $dataArray['rvstCode'] = $resv->getStatus();
            $dataArray['patient'] = $patient->createReservationMarkup();
            return $dataArray;

        }


        $idPsg = $resv->getIdPsg($dbh);

        if ($idPsg == 0) {
            // New Reservation...
            if ($chosenIdPsg == 0) {

                $ngRss = Psg::getNameGuests($dbh, $id);

                if (count($ngRss) > 0) {
                    // Select psg
                    $mkup = ReservationSvcs::psgChooserMkup($dbh, $ngRss, $uS->PatientAsGuest);
                    return array('choosePsg'=>$mkup, 'idGuest'=>$id);
                }

           } else if ($chosenIdPsg == -1) {
                // Flag to force a new psg
                $idPsg = 0;

            } else {
                // New reservation, PSG already chosen.
                $idPsg = $chosenIdPsg;
            }
        }


        $psg = new Psg($dbh, $idPsg);

        // Sanity check for PSG
        if ($resv->isNew() === FALSE && $resv->getIdPsg($dbh) != $psg->getIdPsg()) {
            throw new Hk_Exception_Runtime("PSG mis-match!  ");
        }

        $dataArray['idPsg'] = $psg->getIdPsg();

        // additional guests list
        if ($resv->isNew() === FALSE) {

            $guests = self::getReservGuests($dbh, $resv->getIdReservation());

            // Reservation-Guests list
            $dataArray['adguests'] = HTMLContainer::generateMarkup('fieldset',
                ReservationSvcs::moreGuestsTable($dbh, $resv, $guests, $psg, $static), array('class'=>'hhk-panel'));

            // Check for patient in additional guests list
            if (isset($guests[$psg->getIdPatient()]) || $resv->getIdGuest() == $psg->getIdPatient()) {
                $patientStaying = TRUE;
            } else {
                $patientStaying = FALSE;
            }

            $dataArray['patStay'] = $patientStaying;
            $numGuests = count($guests);

        }

        // Check for patient not staying
        if ($role == 'g' && $id > 0 && $id == $psg->getIdPatient() && ($patientStaying === FALSE || $uS->PatientAsGuest === FALSE)) {

            $patient = new Patient($dbh, 'h_', $id);
            $hospitalStay = new HospitalStay($dbh, $patient->getIdName());
            $dataArray['hosp'] = Hospital::createReferralMarkup($dbh, $hospitalStay);
            $dataArray['rvstCode'] = $resv->getStatus();
            $dataArray['patient'] = $patient->createReservationMarkup();

            return $dataArray;
        }

        $guest = new Guest($dbh, $idPrefix, $id);

        // No return?
        if ($guest->getNoReturn() != '' && $static === FALSE) {
            $dataArray['warning'] = 'Guest "' .$guest->getNameObj()->get_FullName() . '" is flagged for No Return.  Reason: ' . $guest->getNoReturn();
        }

        $guest->setPatientRelationshipCode($psg->getGuestRelationship($guest->getIdName()));


        // Registration
        $reg = new Registration($dbh, $psg->getIdPsg());



        // Pick up the old ReservationRS, if it exists.
        // Use it to fill in the visit requirements.
        $oldResvId = 0;

        if ($resv->isNew()) {

            // Look for a previous reservation to copy from ...
            if ($guest->getIdName() > 0) {
                $stmt = $dbh->query("select r.idReservation, max(r.Expected_Arrival) from reservation r  where r.idGuest = " . $guest->getIdName() . " order by r.idGuest");
                $rows = $stmt->fetchAll(PDO::FETCH_NUM);

                if (count($rows > 0)) {
                    $oldResvId = $rows[0][0];
                }
            }

            $resv->setExpectedPayType($uS->DefaultPayType);

            if ($psg->getIdPatient() != $guest->getIdName() && $patientStaying) {
                $numGuests++;
            }


        } else {

            if ($resv->getExpectedDays() < 1) {
                $dataArray['warn'] = 'Reservation dates are the same or backwards.';
            }
        }


        if ($static) {
            $guest->setExpectedCheckinDate($resv->getActualArrival() == '' ? date('M j, Y', strtotime($resv->getExpectedArrival())) : date('M j, Y', strtotime($resv->getActualArrival())));
            $guest->setExpectedCheckOut($resv->getActualDeparture() == '' ? date('M j, Y', strtotime($resv->getExpectedDeparture())) : date('M j, Y', strtotime($resv->getActualDeparture())));
        } else {
            $guest->setExpectedCheckinDate($resv->getExpectedArrival() == '' ? '' : date('M j, Y', strtotime($resv->getExpectedArrival())));
            $guest->setExpectedCheckOut($resv->getExpectedDeparture() == '' ? '' : date('M j, Y', strtotime($resv->getExpectedDeparture())));
        }



        $dataArray['rvstatus'] = $resv->getStatusTitle($resv->getStatus());
        $dataArray['rvstCode'] = $resv->getStatus();

        if ($resv->getStatus() == ReservationStatus::Committed) {
            $dataArray['showRegBtn'] = 'y';
        } else {
            $dataArray['showRegBtn'] = 'n';
        }

        // Hospital
        $idPatient = $psg->getIdPatient();
        $hospitalStay = new HospitalStay($dbh, $idPatient);

        $dataArray['hosp'] = Hospital::createReferralMarkup($dbh, $hospitalStay);

        // Patient markup
        if ($idPatient > 0 && $idPatient != $guest->getIdName()) {

            // Patient is someone other than the guest
            $patient = new Patient($dbh, 'h_', $idPatient);
            $dataArray['patient'] = $patient->createReservationMarkup();
            $dataArray['patStay'] = $patientStaying;
        }

        $dataArray = array_merge($dataArray, $guest->createReservationMarkup($patientStaying));
        $dataArray['notes'] = $guest->createNotesMU($resv->getNotes(), 'txtRnotes', $labels);


        // Look for previously chosen room.
        if ($resv->isActive() && $static === FALSE) {

            $dataArray['idReserv'] = $resv->getIdReservation();

            // Vehicles
            if ($uS->TrackAuto) {
                // CLose box on default
                $noVeh = $reg->getNoVehicle();

                if ($reg->isNew()) {
                    $noVeh = '1';
                }

                $dataArray['vehicle'] = Vehicle::createVehicleMarkup($dbh, $reg->getIdRegistration(), $noVeh);
            }

            // Room Chooser
            $roomChooser = new RoomChooser($dbh, $resv, $resv->getNumberGuests(), new DateTime($guest->getExpectedCheckinDate()), $guest->getExpectedCheckOutDT());
            $roomChooser->setOldResvId($oldResvId);
            $dataArray['resc'] = $roomChooser->CreateResvMarkup($dbh, $isAuthorized);

            $rateChooser = new RateChooser($dbh);
            $showPayWith = TRUE;

            // Rate Chooser
            if ($uS->RoomPriceModel != ItemPriceCode::None) {

                $dataArray['rate'] = $rateChooser->createResvMarkup($dbh, $resv, $resv->getExpectedDays(), $labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee'));
                // Array with amount calculated for each rate.
                $dataArray['ratelist'] = $rateChooser->makeRateArray($dbh, $resv->getExpectedDays(), $resv->getIdRegistration(), $resv->getFixedRoomRate());
                // Array with key deposit info
                $dataArray['rooms'] = $rateChooser->makeRoomsArray($roomChooser, $uS->guestLookups['Static_Room_Rate'], $uS->guestLookups[GL_TableNames::KeyDepositCode]);

                if ($uS->VisitFee) {
                    // Visit Fee Array
                    $dataArray['vfee'] = $rateChooser::makeVisitFeeArray($dbh);
                }

                $dataArray['pay'] =
                        PaymentChooser::createResvMarkup($dbh, $guest->getIdName(), $reg, removeOptionGroups($uS->nameLookups[GL_TableNames::PayType]), $resv->getExpectedPayType(), $uS->ccgw);

            } else {
                // Price Model - NONE
                $showPayWith = FALSE;
            }


            // Registration Data
            $dataArray['resv'] = self::createStatusChooser($resv, $resv->getChooserStatuses($uS->guestLookups['ReservStatus']), $uS->nameLookups[GL_TableNames::PayType], $labels, $showPayWith, Registration::loadLodgingBalance($dbh, $resv->getIdRegistration()));

        } else {
            // Constraints panel.
            $roomChooser = new RoomChooser($dbh, $resv, 1);
            $roomChooser->setOldResvId($oldResvId);
            $dataArray['resc'] = $roomChooser->createConstraintsChooser($dbh, $resv->getIdReservation(), $numGuests, FALSE, $resv->getRoomTitle($dbh));

        }

        return $dataArray;
    }

    public static function getCurrentReservations(\PDO $dbh, $idResv, $id, $idPsg, \DateTime $startDT, \DateTime $endDT) {

        $rows = array();

        if ($idPsg > 0 && $id > 0) {
            // look for both
            $stmt = $dbh->query("select g.idReservation, g.idPsg, r.idGuest from vresv_guest g join reservation_guest r on g.idReservation = r.idReservation "
                    . "where (g.idPsg = $idPsg or r.idGuest = $id) and g.idReservation != $idResv and "
                . "Date(g.Expected_Arrival) < DATE('".$endDT->format('Y-m-d') . "') and Date(g.Expected_Departure) > DATE('".$startDT->format('Y-m-d') . "')");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } else if ($idPsg > 0) {

            $stmt = $dbh->query("select * from vresv_guest where idPsg = $idPsg and idReservation != $idResv and "
                . "Date(Expected_Arrival) < DATE('".$endDT->format('Y-m-d') . "') and Date(Expected_Departure) > DATE('".$startDT->format('Y-m-d') . "')");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        } else if ($id > 0) {

            $stmt = $dbh->query("select * from vresv_guest where idGuest= $id and idReservation != $idResv and "
                . "Date(Expected_Arrival) < DATE('".$endDT->format('Y-m-d') . "') and Date(Expected_Departure) > DATE('".$startDT->format('Y-m-d') . "')");

            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $rows;
    }

    public static function reservationChooser(\PDO $dbh, $id, $idPsg, $reservStatuses, \Config_Lite $labels, $offerCheckinDays = 0) {

        //
        if ($idPsg > 0) {

            $idPatient = 0;

                $stmt = $dbh->query("select * from vresv_patient "
                    . "where Status in ('".ReservationStatus::Staying."','".ReservationStatus::Committed."','".ReservationStatus::Imediate."','".ReservationStatus::UnCommitted."','".ReservationStatus::Waitlist."') "
                    . "and idPsg=$idPsg order by `Expected_Arrival`");


            $trs = array();
            $today = new DateTime();
            $today->setTime(0, 0, 0);

            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

                if ($r['idPatient'] > 0) {
                    $idPatient = $r['idPatient'];
                }

                $resvRs = new ReservationRS();
                EditRS::loadRow($r, $resvRs);

                $checkinNow = HTMLContainer::generateMarkup('a',
                            HTMLInput::generateMarkup('Open ' . $labels->getString('guestEdit', 'reservationTitle', 'Reservation'), array('type'=>'button'))
                            , array('style'=>'text-decoration:none;', 'href'=>'Referral.php?rid='.$resvRs->idReservation->getStoredVal()));

                $expArrDT = new DateTime($resvRs->Expected_Arrival->getStoredVal());
                $expArrDT->setTime(0, 0, 0);

                if ($resvRs->Status->getStoredVal() == ReservationStatus::Staying) {
                    $checkinNow = HTMLInput::generateMarkup('Add Guest', array('type'=>'button', 'style'=>'margin-left:.4em;', 'class'=>'hhk-checkinNow', 'data-rid'=>$resvRs->idReservation->getStoredVal()));
                } else if ($expArrDT->diff($today, TRUE)->days == 0) {
                    $checkinNow .= HTMLInput::generateMarkup('Check-in Now', array('type'=>'button', 'style'=>'margin-left:.4em;', 'class'=>'hhk-checkinNow', 'data-rid'=>$resvRs->idReservation->getStoredVal()));
                } else if ($expArrDT->diff($today, TRUE)->days <= $offerCheckinDays) {
                    $checkinNow .= HTMLInput::generateMarkup('Check-in Early', array('type'=>'button', 'style'=>'margin-left:.4em;', 'class'=>'hhk-checkinNow', 'data-rid'=>$resvRs->idReservation->getStoredVal()));
                }


                $trs[] = HTMLTable::makeTd($checkinNow)
                        .HTMLTable::makeTd($reservStatuses[$resvRs->Status->getStoredVal()][1])
                        .HTMLTable::makeTd($r['Title'])
                        .HTMLTable::makeTd($r['Patient_Name'])
                        .HTMLTable::makeTd($expArrDT->format('M j, Y'))
                        .HTMLTable::makeTd(date('M j, Y', strtotime($resvRs->Expected_Departure->getStoredVal())))
                        .HTMLTable::makeTd($resvRs->Number_Guests->getStoredVal());
            }


            if (count($trs) > 0) {

                // Caught some
                $tbl = new HTMLTable();
                foreach ($trs as $tr) {
                    $tbl->addBodyTr($tr);
                }

                $tbl->addHeaderTr(HTMLTable::makeTh('').HTMLTable::makeTh('Status').HTMLTable::makeTh('Room').HTMLTable::makeTh('Patient').HTMLTable::makeTh('Expected Arrival').HTMLTable::makeTh('Expected Departure')
                        .HTMLTable::makeTh('# Guests'));

                $mrkup = '';

                if ($id > 0) {
                    $name = new GuestMember($dbh, MemBasis::Indivual, $id);
                    $mrkup =  HTMLContainer::generateMarkup('p', 'Guest: ' . $name->get_fullName());
                }

                $mrkup .= $tbl->generateMarkup();

                return array('resCh'=>$mrkup, 'idPsg'=>$idPsg, 'id'=>$id, 'title'=>$labels->getString('referral', 'reservationChooserTitle', 'Reservation Chooser'), 'newButtonLabel'=>$labels->getString('referral', 'newButtonLabel', 'New Reservation'));
            }
        }
        return array();
    }

    protected static function visitChooser(\PDO $dbh, Guest $guest, $idPsg, $expectedArrivalDate, \Config_Lite $labels) {

        if ($idPsg < 1 || $expectedArrivalDate == '') {
            return array();
        }

        $trs = array();

        $expArrDT = new DateTime($expectedArrivalDate);

        // Check for existing visits
        $stmt = $dbh->query("select * "
                . "from vvisit_patient where idPsg = $idPsg and DATE(Expected_Departure) > DATE('" . $expArrDT->format('Y-m-d') . "') order by Expected_Departure");

        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

            if ($guest->getIdName() == $r['idPatient']) {
                $pname = 'Myself';
            } else {
                $pname = $r['Patient_Name'];
            }

            $trs[] = HTMLTable::makeTd(HTMLInput::generateMarkup('Add Guest', array('type'=>'button', 'style'=>'margin-left:.4em;', 'class'=>'hhk-checkinNow', 'data-rid'=>$r['idReservation'])))
                    .HTMLTable::makeTd($r['Room'])
                    .HTMLTable::makeTd(date('M j, Y', strtotime($r['Arrival_Date'])))
                    .HTMLTable::makeTd(date('M j, Y', strtotime($r['Expected_Departure'])))
                    .HTMLTable::makeTd($pname)
                    .HTMLTable::makeTd($r['NumberGuests']);

        }

        if (count($trs) > 0) {

            // Caught some
            $tbl = new HTMLTable();
            foreach ($trs as $tr) {
                $tbl->addBodyTr($tr);
            }

            $tbl->addHeaderTr(HTMLTable::makeTh('').HTMLTable::makeTh('Room').HTMLTable::makeTh('Arrival').HTMLTable::makeTh('Expected Departure').HTMLTable::makeTh('Patient').HTMLTable::makeTh('# Guests'));

            $name = $guest->getNameObj();

            $mrkup =  HTMLContainer::generateMarkup('p', 'Guest: ' . $name->get_fullName()) . $tbl->generateMarkup();

            return array('resCh'=>$mrkup, 'idPsg'=>$idPsg, 'id'=>$guest->getIdName(), 'title'=>'Existing Visits', 'newButtonLabel'=>$labels->getString('referral', 'newButtonLabel', 'New Reservation'));
        }

        return array();
    }

    /**
     *
     * @param \PDO $dbh
     * @param type $idReserv
     * @param type $id
     * @param array $post
     * @return type
     */
    public static function addResv(\PDO $dbh, $idReserv, $id, $addRoom, array $post = array()) {

        $uS = Session::getInstance();

        // Get labels
        $labels = new Config_Lite(LABEL_FILE);

        $resv = Reservation_1::instantiateFromIdReserv($dbh, $idReserv);

        // Can't use an old reservation
        if ($resv->isNew() === FALSE && $resv->isActive() === FALSE) {
            return array('error'=>'Cannot use an old ' . $labels->getString('guestEdit', 'reservationTitle', 'reservation'));
        }

        $idPsg = $resv->getIdPsg($dbh);

        if ($idPsg == 0) {
            return array('error'=>'Undefined patient support group.  ');
        }

        $psg = new Psg($dbh, $idPsg);
        $guest = new Guest($dbh, 'b', $id);

        // Check guest id
        if ($id == 0) {

            if (isset($post['btxtLastName'])) {
                // save the guest
                $guest->save($dbh, $post, $uS->username);
                $id = $guest->getIdName();

            } else {
                // send back a guest dialog to collect name, address, etc.
                $dataArray['addtguest'] = HTMLContainer::generateMarkup('div', $guest->createAddToResvMarkup()
                        , array('id'=>'diagAddGuest', 'class'=>'hhk-tdbox hhk-visitdialog'));
                return $dataArray;
            }
        }

        if ($guest->getNoReturn() != '') {

            return array('error'=>'Guest "' .$guest->getNameObj()->get_FullName() . '" is flagged for No Return.  Reason: ' . $guest->getNoReturn());
        }

        // Set as member of PSG if needed.
        if (isset($psg->psgMembers[$id]) === FALSE) {
            // add member to this psg
            $rel = RelLinkType::Friend;

            if(isset($post['bselPatRel'])) {
                $rel = filter_var($post['bselPatRel'], FILTER_SANITIZE_STRING);
                if ($rel == '') {
                    $rel = RelLinkType::Friend;
                }
            }

            $psg->setNewMember($id, 0, $rel);
            $psg->savePSG($dbh, $psg->getIdPatient(), $uS->username);

        } else if (!$uS->PatentAsGuest && $psg->getGuestRelationship($id) == RelLinkType::Self) {
            return array('error'=>'Patients are not allowed to be guests at our House.  ');
        }

        $arrivalDT = new DateTIme($resv->getExpectedArrival());
        $departureDT = new DateTime($resv->getExpectedDeparture());

        if ($resv->getActualArrival() != '') {
            $arrivalDT = new DateTIme($resv->getActualArrival());
        }

        if ($resv->getActualDeparture() != '') {
            $departureDT = new DateTime($resv->getActualDeparture());
        }

        // Check for existing reservations within the time period.
        $resvs = self::getCurrentReservations($dbh, $resv->getIdReservation(), $guest->getIdName(), $psg->getIdPsg(), $arrivalDT, $departureDT);

        foreach ($resvs as $rv) {

            // guest have a resv with another psg?
            if ($guest->getIdName() == $rv['idGuest'] && $rv['idPsg'] != $psg->getIdPsg()) {

                $dataArray['error'] = 'This guest has a concurrent <a href="Referral.php?rid=' . $rv['idReservation'] . '">'. $labels->getString('guestEdit', 'reservationTitle', 'reservation') . '</a> with a different patient.  ';
                return $dataArray;
            }

            if ($rv['idPsg'] == $psg->getIdPsg() && $guest->getIdName() == $rv['idGuest']) {

                $dataArray['error'] = 'This guest already has a <a href="Referral.php?rid=' . $rv['idReservation'] . '">'. $labels->getString('guestEdit', 'reservationTitle', 'reservation') . '</a>.  ';
                return $dataArray;

            }
        }


        // Add new room to the reservation?
        if ($addRoom) {

            $newResv = Reservation_1::instantiateFromIdReserv($dbh, 0);
            $newResv->setAddRoom(1);

            $newResv->setIdHospitalStay($resv->getIdHospitalStay());
            $newResv->setNumberGuests(1);
            $newResv->setIdGuest($id);

            $newResv->setIdRoomRate($resv->getIdRoomRate());
            $newResv->setRoomRateCategory($resv->getRoomRateCategory());
            $newResv->setVisitFee($resv->getVisitFee());

            $newResv->setStatus(ReservationStatus::Waitlist);
            $newResv->setExpectedArrival($resv->getExpectedArrival());
            $newResv->setExpectedDeparture($resv->getExpectedDeparture());

            $newResv->saveReservation($dbh, $resv->getIdRegistration(), $uS->username);
            ReservationSvcs::saveReservationGuest($dbh, $newResv->getIdReservation(), $id, TRUE);

            return array('newRoom'=>$newResv->getIdReservation());

        }

        $roomChooser = new RoomChooser($dbh, $resv, ($resv->getNumberGuests() + 1), new DateTime($resv->getExpectedArrival()), new DateTime($resv->getExpectedDeparture()));
        $rescs = $roomChooser->findResources($dbh, ComponentAuthClass::is_Authorized("guestadmin"));

        if ($resv->getIdResource() > 0 && isset($rescs[$resv->getIdResource()]) === FALSE) {
            // Too many occupants
            return array('error'=>'Too many occupants for room ' . $resv->getRoomTitle($dbh) . '.  ');
        }

        ReservationSvcs::saveReservationGuest($dbh, $resv->getIdReservation(), $id, FALSE);


        $guests = self::getReservGuests($dbh, $resv->getIdReservation());
        $dataArray['numGuests'] = count($guests);

        // Update reservation record
        $resv->setNumberGuests(count($guests));
        $resv->saveReservation($dbh, $resv->getIdRegistration(), $uS->username);

        if ($id == $psg->getIdPatient()) {
            // We just added the patient to the room
            $patient = new Patient($dbh, 'h_', $id);
            $dataArray['patient'] = $patient->createReservationMarkup();
            $dataArray['patStay'] = TRUE;
        }

        $dataArray['adguests'] = HTMLContainer::generateMarkup('fieldset',
                ReservationSvcs::moreGuestsTable($dbh, $resv, $guests, $psg, FALSE), array('class'=>'hhk-panel'));


        return $dataArray;

    }

    public static function findARoom(\PDO $dbh, $post, $isAuthorized = FALSE) {

        $uS = Session::getInstance();
        $patient = NULL;
        $patientStaying = FALSE;
        $creditCheckOut = array();

        // Get labels
        $labels = new Config_Lite(LABEL_FILE);

        // Patient staying flag
        if (isset($post['patStay'])) {
            $patientStaying = filter_var($post['patStay'], FILTER_VALIDATE_BOOLEAN);
        }

        // Primary guest Id
        $id = 0;
        if (isset($post['pgidName'])) {
            $id = intval(filter_var($post['pgidName'], FILTER_SANITIZE_NUMBER_INT), 10);
        }


        // Reservation Id
        $idReserv = 0;
        if (isset($post['rid'])) {
            $idReserv = intval(filter_var($post['rid'], FILTER_SANITIZE_NUMBER_INT), 10);
        }

        // PSG id.
        $idPsg = 0;
        if (isset($post['idPsg'])) {
            $idPsg = intval(filter_var($post['idPsg'], FILTER_SANITIZE_NUMBER_INT), 10);

            if ($idPsg < 0) {
                $idPsg = 0;
            }
        }

        // Check for reserv id = 0, new resv or pick up old?
        if ($idReserv == 0 && $id > 0 && $idPsg > 0) {

            $dataArray = self::reservationChooser($dbh, $id, $idPsg, $uS->guestLookups['ReservStatus'], $labels, $uS->ResvEarlyArrDays);

            if (count($dataArray) > 0) {
                return $dataArray;
            }
        }

        if ($idReserv < 0) {
            $idReserv = 0;
        }

        // Create/Get reservation
        $resv = Reservation_1::instantiateFromIdReserv($dbh, $idReserv);
        $psg = new Psg($dbh, ($idPsg == 0 ? $resv->getIdPsg($dbh) : $idPsg));

        // Reroute an old reservation
        if ($resv->getStatus() == ReservationStatus::Checkedout || $resv->getStatus() == ReservationStatus::Staying) {
            return self::getResv($dbh, $idReserv, $id, 'g', 0, 'pg', $isAuthorized);
        }

        // handle Immediate reservations - generted by checkin
        if ($resv->getStatus() == ReservationStatus::Imediate) {
            return array('error'=>'Use the <a href="Checkin.php?rid=' . $resv->getIdReservation() . '">Check In page</a> for this guest.');
        }

        // get Guests
        $allGuests = array();
        if ($resv->isNew() === FALSE) {
            $allGuests = self::getReservGuests($dbh, $resv->getIdReservation());
            if (isset($allGuests[$psg->getIdPatient()])) {
                $patientStaying = TRUE;
            }
        }


        $guest = new Guest($dbh, 'pg', $id);

        $name = $guest->getNameObj();
        if ($name->get_status() == MemStatus::Deceased) {
            return array('error'=>'This Guest is marked as "Deceased".');
        }

        $guest->save($dbh, $post, $uS->username);

        // primary guest markup
        $dataArray = $guest->createReservationMarkup($patientStaying);


        // Existing visit?
        if ($idReserv == 0 && $idPsg > 0) {

            $events = self::visitChooser($dbh, $guest, $idPsg, $guest->getExpectedCheckinDate(), $labels);

            if (count($events) > 0) {
                return array_merge($dataArray, $events);
            }
        }

        // No return?
        if ($guest->getNoReturn() != '') {
            $dataArray['warning'] = 'Guest "' .$guest->getNameObj()->get_FullName() . '" is flagged for No Return.  Reason: ' . $guest->getNoReturn();
        }




        // Guest Patient relationship (saved from the paage.
        $newRel = $guest->getPatientRelationshipCode();



        // Find the patient
        if ($newRel == RelLinkType::Self) {
            // Patient is the Primary Guest
            $patientStaying = TRUE;

            if ($psg->getIdPsg() > 0) {

                // PSG exist.
                if (isset($psg->psgMembers[$guest->getIdName()]) === FALSE) {
                    // Guest-Patient is not part of this psg
                    throw new Hk_Exception_Runtime("Reservation already has a PSG.  Make a new reservation for this patient-guest.");

                } else if ($psg->getGuestRelationship($guest->getIdName()) != RelLinkType::Self) {
                    // Guest-Patient is not a patient of the psg

                    // Switch patient with this guest.
                    $psg->setNewMember($psg->getIdPatient(), 0, RelLinkType::Friend);
                    $psg->setNewMember($guest->getIdName(), 0, RelLinkType::Self);

                    $psg->savePSG($dbh, $guest->getIdName(), $uS->username);

                }

            } else {

                // unknown PSG, Check for guest's existing PSG
                $ngRss = Psg::getNameGuests($dbh, $guest->getIdName());
                $idPsg = 0;

                foreach ($ngRss as $ngRs) {

                    if ($ngRs->Relationship_Code->getStoredVal() == RelLinkType::Self) {
                        // Use it
                        $idPsg = $ngRs->idPsg->getStoredVal();
                        break;
                    }
                }

                $psg = new Psg($dbh, $idPsg);
                $psg->setNewMember($guest->getIdName(), 0, RelLinkType::Self);
                $psg->savePSG($dbh, $guest->getIdName(), $uS->username);
            }

        } else if (isset($post['h_idName'])) {
            // Stand-alone patient.

            $patient = new Patient($dbh, 'h_', intval(filter_var($post['h_idName'], FILTER_SANITIZE_NUMBER_INT), 10));

            $patient->save($dbh, $post, $uS->username);

            // Could already have the psg defined here.....

            $ngRss = Psg::getNameGuests($dbh, $patient->getIdName());
            $idPsg = 0;

            foreach ($ngRss as $ngRs) {
                if ($ngRs->Relationship_Code->getStoredVal() == RelLinkType::Self) {
                    // Use it
                    $idPsg = $ngRs->idPsg->getStoredVal();
                    break;
                }

            }

            $psg = new Psg($dbh, $idPsg);
            $psg->setNewMember($guest->getIdName(), 0, $newRel);
            $psg->setNewMember($patient->getIdName(), 0, RelLinkType::Self);
            $psg->savePSG($dbh, $patient->getIdName(), $uS->username);

            $dataArray['patient'] = $patient->createReservationMarkup();

        } else {
            // uh-oh.  no patient defined.
            $dataArray['warning'] = 'A Patient must be defined.  ';
            return $dataArray;
        }


        // Room number chosen
        $idRescPosted = 0;
        if (isset($post['selResource'])) {
            $idRescPosted = intval(filter_Var($post['selResource'], FILTER_SANITIZE_NUMBER_INT), 10);
        }


        // Reservation Status
        $reservStatus = ReservationStatus::Pending;
        if (isset($post['selResvStatus'])) {
            $reservStatus = filter_var($post['selResvStatus'], FILTER_SANITIZE_STRING);
        } else if ($resv->getIdReservation() > 0) {
            $reservStatus = $resv->getStatus();
        }

        // Set dates
        $arrivalDT = new DateTime($resv->getExpectedArrival());
        $departureDT = new DateTime($resv->getExpectedDeparture());

        // Verify Primary guest
        if ($resv->isNew()) {

            $resv->setIdGuest($guest->getIdName());
            $resv->setExpectedArrival($guest->getExpectedCheckinDate());
            $resv->setExpectedDeparture($guest->getExpectedCheckOut());
            $resv->setExpectedPayType($uS->DefaultPayType);

            $arrivalDT = new DateTime($guest->getExpectedCheckinDate());
            $departureDT = new DateTime($guest->getExpectedCheckOut());

        } else if ($resv->getIdGuest() != $guest->getIdName()) {
            throw new Hk_Exception_Runtime("Primary Guest Id Mis-match for this reservation! ");
        }


        if ($resv->getActualArrival() != '') {
            $arrivalDT = new DateTIme($resv->getActualArrival());
        }

        // Check for existing reservations within the time period.
        $resvs = self::getCurrentReservations($dbh, $resv->getIdReservation(), $guest->getIdName(), $psg->getIdPsg(), $arrivalDT, $departureDT);

        foreach ($resvs as $rv) {

            // guest have a resv with another psg?
            if ($guest->getIdName() == $rv['idGuest'] && $rv['idPsg'] != $psg->getIdPsg()) {

                $dataArray['warning'] = 'This guest has a concurrent <a href="Referral.php?rid=' . $rv['idReservation'] . '">'. $labels->getString('guestEdit', 'reservationTitle', 'reservation') . '</a> with a different patient.  ';
                return $dataArray;
            }

            // another concurrent reservation already there
            if ($guest->getIdName() == $rv['idGuest'] && $rv['idPsg'] == $psg->getIdPsg()) {

                $dataArray['warning'] = 'This guest already has a <a href="Referral.php?rid=' . $rv['idReservation'] . '">'. $labels->getString('guestEdit', 'reservationTitle', 'reservation') . '</a> with this patient.  ';
                return $dataArray;
            }
        }






        // Hospital
        $hstay = new HospitalStay($dbh, $psg->getIdPatient());
        Hospital::saveReferralMarkup($dbh, $psg, $hstay, $post);


        // Number Guests
        $numGuests = 1;
        if ($resv->isNew() === FALSE) {
            $numGuests = count($allGuests);
        } else if ($resv->isNew() && $patientStaying) {
            $numGuests++;
        }

        $resv->setNumberGuests($numGuests);

        // Notes
        $notes = '';
        if (isset($post['txtRnotes'])) {
            $notes = filter_var($post['txtRnotes'], FILTER_SANITIZE_STRING);
        }


        // Registration
        $reg = new Registration($dbh, $psg->getIdPsg());

        if ($uS->TrackAuto) {
            $reg->extractVehicleFlag($post);
        }

        $reg->saveRegistrationRs($dbh, $psg->getIdPsg(), $uS->username);


        // reservation parameters
        $resv->setHospitalStay($hstay);
        $resv->setNotes($notes, $uS->username);

        // Room Rate
        $rateChooser = new RateChooser($dbh);

        // Default Room Rate category
        if ($uS->RoomPriceModel == ItemPriceCode::Basic) {
            $rateCategory = RoomRateCategorys::Fixed_Rate_Category;
        } else if ($uS->RoomRateDefault != '') {
            $rateCategory = $uS->RoomRateDefault;
        } else {
            $rateCategory = Default_Settings::Rate_Category;
        }


        // Get the rate category
        if (isset($post['selRateCategory'])) {

            $rateCat = filter_var($post['selRateCategory'], FILTER_SANITIZE_STRING);

            if ($rateChooser->validateCategory($rateCat) === TRUE) {
                $rateCategory = $rateCat;
            }

        } else {
            // Look for an approved rate
            if ($reg->getIdRegistration() > 0 && $uS->IncomeRated) {

                $fin = new FinAssistance($dbh, $reg->getIdRegistration());

                if ($fin->hasApplied() && $fin->getFaCategory() != '') {
                    $rateCategory = $fin->getFaCategory();
                }
            }
        }

        // Only assign the rate id if the category changes
        if ($resv->getRoomRateCategory() != $rateCategory) {
            $rateRs = $rateChooser->getPriceModel()->getCategoryRateRs(0, $rateCategory);
            $resv->setIdRoomRate($rateRs->idRoom_rate->getStoredVal());
        }

        $resv->setRoomRateCategory($rateCategory);


        if ($rateCategory == RoomRateCategorys::Fixed_Rate_Category) {

            // Check for rate setting amount.
            if (isset($post['txtFixedRate'])) {

                if ($post['txtFixedRate'] === '0' || $post['txtFixedRate'] === '') {
                    $fixedRate = 0;
                } else {
                    $fixedRate = floatval(filter_var($post['txtFixedRate'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
                }

                if ($fixedRate < 0) {
                    $fixedRate = 0;
                }

                $resv->setFixedRoomRate($fixedRate);
                $resv->setRateAdjust(0);
            }

        } else if (isset($post['txtadjAmount'])) {

            // Save rate adjustment
            if ($post['txtadjAmount'] === '0' || $post['txtadjAmount'] === '') {
                $rateAdjust = 0;
            } else {
                $rateAdjust = floatval(filter_var($post['txtadjAmount'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION));
            }

            $resv->setRateAdjust($rateAdjust);

        }


        // Set reservation status
        if ($reservStatus != '') {
            $resv->setStatus($reservStatus);

            // remove room if reservation is in waitlist
            if ($reservStatus == ReservationStatus::Waitlist) {
                $resv->setIdResource(0);
            }
        }

        if (isset($post['selPayType'])) {
            $resv->setExpectedPayType(filter_var($post['selPayType'], FILTER_SANITIZE_STRING));
        }

        if (isset($post['cbVerbalConf']) && $resv->getVerbalConfirm() != 'v') {
            $resv->setVerbalConfirm('v');
            $resv->setNotes('Verbal confirmation set', $uS->username);
        } else {
            $resv->setVerbalConfirm('');
        }

        if (isset($post['taCkinNotes'])) {
            $tackin = filter_var($post['taCkinNotes'], FILTER_SANITIZE_STRING);
            $resv->setCheckinNotes($tackin);
        }

        if (isset($post['selVisitFee']) && $uS->VisitFee) {

            $visitFeeOption = filter_var($post['selVisitFee'], FILTER_SANITIZE_STRING);

            $vFees = RateChooser::makeVisitFeeArray($dbh);

            if (isset($vFees[$visitFeeOption])) {
                $resv->setVisitFee($vFees[$visitFeeOption][2]);
            } else {
                $resv->setVisitFee($vFees[$uS->DefaultVisitFee][2]);
            }

        } else if ($resv->isNew() && $uS->VisitFee) {

            $vFees = RateChooser::makeVisitFeeArray($dbh);
            $resv->setVisitFee($vFees[$uS->DefaultVisitFee][2]);
        }

        // Save reservation
        $resv->saveReservation($dbh, $reg->getIdRegistration(), $uS->username);
        ReservationSvcs::saveReservationGuest($dbh, $resv->getIdReservation(), $guest->getIdName(), TRUE);
        $resv->saveConstraints($dbh, $post);

        // Save any vehicles
        if ($uS->TrackAuto && $reg->getNoVehicle() == 0) {
            Vehicle::saveVehicle($dbh, $post, $reg->getIdRegistration());
        }

        // Save patient as reservationGuest if staying at the house
        if ($patientStaying && is_null($patient) === FALSE) {
            self::saveReservationGuest($dbh, $resv->getIdReservation(), $patient->getIdName(), FALSE);
        }

        $chooserMessage = '';

        $roomChooser = new RoomChooser($dbh, $resv, $resv->getNumberGuests(), new DateTime($resv->getExpectedArrival()), new DateTime($resv->getExpectedDeparture()));

        // Process reservation
        if ($resv->getStatus() == ReservationStatus::Pending || $resv->isActive()) {

            $roomChooser->findResources($dbh, $isAuthorized);

            $chooserMessage = ReservationSvcs::processReservation($dbh, $resv, $idRescPosted, $resv->getFixedRoomRate(), $numGuests, $guest->getExpectedCheckinDate(), $guest->getExpectedCheckOut(), $isAuthorized, $uS->username, $uS->InitResvStatus);

        }



        //
        // Payment
        //
        $paymentManager = new PaymentManager(PaymentChooser::readPostedPayment($dbh, $post));

        $payResult = self::processPayments($dbh, $paymentManager, $resv, 'Referral.php');

        if ($payResult !== NULL) {

            if ($payResult->getStatus() == PaymentResult::FORWARDED) {
                $creditCheckOut = $payResult->getForwardHostedPayment();
            }

            // Receipt
            if (is_null($payResult->getReceiptMarkup()) === FALSE && $payResult->getReceiptMarkup() != '') {
                $dataArray['receipt'] = HTMLContainer::generateMarkup('div', $payResult->getReceiptMarkup());
                Registration::updatePrefTokenId($dbh, $resv->getIdRegistration(), $payResult->getIdToken());
            }

            // New Invoice
            if (is_null($payResult->getInvoiceMarkup()) === FALSE && $payResult->getInvoiceMarkup() != '') {
                $dataArray['invoice'] = HTMLContainer::generateMarkup('div', $payResult->getInvoiceMarkup());
            }
        }

        $results = HouseServices::cardOnFile($dbh, $resv->getIdGuest(), $reg->getIdRegistration(), $post, 'Referral.php?rid='.$resv->getIdReservation());

        if (isset($results['error'])) {
            $dataArray['error'] = $results['error'];
            unset($results['error']);
        }

        // GO to Card on file?
        if (count($creditCheckOut) > 0) {
            return $creditCheckOut;
        } else if (count($results) > 0) {
            return $results;
        }



        //
        // Start defining the return markup
        //

        $dataArray['hosp'] = Hospital::createReferralMarkup($dbh, $hstay);

        $dataArray['idReserv'] = $resv->getIdReservation();

        $dataArray['rvstatus'] = $resv->getStatusTitle($resv->getStatus());
        $dataArray['rvstCode'] = $resv->getStatus();

        if ($resv->getStatus() == ReservationStatus::Committed) {
            $dataArray['showRegBtn'] = 'y';
        } else {
            $dataArray['showRegBtn'] = 'n';
        }

        // REservation notes
        $dataArray['notes'] = $guest->createNotesMU($resv->getNotes(), 'txtRnotes', $labels);

        // send resource information
        if (is_null($roomChooser->getSelectedResource()) === FALSE) {

            $dataArray['rmax'] = $roomChooser->getSelectedResource()->getMaxOccupants();
        }

        $showPayWith = TRUE;

        // Rate and payments
        if ($uS->RoomPriceModel != ItemPriceCode::None) {

            $dataArray['pay'] = PaymentChooser::createResvMarkup($dbh, $guest->getIdName(), $reg, removeOptionGroups($uS->nameLookups[GL_TableNames::PayType]), $resv->getExpectedPayType(), $uS->ccgw);

            // Array with amount calculated for each rate.
            $dataArray['ratelist'] = $rateChooser->makeRateArray($dbh, $resv->getExpectedDays(), $resv->getIdRegistration(), $resv->getFixedRoomRate());

            // Rate Chooser
            $dataArray['rate'] = $rateChooser->createResvMarkup($dbh, $resv, $resv->getExpectedDays(), $labels->getString('statement', 'cleaningFeeLabel', 'Cleaning Fee'));

            if ($uS->VisitFee) {
                // Visit Fee Array
                $dataArray['vfee'] = $rateChooser::makeVisitFeeArray($dbh);
            }

        } else {
            $showPayWith = FALSE;
        }

        // Reservation Status
        $dataArray['resv'] = self::createStatusChooser($resv, $resv->getChooserStatuses($uS->guestLookups['ReservStatus']), $uS->nameLookups[GL_TableNames::PayType], $labels, $showPayWith, Registration::loadLodgingBalance($dbh, $resv->getIdRegistration()));

        // Array with key deposit info
        $dataArray['rooms'] = $rateChooser->makeRoomsArray($roomChooser, $uS->guestLookups['Static_Room_Rate'], $uS->guestLookups[GL_TableNames::KeyDepositCode]);


        // Room Chooser
        $dataArray['resc'] = $roomChooser->CreateResvMarkup($dbh, $isAuthorized, FALSE, 'hhk-rmchsr');



        // Vehicles
        if ($uS->TrackAuto) {
            $dataArray['vehicle'] = Vehicle::createVehicleMarkup($dbh, $reg->getIdRegistration(), $reg->getNoVehicle());
        }

        $dataArray['patStay'] = $patientStaying;

        $dataArray['adguests'] = HTMLContainer::generateMarkup('fieldset',
                    ReservationSvcs::moreGuestsTable($dbh, $resv, self::getReservGuests($dbh, $resv->getIdReservation()), $psg, FALSE)
                    , array('class'=>'hhk-panel'));


        return $dataArray;
    }

    public static function processPayments(\PDO $dbh, PaymentManager $paymentManager, Reservation_1 $resv, $postbackPage) {

        $uS = Session::getInstance();
        $payResult = NULL;

//        if (is_null($paymentManager->pmp)) {
//            return $payResult;
//        }
//
//
//        // Create Invoice.
//
//
//        if ($invoice->getStatus() == InvoiceStatus::Unpaid) {
//
//            // Make guest payment
//            $payResult = $paymentManager->makeHousePayment($dbh, $pmp, $invoice, $postbackPage, $uS->username, $pmp->getPayDate());
//        }

        return $payResult;

    }


    public static function processReservation(\PDO $dbh, Reservation_1 &$resv, $idRescPosted, $fixedRateAmount, $numGuests, $chkinDate, $chkoutDate, $isAuthorized, $userName, $initialResvStatus) {

        $chooserMessage = '';

        if ($resv->getStatus() == ReservationStatus::Pending || $resv->isActive()) {

            $resources = $resv->getAvailableResources();

            // Does the resource still fit the requirements?
            if (($resv->getStatus() == ReservationStatus::Committed || $resv->getStatus() == ReservationStatus::UnCommitted) && $resv->getIdResource() > 0 && isset($resources[$resv->getIdResource()]) === FALSE) {
                $idRescPosted = 0;
                $chooserMessage .= "Original Room is unavailable.  ";
            }

            // Getting or changing a room?
            if ($idRescPosted > 0 && $resv->getIdResource() != $idRescPosted) {

                // Does the resource still fit the requirements?
                if (isset($resources[$idRescPosted]) === FALSE) {

                    $chooserMessage .= 'Chosen Room is unavailable.  ';
                    $resv->setIdResource(0);
                    $resv->setFixedRoomRate($fixedRateAmount);
                    $resv->setStatus(ReservationStatus::Waitlist);

                } else {

                    $resv->setExpectedArrival($chkinDate);
                    $resv->setExpectedDeparture($chkoutDate);
                    $resv->setIdResource($idRescPosted);
                    $resv->setFixedRoomRate($fixedRateAmount);

                    // Don't change comitted to uncommitted.
                    if ($resv->getStatus() != ReservationStatus::Committed) {
                        $resv->setStatus($initialResvStatus);
                    }
                }


            //  waitlist?
            } else if ($idRescPosted == 0 || $resv->getStatus() == ReservationStatus::Waitlist) {

                // reverting to a waitlist
                $resv->setExpectedArrival($chkinDate);
                $resv->setExpectedDeparture($chkoutDate);
                $resv->setIdResource(0);
                $resv->setFixedRoomRate($fixedRateAmount);

                $resv->setStatus(ReservationStatus::Waitlist);



            // Dates changed?  Number of guests changed?
            } else if ($resv->getStatus() != ReservationStatus::Pending) {

                $rStart = new DateTime($resv->getExpectedArrival());
                $rEnd = new DateTime($resv->getExpectedDeparture());
                $chkinDT = new DateTime($chkinDate);
                $chkoutDT = new DateTime($chkoutDate);

                if ($chkinDT->format('Y-m-d') != $rStart->format('Y-m-d')
                    || $chkoutDT->format('Y-m-d') != $rEnd->format('Y-m-d')
                    || $resv->getNumberGuests() != $numGuests) {

                    // Dates have changed.
                    // Does the resource still fit the requirements?
                    $roomChooser = new RoomChooser($dbh, $resv, $numGuests, $chkinDT, $chkoutDT);
                    $resources = $roomChooser->findResources($dbh, $isAuthorized);

                    if (isset($resources[$resv->getIdResource()]) === FALSE) {

                        $chooserMessage .= "This Room is unavailable for new dates or number of guests.  ";
                        $resv->setIdResource(0);

                    }

                    // finish
                    $resv->setExpectedArrival($chkinDate);
                    $resv->setExpectedDeparture($chkoutDate);

                }
            }

            $resv->saveReservation($dbh, $resv->getIdRegistration(), $userName);
        }

        return $chooserMessage;

    }


    protected static function createStatusChooser(Reservation_1 $resv, array $limResvStatuses, array $payTypes, \Config_Lite $labels, $showPayWith, $moaBal) {

        $tbl2 = new HTMLTable();
        // Pay option, verbal confirmation

        $attr = array('name'=>'cbVerbalConf', 'type'=>'checkbox');

        if ($resv->getVerbalConfirm() == 'v') {
            $attr['checked'] = 'checked';
        }

        $moaHeader = '';
        $moaData = '';
        if ($moaBal > 0) {
            $moaHeader = HTMLTable::makeTh('MOA Balance', array('title'=>'MOA = Money on Account'));
            $moaData = HTMLTable::makeTd('$' . number_format($moaBal, 2), array('style'=>'text-align:center'));
        }

        $tbl2->addBodyTr(
                ($showPayWith ? HTMLTable::makeTh('Pay With') . $moaHeader : '')
                .HTMLTable::makeTh('Verbal Confirmation')
                .($resv->getStatus() == ReservationStatus::UnCommitted ? HTMLTable::makeTh('Status', array('class'=>'ui-state-highlight')) : HTMLTable::makeTh('Status'))
                );

        $tbl2->addBodyTr(
                ($showPayWith ? HTMLTable::makeTd(HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup(removeOptionGroups($payTypes), $resv->getExpectedPayType()), array('name'=>'selPayType')))
                . $moaData : '')
                .HTMLTable::makeTd(HTMLInput::generateMarkup('', $attr), array('style'=>'text-align:center;'))
                .HTMLTable::makeTd(
                        HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($limResvStatuses, $resv->getStatus(), FALSE), array('name'=>'selResvStatus', 'style'=>'float:left;margin-right:.4em;'))
                        .HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ui-icon-comment hhk-viewResvActivity', 'data-rid'=>$resv->getIdReservation(), 'title'=>'View Activity Log', 'style'=>'cursor:pointer;float:right;')))
                );



        $tbl2->addBodyTr(HTMLTable::makeTd('Check-in Note:',array('class'=>'tdlabel')).HTMLTable::makeTd(HTMLContainer::generateMarkup('textarea',$resv->getCheckinNotes(), array('name'=>'taCkinNotes', 'rows'=>'1', 'cols'=>'40')),array('colspan'=>'3')));

        // Confirmation button
        $mk2 = '';
        if ($resv->getStatus() == ReservationStatus::Committed) {
            $mk2 .= HTMLInput::generateMarkup('Create Confirmation...', array('type'=>'button', 'id'=>'btnShowCnfrm', 'style'=>'margin:.3em;float:right;', 'data-rid'=>$resv->getIdReservation()));
        }

        // fieldset wrapper
        return HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('fieldset',
                    HTMLContainer::generateMarkup('legend', $labels->getString('referral', 'statusLabel', 'Reservation Status'), array('style'=>'font-weight:bold;'))
                    . $tbl2->generateMarkup() . $mk2,
                    array('class'=>'hhk-panel'))
            , array('style'=>'float:left;'));

    }


    public static function viewActivity(\PDO $dbh, $idResv) {

        if ($idResv < 1) {
            return array('error'=>'Reservation not defined.  ');
        }

        return array('activity'=>  ActivityReport::reservLog($dbh, '', '', $idResv));
    }


    public static function getReservGuests(\PDO $dbh, $idReservation) {

        $rGuests = array();

        if ($idReservation != 0) {

            $resGuestRs = new Reservation_GuestRS();
            $resGuestRs->idReservation->setStoredVal($idReservation);
            $grows = EditRS::select($dbh, $resGuestRs, array($resGuestRs->idReservation));

            foreach ($grows as $g) {
                $resGuestRs = new Reservation_GuestRS();
                EditRS::loadRow($g, $resGuestRs);
                $rGuests[$resGuestRs->idGuest->getStoredVal()] = $resGuestRs->Primary_Guest->getStoredVal();
            }
        }

        return $rGuests;

    }

    public static function moreGuestsTable(\PDO $dbh, Reservation_1 $resv, array $guests, $psg, $nonActiveButtons, $editPage = "GuestEdit.php") {

        $uS = Session::getInstance();

        $rows = $psg->loadViews($dbh, 0, $psg->getIdPsg());

        $tbl = new HTMLTable();

        foreach ($rows as $r) {

            // Filter out primary guest
            if (isset($guests[$r['idGuest']]) && $guests[$r['idGuest']] == '1') {
                continue;
            }

            if (isset($guests[$r['idGuest']])) {
                $btn = HTMLContainer::generateMarkup('button', 'Remove', array('id'=>'btnDelGuest'.$r['idGuest'], 'type'=>'button', 'data-name'=>$r['Name_First'] . ' ' . $r['Name_Last'], 'data-id'=>$r['idGuest'], 'class'=>'hhk-delResv', 'title'=>'Remove from the reservation'));
                $ent = HTMLContainer::generateMarkup('a', $r['Name_First'] . ' ' . $r['Name_Last'], array('href'=>$editPage.'?id='.$r['idGuest'].'&psg='.$psg->getIdPsg(), 'class'=>'ui-state-highlight'));
                $rel = HTMLTable::makeTd($uS->guestLookups[GL_TableNames::PatientRel][$r['Relationship_Code']][1]);
                $ph = HTMLTable::makeTd($r['Preferred_Phone']);
            } else {
                $btn = HTMLContainer::generateMarkup('button', 'Add', array('id'=>'btnAdd'.$r['idGuest'], 'type'=>'button', 'data-name'=>$r['Name_First'] . ' ' . $r['Name_Last'], 'data-id'=>$r['idGuest'], 'class'=>'hhk-addResv', 'title'=>'Add to the reservation'));
                $ent = HTMLContainer::generateMarkup('a', $r['Name_First'] . ' ' . $r['Name_Last'], array('href'=>$editPage.'?id='.$r['idGuest'].'&psg='.$psg->getIdPsg(), 'style'=>'color:#B5BECF;'));
                $rel = HTMLTable::makeTd($uS->guestLookups[GL_TableNames::PatientRel][$r['Relationship_Code']][1], array('style'=>'color:#B5BECF;'));
                $ph = HTMLTable::makeTd($r['Preferred_Phone'], array('style'=>'color:#B5BECF;'));
            }

            $tbl->addBodyTr(
                    HTMLTable::makeTd($btn)
                    .HTMLTable::makeTd($ent)
                    .$rel
                    .$ph
                    );
        }

        $tbl->addHeaderTr(HTMLTable::makeTh('').HTMLTable::makeTh('Guest Name').HTMLTable::makeTh('Patient Relationship').HTMLTable::makeTh('Phone'));

        if ($nonActiveButtons === FALSE) {
            $tbl->addFooter(
                HTMLTable::makeTh('New')
                .HTMLTable::makeTd('Name: ' . HTMLInput::generateMarkup('', array('id'=>'txtAddGuest', 'type'=>'text', 'size'=>'20', 'title'=>'Enter 3 letters to start search.')), array('colspan'=>'2'))
                .HTMLTable::makeTd('Ph:'. HTMLInput::generateMarkup('', array('id'=>'txtAddPhone', 'type'=>'text', 'size'=>'10', 'title'=>'Enter 5 numbers to start search.')))
                );
        }

        $addGuests = HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'Additional Guests', array('style'=>'font-weight:bold;'))
                .$tbl->generateMarkup()
                ,array('class'=>'hhk-panel'));


        $additionalRoomMkup = '';

        // Attitional Rooms
        if ($uS->RoomsPerPatient > 1 && ($resv->getStatus() == ReservationStatus::Waitlist || $resv->getStatus() == ReservationStatus::Committed || $resv->getStatus() == ReservationStatus::UnCommitted)) {

            $stmt = $dbh->query("select count(*) from reservation "
                    . " where  idRegistration = " . $resv->getIdRegistration() . " and `Status` in ('" . ReservationStatus::Staying . "', '" . ReservationStatus::Committed . "', '" . ReservationStatus::Waitlist . "', '" . ReservationStatus::UnCommitted . "')"
                    . " and DATE(ifnull(Actual_Arrival, Expected_Arrival)) < DATE('"
                        . date('Y-m-d', strtotime($resv->getExpectedDeparture())) . "') and DATE(ifnull(Actual_Departure, Expected_Departure)) > DATE('"
                        . date('Y-m-d', strtotime($resv->getExpectedArrival())) . "')");
            $rcount = $stmt->fetchAll(PDO::FETCH_NUM);

            if ($rcount[0][0] < $uS->RoomsPerPatient) {
                // Include Additional Room Query
                $additionalRoomMkup = RoomChooser::moreRoomsMarkup($rcount[0][0], FALSE);
            } else {
                $additionalRoomMkup = HTMLContainer::generateMarkup('p', 'Already using the maximum of ' . $uS->RoomsPerPatient . ' rooms per patient.', array('style'=>'margin:.3em;'));
            }
        }

        return $additionalRoomMkup . $addGuests;

    }

    public static function removeResvGuest(\PDO $dbh, $id, $idReserv, $uname = '') {

        $resGuestRs = new Reservation_GuestRS();
        $resGuestRs->idReservation->setStoredVal($idReserv);
        $resGuestRs->idGuest->setStoredVal($id);

        EditRS::delete($dbh, $resGuestRs, array($resGuestRs->idReservation, $resGuestRs->idGuest));

        // Reservation-Guests list
        $guests = self::getReservGuests($dbh, $idReserv);
        $dataArray['numGuests'] = count($guests);

        $resv = Reservation_1::instantiateFromIdReserv($dbh, $idReserv);

        $idPsg = $resv->getIdPsg($dbh);

        if ($idPsg == 0) {
            return array('error'=>'Undefined patient support group.  ');
        }

        $psg = new Psg($dbh, $idPsg);

        // Update reservation record
        $resv->setNumberGuests(count($guests));
        $resv->saveReservation($dbh, 0, $uname);

        if ($psg->getIdPatient() == $id) {
            $dataArray['patStay'] = false;
        }

        $dataArray['adguests'] = HTMLContainer::generateMarkup('fieldset',
                ReservationSvcs::moreGuestsTable($dbh, $resv, $guests, $psg, FALSE), array('class'=>'hhk-panel'));

        return $dataArray;
    }

    public static function saveReservationGuest(\PDO $dbh, $idReservation, $idGuest, $primary = FALSE) {

        // Save reservation-guest
        $rgRs = new Reservation_GuestRS();
        $rgRs->idReservation->setStoredVal($idReservation);
        $rgRs->idGuest->setStoredVal($idGuest);
        $rgs = EditRS::select($dbh, $rgRs, array($rgRs->idReservation, $rgRs->idGuest));


        if (count($rgs) == 0) {
            $rgRs = new Reservation_GuestRS();
            $rgRs->idReservation->setNewVal($idReservation);
            $rgRs->idGuest->setNewVal($idGuest);

            if ($primary) {
                $rgRs->Primary_Guest->setNewVal('1');
            }

            EditRS::insert($dbh, $rgRs);

        } else {
            $rgRs = new Reservation_GuestRS();
            EditRS::loadRow($rgs[0], $rgRs);

            if ($primary) {
                $rgRs->Primary_Guest->setNewVal('1');
            } else {
                $rgRs->Primary_Guest->setNewVal('');
            }

            EditRS::update($dbh, $rgRs, array($rgRs->idReservation, $rgRs->idGuest));

        }

        return 1;
    }

    public static function getConfirmForm(\PDO $dbh, $idReservation, $amount, $sendEmail = FALSE, $notes = '-', $emailAddr = '') {

        if ($idReservation == 0) {
            return array('error'=>'Bad reservation Id: ' . $idReservation);
        }

        $uS = Session::getInstance();
        $dataArray = array();

        $reserv = Reservation_1::instantiateFromIdReserv($dbh, $idReservation);

        $guest = new Guest($dbh, '', $reserv->getIdGuest());

        $expectedDays = $reserv->getExpectedDays($reserv->getExpectedArrival(), $reserv->getExpectedDeparture());

        if ($emailAddr == '') {
            $emAddr = $guest->getEmailsObj()->get_data($guest->getEmailsObj()->get_preferredCode());
            $emailAddr = $emAddr["Email"];
        }

        require(HOUSE . 'ConfirmationForm.php');

        $form = ConfirmationForm::createForm(
                ConfirmationForm::getFormTemplate($uS->ConfirmFile),
                $guest->getNameObj()->get_fullName(),
                $reserv->getExpectedArrival(),
                $reserv->getExpectedDeparture(),
                $expectedDays,
                floatval($amount),
                $notes);

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

                $mail->Subject = $uS->siteName . ' Reservation Confirmation';
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
            $dataArray['confrv'] = $form;
            $dataArray['email'] = $emailAddr;
        }

        return $dataArray;
    }

    public static function generateCkinDoc(\PDO $dbh, $idReservation = 0, $idVisit = 0, $logoURL = '', $mode = 'live', $notes = '') {

        $uS = Session::getInstance();


        if ($uS->RegForm == 1) {

            $doc = RegisterForm::prepareReceipt($dbh, $idVisit, $idReservation);
            $sty = RegisterForm::getStyling();

        } else if ($uS->RegForm == 2) {

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

            $doc = $regdoc->getDocument($dbh, $priGuest, $billingGuest, $additionalGuests, $patientName, $hospitalName, $roomTitle, $cardName, $cardType, $cardNumber, $logoURL, $logoWidth, $expectedPayType, $notes, $todaysDate);
            $sty = $regdoc->getStyle();

        } else {
            return array('doc'=>'Error - Registration Form # is not defined in the system configuration table.', 'style'=>' ');
        }

        return array('doc'=>$doc, 'style'=>$sty);

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

        if (ComponentAuthClass::is_Authorized('guestadmin') === FALSE) {
            return array("error" => "User not authorized to move reservations.");
        }

        if ($idReservation == 0) {
            return array("error" => "Reservation not specified.");
        }

        if ($startDelta == 0 && $endDelta == 0) {
            return array("error" => "Reservation not moved.");
        }

        if (abs($endDelta) > 21 || abs($startDelta) > 21) {
            return array("error" => 'Move refused, change too large: Start Delta = ' . $startDelta . ', End Delta = ' . $endDelta);
        }

        // save the reservation info
        $reserv = Reservation_1::instantiateFromIdReserv($dbh, $idReservation);
        $worked = $reserv->move($dbh, $startDelta, $endDelta, $uS->username);
        $reply = $reserv->getResultMessage();

        if ($worked) {

            // Return checked in guests markup?
            if ($uS->Reservation) {
                if ($reserv->getStatus() == ReservationStatus::Committed) {
                    $dataArray['reservs'] = 'y';
                } else if ($reserv->getStatus() == ReservationStatus::UnCommitted && $uS->ShowUncfrmdStatusTab) {
                    $dataArray['unreserv'] = 'y';
                } else if ($reserv->getStatus() == ReservationStatus::Waitlist) {
                    $dataArray['waitlist'] = 'y';
                }
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


    public static function psgChooserMkup(\PDO $dbh, array $ngRss, $patientAsGuest, $offerNew = TRUE) {

        $tbl = new HTMLTable();
        $tbl->addHeaderTr(HTMLTable::makeTh('Who is the Patient?', array('colspan'=>'2')));

        $firstOne = TRUE;

        foreach ($ngRss as $n) {

            $psg = new Psg($dbh, $n->idPsg->getStoredVal());

            $attrs = array('type'=>'radio', 'value'=>$psg->getIdPsg(), 'name'=>'cbselpsg', 'data-pid'=>$psg->getIdPatient(), 'data-ngid'=>$n->idName->getStoredVal());
            if ($firstOne) {
                $attrs['checked'] = 'checked';
                $firstOne = FALSE;
            }

            $tbl->addBodyTr(
                    HTMLTable::makeTd($psg->getPatientName($dbh), array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup('', $attrs)));

        }

        // Add new PSG choice
        if ($offerNew) {
            $tbl->addBodyTr(
                HTMLTable::makeTd('New Patient', array('class'=>'tdlabel'))
               .HTMLTable::makeTd(HTMLInput::generateMarkup('-1', array('type'=>'radio', 'name'=>'cbselpsg', 'data-pid'=>'0', 'data-ngid'=>'0'))));
        }

        if ($patientAsGuest) {

            $tbl->addBodyTr(HTMLTable::makeTd('', array('colspan'=>'2')));
            $tbl->addBodyTr(HTMLTable::makeTh('Is the Patient staying the First night (or longer)?', array('colspan'=>'2')));

            $tbl->addBodyTr(
                    HTMLTable::makeTd(HTMLContainer::generateMarkup('label', 'Yes, at least the First night', array('for'=>'cbpstayy')), array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('type'=>'radio', 'value'=>'yes', 'name'=>'cbpstay', 'id'=>'cbpstayy')), array('class'=>'pstaytd')));
            $tbl->addBodyTr(
                    HTMLTable::makeTd(HTMLContainer::generateMarkup('label', 'No, not the first night', array('for'=>'cbpstayn')), array('class'=>'tdlabel'))
                    .HTMLTable::makeTd(HTMLInput::generateMarkup('', array('type'=>'radio', 'value'=>'no', 'name'=>'cbpstay', 'id'=>'cbpstayn')), array('class'=>'pstaytd')));
            $tbl->addBodyTr(HTMLTable::makeTd(HTMLContainer::generateMarkup('span', '', array('id'=>'spnstaymsg', 'style'=> 'color:red')), array('colspan'=>'2')));
        }


        return $tbl->generateMarkup();
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


        if ($uS->Reservation) {

            if ($oldStatus == ReservationStatus::Committed || $status == ReservationStatus::Committed) {
                $dataArray['reservs'] = 'y';
            }

            if ($oldStatus == ReservationStatus::UnCommitted || $status == ReservationStatus::UnCommitted) {
                $dataArray['unreserv'] = 'y';
            }

            if ($oldStatus == ReservationStatus::Waitlist || $status == ReservationStatus::Waitlist) {
                $dataArray['waitlist'] = 'y';
            }

        }

        return $dataArray;
    }

    public static function getRoomList(\PDO $dbh, $idResv, $eid, $isAuthorized) {

        if ($idResv < 1) {
            return array('error'=>'Reservation Id is not set.');
        }

        $resv = Reservation_1::instantiateFromIdReserv($dbh, $idResv);

        if ($isAuthorized) {
            $resv->findGradedResources($dbh, $resv->getExpectedArrival(), $resv->getExpectedDeparture(), $resv->getNumberGuests(), array('room','rmtroom','part'), TRUE);
        } else {
            $resv->findResources($dbh, $resv->getExpectedArrival(), $resv->getExpectedDeparture(), $resv->getNumberGuests(), array('room','rmtroom','part'), TRUE);
        }

        $resources = array();
        $errorMessage = '';

        // Load available resources
        foreach ($resv->getAvailableResources() as $r) {
            $resources[$r->getIdResource()] = array($r->getIdResource(), $r->getTitle(), $r->optGroup);
        }

        // add waitlist option to the top of the list
        $resources[0] = array(0, 'Waitlist', '');


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

        $dataArray['container'] = HTMLContainer::generateMarkup('div',
        HTMLSelector::generateMarkup(HTMLSelector::doOptionsMkup($resources, $idResourceChosen), array('id'=>'selRoom', 'size'=>$selSize))
                , array('id'=>'pudiv', 'class'=>"ui-widget ui-widget-content ui-helper-clearfix ui-corner-all", 'style'=>"font-size:0.9em;position: absolute; z-index: 1; display: block;"));  // top:".$y."px; left:".$xa."px;
        $dataArray['eid'] = $eid;
        $dataArray['msg'] = $errorMessage;
        $dataArray['rid'] = $idResv;

        return $dataArray;
    }

    public static function setNewRoom(\PDO $dbh, $idResv, $idResc, $isAuthorized) {

        $uS = Session::getInstance();

        if ($idResv < 1) {
            return array('error'=>'Reservation Id is not set.');
        }

        $resv = Reservation_1::instantiateFromIdReserv($dbh, $idResv);

        if ($isAuthorized) {
            $resv->findGradedResources($dbh, $resv->getExpectedArrival(), $resv->getExpectedDeparture(), $resv->getNumberGuests(), array('room','rmtroom','part'), TRUE);
        } else {
            $resv->findResources($dbh, $resv->getExpectedArrival(), $resv->getExpectedDeparture(), $resv->getNumberGuests(), array('room','rmtroom','part'), TRUE);
        }

        $dataArray['msg'] = self::processReservation($dbh, $resv, $idResc, $resv->getFixedRoomRate(), $resv->getNumberGuests(), $resv->getExpectedArrival(), $resv->getExpectedDeparture(), $isAuthorized, $uS->username, $uS->InitResvStatus);

        // New resservation lists
        if ($uS->Reservation) {
            if ($resv->getStatus() == ReservationStatus::Committed) {
                $dataArray['reservs'] = 'y';
            }

            if ($resv->getStatus() == ReservationStatus::UnCommitted) {
                $dataArray['unreserv'] = 'y';
            }

            if ($resv->getStatus() == ReservationStatus::Waitlist) {
                $dataArray['waitlist'] = 'y';
            }
        }

        return $dataArray;

    }

    public static function deleteReservation(\PDO $dbh, $rid) {

        // Get labels
        $labels = new Config_Lite(LABEL_FILE);
        $uS = Session::getInstance();

        if ($rid > 0) {

            $resv = Reservation_1::instantiateFromIdReserv($dbh, $rid);

            if ($resv->getStatus() == ReservationStatus::Committed || $resv->getStatus() == ReservationStatus::UnCommitted || $resv->getStatus() == ReservationStatus::Waitlist || $resv->getStatus() == ReservationStatus::Imediate || $resv->getStatus() == ReservationStatus::Canceled) {

                // Okay to delete
                $resv->deleteMe($dbh, $uS->username);

                $dataArray['result'] = $labels->getString('guestEdit', 'reservationTitle', 'Reservation') . ' Deleted.';

            } else {
                $dataArray['warning'] = $labels->getString('guestEdit', 'reservationTitle', 'Reservation') . ' status cannot be deleted: "' . $resv->getStatusTitle() . '"';
            }

        } else {
            $dataArray['warning'] = $labels->getString('guestEdit', 'reservationTitle', 'Reservation') . ' Id is not valid.  ';
        }

        return $dataArray;
    }
}
