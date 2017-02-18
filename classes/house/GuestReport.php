<?php
/**
 * GuestReport.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of GuestReport
 *
 * @author Eric
 */
class GuestReport {

    public static function demogReport(PDO $dbh, $startDate, $endDate, $whHosp, $whAssoc, $sourceZip) {

        if ($startDate == '') {
            return;
        }

        if ($endDate == '') {
            $endDate = date('Y-m-01');
        }

        $stDT = new DateTime($startDate);
        $stDT->setTime(0, 0, 0);

        $endDT = new DateTime($endDate);
        $endDT->setTime(0, 0, 0);

        if ($stDT === FALSE || $endDT === FALSE) {
            return;
        }

        $indxDT = new DateTime($startDate);
        $indxDT->setTime(0, 0, 0);

        $periodFormat = 'Y-m';


        $accum = array();
        $periods = array();
        $totalPSGs = array();

        $firstPeriod = $thisPeriod = $stDT->format($periodFormat);
        $badZipCodes = array();

        $now = new DateTime();
        $now->setTime(0, 0, 0);

        // Set up user selected demographics categoryeis.
        $demoCategorys = array();

        $fields = '';

        foreach (readGenLookupsPDO($dbh, 'Demographics') as $d) {

            if (strtolower($d[2]) == 'y') {


                if ($d[0] == 'Gender') {
                    $fields .= "ifnull(n.`Gender`,'') as `Gender`,";
                } else {
                    $fields .= "ifnull(nd.`" . $d[0] . "`, '') as `" . $d[0] . "`,";
                }

                $demoCategorys[$d[0]] = $d[0];
            }
        }


        // Set up the Months array
        $th = HTMLTable::makeTh('');
        while ($indxDT < $endDT) {

            $thisPeriod = $indxDT->format($periodFormat);

            $th .= HTMLTable::makeTh($indxDT->format('M, Y'));

//            $accum[$thisPeriod]['Guests']['p']['cnt'] = 0;
//            $accum[$thisPeriod]['Guests']['p']['title'] = 'New PSGs';
            $accum[$thisPeriod]['Guests']['o']['cnt'] = 0;
            $accum[$thisPeriod]['Guests']['o']['title'] = 'New Guests';

            // Demographics
            foreach ($demoCategorys as $d) {
                $accum[$thisPeriod][$d] = self::makeCounters(readGenLookupsPDO($dbh, $d));
            }

            $accum[$thisPeriod]['Distance'] = self::makeCounters(readGenLookupsPDO($dbh, 'Distance_Range', 'Substitute'));

            $periods[] = $thisPeriod;

            $indxDT->add(new DateInterval('P1M'));
        }

        $periods[] = 'Total';

//        $accum['Total']['Guests']['p']['cnt'] = 0;
//        $accum['Total']['Guests']['p']['title'] = 'New PSGs';
        $accum['Total']['Guests']['o']['cnt'] = 0;
        $accum['Total']['Guests']['o']['title'] = 'New Guests';
        // Totals
        foreach ($demoCategorys as $d) {
            $accum['Total'][$d] = self::makeCounters(readGenLookupsPDO($dbh, $d));
        }

        $accum['Total']['Distance'] = self::makeCounters(readGenLookupsPDO($dbh, 'Distance_Range', 'Substitute'));


        $th .= HTMLTable::makeTh("Total");

        $query = "SELECT
    s.idName,
    MIN(DATE(s.Span_Start_Date)) AS `minDate`,
    CONCAT(YEAR(s.Span_Start_Date),
            '-',
            MONTH(s.Span_Start_Date),
            '-01') AS `In_Date`,
    na.Postal_Code,
    $fields
    ng.idPsg,
    YEAR(s.Span_Start_Date) AS fy,
    hs.idHospital,
    hs.idAssociation
FROM
    stays s
        LEFT JOIN
    name n ON s.idName = n.idName
        LEFT JOIN
    name_demog nd ON s.idName = nd.idName
        LEFT JOIN
    name_address na ON s.idName = na.idName
        AND na.Purpose = n.Preferred_Mail_Address
        LEFT JOIN
    name_guest ng ON n.idName = ng.idName
        LEFT JOIN
    hospital_stay hs on ng.idPsg = hs.idPsg
WHERE
    n.Member_Status IN ('a' , 'in', 'd') $whHosp $whAssoc
        AND DATEDIFF(IFNULL(s.Span_End_Date, NOW()),
            s.Span_Start_Date) > 0
        AND DATE(s.Span_Start_Date) < DATE('" . $endDT->format('Y-m-01') . "')
GROUP BY s.idName
HAVING `minDate` >= DATE('" . $stDT->format('Y-m-01') . "')";



        $stmt = $dbh->query($query);


        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {

            $spanStDT = new DateTime($r['In_Date']);
            $startPeriod = $spanStDT->format($periodFormat);

            foreach ($demoCategorys as $d) {
                $accum[$startPeriod][$d][$r[$d]]['cnt']++;
                $accum['Total'][$d][$r[$d]]['cnt']++;
            }

            $accum[$startPeriod]['Guests']['o']['cnt']++;
            $accum['Total']['Guests']['o']['cnt']++;
//            $accum[$startPeriod]['Guests']['p']['cnt']++;

            try {
                $miles = self::calcZipDistance($dbh, $sourceZip, $r['Postal_Code']);

                foreach ($accum[$startPeriod]['Distance'] as $d => $val) {

                    if ($miles <= $d) {
                        $accum[$startPeriod]['Distance'][$d]['cnt']++;
                        $accum['Total']['Distance'][$d]['cnt']++;
                        break;
                    }
                }

            } catch (Hk_Exception_Runtime $hex) {

                $badZipCodes[$r['Postal_Code']] = 'y';
                $accum[$startPeriod]['Distance']['']['cnt']++;
                $accum['Total']['Distance']['']['cnt']++;

            }

            $totalPSGs[$r['idPsg']] = 'y';
        }

        $trs = array();     // tRow array
        $rowCount = 0;

        // Title Column
        foreach ($accum[$firstPeriod] as $k => $demog) {

            $trs[$rowCount++] = HTMLTable::makeTd(str_replace('_', ' ', $k), array('class' => 'hhk-tdTitle'));

            foreach ($demog as $indx) {
                $trs[$rowCount++] = HTMLTable::makeTd($indx['title'], array('class' => 'tdlabel'));
            }
        }

        // Data columns
        foreach ($periods as $col) {

            $rowCount = 0;

            foreach ($accum[$col] as $demog) {

                $trs[$rowCount++] .= HTMLTable::makeTd('', array('class' => 'hhk-tdTitle'));

                foreach ($demog as $indx) {
                    $trs[$rowCount++] .= HTMLTable::makeTd($indx['cnt'] > 0 ? $indx['cnt'] : '');
                }
            }
        }


        // create table
        $tbl = new HTMLTable();
        $tbl->addHeaderTr($th);

        foreach ($trs as $tr) {
            $tbl->addBodyTr($tr);
        }

        if (count($badZipCodes) > 0) {

            $zipList = '';

            foreach ($badZipCodes as $k => $v) {
                if ($k != '') {
                    $zipList .= ", " . $k;
                }
            }

            if ($zipList != '') {
                $tbl->addBodyTr(HTMLTable::makeTd("Bad zip codes") . HTMLTable::makeTd(substr($zipList, 2), array('colspan' => '12')) . HTMLTable::makeTd(count($badZipCodes)));
            }
        }

        return $tbl->generateMarkup(array('class'=>'hhk-tdbox'));
    }

    public static function calcZipDistance(PDO $dbh, $sourceZip, $destZip) {

        if (strlen($destZip) > 5) {
            $destZip = substr($destZip, 0, 5);
        }

        if ($destZip == $sourceZip) {
            return 0;
        }

        $miles = 0;
        $stmt = $dbh->prepare("select Zip_Code, Lat, Lng from postal_codes where Zip_Code in (:src, :dest)");
        $stmt->execute(array(':src' => $sourceZip, ':dest' => $destZip));

        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        if (count($rows) == 2) {
            $miles = self::calcDist($rows[0][1], $rows[0][2], $rows[1][1], $rows[1][2]);
        } else {
            throw new Hk_Exception_Runtime("One or both zip codes not found in zip table, source=$sourceZip, dest=$destZip.  ");
        }
        return $miles;
    }

    protected static function calcDist($lat_A, $long_A, $lat_B, $long_B) {

        $distance = sin(deg2rad((double)$lat_A)) * sin(deg2rad((double)$lat_B)) + cos(deg2rad((double)$lat_A)) * cos(deg2rad((double)$lat_B)) * cos(deg2rad((double)$long_A - (double)$long_B));
        $distance2 = (rad2deg(acos($distance))) * 69.09;

        return $distance2;
    }

    protected static function makeCounters($GL_Table, $includeBlank = TRUE) {
        $age = array();
        foreach ($GL_Table as $a) {
            $age[$a[0]]['cnt'] = 0;
            $age[$a[0]]['title'] = $a[1];
        }

        if ($includeBlank) {
            $age['']['cnt'] = 0;
            $age['']['title'] = 'Not Indicated';
        }

        return $age;
    }

}
