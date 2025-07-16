<?php

namespace HHK\House;

use DateInterval;
use HHK\sec\Session;
use HHK\SysConst\VisitStatus;
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

class TrackFutureVisits {

    public static function updateFutureVisits(\PDO $dbh, \DateTime $pivotDate) {

        $spans = self::findFutureVisitSpans($dbh, $pivotDate);

        // Check Validity


        $updatedSpans = 0;
        foreach ($spans as $s) {
            $updatedSpans = self::bumpToPivot($dbh, $pivotDate, $s);
        }

        return $updatedSpans;
    }
    protected static function bumpToPivot(\PDO $dbh, \Datetime $pivotDate, array $span) {

        $uS = Session::getInstance();

        // Update checked-in visit
        $visitRs = new VisitRS();
        $visitRs->idVisit -> setStoredVal($span[0]['idVisit']);
        $visitRs->Span->setStoredVal($span[0]['Span']);
        $visitRs->Expected_Departure->setNewVal($pivotDate->format("Y-m-d $uS->CheckOutTime:00:00"));

        if (EditRS::update($dbh,$visitRs, [$visitRs->idVisit, $visitRs->Span]) < 1) {
            return false;
        }


        // Update checked in stays
        $stayRs = new StaysRS();
        $stayRs->idVisit->setStoredVal($span[0]['idVisit']);
        $stayRs->Visit_Span->setStoredVal($span[0]['Span']);
        $stayRs->Status->setStoredVal(VisitStatus::Active);
        $stayRs->Expected_Co_Date->setNewVal($pivotDate->format("Y-m-d $uS->CheckOutTime:00:00"));

        EditRS::update($dbh, $stayRs, [$stayRs->idVisit, $stayRs->Visit_Span, $stayRs->Status]);


        // Update future span(s)
        $startDate = $pivotDate;
        $rcrdsUpdated = 0;

        foreach ($span as $s) {

            $visitRs = new VisitRS();
            $visitRs->idVisit->setStoredVal($s['idVisit']);
            $visitRs->Span->setStoredVal($s['Span_Future']);

            $visitRs->Span_Start->setNewVal($startDate->format("Y-m-d $uS->CheckInTime:00:00"));
            $startDate->add(new DateInterval('P' . $s['Days'] . 'D'));
            $visitRs->Expected_Departure->setNewVal($startDate->format("Y-m-d $uS->CheckOutTime:00:00"));

            $rcrdsUpdated += EditRS::update($dbh, $visitRs, [$visitRs->idVisit, $visitRs->Span]);
        }

        return $rcrdsUpdated;
    }

    protected static function findFutureVisitSpans(\PDO $dbh, \DateTime $pivotDate) {

        $visits = [];

        // Collect all visits with future spans
        $stmt = $dbh->prepare("Select
            v.idVisit,
            v.Span,
            v.Span_Start,
            v.Expected_Departure,
            vf.Span AS Span_Future,
            vf.Span_Start AS Span_Future_Start,
            vf.Expected_Departure AS Span_Future_Expected_Departure,
            DATEDIFF(
                    DATE(IFNULL(vf.Span_End, datedefaultnow(vf.Expected_Departure))),
                    DATE(vf.Span_Start)) as Days
        from visit v JOIN visit vf on vf.Status = 'r' and vf.idVisit = v.idVisit
	    where v.`Status` = 'a' and DATE(v.Expected_Departure) < :pivotDate
        ORDER BY vf.idVisit, vf.Span;");

        $stmt->execute([
            ":pivotDate" => $pivotDate->format('Y-m-d 00-00-00'),
        ]);

        $idVisit = 0;
        $spans = [];

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
            $visits[$r['idVisit']] = $spans;
        }

        return $visits;
    }
}