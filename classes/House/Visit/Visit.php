<?php

namespace HHK\House\Visit;

use HHK\Exception\RuntimeException;
use HHK\Payment\Invoice\Invoice;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\SysConst\{RoomRateCategories, VisitStatus};
use HHK\TableLog\VisitLog;
use HHK\Tables\EditRS;
use HHK\Tables\House\ResourceRS;
use HHK\Tables\Visit\{StaysRS, VisitRS, Visit_onLeaveRS};
use HHK\sec\Session;
use HHK\Exception\UnexpectedValueException;
use HHK\HTMLControls\HTMLContainer;
use HHK\HTMLControls\HTMLTable;
use HHK\House\Reservation\ReservationSvcs;
use HHK\House\Reservation\Reservation_1;
use HHK\House\Resource\AbstractResource;
use HHK\Tables\Fields\DB_Field;

/**
 * Visit.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
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
    protected $infoMessage = '';
    protected $errorMessage = '';

    /**
     *
     * @param \PDO $dbh
     * @param integer $idReg
     * @param integer $idVisit

     * @param Resource $resource
     * @throws RuntimeException::
     */
    function __construct(\PDO $dbh, $idReg, $idVisit, \DateTime $arrivalDT = NULL, \DateTime $departureDT = NULL, AbstractResource $resource = NULL, $userName = '', $span = -1, $forceNew = FALSE) {

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
            $nowDT = new \DateTime();
            $nowDT->setTime(0, 0, 0);

            if ($arrivalDT > $departureDT) {
                throw new RuntimeException('Silly human, the arrival date cannot be AFTER the departure date.  ');
            }

            $this->visitRS->Arrival_Date->setNewVal($arrivalDT->format('Y-m-d H:i:s'));
            $this->visitRS->Span_Start->setNewVal($arrivalDT->format('Y-m-d H:i:s'));
            $this->visitRS->Status->setNewVal(VisitStatus::Pending);

            $this->visitRS->Expected_Departure->setNewVal($departureDT->format('Y-m-d 10:00:00'));

            $idVisit = EditRS::insert($dbh, $this->visitRS);

            if ($idVisit == 0) {
                throw new RuntimeException('Visit insert failed. ');
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
     * @param \PDO $dbh
     * @param int $idReg
     * @param int $idVisit
     * @return VisitRs
     * @throws RuntimeException
     */
    protected function loadVisits(\PDO $dbh, $idReg, $idVisit, $span = -1, $forceNew = FALSE) {

        $uS = Session::getInstance();
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
                throw new RuntimeException("Visit record not found.");
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

            if (count($rows) > $uS->RoomsPerPatient) {

                throw new RuntimeException('More than ' . $uS->RoomsPerPatient . ' checked-in visits for this registration.');

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
            throw new RuntimeException("Visit not instantiated.");
        }

        return $visits;
    }

    public function updateVisitRecord(\PDO $dbh, $uname = '') {

        return self::updateVisitRecordStatic($dbh, $this->visitRS, $uname);
    }

    public static function updateVisitRecordStatic(\PDO $dbh, VisitRs $visitRS, $uname = '') {

        $visitRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $visitRS->Updated_By->setNewVal($uname);

        $upCtr = EditRS::update($dbh, $visitRS, array($visitRS->idVisit, $visitRS->Span));

        if ($upCtr > 0) {
            $logText = VisitLog::getUpdateText($visitRS);

            EditRS::updateStoredVals($visitRS);
            VisitLog::logVisit($dbh, $visitRS->idVisit->getStoredVal(), $visitRS->Span->getStoredVal(), $visitRS->idResource->getStoredVal(), $visitRS->idRegistration->getStoredVal(), $logText, "update", $uname);

        }

        return $upCtr;
    }

    public function addGuestStay($idGuest, $checkinDate, $stayStartDate, $expectedCO = '', $stayOnLeave = 0) {

        // If guest already has an active stay ...
        foreach ($this->stays as $sRS) {

            if (($sRS->Status->getStoredVal() == VisitStatus::CheckedIn || $sRS->Status->getNewVal() == VisitStatus::CheckedIn) &&
                    ($sRS->idName->getStoredVal() == $idGuest || $sRS->idName->getNewVal() == $idGuest)) {
                return;
            }
        }

        // Measure against visit span start date
        if ($this->visitRS->Span_Start->getStoredVal() != '') {

            $spanStartDT = new \DateTime($this->visitRS->Span_Start->getStoredVal());
            $ssDT = new \DateTime($stayStartDate);
            $spanStartDT->setTime(0, 0, 0);

            if ($ssDT < $spanStartDT) {
                throw new RuntimeException('Stay start date (' . $stayStartDate . ') is earlier than visit span start date (' . $spanStartDT->format('Y-m-d') . ').  ');
            }
        }

        // Check room size
        $rm = $this->resource->allocateRoom(1, $this->overrideMaxOccupants);
        if (is_null($rm)) {
            throw new RuntimeException('The Room is full.  ');
        }


        if ($expectedCO == '') {
            $expectedCO = $this->getExpectedDeparture();
        }

        if ($expectedCO == '') {
            throw new UnexpectedValueException("Set the Expected Departure date.");
        }

        $stayCoDt = new \DateTime($expectedCO);
        $visitCoDt = new \DateTime($this->getExpectedDeparture());

        if ($stayCoDt > $visitCoDt) {
            $this->visitRS->Expected_Departure->setNewVal($stayCoDt->format('Y-m-d 10:00:00'));
        }


        // Create a new stay in memory
        $stayRS = new StaysRS();

        $stayRS->idName->setNewVal($idGuest);
        $stayRS->idRoom->setNewVal($rm->getIdRoom());
        $stayRS->Checkin_Date->setNewVal(date("Y-m-d H:i:s", strtotime($checkinDate)));
        $stayRS->Span_Start_Date->setNewVal(date("Y-m-d H:i:s", strtotime($stayStartDate)));
        $stayRS->Expected_Co_Date->setNewVal($stayCoDt->format('Y-m-d 10:00:00'));

        $stayRS->On_Leave->setNewVal($stayOnLeave);

        $stayRS->Status->setNewVal(VisitStatus::CheckedIn);
        $stayRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $this->stays[] = $stayRS;

    }

    public function checkin(\PDO $dbh, $username) {

        // Verify data
        if ($this->resource->getIdResource() == 0 || $this->getIdRegistration() == 0 || $this->getArrivalDate() == "" || $this->getExpectedDeparture() == "" || count($this->stays) < 1) {
            throw new UnexpectedValueException("Bad or missing data when trying to save a new Visit.");
        }

        $this->visitRS->Status->setNewVal(VisitStatus::CheckedIn);
        $this->visitRS->idResource->setNewVal($this->resource->getIdResource());

        $this->updateVisitRecord($dbh, $username);

        // Save Stays
        $this->saveNewStays($dbh, $username);

        return TRUE;
    }

    protected function saveNewStays(\PDO $dbh, $username) {

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

    public function changeRooms(\PDO $dbh, AbstractResource $resc, $uname, \DateTime $chgDT, $isAdmin) {

        $uS = Session::getInstance();

        $rtnMessage = '';

        if ($resc->isNewResource()) {
            throw new RuntimeException('Invalid Resource supplied to visit->changeRooms.');
        }

        if ($this->visitRS->idResource->getStoredVal() == $resc->getIdResource()) {
            return "Error - Change Rooms Failed: new room = old room.  ";
        }

        if (count($this->stays) > $resc->getMaxOccupants()) {
            return "Error - Change Rooms failed: New room too small, too many occupants.  ";
        }

        // Change date cannot be earlier than span start date.
        $spanStartDT = new \DateTime($this->visitRS->Span_Start->getStoredVal());
        $spanStartDT->setTime(0,0,0);
        if ($chgDT < $spanStartDT) {
            return "Error - Change Rooms failed: Change Date is prior to Visit Span start date.  ";
        }

        $expDepDT = new \DateTime($this->getExpectedDeparture());
        $expDepDT->setTime(10, 0, 0);
        $now = new \DateTime();
        $now->setTime(10, 0, 0);

        if ($expDepDT < $now) {
            $expDepDT = $now->add(new \DateInterval('P1D'));
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

        // if room change date = span start date, just replace the room in the visit record.
        $roomChangeDate = new \DateTime($chgDT->format('Y-m-d'));
        $roomChangeDate->setTime(0,0,0);

        $houseKeepingEmail = $uS->HouseKeepingEmail;

        if ($spanStartDT == $roomChangeDate) {
            // Just replace the room
            $this->setIdResource($resc->getIdResource());
            $this->resource = $resc;

            $cnt = $this->updateVisitRecord($dbh, $uname);
            $houseKeepingEmail = '';  // Don't trigger housekeeping for replace room

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
            $this->createNewSpan($dbh, $resc, VisitStatus::NewSpan, $this->getRateCategory(), $this->getIdRoomRate(), $this->getPledgedRate(), $this->visitRS->Expected_Rate->getStoredVal(), $uname, $chgDT->format('Y-m-d H:i:s'), (intval($this->visitRS->Span->getStoredVal(), 10) + 1));
            $rtnMessage .= 'Guests Changed Rooms.  ';

            // Change date today?
            if ($chgDT->format('Y-m-d') == date('Y-m-d')) {
                foreach ($rooms as $r) {
                    $r->putDirty();
                    $r->saveRoom($dbh, $uname, TRUE);
                }
            }
        }

        // Send email
        if ($uS->NoReplyAddr != '' && ($uS->Guest_Track_Address != '' || $houseKeepingEmail != '')) {

            try {
                $mail = prepareEmail();

                $mail->From = $uS->NoReplyAddr;
                $mail->FromName = $uS->siteName;
                $mail->addReplyTo($uS->NoReplyAddr, $uS->siteName);

                $tos = array_merge(explode(',', $uS->Guest_Track_Address), explode(',', $uS->HouseKeepingEmail));

                foreach ($tos as $t) {
                    $to = filter_var(trim($t), FILTER_SANITIZE_EMAIL);
                    if ($to !== FALSE && $to != '') {
                        $mail->addAddress($to);
                    }
                }

                $mail->isHTML(true);

                $mail->Subject = "Change rooms from " . $oldRoom . " to " . $resc->getTitle() . " by " . $uS->username;
                $mail->msgHTML("Room change Date: " . $chgDT->format('g:ia D M jS, Y') . "<br />");

                if ($mail->send() === FALSE) {
                    $rtnMessage .= $mail->ErrorInfo;
                }

            } catch (\Exception $ex) {
                $rtnMessage .= 'Email Failed.  ' . $ex->errorMessage();
            }
        }

        return $rtnMessage;
    }

    public static function replaceRoomRate(\PDO $dbh, VisitRs $visitRs, $newRateCategory, $pledgedRate, $rateAdjust, $uname) {

        $uS = Session::getInstance();
        $reply = "";

        // Get the idRoomRate
        $pm = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
        $rateRs = $pm->getCategoryRateRs(0, $newRateCategory);


        $visitRs->Pledged_Rate->setNewVal($pledgedRate);
        $visitRs->Rate_Category->setNewVal($newRateCategory);
        $visitRs->Expected_Rate->setNewVal($rateAdjust);
        $visitRs->idRoom_rate->setNewVal($rateRs->idRoom_rate->getStoredVal());

        $upCtr = self::updateVisitRecordStatic($dbh, $visitRs, $uname);

        if ($upCtr > 0) {
            $reply = "Room Rate Replaced.  ";
        }


        if ($visitRs->Status->getStoredVal() == VisitStatus::CheckedIn || $visitRs->Status->getStoredVal() == VisitStatus::CheckedOut) {

            // This is the last span.
            if ($visitRs->idReservation->getStoredVal() > 0) {

                $resv = Reservation_1::instantiateFromIdReserv($dbh, $visitRs->idReservation->getStoredVal());

                $resv->setFixedRoomRate($pledgedRate);
                $resv->setRateAdjust($rateAdjust);
                $resv->setRoomRateCategory($newRateCategory);
                $resv->setIdRoomRate($rateRs->idRoom_rate->getStoredVal());

                $resv->saveReservation($dbh, $resv->getIdRegistration(), $uname);
            }

        } else if ($visitRs->Status->getStoredVal() != VisitStatus::ChangeRate) {

            // Check the remaining spans until a change rate
            $visRS = new VisitRs();
            $visRS->idVisit->setStoredVal($visitRs->idVisit->getStoredVal());
            $rows = EditRS::select($dbh, $visRS, Array($visRS->idVisit));

            $idSpan = $visitRs->Span->getStoredVal();

            foreach ($rows as $rs) {

                $vrs = new VisitRs();
                EditRS::loadRow($rs, $vrs);

                if ($vrs->Span->getStoredVal() > $idSpan && $vrs->Status->getStoredVal() != VisitStatus::ChangeRate) {

                    // Change the rate
                    $vrs->Pledged_Rate->setNewVal($pledgedRate);
                    $vrs->Rate_Category->setNewVal($newRateCategory);
                    $vrs->Expected_Rate->setNewVal($rateAdjust);
                    $vrs->idRoom_rate->setNewVal($rateRs->idRoom_rate->getStoredVal());

                    $upCtr = self::updateVisitRecordStatic($dbh, $vrs, $uname);
                    $idSpan = $vrs->Span->getStoredVal();

                } else if ($vrs->Span->getStoredVal() > $idSpan && $vrs->Status->getStoredVal() == VisitStatus::ChangeRate) {
                    break;
                }
            }

        }


        return $reply;
    }

    public function changePledgedRate(\PDO $dbh, $newRateCategory, $pledgedRate, $rateAdjust, $uname, \DateTime $chgDT, $useRateGlide = TRUE, $stayOnLeave = 0) {

        $uS = Session::getInstance();
        $this->getResource($dbh);

        // Get the idRoomRate
        $pm = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
        $rateRs = $pm->getCategoryRateRs(0, $newRateCategory);

        // Temporarily override max occupants, as the room will seem to double.
        $tempOverrideMaxOcc = $this->overrideMaxOccupants;
        $this->overrideMaxOccupants = true;

        $this->createNewSpan($dbh, $this->resource, VisitStatus::ChangeRate, $newRateCategory, $rateRs->idRoom_rate->getStoredVal(), $pledgedRate, $rateAdjust, $uname, $chgDT->format('Y-m-d H:i:s'), (intval($this->visitRS->Span->getStoredVal(), 10) + 1), $useRateGlide, $stayOnLeave);
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

    /**
     * Cloaes the old span with $visitStatus, and starts a new one with the same status as the replaced span.
     *
     * @param \PDO $dbh
     * @param Resource $resc
     * @param string $visitStatus
     * @param string $newRateCategory
     * @param integer $newRateId
     * @param float $pledgedRate
     * @param float $rateAdjust
     * @param string $uname
     * @param string $changeDate
     * @param integer $newSpan
     * @param boolean $useRateGlide
     * @param integer $stayOnLeave
     * @param integer $idRoomRate
     * @throws RuntimeException
     */
    protected function createNewSpan(\PDO $dbh, AbstractResource $resc, $visitStatus, $newRateCategory, $newRateId, $pledgedRate, $rateAdjust, $uname, $changeDate, $newSpan, $useRateGlide = TRUE, $stayOnLeave = 0) {

        $glideDays = 0;
        $this->stays = array();

        // Load all stays for this span
        $stayRs = new StaysRS();
        $stayRs->idVisit->setStoredVal($this->getIdVisit());
        $stayRs->Visit_Span->setStoredVal($this->getSpan());
        $stays = EditRS::select($dbh, $stayRs, array($stayRs->idVisit, $stayRs->Visit_Span));

        foreach ($stays as $s) {
            $sRs = new StaysRS();
            EditRS::loadRow($s, $sRs);
            $this->stays[] = $sRs;
        }

        if ($useRateGlide) {
            // Calculate days of old span
            $stDT = new \DateTime($this->visitRS->Span_Start->getStoredVal());
            $stDT->setTime(0, 0, 0);
            $endDT = new \DateTime($changeDate);
            $endDT->setTime(0, 0, 0);
            $glideDays = $this->visitRS->Rate_Glide_Credit->getStoredVal() + $endDT->diff($stDT, TRUE)->days;
        }

        // End old span
        $newSpanStatus = $this->visitRS->Status->getStoredVal();
        $newSpanEnd = $this->visitRS->Span_End->getStoredVal();
        $this->visitRS->Span_End->setNewVal($changeDate);
        $this->visitRS->Status->setNewVal($visitStatus);

        $this->updateVisitRecord($dbh, $uname);


        // set all new values for visit rs
        foreach ($this->visitRS as $p) {
            if ($p instanceof DB_Field) {
                $p->setNewVal($p->getStoredVal());
            }
        }

        $this->resource = $resc;

        // Create new visit span
        $this->visitRS->idResource->setNewVal($resc->getIdResource());
        $this->visitRS->Span->setNewVal($newSpan);
        $this->visitRS->Span_End->setNewVal($newSpanEnd);
        $this->visitRS->Span_Start->setNewVal($changeDate);
        $this->visitRS->Status->setNewVal($newSpanStatus);
        $this->visitRS->Pledged_Rate->setNewVal($pledgedRate);
        $this->visitRS->Rate_Category->setNewVal($newRateCategory);
        $this->visitRS->idRoom_rate->setNewVal($newRateId);
        $this->visitRS->Expected_Rate->setNewVal($rateAdjust);
        $this->visitRS->Rate_Glide_Credit->setNewVal($glideDays);
        $this->visitRS->Timestamp->setNewVal(date('Y-m-d H:i:s'));

        $idVisit = EditRS::insert($dbh, $this->visitRS);

        if ($idVisit == 0) {
            throw new RuntimeException('Visit insert failed.   ');
        }

        $logTexti = VisitLog::getInsertText($this->visitRS);
        VisitLog::logVisit($dbh, $this->getIdVisit(), $newSpan, $resc->getIdResource(), $this->visitRS->idRegistration->getStoredVal(), $logTexti, "insert", '');

        EditRS::updateStoredVals($this->visitRS);

        $this->replaceStays($dbh, $visitStatus, $newSpanStatus, $uname, $stayOnLeave);

    }

    protected function replaceStays(\PDO $dbh, $oldStayStatus, $newSpanStatus, $uname, $stayOnLeave = 0) {

        $oldStays = $this->stays;
        $this->stays = array();

        $this->getResource($dbh);
        $visitSpanStartDT = new \DateTime($this->visitRS->Span_Start->getStoredVal());
        $visitSpanStartDT->setTime(0, 0, 0);

        foreach ($oldStays as $stayRS) {

            $stayStartDT = new \DateTime($stayRS->Span_Start_Date->getStoredVal());
            $stayStartDT->setTime(0, 0, 0);

            $stayEndDT = NULL;
            if ($stayRS->Span_End_Date->getStoredVal() != '') {
                $stayEndDT = new \DateTime($stayRS->Span_End_Date->getStoredVal());
                $stayEndDT->setTime(0, 0, 0);
            }

            if ($stayStartDT >= $visitSpanStartDT && $newSpanStatus = VisitStatus::Active) {
                // Special case - just update the span id and status

                $rm = $this->resource->allocateRoom(1, $this->overrideMaxOccupants);
                if (is_null($rm)) {
                    throw new RuntimeException('Room is full.  ');
                }

                if ($stayRS->Status->getStoredVal() != VisitStatus::CheckedOut) {
                    $stayRS->Status->setNewVal($this->visitRS->Status->getStoredVal());
                }

                $stayRS->Visit_Span->setNewVal($this->visitRS->Span->getStoredVal());
                $stayRS->idRoom->setNewVal($rm->getIdRoom());
                $stayRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
                $stayRS->Updated_By->setNewVal($uname);

                EditRS::update($dbh, $stayRS, array($stayRS->idStays));
                $logText = VisitLog::getUpdateText($stayRS);
                VisitLog::logStay($dbh, $this->getIdVisit(), $stayRS->Visit_Span->getStoredVal(), $stayRS->idRoom->getStoredVal(), $stayRS->idStays->getStoredVal(), $stayRS->idName->getStoredVal(), $this->visitRS->idRegistration->getStoredVal(), $logText, "update", $uname);

            } else if ($stayStartDT < $visitSpanStartDT && (is_null($stayEndDT) || $stayEndDT > $visitSpanStartDT)) {
                // Split the stay

                // Close old stay
                $stayRS->Status->setNewVal($oldStayStatus);
                $stayRS->Span_End_Date->setNewVal($this->visitRS->Span_Start->getStoredVal());
                $stayRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
                $stayRS->Updated_By->setNewVal($uname);

                EditRS::update($dbh, $stayRS, array($stayRS->idStays));
                $logText = VisitLog::getUpdateText($stayRS);
                VisitLog::logStay($dbh, $this->getIdVisit(), $stayRS->Visit_Span->getStoredVal(), $stayRS->idRoom->getStoredVal(), $stayRS->idStays->getStoredVal(), $stayRS->idName->getStoredVal(), $this->visitRS->idRegistration->getStoredVal(), $logText, "update", $uname);

                EditRS::updateStoredVals($stayRS);

                // Make second half of the stay
                $this->addStay($stayRS, $stayOnLeave, $this->visitRS->Span_Start->getStoredVal());

            } else if ($stayStartDT > $visitSpanStartDT && count($oldStayStatus) > 1) {

                // Remove stay from this span
                EditRS::delete($dbh, $stayRS, array($stayRS->idStays));

                // add stay at stay start date
                $this->addStay($stayRS, $stayOnLeave, $stayRS->Span_Start_Date->getStoredVal());

            }
        }

        $this->saveNewStays($dbh, $uname);

    }

    protected function addStay(StaysRS $oldStay, $stayOnLeave, $spanStDate) {

        $rm = $this->resource->allocateRoom(1, $this->overrideMaxOccupants);

        // Check room size
        if (is_null($rm)) {
            throw new RuntimeException('The room is full.  ');
        }

        // Create a new stay record
        $stayRS = new StaysRS();

        $stayRS->idName->setNewVal($oldStay->idName->getStoredVal());
        $stayRS->idRoom->setNewVal($rm->getIdRoom());
        $stayRS->Checkin_Date->setNewVal($oldStay->Checkin_Date->getStoredVal());
        $stayRS->Checkout_Date->setNewVal($this->visitRS->Actual_Departure->getStoredVal());
        $stayRS->Span_Start_Date->setNewVal($spanStDate);
        $stayRS->Span_End_Date->setNewVal($this->visitRS->Span_End->getStoredVal());
        $stayRS->Expected_Co_Date->setNewVal($oldStay->Expected_Co_Date->getStoredVal());
        $stayRS->On_Leave->setNewVal($stayOnLeave);
        $stayRS->Status->setNewVal($this->visitRS->Status->getStoredVal());
        $stayRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));

        $this->stays[] = $stayRS;
    }

    public function checkOutGuest(\PDO $dbh, $idGuest, $dateDeparted = "", $notes = "", $sendEmail = TRUE) {

        $stayRS = NULL;

        // Guest must be already checked in
        foreach ($this->stays as $sRS) {

            if ($sRS->Status->getStoredVal() == VisitStatus::CheckedIn && $sRS->idName->getStoredVal() == $idGuest) {
                $stayRS = $sRS;
                break;
            }
        }

        if (is_null($stayRS) || $stayRS->idStays->getStoredVal() == 0) {
        	$this->setErrorMessage("Checkout Failed: The guest was not checked-in.  ");
            return "Checkout Failed: The guest was not checked-in.  ";
        }

        $uS = Session::getInstance();

        // Check out date
        if ($dateDeparted == "") {

            $dateDepartedDT = new \DateTime();
            $depDate = new \DateTime();

        } else {

            $dateDepartedDT = setTimeZone($uS, $dateDeparted);
            $depDate = setTimeZone($uS, $dateDeparted);

        }

        $depDate->setTime(0, 0, 0);
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        if ($depDate > $today) {
        	$this->setErrorMessage("Checkout failed:  Cannot checkout in the future.  ");
            return "Checkout failed:  Cannot checkout in the future.  ";
        }

        // Earliser than span start date (see mod below to remove this)
        $stDate = new \DateTime($stayRS->Span_Start_Date->getStoredVal());
        $stDate->setTime(0, 0, 0);

        if ($depDate < $stDate) {
        	$this->setErrorMessage("Checkout Failed: The checkout date was before the span start date.  ");
            return "Checkout Failed: The checkout date was before the span start date.  ";
        }
        
        // Check out
        $stayRS->Status->setNewVal(VisitStatus::CheckedOut);
        $stayRS->Checkout_Date->setNewVal($dateDepartedDT->format('Y-m-d H:i:s'));
        $stayRS->Span_End_Date->setNewVal($dateDepartedDT->format('Y-m-d H:i:s'));
        $stayRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
        $stayRS->Updated_By->setNewVal($uS->username);

        EditRS::update($dbh, $stayRS, array($stayRS->idStays));

        $logText = VisitLog::getUpdateText($stayRS);
        VisitLog::logStay($dbh, $stayRS->idVisit->getStoredVal(), $stayRS->Visit_Span->getStoredVal(), $stayRS->idRoom->getStoredVal(), $stayRS->idStays->getStoredVal(), $idGuest, $this->visitRS->idRegistration->getStoredVal(), $logText, "update", $uS->username);

        EditRS::updateStoredVals($stayRS);

        // Get guest names
        $stmt = $dbh->query("Select Name_Full from `name` where idName = $idGuest;");
        $gsts = $stmt->fetchAll(\PDO::FETCH_NUM);
        $guestName = '';
        
        if (count($gsts) > 0) {
        	$guestName = $gsts[0][0];
        }
        
        $this->setInfoMessage($guestName . " checked out on " . $dateDepartedDT->format('M j, Y') . ".  ");
        
        
        // Last Guest?
        if ($this->checkStaysEndVisit($dbh, $uS->username, $dateDepartedDT, $sendEmail) === FALSE) {

	        // prepare email message if needed
	        try {
	            if ($this->getVisitStatus() != VisitStatus::CheckedOut && $uS->Guest_Track_Address != ''  && $uS->NoReplyAddr != '') {
	
	                // Get room name
	                $roomTitle = 'Unknown';
	                if (is_null($this->getResource($dbh)) === FALSE) {
	                    $roomTitle = $this->resource->getTitle();
	                }
	
	
	
	                $gMarkup = '<html><body><h3>Guest Checkout</h3><p>Departure Date: ' . date('g:ia D M jS, Y', strtotime($stayRS->Checkout_Date->getStoredVal())) . ';  from ' . $roomTitle . '</p>';
	
	                if (count($gsts) > 0) {
	                    $tbl = new HTMLTable();
	                    $tbl->addHeaderTr(HTMLTable::makeTh('Id') . HTMLTable::makeTh('Guest Name') . HTMLTable::makeTh('Checked-In') . HTMLTable::makeTh('Checked-Out'));
	
	                    foreach ($gsts as $g) {
	                        $tbl->addBodyTr(HTMLTable::makeTd($idGuest) . HTMLTable::makeTd($guestName)
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
	
	                // Send email
	                $mail = prepareEmail();
	
	                $mail->From = $uS->NoReplyAddr;
	                $mail->FromName = $uS->siteName;
	                $mail->addReplyTo($uS->NoReplyAddr, $uS->siteName);
	
	                $tos = explode(',', $uS->Guest_Track_Address);
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
	        } catch (\Exception $ex) {
	            $this->setErrorMessage("Failed to send email to house admin: " . $ex->getMessage());
	        }
        }
        
        return $guestName . " checked out on " . $dateDepartedDT->format('M j, Y');
    }

    protected function checkStaysEndVisit(\PDO $dbh, $username, \DateTime $dateDeparted, $sendEmail) {

        $uS = Session::getInstance();

        //$this->loadStays($dbh, '');
        
        $allStays = self::loadStaysStatic($dbh, $this->getIdVisit(), $this->getSpan(), '');
        
        // Check each stay status
        foreach ($allStays as $stayRS) {

            if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedIn) {
                return FALSE;
            }

            // Get the latest checkout date
            if ($stayRS->Span_End_Date->getStoredVal() != '') {

                $dt = new \DateTime($stayRS->Span_End_Date->getStoredVal());

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

        $this->updateVisitRecord($dbh, $username);


        // Update resource cleaning status
        $resc = AbstractResource::getResourceObj($dbh, $this->getidResource());
        $rooms = $resc->getRooms();

        $rmCleans = readGenLookupsPDO($dbh, 'Room_Cleaning_Days');

        foreach ($rooms as $r) {

            // Only if cleaning cycle is defined and > 0
            if (isset($rmCleans[$r->getCleaningCycleCode()]) && $rmCleans[$r->getCleaningCycleCode()][2] > 0) {
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
            } catch (RuntimeException $hex) {
            	$this->setErrorMessage($hex->getMessage());
            }
        }


        try {
            if ($sendEmail && $uS->NoReplyAddr != '' && ($uS->Guest_Track_Address != '' || $uS->HouseKeepingEmail != '')) {

                // Get room name
                $roomTitle = 'Unknown';
                if (is_null($this->getResource($dbh)) === FALSE) {
                    $roomTitle = $this->resource->getTitle();
                }

                // Get guest names
                $query = "Select n.idName, n.Name_First, n.Name_Last, s.Checkin_Date, s.Checkout_Date
                from stays s join `name` n on s.idName = n.idName
                where s.idVisit = :vst and s.Status = :stat;";
                $stmt = $dbh->prepare($query);
                $stmt->execute(array(':vst' => $this->getIdVisit(), ':stat' => VisitStatus::CheckedOut));
                $gsts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                $gMarkup = '<html><body><h3>Guest Checkout</h3><p>Departure Date: ' . $dateDeparted->format('g:ia D M jS, Y') . ';  from ' . $roomTitle . '</p>';

                if (count($gsts) > 0) {
                    $tbl = new HTMLTable();
                    $tbl->addHeaderTr(HTMLTable::makeTh('Id') . HTMLTable::makeTh('Guest Name') . HTMLTable::makeTh('Checked-In') . HTMLTable::makeTh('Checked-Out'));

                    foreach ($gsts as $g) {
                        $tbl->addBodyTr(HTMLTable::makeTd($g['idName']) . HTMLTable::makeTd($g['Name_First'] . ' ' . $g['Name_Last'])
                                . HTMLTable::makeTd(date('g:ia D M jS, Y', strtotime($g['Checkin_Date'])))
                                . HTMLTable::makeTd(date('g:ia D M jS, Y', strtotime($g['Checkout_Date']))));
                    }

                    $gMarkup .= $tbl->generateMarkup();
                }


                // Finalize body
                $gMarkup .= '</body></html>';

                $subj = "Visit audit report for room: " . $roomTitle . ".  Room is now empty.";

                // Get the site configuration object

                // Send email
                $mail = prepareEmail();

                $mail->From = $uS->NoReplyAddr;
                $mail->FromName = $uS->siteName;
                $mail->addReplyTo($uS->NoReplyAddr, $uS->siteName);

                $tos = array_merge(explode(',', $uS->Guest_Track_Address), explode(',', $uS->HouseKeepingEmail));

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
        } catch (\Exception $ex) {
        	$this->setErrorMessage($ex->getMessage());
        }

        $this->setInfoMessage("Visit Ended.  ");

        return TRUE;
    }

    protected function checkOutVisit(\PDO $dbh, $dateDeparted = "", $sendEmail = TRUE) {
        $msg = "";

        // Check out date
        if ($dateDeparted == "") {
            $dateDepartedDT = new \DateTime();
            $depDate = new \DateTime();
            $depDate->setTime(0, 0, 0);
        } else {
            $dateDepartedDT = new \DateTime($dateDeparted);
            $depDate = new \DateTime($dateDeparted);
            $depDate->setTime(0, 0, 0);
        }

        $nowDate = new \DateTime();
        $nowDate->setTime(0, 0, 0);

        if ($depDate > $nowDate) {
            return "Checkout failed:  Cannot checkout in the future.";
        }

        // CO date validity
        $stDate = new \DateTime($this->getArrivalDate());

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
     *
     * @param \PDO $dbh
     * @param array $newStayStartDates posted array of stay start dates indexed by idStays.
     * @return string
     */
    public function checkStayStartDates(\PDO $dbh, $newStayStartDates) {

        $uS = Session::getInstance();
        $reply = '';
        $staysToUpdate = array();
        $visitActive = FALSE;
        $stayStartDates = array();

        if ($this->getSpan() == 0) {
            $visitActive = TRUE;
        }

        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        if ($this->getSpanStart() == '') {
            return 'Visit Start Date Missing.  ';
        }

        $visitStartDT = new \DateTime($this->getSpanStart());
        $visitStartDT->setTime(0, 0, 0);

        if ($this->getVisitStatus() == VisitStatus::CheckedIn && $this->getExpectedDeparture() != '') {

            $visitEndDT = new \DateTime($this->getExpectedDeparture());

            if ($visitEndDT < $today) {
                $visitEndDT = new \DateTime($today->format('Y-m-d'));
            }
        } else if ($this->getSpanEnd() != '') {
            $visitEndDT = new \DateTime($this->getSpanEnd());
        } else {
            return 'A non-active visit has no span end date.  ';
        }

        $visitEndDT->setTime(0, 0, 0);

        $firstStayStartDT = new \DateTime($visitEndDT->format('Y-m-d 00:00:00'));

        $stays = $this->loadStaysStatic($dbh, $this->getIdVisit(), $this->getSpan(), '');  // Loads all stays

        // Collect stay start dates
        foreach ($stays as $stayRs) {
            $stayStartDT = new \DateTime($stayRs->Span_Start_Date->getStoredVal());
            $stayStartDT->setTime(0, 0, 0);
            $stayStartDates[$stayRs->idStays->getStoredVal()] = $stayStartDT;
        }

        // Can't change the start date of a visit span > 0
//        if (count($stays) == 1 && $this->getSpan() > 0 ) {
//            return 'Cannot change start date, this visit has a previous span.  ';
//        }

        foreach ($stays as $stayRs) {

            $stayStartTime = new \DateTime($stayRs->Span_Start_Date->getStoredVal());
            $stayStartDT = $stayStartDates[$stayRs->idStays->getStoredVal()];

            // Is this set in the POST?
            if (isset($newStayStartDates[$stayRs->idStays->getStoredVal()]) === FALSE) {

                if ($visitActive && $stayStartDT < $firstStayStartDT) {
                    $firstStayStartDT = new \DateTime($stayStartDT->format('Y-m-d 00:00:00'));
                }
                continue;
            }

            $newStayStart = filter_var($newStayStartDates[$stayRs->idStays->getStoredVal()], FILTER_SANITIZE_STRING);

            // Cant do anything with a blank date
            if ($newStayStart == '') {

                if ($visitActive && $stayStartDT < $firstStayStartDT) {
                    $firstStayStartDT = new \DateTime($stayStartDT->format('Y-m-d 00:00:00'));
                }
                continue;
            }

            // Create date object
            try {
                $ckinDT = new \DateTime($newStayStart);
                $ckinDT->setTime(0, 0, 0);
            } catch(\Exception $ex) {

                $reply .= "Malformed new Stay Start date: " . $newStayStart . ".  ";

                if ($visitActive && $stayStartDT < $firstStayStartDT) {
                    $firstStayStartDT = new \DateTime($stayStartDT->format('Y-m-d 00:00:00'));
                }
                continue;
            }


            // Did the new stay start date change?
            if ($ckinDT == $stayStartDT) {
                // No Change
                if ($visitActive && $stayStartDT < $firstStayStartDT) {
                    $firstStayStartDT = new \DateTime($stayStartDT->format('Y-m-d 00:00:00'));
                }
                continue;

            } else if ($ckinDT > $today) {
                // Future
                $reply .= 'Cannot change the start date to a future date.  ';
                if ($visitActive && $stayStartDT < $firstStayStartDT) {
                    $firstStayStartDT = new \DateTime($stayStartDT->format('Y-m-d 00:00:00'));
                }
                continue;

            // New date cant be later than the visit ending date.
            } else if ($ckinDT >= $visitEndDT) {

                $reply .= "Guest's start date cannot be on or after the visit span ends: " . $visitEndDT->format('M j, Y') . '.  ';
                if ($visitActive && $stayStartDT < $firstStayStartDT) {
                    $firstStayStartDT = new \DateTime($stayStartDT->format('Y-m-d 00:00:00'));
                }
                continue;

            // New date less than the visit start?
            } else if ($ckinDT < $visitStartDT) {

                // Move the visit start date?
                if ($this->getSpan() == 0) {

                    $resv = Reservation_1::instantiateFromIdReserv($dbh, $this->getReservationId());
                    $hasResource = $resv->isResourceOpen($dbh, $this->getidResource(), $ckinDT->format('Y-m-d 23:55:00'), $stayStartDT->format('Y-m-d 09:00:00'), 1, array('room', 'rmtroom'), TRUE, TRUE);

                    if ($hasResource === FALSE) {
                        $reply .= "Cannot start the visit earlier, the room is not available.";
                        continue;
                    }

                } else {

                    $reply .= "Guest's start date cannot be on or before this visit span starts: " . $visitStartDT->format('M j, Y') . '.  ';
                    if ($visitActive && $stayStartDT < $firstStayStartDT) {
                        $firstStayStartDT = new \DateTime($stayStartDT->format('Y-m-d 00:00:00'));
                    }
                    continue;
                }
            }

            // Compute stay end date
            if ($stayRs->Span_End_Date->getStoredVal() != '') {

                $stayEndDT = new \DateTime($stayRs->Span_End_Date->getStoredVal());
                $stayEndDT->setTime(0, 0, 0);

            } else if ($stayRs->Status->getStoredVal() == VisitStatus::CheckedIn) {

                $stayEndDT = $visitEndDT;

            } else {

                $reply .= "Stay's span end date missing.  ";
                if ($visitActive && $stayStartDT < $firstStayStartDT) {
                    $firstStayStartDT = new \DateTime($stayStartDT->format('Y-m-d 00:00:00'));
                }
                continue;
            }

            // New date cannot be past it's own end date.
            if ($ckinDT >= $stayEndDT) {
                //
                $reply .= "Guest's start date cannot be on or after the guest's stay ends: " . $stayEndDT->format('M j, Y') . '.  ';
                if ($visitActive && $stayStartDT < $firstStayStartDT) {
                    $firstStayStartDT = new \DateTime($stayStartDT->format('Y-m-d 00:00:00'));
                }
                continue;
            }

            // Cannot change the only stay with the visit start
            if ($this->getSpan() > 0) {

                $num = 0;
                $isMine = FALSE;

                foreach ($stayStartDates as $k => $s) {

                    if ($s == $visitStartDT) {

                        $num++;

                        if ($k == $stayRs->idStays->getStoredVal()) {
                            $isMine = TRUE;
                        }
                    }
                }

                if ($num < 2 && $isMine) {
                    $reply .= 'Cannot change start date for this visit span.';
                    continue;
                }
            }

            // Add the time to the new stay start date.
            $newStart = $ckinDT->format('Y-m-d') . ' ' . $stayStartTime->format('H:i:s');

            // Change the stay span start date
            $stayRs->Span_Start_Date->setNewVal($newStart);
            $stayStartDates[$stayRs->idStays->getStoredVal()] = $ckinDT;

            // Check the stay expected end date.
            $stayExpEnd = new \DateTime($stayRs->Expected_Co_Date->getStoredVal());
            $stayExpEnd->setTime(0, 0, 0);

            // If the stay expected end is too early, make it the same as the visit.
            if ($stayExpEnd <= $stayEndDT) {
                $stayRs->Expected_Co_Date->setNewVal($visitEndDT->format('Y-m-d '. $uS->CheckOutTime . ':00:00'));
            }

            // Check the Stay Check-in date.  Can only change the first span.
            if ($this->getSpan() == 0) {
                $stayRs->Checkin_Date->setNewVal($newStart);
            }

            // update the first start if appropriate.
            if ($visitActive && $ckinDT < $firstStayStartDT) {
                $firstStayStartDT = new \DateTime($ckinDT->format('Y-m-d 00:00:00'));
            }

            $staysToUpdate[] = $stayRs;

            $reply .= 'Stay start date moved to: ' . $ckinDT->format('M j, Y') . '.  ';

        }

        if (count($staysToUpdate) > 0) {

            VisitViewer::saveStaysDates($dbh, $staysToUpdate, $this->getIdRegistration(), $uS->username);
            $this->loadStays($dbh);


            // See if the visit start changed.
            if ($visitActive && $visitStartDT != $firstStayStartDT) {

                $visitStartTime = new \DateTime($this->getSpanStart());
                $startStr = $firstStayStartDT->format('Y-m-d') . ' ' . $visitStartTime->format('H:i:s');

                // Update visit arrival
                $this->visitRS->Arrival_Date->setNewVal($startStr);
                $this->visitRS->Span_Start->setNewVal($startStr);
                $this->visitRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
                $this->visitRS->Updated_By->setNewVal($uS->username);

                $uctr = $this->updateVisitRecord($dbh, $uS->username);

                if ($uctr > 0) {

                    $reply .= 'Visit Arrival date changed to: ' . $firstStayStartDT->format('M j, Y') . '.  ';

                    // Update reservation actual Arrival
                    $resv = Reservation_1::instantiateFromIdReserv($dbh, $this->getReservationId());
                    $resv->setActualArrival($startStr);
                    $resv->saveReservation($dbh, $resv->getIdRegistration(), $uS->username);
                    
                    $startDelta = $visitStartDT->diff($firstStayStartDT)->days;

                    // Update any invoice line dates
                    Invoice::updateInvoiceLineDates($dbh, $this->getIdVisit(), $startDelta);
                }
            }
        }

        return $reply;
    }

    /**
     * Compares each guest date in array $guestDates with the visit Expected Departure date, and updates it accordingly.  Also forces any reservations to move.
     *
     * @param \PDO $dbh
     * @param array $guestDates String dates indexed by idGuest
     * @param int $maxExpected The administrative limit on the number of days forward
     * @param \DateTimeZone $tz
     * @param string $uname
     * @return string
     */
    public function changeExpectedCheckoutDates(\PDO $dbh, array $guestDates, $maxExpected, $uname) {

        if ($this->getVisitStatus() != VisitStatus::CheckedIn) {
            return array('message' => '');
        }

        $uS = Session::getInstance();
        $isChanged = FALSE;
        $rtnMsg = '';
        $staysToUpdate = array();

        $todayDT = new \DateTime();
        $todayDT->setTime(0, 0, 0);


        // Init the latest departure date for the visit
        $lastDepartureDT = new \DateTime($this->getArrivalDate());
        $lastDepartureDT->setTime(0, 0, 0);

        $visitArrivalDT = new \DateTime($this->getArrivalDate());

        foreach ($this->stays as $stayRS) {

            $guestId = $stayRS->idName->getStoredVal();

            $ecoDT = new \DateTime($stayRS->Expected_Co_Date->getStoredVal());
            $ecoDT->setTime(0, 0, 0);

            // Not trying to update it.
            if (isset($guestDates[$guestId]) === FALSE) {
                // Check last date
                if ($ecoDT > $lastDepartureDT) {
                    $lastDepartureDT = new \DateTime($ecoDT->format('Y-m-d 00:00:00'));
                }

                continue;
            }

            // Get the new date
            $coDate = filter_var($guestDates[$guestId], FILTER_SANITIZE_STRING);

            // no value set?
            if ($coDate == '') {
                continue;
            }

            // Creaate new Checkout date
            try {
                $coDT = setTimeZone(NULL, $coDate);
                $coDT->setTime(0, 0, 0);
            } catch (\Exception $ex) {
                $rtnMsg .= "Something wrong with the Expected Checkout Date: " . $coDate;
                continue;
            }

            // Not changed.
            if ($ecoDT == $coDT) {

                // Check last date
                if ($ecoDT > $lastDepartureDT) {
                    $lastDepartureDT = new \DateTime($ecoDT->format('Y-m-d 00:00:00'));
                }

                Continue;
            }

            // Only if trying to set a new expected checkout date
            if ($coDT < $todayDT) {

                $rtnMsg .= "Expected Checkout date cannot be earlier than today.  ";
                 // Check last date
                if ($ecoDT > $lastDepartureDT) {
                    $lastDepartureDT = new \DateTime($ecoDT->format('Y-m-d 00:00:00'));
                }

               continue;
            }

            // Span start date
            $spnStartDT = new \DateTime($stayRS->Span_Start_Date->getStoredVal());
            $spnStartDT->setTime(0, 0, 0);

            // Earlier than check in date?
            if ($coDT <= $spnStartDT) {

                $rtnMsg .= "The Expected Checkout date cannot be earlier or the same as the Check-in date.  ";
                // Check last date
                if ($ecoDT > $lastDepartureDT) {
                    $lastDepartureDT = new \DateTime($ecoDT->format('Y-m-d 00:00:00'));
                }

                continue;
            }

            // Too rar out?
            if ($todayDT->diff($coDT)->days > $maxExpected) {

                $rtnMsg .= "Expected Checkout date cannot be beyond " . $maxExpected . " days from today.  The max days setting can be changed.";
                // Check last date
                if ($ecoDT > $lastDepartureDT) {
                    $lastDepartureDT = new \DateTime($ecoDT->format('Y-m-d 00:00:00'));
                }

                continue;
            }



            // Okay to update
            if ($coDT > $lastDepartureDT) {
                $lastDepartureDT = new \DateTime($coDT->format('Y-m-d 00:00:00'));
            }

            $stayRS->Expected_Co_Date->setNewVal($coDT->format('Y-m-d '. $uS->CheckOutTime . ':00:00'));
            $stayRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $stayRS->Updated_By->setNewVal($uname);

            $staysToUpdate[] = $stayRS;

            $rtnMsg .= 'Stay expected departure date changed to: ' . $coDT->format('M j, Y') . '.  ';
            $isChanged = TRUE;
        }

        // Update indicated stays.
        if (count($staysToUpdate) > 0) {
            VisitViewer::saveStaysDates($dbh, $staysToUpdate, $this->getIdRegistration(), $uS->username);
            $this->loadStays($dbh);
        }

        // See if the visit expected departure changed.
        $visitExpDepDT = new \DateTime($this->getExpectedDeparture());
        $visitExpDepDT->setTime(0, 0, 0);

        // Make sure the lastDepart date is greater than the visit arrival.
        if ($visitExpDepDT != $lastDepartureDT && $lastDepartureDT > $visitArrivalDT) {

            // Update visit exepected departure
            $this->visitRS->Expected_Departure->setNewVal($lastDepartureDT->format('Y-m-d '. $uS->CheckOutTime . ':00:00'));
            $this->visitRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
            $this->visitRS->Updated_By->setNewVal($uname);

            $uctr = $this->updateVisitRecord($dbh, $uname);

            if ($uctr > 0) {

                $rtnMsg .= 'Visit expected departure date changed to: ' . $lastDepartureDT->format('M j, Y') . '.  ';
                $isChanged = TRUE;

                // Update reservation expected departure
                $resv = Reservation_1::instantiateFromIdReserv($dbh, $this->getReservationId());
                $resv->setExpectedDeparture($lastDepartureDT->format('Y-m-d ' . $uS->CheckOutTime . ':00:00'));
                $resv->saveReservation($dbh, $resv->getIdRegistration(), $uname);

                // Move other reservations to alternative rooms
                $rtnMsg .= ReservationSvcs::moveResvAway($dbh, new \DateTime($this->getArrivalDate()), $lastDepartureDT, $this->getidResource(), $uname);
            }
        }

        return array('message'=>$rtnMsg, 'isChanged' => $isChanged);
    }

    protected function onLeaveStays(\PDO $dbh, $visitStatus, $changeDate, $uname, $stayOnLeave = 0) {

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

        $this->saveNewStays($dbh, $uname);

    }

    public function endLeave(\PDO $dbh, $returning, $extendReturnDate) {

        $reply = '';
        $uS = Session::getInstance();

        if ($extendReturnDate == '') {
            $extendReturnDate = date('Y-m-d');
        }

        $retDT = setTimeZone(NULL, $extendReturnDate);
        $retDT->setTime(0, 0, 0);
        $now = new \DateTime();
        $now->setTime(0, 0, 0);
        $timeNow = date('H:i:s');

        $dt = $retDT->format('Y-m-d');
        $coDT = new \DateTime($dt . ' ' . $timeNow);


        if ($returning === FALSE) {
            // end visit

            if ($retDT > $now) {
                return 'Cannot checkout in the future.  ';
            }

            $reply .= $this->checkOutVisit($dbh, $coDT->format('Y-m-d H:i:s'));

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
                $this->onLeaveStays($dbh, VisitStatus::CheckedOut, $retDT->format('Y-m-d H:i:s'), $uS->username, FALSE);

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

        $coDT = new \DateTime($dt . ' ' . $timeNow);

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
            $reply .= $this->changePledgedRate($dbh, RoomRateCategories::Fixed_Rate_Category, 0, 0, $uS->username, $coDT, ($uS->RateGlideExtend > 0 ? TRUE : FALSE), $extDays);
            $reply .= 'Guests on Leave.  ';

        } else {
            // continue with current room rate.

            // Remove any previous visit
            $vol = new Visit_onLeaveRS();
            $vol->idVisit->setStoredVal($this->getIdVisit());
            EditRS::delete($dbh, $vol, array($vol->idVisit));

            // Check out all guest, check back in with OnLeave set.
            $this->onLeaveStays($dbh, VisitStatus::CheckedOut, $coDT->format('Y-m-d H:i:s'), $uS->username, $extDays);
            $reply .= 'Guests on Leave.  ';
        }

        return $reply;

    }

    public static function loadStaysStatic(\PDO $dbh, $idVisit, $span, $statusFilter = VisitStatus::CheckedIn) {

        $stays = array();

        if ($idVisit !== 0) {
            $stayRS = new StaysRS();
            $stayRS->idVisit->setStoredVal($idVisit);
            $stayRS->Visit_Span->setStoredVal($span);

            if ($statusFilter != '') {
                $stayRS->Status->setStoredVal($statusFilter);
                $rows = EditRS::select($dbh, $stayRS, array($stayRS->idVisit, $stayRS->Visit_Span, $stayRS->Status));
            } else {
                $rows = EditRS::select($dbh, $stayRS, array($stayRS->idVisit, $stayRS->Visit_Span));
            }

            foreach ($rows as $r) {

                $stayRS = new StaysRS();
                EditRS::loadRow($r, $stayRS);

                $stays[] = $stayRS;

            }
        }

        return $stays;
    }

    public function loadStays(\PDO $dbh, $statusFilter = VisitStatus::CheckedIn) {

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

        if (is_null($this->resource) || $this->resource instanceof AbstractResource === FALSE) {
            if ($this->visitRS->idResource->getStoredVal() > 0 && $dbh instanceof \PDO) {
                $this->resource = AbstractResource::getResourceObj($dbh, $this->visitRS->idResource->getStoredVal());
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

    public function getSpanEnd() {
        return $this->visitRS->Span_End->getStoredVal();
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

    public function getRoomTitle(\PDO $dbh) {

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

    public function getInfoMessage() {
    	return $this->infoMessage;
    }
    
    public function getErrorMessage() {
    	return $this->errorMessage;
    }
    
    protected function setInfoMessage($v) {
    	$this->infoMessage .= $v;
    }
    
    protected function setErrorMessage($v) {
    	$this->errorMessage .= $v;
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
?>