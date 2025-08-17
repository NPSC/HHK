<?php

namespace HHK\House\Visit;

use \DateInterval;
use DateTimeImmutable;
use HHK\House\Reservation\Reservation_1;
use HHK\House\Reservation\ReservationSvcs;
use HHK\sec\Session;
use HHK\SysConst\ReservationStatus;
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

        $visits = self::findFutureVisitSpans($dbh, $selectedIdVisit);

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
        $activeNextIdResource = $spans[0]['Next_IdResource'];

        $today = new \DateTime();
        $today->setTime(0, 0);

        // Right now, this is the active span expected departure
        $expectedDepartureDT = new \DateTime($spans[0]['Expected_Departure']);
        $expectedDepartureDT->setTime(0, 0);

        // If the active span ends in the past, then we need to update it.
        if ($expectedDepartureDT < $today) {

            // For now, don't deal with it.  (after all my work! Whaaa.)
            return;

            $expectedDepartureDT = $today;

            // Update checked-in visit
            $visitRs = new VisitRS();
            $visitRs->idVisit -> setStoredVal($idVisit);
            $visitRs->Span->setStoredVal($activeSpan);
            $rows = EditRS::select($dbh, $visitRs, [$visitRs->idVisit, $visitRs->Span]);
            EditRS::loadRow($rows[0], $visitRs);

            // Set new expected dept.
            $visitRs->Expected_Departure->setNewVal($expectedDepartureDT->format("Y-m-d $uS->CheckOutTime:00:00"));
            $upCtr = Visit::updateVisitRecordStatic($dbh, $visitRs, $uS->username);

            if ($upCtr > 0) {

                // Update any stays
                Visit::setStaysExpectedEnd($dbh, $stays, $expectedDepartureDT);

                // Move any reservations away.
                ReservationSvcs::moveResvAway($dbh, new \DateTime($visitRs->Span_Start->getStoredVal()), $expectedDepartureDT, $visitRs->idResource->getStoredVal(), $uS->username);
            }
        }

        // eat up any not needed future spans.
        $nextValidSpan = $this->eatUpFutureSpans(
            $dbh,
            $spans,
            $idVisit,
            $activeNextIdResource,
            $expectedDepartureDT);

        // Update the start date of any next span
        if (isset($spans[$nextValidSpan])) {
            $dbh->exec("update visit set Span_Start = '" . $expectedDepartureDT->format("Y-m-d $uS->CheckOutTime:00:00") . "' WHERE idVisit= $idVisit AND Span=" . $spans[$nextValidSpan]['Span']);
        }
        return;
    }

    /**
     * Summary of eatUpFutureSpans
     * @param \PDO $dbh
     * @param array $spans
     * @param int $idVisit
     * @param int $activeNextIdResource
     * @param \DateTime $expectedDepartureDT
     * @return int
     */
    protected function eatUpFutureSpans(\PDO $dbh, $spans, $idVisit, &$activeNextIdResource, &$expectedDepartureDT) {

        $uS = Session::getInstance();
        $nextValidSpan = 1;
        $spanDeleted = false;

        // eat up any not needed future spans.
        for ($s = 1; $s < count($spans); $s++) {

            $myExpDepartureDT = new \DateTime($spans[$s]['Expected_Departure']);
            $myExpDepartureDT->setTime(0, 0);
            $myNextIdResource = $spans[$s]['Next_IdResource'];

            if ($myExpDepartureDT <= $expectedDepartureDT || $spans[0]['idResource'] == $spans[$s]['idResource']) {

                // Delete this future span
                $dbh->exec("Delete from visit where idVisit = $idVisit and Span = " . $spans[$s]['Span']);

                // Log delete
                $logText['visit'] = $idVisit;
                VisitLog::logVisit($dbh, $idVisit, $spans[$s]['Span'], $spans[$s]['idResource'], $spans[$s]['idRegistration'], $logText, "delete", $uS->username);

                // get the new nextIdResource
                $activeNextIdResource = $myNextIdResource;

                // And expected departure
                if ($myExpDepartureDT > $expectedDepartureDT){
                    $expectedDepartureDT = $myExpDepartureDT;
                }

                $spanDeleted = true;

            } else {
                $nextValidSpan = $s;
                break;
            }
        }

        // update active span
        if ($spanDeleted) {
            // Update checked-in visit again
            $visitRs = new VisitRS();
            $visitRs->idVisit -> setStoredVal($idVisit);
            $visitRs->Span->setStoredVal($spans[0]['Span']);
            $rows = EditRS::select($dbh, $visitRs, [$visitRs->idVisit, $visitRs->Span]);
            EditRS::loadRow($rows[0], $visitRs);

            // Set new expected dept.
            $visitRs->Expected_Departure->setNewVal($expectedDepartureDT->format("Y-m-d $uS->CheckOutTime:00:00"));
            $visitRs->Next_IdResource->setNewVal($activeNextIdResource);

            $upCtr = Visit::updateVisitRecordStatic($dbh, $visitRs, $uS->username);

            if ($upCtr > 0) {

                // Update any stays
                Visit::setStaysExpectedEnd($dbh, $stays, $expectedDepartureDT);

                // Move any reservations away.
                ReservationSvcs::moveResvAway($dbh, new \DateTime($visitRs->Span_Start->getStoredVal()), $expectedDepartureDT, $visitRs->idResource->getStoredVal(), $uS->username);
            }
        }

        return $nextValidSpan;
    }


    /**
     * Summary of findFutureVisitSpans- returns an array of visits with future spans.
     * @param \PDO $dbh
     * @return array<array>
     */
    protected function findFutureVisitSpans(\PDO $dbh, $selectedIdVisit = 0) {

        $visits = [];

        if ($selectedIdVisit > 0) {
            // Collect all visits with with the next future span only.
            $stmt = $dbh->query("Select * FROM visit WHERE ((`Status` = 'a' AND Next_IdResource > 0) OR Status = 'r') && idVisit = $selectedIdVisit ORDER BY idVisit, Span;");
        } else {
            // Collect all visits with with the next future span only.
            $stmt = $dbh->query("Select * FROM visit WHERE (`Status` = 'a' AND Next_IdResource > 0) OR Status = 'r' ORDER BY idVisit, Span;");
        }

        $idVisit = 0;
        $spans = [];

        // Pack each visit into an array of spans.
        While ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            // Reset for new visit?
            if ($idVisit != $r['idVisit']) {

                // Save the previous visit spans, if not just starting.
                if ($idVisit != 0) {
                    $visits[$idVisit] = $spans;
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

}