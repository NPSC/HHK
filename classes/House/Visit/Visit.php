<?php

namespace HHK\House\Visit;

use DateTimeInterface;
use HHK\Exception\RuntimeException;
use HHK\Notification\Mail\HHKMailer;
use HHK\Payment\Invoice\Invoice;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\sec\SecurityComponent;
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
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Visit
 * @author Eric
 */
class Visit {

    /**
     * Summary of resource
     * @var AbstractResource|null
     */
    protected $resource = NULL;
    /**
     * Summary of visitRS
     * @var VisitRS
     */
    public $visitRS;
    /**
     * Summary of visitRSs
     * @var array[VisitRS]
     */
    protected $visitRSs = [];
    /**
     * Summary of newVisit
     * @var bool
     */
    private $newVisit = FALSE;
    /**
     * Summary of stays
     * @var array
     */
    public $stays = [];
    /**
     * Summary of overrideMaxOccupants
     * @var bool
     */
    protected $overrideMaxOccupants = FALSE;
    /**
     * Summary of infoMessage
     * @var string
     */
    protected $infoMessage = '';
    /**
     * Summary of errorMessage
     * @var string
     */
    protected $errorMessage = '';

    /**
     * Summary of __construct
     * @param \PDO $dbh
     * @param int $idReg
     * @param int $searchIdVisit
     * @param \DateTime|null $arrivalDT
     * @param \DateTime|null $departureDT
     * @param \HHK\House\Resource\AbstractResource|null $resource
     * @param string $userName
     * @param int $span
     * @param bool $forceNew
     * @throws \HHK\Exception\RuntimeException
     */
    public function __construct(\PDO $dbh, $idReg, $searchIdVisit, $span = -1, DateTimeInterface $arrivalDT = NULL, DateTimeInterface $departureDT = NULL, AbstractResource $resource = NULL, $forceNew = FALSE) {

        $uS = Session::getInstance();

        $this->visitRSs = $this->loadVisits($dbh, $idReg, $searchIdVisit, $span, $forceNew);

        if ($resource !== null && ! $resource->isNewResource()) {
            $this->resource = $resource;
        }


        // Find the last visit span.
        $currentSpan = -1;
        foreach ($this->visitRSs as $v) {
            if ($v->Span->getStoredVal() > $currentSpan) {
                $currentSpan = $v->Span->getStoredVal();
                $this->visitRS = $v;
            }
        }

        // If no visit found, create and save a new one.
        if ($this->visitRS->idVisit->getStoredVal() === 0) {

            // New Visit
            // Compare dates
            $nowDT = new \DateTime();
            $nowDT->setTime(0, 0, 0);

            if ($arrivalDT > $departureDT) {
                throw new RuntimeException('The arrival date cannot be AFTER the departure date.  ');
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
            VisitLog::logVisit($dbh, $idVisit, 0, $this->getidResource(), $idReg, $logText, "insert", $uS->userName);

            $this->visitRS->idVisit->setNewVal($idVisit);
            EditRS::updateStoredVals($this->visitRS);
            $this->newVisit = TRUE;
        }

        $this->loadStays($dbh, VisitStatus::CheckedIn);
    }

    /**
     * Summary of loadVisits
     * @param \PDO $dbh
     * @param mixed $idReg
     * @param mixed $idVisit
     * @param mixed $span
     * @param mixed $forceNew
     * @throws \HHK\Exception\RuntimeException
     * @return VisitRS[]
     */
    protected function loadVisits(\PDO $dbh, $idReg, $idVisit, $span = -1, $forceNew = FALSE) {

        $uS = Session::getInstance();
        $visitRS = new VisitRs();
        $visits = [];

        if ($idVisit > 0) {
            // Existing Visit

            if ($span >= 0) {
                // Load a specific visit span
                $visitRS->Span->setStoredVal($span);
                $visitRS->idVisit->setStoredVal($idVisit);
                $rows = EditRS::select($dbh, $visitRS, [$visitRS->idVisit, $visitRS->Span]);

            } else {
                // Load all visit spans for this visit
                $visitRS->idVisit->setStoredVal($idVisit);
                $rows = EditRS::select($dbh, $visitRS, [$visitRS->idVisit]);
            }

            if (count($rows) > 0) {
                // collect each visit span
                foreach ($rows as $r) {
                    $vRS = new VisitRs();
                    EditRS::loadRow($r, $vRS);
                    $visits[$vRS->Span->getStoredVal()] = $vRS;
                }
            } else {
                throw new RuntimeException("Existing Visit record not found. Visit ID: {$idVisit}.");
            }

        } else if ($idReg > 0 && $idVisit == 0) {
            // New visit

            // Check if there are existing active visits for this registration.
            $visitRS->idRegistration->setStoredVal($idReg);
            $visitRS->Status->setStoredVal(VisitStatus::CheckedIn);
            $activeVisitRows = EditRS::select($dbh, $visitRS, [$visitRS->idRegistration, $visitRS->Status]);

            if (count($activeVisitRows) > $uS->RoomsPerPatient) {

                throw new RuntimeException("This visit is declined. It exceeds the maximum number of rooms per patient: {$uS->RoomsPerPatient}" );

            } else if (count($activeVisitRows) == 0 || $forceNew) {
                // make a new visit
                $visitRS = new VisitRs();
                $visitRS->idRegistration->setNewVal($idReg);
                $visitRS->Span->setNewVal(0);
                $visits[0] = $visitRS;
            } else {
                // use an existing active visit
                $vRS = new VisitRs();
                EditRS::loadRow($activeVisitRows[0], $vRS);
                $visits[$vRS->Span->getStoredVal()] = $vRS;
            }

        } else {
            throw new RuntimeException("Visit not instantiated. Visit ID: {$idVisit}, Registration ID: {$idReg}.");
        }

        return $visits;
    }

    /**
     * Summary of updateVisitRecord
     * @param \PDO $dbh
     * @param string $uname
     * @return int
     */
    public function updateVisitRecord(\PDO $dbh, $uname = '') {

        return self::updateVisitRecordStatic($dbh, $this->visitRS, $uname);
    }

    /**
     * Summary of updateVisitRecordStatic
     * @param \PDO $dbh
     * @param \HHK\Tables\Visit\VisitRS $visitRS
     * @param string $uname
     * @return int
     */
    public static function updateVisitRecordStatic(\PDO $dbh, VisitRs $visitRS, $uname = '') {

        $visitRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $visitRS->Updated_By->setNewVal($uname);

        $upCtr = EditRS::update($dbh, $visitRS, array($visitRS->idVisit, $visitRS->Span));

        if ($upCtr > 0) {
            $logText = VisitLog::getUpdateText($visitRS);

            // Update the visit log
            EditRS::updateStoredVals($visitRS);
            VisitLog::logVisit($dbh, $visitRS->idVisit->getStoredVal(), $visitRS->Span->getStoredVal(), $visitRS->idResource->getStoredVal(), $visitRS->idRegistration->getStoredVal(), $logText, "update", $uname);

        }

        return $upCtr;
    }

    /**
     * Summary of addGuestStay  Adds a new stay for a guest.
     * @param int $idGuest
     * @param string $checkinDate
     * @param string $stayStartDate
     * @param string $expectedCO
     * @param mixed $stayOnLeave
     * @throws \HHK\Exception\RuntimeException
     * @throws \HHK\Exception\UnexpectedValueException
     * @return void
     */
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

        // Update the visit expected departure date if the stay's expected departure is later than the visit's expected departure.
        $stayCoDt = new \DateTime($expectedCO);
        $visitCoDt = new \DateTime($this->getExpectedDeparture());

        if ($stayCoDt > $visitCoDt) {
            $this->visitRS->Expected_Departure->setNewVal($stayCoDt->format('Y-m-d 10:00:00'));
        }


        // Create a new stay record
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

    /**
     * Summary of checkin
     * @param \PDO $dbh
     * @param string $username
     * @throws \HHK\Exception\UnexpectedValueException
     * @return bool
     */
    public function checkin(\PDO $dbh, $username) {

        // Verify data
        if ($this->resource->getIdResource() == 0 || $this->getIdRegistration() == 0 || $this->getArrivalDate() == "" || $this->getExpectedDeparture() == "" || count($this->stays) < 1) {
            throw new UnexpectedValueException("Bad or missing data when trying to save a new Visit.");
        }

        $this->visitRS->Status->setNewVal(VisitStatus::CheckedIn);

        $this->visitRS->idResource->setNewVal($this->resource->getIdResource());

        $this->visitRS->Checked_In_By->setNewVal($username);

        $this->updateVisitRecord($dbh, $username);

        // Save Stays
        $this->saveNewStays($dbh, $username);

        return TRUE;
    }

    /**
     * Summary of saveNewStays
     * @param \PDO $dbh
     * @param string $username
     * @return void
     */
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

    /**
     * Summary of changeRooms - Make a new span with the new room.
     * @param \PDO $dbh
     * @param \HHK\House\Resource\AbstractResource $resc
     * @param \DateTimeInterface $chgDT
     * @param \DateTimeInterface $chgEndDT
     * @param mixed $newRateCategory
     * @throws \HHK\Exception\RuntimeException
     * @return string
     */
    public function changeRooms(\PDO $dbh, AbstractResource $resc, DateTimeInterface $chgDT, DateTimeInterface $chgEndDT, $newRateCategory) {

        $uS = Session::getInstance();

        // used to control sending email to housekeeping.
        $houseKeepingEmail = $uS->HouseKeepingEmail;
        $rateGlideDays = 0;
        $rtnMessage = '';

        $now = new \DateTimeImmutable();
        $today = $now->setTime(0, 0, 0);
        $chgDayDT = new \DateTime($chgDT->format('Y-m-d 0:0:0'));

        $isFutureChange = ($chgDayDT > $today) ? true : false;

        // Check if the resource is valid.
        if ($resc->isNewResource()) {
            throw new RuntimeException('Invalid Resource supplied to visit->changeRooms.');
        }

        if ($this->visitRS->idResource->getStoredVal() == $resc->getIdResource()) {
            return "Error - Change Rooms: the new room cannot be the same as the old room.  ";
        }

        if (count($this->stays) > $resc->getMaxOccupants()) {
            return "Error - Change Rooms failed:The New room is too small, or has too many occupants.  ";
        }

        // Change date cannot be earlier than span start date.
        $spanStartDT = new \DateTime($this->visitRS->Span_Start->getStoredVal());
        $spanStartDT->setTime(0,0,0);
        if ($chgDayDT < $spanStartDT) {
            return "Error - Change Rooms failed: The Change Date is prior to Visit Span start date.  ";
        }

        $expDepDT = new \DateTime($this->getExpectedDeparture());
        $expDepDT->setTime(0, 0, 0);

        if ($expDepDT <= $today) {
            $expDepDT = $today->add(new \DateInterval('P1D'));
        }

        if ($expDepDT < $chgDayDT) {
            $expDepDT = $chgDayDT;
        };

        // Reservation
        $reserv = Reservation_1::instantiateFromIdReserv($dbh, $this->getReservationId());

        // Room Available
        if ($reserv->isNew() === FALSE) {

            $rescOpen = $reserv->isResourceOpen(
                    $dbh, $resc->getIdResource(),
                    $chgDT->format('Y-m-d H:i:s'),
                    $expDepDT->format("Y-m-d $uS->CheckOutTime:00:00"),
                    count($this->stays),
                    ['room', 'rmtroom', 'part'],
                    FALSE,
                    SecurityComponent::is_Authorized("guestadmin")
                );

            if ($rescOpen) {

                if ($isFutureChange === false) {
                    $reserv->setIdResource($resc->getIdResource());
                    $reserv->saveReservation($dbh, $this->getIdRegistration(), $uS->username);
                }

            } else {
                return "Error - Change Rooms failed: The new room is busy or missing necessary attributes.  ";
            }
        }


        // get rooms
        $oldRoom = "";
        $rooms = [];
        if ($this->getResource($dbh) != NULL) {
            $oldRoom = $this->resource->getTitle();
            $rooms = $this->resource->getRooms();
        }

        $newIdRoomRate = $this->getIdRoomRate();

        if ($newRateCategory != '' && $newRateCategory != $this->getRateCategory()) {
            $pm = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
            $rateRs = $pm->getCategoryRateRs(0, $newRateCategory);
            $newIdRoomRate = $rateRs->idRoom_rate->getStoredVal();
        }

        if ($spanStartDT == $chgDayDT) {
            // Just replace the room
            $this->setIdResource($resc->getIdResource());
            $this->resource = $resc;

            if ($newRateCategory != '' && $newRateCategory != $this->getRateCategory()) {
                $this->setRateCategory($newRateCategory);
                $this->setIdRoomRate($newIdRoomRate);
                $rtnMessage .= 'Room Rate Changed.  ';
            }

            $cnt = $this->updateVisitRecord($dbh, $uS->username);
            $houseKeepingEmail = '';  // Don't trigger housekeeping for replace room

            if ($cnt > 0) {

                foreach ($this->stays as $stayRS) {

                    if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedIn) {

                        // update current stay
                        $rm = $this->resource->allocateRoom(1, $this->overrideMaxOccupants);

                        if ($rm === null) {
                            continue;
                        }

                        $stayRS->idRoom->setNewVal($rm->getIdRoom());
                        $stayRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
                        $stayRS->Updated_By->setNewVal($uS->username);

                        EditRS::update($dbh, $stayRS, [$stayRS->idStays]);
                        $logText = VisitLog::getUpdateText($stayRS);
                        VisitLog::logStay(
                            $dbh,
                            $this->getIdVisit(),
                            $stayRS->Visit_Span->getStoredVal(),
                            $stayRS->idRoom->getStoredVal(),
                            $stayRS->idStays->getStoredVal(),
                            $stayRS->idName->getStoredVal(),
                            $this->visitRS->idRegistration->getStoredVal(),
                            $logText, "update",
                            $uS->username);

                        EditRS::updateStoredVals($stayRS);
                    }
                }

                $rtnMessage .= 'Guests Replaced Rooms.  ';
            }
        } else {  // Change rooms on a date other than the span start date.

            // Set new room rate?
            if ($newRateCategory != '' && $newRateCategory != $this->getRateCategory()) {
                $rateCategory = $newRateCategory;
                $idRoomRate = $newIdRoomRate;
                $rtnMessage .= 'Room Rate Changed.  ';
                $rateGlideDays = $this->getRateGlideCredit();
            } else {
                $rateCategory = $this->getRateCategory();
                $idRoomRate = $this->getIdRoomRate();

                // add previous span duration to visit's rate glide credit.
                $rateGlideDays = $this->getRateGlideCredit() +  $chgDayDT->diff($spanStartDT, true)->days;
            }

            // Is the change in the future?
            if ($isFutureChange) {
                $newStatus = VisitStatus::Reserved;
                $rtnMessage .= 'Guests scheduled to Change Rooms on ' . $chgDT->format('D M jS, Y') . '.  ';
            } else {
                $newStatus = VisitStatus::NewSpan;
                $rtnMessage .= 'Guests Changed Rooms.  ';
            }

            // Change rooms on date given.
            $this->cutInNewSpan(
                $dbh,
                $resc,
                $newStatus,
                $rateCategory,
                $idRoomRate,
                $this->getPledgedRate(),
                $this->visitRS->Expected_Rate->getStoredVal(),
                $this->visitRS->idRateAdjust->getStoredVal(),
                $chgDT,
                $chgEndDT,
                intval($this->visitRS->Span->getStoredVal(), 10) + 1,
                0,
                $rateGlideDays);


            // Change date today?
            if ($chgDT->format('Y-m-d') == date('Y-m-d')) {
                foreach ($rooms as $r) {
                    $r->putTurnOver();
                    $r->saveRoom($dbh, $uS->username, TRUE);
                }
            }
        }

        // Send email
        if ($uS->NoReplyAddr != '' && ($uS->Guest_Track_Address != '' || $houseKeepingEmail != '')) {

            try {
                $mail = new HHKMailer($dbh);

                $mail->From = $uS->NoReplyAddr;
                $mail->FromName = htmlspecialchars_decode($uS->siteName, ENT_QUOTES);
                $mail->addReplyTo($uS->NoReplyAddr, htmlspecialchars_decode($uS->siteName, ENT_QUOTES));

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

                $mail->send();

            } catch (\Exception $ex) {
                $rtnMessage .= 'Email Failed.  ' . $mail->ErrorInfo;
            }
        }

        return $rtnMessage;
    }

    /**
     * Summary of replaceRoomRate: change the rate for this span.
     * @param \PDO $dbh
     * @param \HHK\Tables\Visit\VisitRS $visitRs
     * @param string $newRateCategory
     * @param float $pledgedRate
     * @param float $rateAdjust
     * @param string $uname
     * @return string
     */
    public static function replaceRoomRate(\PDO $dbh, VisitRs $visitRs, $newRateCategory, $pledgedRate, $rateAdjust, $idRateAdjust, $uname) {

        $uS = Session::getInstance();
        $reply = "";

        // Get the idRoomRate
        $pm = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
        $rateRs = $pm->getCategoryRateRs(0, $newRateCategory);


        $visitRs->Pledged_Rate->setNewVal($pledgedRate);
        $visitRs->Rate_Category->setNewVal($newRateCategory);
        $visitRs->Expected_Rate->setNewVal($rateAdjust);
        $visitRs->idRateAdjust->setNewVal($idRateAdjust);
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

    /**
     * Summary of changePledgedRate:  Split the span into a new span at $chgDT
     * @param \PDO $dbh
     * @param string $newRateCategory
     * @param float $pledgedRate
     * @param float $rateAdjust
     * @param string $uname
     * @param \DateTimeInterface $chgDT
     * @param mixed $useRateGlide
     * @param mixed $stayOnLeave
     * @return string
     */
    public function changePledgedRate(\PDO $dbh, $newRateCategory, $pledgedRate, $rateAdjust, $idRateAdjust, $chgDT, $stayOnLeave = 0) {

        $uS = Session::getInstance();
        $this->getResource($dbh);

        // Get the idRoomRate
        $pm = AbstractPriceModel::priceModelFactory($dbh, $uS->RoomPriceModel);
        $rateRs = $pm->getCategoryRateRs(0, $newRateCategory);

        // Temporarily override max occupants, as the room will seem to double.
        $tempOverrideMaxOcc = $this->overrideMaxOccupants;
        $this->overrideMaxOccupants = true;

        $this->cutInNewSpan(
            $dbh, $this->resource,
            VisitStatus::ChangeRate,
            $newRateCategory,
            $rateRs->idRoom_rate->getStoredVal(),
            $pledgedRate,
            $rateAdjust,
            $idRateAdjust,
            $chgDT,
            new \DateTime(),
            (intval($this->visitRS->Span->getStoredVal(), 10) + 1),
            $stayOnLeave);

        $this->overrideMaxOccupants = $tempOverrideMaxOcc;


        // change reservation entry
        $resv = Reservation_1::instantiateFromIdReserv($dbh, $this->visitRS->idReservation->getStoredVal());

        if ($resv->isNew() === FALSE) {
            $resv->setFixedRoomRate($pledgedRate);
            $resv->setRateAdjust($rateAdjust);
            $resv->setRoomRateCategory($newRateCategory);
            $resv->setIdRoomRate($rateRs->idRoom_rate->getStoredVal());

            $resv->saveReservation($dbh, $resv->getIdRegistration(), $uS->username);
        }

        return "Room Rate Changed.  ";

    }

    /**
     * Closes the old span with $visitStatus, and starts a new one with the same status as the replaced span.
     *
     * @param \PDO $dbh
     * @param AbstractResource $resc
     * @param string $visitStatus  use to close out old visit span
     * @param string $newRateCategory
     * @param integer $newRateId
     * @param float $pledgedRate
     * @param float $rateAdjust
     * @param string $uname
     * @param \DateTimeInterface $chgDT
     * @param \DateTimeInterface $chgEndDT
     * @param integer $newSpan  span Id for new visit span.
     * @param integer $stayOnLeave
     * @throws RuntimeException
     */
    protected function cutInNewSpan(\PDO $dbh, AbstractResource $resc, $visitStatus, $newRateCategory, $newRateId, $pledgedRate, $rateAdjust, $idRateAdjust, DateTimeInterface $chgDT, $chgEndDT, $newSpan, $stayOnLeave, $rateGlideDays = 0) {

        $uS = Session::getInstance();
        $this->stays = [];

        $now = new \DateTimeImmutable();
        $today = $now->setTime(0, 0, 0);
        $chgDayDT = new \DateTime($chgDT->format('Y-m-d 0:0:0'));

        // Load all stays for this span
        $this->loadStays($dbh, '');  // empty string loads all stays of all statuses

        // Change Date in the future?
        if ($chgDayDT > $today) {
            // No change to current visit status.

            $newSpanStatus = $visitStatus;
            $newSpanEnd = '';

            $this->visitRS->Expected_Departure->setNewVal($chgDT->format("Y-m-d $uS->CheckOutTime:00:00"));

        } else {
            // End current span
            $newSpanStatus = $this->visitRS->Status->getStoredVal();  // use the current visit status for the new span.
            $this->visitRS->Status->setNewVal($visitStatus);          // set the current visit status to the one passed in.

            $newSpanEnd = $this->visitRS->Span_End->getStoredVal();

            $this->visitRS->Span_End->setNewVal($chgDT);
        }

        // Save the old visit span.
        $this->updateVisitRecord($dbh, $uS->username);

        // Start the new visit span.

        // copy old values for new visit rs
        foreach ($this->visitRS as $p) {
            if ($p instanceof DB_Field) {
                $p->setNewVal($p->getStoredVal());
            }
        }

        $this->resource = $resc;

        // Insert new visit span
        $this->visitRS->idResource->setNewVal($resc->getIdResource());
        $this->visitRS->Span->setNewVal($newSpan);
        $this->visitRS->Span_End->setNewVal($newSpanEnd);
        $this->visitRS->Span_Start->setNewVal($chgDT->format('Y-m-d H:i:s'));
        $this->visitRS->Status->setNewVal($newSpanStatus);
        $this->visitRS->Pledged_Rate->setNewVal($pledgedRate);
        $this->visitRS->Rate_Category->setNewVal($newRateCategory);
        $this->visitRS->idRoom_rate->setNewVal($newRateId);
        $this->visitRS->Expected_Rate->setNewVal($rateAdjust);
        $this->visitRS->idRateAdjust->setNewVal($idRateAdjust);
        $this->visitRS->Rate_Glide_Credit->setNewVal($rateGlideDays);
        $this->visitRS->Timestamp->setNewVal(date('Y-m-d H:i:s'));

        if ($chgDayDT > $today) {
            // Future change, set the expected departure date to the end date.
            $this->visitRS->Expected_Departure->setNewVal($chgEndDT->format("Y-m-d $uS->CheckOutTime:00:00"));
        }

        // Save the new visit span.
        $idVisit = EditRS::insert($dbh, $this->visitRS);

        if ($idVisit == 0) {
            throw new RuntimeException('[cutinNewSpan()] Visit span insert failed.   ');
        }

        $logTexti = VisitLog::getInsertText($this->visitRS);
        VisitLog::logVisit($dbh, $this->getIdVisit(), $newSpan, $resc->getIdResource(), $this->visitRS->idRegistration->getStoredVal(), $logTexti, "insert", $uS->username);

        EditRS::updateStoredVals($this->visitRS);

        // Don't need to replace the stays if the change date is in the future.
        if ($chgDayDT <= $today) {
            $this->replaceStays($dbh, $visitStatus, $uS->username, $stayOnLeave);
        }

    }

    /**
     * Summary of replaceStays: copies appropriate stays into a new visit span
     * @param \PDO $dbh
     * @param mixed $oldVisitStatus
     * @param mixed $uname
     * @param mixed $stayOnLeave
     * @throws \HHK\Exception\RuntimeException
     * @return void
     */
    protected function replaceStays(\PDO $dbh, $oldVisitStatus, $uname, $stayOnLeave = 0) {

        $oldStays = $this->stays;  // contains all the stays.
        $this->stays = [];

        $this->getResource($dbh);
        $rm = $this->resource->allocateRoom(0, $this->overrideMaxOccupants);
        // TODO:  This should be a check for the number of occupants in the room, not just the overrideMaxOccupants.

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


            // the following cannot happen if we are in the middle of updating the stays.  EKC 6/8/2025
            //
            // if($stayRS->Status->getStoredVal() == VisitStatus::Active){
            //     $rm = $this->resource->allocateRoom(1, $this->overrideMaxOccupants); //only allocate a room if the stay is checked in
            //     if ($rm === null) {
            //         throw new RuntimeException('Room is full.  ');
            //     }
            // }

            if ($stayStartDT >= $visitSpanStartDT) {
                // Just update the span id, room and status

                // update stay status if not checked out.
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

                // save critical data
                $oldStayStatus = $stayRS->Status->getStoredVal();
                $oldCkOutDate = $stayRS->Span_End_Date->getStoredVal();


                // Close old stay
                $stayRS->Status->setNewVal($oldVisitStatus);
                $stayRS->Span_End_Date->setNewVal($this->visitRS->Span_Start->getStoredVal());
                $stayRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
                $stayRS->Updated_By->setNewVal($uname);

                EditRS::update($dbh, $stayRS, array($stayRS->idStays));
                $logText = VisitLog::getUpdateText($stayRS);
                VisitLog::logStay($dbh, $this->getIdVisit(), $stayRS->Visit_Span->getStoredVal(), $stayRS->idRoom->getStoredVal(), $stayRS->idStays->getStoredVal(), $stayRS->idName->getStoredVal(), $this->visitRS->idRegistration->getStoredVal(), $logText, "update", $uname);

                EditRS::updateStoredVals($stayRS);


                // Create a new stay record
                $newStayRS = new StaysRS();

                // special handling for checked out stays
                if ($oldStayStatus == VisitStatus::CheckedOut) {

                    $newStayRS->Checkout_Date->setNewVal($oldCkOutDate);
                    $newStayRS->Status->setNewVal($oldStayStatus);

                } else {

                    $newStayRS->Status->setNewVal($this->visitRS->Status->getStoredVal());
                }

                $newStayRS->idName->setNewVal($stayRS->idName->getStoredVal());
                $newStayRS->idRoom->setNewVal($rm->getIdRoom());
                $newStayRS->Checkin_Date->setNewVal($stayRS->Checkin_Date->getStoredVal());
                $newStayRS->Span_End_Date->setNewVal($oldCkOutDate);
                $newStayRS->Span_Start_Date->setNewVal($this->visitRS->Span_Start->getStoredVal());
                $newStayRS->Expected_Co_Date->setNewVal($stayRS->Expected_Co_Date->getStoredVal());
                $newStayRS->On_Leave->setNewVal($stayOnLeave);
                $newStayRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));

                $this->stays[] = $newStayRS;
            }
        }

        $this->saveNewStays($dbh, $uname);
    }


    /**
     * Summary of checkOutGuest
     * @param \PDO $dbh
     * @param int $idGuest
     * @param string $dateDeparted
     * @param string $notes
     * @param bool $sendEmail
     * @return string
     */
    public function checkOutGuest(\PDO $dbh, $idGuest, $dateDeparted = "", $notes = "", $sendEmail = TRUE) {

        $stayRS = NULL;

        // Guest must be checked in
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
	                        $tbl->addBodyTr(HTMLTable::makeTd($idGuest) . HTMLTable::makeTd($g[0])
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
	                $mail = new HHKMailer($dbh);

	                $mail->From = $uS->NoReplyAddr;
	                $mail->FromName = htmlspecialchars_decode($uS->siteName, ENT_QUOTES);
	                $mail->addReplyTo($uS->NoReplyAddr, $uS->siteName);

	                $tos = explode(',', $uS->Guest_Track_Address);
	                foreach ($tos as $t) {
	                    $to = filter_var($t, FILTER_SANITIZE_EMAIL);
	                    if ($to !== FALSE && $to != '') {
	                        $mail->addAddress($to);
	                    }
	                }

	                $mail->isHTML(true);

	                $mail->Subject = htmlspecialchars_decode($subj, ENT_QUOTES);
	                $mail->msgHTML($gMarkup);
	                $mail->send();
	            }
	        } catch (\Exception $ex) {
	            $this->setErrorMessage("Failed to send email to house admin: " . $ex->getMessage());
	        }
        }

        return $guestName . " checked out on " . $dateDepartedDT->format('M j, Y');
    }

    /**
     * Summary of checkStaysEndVisit
     * @param \PDO $dbh
     * @param mixed $username
     * @param \DateTime $dateDeparted
     * @param bool $sendEmail
     * @return bool
     */
    protected function checkStaysEndVisit(\PDO $dbh, $username, DateTimeInterface $dateDeparted, $sendEmail) {

        $uS = Session::getInstance();

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

        //
        // Visit has ended.
        //


        // Check for last span > 0 being 0 days long, i.e. checked out same day as change of room or rate.
        if ($this->visitRS->Span->getStoredVal() > 0) {

            $visitCkedIn = new \DateTime($this->visitRS->Span_Start->getStoredVal());
            $visitCkedIn->setTime(0, 0);
            $endDate = new \DateTime($dateDeparted->format('Y-m-d 0:0:0'));

            if ($endDate == $visitCkedIn) {
                // Delete this new span .
                $this->removeSpanStub($dbh, $dateDeparted);
            }
        }

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
                $mail = new HHKMailer($dbh);

                $mail->From = $uS->NoReplyAddr;
                $mail->FromName = htmlspecialchars_decode($uS->siteName, ENT_QUOTES);
                $mail->addReplyTo($uS->NoReplyAddr, $uS->siteName);

                $tos = array_merge(explode(',', $uS->Guest_Track_Address), explode(',', $uS->HouseKeepingEmail));

                foreach ($tos as $t) {
                    $to = filter_var($t, FILTER_SANITIZE_EMAIL);
                    if ($to !== FALSE && $to != '') {
                        $mail->addAddress($to);
                    }
                }

                $mail->isHTML(true);

                $mail->Subject = htmlspecialchars_decode($subj, ENT_QUOTES);

                $mail->msgHTML($gMarkup);
                $mail->send();
            }
        } catch (\Exception $ex) {
        	$this->setErrorMessage($ex->getMessage());
        }

        $this->setInfoMessage("Visit Ended.  ");

        return TRUE;
    }

    /**
     * Summary of removeSpanStub
     * @param \PDO $dbh
     * @param \DateTime $dateDepartedDT
     * @throws \HHK\Exception\RuntimeException
     * @return void
     */
    protected function removeSpanStub(\PDO $dbh, DateTimeInterface $dateDepartedDT){

        $uS = Session::getInstance();

        // delete this visit span and all its stays
        $rowsAffected = $dbh->exec("Delete from visit where idVisit = ". $this->getIdVisit()." and Span = ". $this->getSpan() .";");

        if ($rowsAffected == 1){
            // remove any onleave stuff.
            $dbh->exec("Delete from visit_onleave where idVisit = ". $this->getIdVisit()." and Span = ". $this->getSpan() .";");

            // Delete stays
            $dbh->exec("Delete from stays where idVisit = ". $this->getIdVisit()." and Visit_Span = ". $this->getSpan() .";");

            $logText = VisitLog::getDeleteText($this->visitRS, $this->getIdVisit());
            VisitLog::logVisit($dbh, $this->visitRS->idVisit->getStoredVal(), $this->visitRS->Span->getStoredVal(), $this->visitRS->idResource->getStoredVal(), $this->visitRS->idRegistration->getStoredVal(), $logText, "delete", $uS->username);

            unset($this->visitRSs[$this->getSpan()]);
            unset($this->stays);
            $this->resource = NULL;

        } else {
            throw new RuntimeException("Remove stub: Visit record not found.");
        }

        // Load previous visit span
        $visitRS = new VisitRS();
        $visitRS->Span->setStoredVal(($this->getSpan() - 1));
        $visitRS->idVisit->setStoredVal($this->getIdVisit());
        $rows = EditRS::select($dbh, $visitRS, Array($visitRS->idVisit, $visitRS->Span));

        if (count($rows) == 0) {
            throw new RuntimeException("Remove stub: Previous Visit record not found.");
        }

        // load visitRS
        $this->visitRS = new VisitRs();
        EditRS::loadRow($rows[0], $this->visitRS);

        // Load and update previous stays
        $allStays = self::loadStaysStatic($dbh, $this->getIdVisit(), $this->getSpan(), '');
        foreach ($allStays as $stayRs) {

            $stayRs->Status->setNewVal(VisitStatus::CheckedOut);
            $stayRs->Checkout_Date->setNewVal($dateDepartedDT->format('Y-m-d H:i:s'));
            $stayRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $stayRs->Updated_By->setNewVal($uS->username);
            EditRS::update($dbh, $stayRs, array($stayRs->idStays));

            $logText = VisitLog::getUpdateText($stayRs);
            VisitLog::logStay($dbh, $this->getIdVisit(), $stayRs->Visit_Span->getStoredVal(), $stayRs->idRoom->getStoredVal(), $stayRs->idStays->getStoredVal(), $stayRs->idName->getStoredVal(), $this->visitRS->idRegistration->getStoredVal(), $logText, "update", $uS->username);
        }

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
        $staysToUpdate = [];
        $visitActive = FALSE;
        $stayStartDates = [];

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

            $newStayStart = filter_var($newStayStartDates[$stayRs->idStays->getStoredVal()], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

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
     * @param string $uname
     * @return array
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
            $coDate = filter_var($guestDates[$guestId], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

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

    /**
     * Summary of onLeaveStays
     * @param \PDO $dbh
     * @param string $visitStatus
     * @param mixed $changeDate
     * @param string $uname
     * @param mixed $stayOnLeave
     * @return void
     */
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
        $this->updateVisitRecord($dbh);

    }

    /**
     * Summary of endLeave
     * @param \PDO $dbh
     * @param string $returnDateStr
     * @return string
     */
    public function endLeave(\PDO $dbh, $returnDateStr) {

        $reply = '';
        $uS = Session::getInstance();

        if ($returnDateStr == '') {
            $returnDT = new \DateTimeImmutable();
        } else {
            $returnDT = new \DateTimeImmutable($returnDateStr);
        }

        if ($returnDT->getTimezone()->getName() != $uS->tz) {
            $returnDT = $returnDT->setTimezone(new \DateTimeZone($uS->tz));
        }

        $retDT = $returnDT->setTime(0, 0, 0);

        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        if ($retDT > $today) {

            return 'Cannot return from leave in the future.';

        } else {

            /**
             * @var \DateTimeImmutable $stayStartDT
             */
            $stayStartDT = NULL;

            // Get extended stay start date.
            foreach ($this->stays as $stayRS) {

                if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedIn && $stayRS->On_Leave->getStoredVal() > 0) {

                    $stayStartDT = new \DateTimeImmutable($stayRS->Span_Start_Date->getStoredVal());
                    break;
                }
            }

            // Make sure a stay was found.
            if (is_null($stayStartDT) === FALSE) {

                // return date must be later than stay start date.
                if ($stayStartDT->setTime(0,0,0) > $retDT) {
                    return 'Cannot return before the leave start date.  ';
                }

                $leaveDays = $retDT->diff($stayStartDT->setTime(0,0,0))->days;

                // Was the rate changed for the leave?
                $volRS = new Visit_onLeaveRS();
                $volRS->idVisit->setStoredVal($this->getIdVisit());
                $rows = EditRS::select($dbh, $volRS, array($volRS->idVisit));

                if (count($rows) > 0) {
                    // Rate was changed - Load changed info
                    EditRS::loadRow($rows[0], $volRS);

                    // And delete it.
                    EditRS::delete($dbh, $volRS, array($volRS->idVisit));
                } else {
                    // Rate not changed.
                    $volRS = NULL;
                }

                if ($leaveDays <= 0) {
                    // Undo Leave.
                    $reply .= $this->undoLeave($dbh, $returnDT, $volRS);

                } else {
                    // End Leave

                    if (is_null($volRS) === FALSE) {

                        // Rate was changed
                        $reply .= $this->changePledgedRate($dbh, $volRS->Rate_Category->getStoredVal(), $volRS->Pledged_Rate->getStoredVal(), $volRS->Rate_Adjust->getStoredVal(), $volRS->idRateAdjust->getStoredVal(), $returnDT, FALSE);
                        $reply .= "Leave ended. ";
                    } else {

                        // Check out all guest, check back in.
                        $this->onLeaveStays($dbh, VisitStatus::CheckedOut, $returnDT->format('Y-m-d H:i:s'), $uS->username, FALSE);
                        $reply .= "Leave ended. ";
                    }
                }
            }
        }

        return $reply;

    }

    /**
     * Summary of extendLeave
     * @param \PDO $dbh
     * @param string $extendDateStr
     * @return string
     */
    public function extendLeave(\PDO $dbh, $extendDateStr) {

        $reply = '';
        $uS = Session::getInstance();

        if ($extendDateStr == '') {
            return "Leave is not extended. ";
        } else {
            $extendDT = new \DateTimeImmutable($extendDateStr);
        }

        $retDT = $extendDT->setTime(0, 0, 0);

        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        if ($retDT >= $today) {
            // Extend leave

            if ($retDT->diff($today)->days > 7) {
                return 'Cannot extend a leave beyond 7 days from today. ';
            }

            $leaveDays = 0;

            // Get number of days for extended stay
            foreach ($this->stays as $stayRS) {

                if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedIn && $stayRS->On_Leave->getStoredVal() > 0) {

                    $stayStartDT = new \DateTimeImmutable($stayRS->Span_Start_Date);
                    $leaveDays = $retDT->diff($stayStartDT)->days + 1;
                    break;
                }
            }

            // Found a checked-in extended stay?
            if ($leaveDays > 0) {

                // Apply to each checked-in stay
                foreach ($this->stays as $stayRS) {

                    if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedIn) {

                        $stayRS->On_Leave->setNewVal($leaveDays);

                        $stayRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
                        $stayRS->Updated_By->setNewVal($uS->username);

                        EditRS::update($dbh, $stayRS, array($stayRS->idStays));
                        $logText = VisitLog::getUpdateText($stayRS);
                        VisitLog::logStay($dbh, $this->getIdVisit(), $stayRS->Visit_Span->getStoredVal(), $stayRS->idRoom->getStoredVal(), $stayRS->idStays->getStoredVal(), $stayRS->idName->getStoredVal(), $this->visitRS->idRegistration->getStoredVal(), $logText, "update", $uS->username);

                        EditRS::updateStoredVals($stayRS);
                    }
                }

                $reply .= "Guests leave extended. ";
            }

        } else {
            $reply .= $this->endLeave($dbh, $extendDateStr);
        }

        return $reply;

    }

    /**
     * Summary of beginLeave
     * @param \PDO $dbh
     * @param string $extendStartDate
     * @param int $extDays
     * @param bool $noCharge
     * @return string
     */
    public function beginLeave(\PDO $dbh, $extendStartDate, $extDays, $noCharge) {
        // Extend the visit

        $uS = Session::getInstance();
        $reply = '';
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        if ($extDays < 1 || $extDays > 60) {
            return '';
        }

        if ($extendStartDate != '') {

            $extndStartDT = new \DateTimeImmutable($extendStartDate);

        } else {
            //start today.
            $extndStartDT = new \DateTimeImmutable();
        }

        $extendDayDT = $extndStartDT->setTime(0,0,0);


        if ($extendDayDT < $today) {

            // is it less than the current span?
            $stayStartDT = null;

            // Get current latest stay start date
            foreach ($this->stays as $stayRS) {

                if ($stayRS->Status->getStoredVal() == VisitStatus::CheckedIn && $stayRS->On_Leave->getStoredVal() == 0) {

                    $tempDT = new \DateTimeImmutable($stayRS->Span_Start_Date);
                    $tempDT = $tempDT->setTime(0,0,0);

                    if (is_null($stayStartDT) || $tempDT > $stayStartDT) {
                        $stayStartDT = $tempDT;
                    }
                }
            }

            // Must start no earlier than the day after the stay start date.
            if (is_null($stayStartDT) || $extendDayDT <= $stayStartDT) {
                return  'Starting a Leave before the guest stay start date is not implemented.  ';
            }


        } else if ($extendDayDT > $today) {
            return  'Cannot start a Leave in the future.  ';
        }

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
            $reply .= $this->changePledgedRate($dbh, RoomRateCategories::Fixed_Rate_Category, 0, 0, 0, $extndStartDT, $extDays);
            $reply .= 'Guests on Leave.  ';

        } else {
            // continue with current room rate.

            // Remove any previous visit-on-leave
            $vol = new Visit_onLeaveRS();
            $vol->idVisit->setStoredVal($this->getIdVisit());
            EditRS::delete($dbh, $vol, array($vol->idVisit));

            // Check out all guest, check back in with OnLeave set.
            $this->onLeaveStays($dbh, VisitStatus::CheckedOut, $extndStartDT->format('Y-m-d H:i:s'), $uS->username, $extDays);
            $reply .= 'Guests on Leave.  ';
        }

        return $reply;

    }

    /**
     * Summary of undoLeave
     * @param \PDO $dbh
     * @param \DateTimeInterface $returnDT
     * @param mixed $volRS
     * @return string
     */
    protected function undoLeave(\PDO $dbh, $returnDT, $volRS) {

        $uS = Session::getInstance();
        $reply = '';

        if (is_null($volRS) === FALSE && $this->getSpan() > 0) {
            //
            // Changed Rate.  Need to delete new span and reset previuos span
            //

            $oldSpan = $this->getSpan() - 1;
            $prevStays = self::loadStaysStatic($dbh, $this->getIdVisit(), $oldSpan, VisitStatus::ChangeRate);

            if (count($prevStays) < 1) {
                // Huh? no previous stays to reset.
                return '';
            }

            $changedStays = $this->resetStays($dbh, $prevStays, $returnDT);

            // Delete current visit
            if ($changedStays > 0) {

                // Delete current visit
                EditRS::delete($dbh, $this->visitRS, array($this->visitRS->idVisit, $this->visitRS->Span));

                // Delete all stays in current visit
                $dbh->exec("delete from stays where idVisit = " . $this->getIdVisit() . " and Visit_Span = ".$this->getSpan());

                // Reset previous visit
                $vRs = new VisitRS();
                $vRs->idVisit->setStoredVal($this->getIdVisit());
                $vRs->Span->setStoredVal($oldSpan);
                $rows = EditRS::select($dbh, $vRs, array($vRs->idVisit, $vRs->Span));

                if (count($rows) == 1) {
                    EditRS::loadRow($rows[0], $vRs);

                    $vRs->Span_End->setNewVal('');
                    $vRs->Status->setNewVal(VisitStatus::CheckedIn);

                    $this->updateVisitRecordStatic($dbh, $vRs, $uS->username);

                    $reply = "Leave is undone. ";
                }
            }


        } else if (is_null($volRS)) {
            //
            // No rate change. (No span change)  Just delete checked-in stays and reset previous.
            //

            $prevStays = self::loadStaysStatic($dbh, $this->getIdVisit(), $this->getSpan(), VisitStatus::CheckedOut);

            if (count($prevStays) < 1) {
                // Huh? no previous stays to reset.
                return '';
            }

            $changedStays = $this->resetStays($dbh, $prevStays, $returnDT);

            if ($changedStays > 0) {
                // Delete old checked in stays
                foreach ($this->stays as $stayRS) {

                    EditRS::delete($dbh, $stayRS, array($stayRS->idStays));
                    $logText = VisitLog::getDeleteText($stayRS, $stayRS->idStays->getStoredVal());
                    VisitLog::logStay($dbh, $this->getIdVisit(), $stayRS->Visit_Span->getStoredVal(), $stayRS->idRoom->getStoredVal(), $stayRS->idStays->getStoredVal(), $stayRS->idName->getStoredVal(), $this->visitRS->idRegistration->getStoredVal(), $logText, "delete", $uS->username);
                }

                $reply = "Leave is undone. ";
            }
        }

        return $reply;
    }

    /**
     * Summary of resetStays
     * @param \PDO $dbh
     * @param array $prevStays
     * @param \DateTime $returnDT
     * @return int
     */
    protected function resetStays(\PDO $dbh, $prevStays, DateTimeInterface $returnDT) {

        $uS = Session::getInstance();

        /**
         * @var DateTimeInterface $retDayDT
         */
        $retDayDT = $returnDT->setTime(0,0,0);
        $changedStays = 0;

        foreach ($prevStays as $stayRS) {

            $stayEndDT = new \DateTimeImmutable($stayRS->Span_End_Date->getStoredVal());
            $stayEndDT = $stayEndDT->setTime(0,0,0);

            if ($stayEndDT == $retDayDT && $stayRS->On_Leave->getStoredVal() == 0) {
                // reset this stay

                $stayRS->Span_End_Date->setNewVal('');
                $stayRS->Status->setNewVal(VisitStatus::CheckedIn);
                $stayRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
                $stayRS->Updated_By->setNewVal($uS->username);

                EditRS::update($dbh, $stayRS, array($stayRS->idStays));
                $logText = VisitLog::getUpdateText($stayRS);
                VisitLog::logStay($dbh, $this->getIdVisit(), $stayRS->Visit_Span->getStoredVal(), $stayRS->idRoom->getStoredVal(), $stayRS->idStays->getStoredVal(), $stayRS->idName->getStoredVal(), $this->visitRS->idRegistration->getStoredVal(), $logText, "update", $uS->username);

                $changedStays++;
            }
        }

        return $changedStays;
    }

    /**
     * Summary of loadStaysStatic
     * @param \PDO $dbh
     * @param int $idVisit
     * @param int $span
     * @param string $statusFilter
     * @return array<StaysRS>
     */
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

    /**
     * Summary of loadStays
     * @param \PDO $dbh
     * @param string $statusFilter
     * @return void
     */
    public function loadStays(\PDO $dbh, $statusFilter = VisitStatus::CheckedIn) {

        unset($this->stays);
        $this->stays = self::loadStaysStatic($dbh, $this->getIdVisit(), $this->getSpan(), $statusFilter);

    }

    /**
     * Summary of getIdRegistration
     * @return mixed
     */
    public function getIdRegistration() {
        return $this->visitRS->idRegistration->getStoredVal();
    }

    /**
     * Summary of getRateGlideCredit
     * @return mixed
     */
    public function getRateGlideCredit() {
        return $this->visitRS->Rate_Glide_Credit->getStoredVal();
    }

    /**
     * Summary of setRateGlideCredit
     * @param mixed $v
     * @return void
     */
    public function setRateGlideCredit($v) {
        $this->visitRS->Rate_Glide_Credit->setNewVal(intval($v, 10));
    }

    /**
     * Summary of isNew
     * @return mixed
     */
    public function isNew() {
        return $this->newVisit;
    }

    /**
     * Summary of setOverrideMaxOccupancy
     * @param mixed $val
     * @return void
     */
    public function setOverrideMaxOccupancy($val) {
        if ($val === TRUE) {
            $this->overrideMaxOccupants = TRUE;
        } else {
            $this->overrideMaxOccupants = FALSE;
        }
    }

    /**
     * Summary of getResource
     * @param mixed $dbh
     * @return AbstractResource|\HHK\House\Resource\PartitionResource|\HHK\House\Resource\RemoteResource|\HHK\House\Resource\RoomResource|null
     */
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

    /**
     * Summary of getidResource
     * @return int
     */
    public function getidResource() {
        if (is_null($this->resource)) {
            return $this->visitRS->idResource->getStoredVal();
        } else {
            return $this->resource->getIdResource();
        }
    }

    /**
     * Summary of setIdResource
     * @param int $v
     * @return void
     */
    public function setIdResource($v) {
        $this->visitRS->idResource->setNewVal($v);
    }

    /**
     * Summary of getIdVisit
     * @return int
     */
    public function getIdVisit() {
        return $this->visitRS->idVisit->getStoredVal();
    }

    /**
     * Summary of getSpan
     * @return int
     */
    public function getSpan() {
        return $this->visitRS->Span->getStoredVal();
    }

    /**
     * Summary of getidHospital_stay
     * @return int
     */
    public function getidHospital_stay() {
        return $this->visitRS->idHospital_stay->getStoredVal();
    }

    /**
     * Summary of getArrivalDate
     * @return mixed
     */
    public function getArrivalDate() {
        return $this->visitRS->Arrival_Date->getStoredVal();
    }

    /**
     * Summary of getSpanStart
     * @return mixed
     */
    public function getSpanStart() {
        return $this->visitRS->Span_Start->getStoredVal();
    }

    /**
     * Summary of getSpanEnd
     * @return mixed
     */
    public function getSpanEnd() {
        return $this->visitRS->Span_End->getStoredVal();
    }

    /**
     * Summary of getActualDeparture
     * @return mixed
     */
    public function getActualDeparture() {
        return $this->visitRS->Actual_Departure->getStoredVal();
    }

    /**
     * Summary of getExpectedDeparture
     * @return mixed
     */
    public function getExpectedDeparture() {
        return $this->visitRS->Expected_Departure->getStoredVal();
    }

    /**
     * Summary of getPledgedRate
     * @return mixed
     */
    public function getPledgedRate() {
        return $this->visitRS->Pledged_Rate->getStoredVal();
    }

    /**
     * Summary of setPledgedRate
     * @param mixed $pledgedRate
     * @return void
     */
    public function setPledgedRate($pledgedRate) {
        $this->visitRS->Pledged_Rate->setNewVal($pledgedRate);
    }

    /**
     * Summary of getIdRoomRate
     * @return mixed
     */
    public function getIdRoomRate() {
        return $this->visitRS->idRoom_rate->getStoredVal();
    }

    /**
     * Summary of setIdRoomRate
     * @param mixed $id
     * @return void
     */
    public function setIdRoomRate($id) {
        $this->visitRS->idRoom_rate->setNewVal($id);
    }

    /**
     * Summary of setRateCategory
     * @param mixed $strCategory
     * @return void
     */
    public function setRateCategory($strCategory) {
        $this->visitRS->Rate_Category->setNewVal($strCategory);
    }

    /**
     * Summary of getRateCategory
     * @return mixed
     */
    public function getRateCategory() {
        return $this->visitRS->Rate_Category->getStoredVal();
    }

    /**
     * Summary of getRoomTitle
     * @param \PDO $dbh
     * @return string
     */
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

    /**
     * Summary of getInfoMessage
     * @return string
     */
    public function getInfoMessage() {
    	return $this->infoMessage;
    }

    /**
     * Summary of getErrorMessage
     * @return string
     */
    public function getErrorMessage() {
    	return $this->errorMessage;
    }

    /**
     * Summary of setInfoMessage
     * @param mixed $v
     * @return void
     */
    protected function setInfoMessage($v) {
    	$this->infoMessage .= $v;
    }

    /**
     * Summary of setErrorMessage
     * @param mixed $v
     * @return void
     */
    protected function setErrorMessage($v) {
    	$this->errorMessage .= $v;
    }

    /**
     * Summary of setNotes
     * @param mixed $notes
     * @param mixed $username
     * @param mixed $roomTitle
     * @return void
     */
    public function setNotes($notes) {
        $this->visitRS->Notes->setNewVal($notes);
    }

    /**
     * Summary of getNotes
     * @return string
     */
    public function getNotes() {
        return is_null($this->visitRS->Notes->getStoredVal()) ? '' : $this->visitRS->Notes->getStoredVal();
    }

    /**
     * Summary of setNoticeToCheckout
     * @param mixed $v
     * @return void
     */
    public function setNoticeToCheckout($v) {
        $this->visitRS->Notice_to_Checkout->setNewVal($v);
    }

    /**
     * Summary of getNoticeToCheckout
     * @return mixed
     */
    public function getNoticeToCheckout() {
        return $this->visitRS->Notice_to_Checkout->getStoredVal();
    }

    /**
     * Summary of setRateAdjust
     * @param mixed $v
     * @return void
     */
    public function setRateAdjust($v) {
        $this->visitRS->Expected_Rate->setNewVal($v);
    }

    /**
     * Summary of getRateAdjust
     * @return mixed
     */
    public function getRateAdjust() {
        return $this->visitRS->Expected_Rate->getStoredVal();
    }

    /**
     * Summary of setIdRateAdjust
     * @param mixed $v
     * @return void
     */
    public function setIdRateAdjust($v) {
        $this->visitRS->idRateAdjust->setNewVal($v);
    }

    /**
     * Summary of getIdRateAdjust
     * @return mixed
     */
    public function getIdRateAdjust() {
        return $this->visitRS->idRateAdjust->getStoredVal();
    }

    /**
     * Summary of setReturnDate
     * @param mixed $v
     * @return void
     */
    public function setReturnDate($v) {
        $this->visitRS->Return_Date->setNewVal($v);
    }

    /**
     * Summary of getReturnDate
     * @return mixed
     */
    public function getReturnDate() {
        return $this->visitRS->Return_Date->getStoredVal();
    }

    /**
     * Summary of setPrimaryGuestId
     * @param mixed $id
     * @return void
     */
    public function setPrimaryGuestId($id) {
        $this->visitRS->idPrimaryGuest->setNewVal($id);
    }

    /**
     * Summary of getIdReservation
     * @return mixed
     */
    public function getIdReservation() {
        return $this->visitRS->idReservation->getStoredVal();
    }

    /**
     * Summary of getPrimaryGuestId
     * @return mixed
     */
    public function getPrimaryGuestId() {
        return $this->visitRS->idPrimaryGuest->getStoredVal();
    }

    /**
     * Summary of setReservationId
     * @param mixed $id
     * @return void
     */
    public function setReservationId($id) {
        $this->visitRS->idReservation->setNewVal($id);
    }

    /**
     * Summary of setIdHospital_stay
     * @param mixed $id
     * @return void
     */
    public function setIdHospital_stay($id) {
        $this->visitRS->idHospital_stay->setNewVal($id);
    }

    /**
     * Summary of getReservationId
     * @return mixed
     */
    public function getReservationId() {
        return $this->visitRS->idReservation->getStoredVal();
    }

    /**
     * Summary of getKeyDeposit
     * @return mixed
     */
    public function getKeyDeposit() {
        return $this->visitRS->Key_Deposit->getStoredVal();
    }

    /**
     * Summary of setKeyDeposit
     * @param mixed $v
     * @return void
     */
    public function setKeyDeposit($v) {
        $this->visitRS->Key_Deposit->setNewVal($v);
    }

    /**
     * Summary of getVisitStatus
     * @return mixed
     */
    public function getVisitStatus() {
        return $this->visitRS->Status->getStoredVal();
    }

    /**
     * Summary of getExtPhoneInstalled
     * @return mixed
     */
    public function getExtPhoneInstalled() {
        return $this->visitRS->Ext_Phone_Installed->getStoredVal();
    }

    /**
     * Summary of setExtPhoneInstalled
     * @return void
     */
    public function setExtPhoneInstalled() {
        $this->visitRS->Ext_Phone_Installed->setNewVal(1);
    }

}

