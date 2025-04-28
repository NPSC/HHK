<?php

namespace HHK\House\Reservation;

use HHK\Exception\RuntimeException;
use HHK\House\Registration;
use HHK\House\Vehicle;
use HHK\HTMLControls\HTMLContainer;
use HHK\House\Family\Family;
use HHK\House\ReserveData\ReserveData;
use HHK\House\Resource\AbstractResource;
use HHK\House\Room\RoomChooser;
use HHK\House\Visit\Visit;
use HHK\SysConst\VisitStatus;
use HHK\Tables\EditRS;
use HHK\Tables\Reservation\ReservationRS;
use HHK\Tables\Visit\VisitRS;
use HHK\sec\{SecurityComponent, Session};
use HHK\sec\Labels;
use HHK\House\ReserveData\PSGMember\PSGMemVisit;

/**
 * Description of StayingReservation
 *
 * @author Eric
 */

class StayingReservation extends CheckingIn {

    /**
     * Summary of createMarkup
     * @param \PDO $dbh
     * @throws \HHK\Exception\RuntimeException
     * @return array
     */
    public function createMarkup(\PDO $dbh) {

        if ($this->reserveData->getIdVisit() < 1 || $this->reserveData->getSpan() < 0) {
            throw new RuntimeException('The visit is not defined.');
        }

        $this->getFamilyMarkup($dbh);

        $this->reserveData->setResvSection($this->createAddGuestMarkup($dbh));

        return $this->reserveData->toArray();

    }

    /**
     * Summary of save
     * @param \PDO $dbh
     * @return ActiveReservation|StayingReservation
     */
    public function save(\PDO $dbh) {

        // Check for new room
        if (isset($_POST['cbNewRoom'])) {
            // New Room
            $this->reserveData->setIdResv(0);
            $this->reserveData->setIdVisit(0);
            $this->reserveData->setSpan(0);
            $_POST['rid'] = 0;
            $_POST['vid'] = 0;
            $_POST['span'] = 0;
            $_POST['rbPriGuest'] = 0;

            $checkingIn = new ActiveReservation($this->reserveData, new ReservationRS(), new Family($dbh, $this->reserveData, TRUE));
            $checkingIn->save($dbh);
            $checkingIn->setGotoCheckingIn('yes');
            return $checkingIn;

        } else {

            $uS = Session::getInstance();
            
            // Same room, just add the guests.
            $this->addGuestStay($dbh);

            // Save any vehicles
            $reg = new Registration($dbh, $this->reserveData->getIdPsg());
            if ($uS->TrackAuto) {
                $reg->extractVehicleFlag();
            }

            $reg->saveRegistrationRs($dbh, $this->reserveData->getIdPsg(), $uS->username);

            // Save any vehicles
            if ($uS->TrackAuto && $reg->getNoVehicle() == 0) {
                Vehicle::saveVehicle($dbh, $reg->getIdRegistration(), $this->reservRs->idReservation->getStoredVal());
            }
        }

        return $this;
    }

    /**
     * Summary of getFamilyMarkup
     * @param \PDO $dbh
     * @param array $formUserData
     * @return void
     */
    protected function getFamilyMarkup(\PDO $dbh, array $formUserData = []) {

        $psgMembers = $this->reserveData->getPsgMembers();

        $this->reserveData->addConcurrentRooms($this->findConflictingReservations($dbh, $this->reserveData->getIdPsg(), $this->reserveData->getIdResv(), $psgMembers, $this->reserveData->getSpanStartDT(), $this->reserveData->getSpanEndDT(), $this->reserveData->getResvPrompt()));
        $this->reserveData->addConcurrentRooms($this->findCheckedInStays($dbh, $psgMembers, $this->reserveData->getIdPsg(), $this->reserveData->getIdVisit(), $this->reserveData->getSpan()));

        $this->reserveData->setPsgMembers($psgMembers);

        $this->reserveData->setFamilySection($this->family->createFamilyMarkup($dbh, $this->reserveData));

    }

    /**
     * Summary of createAddGuestMarkup
     * @param \PDO $dbh
     * @return array
     */
    protected function createAddGuestMarkup(\PDO $dbh) {

        $uS = Session::getInstance();

        $resvSectionHeaderPrompt = 'Add ' . Labels::getString('MemberType', 'visitor', 'Guest') . 's:';

        $nowDT = new \DateTime();
        $nowDT->setTime(intval($uS->CheckInTime), 0, 0);

        $resv = new Reservation_1($this->reservRs);

        // Dates
        $this->reserveData->setArrivalDT($nowDT);
        $this->reserveData->setDepartureDateStr($resv->getExpectedDeparture());


        // Room Chooser
        $roomChooser = new RoomChooser($dbh, $resv, 1, $resv->getExpectedArrival(), $resv->getExpectedDeparture());
        $dataArray['rChooser'] = $roomChooser->createAddGuestMarkup($dbh, SecurityComponent::is_Authorized(ReserveData::GUEST_ADMIN));

        $dataArray = array_merge($dataArray, $this->createExpDatesControl(TRUE, $this->reserveData->getSpanStartDT()->format('M j, Y')));

        // Vehicles
        if ($uS->TrackAuto) {
            $dataArray['vehicle'] = $this->vehicleMarkup($dbh);
        }

        // Reservation status title
        $dataArray['rStatTitle'] = 'Adding Guests';

        // Reservation notes
        $dataArray['notes'] = HTMLContainer::generateMarkup('fieldset',
            HTMLContainer::generateMarkup('legend', 'Visit Notes', array('style'=>'font-weight:bold;'))
            , array('id'=>'hhk-noteViewer', 'class'=>'hhk-panel', 'style'=>'width:100%'));


        // Collapsing header
        $hdr = HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('span', $resvSectionHeaderPrompt, array('style'=>'float:left;font-size:.9em; margin-right: 1em;'))
            . HTMLContainer::generateMarkup('span', '', array('id'=>'addGuestHeader', 'style'=>'float:left;'))
            , array('style'=>'float:left;', 'class'=>'hhk-checkinHdr'));


        return array('hdr'=>$hdr, 'rdiv'=>$dataArray);
    }

    /**
     * Summary of addGuestStay
     * @param \PDO $dbh
     * @throws \HHK\Exception\RuntimeException
     * @return void
     */
    protected function addGuestStay(\PDO $dbh) {

        $uS = Session::getInstance();
        $visitRs = new VisitRs();


        if ($this->reserveData->hasError()) {
            return;
        }

        // Does visit exist
        $stmt = $dbh->query("Select * from visit where Status = '" . VisitStatus::CheckedIn . "' and idReservation = " . $this->reserveData->getIdResv() . ";");

        if ($stmt->rowCount() == 0) {
            throw new RuntimeException('Visit not found for reservation Id ' . $this->reserveData->getIdResv());
        }

        $vrows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        EditRS::loadRow($vrows[0], $visitRs);

        // Checking the new guest stay dates.
        $this->checkVisitDates($uS->CheckInTime);

        if ($this->reserveData->hasError()) {
            return;
        }

        // Get the resource
        $resc = null;
        if ($visitRs->idResource->getStoredVal() > 0) {
            $resc = AbstractResource::getResourceObj($dbh, $visitRs->idResource->getStoredVal());
        } else {
            $this->reserveData->addError('Room not found.  ');
            return;
        }

        // Maximym occupants...
        $numOccupants = $resc->getCurrantOccupants($dbh) + count($this->getStayingMembers());

        if ($numOccupants > $resc->getMaxOccupants()) {
            $this->reserveData->addError("The maximum occupancy (" . $resc->getMaxOccupants() . ") for room " . $resc->getTitle() . " is exceded.  ");
            return;
        }

        // Save any new guests now.  (after error checking)
        $this->initialSave($dbh);

        // open visit
        $visit = new Visit($dbh, 0, $visitRs->idVisit->getStoredVal(), NULL, NULL, $resc, $uS->username, $visitRs->Span->getStoredVal());

        // Add guests
        foreach ($this->getStayingMembers() as $m) {

            if ($uS->PatientAsGuest === FALSE && $m->isPatient()) {
                $this->reserveData->addError('Patients cannot stay.  ');
                continue;
            }

            $visit->addGuestStay($m->getId(), $this->reserveData->getArrivalDT()->format('Y-m-d H:i:s'), $this->reserveData->getArrivalDT()->format('Y-m-d H:i:s'), $this->reserveData->getDepartureDT()->format('Y-m-d H:i:s'));
        }


        //
        // Checkin  Saves visit
        //
        $visit->checkin($dbh, $uS->username);

        $this->payResult = NULL;

        $this->resc = $resc;
        $this->visit = new Visit($dbh, 0, $visitRs->idVisit->getStoredVal(), NULL, NULL, $resc, $uS->username, $visitRs->Span->getStoredVal());

        return;
    }

    /**
     * Summary of findCheckedInStays
     * @param \PDO $dbh
     * @param array $psgMembers
     * @param mixed $idPsg
     * @param mixed $idVisit
     * @param mixed $idSpan
     * @return int
     */
    protected function findCheckedInStays(\PDO $dbh, array &$psgMembers, $idPsg, $idVisit = 0, $idSpan = -1) {

        $whStays = '';
        $rooms = [];

        // Collect member ids
        foreach ($psgMembers as $m) {
            if ($m->getId() != 0 && $m->isBlocked() === FALSE) {
                $whStays .= ',' . $m->getId();
            }
        }

        // Find any visits.
        if ($whStays != '') {

            // Check ongoing visits
            $vstmt = $dbh->query("SELECT
    s.`idName`,
    s.`idVisit`,
    s.`Visit_Span`,
    s.`idRoom`,
    s.`Status` as `Status`,
    r.`idPsg`,
    rm.`Title`,
    v.`idPrimaryGuest`
FROM
    stays s
        JOIN
    visit v ON s.idVisit = v.idVisit
        AND s.Visit_Span = v.Span
        JOIN
    room rm ON s.idRoom = rm.idRoom
        JOIN
    registration r ON v.idRegistration = r.idRegistration
WHERE
    s.Status = 'a'
    and s.idName in (" . substr($whStays, 1) . ") "
                . " order by s.idVisit, s.Visit_Span");

            while ($s = $vstmt->fetch(\PDO::FETCH_ASSOC)) {
                // These guests are already staying somewhere

                if ($s['idVisit'] == $idVisit && $s['Visit_Span'] == $idSpan) {
                    // My visit
                    $memVisit = new PSGMemVisit(array());

                } else {
                    // Not my visit
                    $memVisit = new PSGMemVisit(array('idVisit'=>$s['idVisit'], 'Visit_Span'=>$s['Visit_Span'], 'room'=>$s['Title'], 'status'=>$s['Status']));
                }

                // Set visit id and Check primary guest
                foreach ($psgMembers as $m) {

                    if ($m->getId() == $s['idName']) {

                        $psgMembers[$m->getPrefix()]->setStayObj($memVisit);

                        if ($m->getId() == $s['idPrimaryGuest'] && $s['idVisit'] == $idVisit) {
                            $psgMembers[$m->getPrefix()]->setPrimaryGuest(TRUE);
                        }
                    }
                }

                // Count different rooms
                if ($s['idPsg'] == $idPsg) {
                    $rooms[$s['idRoom']] = '1';
                }
            }
        }

        // Return number of rooms being used by this psg.
        return count($rooms);
    }

}
?>