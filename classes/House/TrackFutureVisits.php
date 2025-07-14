<?php

namespace HHK\House;

use HHK\sec\Session;
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

class TrackFutureVisits {

    protected $balanceDate;

    public function __construct(\DateTime $balanceDate) {

        $this->balanceDate = $balanceDate;

    }

    public function resolve(\PDO $dbh) {

    }

    protected function findFutureVisitSpans(\PDO $dbh, \DateTime $pivotDate) {


        // Collect all visits with future spans
        $stmt = $dbh->prepare("Select
            v.idVisit,
            v.Span,
            v.Span_Start,
            v.Expected_Departure,
            v.`Status`,
            vf.idVisit AS idFuture,
            vf.Span AS Span_Future,
            vf.Span_Start AS Span_Future_Start,
            vf.Expected_Departure AS Span_Future_Expected_Departure,
            vf.`Status` AS Status_Future
        from visit v JOIN visit vf on vf.Status = 'r' and vf.idVisit = v.idVisit
	    where v.`Status` = 'a' and DATE(v.Expected_Departure) < :balanceDate;");

        $stmt->execute([
            ":balanceDate" => $pivotDate->format('Y-m-d'),
        ]);

        While ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

        }
    }
}