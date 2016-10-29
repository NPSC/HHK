<?php

/**
 * Visit.php
 *
 *
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2016 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of Visit
 * @author Eric
 */
class Visit {

    protected $resource = NULL;
    public $visitRS;
    protected $visitRSs = array();
    private $newVisit = FALSE;
    public $stays = array();
    protected $overrideMaxOccupants = FALSE;

    /**
     *
     * @param PDO $dbh
     * @param integer $idReg
     * @param integer $idVisit

     * @param Resource $resource
     * @throws Hk_Exception_Runtime
     */
    function __construct(PDO $dbh, $idReg, $idVisit, DateTime $arrivalDT = NULL, DateTime $departureDT = NULL, Resource $resource = NULL, $userName = '', $span = -1, $forceNew = FALSE) {

        $this->visitRSs = $this->loadVisits($dbh, $idReg, $idVisit, $span, $forceNew);

        if (!is_null($resource) && !$resource->isNewResource()) {
            $this->resource = $resource;
        }


        // index the current visit span.
        $currentSpan = -1;
        foreach ($this->visitRSs as $v) {
            if ($v->Span->getStoredVal() > $currentSpan) {
                $currentSpan = $v->Span->getStoredVal();
                $this->visitRS = $v;
            }
        }

        if ($this->visitRS->idVisit->getStoredVal() === 0) {

            // New Visit
            // Compare dates
            $nowDT = new DateTime();
            $nowDT->setTime(0, 0, 0);

            if ($arrivalDT > $departureDT) {
                throw new Hk_Exception_Runtime('Silly human, the arrival date cannot be AFTER the departure date.  ');
            }

            $this->visitRS->Arrival_Date->setNewVal($arrivalDT->format('Y-m-d H:i:s'));
            $this->visitRS->Span_Start->setNewVal($arrivalDT->format('Y-m-d H:i:s'));
            $this->visitRS->Status->setNewVal(VisitStatus::Pending);

            $this->visitRS->Expected_Departure->setNewVal($departureDT->format('Y-m-d 10:00:00'));

            $idVisit = EditRS::insert($dbh, $this->visitRS);

            if ($idVisit == 0) {
                throw new Hk_Exception_Runtime('Visit insert failed. ');
            }

            $logText = VisitLog::getInsertText($this->visitRS);
            VisitLog::logVisit($dbh, $idVisit, 0, $this->getidResource(), $idReg, $logText, "insert", $userName);

            $this->visitRS->idVisit->setNewVal($idVisit);
            EditRS::updateStoredVals($this->visitRS);
            $this->newVisit = TRUE;
        }

        $this->loadStays($dbh, VisitStatus::CheckedIn);
    }

    /**
     *
     * @param PDO $dbh
     * @param int $idReg
     * @param int $idVisit
     * @return \VisitRs
     * @throws Hk_Exception_Runtime
     */
    protected function loadVisits(PDO $dbh, $idReg, $idVisit, $span = -1, $forceNew = FALSE) {

        $visitRS = new VisitRs();
        $visits = array();

        if ($idVisit > 0) {

            if ($span >= 0) {

                $visitRS->Span->setStoredVal($span);
                $visitRS->idVisit->setStoredVal($idVisit);
                $rows = EditRS::select($dbh, $visitRS, Array($visitRS->idVisit, $visitRS->Span));

            } else {

                $visitRS->idVisit->setStoredVal($idVisit);
                $rows = EditRS::select($dbh, $visitRS, Array($visitRS->idVisit));
            }

            if (count($rows) == 0) {
                throw new Hk_Exception_Runtime("Visit record not found.");
            } else {
                // load each visit span
                foreach ($rows as $r) {
                    $vRS = new VisitRs();
                    EditRS::loadRow($r, $vRS);
                    $visits[$vRS->Span->getStoredVal()] = $vRS;
                }
            }
        } else if ($idReg > 0 && $idVisit == 0) {

            $visitRS->idRegistration->setStoredVal($idReg);
            $visitRS->Status->setStoredVal(VisitStatus::CheckedIn);
            $rows = EditRS::select($dbh, $visitRS, Array($visitRS->idRegistration, $visitRS->Status));

            if (count($rows) > 1) {

                throw new Hk_Exception_Runtime('More than one checked-in visit for this registration.');
            } else if (count($rows) == 0 || $forceNew) {
                // new visit
                $visitRS = new VisitRs();
                $visitRS->idRegistration->setNewVal($idReg);
                $visitRS->Span->setNewVal(0);
                $visits[0] = $visitRS;
            } else {
                // existing active visit
                $vRS = new VisitRs();
                EditRS::loadRow($rows[0], $vRS);
                $visits[$vRS->Span->getStoredVal()] = $vRS;
            }

        } else if ($idReg > 0 && $idVisit < 0) {
            // add a room to this registration
            $visitRS = new VisitRs();
            $visitRS->idRegistration->setNewVal($idReg);
            $visitRS->Span->setNewVal(0);
            $visits[0] = $visitRS;

        } else {
            throw new Hk_Exception_Runtime("Visit not instantiated.");
        }

        return $visits;
    }

    public function updateVisitRecord(\PDO $dbh, $uname = '') {

        $this->visitRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $this->visitRS->Updated_By->setNewVal($uname);

        $upCtr = EditRS::update($dbh, $this->visitRS, array($this->visitRS->idVisit, $this->visitRS->Span));

        if ($upCtr > 0) {
            $logText = VisitLog::getUpdateText($this->visitRS);

            EditRS::updateStoredVals($this->visitRS);
            VisitLog::logVisit($dbh, $this->getIdVisit(), $this->visitRS->Span->getStoredVal(), $this->visitRS->idResource->getStoredVal(), $this->visitRS->idRegistration->getStoredVal(), $logText, "update", $uname);

        }

        return $upCtr;
    }

    public function addGuestStay($idGuest, $checkinDate, $spanStartDate, $expectedCO = '', $stayOnLeave = 0) {

        // If guest already has an active stay ...
        foreach ($this->stays as $sRS) {

            if (($sRS->Status->getStoredVal() == VisitStatus::CheckedIn || $sRS->Status->getNewVal() == VisitStatus::CheckedIn) &&
                    ($sRS->idName->getStoredVal() == $idGuest || $sRS->idName->getNewVal() == $idGuest)) {
                return;
            }
        }

        // Measure against visit span start date
        if ($this->visitRS->Span_Start->getStoredVal() != '') {
            $spanStartDT = new DateTime($this->visitRS->Span_Start->getStoredVal());
            $ssDT = new DateTime($spanStartDate);
            $spanStartDT->setTime(0, 0, 0);
            if ($ssDT < $spanStartDT) {
                throw new Hk_Exception_Runtime('Stay start date (' . $spanStartDate . ') earlier than visit span start date (' . $spanStartDT->format('Y-m-d H:i:s') . ').  ');
            }
        }


        $rm = $this->resource->allocateRoom(1, $this->overrideMaxOccupants);
        if (is_null($rm)) {
            throw new Hk_Exception_Runtime('Room is full.  ');
        }

        // Create a new stay in memory
        $stayRS = new StaysRS();

        $stayRS->idName->setNewVal($idGuest);
        $stayRS->idRoom->setNewVal($rm->getIdRoom());
        $stayRS->Checkin_Date->setNewVal(date("Y-m-d H:i:s", strtotime($checkinDate)));
        $stayRS->Span_Start_Date->setNewVal(date("Y-m-d H:i:s", strtotime($spanStartDate)));

        if ($expectedCO == '') {
            $expectedCO = $this->getExpectedDeparture();
        }

        if ($expectedCO == '') {
            throw new Hk_Exception_UnexpectedValue("The Expected Departure date is not set.");
        } else {
            $stayCoDt = new DateTime($expectedCO);
            $visitCoDt = new DateTime($this->getExpectedDeparture());
            if ($stayCoDt > $visitCoDt) {
                $this->visitRS->Expected_Departure->setNewVal($stayCoDt->format('Y-m-d 10:00:00'));
            }
        }

        $stayRS->Expected_Co_Date->setNewVal(date("Y-m-d 10:00:00", strtotime($expectedCO)));

        $stayRS->On_Leave->setNewVal($stayOnLeave);

        $stayRS->Status->setNewVal(VisitStatus::CheckedIn);
        $stayRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $this->stays[] = $stayRS;

    }

    public function checkin(PDO $dbh, $username) {

        // Verify data
        if ($this->resource->getIdResource() == 0 || $this->getIdRegistration() == 0 || $this->getArrivalDate() == "" || $this->getExpectedDeparture() == "" || count($this->stays) < 1) {
            throw new Hk_Exception_UnexpectedValue("Bad or missing data when trying to save a new Visit.");
        }

        $this->visitRS->Status->setNewVal(VisitStatus::CheckedIn);
        $this->visitRS->idResource->setNewVal($this->resource->getIdResource());

        $this->updateVisitRecord($dbh, $username);

        // Save Stays
        $this->checkInStays($dbh, $username);

        return TRUE;
    }

    protected function checkInStays(PDO $dbh, $username) {

        foreach ($this->stays as $stayRS) {

            if ($stayRS->idStays->getStoredVal() == 0) {

                $stayRS->idVisit->setNewVal($this->getIdVisit());
                $stayRS->Visit_Span->setNewVal($this->getSpan());
                $stayRS->Updated_By->setNewVal($username);

                $idStays = EditRS::insert($dbh, $stayRS);
                $stayRS->idStays->setNewVal($idStays);

                $logText = VisitLog::getInsertText($stayRS);
                VisitLog::logStay($dbh, $this->getIdVisit(), $this->getSpan(), $stayRS->idRoom->getNewVal(), $idStays, $stayRS->idName->getNewVal(), $this->getIdRegistration(), $logText, "insert", $username);
            }

            EditRS::updateStoredVals($stayRS);
        }
    }

    public function changeRooms(PDO $dbh, Resource $resc, $uname, \DateTime $chgDT, $isAdmin, $depDisposition) {

        $rtnMessage = '';

        if ($resc->isNewResource()) {
            throw new Hk_Exception_Runtime('Invalid Resource supplied to visit->changeRooms.');
        }

        if ($this->visitRS->idResource->getStoredVal() == $resc->getIdResource()) {
            return "Error - Change Rooms Failed: new room = old room.  ";
        }

        if (count($this->stays) > $resc->getMaxOccupants()) {
            return "Error - Change Rooms failed: New room too small, too many occupants.  ";
        }

        // Change date cannot be earlier than span start date.
        $spanStartDT = new DateTime($this->visitRS->Span_Start->getStoredVal());
        $spanStartDT->setTime(0,0,0);
        if ($chgDT < $spanStartDT) {
            return "Error - Change Rooms failed: Change Date is prior to Visit Span start date.  ";
        }

        $expDepDT = new DateTime($this->getExpectedDeparture());
        $expDepDT->setTime(10, 0, 0);
        $now = new DateTime();
        $now->setTime(10, 0, 0);

        if ($expDepDT < $now) {
            $expDepDT = $now->add(new DateInterval('P1D'));
        }


        // Reservation
        $reserv = Reservation_1::instantiateFromIdReserv($dbh, $this->getReservationId());

        // Room Available
        if ($reserv->isNew() === FALSE) {

            $rescOpen = $reserv->isResourceOpen($dbh, $resc->getIdResource(), $chgDT->format('Y-m-d H:i:s'), $expDepDT->format('Y-m-d H:i:s'), count($this->stays), array('room','rmtroom','part'), FALSE, $isAdmin);

            if ($rescOpen) {
                $reserv->setIdResource($resc->getIdResource());
                $reserv->saveReservation($dbh, $this->getIdRegistration(), $uname);
            } else {
                return "Error - Change Rooms failed: The new room is busy or missing necessary attributes.  ";
            }
        }


        // get rooms
        $oldRoom = "";
        $rooms = array();
        if ($this->getResource($dbh) != NULL) {
            $oldRoom = $this->resource->getTitle();
            $rooms = $this->resource->getRooms();
        }

        // check room size
        $numGuests = 0;
        foreach ($this->stays as $stayRS) {

            if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedIn) {
                $numGuests++;
            }
        }

        $rm = $this->resource->testAllocateRoom($numGuests, $this->overrideMaxOccupants);
        if ($rm === FALSE) {
            return 'The Room is too small.  Change rooms failed.  ';
        }

        // if room change date = span start date, just replace the room in the visit record.
        $roomChangeDate = new DateTime($chgDT->format('Y-m-d'));
        $roomChangeDate->setTime(0,0,0);

        if ($spanStartDT == $roomChangeDate) {
            // Just replace the room
            $this->setIdResource($resc->getIdResource());
            $this->resource = $resc;

            $cnt = $this->updateVisitRecord($dbh, $uname);

            if ($cnt > 0) {

                foreach ($this->stays as $stayRS) {

                    if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedIn) {

                        // update current stay
                        $rm = $this->resource->allocateRoom(1, $this->overrideMaxOccupants);

                        if (is_null($rm)) {
                            continue;
                        }

                        $stayRS->idRoom->setNewVal($rm->getIdRoom());
                        $stayRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
                        $stayRS->Updated_By->setNewVal($uname);

                        EditRS::update($dbh, $stayRS, array($stayRS->idStays));
                        $logText = VisitLog::getUpdateText($stayRS);
                        VisitLog::logStay($dbh, $this->getIdVisit(), $stayRS->Visit_Span->getStoredVal(), $stayRS->idRoom->getStoredVal(), $stayRS->idStays->getStoredVal(), $stayRS->idName->getStoredVal(), $this->visitRS->idRegistration->getStoredVal(), $logText, "update", $uname);

                        EditRS::updateStoredVals($stayRS);
                    }
                }

                $rtnMessage .= 'Guests Replaced Rooms.  ';
            }
        } else {

            // Change rooms on date given.
            $this->createNewSpan($dbh, $resc, VisitStatus::NewSpan, $this->getRateCategory(), $this->getIdRoomRate(), $this->getPledgedRate(), $this->visitRS->Expected_Rate->getStoredVal(), $uname, $chgDT->format('Y-m-d H:i:s'));
            $rtnMessage .= 'Guests Changed Rooms.  ';

            // Change date today?
            if ($chgDT->format('Y-m-d') == date('Y-m-d')) {
                foreach ($rooms as $r) {
                    $r->putDirty();
                    $r->saveRoom($dbh, $uname, TRUE);
                }
            }
        }

        if ($depDisposition != '') {
            $rtnMessage .= $this->depositDisposition($dbh, $depDisposition);
        }

        $uS = Session::getInstance();

            // Send email
        if (is_null($this->getResource($dbh)) === FALSE && $uS->noreplyAddr != '' && $uS->adminEmailAddr != '') {

            try {
                // Get the site configuration object
                $config = new Config_Lite(ciCFG_FILE);
                $mail = prepareEmail($config);

                $mail->From = $uS->noreplyAddr;
                $mail->FromName = $uS->siteName;
                $mail->addReplyTo($uS->noreplyAddr, $uS->siteName);

                $tos = explode(',', $uS->adminEmailAddr);
                foreach ($tos as $t) {
                    $to = filter_var($t, FILTER_SANITIZE_EMAIL);
                    if ($to !== FALSE && $to != '') {
                        $mail->addAddress($to);
                    }
                }

                $mail->isHTML(true);

                $mail->Subject = "Change rooms from " . $oldRoom . " to " . $this->resource->getTitle() . " by " . $uS->username;
                $mail->msgHTML("Room change Date: " . $chgDT->format('g:ia D M jS, Y') . "<br />");

                if ($mail->send() === FALSE) {
                    $rtnMessage .= $mail->ErrorInfo;
                }

            } catch (phpmailerException $ex) {
                $rtnMessage .= 'Email Failed.  ' . $ex->errorMessage();
            }
        }

        return $rtnMessage;
    }

    public static function replaceRoomRate(PDO $dbh, \VisitRs $visitRs, $newRateCategory, $pledgedRate, $rateAdjust, $uname) {

        $uS = Session::getInstance();

        // Get the idRoomRate
        $pm = PriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
        $rateRs = $pm->getCategoryRateRs(0, $newRateCategory);


        if ($visitRs->idReservation->getStoredVal() > 0) {

            $resv = Reservation_1::instantiateFromIdReserv($dbh, $visitRs->idReservation->getStoredVal());

            $resv->setFixedRoomRate($pledgedRate);
            $resv->setRateAdjust($rateAdjust);
            $resv->setRoomRateCategory($newRateCategory);
            $resv->setIdRoomRate($rateRs->idRoom_rate->getStoredVal());

            $resv->saveReservation($dbh, $resv->getIdRegistration(), $uname);
        }

        $visitRs->Pledged_Rate->setNewVal($pledgedRate);
        $visitRs->Rate_Category->setNewVal($newRateCategory);
        $visitRs->Expected_Rate->setNewVal($rateAdjust);
        $visitRs->idRoom_rate->setNewVal($rateRs->idRoom_rate->getStoredVal());

        $visitRs->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $visitRs->Updated_By->setNewVal($uname);

        $upCtr = EditRS::update($dbh, $visitRs, array($visitRs->idVisit, $visitRs->Span));

        if ($upCtr > 0) {
            $logText = VisitLog::getUpdateText($visitRs);

            EditRS::updateStoredVals($visitRs);
            VisitLog::logVisit($dbh, $visitRs->idVisit->getStoredVal(), $visitRs->Span->getStoredVal(), $visitRs->idResource->getStoredVal(), $visitRs->idRegistration->getStoredVal(), $logText, "update", $uname);

        }

        if ($upCtr > 0) {

            $reply = "Room Rate Replaced.  ";

        } else {

            $reply = "";
        }

        return $reply;
    }

    public function changePledgedRate(PDO $dbh, $newRateCategory, $pledgedRate, $rateAdjust, $uname, \DateTime $chgDT, $useRateGlide = TRUE, $stayOnLeave = 0) {

        if ($this->getResource($dbh) != NULL) {


            $uS = Session::getInstance();

            // Get the idRoomRate
            $pm = PriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
            $rateRs = $pm->getCategoryRateRs(0, $newRateCategory);

            // Temporarily override max occupants, as the room will seem to double.
            $tempOverrideMaxOcc = $this->overrideMaxOccupants;
            $this->overrideMaxOccupants = true;

            $this->createNewSpan($dbh, $this->resource, VisitStatus::ChangeRate, $newRateCategory, $rateRs->idRoom_rate->getStoredVal(), $pledgedRate, $rateAdjust, $uname, $chgDT->format('Y-m-d H:i:s'), $useRateGlide, $stayOnLeave, $rateRs->idRoom_rate->getStoredVal());
            $this->overrideMaxOccupants = $tempOverrideMaxOcc;


            // change reservation entry
            $resv = Reservation_1::instantiateFromIdReserv($dbh, $this->visitRS->idReservation->getStoredVal());

            if ($resv->isNew() === FALSE) {
                $resv->setFixedRoomRate($pledgedRate);
                $resv->setRateAdjust($rateAdjust);
                $resv->setRoomRateCategory($newRateCategory);
                $resv->setIdRoomRate($rateRs->idRoom_rate->getStoredVal());

                $resv->saveReservation($dbh, $resv->getIdRegistration(), $uname);
            }

            return "Room Rate Changed.  ";
        }
    }

    protected function createNewSpan(PDO $dbh, Resource $resc, $visitStatus, $newRateCategory, $newRateId, $pledgedRate, $rateAdjust, $uname, $changeDate, $useRateGlide = TRUE, $stayOnLeave = 0, $idRoomRate = 0) {

        $glideDays = 0;

        if ($useRateGlide) {
            // Calculate days of old span
            $stDT = new DateTime($this->visitRS->Span_Start->getStoredVal());
            $stDT->setTime(0, 0, 0);
            $endDT = new DateTime($changeDate);
            $endDT->setTime(0, 0, 0);
            $glideDays = $this->visitRS->Rate_Glide_Credit->getStoredVal() + $endDT->diff($stDT, TRUE)->days;
        }

        $this->visitRS->Span_End->setNewVal($changeDate);
        $this->visitRS->Status->setNewVal($visitStatus);

        $cnt = $this->updateVisitRecord($dbh, $uname);

        if ($cnt == 0) {
            throw new Hk_Exception_Runtime('Visit Span update failed.');
        }


        // set all new values for visit rs
        foreach ($this->visitRS as $p) {
            if (is_a($p, "DB_Field")) {
                $p->setNewVal($p->getStoredVal());
            }
        }

        $this->resource = $resc;

        // Create new visit span
        $newSpan = intval($this->visitRS->Span->getStoredVal(), 10) + 1;

        $this->visitRS->idResource->setNewVal($resc->getIdResource());
        $this->visitRS->Span->setNewVal($newSpan);
        $this->visitRS->Span_End->setNewVal('');
        $this->visitRS->Span_Start->setNewVal($changeDate);
        $this->visitRS->Status->setNewVal(VisitStatus::CheckedIn);
        $this->visitRS->Key_Dep_Disposition->setNewVal('');
        $this->visitRS->Pledged_Rate->setNewVal($pledgedRate);
        $this->visitRS->Rate_Category->setNewVal($newRateCategory);
        $this->visitRS->idRoom_rate->setNewVal($newRateId);
        $this->visitRS->Expected_Rate->setNewVal($rateAdjust);
        $this->visitRS->Rate_Glide_Credit->setNewVal($glideDays);

        if ($idRoomRate > 0) {
            $this->visitRS->idRoom_rate->setNewVal($idRoomRate);
        }


        $idVisit = EditRS::insert($dbh, $this->visitRS);

        if ($idVisit == 0) {
            throw new Hk_Exception_Runtime('Visit insert failed.   ');
        }

        $logTexti = VisitLog::getInsertText($this->visitRS);
        VisitLog::logVisit($dbh, $this->getIdVisit(), $newSpan, $resc->getIdResource(), $this->visitRS->idRegistration->getStoredVal(), $logTexti, "insert", '');

        EditRS::updateStoredVals($this->visitRS);

        $this->replaceStays($dbh, $visitStatus, $changeDate, $uname, $stayOnLeave);

    }

    public function replaceStays(\PDO $dbh, $visitStatus, $changeDate, $uname, $stayOnLeave = 0) {

        $oldStays = $this->stays;
        $this->stays = array();

        $this->getResource($dbh);

        foreach ($oldStays as $stayRS) {

            if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedIn) {

                // end current stay
                $stayRS->Status->setNewVal($visitStatus);
                $stayRS->Span_End_Date->setNewVal($changeDate);
                $stayRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
                $stayRS->Updated_By->setNewVal($uname);

                EditRS::update($dbh, $stayRS, array($stayRS->idStays));
                $logText = VisitLog::getUpdateText($stayRS);
                VisitLog::logStay($dbh, $this->getIdVisit(), $stayRS->Visit_Span->getStoredVal(), $stayRS->idRoom->getStoredVal(), $stayRS->idStays->getStoredVal(), $stayRS->idName->getStoredVal(), $this->visitRS->idRegistration->getStoredVal(), $logText, "update", $uname);

                EditRS::updateStoredVals($stayRS);
                $this->addGuestStay($stayRS->idName->getStoredVal(), $stayRS->Checkin_Date->getStoredVal(), $changeDate, $stayRS->Expected_Co_Date->getStoredVal(), $stayOnLeave);
            }
        }

        $this->checkInStays($dbh, $uname);

    }

    public function checkOutGuest(PDO $dbh, $idGuest, $dateDeparted = "", $notes = "", $sendEmail = TRUE, $newDepositDisposition = '') {

        $stayRS = NULL;

        // Guest must be already checked in
        foreach ($this->stays as $sRS) {

            if ($sRS->Status->getStoredVal() == VisitStatus::CheckedIn && $sRS->idName->getStoredVal() == $idGuest) {
                $stayRS = $sRS;
                break;
            }
        }


        if (is_null($stayRS) || $stayRS->idStays->getStoredVal() == 0) {
            return "Checkout Failed: The guest was not checked in.  ";
        }

        $uS = Session::getInstance();

        // Check out date
        if ($dateDeparted == "") {

            $dateDepartedDT = new DateTime();
            $depDate = new DateTime();

        } else {

            $dateDepartedDT = setTimeZone($uS, $dateDeparted);
            $depDate = setTimeZone($uS, $dateDeparted);

        }

        $depDate->setTime(0, 0, 0);
        $today = new DateTime();
        $today->setTime(0, 0, 0);

        if ($depDate > $today) {
            return "Checkout failed:  Cannot checkout in the future.";
        }

        // Earlier than checkin date
        $ciDate = new DateTime($stayRS->Checkin_Date->getStoredVal());
        $ciDate->setTime(0, 0, 0);

        if ($depDate < $ciDate) {
            return "Checkout Failed: The checkout date was before the checkin date.  ";
        }

        // Earliser than span start date (see mod below to remove this)
        $stDate = new DateTime($stayRS->Span_Start_Date->getStoredVal());
        $stDate->setTime(0, 0, 0);

        if ($depDate < $stDate) {
            return "Checkout Failed: The checkout date was before the span start date.  ";
        }


        // Check out
        $stayRS->Status->setNewVal(VisitStatus::CheckedOut);
        $stayRS->Checkout_Date->setNewVal($dateDepartedDT->format("Y-m-d H:i:s"));
        $stayRS->Span_End_Date->setNewVal($dateDepartedDT->format("Y-m-d H:i:s"));
        $stayRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $stayRS->Updated_By->setNewVal($uS->username);

        EditRS::update($dbh, $stayRS, array($stayRS->idStays));

        $logText = VisitLog::getUpdateText($stayRS);
        VisitLog::logStay($dbh, $stayRS->idVisit->getStoredVal(), $stayRS->Visit_Span->getStoredVal(), $stayRS->idRoom->getStoredVal(), $stayRS->idStays->getStoredVal(), $idGuest, $this->visitRS->idRegistration->getStoredVal(), $logText, "update", $uS->username);

        EditRS::updateStoredVals($stayRS);


        $msg = $this->checkStaysEndVisit($dbh, $uS->username, $dateDepartedDT, $sendEmail, $newDepositDisposition);


        // prepare email message if needed
        try {
            if ($sendEmail && $this->getVisitStatus() != VisitStatus::CheckedOut && $uS->adminEmailAddr != ''  && $uS->noreplyAddr != '') {

                // Get room name
                $roomTitle = 'Unknown';
                if (is_null($this->getResource($dbh)) === FALSE) {
                    $roomTitle = $this->resource->getTitle();
                }


                // Get guest names
                $query = "Select Name_First, Name_Last
                    from `name` where idName = :vst; ";
                $stmt = $dbh->prepare($query, array(PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY));
                $stmt->execute(array(':vst' => $idGuest));
                $gsts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $gMarkup = '<html><body><h3>Guest Checkout</h3><p>Departure Date: ' . date('g:ia D M jS, Y', strtotime($stayRS->Checkout_Date->getStoredVal())) . ';  from ' . $roomTitle . '</p>';

                if (count($gsts) > 0) {
                    $tbl = new HTMLTable();
                    $tbl->addHeaderTr(HTMLTable::makeTh('Id') . HTMLTable::makeTh('Guest Name') . HTMLTable::makeTh('Checked-In') . HTMLTable::makeTh('Checked-Out'));

                    foreach ($gsts as $g) {
                        $tbl->addBodyTr(HTMLTable::makeTd($idGuest) . HTMLTable::makeTd($g['Name_First'] . ' ' . $g['Name_Last'])
                                . HTMLTable::makeTd(date('g:ia D M jS, Y', strtotime($stayRS->Checkin_Date->getStoredVal())))
                                . HTMLTable::makeTd(date('g:ia D M jS, Y', strtotime($stayRS->Checkout_Date->getStoredVal()))));
                    }

                    $tbl->addBodyTr(HTMLTable::makeTd('Return Date') . HTMLTable::makeTd($this->getReturnDate() == '' ? '' : date('D M jS, Y', strtotime($this->getReturnDate())), array('colspan' => '3')));
                    $tbl->addBodyTr(HTMLTable::makeTd(HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('h4', 'Notes') . nl2br($notes)), array('colspan' => '4')));
                    $gMarkup .= $tbl->generateMarkup();
                }

                // Finalize body
                $gMarkup .= '</body></html>';

                $subj = "Check-Out from " . $roomTitle . " by " . $uS->username . ".";

                // Get the site configuration object
                $config = new Config_Lite(ciCFG_FILE);

                // Send email
                $mail = prepareEmail($config);

                $mail->From = $uS->noreplyAddr;
                $mail->FromName = $uS->siteName;
                $mail->addReplyTo($uS->noreplyAddr, $uS->siteName);

                $tos = explode(',', $uS->adminEmailAddr);
                foreach ($tos as $t) {
                    $to = filter_var($t, FILTER_SANITIZE_EMAIL);
                    if ($to !== FALSE && $to != '') {
                        $mail->addAddress($to);
                    }
                }

                $mail->isHTML(true);

                $mail->Subject = $subj;
                $mail->msgHTML($gMarkup);
                $mail->send();
            }
        } catch (Exception $ex) {
            // Do nothing.
            $msg .= $ex->getMessage();
        }

        return "Guest Id " . $idGuest . " checked out on " . $dateDepartedDT->format('m-d-Y') . ".  " . $msg;
    }

    protected function checkStaysEndVisit(\PDO $dbh, $username, DateTime $dateDeparted, $sendEmail = TRUE, $newDepositDisposition = '') {

        $msg = '';
        $uS = Session::getInstance();

        // Check each stay status
        foreach ($this->stays as $stayRS) {

            if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedIn) {
                return;
            }

            // Get the latest checkout date
            if ($stayRS->Span_End_Date->getStoredVal() != '') {

                $dt = new DateTime($stayRS->Span_End_Date->getStoredVal());

                if ($dt > $dateDeparted) {
                    $dateDeparted = $dt;
                }
            }
        }


        // Visit is done



        // Update visit record
        $this->visitRS->Actual_Departure->setNewVal($dateDeparted->format("Y-m-d H:i:s"));
        $this->visitRS->Span_End->setNewVal($dateDeparted->format("Y-m-d H:i:s"));
        $this->visitRS->Status->setNewVal(VisitStatus::CheckedOut);
        $this->visitRS->Key_Dep_Disposition->setNewVal($newDepositDisposition);

        $this->updateVisitRecord($dbh, $username);


        // Update resource cleaning status
        $resc = Resource::getResourceObj($dbh, $this->getidResource());
        $rooms = $resc->getRooms();

        $rmCleans = readGenLookupsPDO($dbh, 'Room_Cleaning_Days');

        foreach ($rooms as $r) {

            // Only if cleaning cycle is defined and > 0
            if (isset($rmCleans[$r->getCleaningCycleCode()]) && $rmCleans[$r->getCleaningCycleCode()][2] != '0') {
                $r->putTurnOver();
                $r->saveRoom($dbh, $username, TRUE);
            }
        }


        // Reservation?
        $reserv = Reservation_1::instantiateFromIdReserv($dbh, $this->getReservationId());
        if ($reserv->isNew() === FALSE) {
            // checkout reservation
            try {
                $reserv->checkOut($dbh, $dateDeparted->format("Y-m-d H:i:s"), $username);
            } catch (Hk_Exception_Runtime $hex) {
                $msg .= $hex->getMessage();
            }
        }


        // prepare email message
        try {
            if ($sendEmail && $uS->adminEmailAddr != '' && $uS->noreplyAddr != '') {
                // Get room name
                $roomTitle = 'Unknown';
                if (is_null($this->getResource($dbh)) === FALSE) {
                    $roomTitle = $this->resource->getTitle();
                }

                // Get room list
                $rooms = array();
                $stmt2 = $dbh->query("select idResource, Title from resource;");

                while ($rw = $stmt2->fetch(PDO::FETCH_ASSOC)) {
                    $rooms[$rw['idResource']] = $rw['Title'];
                }

                // fees transaction table
                //$feesMarkup = HTMLContainer::generateMarkup('div', Fees::createVisitFeesMarkup($dbh, $this->getIdVisit(), $rooms));

                // Get guest names
                $query = "Select n.idName, n.Name_First, n.Name_Last, s.Checkin_Date, s.Checkout_Date, p.Notes
                from stays s join `name` n on s.idName = n.idName
                left join name_guest ng on s.idName = ng.idName
                left join psg p on ng.idPsg = p.idPsg
                where s.idVisit = :vst and s.Status = :stat;";
                $stmt = $dbh->prepare($query);
                $stmt->execute(array(':vst' => $this->getIdVisit(), ':stat' => VisitStatus::CheckedOut));
                $gsts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $gMarkup = '<html><body><h3>Guest Checkout</h3><p>Departure Date: ' . $dateDeparted->format('g:ia D M jS, Y') . ';  from ' . $roomTitle . '</p>';

                if (count($gsts) > 0) {
                    $tbl = new HTMLTable();
                    $tbl->addHeaderTr(HTMLTable::makeTh('Id') . HTMLTable::makeTh('Guest Name') . HTMLTable::makeTh('Checked-In') . HTMLTable::makeTh('Checked-Out'));

                    foreach ($gsts as $g) {
                        $tbl->addBodyTr(HTMLTable::makeTd($g['idName']) . HTMLTable::makeTd($g['Name_First'] . ' ' . $g['Name_Last'])
                                . HTMLTable::makeTd(date('g:ia D M jS, Y', strtotime($g['Checkin_Date'])))
                                . HTMLTable::makeTd(date('g:ia D M jS, Y', strtotime($g['Checkout_Date']))));
                    }

                    $tbl->addBodyTr(HTMLTable::makeTd('Return Date') . HTMLTable::makeTd($this->getReturnDate() == '' ? '' : date('D M jS, Y', strtotime($this->getReturnDate())), array('colspan' => '3')));
                    $tbl->addBodyTr(HTMLTable::makeTd(HTMLContainer::generateMarkup('div', HTMLContainer::generateMarkup('h4', 'Notes') . nl2br($gsts[0]['Notes'])), array('colspan' => '4')));
                    $gMarkup .= $tbl->generateMarkup();
                }


                // Finalize body
                $gMarkup .= '</body></html>';

                $subj = "Visit audit report for room: " . $roomTitle . ".  Room is now empty.";



                // Get the site configuration object
                $config = new Config_Lite(ciCFG_FILE);

                // Send email
                $mail = prepareEmail($config);

                $mail->From = $uS->noreplyAddr;
                $mail->FromName = $uS->siteName;
                $mail->addReplyTo($uS->noreplyAddr, $uS->siteName);

                $tos = explode(',', $uS->adminEmailAddr);
                foreach ($tos as $t) {
                    $to = filter_var($t, FILTER_SANITIZE_EMAIL);
                    if ($to !== FALSE && $to != '') {
                        $mail->addAddress($to);
                    }
                }

                $mail->isHTML(true);

                $mail->Subject = $subj;

                $mail->msgHTML($gMarkup);
                $mail->send();
            }
        } catch (Exception $ex) {
            // Do nothing.
            $msg .= $ex->getMessage();
        }

        $msg .= "Visit Ended.  ";

        return $msg;
    }

    public function checkOutVisit(PDO $dbh, $dateDeparted = "", $keyDepositDisp = '', $sendEmail = TRUE) {
        $msg = "";

        // Check out date
        if ($dateDeparted == "") {
            $dateDepartedDT = new DateTime();
            $depDate = new DateTime();
            $depDate->setTime(0, 0, 0);
        } else {
            $dateDepartedDT = new DateTime($dateDeparted);
            $depDate = new DateTime($dateDeparted);
            $depDate->setTime(0, 0, 0);
        }

        $nowDate = new DateTime();
        $nowDate->setTime(0, 0, 0);

        if ($depDate > $nowDate) {
            return "Checkout failed:  Cannot checkout in the future.";
        }

        // CO date validity
        $stDate = new DateTime($this->getArrivalDate());

        if ($dateDepartedDT < $stDate) {
            return "But Checkout Failed: The checkout date is before the checkin date.  ";
        }

        // Check out each stay
        foreach ($this->stays as $stayRS) {
            if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedIn) {
                $msg .= $this->checkOutGuest($dbh, $stayRS->idName->getStoredVal(), $dateDeparted, '', $sendEmail);
            }
        }
        return $msg;
    }

    /**
     * Compares each guest date in array $guestDates with the visit Expected Departure date, and updates it accordingly.  Also forces any reservations to move.
     *
     * @param \PDO $dbh
     * @param array $guestDates String dates indexed by idGuest
     * @param int $maxExpected The administrative limit on the number of days forward
     * @param \DateTimeZone $tz
     * @param type $uname
     * @return string
     */
    public function changeExpectedCheckoutDates(\PDO $dbh, array $guestDates, $maxExpected, $uname) {

        if ($this->getVisitStatus() != VisitStatus::CheckedIn) {
            return array('message' => '');
        }

        $isChanged = FALSE;
        $rtnMsg = '';
        $nowDT = new DateTime();
        $nowDT->setTime(0, 0, 0);


        // Init the latest departure date for the visit
        $lastDepartureDT = new DateTime($this->getArrivalDate());
        $departureDateUpdated = FALSE;

        foreach ($this->stays as $stayRS) {

            $guestId = $stayRS->idName->getStoredVal();

            $ecoDT = new DateTime($stayRS->Expected_Co_Date->getStoredVal());
            $ecoDT->setTime(0, 0, 0);

            // Get the new date
            if (isset($guestDates[$guestId])) {

                $coDate = filter_var($guestDates[$guestId], FILTER_SANITIZE_STRING);

                // no value set?
                if ($coDate == '') {
                    continue;
                }

                try {
                    $coDT = setTimeZone(NULL, $coDate);
                    $coDT->setTime(0, 0, 0);
                } catch (Exception $ex) {
                    $rtnMsg .= "Something wrong with the Expected Checkout Date: " . $coDate;
                    continue;
                }

                if ($ecoDT == $coDT) {

                    if ($ecoDT > $lastDepartureDT) {
                        $lastDepartureDT = $ecoDT;
                    }

                    Continue;
                }

                $ecoDT = new DateTime($coDT->format('Y-m-d 00:00:00'));

            } else {
                continue;
            }


            // Only if trying to set a new expected checkout date
            if ($ecoDT < $nowDT && isset($guestDates[$guestId])) {
                $rtnMsg .= "Expected Checkout date is earlier than today.  ";
                continue;
            }

            // make span end date
            $spnEndDT = new DateTime($stayRS->Span_Start_Date->getStoredVal());
            $spnEndDT->setTime(0, 0, 0);

            // Earlier than check in date?
            if ($ecoDT < $spnEndDT) {
                $rtnMsg .= "Expected Checkout date cannot be earlier than the Checkin date.  ";
                continue;
            }

            if ($nowDT->diff($ecoDT)->days > $maxExpected) {
                $rtnMsg .= "Expected Checkout date cannot be beyond " . $maxExpected . " days from today.  The max days setting can be changed.";
                continue;
            }

            if ($ecoDT > $lastDepartureDT) {
                $lastDepartureDT = $ecoDT;
                $departureDateUpdated = TRUE;
            }


            $stayRS->Expected_Co_Date->setNewVal($ecoDT->format('Y-m-d 10:00:00'));
            $stayRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $stayRS->Updated_By->setNewVal($uname);

            $cnt = EditRS::update($dbh, $stayRS, array($stayRS->idStays));

            if ($cnt > 0) {

                $logText = VisitLog::getUpdateText($stayRS);
                VisitLog::logStay($dbh, $this->getIdVisit(), $this->getSpan(), $stayRS->idRoom->getStoredVal(), $stayRS->idStays->getStoredVal(), $stayRS->idName->getStoredVal(), $this->getIdRegistration(), $logText, "update", $uname);
                EditRS::updateStoredVals($stayRS);
                $isChanged = TRUE;
            }

        }


        if ($departureDateUpdated) {

            // Update visit exepected departure
            $this->visitRS->Expected_Departure->setNewVal($lastDepartureDT->format('Y-m-d 10:00:00'));
            $this->visitRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
            $this->visitRS->Updated_By->setNewVal($uname);

            $uctr = $this->updateVisitRecord($dbh, $uname);


            if ($uctr > 0) {

                $rtnMsg = 'Visit expected departure date(s) changed.  ';

                // Update reservation expected departure
                $resv = Reservation_1::instantiateFromIdReserv($dbh, $this->getReservationId());
                $resv->setExpectedDeparture($lastDepartureDT->format('Y-m-d 10:00:00'));
                $resv->saveReservation($dbh, $resv->getIdRegistration(), $uname);

                // Move other reservations to alternative rooms
                $rtnMsg .= ReservationSvcs::moveResvAway($dbh, new DateTime($this->getArrivalDate()), $lastDepartureDT, $this->getidResource(), $uname);
            }

            return array('message'=>$rtnMsg, 'isChanged' => $isChanged);
        }

        return array('message'=>$rtnMsg);
    }

    public function endLeave(\PDO $dbh, $returning, $extendReturnDate, $newDisposition) {

        $reply = '';
        $uS = Session::getInstance();

        if ($extendReturnDate == '') {
            $extendReturnDate = date('Y-m-d');
        }

        $retDT = setTimeZone(NULL, $extendReturnDate);
        $retDT->setTime(0, 0, 0);
        $now = new DateTime();
        $now->setTime(0, 0, 0);
        $timeNow = date('H:i:s');

        $dt = $retDT->format('Y-m-d');
        $coDT = new DateTime($dt . ' ' . $timeNow);


        if ($returning === FALSE) {
            // end visit

            if ($retDT > $now) {
                return 'Cannot checkout in the future.  ';
            }

            $reply .= $this->checkOutVisit($dbh, $coDT->format('Y-m-d H:i:s'), $newDisposition);

            // Delete any On-leave records.
            $vol = new Visit_onLeaveRS();
            $vol->idVisit->setStoredVal($this->getIdVisit());
            EditRS::delete($dbh, $vol, array($vol->idVisit));

        } else {
            // Return

            if ($retDT > $now) {
                return 'Cannot return from leave in the future.  ';
            }

            // Was the rate changed for the leave?
            $vol = new Visit_onLeaveRS();
            $vol->idVisit->setStoredVal($this->getIdVisit());
            $rows = EditRS::select($dbh, $vol, array($vol->idVisit));

            if (count($rows) > 0) {
                // Rate was changed
                EditRS::loadRow($rows[0], $vol);

                $reply .= $this->changePledgedRate($dbh, $vol->Rate_Category->getStoredVal(), $vol->Pledged_Rate->getStoredVal(), $vol->Rate_Adjust->getStoredVal(), $uS->username, $retDT, ($uS->RateGlideExtend > 0 ? TRUE : FALSE), FALSE);

                EditRS::delete($dbh, $vol, array($vol->idVisit));

            } else {
                // Check out all guest, check back in.
                $this->replaceStays($dbh, VisitStatus::CheckedOut, $retDT->format('Y-m-d H:i:s'), $uS->username, FALSE);

            }
        }

        return $reply;

    }

    public function beginLeave(\PDO $dbh, $extendStartDate, $extDays, $noCharge) {
        // Extend the visit

        $uS = Session::getInstance();
        $reply = '';

        // Get Start date
        $startDateStr = date('Y-m-d');

        // check the extend days desired
        if ($extDays > $uS->EmptyExtendLimit) {
            $extDays = $uS->EmptyExtendLimit;
        }

        if ($extDays < 1) {
            return;
        }


        $cDT = setTimeZone(NULL, $startDateStr);
        $dt = $cDT->format('Y-m-d');
        $timeNow = date('H:i:s');

        $coDT = new DateTime($dt . ' ' . $timeNow);

        if ($noCharge) {

            // Save current rate info
            $vol = new Visit_onLeaveRS();
            $vol->idVisit->setStoredVal($this->getIdVisit());
            $rows = EditRS::select($dbh, $vol, array($vol->idVisit));

            if (count($rows) > 0) {
                EditRS::loadRow($rows[0], $vol);
            } else {
                $vol = new Visit_onLeaveRS();
                $vol->idVisit->setNewVal($this->getIdVisit());
            }

            $vol->Span->setNewVal($this->getSpan());
            $vol->Rate_Adjust->setNewVal($this->getRateAdjust());
            $vol->Rate_Category->setNewVal($this->getRateCategory());
            $vol->idRoom_rate->setNewVal($this->getIdRoomRate());
            $vol->Pledged_Rate->setNewVal($this->getPledgedRate());


            if (count($rows) > 0) {
                 EditRS::update($dbh, $vol, array($vol->idVisit));
            } else {
                 EditRS::insert($dbh, $vol);
            }

            // Change rate and trigger OnLeave.
            $reply .= $this->changePledgedRate($dbh, RoomRateCategorys::Fixed_Rate_Category, 0, 0, $uS->username, $coDT, ($uS->RateGlideExtend > 0 ? TRUE : FALSE), $extDays);
            $reply .= 'Guests on Leave.  ';

        } else {
            // continue with current room rate.

            // Remove any previous visit
            $vol = new Visit_onLeaveRS();
            $vol->idVisit->setStoredVal($this->getIdVisit());
            EditRS::delete($dbh, $vol, array($vol->idVisit));

            // Check out all guest, check back in with OnLeave set.
            $this->replaceStays($dbh, VisitStatus::CheckedOut, $coDT->format('Y-m-d H:i:s'), $uS->username, $extDays);
            $reply .= 'Guests on Leave.  ';
        }

        return $reply;

    }

    public static function loadStaysStatic(PDO $dbh, $idVisit, $span, $statusFilter = VisitStatus::CheckedIn) {

        $stays = array();

        if ($idVisit !== 0) {
            $stayRS = new StaysRS();
            $stayRS->idVisit->setStoredVal($idVisit);
            $stayRS->Visit_Span->setStoredVal($span);
            $stayRS->Status->setStoredVal($statusFilter);
            $rows = EditRS::select($dbh, $stayRS, array($stayRS->idVisit, $stayRS->Visit_Span, $stayRS->Status));

            foreach ($rows as $r) {

                $stayRS = new StaysRS();
                EditRS::loadRow($r, $stayRS);

                $stays[] = $stayRS;

            }
        }

        return $stays;
    }

    public function loadStays(PDO $dbh, $statusFilter = VisitStatus::CheckedIn) {

        unset($this->stays);
        $this->stays = self::loadStaysStatic($dbh, $this->getIdVisit(), $this->getSpan(), $statusFilter);

    }

    public function getIdRegistration() {
        return $this->visitRS->idRegistration->getStoredVal();
    }

    public function getRateGlideCredit() {
        return $this->visitRS->Rate_Glide_Credit->getStoredVal();
    }

    public function setRateGlideCredit($v) {
        $this->visitRS->Rate_Glide_Credit->setNewVal(intval($v, 10));
    }

    public function isNew() {
        return $this->newVisit;
    }

    public function setOverrideMaxOccupancy($val) {
        if ($val === TRUE) {
            $this->overrideMaxOccupants = TRUE;
        } else {
            $this->overrideMaxOccupants = FALSE;
        }
    }

    public function getResource($dbh = null) {
        if (is_null($this->resource) || is_a($this->resource, 'Resource') === FALSE) {
            if ($this->visitRS->idResource->getStoredVal() > 0 && is_a($dbh, 'PDO')) {
                $this->resource = Resource::getResourceObj($dbh, $this->visitRS->idResource->getStoredVal());
                return $this->resource;
            }
        } else {
            return $this->resource;
        }
        return null;
    }

    public function getidResource() {
        if (is_null($this->resource)) {
            return $this->visitRS->idResource->getStoredVal();
        } else {
            return $this->resource->getIdResource();
        }
    }

    public function setIdResource($v) {
        $this->visitRS->idResource->setNewVal($v);
    }

    public function getIdVisit() {
        return $this->visitRS->idVisit->getStoredVal();
    }

    public function getSpan() {
        return $this->visitRS->Span->getStoredVal();
    }

    public function getidHospital_stay() {
        return $this->visitRS->idHospital_stay->getStoredVal();
    }

    public function getArrivalDate() {
        return $this->visitRS->Arrival_Date->getStoredVal();
    }

    public function getSpanStart() {
        return $this->visitRS->Span_Start->getStoredVal();
    }

    public function getActualDeparture() {
        return $this->visitRS->Actual_Departure->getStoredVal();
    }

    public function getExpectedDeparture() {
        return $this->visitRS->Expected_Departure->getStoredVal();
    }

    public function getPledgedRate() {
        return $this->visitRS->Pledged_Rate->getStoredVal();
    }

    public function setPledgedRate($pledgedRate) {
        $this->visitRS->Pledged_Rate->setNewVal($pledgedRate);
    }

    public function getIdRoomRate() {
        return $this->visitRS->idRoom_rate->getStoredVal();
    }

    public function setIdRoomRate($id) {
        $this->visitRS->idRoom_rate->setNewVal($id);
    }

    public function setRateCategory($strCategory) {
        $this->visitRS->Rate_Category->setNewVal($strCategory);
    }

    public function getRateCategory() {
        return $this->visitRS->Rate_Category->getStoredVal();
    }

    public function getRoomTitle(PDO $dbh) {

        if ($this->getIdResource() > 0) {

            $resourceRS = new ResourceRS();
            $resourceRS->idResource->setStoredVal($this->getIdResource());
            $rows = EditRS::select($dbh, $resourceRS, array($resourceRS->idResource));

            if (count($rows) == 1) {
                EditRS::loadRow($rows[0], $resourceRS);
                return $resourceRS->Title->getStoredVal();
            }
        }

        return '';
    }



    public function setNotes($notes, $username, $roomTitle = '') {
        $oldNotes = $this->getNotes();
        $this->visitRS->Notes->setNewVal($oldNotes . "\r\n" . date('m-d-Y') . ', visit ' . $this->getIdVisit() . '-' . $this->getSpan() . ', room ' . $roomTitle . ', ' . $username . ' - ' . $notes);
    }

    public function getNotes() {
        return is_null($this->visitRS->Notes->getStoredVal()) ? '' : $this->visitRS->Notes->getStoredVal();
    }

    public function setRateAdjust($v) {
        $this->visitRS->Expected_Rate->setNewVal($v);
    }

    public function getRateAdjust() {
        return $this->visitRS->Expected_Rate->getStoredVal();
    }

    public function setReturnDate($v) {
        $this->visitRS->Return_Date->setNewVal($v);
    }

    public function getReturnDate() {
        return $this->visitRS->Return_Date->getStoredVal();
    }

    public function setPrimaryGuestId($id) {
        $this->visitRS->idPrimaryGuest->setNewVal($id);
    }

    public function getPrimaryGuestId() {
        return $this->visitRS->idPrimaryGuest->getStoredVal();
    }

    public function setReservationId($id) {
        $this->visitRS->idReservation->setNewVal($id);
    }

    public function setIdHospital_stay($id) {
        $this->visitRS->idHospital_stay->setNewVal($id);
    }

    public function getReservationId() {
        return $this->visitRS->idReservation->getStoredVal();
    }

    public function getKeyDeposit() {
        return $this->visitRS->Key_Deposit->getStoredVal();
    }

    public function setKeyDeposit($v) {
        $this->visitRS->Key_Deposit->setNewVal($v);
    }

    public function setKeyDepDisposition($keyDepDisposition) {
        $this->visitRS->Key_Dep_Disposition->setNewVal($keyDepDisposition);
    }

    public function getKeyDepDisposition() {
        return $this->visitRS->Key_Dep_Disposition->getStoredVal();
    }

    public function getVisitStatus() {
        return $this->visitRS->Status->getStoredVal();
    }

    public function getExtPhoneInstalled() {
        return $this->visitRS->Ext_Phone_Installed->getStoredVal();
    }

    public function setExtPhoneInstalled() {
        $this->visitRS->Ext_Phone_Installed->setNewVal(1);
    }

}
