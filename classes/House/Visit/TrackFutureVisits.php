<?php

namespace HHK\House\Visit;

use \DateInterval;
use DateTimeImmutable;
use HHK\House\Reservation\Reservation_1;
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
     * Summary of updateFutureVisits
     * @param \PDO $dbh
     * @return bool|int
     */
    public function updateFutureVisits(\PDO $dbh, $selectedIdVisit = 0) {

        $visits = self::findFutureVisitSpans($dbh);

        // Anything returned?
        if (count($visits) < 1) {
            return 0;
        }

        foreach ($visits as $spans) {

            // Update the spans.
            self::UpdateVisit($dbh, $spans);
        }

        return 0;
    }


    /**
     * Summary of UpdateVisit
     * @param \PDO $dbh
     * @param array $spans  // the spans for a single visit.
     * @return void
     */
    protected function UpdateVisit(\PDO $dbh, array $spans) {

        $uS = Session::getInstance();

        // Need at least 2 spans to do anything.
        if (count($spans) < 2) {
            return;
        }

        // First span must be active, and next idresource must be set
        if ($spans[0]['Status'] != VisitStatus::Active && $spans[0]['Next_IdResource'] < 1) {
            return;
        }

        $activeSpan = $spans[0]['Span'];
        $idVisit = $spans[0]['idVisit'];

        $today = new \DateTime();
        $today->setTime(0, 0);
        $expectedDepartureDT = new \DateTime($spans[0]['Expected_Departure']);
        $expectedDepartureDT->setTime(0, 0);


        // If the active span ends in the past, then we need to update it.
        if ($expectedDepartureDT < $today) {

            // Update checked-in visit
            $visitRs = new VisitRS();
            $visitRs->idVisit -> setStoredVal($idVisit);
            $visitRs->Span->setStoredVal($activeSpan);
            $visitRs->Expected_Departure->setNewVal($today->format("Y-m-d $uS->CheckOutTime:00:00"));

            $upCtr = EditRS::update($dbh, $visitRs, [$visitRs->idVisit, $visitRs->Span]);

            if ($upCtr > 0) {
                // Update the visit log
                $logText = VisitLog::getUpdateText($visitRs);
                EditRS::updateStoredVals($visitRs);
                VisitLog::logVisit($dbh, $visitRs->idVisit->getStoredVal(), $visitRs->Span->getStoredVal(), $visitRs->idResource->getStoredVal(), $visitRs->idRegistration->getStoredVal(), $logText, "update", $uS->username);
            }

            $expectedDepartureDT = $today;
        }

        
        $lastSpanIndex = 0;

        for ($s = 1; $s < count($spans); $s++) {

            $myExpDepartureDT = new \DateTime($spans[$s]['Expected_Departure']);
            $myExpDepartureDT->setTime(0, 0);
            $myNextIdResource = $spans[$s]['Next_IdResource'];

            if ($myExpDepartureDT <= $expectedDepartureDT) {
                // Delete this future span
                $dbh->exec("Delete from visit where idVisit = " . $spans[$s]['idVisit'] . " and Span = " . $spans[$s]['Span']);

                // if the next idresoure is the same as my idresouce, then what?

                // TODO
                // update the last span with the new nextIdResource
                $dbh->exec("Update visit set Next_IdResource = " . $myNextIdResource . " where idVisit = " . $spans[$lastSpanIndex]['idVisit'] . " and Span = " . $spans[$lastSpanIndex]['Span']);

                // Move reservations away

                continue;
            }
            
            // Set my span start to the new expected departure
            $dbh->exec("Update visit set Span_Start = '" . $expectedDepartureDT->format("Y-m-d $uS->CheckOutTime:00:00") . "'where idVisit = " . $spans[$s]['idVisit'] . " and Span = " . $spans[$s]['Span']);

            // move reservations away.

            $lastSpanIndex = $s;

            $expectedDepartureDT = $myExpDepartureDT;

        }



        return;
    }


    /**
     * Summary of findFutureVisitSpans- returns an array of visits with future spans.
     * @param \PDO $dbh
     * @return array<array>
     */
    protected function findFutureVisitSpans(\PDO $dbh) {

        $visits = [];

        // Collect all visits with with the next future span only.
        $stmt = $dbh->query("Select * FROM visit WHERE (`Status` = 'a' AND Next_IdResource > 0) OR Status = 'r' ORDER BY idVisit, Span;");

        $idVisit = 0;
        $spans = [];

        // Pack each visit into an array of spans.
        While ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            // Reset for new visit?
            if ($idVisit != $r['idVisit']) {

                // Save the previous visit spans, if not just starting.
                if ($idVisit != 0) {
                    $visits[$r['idVisit']] = $spans;
                }

                // Reset for new visit.
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
}