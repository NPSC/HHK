<?php

namespace HHK\House\Reservation;

use HHK\HTMLControls\{HTMLContainer, HTMLInput, HTMLTable};
use HHK\House\Constraint\{ConstraintsReservation, ConstraintsVisit};
use HHK\House\Hospital\HospitalStay;
use HHK\House\Resource\AbstractResource;
use HHK\House\Room\Room;
use HHK\Member\RoleMember\GuestMember;
use HHK\Note\{LinkNote, Note};
use HHK\SysConst\{MemBasis, ReservationStatus, RoomState, VisitStatus, RoomRateCategories, ItemId, InvoiceStatus};
use HHK\TableLog\{ReservationLog, VisitLog};
use HHK\Tables\EditRS;
use HHK\Tables\Registration\RegistrationRS;
use HHK\Tables\Reservation\ReservationRS;
use HHK\Tables\House\{ResourceRS, RoomRS};
use HHK\sec\Labels;
use HHK\sec\Session;
use HHK\Exception\RuntimeException;
use HHK\Exception\UnexpectedValueException;
use HHK\US_Holidays;
use HHK\SysConst\ReservationStatusType;

/**
 * Reservation_1.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Reservation_1
 *
 */
class Reservation_1 {

    const ROOM_TOO_SMALL = 'Too Small';
    const ROOM_UNAVAILABLE = '';
    const ROOM_NOT_SUITABLE = 'Not Suitable';

    /**
     * Summary of reservRs
     * @var ReservationRS
     */
    protected $reservRs;

    /**
     * Summary of reservConstraints
     * @var ConstraintsReservation
     */
    protected $reservConstraints;

    /**
     * Summary of visitConstraints
     * @var ConstraintsVisit
     */
    protected $visitConstraints;

    /**
     * Summary of boDays
     * @var mixed
     */
    protected $boDays;

    /**
     * Summary of startHolidays
     * @var mixed
     */
    protected $startHolidays;

    /**
     * Summary of endHolidays
     * @var mixed
     */
    protected $endHolidays;

    /**
     * Summary of idPsg
     * @var int
     */
    protected $idPsg;

    /**
     * Summary of idHospital
     * @var int
     */
    protected $idHospital;

    /**
     * Summary of idAssociation
     * @var int
     */
    protected $idAssociation;

    /**
     * Summary of idVisit
     * @var int
     */
    protected $idVisit;

    /**
     * Summary of constrainedRooms
     * @var array
     */
    protected $constrainedRooms;

    /**
     * Summary of availableResources
     * @var array
     */
    protected $availableResources = array();

    /**
     * Summary of untestedResources
     * @var array
     */
    protected $untestedResources = array();

    /**
     * Summary of resultMessage
     * @var string
     */
    protected $resultMessage = '';

    /**
     * Summary of reserveStatusType
     * @var string
     */
    protected $reserveStatusType;

    /**
     * Summary of idResource
     * @var int
     */
    protected $idResource;

    /**
     * Summary of idReferralDoc
     * @var int
     */
    protected $idReferralDoc;

    /**
     * Summary of expectedArrival
     * @var mixed
     */
    protected $expectedArrival;

    /**
     * Summary of expectedDeparture
     * @var mixed
     */
    protected $expectedDeparture;

    /**
     * Summary of numGuests
     * @var int
     */
    protected $numGuests;

    /**
     * Summary of roomTitle
     * @var string
     */
    protected $roomTitle;


    /**
     * Summary of __construct
     * @param \HHK\Tables\Reservation\ReservationRS $reservRs
     */
    public function __construct(ReservationRS $reservRs) {

        $this->reservRs = $reservRs;
        $this->idVisit = -1;
        $this->expectedArrival = $reservRs->Expected_Arrival->getStoredVal();
        $this->expectedDeparture = $reservRs->Expected_Departure->getStoredVal();
        $this->numGuests = $reservRs->Number_Guests->getStoredVal();
        $this->idResource = $reservRs->idResource->getStoredVal();
        $this->idReferralDoc = $reservRs->idReferralDoc->getStoredVal();
        $this->roomTitle = '';

    }

    /**
     * Summary of getAvailableResources
     * @return array
     */
    public function getAvailableResources() {
        return $this->availableResources;
    }

    /**
     * Summary of getUntestedResources
     * @return array|mixed
     */
    public function getUntestedResources() {
        return $this->untestedResources;
    }

    /**
     * Summary of getResultMessage
     * @return string
     */
    public function getResultMessage() {
        return $this->resultMessage;
    }


    /**
     * Returns specified reservation ID with max span id.
     *
     * @param \PDO $dbh
     * @param integer $idResv
     * @return Reservation_1
     */
    public static function instantiateFromIdReserv(\PDO $dbh, $idResv) {

        $resvRs = new ReservationRS();

        $idReserv = intval($idResv, 10);

        if ($idReserv > 0) {

            $resvRs->idReservation->setStoredVal($idReserv);
            $rows = EditRS::select($dbh, $resvRs, array($resvRs->idReservation));

            if (count($rows) > 0) {
                $resvRs = new ReservationRS();
                EditRS::loadRow($rows[0], $resvRs);
            }
        }

        return new Reservation_1($resvRs);
    }

    /**
     * Summary of getPrePayment
     * @param \PDO $dbh
     * @param int $idResv
     * @return float
     */
    public static function getPrePayment(\PDO $dbh, $idResv) {

        $prePayment = 0.0;

        if ($idResv > 0) {

             $query = "select
        sum(il.Amount)
    from
        invoice_line il
            join
        invoice i ON il.Invoice_Id = i.idInvoice and il.Item_Id = ". ItemId::LodgingMOA . " and il.Deleted = 0
            join
    	reservation_invoice ri on ri.Reservation_Id = $idResv AND i.idInvoice = ri.Invoice_Id
    where
            i.Deleted = 0
            and i.`Status` = '" . InvoiceStatus::Paid . "'";

            $stmt = $dbh->query($query);

            $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

            if (count($rows) == 1) {
                $prePayment = floatval($rows[0][0]);
            }
        }

        return $prePayment;
    }

    /**
     * Summary of move
     * @param \PDO $dbh
     * @param int $startDelta
     * @param int $endDelta
     * @param string $uname
     * @param bool $forceNewResource
     * @return bool
     */
    public function move(\PDO $dbh, $startDelta, $endDelta, $uname, $forceNewResource = FALSE) {

        $startInterval = new \DateInterval('P' . abs($startDelta) . 'D');
        $endInterval = new \DateInterval('P' . abs($endDelta) . 'D');

        $newStartDT = new \DateTime($this->getExpectedArrival());
        $newEndDt = new \DateTime($this->getExpectedDeparture());

        if ($endDelta < 0 || $startDelta < 0) {

            // Move back
            $newEndDt->sub($endInterval);
            $newStartDT->sub($startInterval);

            // Validity check
            if ($endDelta < 0 && $startDelta == 0) {
                $endDATE = new \DateTime($newEndDt->format('Y-m-d 00:00:00'));
                $startDATE = new \DateTime($newStartDT->format('Y-m-d 00:00:00'));
                if ($endDATE <= $startDATE) {
                    $this->resultMessage = "The End date precedes the Start date.  ";
                    return FALSE;
                }
            }


        } else {

            // Spring ahead
            $newEndDt->add($endInterval);
            $newStartDT->add($startInterval);

            // Validity check
            if ($startDelta > 0 && $endDelta == 0) {
                $endDATE = new \DateTime($newEndDt->format('Y-m-d 00:00:00'));
                $startDATE = new \DateTime($newStartDT->format('Y-m-d 00:00:00'));
                if ($endDATE <= $startDATE) {
                    $this->resultMessage = "The End date precedes the Start date.  ";
                    return FALSE;
                }
            }
        }

        // Check for pre-existing visits
        $resvs = ReservationSvcs::getCurrentReservations($dbh, $this->getIdReservation(), $this->getIdGuest(), 0, $newStartDT, $newEndDt);
        if (count($resvs) > 0) {
            $this->resultMessage = "The Move overlaps another reservation or visit.  ";
            return FALSE;
        }

        $rescs = array();

        if ($this->getStatus() == ReservationStatus::Waitlist) {

            // move the reservation
            $this->setExpectedArrival($newStartDT->format('Y-m-d'));
            $this->setExpectedDeparture($newEndDt->format('Y-m-d'));

            $this->saveReservation($dbh, $this->getIdRegistration(), $uname);
            $this->resultMessage = 'Reservation moved';
            return TRUE;

        } else {

            // Check for vacant rooms
            $rescs = $this->findResources($dbh, $newStartDT->format('Y-m-d 17:00:00'), $newEndDt->format('Y-m-d 09:00:00'), $this->getNumberGuests(), array('room','rmtroom','part'), TRUE);

            if (count($rescs) > 0) {

                // move the reservation
                $this->setExpectedArrival($newStartDT->format('Y-m-d'));
                $this->setExpectedDeparture($newEndDt->format('Y-m-d'));

                // If my original resource is unavailable, use another
                $roomChanged = '';
                if (isset($rescs[$this->getIdResource()]) === FALSE || $forceNewResource) {

                    $keys = array_keys($rescs);
                    $this->setIdResource($keys[0]);

                    $resc = $rescs[$keys[0]];
                    $roomChanged = 'to room ' . $resc->getTitle() . '.  ';

                    if ($this->getStatus() == ReservationStatus::Waitlist) {
                        $this->setStatus(ReservationStatus::Committed);
                        $roomChanged .= 'New status is ' . $this->getStatusTitle($dbh, ReservationStatus::Committed);
                    }

                }

                $this->saveReservation($dbh, $this->getIdRegistration(), $uname);

                $this->resultMessage = 'Reservation changed ' . $roomChanged;
                return TRUE;

            } else {

                if ($forceNewResource) {

                    // move the reservation
                    $this->setExpectedArrival($newStartDT->format('Y-m-d'));
                    $this->setExpectedDeparture($newEndDt->format('Y-m-d'));

                    $this->setIdResource('0');
                    $this->setStatus(ReservationStatus::Waitlist);

                    $this->saveReservation($dbh, $this->getIdRegistration(), $uname);

                    $this->resultMessage = 'Reservation waitlisted';
                    return TRUE;

                } else {

                    $this->resultMessage = 'The date range is not available.  ';
                    return FALSE;
                }
            }
        }
    }

    /**
     * Summary of checkOut
     * @param \PDO $dbh
     * @param string $endDate
     * @param string $uname
     * @return void
     */
    public function checkOut(\PDO $dbh, $endDate, $uname) {

        $this->setStatus(ReservationStatus::Checkedout);
        $this->setActualDeparture($endDate);
        $this->saveReservation($dbh, $this->getIdRegistration(), $uname);
    }

    /**
     * Summary of saveConstraints
     * @param \PDO $dbh
     * @param array $pData
     * @return void
     */
    public function saveConstraints(\PDO $dbh, $pData) {

        if ($this->isNew()) {
            return;
        }

        $capturedConstraints = array();

        if (isset($pData['cbRS'])) {

            foreach ($pData['cbRS'] as $idConstraint => $v) {
                $capturedConstraints[$idConstraint] = $idConstraint;
            }

        }

        $cResv = $this->getConstraints($dbh);
        $cResv->saveConstraints($dbh, $capturedConstraints);

        $vConst = new ConstraintsVisit($dbh, $this->getIdReservation());
        $vConst->saveConstraints($dbh, $capturedConstraints);

        $this->getConstraints($dbh, TRUE);  //reservConstraints = $this->loadReservationConstraints($dbh, $this->getIdReservation());

    }

    /**
     * Summary of saveReservation
     * @param \PDO $dbh
     * @param int $idReg
     * @param string $uname
     * @return ReservationRS
     */
    public function saveReservation(\PDO $dbh, $idReg, $uname) {

        $this->reservRs->Updated_By->setNewVal($uname);
        $this->reservRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

        $this->reservRs->Number_Guests->setNewVal($this->getNumberGuests());
        $this->reservRs->Expected_Arrival->setNewVal($this->getExpectedArrival());
        $this->reservRs->Expected_Departure->setNewVal($this->getExpectedDeparture());
        $this->reservRs->idResource->setNewVal($this->getIdResource());
        $this->reservRs->idReferralDoc->setNewVal($this->getIdReferralDoc());

        // Insert or Update
        if ($this->reservRs->idReservation->getStoredVal() == 0) {

            // INSERT
            $this->reservRs->idRegistration->setNewVal($idReg);

            $idResv = EditRS::insert($dbh, $this->reservRs);

            $this->reservRs->idReservation->setNewVal($idResv);

            $logText = VisitLog::getInsertText($this->reservRs);

            EditRS::updateStoredVals($this->reservRs);

            ReservationLog::logReservation($dbh, $idResv,
                    $this->reservRs->idRegistration->getStoredVal(),
                    $this->reservRs->idHospital_Stay->getStoredVal(),
                    $this->reservRs->idResource->getStoredVal(),
                    $this->reservRs->idRoom_rate->getStoredVal(),
                    $this->reservRs->idGuest->getStoredVal(),
                    $logText, "insert", $uname);

            // Load constraints with new Id.
            $this->getConstraints($dbh, TRUE);

        } else {

            // UPDATE
            $updt = EditRS::update($dbh, $this->reservRs, array($this->reservRs->idReservation));

            if ($updt == 1) {
                $logText = VisitLog::getUpdateText($this->reservRs);

                EditRS::updateStoredVals($this->reservRs);

                ReservationLog::logReservation($dbh, $this->reservRs->idReservation->getStoredVal(),
                    $this->reservRs->idRegistration->getStoredVal(),
                    $this->reservRs->idHospital_Stay->getStoredVal(),
                    $this->reservRs->idResource->getStoredVal(),
                    $this->reservRs->idRoom_rate->getStoredVal(),
                    $this->reservRs->idGuest->getStoredVal(),
                    $logText, "update", $uname);
            }
        }

        return $this->reservRs;
    }

    /**
     * Summary of deleteMe
     * @param \PDO $dbh
     * @param bool $deleteHost
     * @param string $uname
     * @throws \HHK\Exception\RuntimeException
     * @return bool
     */
    public function deleteMe(\PDO $dbh, $uname) {

        if ($this->getStatus() == ReservationStatus::Staying || $this->getStatus() == ReservationStatus::Checkedout) {
            throw new RuntimeException('Reservation cannot be deleted.  Delete the Visit instead.');
        }

        // Set referal doc to archived
        $dbh->exec("update document d left join reservation r on d.idDocument = r.idReferralDoc set d.Status = 'ar' where r.idReservation = " . $this->getIdReservation());

        // Delete
        $cnt = $dbh->exec("Delete from reservation where idReservation = " . $this->getIdReservation());

        if ($cnt == 1) {
            $logText = VisitLog::getDeleteText($this->reservRs, $this->getIdReservation());

            ReservationLog::logReservation($dbh, $this->reservRs->idReservation->getStoredVal(),
                $this->reservRs->idRegistration->getStoredVal(),
                $this->reservRs->idHospital_Stay->getStoredVal(),
                $this->getIdResource(),
                0, //$this->reservRs->idRoom_rate->getStoredVal(),
                $this->reservRs->idGuest->getStoredVal(),
                $logText, "delete", $uname);

            $dbh->exec("Delete from reservation_guest where idReservation = " . $this->getIdReservation());
            $dbh->exec("Delete from constraint_entity where idEntity = " . $this->getIdReservation());
            $dbh->exec("delete from reservation_multiple where Child_Id = " . $this->getIdReservation());
            $dbh->exec("delete from reservation_multiple where Host_Id = " . $this->getIdReservation());

            $this->reservRs = NULL;

            return TRUE;
        }

        return FALSE;

    }

    /**
     * Summary of loadNonCleaningDays
     * @param \PDO $dbh
     * @return array<int>
     */
    public static function loadNonCleaningDays(\PDO $dbh) {

        $stmt = $dbh->query("select Code from gen_lookups where Table_Name = 'Non_Cleaning_Day';");
        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        $boDays = array();

        foreach ($rows as $r) {
            $boDays[] = intval($r[0], 10);
        }

        return $boDays;

    }

    /**
     * Summary of setNonCleaningDays
     * @param \PDO $dbh
     * @return void
     */
    public function setNonCleaningDays(\PDO $dbh) {
        if (is_null($this->boDays)) {
            $this->boDays = $this->loadNonCleaningDays($dbh);
        }
    }

    /**
     * Summary of adjustArrivalDate
     * @param mixed $stringDate
     * @return string
     */
    protected function adjustArrivalDate($stringDate) {
        return $this->adjustStartDate($stringDate, $this->startHolidays, $this->endHolidays, $this->boDays);
    }

    /**
     * Summary of adjustStartDate
     * @param mixed $stringDate
     * @param mixed $startHolidays
     * @param mixed $endHolidays
     * @param mixed $boDays
     * @return string
     */
    protected static function adjustStartDate($stringDate, $startHolidays, $endHolidays, $boDays) {

        $arDate = new \DateTime($stringDate);

        // Initially check holidays
        while ($startHolidays->is_holiday($arDate->format('U')) || $endHolidays->is_holiday($arDate->format('U'))) {
            $arDate->sub(new \DateInterval('P1D'));
        }

        $dateInfo = getDate($arDate->format('U'));
        $limit = 5;

        // Now Check weekends
        while (array_search($dateInfo['wday'], $boDays) !== FALSE && $limit-- > 0) {
            // move the beginning of the stay back a day

            $arDate->sub(new \DateInterval('P1D'));
            $dateInfo = getDate($arDate->format('U'));

        }

        // Finally check holidays again
        while ($startHolidays->is_holiday($arDate->format('U')) || $endHolidays->is_holiday($arDate->format('U'))) {
            $arDate->sub(new \DateInterval('P1D'));
        }

        return $arDate->format('Y-m-d H:i:s');
    }

    /**
     * Summary of adjustDepartureDate
     * @param mixed $stringDate
     * @return string
     */
    protected function adjustDepartureDate($stringDate) {
        return $this->adjustEndDate($stringDate, $this->startHolidays, $this->endHolidays, $this->boDays);
    }

    /**
     * Summary of adjustEndDate
     * @param mixed $stringDate
     * @param mixed $startHolidays
     * @param mixed $endHolidays
     * @param mixed $boDays
     * @return string
     */
    protected static function adjustEndDate($stringDate, $startHolidays, $endHolidays, $boDays) {

        $arDate = new \DateTime($stringDate);

        // add all consecutive holidays
        while ($startHolidays->is_holiday($arDate->format('U')) || $endHolidays->is_holiday($arDate->format('U'))) {
            $arDate->add(new \DateInterval('P1D'));
        }


        $dateInfo = getDate($arDate->format('U'));
        $limit = 5;

        // Add all consecutive non work weekdays
        while (array_search($dateInfo['wday'], $boDays) !== FALSE && $limit-- > 0) {
            // add a day to the end of the stay
            $arDate->add(new \DateInterval('P1D'));
            $dateInfo = getDate($arDate->format('U'));
        }

        // Finally, check for holidays again.
        while ($startHolidays->is_holiday($arDate->format('U')) || $endHolidays->is_holiday($arDate->format('U'))) {
            $arDate->add(new \DateInterval('P1D'));
        }

        return $arDate->format('Y-m-d H:i:s');
    }

    /**
     * Find resources filtered by this resource attribute array
     *
     * @param \PDO $dbh
     * @param string $expectedArrival
     * @param string $expectedDeparture
     * @param int $numOccupants
     * @param array $resourceTypes
     * @param bool $omitSelf
     * @return array of resource objects
     */
    public function findResources(\PDO $dbh, $expectedArrival, $expectedDeparture, $numOccupants, array $resourceTypes, $omitSelf = FALSE) {

        $this->untestedResources = $this->loadAvailableResources($dbh, $expectedArrival, $expectedDeparture, $numOccupants, $resourceTypes, $omitSelf);

        $this->availableResources = $this->testResources($dbh, $this->untestedResources);

        foreach ($this->availableResources as $resc) {

            if ($resc->getMaxOccupants() < $this->getNumberGuests()) {
                $resc->optGroup = 'Too Small';
            }
        }

        // Return an array of Resource objs.
        return $this->availableResources;
    }

    /**
     * Finds all available open resources and returns both the filtered rooms and the unsuitable rooms
     *
     * @param \PDO $dbh
     * @param string $expectedArrival
     * @param string $expectedDeparture
     * @param int $numOccupants
     * @param array $resourceTypes
     * @param bool $omitSelf
     * @return array of Resource objects .
     */
    public function findGradedResources(\PDO $dbh, $expectedArrival, $expectedDeparture, $numOccupants, array $resourceTypes, $omitSelf = FALSE) {

        $this->untestedResources = $this->loadAvailableResources($dbh, $expectedArrival, $expectedDeparture, $numOccupants, $resourceTypes, $omitSelf);
        $this->availableResources = array();

        // Returns an array of Resource objs.
        $testedRows = $this->testResources($dbh, $this->untestedResources);

        foreach ($this->untestedResources as $r) {

            if (isset($testedRows[$r['idResource']])) {

                $resc = $testedRows[$r['idResource']];
                $resc->optGroup = '';

                if ($resc->getMaxOccupants() < $this->getNumberGuests()) {
                    $resc->optGroup = self::ROOM_TOO_SMALL;
                }

                $this->availableResources[$r['idResource']] = $resc;

            } else {

                $resourceRS = new ResourceRS();
                EditRS::loadRow($r, $resourceRS);
                $resc = AbstractResource::getThisFromRS($dbh, $resourceRS);
                $resc->optGroup = self::ROOM_NOT_SUITABLE;

                if ($resc->getMaxOccupants() < $this->getNumberGuests()) {
                    $resc->optGroup = self::ROOM_TOO_SMALL;
                }

                $this->availableResources[$r['idResource']] = $resc;
            }

        }

        return $this->availableResources;
    }

    /**
     *
     * @param \PDO $dbh
     * @param int $idResource
     * @param string $expectedArrival
     * @param string $expectedDeparture
     * @param int $numOccupants
     * @param array $resourceTypes
     * @param bool $omitSelf
     * @return boolean
     */
    public function isResourceOpen(\PDO $dbh, $idResource, $expectedArrival, $expectedDeparture, $numOccupants, array $resourceTypes, $omitSelf = FALSE, $isAuthorized = FALSE) {

        // Only check if its a real room
        if ($idResource > 0) {

            if ($isAuthorized) {
                $resources = $this->findGradedResources($dbh, $expectedArrival, $expectedDeparture, $numOccupants, $resourceTypes, $omitSelf);
            } else {
                $resources = $this->findResources($dbh, $expectedArrival, $expectedDeparture, $numOccupants, $resourceTypes, $omitSelf);
            }

            return isset($resources[$idResource]);

        }

        return TRUE;

    }

    /**
     * Summary of loadAvailableResources
     * @param \PDO $dbh
     * @param mixed $expectedArrival
     * @param mixed $expectedDeparture
     * @param mixed $numOccupants
     * @param mixed $resourceTypes
     * @param mixed $omitSelf
     * @return array
     */
    protected function loadAvailableResources(\PDO $dbh, $expectedArrival, $expectedDeparture, $numOccupants, array $resourceTypes, $omitSelf = FALSE) {

        if ($expectedArrival == '' || $expectedDeparture == '') {
            return array();
        }

        $uS = Session::getInstance();

        // Resource types
        $typeList = '';
        foreach ($resourceTypes as $t) {
            if ($typeList == '') {
                $typeList = "'" . $t . "'";
            } else {
                $typeList .= ",'" . $t . "'";
            }
        }

        if ($typeList != '') {
            $typeList =  " rc.`Type` in (" . $typeList . ") ";
        }

        $expectedDepartureDT = new \DateTime($expectedDeparture);
        if($uS->IncludeLastDay){
            $expectedDepartureDT->sub(new \DateInterval("P1D")); //allow checkout on first day of room retirement
        }

        // Get the list of available resources
        $stmtr = $dbh->query("select rc.*, sum(r.Max_Occupants) as `Max_Occupants`
from resource rc join resource_room rr on rc.idResource = rr.idResource left join `room` r on r.idRoom = rr.idRoom
where $typeList and (rc.`Retired_At` is null or date(rc.`Retired_At`) > '" . $expectedDepartureDT->format("Y-m-d") . "') group by rc.idResource having `Max_Occupants` >= $numOccupants order by rc.Util_Priority;");

        $rescRows = array();

        foreach ($stmtr->fetchAll(\PDO::FETCH_ASSOC) as $re) {
            $rescRows[$re["idResource"]] = $re;
        }


        $rows = $this->findRescsInUse($dbh, $expectedArrival, $expectedDeparture, $omitSelf);

        foreach ($rows as $r) {
            // remove these from the resources array
            if (isset($rescRows[$r['idResource']])) {
                unset($rescRows[$r['idResource']]);
            }
        }

        return $rescRows;

    }

    /**
     * Summary of findReservations
     * @param \PDO $dbh
     * @param mixed $expectedArrival
     * @param mixed $expectedDeparture
     * @param mixed $idResource
     * @return array
     */
    public static function findReservations(\PDO $dbh, $expectedArrival, $expectedDeparture, $idResource) {

        // Deal with non-cleaning days
        $boDays = self::loadNonCleaningDays($dbh);
        $startHolidays = new US_Holidays($dbh, date('Y', strtotime($expectedArrival)));
        $endHolidays = new US_Holidays($dbh, date('Y', strtotime($expectedDeparture)));

        $arr = self::adjustStartDate($expectedArrival, $startHolidays, $endHolidays, $boDays);
        $dep = self::adjustEndDate($expectedDeparture, $startHolidays, $endHolidays, $boDays);
        $statuses = "'" . ReservationStatus::UnCommitted . "','" . ReservationStatus::Committed . "'";

        //
        $query = "select r.idReservation from reservation r "
                . "where r.idResource = $idResource and r.Status in ($statuses) and DATE(ifnull(r.Actual_Arrival, r.Expected_Arrival)) <= DATE('$dep') "
                . "and DATE(ifnull(r.Actual_Departure, r.Expected_Departure)) > DATE('$arr')";
        $stmt = $dbh->query($query);


        // return an array of resourceId's
        return $stmt->fetchAll(\PDO::FETCH_NUM);

    }

    /**
     * Summary of findRescsInUse
     * @param \PDO $dbh
     * @param mixed $expectedArrival
     * @param mixed $expectedDeparture
     * @param mixed $omitSelf
     * @return array
     */
    protected function findRescsInUse(\PDO $dbh, $expectedArrival, $expectedDeparture, $omitSelf = FALSE) {

        // Deal with non-cleaning days
        $this->setNonCleaningDays($dbh);
        $this->startHolidays = new US_Holidays($dbh, date('Y', strtotime($expectedArrival)));
        $this->endHolidays = new US_Holidays($dbh, date('Y', strtotime($expectedDeparture)));

        $arr = $this->adjustArrivalDate($expectedArrival);
        $dep = $this->adjustDepartureDate($expectedDeparture);

        $uS = Session::getInstance();

        $omitTxt = '';
        $omitVisit = '';
        if ($omitSelf && $this->getIdReservation() > 0) {
            $omitTxt = " and r.idReservation != " . $this->getIdReservation();
            $omitVisit = " and v.idVisit != " . $this->getIdVisit($dbh);
        }

        $stat = "'" . ReservationStatus::Committed . "', '" . ReservationStatus::UnCommitted . "'";
        $vStat = "'" . VisitStatus::Pending . "', '" . VisitStatus::Cancelled . "'";

        // Find resources in use
        if (!$uS->IncludeLastDay) {

            $query = "select r.idResource "
                . "from reservation r where r.Status in ($stat) $omitTxt and DATE(r.Expected_Arrival) < DATE('$dep') and DATE(r.Expected_Departure) > DATE('$arr')
    union select ru.idResource from resource_use ru where DATE(ru.Start_Date) < DATE('$dep') and ifnull(DATE(ru.End_Date), DATE(now())) > DATE('$arr')
    union select v.idResource from visit v where v.Status not in ($vStat) $omitVisit and (case when v.Status != 'a' then DATE(v.Span_Start) != DATE(v.Span_End) else 1=1 end) and DATE(v.Arrival_Date) < DATE('$dep') and
    ifnull(AddDate(DATE(v.Span_End), -1), case when DATE(now()) >= DATE(v.Expected_Departure) then AddDate(DATE(now()), 1) else DATE(v.Expected_Departure) end) >= DATE('$arr')";

        } else {

            $query = "select r.idResource "
                . "from reservation r where r.Status in ($stat) $omitTxt and DATE(r.Expected_Arrival) < DATE('$dep') and DATE(r.Expected_Departure) > DATE('$arr')
    union select ru.idResource from resource_use ru where DATE(ru.Start_Date) < DATE('$dep') and ifnull(DATE(ru.End_Date), DATE(now())) > DATE('$arr')
    union select v.idResource from visit v where v.Status not in ($vStat) $omitVisit  and (case when v.Status != 'a' then DATE(v.Span_Start) != DATE(v.Span_End) else 1=1 end) and DATE(v.Arrival_Date) < DATE('$dep') and
    ifnull(DATE(v.Span_End), case when DATE(now()) > DATE(v.Expected_Departure) then AddDate(DATE(now()), 1) else DATE(v.Expected_Departure) end) > DATE('$arr')";
        }

        $stmt = $dbh->query($query);

        // return an array of resourceId's
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Summary of testResources
     * @param \PDO $dbh
     * @param mixed $rescRows
     * @return array
     */
    protected function testResources(\PDO $dbh, $rescRows) {

        $resources = array();

        $roomIds = $this->getConstrainedRoomsArray($dbh);


         // Make resource objs, test the room id's
        foreach ($rescRows as $r) {

            $resourceRS = new ResourceRS();
            EditRS::loadRow($r, $resourceRS);
            $resc = AbstractResource::getThisFromRS($dbh, $resourceRS);

            $rescRooms = $resc->getRooms();
            $rmIncludes = array();

            // look for bad rooms
            foreach ($rescRooms as $roomObj) {

                if (isset($roomIds[$roomObj->getIdRoom()]) || isset($roomIds[0])) {
                    $rmIncludes[$roomObj->getIdRoom()] = $roomObj->getIdRoom();
                }
            }

            if (count($rmIncludes) == count($rescRooms)) {
                $resources[$resc->getIdResource()] = $resc;
            }

        }

        // Return an array of tested Resource objs.
        return $resources;

    }

    /**
     * Summary of getConstrainedRoomsArray
     * @param \PDO $dbh
     * @return array<string>
     */
    protected function getConstrainedRoomsArray(\PDO $dbh) {

        if (is_null($this->constrainedRooms)) {
            $this->constrainedRooms = $this->loadConstrainedRooms($dbh);
        }

        return $this->constrainedRooms;
    }

    /**
     * Summary of loadConstrainedRooms
     * @param \PDO $dbh
     * @return array<string>
     */
    protected function loadConstrainedRooms(\PDO $dbh) {

        $roomIds = array();

        // Find any constrained rooms.  Comes back with roomId = 0 for no constraints
        $stmt = $dbh->query("call constraint_room(" . $this->getIdReservation() . " );");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $roomIds[$r['idEntity']] = '';
        }

        return $roomIds;
    }

    /**
     * Summary of testResource
     * @param \PDO $dbh
     * @param \HHK\House\Resource\AbstractResource $resc
     * @throws \HHK\Exception\UnexpectedValueException
     * @return bool
     */
    public function testResource(\PDO $dbh, AbstractResource $resc) {

        $pass = FALSE;
        $roomIds = $this->getConstrainedRoomsArray($dbh);

        $rescRooms = $resc->getRooms();
        $rmIncludes = array();

        if (count($rescRooms) < 1) {
            throw new UnexpectedValueException('Resource Id=' . $resc->getIdResource() . ' has no rooms');
        }

        // look for bad rooms
        foreach ($rescRooms as $roomObj) {

            if (isset($roomIds[$roomObj->getIdRoom()]) || isset($roomIds[0])) {
                $rmIncludes[$roomObj->getIdRoom()] = $roomObj->getIdRoom();
            }
        }

        if (count($rmIncludes) == count($rescRooms)) {
            $pass = TRUE;
        }

        return $pass;
    }

    /**
     * Summary of getConstraints
     * @param \PDO $dbh
     * @param mixed $refresh
     * @return ConstraintsReservation
     */
    public function getConstraints(\PDO $dbh, $refresh = FALSE) {

    	if (is_null($this->reservConstraints) || $refresh) {
    		$this->reservConstraints = new ConstraintsReservation($dbh, $this->getIdReservation());
    	}

    	return $this->reservConstraints;
    }

    /**
     * Summary of getVisitConstraints
     * @param \PDO $dbh
     * @param mixed $refresh
     * @return ConstraintsVisit
     */
    public function getVisitConstraints(\PDO $dbh, $refresh = FALSE) {

    	if (is_null($this->visitConstraints) || $refresh) {
    		$this->visitConstraints = new ConstraintsVisit($dbh, $this->getIdReservation());
    	}

    	return $this->visitConstraints;
    }

    /**
     * Summary of showListByStatus
     * @param \PDO $dbh
     * @param mixed $editPage
     * @param mixed $checkinPage
     * @param mixed $reservStatus
     * @param mixed $shoDirtyRooms
     * @param mixed $idResc
     * @param mixed $daysAhead
     * @param mixed $showConstraints
     * @return string
     */
    public static function showListByStatus(\PDO $dbh, $editPage, $checkinPage, $reservStatus = ReservationStatus::Committed, $shoDirtyRooms = FALSE, $idResc = NULL, $daysAhead = 2, $showConstraints = FALSE) {

        $dateAhead = new \DateTime();


        if ($daysAhead > 0) {
            $dateAhead->add(new \DateInterval('P' . $daysAhead . 'D'));
        }

        if (is_null($idResc) === FALSE) {

            $stmt = $dbh->prepare("select * from vresv_patient where ifnull(DATE(Actual_Arrival), DATE(`Expected_Arrival`)) <= DATE(:dte) and Status = '$reservStatus'  and idResource = :idr order by `Expected_Arrival`");
            $stmt->execute(array(':idr'=>$idResc, ':dte'=>$dateAhead->format('Y-m-d')));

        } else {

            $stmt = $dbh->prepare("select * from vresv_patient where ifnull(DATE(Actual_Arrival), DATE(`Expected_Arrival`)) <= DATE(:dte) and Status = '$reservStatus' order by `Expected_Arrival`");
            $stmt->execute(array(':dte'=>$dateAhead->format('Y-m-d')));
        }

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return self::showList($dbh, $rows, $editPage, $checkinPage, $reservStatus, $shoDirtyRooms, $showConstraints);

    }

    /**
     * Summary of showList
     * @param \PDO $dbh
     * @param mixed $rows
     * @param mixed $editPage
     * @param mixed $checkinPage
     * @param mixed $reservStatus
     * @param mixed $shoDirtyRooms
     * @param mixed $showConstraints
     * @return string
     */
    public static function showList(\PDO $dbh, $rows, $editPage, $checkinPage, $reservStatus = ReservationStatus::Committed, $shoDirtyRooms = FALSE, $showConstraints = FALSE) {

        $uS = Session::getInstance();

        // Get labels
        $labels = Labels::getLabels();

        $rooms = array();
        $markupPrepay = FALSE;

        $noCleaning = '';

        // Check-in button text
        $buttonText = 'Add ' . $labels->getString('MemberType', 'visitor', 'Guest');
        if ($reservStatus == ReservationStatus::Committed  || $reservStatus == ReservationStatus::Waitlist) {
            $buttonText = 'Check In';
        }

        // Pre-Payment markup
        if ($uS->AcceptResvPaymt && ($reservStatus == ReservationStatus::Committed  || $reservStatus == ReservationStatus::Waitlist || $reservStatus == ReservationStatus::UnCommitted)) {
            $markupPrepay = TRUE;
        }

        if (count($rows) > 0) {

        	$roomStatuses = readGenLookupsPDO($dbh, 'Room_Status');

            if ($shoDirtyRooms) {
                $cleanCodes = readGenLookupsPDO($dbh, 'Room_Cleaning_Days');

                foreach ($cleanCodes as $i) {
                    if ($i['Substitute'] == '0') {
                        $noCleaning = $i['Code'];
                    }
                }

                unset($cleanCodes);

                // Get the list of rooms
                $stmt = $dbh->query("select rr.idResource, r.* from resource_room rr left join room r on rr.idRoom = r.idRoom");

                while ($rm = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $rooms[$rm['idResource']] = $rm;
                }
            }

            $tbl = new HTMLTable();

            $tbl->addHeaderTr(
                    ($checkinPage == '' ? '' : HTMLTable::makeTh(''))
                    .HTMLTable::makeTh($labels->getString('MemberType', 'primaryGuest', 'Primary Guest'))
                    .HTMLTable::makeTh($labels->getString('MemberType', 'patient', 'Patient'))
                    .HTMLTable::makeTh($labels->getString('MemberType', 'visitor', 'Guest') . 's')
                    .HTMLTable::makeTh('Arrival Date')
                    .HTMLTable::makeTh('Expected Departure')
                    .HTMLTable::makeTh('Room')
                    .HTMLTable::makeTh('Nights')
                .($markupPrepay ? HTMLTable::makeTh('Pre-Paymt') : '')
                    .($showConstraints ? HTMLTable::makeTh('Additional Items') : ''));

            foreach ($rows as $r) {

                $rvRs = new ReservationRS();
                EditRS::loadRow($r, $rvRs);
                $resv = new Reservation_1($rvRs);

                $guestMember = GuestMember::GetDesignatedMember($dbh, $resv->getIdGuest(), MemBasis::Indivual);

                $today = new \DateTimeImmutable();
                $today = $today->setTime(23, 59, 59);
                $expArr = new \DateTime($resv->getExpectedArrival());

                $star = '';
                $dirtyRoom = '';
                $roomAttr = array('style'=>'text-align:center;');

                if ($reservStatus == ReservationStatus::Staying || $expArr <= ($uS->ResvEarlyArrDays >= 0 ? $today->add(new \DateInterval('P' . $uS->ResvEarlyArrDays . 'D')): $today)) {

                    if ($checkinPage != '') {

                        $href = $checkinPage.'?rid='.$resv->getIdReservation();

                        if ($r['idVisit'] > 0) {
                            $href .= '&vid='.$r['idVisit'].'&span='.$r['Span'].'&vstatus='.$r['Visit_Status'];
                        }

                        if ($expArr > $today){
                            $buttonText = "Check In Early";
                        }

                        $star = HTMLContainer::generateMarkup('a',
                            HTMLInput::generateMarkup($buttonText, array('type'=>'button'))
                                , array('href'=>$href));
                    }

                    if ($shoDirtyRooms) {

                        if (isset($rooms[$resv->getIdResource()])) {
                            $roomRs = new RoomRs();
                            EditRS::loadRow($rooms[$resv->getIdResource()], $roomRs);
                            $room = new Room($dbh, 0, $roomRs);


                            if ($room->getCleaningCycleCode() != $noCleaning) {

                                if ($uS->HouseKeepingSteps > 1 && $room->isClean() === TRUE) {
                                    $dirtyRoom = '(Not Ready)';
                                    $roomAttr = array('style'=>'text-align:center; background-color:yellow;');

                                } else if ($room->isClean() === FALSE && $room->isReady() === FALSE) {
                                	$dirtyRoom = '('. $roomStatuses[RoomState::Dirty][1].')';
                                    $roomAttr = array('style'=>'text-align:center; background-color:yellow;');
                                }
                            }
                        }
                    }
                }

                $constList = '';
                if ($showConstraints && ($reservStatus == ReservationStatus::Committed || $reservStatus == ReservationStatus::Waitlist)) {

                    // Get constraints
                    $constraints = new ConstraintsVisit($dbh, $resv->getIdReservation(), 0);
                    $constrs = array();

                    foreach ($constraints->getConstraints() as $c) {

                        if ($c['isActive'] == 1) {
                            $constrs[] = $c['Title'];
                        }
                    }

                    $constList = HTMLTable::makeTd(implode(', ', $constrs));

                }

                if ($resv->getIdGuest() == $r['idPatient']) {
                    $pname = '(same)';
                } else {
                    $pname = $r['Patient_Name'];
                }

                $guestAttrs = array();
                $guestName = '';

                if ($editPage != '') {
                    $guestName = HTMLContainer::generateMarkup('a', $guestMember->getMemberName(), array('href'=>$editPage.'?id='.$resv->getIdGuest().'&rid='.$resv->getIdReservation(), 'title'=>'Click to view guest details'));
                } else {
                    if ($resv->getStatus() == ReservationStatus::UnCommitted) {
                        $guestAttrs['class'] = 'ui-state-highlight';
                        $guestAttrs['title'] = 'Reservation status is ' . $resv->getStatusTitle($dbh) . '.  ' . $guestAttrs['title'];
                    }

                    $guestName = HTMLContainer::generateMarkup('span', $guestMember->getMemberName(), $guestAttrs);
                }

                $tbl->addBodyTr(
                    ($checkinPage == '' ? '' : HTMLTable::makeTd($star, array('style'=>'text-align:center;font-size:.9em;', 'title'=>'Check a new guest in to this room')))
                        .HTMLTable::makeTd($guestName)
                        .HTMLTable::makeTd($pname)
                        .HTMLTable::makeTd($resv->getNumberGuests($dbh), array('style'=>'text-align:center;'))
                        .HTMLTable::makeTd($resv->getActualArrival() != '' ? date('c', strtotime($resv->getActualArrival())) : date('c', strtotime($resv->getExpectedArrival())))
                        .HTMLTable::makeTd($resv->getActualDeparture() != '' ? date('c', strtotime($resv->getActualDeparture())) : date('c', strtotime($resv->getExpectedDeparture())))
                        .HTMLTable::makeTd($resv->getRoomTitle($dbh) . $dirtyRoom, $roomAttr)
                        .HTMLTable::makeTd($resv->getExpectedDays(), array('style'=>'text-align:center;'))
                    .($markupPrepay ? HTMLTable::makeTd(($r['PrePaymt'] > 0 ? '$'.number_format($r['PrePaymt']) : ''), array('style'=>'text-align:center;')) : '')
                        .$constList
                );

            }

            return $tbl->generateMarkup(array('id'=>$reservStatus . 'tblgetter', 'style'=>'width:100%;', 'class'=>'display'));

        } else {
            return "";
        }

    }


    /**
     * Summary of getStatusTitle
     * @param \PDO $dbh
     * @param mixed $status
     * @return mixed
     */
    public function getStatusTitle(\PDO $dbh, $status = '') {

        if ($status == '') {
            $status = $this->getStatus();

            if ($status == '') {
                return '';
            }
        }

        $reservStatuses = readLookups($dbh, "ReservStatus", "Code", true);

        if(isset($reservStatuses[$status])){
            return $reservStatuses[$status]["Title"];
        }

         $uS = Session::getInstance();

         if (isset($uS->guestLookups['ReservStatus'][$status][1])) {

             return $uS->guestLookups['ReservStatus'][$status][1];
         }

        return '';
    }

    /**
     * Summary of getStatusIcon
     * @param \PDO $dbh
     * @param mixed $status
     * @return string
     */
    public function getStatusIcon(\PDO $dbh, $status = '') {

        if ($status == '') {

            $status = $this->getStatus();

            if ($status == '') {
                return '';
            }
        }

        $reservStatuses = readLookups($dbh, "reservStatus", "Code", true);

        if(isset($reservStatuses[$status])){
            return HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ' . $reservStatuses[$status]["Icon"], 'style'=>'float: left; margin-left:.3em;', 'title'=>$reservStatuses[$status]["Title"]));
        }

        $uS = Session::getInstance();

        if (isset($uS->guestLookups['ReservStatus'][$status][2])) {

            return HTMLContainer::generateMarkup('span', '', array('class'=>'ui-icon ' . $uS->guestLookups['ReservStatus'][$status][2], 'style'=>'float: left; margin-left:.3em;', 'title'=>$uS->guestLookups['ReservStatus'][$status][1]));
        }

        return '';
    }

    /**
     *
     * @param array $reserveStatuses
     * @return array
     */
    public function getChooserStatuses($reserveStatuses) {

        $uS = Session::getInstance();

        $limResvStatuses = [];

        foreach ($reserveStatuses as $s) {

            if ($s['Show'] == 'y') {

                // Opt out if not useing Unconfirmed Status.
                if ($s['Code'] == ReservationStatus::UnCommitted && $uS->ShowUncfrmdStatusTab === FALSE) {
                    continue;
                }

                if ($s['Type'] == ReservationStatusType::Cancelled) {
                    $s[2] = 'Cancel Codes';
                } else if  ($s['Type'] == '') {
                    continue;
                } else {
                    $s[2] = 'Active Codes';
                }

                $limResvStatuses[$s[0]] = [$s[0], $s[1], $s[2], 'Type' =>$s['Type']];
            }
        }

        return $limResvStatuses;
    }

    /**
     * Summary of isActive
     * @param mixed $reserveStatuses
     * @return bool
     */
    public function isActive($reserveStatuses) {
        return $this->isActiveStatus($this->getStatus(), $reserveStatuses);
    }

    /**
     * Summary of isActiveStatus
     * @param mixed $status
     * @param mixed $reserveStatuses
     * @return bool
     */
    public static function isActiveStatus($status, $reserveStatuses) {

        if (isset($reserveStatuses[$status])) {


            if ($reserveStatuses[$status]['Type'] == ReservationStatusType::Active) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * Summary of isRemoved
     * @param mixed $reserveStatuses
     * @return bool
     */
    public function isRemoved($reserveStatuses) {
        return $this->isRemovedStatus($this->getStatus(), $reserveStatuses);
    }

    /**
     * Summary of isRemovedStatus
     * @param mixed $status
     * @param mixed $reserveStatuses
     * @return bool
     */
    public static function isRemovedStatus($status, $reserveStatuses) {

        if (isset($reserveStatuses[$status])) {

            if ($reserveStatuses[$status]['Type'] == ReservationStatusType::Cancelled) {
                return TRUE;
            }
        }
        return FALSE;
    }

    /**
     * Summary of isNew
     * @return bool
     */
    public function isNew() {
        if ($this->reservRs->idReservation->getStoredVal() > 0) {
            return FALSE;
        }
        return TRUE;
    }

    /**
     * Summary of getIdPsg
     * @param \PDO $dbh
     * @return int|mixed
     */
    public function getIdPsg(\PDO $dbh) {

        if ($this->getIdRegistration() == 0) {

            $this->idPsg = 0;

        } else if (is_null($this->idPsg)) {

            $regRs = new RegistrationRs();
            $regRs->idRegistration->setStoredVal($this->getIdRegistration());
            $rows = EditRS::select($dbh, $regRs, array($regRs->idRegistration));

            if (count($rows) == 1) {
                EditRS::loadRow($rows[0], $regRs);
                $this->idPsg = $regRs->idPsg->getStoredVal();
            } else {
                $this->idPsg = 0;
            }

        }

        return $this->idPsg;
    }

    /**
     * Summary of getIdPsgStatic
     * @param \PDO $dbh
     * @param mixed $idResv
     * @return mixed
     */
    public static function getIdPsgStatic(\PDO $dbh, $idResv) {

        $idPsg = 0;
        $id = intval($idResv, 10);

        if ($idResv > 0) {
            $stmt = $dbh->query("select ifnull(rg.idPsg, 0) from reservation r left join registration rg on rg.idRegistration = r.idRegistration where r.idReservation = $id");
            $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

            if (count($rows) > 0) {
                $idPsg = $rows[0][0];
            }
        }
        return $idPsg;
    }

    /**
     * Summary of isFixedRate
     * @return bool
     */
    public function isFixedRate() {
        if ($this->reservRs->Room_Rate_Category->getStoredVal() == RoomRateCategories::Fixed_Rate_Category) {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * Summary of getExpectedPayType
     * @return mixed
     */
    public function getExpectedPayType() {
        return $this->reservRs->Expected_Pay_Type->getStoredVal();
    }

    /**
     * Summary of setExpectedPayType
     * @param mixed $payTypeCode
     * @return Reservation_1
     */
    public function setExpectedPayType($payTypeCode) {
        $this->reservRs->Expected_Pay_Type->setNewVal($payTypeCode);
        return $this;
    }

    /**
     * Summary of getVisitFee
     * @return mixed
     */
    public function getVisitFee() {
        return $this->reservRs->Visit_Fee->getStoredVal();
    }

    /**
     * Summary of setVisitFee
     * @param mixed $amount
     * @return Reservation_1
     */
    public function setVisitFee($amount) {
        $this->reservRs->Visit_Fee->setNewVal($amount);
        return $this;
    }

    /**
     * Summary of getVerbalConfirm
     * @return mixed
     */
    public function getVerbalConfirm() {
        return $this->reservRs->Confirmation->getStoredVal();
    }

    /**
     * Summary of setVerbalConfirm
     * @param mixed $confirmCode
     * @return Reservation_1
     */
    public function setVerbalConfirm($confirmCode) {
        $this->reservRs->Confirmation->setNewVal($confirmCode);
        return $this;
    }

    /**
     * Summary of setExpectedArrival
     * @param mixed $v
     * @return Reservation_1
     */
    public function setExpectedArrival($v) {
        if ($v != '') {
            $arr = date('Y-m-d 16:00:00', strtotime($v));
            $this->expectedArrival = $arr;
        }
        return $this;
    }

    /**
     * Summary of setExpectedDeparture
     * @param mixed $v
     * @return Reservation_1
     */
    public function setExpectedDeparture($v) {
        if ($v != '') {
            $arr = date('Y-m-d 10:00:00', strtotime($v));
            $this->expectedDeparture = $arr;
        }
        return $this;
    }

    /**
     * Summary of setActualArrival
     * @param mixed $v
     * @return Reservation_1
     */
    public function setActualArrival($v) {
        $this->reservRs->Actual_Arrival->setNewVal($v);
        return $this;
    }

    /**
     * Summary of setActualDeparture
     * @param mixed $v
     * @return Reservation_1
     */
    public function setActualDeparture($v) {
        $this->reservRs->Actual_Departure->setNewVal($v);
        return $this;
    }

    /**
     * Summary of getCheckinNotes
     * @return mixed
     */
    public function getCheckinNotes() {
        return $this->reservRs->Checkin_Notes->getStoredVal();
    }

    /**
     * Summary of setCheckinNotes
     * @param mixed $v
     * @return Reservation_1
     */
    public function setCheckinNotes($v) {
        $this->reservRs->Checkin_Notes->setNewVal($v);
        return $this;
    }

    /**
     * Summary of getIdHospital
     * @return mixed
     */
    public function getIdHospital() {
        return $this->idHospital;
    }

    /**
     * Summary of getIdAssociation
     * @return mixed
     */
    public function getIdAssociation() {
        return $this->idAssociation;
    }

    /**
     * Summary of setIdHospital
     * @param mixed $idHospital
     * @return Reservation_1
     */
    public function setIdHospital($idHospital) {
        $this->idHospital = $idHospital;
        return $this;
    }

    /**
     * Summary of setIdAssociation
     * @param mixed $idAssociation
     * @return Reservation_1
     */
    public function setIdAssociation($idAssociation) {
        $this->idAssociation = $idAssociation;
        return $this;
    }

    /**
     * Summary of setTitle
     * @param mixed $title
     * @return Reservation_1
     */
    public function setTitle($title) {
        $this->reservRs->Title->setNewVal($title);
        return $this;
    }

    /**
     * Summary of getTitle
     * @return mixed
     */
    public function getTitle() {
        return $this->reservRs->Title->getStoredVal();
    }

    /**
     * Returns Actual arrival date or Expected depending on reservation status
     *
     * @return string
     */
    public function getArrival() {
        if ($this->getStatus() == ReservationStatus::Staying || $this->getStatus() == ReservationStatus::Checkedout) {
            return $this->getActualArrival();
        } else {
            return $this->getExpectedArrival();
        }
    }

    /**
     * Returns Actual departure date or Expected depending on reservation status
     *
     * @return string
     */
    public function getDeparture() {
        if ($this->getStatus() == ReservationStatus::Checkedout) {
            return $this->getActualDeparture();
        } else {
            return $this->getExpectedDeparture();
        }
    }

    /**
     * Summary of setIdReferralDoc
     * @param mixed $v
     * @return Reservation_1
     */
    public function setIdReferralDoc($v) {
        $this->idReferralDoc = $v;
        return $this;
    }

    /**
     * Summary of setIdResource
     * @param mixed $v
     * @return Reservation_1
     */
    public function setIdResource($v) {
        $this->idResource = $v;
        return $this;
    }

    /**
     * Summary of setIdGuest
     * @param mixed $v
     * @return Reservation_1
     */
    public function setIdGuest($v) {
        $this->reservRs->idGuest->setNewVal($v);
        return $this;
    }

    /**
     * Summary of setHospitalStay
     * @param \HHK\House\Hospital\HospitalStay $v
     * @return Reservation_1
     */
    public function setHospitalStay(HospitalStay $v) {
        $this->reservRs->idHospital_Stay->setNewVal($v->getIdHospital_Stay());
        $this->setIdHospital($v->getHospitalId())
                ->setIdAssociation($v->getAssociationId());
        return $this;
    }

    /**
     * Summary of setIdHospitalStay
     * @param mixed $idHospitalStay
     * @return Reservation_1
     */
    public function setIdHospitalStay($idHospitalStay) {
        $id = intval($idHospitalStay, 10);
        $this->reservRs->idHospital_Stay->setNewVal($id);
        return $this;
    }

    /**
     * Summary of setRoomRateCategory
     * @param mixed $v
     * @return Reservation_1
     */
    public function setRoomRateCategory($v) {
        $this->reservRs->Room_Rate_Category->setNewVal($v);
        return $this;
    }

    /**
     * Summary of setFixedRoomRate
     * @param mixed $v
     * @return Reservation_1
     */
    public function setFixedRoomRate($v) {
        $this->reservRs->Fixed_Room_Rate->setNewVal($v);
        return $this;
    }

    /**
     * Summary of setAmuntPerGuest
     * @param mixed $v
     * @return Reservation_1
     */
    public function setAmuntPerGuest($v) {
        $this->reservRs->Fixed_Room_Rate->setNewVal($v);
        return $this;
    }

    /**
     * Summary of setRateAdjust
     * @param mixed $v
     * @return Reservation_1
     */
    public function setRateAdjust($v) {

        if ($v >= -100 && $v <= 0) {

            $this->reservRs->Rate_Adjust->setNewVal($v);
        }
        return $this;
    }

    /**
     * Summary of setIdRateAdjust
     * @param mixed $v
     * @return Reservation_1
     */
    public function setIdRateAdjust($v){
        $this->reservRs->idRateAdjust->setNewVal($v);
        return $this;
    }

    /**
     * Summary of getIdRoomRate
     * @return mixed
     */
    public function getIdRoomRate() {
        return $this->reservRs->idRoom_rate->getStoredVal();
    }

    /**
     * Summary of setIdRoomRate
     * @param mixed $idRoom_rate
     * @return Reservation_1
     */
    public function setIdRoomRate($idRoom_rate) {
        $this->reservRs->idRoom_rate->setNewVal($idRoom_rate);
        return $this;
    }

    /**
     * Summary of setNumberGuests
     * @param mixed $v
     * @return Reservation_1
     */
    public function setNumberGuests($v) {
        $this->numGuests = $v;
        return $this;
    }

    /**
     * Summary of setStatus
     * @param mixed $v
     * @return Reservation_1
     */
    public function setStatus($v) {
        $uS = Session::getInstance();
        if (isset($uS->guestLookups['ReservStatus'][$v])) {
            $this->reservRs->Status->setNewVal($v);
        }
        return $this;
    }

    /**
     * Summary of setStatusType
     * @param mixed $t
     * @return void
     */
    public function setStatusType($t) {
        $this->reserveStatusType = $t;
    }

    /**
     * Summary of setAddRoom
     * @param mixed $v
     * @return Reservation_1
     */
    public function setAddRoom($v) {
        $this->reservRs->Add_Room->setNewVal($v);
        return $this;
    }

    /**
     * Summary of saveNote
     * @param \PDO $dbh
     * @param mixed $noteText
     * @param mixed $uname
     * @param mixed $concatNotes
     * @return array<string>|int|mixed
     */
    public function saveNote(\PDO $dbh, $noteText, $uname, $concatNotes) {

        if ($noteText != '') {
            return LinkNote::save($dbh, $noteText, $this->getIdReservation(), Note::ResvLink, '', $uname, $concatNotes);
        }

        return 0;
    }

    /**
     * Summary of getIdVisit
     * @param \PDO $dbh
     * @return int|mixed
     */
    public function getIdVisit(\PDO $dbh) {

        if ($this->idVisit < 0 && $this->getIdReservation() > 0) {

            $stmt = $dbh->query("Select idVisit from visit where Span = 0 and idReservation = " . $this->getIdReservation());
            $lines = $stmt->fetchAll(\PDO::FETCH_NUM);

            if (count($lines) > 0) {
                $this->idVisit = $lines[0][0];
            } else {
                $this->idVisit = 0;
            }
        }

        return $this->idVisit;
    }

    /**
     * Summary of getReservationRS
     * @return ReservationRS
     */
    public function getReservationRS() {
        if (is_null($this->reservRs)) {
            return new ReservationRS();
        }
        return $this->reservRs;
    }

    /**
     * Summary of getNotes
     * @return mixed
     */
    public function getNotes() {
        return $this->reservRs->Notes->getStoredVal();  // == '' ? $this->reservRs->Notes->getNewVal() : $this->reservRs->Notes->getStoredVal();
    }

    /**
     * Summary of setNotes
     * @param mixed $val
     * @return void
     */
    public function setNotes($val) {
        $this->reservRs->Notes->setNewVal($val);
    }

    /**
     * Summary of getIdResource
     * @return int
     */
    public function getIdResource() {
        //return $this->reservRs->idResource->getStoredVal();
        return $this->idResource;
    }

    /**
     * Summary of getIdReferralDoc
     * @return mixed
     */
    public function getIdReferralDoc() {
        return $this->idReferralDoc;
    }

    /**
     * Summary of getIdGuest
     * @return mixed
     */
    public function getIdGuest() {
        return $this->reservRs->idGuest->getStoredVal();
    }

    /**
     * Summary of getIdReservation
     * @return mixed
     */
    public function getIdReservation() {
        return $this->reservRs->idReservation->getStoredVal();
    }

    /**
     * Summary of getIdHospitalStay
     * @return mixed
     */
    public function getIdHospitalStay() {
        return $this->reservRs->idHospital_Stay->getStoredVal();
    }

    /**
     * Summary of getFixedRoomRate
     * @return mixed
     */
    public function getFixedRoomRate() {
        return $this->reservRs->Fixed_Room_Rate->getStoredVal();
    }

    /**
     * Summary of getRoomRateCategory
     * @return mixed
     */
    public function getRoomRateCategory() {
        return $this->reservRs->Room_Rate_Category->getStoredVal();
    }

    /**
     * Summary of getRateAdjust
     * @return mixed
     */
    public function getRateAdjust() {
        return $this->reservRs->Rate_Adjust->getStoredVal();
    }

    /**
     * Summary of getIdRateAdjust
     * @return mixed
     */
    public function getIdRateAdjust(){
        return $this->reservRs->idRateAdjust->getStoredVal();
    }

    /**
     * Summary of getAmouontPerGuest
     * @return mixed
     */
    public function getAmouontPerGuest() {
        return $this->reservRs->Fixed_Room_Rate->getStoredVal();
    }


    /**
     * Summary of getAdjustedTotal
     * @param mixed $amount
     * @return float
     */
    public function getAdjustedTotal($amount) {
        $adjustedAmount = (1 + $this->getRateAdjust() / 100) * $amount;

        return $adjustedAmount;
    }

    /**
     * Summary of getIdRegistration
     * @return mixed
     */
    public function getIdRegistration() {
        return $this->reservRs->idRegistration->getStoredVal();
    }

    /**
     * Summary of getNumberGuests
     * @param \PDO|null $dbh
     * @return int|mixed
     */
    public function getNumberGuests(\PDO $dbh = null) {

        if (!is_null($dbh) && $this->getStatus() == ReservationStatus::Staying) {

            $stmt = $dbh->query("select count(s.idStays)
from stays s join visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
where v.Status = 'a' and s.Status = 'a' and v.idReservation = " . $this->getIdReservation());

            $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
            $this->numGuests = 0;

            if (count($rows) > 0) {
                $this->numGuests = $rows[0][0];
            }
        }

        return $this->numGuests;
    }

    /**
     * Summary of getStatus
     * @return mixed
     */
    public function getStatus() {
        return $this->reservRs->Status->getStoredVal();
    }

    /**
     * Summary of getStatusType
     * @return mixed
     */
    public function getStatusType() {
        return $this->reserveStatusType;
    }

    /**
     * Summary of getAddRoom
     * @return mixed
     */
    public function getAddRoom() {
        return $this->reservRs->Add_Room->getStoredVal();
    }

    /**
     * Summary of getExpectedArrival
     * @return string
     */
    public function getExpectedArrival() {
        return $this->expectedArrival;
    }

    /**
     * Summary of getExpectedDeparture
     * @return string
     */
    public function getExpectedDeparture() {
        return $this->expectedDeparture;
    }

    /**
     * Summary of getActualArrival
     * @return mixed
     */
    public function getActualArrival() {
        return $this->reservRs->Actual_Arrival->getStoredVal();
    }

    /**
     * Summary of getActualDeparture
     * @return mixed
     */
    public function getActualDeparture() {
        return $this->reservRs->Actual_Departure->getStoredVal();
    }

    /**
     * Summary of getRoomTitle
     * @param \PDO $dbh
     * @return string
     */
    public function getRoomTitle(\PDO $dbh) {

        if ($this->getIdResource() > 0 && $this->roomTitle == '') {
            $resourceRS = new ResourceRS();
            $resourceRS->idResource->setStoredVal($this->getIdResource());
            $rows = EditRS::select($dbh, $resourceRS, array($resourceRS->idResource));

            if (count($rows) == 1) {
                EditRS::loadRow($rows[0], $resourceRS);
                $this->roomTitle = htmlspecialchars_decode($resourceRS->Title->getStoredVal(), ENT_QUOTES);
            }
        }

        return $this->roomTitle;
    }

    /**
     * Summary of getExpectedDays
     * @return int
     */
    public function getExpectedDays() {

        if ($this->getExpectedArrival() != '' && $this->getExpectedDeparture() != '') {
            $ad = new \DateTime($this->getExpectedArrival());
            $ad->setTime(11, 0, 0);
            $dd = new \DateTime($this->getExpectedDeparture());
            $dd->setTime(11, 0, 0);

            return $dd->diff($ad, TRUE)->days;
        }
        return 0;
    }

    /**
     * Summary of getExpectedDaysDT
     * @param \DateTime $startDT
     * @param \DateTime $endDT
     * @return int
     */
    public static function getExpectedDaysDT($startDT, $endDT) {

        if ($startDT instanceof \DateTime && $endDT instanceof \DateTime){
            $ad = new \DateTime($startDT->format('Y-m-d H:i:s'));
            $ad->setTime(11, 0, 0);
            $dd = new \DateTime($endDT->format('Y-m-d H:i:s'));
            $dd->setTime(11, 0, 0);

            return $dd->diff($ad, TRUE)->days;
        }

        return 0;
    }

}
?>