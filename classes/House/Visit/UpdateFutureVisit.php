<?php

namespace HHK\House;

use \DateInterval;
use HHK\House\Reservation\Reservation_1;
use HHK\sec\Session;
use HHK\SysConst\VisitStatus;
use HHK\TableLog\VisitLog;
use HHK\Tables\EditRS;
use HHK\Tables\Visit\StaysRS;
use HHK\Tables\Visit\VisitRS;


/*
 * UpdateFutureVisit.php - Intended to be run early each day to update the dates of visits with future spans.
 * Each visit will eat up it's future span until the future span is exausted, then delete the future span.
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010 - 2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Summary of UpdateFutureVisit - This class extends TrackFutureVisits to provide functionality for updating future visits.
 */
class UpdateFutureVisit extends TrackFutureVisits  {

    /**
     * Summary of findFutureVisitSpans
     * @param \PDO $dbh
     * @param \DateTime $pivotDate
     * @param mixed $selectedIdVisit
     * @return array<array>
     */
    protected static function findFutureVisitSpans(\PDO $dbh, \DateTime $pivotDate, $selectedIdVisit = 0) {

        $visits = [];
        $spans = [];
        $parms = [];

        // If a visit is selected, add it to the where clause.
        
        if ($selectedIdVisit > 0) {
            $parms[":idVisit"] = $selectedIdVisit;
        } else {
            return $visits; // No visits selected, return empty array.
        }

        // Collect all visits with with the next future span only.
        $stmt = $dbh->prepare("Select
            v.idVisit,
            v.Span,
            v.idReservation,
            v.Span_Start,
            v.Expected_Departure,
            v.idResource,
            vf.Span AS Future_Span,
            vf.Expected_Departure AS Future_Expected_Departure,
            vf.idResource as Future_idResource
        from visit v JOIN visit vf on vf.idVisit = v.idVisit and vf.Status = '" . VisitStatus::Reserved . "'
	    where v.`Status` = '" . VisitStatus::Active . "' AND v.idVisit = :idVisit AND v.Next_IdResource > 0 AND v.Next_IdResource = vf.idResource;
        ORDER BY vf.idVisit, vf.Span;");

        $stmt->execute($parms);

        // There is only one future span returned.
        While ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $spans[]= $r;
            $visits[$r['idVisit']] = $spans;

        }


        return $visits;
    }

}

