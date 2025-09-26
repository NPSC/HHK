<?php
namespace HHK\Admin\Import;

use HHK\CreateMarkupFromDB;
use HHK\HTMLControls\HTMLContainer;

class ImportMarkup {

    protected \PDO $dbh;
    protected Import $import;

    public function __construct(\PDO $dbh) {
        $this->dbh = $dbh;
        $this->import = new Import($dbh);
    }

    public function generateMkup(bool $includeImportTbl = false, $includeTblDesc = false){
        $importTbl = "";
        $tblDesc = "";
        if($includeImportTbl){

            $query = "select n.idName as `ExternalId`, i.* from `" . Upload::TBL_NAME . "` i left join `name` n on i.importId = n.External_Id group by i.importId order by i.importId";
            $stmt = $this->dbh->query($query);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $importTbl = CreateMarkupFromDB::generateHTML_Table($rows, Upload::TBL_NAME);

        }

        if($includeTblDesc){
            $query = "desc `" . Upload::TBL_NAME . "`";
            $stmt = $this->dbh->query($query);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $tblDesc = HTMLContainer::generateMarkup("div", CreateMarkupFromDB::generateHTML_Table($rows, "tblDesc"), ["class"=>"mb-3"]);
        }

        return $this->generateSummaryMkup() . $tblDesc . $importTbl;
    }

    public function generateSummaryMkup(){
        return HTMLContainer::generateMarkup("h2", "Summary") . HTMLContainer::generateMarkup("div",
             $this->getStayMkup() . $this->getPeopleMkup() . $this->getHospitalMkup() . $this->getDoctorMkup(). $this->getRoomMkup() . $this->getGenLookupMappingMkup()
        , array("class"=>"hhk-flex mb-3"));
    }

    public function importSize(){
        $query = "select count(*) as `Size` from `" . Upload::TBL_NAME . "`";
        $stmt = $this->dbh->query($query);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if(isset($rows[0]["Size"])){
            return $rows[0]["Size"];
        }else{
            return 0;
        }
    }

    public function getHospitalInfo(string $fieldName = ""){
        try{
            $query = "select h.idHospital, ifnull(h.Title, 'unknown') as 'HHK Hospital', i.`$fieldName`, count(*) as `numRecords` from " . Upload::TBL_NAME . " i left join hospital h on h.Title = i.`$fieldName` group by `Hospital`;";
            $stmt = $this->dbh->query($query);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }catch(\PDOException $e){
            return false;
        }
        
    }

    private function getHospitalMkup(){
        $hospitalInfo = $this->getHospitalInfo($this->import->fieldMapping["hospital"]);

        if (is_array($hospitalInfo)) {
            $mkup = HTMLContainer::generateMarkup(
                "div",
                HTMLContainer::generateMarkup("h3", "Hospitals" . HTMLContainer::generateMarkup("button", "Create Missing Hospitals", array("data-entity" => "Hosps", "class" => "makeMissing ui-button ui-corner-all ml-2"))) .
                CreateMarkupFromDB::generateHTML_Table($hospitalInfo, "hosp")
                ,
                array("class" => "ui-widget ui-widget-content hhk-widget-content ui-corner-all mr-2")
            );
        }else{
            $mkup = '';
        }

        return $mkup;
    }

    private function getPeopleInfo(string $uniqueId = "", string $memberType = ""){
        try {
            //$query = "select 'Guests' as `Member Type`, count(distinct(concat(GuestLast,GuestFirst))) as `numRecords` from " . Upload::TBL_NAME . " i UNION select 'Patients' as `Member Type`, count(distinct(PatientId)) as `numRecords` from " . Upload::TBL_NAME . " i;";
            $query = "select 'People' as `Member Type`, count(distinct $uniqueId) as `numRecords` from " . Upload::TBL_NAME . " i ";
            
            $stmt = $this->dbh->query($query);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }catch(\PDOException $e){
            return [];
        }
    }

    private function getPeopleMkup(){
        $mkup = HTMLContainer::generateMarkup("div",
            HTMLContainer::generateMarkup("h3", "People") .
            CreateMarkupFromDB::generateHTML_Table($this->getPeopleInfo("importId"), "people")
            , array("class"=>"ui-widget ui-widget-content hhk-widget-content ui-corner-all mr-2"));

        return $mkup;
    }

    public function getRoomInfo(string $fieldName = ""){
        try{
            $query = "select r.idResource, ifnull(r.Title, 'unknown') as `HHK room`, i.`$fieldName` from " . Upload::TBL_NAME . " i left join `resource` r on i.`$fieldName` = r.Title where i.`$fieldName` != '' group by i.RoomNum order by i.`$fieldName`";
            $stmt = $this->dbh->query($query);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }catch(\Exception $e){
            return false;
        }
    }

    private function getRoomMkup(){
        $roomInfo = $this->getRoomInfo($this->import->fieldMapping["room"]);
        if(is_array($roomInfo)){
            $mkup = HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("h3", "Rooms" . HTMLContainer::generateMarkup("button", "Create Missing Rooms", array("data-entity"=>"Rooms", "class"=>"makeMissing ui-button ui-corner-all ml-2"))) .
                CreateMarkupFromDB::generateHTML_Table($roomInfo, "room")
                , array("class"=>"ui-widget ui-widget-content hhk-widget-content ui-corner-all mr-2"));
        }else{
            $mkup = '';
        }
        return $mkup;
    }

    /**
     * Find/match HHK gen lookups to a specific import field
     * @param string $genLookupTableName
     * @param string $importFieldName
     * @return bool|array{id:int, HHK Name: string, Import Name: string}
     */
    public function getGenLookupInfo(string $genLookupTableName = "", string $importFieldName = ""){
        try{
            $query = "select d.`Code` as `id`, ifnull(d.`Description`, '') as `HHK Name`, i.`$importFieldName` as `Import Name` from `" . Upload::TBL_NAME . "` i left join `gen_lookups` d on i.`$importFieldName` = d.`Description` and d.`Table_Name` = '$genLookupTableName' where i.`$importFieldName` != '' group by i.`$importFieldName` order by d.Description, i.`$importFieldName`;";
            $stmt = $this->dbh->query($query);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }catch(\Exception $e){
            return false;
        }

    }

    private function getGenLookupMkup(string $genLookupTableName, string $importFieldName, string $title){
        $diagInfo = $this->getGenLookupInfo($genLookupTableName, $importFieldName);
        if(is_array($diagInfo)){
            $mkup = HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("h3", $title . HTMLContainer::generateMarkup("button", "Create Missing " . $genLookupTableName, array("data-importfieldname"=>$importFieldName, "class"=>"makeMissingGenLookups ui-button ui-corner-all ml-2"))) .
                CreateMarkupFromDB::generateHTML_Table($diagInfo, "tbl".$importFieldName)
                , array("class"=>"ui-widget ui-widget-content hhk-widget-content ui-corner-all mr-2"));
        }else{
            $mkup = "";
        }
        return $mkup;
    }

    private function getGenLookupMappingMkup(){
        $genLookupMapping = (new Import($this->dbh))->genLookupMapping;
        $mkup = "";
        foreach($genLookupMapping as $importFieldName=>$genLookupTableName){
            $mkup.= $this->getGenLookupMkup($genLookupTableName, $importFieldName, $importFieldName);
        }
        return $mkup;
    }

    public function getDoctorInfo(array $docNameFields){
        try{
            $query = "select n.`idName` as idDoctor, n.Name_Full as `HHK Doctor`, i.`docFirst`, i.`docLast` from `" . Upload::TBL_NAME . "` i 
        left join `name` n on i.`docFirst` = n.`Name_First` and i.`docLast` = n.`Name_Last` group by i.`docFirst`, i.`docLast`;";
        
            $stmt = $this->dbh->query($query);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }catch(\Exception $e){    
            return false;
        }

    }

    private function getDoctorMkup(){
        $doctorInfo = $this->getDoctorInfo($this->import->fieldMapping["doctor"]);
        if(is_array($doctorInfo)){
            $mkup = HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("h3", "Doctors" . HTMLContainer::generateMarkup("button", "Create Missing Doctors", array("data-entity"=>"Doctors", "class"=>"makeMissing ui-button ui-corner-all ml-2"))) .
                CreateMarkupFromDB::generateHTML_Table($doctorInfo, "docs")
                , array("class"=>"ui-widget ui-widget-content hhk-widget-content ui-corner-all mr-2"));
        }else{
            $mkup = "";
        }
        return $mkup;
    }

    private function getStayInfo(){
        try{
            $query = "select 'Checked Out' as `Status`, count(*) as `numRecords` from " . Upload::TBL_NAME . " i where i.Arrive != '' and i.Departure != '' UNION select 'Checked in' as `Status`, count(*) as `numRecords` from " . Upload::TBL_NAME . " i where i.Arrive != '' and i.Departure = ''";
            $stmt = $this->dbh->query($query);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }catch(\Exception $e){
            return false;
        }
    }

    private function getStayMkup(){
        $stayInfo = $this->getStayInfo();
        if(is_array($stayInfo)){
            $mkup = HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("h3", "Stays") .
                CreateMarkupFromDB::generateHTML_Table($this->getStayInfo(), "stays")
                , array("class"=>"ui-widget ui-widget-content hhk-widget-content ui-corner-all mr-2"));
        }else{
            $mkup = '';
        }

        return $mkup;
    }
}

?>