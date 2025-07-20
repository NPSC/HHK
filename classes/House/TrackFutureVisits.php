<?php

namespace HHK\House;

use DateInterval;
use HHK\House\Reservation\Reservation_1;
use HHK\sec\SecurityComponent;
use HHK\sec\Session;
use HHK\SysConst\VisitStatus;
use HHK\TableLog\VisitLog;
use HHK\Tables\EditRS;
use HHK\Tables\Visit\StaysRS;
use HHK\Tables\Visit\VisitRS;


/*
 * TrackFutureVisits.php - Intended to be run early each day to update the dates of visits with future spans.
 * Each visit will eat up it's future span until the future span is exausted, then delete the future span.
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010 - 2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Summary of TrackFutureVisits - This class is used to track future visits and update them based on the given date.
 */
class TrackFutureVisits {

    /**
     * Summary of lockedVisits
     * @var array
     */
    protected $lockedVisits = [];

    /**
     * Summary of updateFutureVisits
     * @param \PDO $dbh
     * @param \DateTime $pivotDate
     * @return bool|int
     */
    public function updateFutureVisits(\PDO $dbh, \DateTime $pivotDate) {

        $visits = self::findFutureVisitSpans($dbh, $pivotDate);

        // Anything returned?
        if (count($visits) < 1) {
            return 0;
        }

        foreach ($visits as $spans) {

            // The expected end of the visit span must be greater than pivotDate.
            $expectedEnd = new \DateTime($spans[0]['Expected_Departure']);
            if ($pivotDate <= $expectedEnd) {
                continue; // Skip this visit if the expected end is not greater than the pivot date.
            }

            // Check that the room is available for the visit.
            if (self::checkRoomAvailability($dbh, $spans[0]['idReservation'], new \DateTime($spans[0]['Expected_Departure']), $pivotDate, $spans[0]['idResource']) === false) {

                // room is unavailable!
                $this->lockedVisits[] = $spans[0]['idVisit'];

                continue; // Skip this visit since the room is not available.
            }

            // Update the spans.
            self::bumpToPivot($dbh, $pivotDate, $spans);

        }

        return 0;
    }


    /**
     * Summary of bumpToPivot - Bump the givenactive visit to the pivot date.  Eat up the future visit span until it's gone, then delete the future span.
     * @param \PDO $dbh
     * @param \Datetime $pivotDate
     * @param array $span
     * @return void
     */
    protected static function bumpToPivot(\PDO $dbh, \Datetime $pivotDate, array $span) {

        $uS = Session::getInstance();

        if (isset($span[0])) {

            // Update checked-in visit
            $visitRs = new VisitRS();
            $visitRs->idVisit -> setStoredVal($span[0]['idVisit']);
            $visitRs->Span->setStoredVal($span[0]['Span']);
            $visitRs->Expected_Departure->setNewVal($pivotDate->format("Y-m-d $uS->CheckOutTime:00:00"));

            if (EditRS::update($dbh,$visitRs, [$visitRs->idVisit, $visitRs->Span]) < 1) {
                return;  // No update made, so return.
            }

            // Update the visit log
            $logText = VisitLog::getUpdateText($visitRs);
            EditRS::updateStoredVals($visitRs);
            VisitLog::logVisit($dbh, $visitRs->idVisit->getStoredVal(), $visitRs->Span->getStoredVal(), $visitRs->idResource->getStoredVal(), $visitRs->idRegistration->getStoredVal(), $logText, "update", $uS->username);


            // Update checked in stays
            $stayRs = new StaysRS();
            $stayRs->idVisit->setStoredVal($span[0]['idVisit']);
            $stayRs->Visit_Span->setStoredVal($span[0]['Span']);
            $stayRs->Status->setStoredVal(VisitStatus::Active);
            $stayRs->Expected_Co_Date->setNewVal($pivotDate->format("Y-m-d $uS->CheckOutTime:00:00"));

            EditRS::update($dbh, $stayRs, [$stayRs->idVisit, $stayRs->Visit_Span, $stayRs->Status]);


            // Set up the future span.
            $visitRs = new VisitRS();
            $visitRs->idVisit->setStoredVal($span[0]['idVisit']);
            $visitRs->Span->setStoredVal($span[0]['Future_Span']);

            $futureDeparture = new \DateTime($span[0]['Future_Expected_Departure']);

            if ($futureDeparture <= $pivotDate) {

                // Delete the future span.
                EditRS::delete($dbh, $visitRs, [$visitRs->idVisit, $visitRs->Span]);

                // Log it
                $logText = VisitLog::getDeleteText($visitRs, $visitRs->idVisit->getStoredVal());
                VisitLog::logVisit($dbh, $visitRs->idVisit->getStoredVal(), $visitRs->Span->getStoredVal(), $visitRs->idResource->getStoredVal(), $visitRs->idRegistration->getStoredVal(), $logText, "delete", $uS->username);

            } else {

                // Start the future span at the pivot date.
                $visitRs->Span_Start->setNewVal($pivotDate->format("Y-m-d $uS->CheckOutTime:00:00"));
                EditRS::update($dbh, $visitRs, [$visitRs->idVisit, $visitRs->Span]);

                // Log it
                $logText = VisitLog::getUpdateText($visitRs);
                EditRS::updateStoredVals($visitRs);
                VisitLog::logVisit($dbh, $visitRs->idVisit->getStoredVal(), $visitRs->Span->getStoredVal(), $visitRs->idResource->getStoredVal(), $visitRs->idRegistration->getStoredVal(), $logText, "update", $uS->username);
            }
        }

        return;
    }

    /**
     * Summary of findFutureVisitSpans
     * @param \PDO $dbh
     * @param \DateTime $pivotDate
     * @return array<array>
     */
    protected static function findFutureVisitSpans(\PDO $dbh, \DateTime $pivotDate) {

        $visits = [];

        // Collect all visits with with the next future span only.
        $stmt = $dbh->prepare("Select
            v.idVisit,
            v.Span,
            v.idReservation,
            v.Span_Start,
            v.Expected_Departure,
            v.idResource,
            vf.Span AS Future_Span,
            vf.Expected_Departure AS Future_Expected_Departure
        from visit v JOIN visit vf on vf.idVisit = v.idVisit and vf.Status = '" . VisitStatus::Reserved . "' and DATE(vf.Span_Start) < :pivotDate1
	    where v.`Status` = '" . VisitStatus::Active . "' and DATE(v.Expected_Departure) < :pivotDate2
        ORDER BY vf.idVisit, vf.Span;");

        $stmt->execute([
            ":pivotDate1" => $pivotDate->format('Y-m-d 00-00-00'),
            ":pivotDate2" => $pivotDate->format('Y-m-d 00-00-00'),
        ]);

        $idVisit = 0;
        $spans = [];

        // Pack the visits into an array of spans.
        While ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            if ($idVisit != $r['idVisit']) {

                if ($idVisit != 0) {
                    $visits[$r['idVisit']] = $spans;
                }

                $idVisit = $r['idVisit'];
                $spans = [];

            }

            $spans[] = $r;

        }

        // catch last one
        if ($idVisit != 0) {
            $visits[$idVisit] = $spans;
        }

        return $visits;
    }

    /**
     * Summary of checkRoomAvailability
     * @param \PDO $dbh
     * @param mixed $idReservation
     * @param \DateTime $arrivalDate
     * @param \DateTime $departureDate
     * @return bool
     */
    protected static function checkRoomAvailability(\PDO $dbh, $idReservation, \DateTime $arrivalDate, \DateTime $departureDate, $idResource) {
        $uS = Session::getInstance();

        // Reservation
        $reserv = Reservation_1::instantiateFromIdReserv($dbh, $idReservation);

        // Room Available
        if ($reserv->isNew() === FALSE) {

            $rescOpen = $reserv->isResourceOpen(
                $dbh,
                $idResource,
                $arrivalDate->format('Y-m-d H:i:s'),
                $departureDate->format("Y-m-d $uS->CheckOutTime:00:00"),
                1,
                ['room', 'rmtroom', 'part'],
                true,
                true,
            );

            if ($rescOpen) {
                return true;
            }
        }

        return false;
    }

    /**
     * Summary of getCrowdedVisits
     * @return array{Expected_Departure: string, Span: mixed, Span_Future: mixed, Span_Start: string, idResource: mixed, idVisit: mixed[]}
     */
    public function getLockedVisits() {
        return $this->lockedVisits;
    }
}