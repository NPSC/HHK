<?php
namespace HHK\House\Report;

use HHK\HTMLControls\{HTMLContainer, HTMLTable};
use HHK\sec\{Session};
use HHK\ColumnSelectors;
use HHK\SysConst\GLTableNames;
use HHK\ExcelHelper;
use HHK\sec\Labels;
use HHK\TableLog\HouseLog;

/**
 *
 * @author Eric
 *
 */
class NewGuest
{

    /**
     *
     * @var \DateTimeInterface
     */
    protected $startDT;

    /**
     *
     * @var \DateTimeInterface
     */
    protected $endDT;

    /**
     * Summary of numberNewGuests
     * @var
     */
    protected int $numberNewGuests;
    /**
     * Summary of numberReturnGuests
     * @var
     */
    protected int $numberReturnGuests;

    /**
     * Summary of numberNewPSGs
     * @var
     */
    protected int $numberNewPSGs;
    /**
     * Summary of numberReturnPSGs
     * @var
     */
    protected int $numberReturnPSGs;

    /**
     * Summary of newGuestIds
     * @var
     */
    protected array $newGuestIds;
    /**
     * Summary of newPSGIds
     * @var
     */
    protected array $newPSGIds;


    /**
     */
    public function __construct($startDate, $endDate)
    {
        $this->setStartDT($startDate);
        $this->setEndDT($endDate);
        $this->numberNewGuests = 0;
        $this->numberReturnGuests = 0;
        $this->numberNewPSGs = 0;
        $this->numberReturnPSGs = 0;
        $this->newGuestIds = [];
        $this->newPSGIds = [];
    }

    /**
     * Summary of doNewGuestReport
     * @param \PDO $dbh
     * @param \HHK\ColumnSelectors $colSelector
     * @param mixed $whereStr
     * @param mixed $local
     * @param \HHK\sec\Labels $labels
     * @return string|void
     */
    public function doNewGuestReport(\PDO $dbh, ColumnSelectors $colSelector, $whereStr, $local, Labels $labels) {

        // get session instance
        $uS = Session::getInstance();

        $pgTitle = $labels->getString('MemberType', 'primaryGuest', 'Primary Guest');

        $tbl = '';
        $reportRows = 0;

        $fltrdTitles = $colSelector->getFilteredTitles();
        $fltrdFields = $colSelector->getFilteredFields();

        if ($local) {

            $tbl = new HTMLTable();
            $th = '';

            foreach ($fltrdTitles as $t) {
                $th .= HTMLTable::makeTh($t);
            }
            $tbl->addHeaderTr($th);

        } else {

            $reportRows = 1;

            $file = 'NewGuests';

            $writer = new ExcelHelper($file);


            // build header
            $hdr = array();
            $colWidths = array();

            foreach($fltrdFields as $field){
                $hdr[$field[0]] = $field[4]; //set column header name and type;
                $colWidths[] = $field[5]; //set column width
            }

            $hdrStyle = $writer->getHdrStyle($colWidths);
            $writer->writeSheetHeader("Sheet1", $hdr, $hdrStyle);
            $reportRows++;

        }

        $stmt = $dbh->query($this->queryNewGuests($pgTitle, $whereStr));
        $this->numberNewGuests = $stmt->rowCount();

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            // Hospital
            $hospital = '';

            if ($r['idAssociation'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']]) && $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1] != '(None)') {
                $hospital .= $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1] . ' / ';
            }
            if ($r['idHospital'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idHospital']])) {
                $hospital .= $uS->guestLookups[GLTableNames::Hospital][$r['idHospital']][1];
            }

            $r['hospitalAssoc'] = $hospital;
            unset($r['idHospital']);
            unset($r['idAssociation']);

            $arrivalDT = new \DateTime($r['First Stay']);
            $this->newGuestIds[$r['idName']] = $r['idName'];

            if ($local) {

                $r['idName'] = HTMLContainer::generateMarkup('a', $r['idName'], array('href'=>'GuestEdit.php?id=' . $r['idName'] . '&psg=' . $r['idPsg']));

                $r['First Stay'] = $arrivalDT->format('c');

                $tr = '';
                foreach ($fltrdFields as $f) {
                    $tr .= HTMLTable::makeTd($r[$f[1]], $f[6]);
                }

                $tbl->addBodyTr($tr);

            } else {

                $r['First Stay'] = $arrivalDT->format('Y-m-d');


                $flds = array();

                foreach ($fltrdFields as $f) {
                    //$flds[$n++] = array('type' => $f[4], 'value' => $g[$f[1]], 'style'=>$f[5]);
                    $flds[] = $r[$f[1]];
                }

                $row = $writer->convertStrings($hdr, $flds);
                $writer->writeSheetRow("Sheet1", $row);

            }

        }   // End of while

        // Finalize and print.
        if ($local) {

            return $tbl->generateMarkup(array('id'=>'tblrpt', 'class'=>'display'));

        } else {
            HouseLog::logDownload($dbh, 'New Guest Report', "Excel", "New Guests Report for " . $this->getStartDT()->format("Y-m-d") . " - " . $this->getEndDT()->format("Y-m-d") . " downloaded", $uS->username);
            $writer->download();
        }
    }

    /**
     * Summary of queryNewGuests
     * @param mixed $pgTitle
     * @param mixed $whereStr
     * @return string
     */
    protected function queryNewGuests($pgTitle, $whereStr = '') {

        return "SELECT
    s.idName,
    IFNULL(g1.Description, '') AS `Name_Prefix`,
    n.Name_First,
    n.Name_Middle,
    n.Name_Last,
    IFNULL(g2.Description, '') AS `Name_Suffix`,
    CASE when s.idName = v.idPrimaryGuest then '$pgTitle' else '' end as `Primary`,
    CASE when IFNULL(na.Address_2, '') = '' THEN IFNULL(na.Address_1, '') ELSE CONCAT(IFNULL(na.Address_1, ''), ' ', IFNULL(na.Address_2, '')) END AS `Address`,
    IFNULL(na.City, '') AS `City`,
    IFNULL(na.County, '') AS `County`,
    IFNULL(na.State_Province, '') AS `State_Province`,
    IFNULL(na.Postal_Code, '') AS `Postal_Code`,
    IFNULL(na.Country_Code, '') AS `Country`,
	CASE WHEN (n.Preferred_Phone = 'no') THEN 'No Phone' ELSE IFNULL(np.Phone_Num, '') END AS `Phone`,
	CASE WHEN (n.Preferred_Email = 'no') THEN 'No Email' ELSE IFNULL(ne.Email, '') END AS `Email`,
    IFNULL(g3.Description, '') AS `Relationship`,
    IFNULL(ng.idPsg, 0) as `idPsg`,
    IFNULL(hs.idHospital, 0) AS `idHospital`,
    IFNULL(hs.idAssociation, 0) AS `idAssociation`,
	IFNULL(v.Actual_Departure, '') AS `Visit End`,
    MIN(s.Span_Start_Date) AS `First Stay`,
    IFNULL(ra.Name_First, '') as `Referral_Agent_First`,
    IFNULL(ra.Name_Last, '') as `Referral_Agent_Last`
FROM
    stays s
        JOIN
    visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
        JOIN
    `name` n ON s.idName = n.idname
        LEFT JOIN
    name_address na ON n.idName = na.idName
        AND n.Preferred_Mail_Address = na.Purpose
        LEFT JOIN
    name_phone np ON n.idName = np.idName AND n.Preferred_Phone = np.Phone_Code
        LEFT JOIN
    name_email ne ON n.idName = ne.idName AND n.Preferred_Email = ne.Purpose
        LEFT JOIN
    hospital_stay hs ON v.idHospital_stay = hs.idHospital_stay
        LEFT JOIN
    `name` ra on hs.idReferralAgent = ra.idName
        LEFT JOIN
    `name_guest` ng ON s.idName = ng.idName and hs.idPsg = ng.idPsg
        LEFT JOIN
    gen_lookups g1 ON g1.`Table_Name` = 'Name_Prefix'
        AND g1.`Code` = n.Name_Prefix
        LEFT JOIN
    gen_lookups g2 ON g2.`Table_Name` = 'Name_Suffix'
        AND g2.`Code` = n.Name_Suffix
	left join
    `gen_lookups` `g3` on `g3`.`Table_Name` = 'Patient_Rel_Type'
        and `g3`.`Code` = `ng`.`Relationship_Code`
WHERE
    n.Member_Status != 'TBD'
        AND n.Record_Member = 1
        AND NOT DATE(s.Span_Start_Date) <=> DATE(s.Span_End_Date)
        $whereStr
GROUP BY s.idName
HAVING DATE(`First Stay`) >= DATE('" . $this->getStartDT()->format('Y-m-d') . "')
    AND DATE(`First Stay`) < DATE('" . $this->getEndDT()->format('Y-m-d') . "')
ORDER BY `First Stay`";

    }

    /**
     * Summary of doReturningGuests
     * @param \PDO $dbh
     * @param mixed $whereStr
     * @return void
     */
    public function doReturningGuests(\PDO $dbh, $whereStr = '') {

        // Returning stays in period with first stay start date less tham start date.
        $query = "SELECT
                s.idName,
                IFNULL(hs.idPsg, 0) as `idPsg`,
                s.Span_Start_Date
            FROM
                stays s
                    JOIN
                visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
                    JOIN
                `name` n ON s.idName = n.idname
                    LEFT JOIN
                hospital_stay hs ON v.idHospital_stay = hs.idHospital_stay
             WHERE
                n.Member_Status != 'TBD'
            	AND n.Record_Member = 1
            	$whereStr
                AND NOT DATE(s.Span_Start_Date) <=> DATE(s.Span_End_Date)
                AND DATE(s.Span_Start_Date) < DATE('" . $this->getEndDT()->format('Y-m-d') . "')
                AND DATE(s.Span_Start_Date) >= DATE('" . $this->getStartDT()->format('Y-m-d') . "')";

            	if (count($this->newGuestIds) > 0) {
            	    $query .= "AND s.idName not in (" . implode(',', $this->newGuestIds) . ")";
            	}

            	$query .= "GROUP BY s.idName;";

    	$stmt = $dbh->query($query);
    	$this->numberReturnGuests = $stmt->rowCount();

    }

    /**
     * Summary of doReturningPSGs
     * @param \PDO $dbh
     * @param mixed $whereStr
     * @return void
     */
    public function doReturningPSGs(\PDO $dbh, $whereStr = '') {

        // Returning stays in period with first stay start date less tham start date.
        $query = "SELECT
                IFNULL(hs.idPsg, 0) as `idPsg`,
                MIN(s.Span_Start_Date) AS `First Stay`
            FROM
                stays s
                    JOIN
                visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
                    JOIN
                `name` n ON s.idName = n.idname
                    LEFT JOIN
                hospital_stay hs ON v.idHospital_stay = hs.idHospital_stay
             WHERE
                n.Member_Status != 'TBD'
            	AND n.Record_Member = 1
            	$whereStr
                AND NOT DATE(s.Span_Start_Date) <=> DATE(s.Span_End_Date)
                AND DATE(s.Span_Start_Date) < DATE('" . $this->getEndDT()->format('Y-m-d') . "')
                AND DATE(s.Span_Start_Date) >= DATE('" . $this->getStartDT()->format('Y-m-d') . "')";

            	if (count($this->newPSGIds) > 0) {
            	    $query .= "AND IFNULL(hs.idPsg, 0) not in (" . implode(',', $this->newPSGIds) . ")";
            	}

            	$query .= "GROUP BY hs.idPsg";


    	$stmt = $dbh->query($query);
    	$this->numberReturnPSGs = $stmt->rowCount();

    }

    /**
     * Summary of doNewPSGs
     * @param \PDO $dbh
     * @param mixed $whereStr
     * @return void
     */
    public function doNewPSGs(\PDO $dbh, $whereStr = '') {

        // Returning stays in period with first stay start date less tham start date.
        $query = "SELECT
                IFNULL(hs.idPsg, 0) as `idPsg`,
                MIN(s.Span_Start_Date) AS `First Stay`
            FROM
                stays s
                    JOIN
                visit v on s.idVisit = v.idVisit and s.Visit_Span = v.Span
                    JOIN
                `name` n ON s.idName = n.idname
                    LEFT JOIN
                hospital_stay hs ON v.idHospital_stay = hs.idHospital_stay
             WHERE
                n.Member_Status != 'TBD'
            	AND n.Record_Member = 1
            	$whereStr
                AND NOT DATE(s.Span_Start_Date) <=> DATE(s.Span_End_Date)
            GROUP BY hs.idPsg
                HAVING  DATE(`First Stay`) >= DATE('" . $this->getStartDT()->format('Y-m-d') . "') AND DATE(`First Stay`) < DATE('" . $this->getEndDT()->format('Y-m-d') . "')
            ORDER BY `First Stay`;";


    	$stmt = $dbh->query($query);
    	$this->numberNewPSGs = $stmt->rowCount();

    	While ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    	    $this->newPSGIds[$r['idPsg']] = $r['idPsg'];
    	}

    }

    /**
     * @return int
     */
    public function getNumberNewGuests()
    {
        return $this->numberNewGuests;
    }

    /**
     * @return int
     */
    public function getNumberReturnGuests()
    {
        return $this->numberReturnGuests;
    }

    /**
     * @return int
     */
    public function getNumberNewPSGs()
    {
        return $this->numberNewPSGs;
    }

    /**
     * @return int
     */
    public function getNumberReturnPSGs()
    {
        return $this->numberReturnPSGs;
    }


    /**
     * @return \DateTimeInterface
     */
    public function getStartDT()
    {
        return $this->startDT;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getEndDT()
    {
        return $this->endDT;
    }

    /**
     * @param \DateTimeInterface $startDT
     */
    public function setStartDT($startDate)
    {
        if ($startDate instanceof \DateTimeInterface) {
            $this->startDT = $startDate;
        } else {
            $this->startDT = new \DateTime($startDate);
        }

    }

    /**
     * @param \DateTimeInterface $endDT
     */
    public function setEndDT($endDate)
    {
        if ($endDate instanceof \DateTimeInterface) {
            $this->endDT = $endDate;
        } else {
            $this->endDT = new \DateTime($endDate);
        }
    }



}

