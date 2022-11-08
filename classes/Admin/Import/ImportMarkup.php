<?php
namespace HHK\Admin\Import;

use HHK\CreateMarkupFromDB;
use HHK\HTMLControls\HTMLContainer;

class ImportMarkup {

    protected \PDO $dbh;

    public function __construct(\PDO $dbh) {
        $this->dbh = $dbh;
    }

    public function generateMkup(bool $includeImportTbl = false){
        $query = "select n.idName as `ExternalId`, i.* from `" . Upload::TBL_NAME . "` i left join `name` n on i.importId = n.External_Id group by i.importId order by `PatientId`, `PatientLast`, `PatientFirst`";
        $stmt = $this->dbh->query($query);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if($includeImportTbl){
            $importTbl = CreateMarkupFromDB::generateHTML_Table($rows, Upload::TBL_NAME);
        }else{
            $importTbl = "";
        }
        return $this->generateSummaryMkup() . $importTbl;
    }

    public function generateSummaryMkup(){
        return HTMLContainer::generateMarkup("h2", "Summary") . HTMLContainer::generateMarkup("div",
            $this->getPeopleMkup() . $this->getStayMkup() . $this->getHospitalMkup(). $this->getRoomMkup() . $this->getDiagMkup()
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

    public function getHospitalInfo(){
        $query = "select h.idHospital, ifnull(h.Title, 'unknown') as 'HHK Hospital', `Hospital`, count(*) as `numRecords` from " . Upload::TBL_NAME . " i left join hospital h on h.Title = i.Hospital group by `Hospital`;";
        $stmt = $this->dbh->query($query);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getHospitalMkup(){
        $mkup = HTMLContainer::generateMarkup("div",
            HTMLContainer::generateMarkup("h3", "Hospitals" . HTMLContainer::generateMarkup("button", "Create Missing Hospitals", array("id"=>"makeHosps", "class"=>"ui-button ui-corner-all ml-2"))) .
            CreateMarkupFromDB::generateHTML_Table($this->getHospitalInfo(), "hosp")
            , array("class"=>"ui-widget ui-widget-content hhk-widget-content ui-corner-all mr-2"));

        return $mkup;
    }

    private function getPeopleInfo(){
        $query = "select 'Guests' as `Member Type`, count(distinct(concat(GuestLast,GuestFirst))) as `numRecords` from " . Upload::TBL_NAME . " i UNION select 'Patients' as `Member Type`, count(distinct(PatientId)) as `numRecords` from " . Upload::TBL_NAME . " i;";
        $stmt = $this->dbh->query($query);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getPeopleMkup(){
        $mkup = HTMLContainer::generateMarkup("div",
            HTMLContainer::generateMarkup("h3", "People") .
            CreateMarkupFromDB::generateHTML_Table($this->getPeopleInfo(), "people")
            , array("class"=>"ui-widget ui-widget-content hhk-widget-content ui-corner-all mr-2"));

        return $mkup;
    }

    public function getRoomInfo(){
        try{
            $query = "select r.idResource, ifnull(r.Title, 'unknown') as `HHK room`, i.`RoomNum` from " . Upload::TBL_NAME . " i left join `resource` r on i.RoomNum = r.Title where i.RoomNum != '' group by i.RoomNum order by i.RoomNum";
            $stmt = $this->dbh->query($query);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }catch(\Exception $e){
            return false;
        }
    }

    private function getRoomMkup(){
        $roomInfo = $this->getRoomInfo();
        if(is_array($roomInfo)){
            $mkup = HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("h3", "Rooms" . HTMLContainer::generateMarkup("button", "Create Missing Rooms", array("id"=>"makeRooms", "class"=>"ui-button ui-corner-all ml-2"))) .
                CreateMarkupFromDB::generateHTML_Table($this->getRoomInfo(), "room")
                , array("class"=>"ui-widget ui-widget-content hhk-widget-content ui-corner-all mr-2"));
        }else{
            $mkup = '';
        }
        return $mkup;
    }

    public function getDiagInfo(){
        try{
        $query = "select d.`Code` as idDiagnosis, ifnull(d.`Description`, '') as `HHK Diagnosis`, i.`Diagnosis` from `" . Upload::TBL_NAME . "` i left join `gen_lookups` d on i.`Diagnosis` = d.`Description` and d.`Table_Name` = 'Diagnosis' where i.Diagnosis != '' group by i.`Diagnosis` order by d.Description, i.Diagnosis;";
        $stmt = $this->dbh->query($query);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }catch(\Exception $e){
            return false;
        }

    }

    private function getDiagMkup(){
        $diagInfo = $this->getDiagInfo();
        if(is_array($diagInfo)){
            $mkup = HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("h3", "Diagnosis" . HTMLContainer::generateMarkup("button", "Create Missing Diagnoses", array("id"=>"makeDiags", "class"=>"ui-button ui-corner-all ml-2"))) .
                CreateMarkupFromDB::generateHTML_Table($this->getDiagInfo(), "diag")
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