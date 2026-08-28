<?php
namespace HHK\House\Report;

use HHK\HTMLControls\{HTMLContainer, HTMLTable};
use HHK\sec\Session;
use HHK\SysConst\GLTableNames;
use HHK\sec\Labels;

/**
 *
 * @author Eric
 *
 */
class NewGuestReport extends AbstractReport implements ReportInterface
{

    protected int $numberNewGuests;

    protected int $numberReturnGuests;

    protected int $numberNewPSGs;

    protected int $numberReturnPSGs;

    protected array $newGuestIds;

    protected array $newPSGIds;

    protected bool $resultSetLoaded = false;

    protected bool $statsComputed = false;



    public function __construct(\PDO $dbh, array $request = [])
    {

        $this->numberNewGuests = 0;
        $this->numberReturnGuests = 0;
        $this->numberNewPSGs = 0;
        $this->numberReturnPSGs = 0;
        $this->newGuestIds = [];
        $this->newPSGIds = [];


        $uS = Session::getInstance();
        $this->reportTitle = $uS->siteName . ' New ' . Labels::getString('MemberType', 'visitor', 'Guest') . 's Report';

        $this->description = "This report shows the number of " . Labels::getString('MemberType', 'visitor', 'Guest') . "s STARTING their stay during the selected period. <br>This may not be the same number as the total number of " . Labels::getString('MemberType', 'visitor', 'Guest') . "s at the house.";
        $this->inputSetReportName = "newGuests";

        parent::__construct($dbh, $this->inputSetReportName, $request);


    }

    public function makeQuery(): void{

        $pgTitle = Labels::getString('MemberType', 'primaryGuest', 'Primary Guest');
        $whHospAssoc = $this->getHospitalFilterSql();

        $this->queryParams = [
            ':pgTitle' => $pgTitle,
            ':reportEnd' => $this->filter->getReportEnd(),
            ':reportStart' => $this->filter->getReportStart(),
        ];

        $this->query = "SELECT
    s.idName,
    IFNULL(g1.Description, '') AS `Name_Prefix`,
    n.Name_First,
    n.Name_Middle,
    n.Name_Last,
    IFNULL(g2.Description, '') AS `Name_Suffix`,
    CASE WHEN s.idName = v.idPrimaryGuest THEN :pgTitle ELSE '' END AS `Primary`,
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
        $whHospAssoc
GROUP BY s.idName
HAVING DATE(`First Stay`) >= DATE(:reportStart)
    AND DATE(`First Stay`) < DATE(:reportEnd)
ORDER BY `First Stay`";

    }

    /**
     * Builds the "and hs.idHospital in (...) and hs.idAssociation in (...)" clause
     * from the currently selected hospital filter.
     * @return string
     */
    protected function getHospitalFilterSql(): string {

        $whHosp = implode(",", $this->filter->getSelectedHosptials());
        $whAssoc = implode(",", $this->filter->getSelectedAssocs());

        $sql = '';

        if ($whHosp != '') {
            $sql .= " and hs.idHospital in (".$whHosp.") ";
        }

        if ($whAssoc != '') {
            $sql .= " and hs.idAssociation in (".$whAssoc.") ";
        }

        return $sql;
    }

    /**
     * Runs the report query and folds in the hospital/association title and
     * tracks the new guest ids used to exclude returning guests later.
     * @return array
     */
    public function getResultSet(): array {

        if (!$this->resultSetLoaded) {

            parent::getResultSet();

            $uS = Session::getInstance();

            foreach ($this->resultSet as $k => $r) {

                $hospital = '';

                if ($r['idAssociation'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']]) && $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1] != '(None)') {
                    $hospital .= $uS->guestLookups[GLTableNames::Hospital][$r['idAssociation']][1] . ' / ';
                }
                if ($r['idHospital'] > 0 && isset($uS->guestLookups[GLTableNames::Hospital][$r['idHospital']])) {
                    $hospital .= $uS->guestLookups[GLTableNames::Hospital][$r['idHospital']][1];
                }

                $this->resultSet[$k]['hospitalAssoc'] = $hospital;
                unset($this->resultSet[$k]['idHospital'], $this->resultSet[$k]['idAssociation']);

                $this->newGuestIds[$r['idName']] = $r['idName'];
            }

            $this->resultSetLoaded = true;
        }

        return $this->resultSet;
    }

    public function generateMarkup(string $outputType = ""){

        $this->getResultSet();

        foreach ($this->resultSet as $k => $r) {
            $this->resultSet[$k]['idName'] = HTMLContainer::generateMarkup('a', $r['idName'], array('href'=>'GuestEdit.php?id=' . $r['idName'] . '&psg=' . $r['idPsg']));
        }

        return parent::generateMarkup($outputType);
    }

    public function makeFilterMkup(): void{
        $this->filterMkup .= $this->filter->timePeriodMarkup()->generateMarkup();
        $this->filterMkup .= $this->filter->hospitalMarkup()->generateMarkup();
        $this->filterMkup .= $this->getColSelectorMkup();
    }

    public function makeFields(): array{

        $labels = Labels::getLabels();
        $uS = Session::getInstance();

        $fields = array();

        $fields[] = array("Id", 'idName', 'checked', '', 'string', '10', array());
        $fields[] = array("Prefix", 'Name_Prefix', 'checked', '', 'string', '15', array());
        $fields[] = array("First", 'Name_First', 'checked', '', 'string', '20', array());
        $fields[] = array("Middle", 'Name_Middle', 'checked', '', 'string', '20', array());
        $fields[] = array("Last", 'Name_Last', 'checked', '', 'string', '20', array());
        $fields[] = array("Suffix", 'Name_Suffix', 'checked', '', 'string', '15', array());
        $fields[] = array($labels->getString('MemberType', 'primaryGuest', 'Primary Guest'), 'Primary', 'checked', '', 'string', '20', array());

        $pFields = array('Address', 'City');
        $pTitles = array('Address', 'City');

        if ($uS->county) {
            $pFields[] = 'County';
            $pTitles[] = 'County';
        }

        $pFields = array_merge($pFields, array('State_Province', 'Postal_Code', 'Country'));
        $pTitles = array_merge($pTitles, array('State', 'Zip', 'Country'));

        $fields[] = array($pTitles, $pFields, '', '', 'string', '20', array());

        $fields[] = array('Phone', 'Phone', 'checked', '', 'string', '20', array());
        $fields[] = array('Email', 'Email', 'checked', '', 'string', '20', array());

        $fields[] = array("First Stay", 'First Stay', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');
        $fields[] = array("Visit End", 'Visit End', 'checked', '', 'MM/DD/YYYY', '15', array(), 'date');

        $fields[] = array($labels->getString('MemberType', 'patient', 'Patient')." Relation", 'Relationship', 'checked', '', 'string', '20', array());
        $fields[] = array($labels->getString('GuestEdit', 'psgTab', 'Patient Support Group')."  Id", 'idPsg', 'checked', '', 'string', '15', array());

        if (count($this->filter->getAList()) > 0) {
            $fields[] = array($labels->getString('hospital', 'hospital', 'Hospital')." / Assoc", 'hospitalAssoc', 'checked', '', 'string', '20', array());
        } else {
            $fields[] = array($labels->getString('hospital', 'hospital', 'Hospital'), 'hospitalAssoc', 'checked', '', 'string', '20', array());
        }

        $fields[] = array($labels->getString('hospital', 'referralAgent', 'Referral Agent')." First", 'Referral_Agent_First', '', '', 'string', '15', array());
        $fields[] = array($labels->getString('hospital', 'referralAgent', 'Referral Agent')." Last", 'Referral_Agent_Last', '', '', 'string', '15', array());

        return $fields;
    }

    public function makeSummaryMkup(): string {

        $this->computeGuestStats();

        $mkup = HTMLContainer::generateMarkup('p', 'Report Generated: ' . date('M j, Y'));

        $mkup .= HTMLContainer::generateMarkup('p', 'Report Period: ' . date('M j, Y', strtotime($this->filter->getReportStart())) . ' thru ' . date('M j, Y', strtotime($this->filter->getReportEnd())));

        $mkup .= HTMLContainer::generateMarkup('p', Labels::getString('hospital', 'hospitals', 'Hospitals') . ': ' . $this->filter->getSelectedHospitalsString());

        $this->statsMkup = $this->generateGuestStatsMkup();

        return $mkup;
    }

    /**
     * Builds the New/Returning Guests and PSGs stats box, placed in $this->statsMkup
     * @return string
     */
    protected function generateGuestStatsMkup(): string {

        $guestLabel = Labels::getString('MemberType', 'visitor', 'Guest');

        $numAllGuests = $this->numberNewGuests + $this->numberReturnGuests;
        $newGuestRatio = $numAllGuests > 0 ? ($this->numberNewGuests / $numAllGuests) * 100 : 0;

        $guestTbl = new HTMLTable();
        $guestTbl->addHeaderTr(HTMLTable::makeTh($guestLabel . 's') . HTMLTable::makeTh('Number') . HTMLTable::makeTh('Percent of Total'));
        $guestTbl->addBodyTr(HTMLTable::makeTd('New ' . $guestLabel . 's:', array('class'=>'tdlabel')) . HTMLTable::makeTd($this->numberNewGuests) . HTMLTable::makeTd(number_format($newGuestRatio) . '%'));
        $guestTbl->addBodyTr(HTMLTable::makeTd('Returning ' . $guestLabel . 's:', array('class'=>'tdlabel')) . HTMLTable::makeTd($this->numberReturnGuests) . HTMLTable::makeTd(number_format(100 - $newGuestRatio) . '%'));
        $guestTbl->addBodyTr(HTMLTable::makeTd('Total ' . $guestLabel . 's:', array('class'=>'tdlabel')) . HTMLTable::makeTd($numAllGuests));

        $numAllPSGs = $this->numberNewPSGs + $this->numberReturnPSGs;
        $newPsgRatio = $numAllPSGs > 0 ? ($this->numberNewPSGs / $numAllPSGs) * 100 : 0;

        $psgTbl = new HTMLTable();
        $psgTbl->addHeaderTr(HTMLTable::makeTh('PSGs') . HTMLTable::makeTh('Number') . HTMLTable::makeTh('Percent of Total'));
        $psgTbl->addBodyTr(HTMLTable::makeTd('New PSGs', array('class'=>'tdlabel')) . HTMLTable::makeTd($this->numberNewPSGs) . HTMLTable::makeTd(number_format($newPsgRatio) . '%'));
        $psgTbl->addBodyTr(HTMLTable::makeTd('Returning PSGs', array('class'=>'tdlabel')) . HTMLTable::makeTd($this->numberReturnPSGs) . HTMLTable::makeTd(number_format(100 - $newPsgRatio) . '%'));
        $psgTbl->addBodyTr(HTMLTable::makeTd('Total PSGs', array('class'=>'tdlabel')) . HTMLTable::makeTd($numAllPSGs));

        return HTMLContainer::generateMarkup("div",
            HTMLContainer::generateMarkup("h3", "Summary", ["class"=>"ui-widget-header ui-state-default ui-corner-top"]) .
            HTMLContainer::generateMarkup("div", $guestTbl->generateMarkup(['class'=>'mx-2 mb-2']) . $psgTbl->generateMarkup(['class'=>'mx-2 mb-2']), ["class"=>"hhk-flex hhk-tdbox hhk-visitdialog ui-widget-content ui-corner-bottom pt-3 pb-2", "style"=>"flex-flow:wrap;"])
        , ["class"=>"ui-widget mb-3 d-inline-block", "id"=>"summaryAccordion"]);
    }

    /**
     * Lazily computes new/returning guest and PSG counts. Requires the main
     * result set to have run first so newGuestIds is populated.
     * @return void
     */
    protected function computeGuestStats(): void {

        if ($this->statsComputed) {
            return;
        }

        $this->getResultSet();

        $this->doNewPSGs();
        $this->doReturningGuests();
        $this->doReturningPSGs();

        $this->statsComputed = true;
    }

    /**
     * Counts guests whose first stay in the period predates the period (i.e. returning).
     * @return void
     */
    protected function doReturningGuests(): void {

        $whHospAssoc = $this->getHospitalFilterSql();

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
            	$whHospAssoc
                AND NOT DATE(s.Span_Start_Date) <=> DATE(s.Span_End_Date)
                AND DATE(s.Span_Start_Date) < DATE(:endDate)
                AND DATE(s.Span_Start_Date) >= DATE(:startDate)";

        $params = [
            ':endDate' => $this->filter->getReportEnd(),
            ':startDate' => $this->filter->getReportStart(),
        ];

        if (count($this->newGuestIds) > 0) {
            $idPh = [];
            foreach (array_values($this->newGuestIds) as $i => $gid) {
                $ph = ':exGid' . $i;
                $idPh[] = $ph;
                $params[$ph] = $gid;
            }
            $query .= " AND s.idName NOT IN (" . implode(', ', $idPh) . ")";
        }

        $query .= " GROUP BY s.idName";

        $stmt = $this->dbh->prepare($query);
        $stmt->execute($params);
        $this->numberReturnGuests = $stmt->rowCount();

    }

    /**
     * Counts PSGs whose first stay in the period predates the period (i.e. returning).
     * @return void
     */
    protected function doReturningPSGs(): void {

        $whHospAssoc = $this->getHospitalFilterSql();

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
            	$whHospAssoc
                AND NOT DATE(s.Span_Start_Date) <=> DATE(s.Span_End_Date)
                AND DATE(s.Span_Start_Date) < DATE(:endDate)
                AND DATE(s.Span_Start_Date) >= DATE(:startDate)";

        $params = [
            ':endDate' => $this->filter->getReportEnd(),
            ':startDate' => $this->filter->getReportStart(),
        ];

        if (count($this->newPSGIds) > 0) {
            $idPh = [];
            foreach (array_values($this->newPSGIds) as $i => $pid) {
                $ph = ':exPid' . $i;
                $idPh[] = $ph;
                $params[$ph] = $pid;
            }
            $query .= " AND IFNULL(hs.idPsg, 0) NOT IN (" . implode(', ', $idPh) . ")";
        }

        $query .= " GROUP BY hs.idPsg";

        $stmt = $this->dbh->prepare($query);
        $stmt->execute($params);
        $this->numberReturnPSGs = $stmt->rowCount();

    }

    /**
     * Counts PSGs whose first stay in the period falls within the period (i.e. new),
     * and records their ids in $this->newPSGIds.
     * @return void
     */
    protected function doNewPSGs(): void {

        $whHospAssoc = $this->getHospitalFilterSql();

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
            	$whHospAssoc
                AND NOT DATE(s.Span_Start_Date) <=> DATE(s.Span_End_Date)
            GROUP BY hs.idPsg
                HAVING  DATE(`First Stay`) >= DATE(:startDate) AND DATE(`First Stay`) < DATE(:endDate)
            ORDER BY `First Stay`";

        $stmt = $this->dbh->prepare($query);
        $stmt->execute([
            ':startDate' => $this->filter->getReportStart(),
            ':endDate' => $this->filter->getReportEnd(),
        ]);
        $this->numberNewPSGs = $stmt->rowCount();

        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $this->newPSGIds[$r['idPsg']] = $r['idPsg'];
        }

    }

    /**
     * @return int
     */
    public function getNumberNewGuests()
    {
        $this->computeGuestStats();
        return $this->numberNewGuests;
    }

    /**
     * @return int
     */
    public function getNumberReturnGuests()
    {
        $this->computeGuestStats();
        return $this->numberReturnGuests;
    }

    /**
     * @return int
     */
    public function getNumberNewPSGs()
    {
        $this->computeGuestStats();
        return $this->numberNewPSGs;
    }

    /**
     * @return int
     */
    public function getNumberReturnPSGs()
    {
        $this->computeGuestStats();
        return $this->numberReturnPSGs;
    }

}
