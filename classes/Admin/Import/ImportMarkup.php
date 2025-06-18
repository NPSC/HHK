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

        if($includeImportTbl){
            $query = "select n.idName as `ExternalId`, i.* from `" . Upload::TBL_NAME . "` i left join `name` n on i.importId = n.External_Id group by i.importId order by i.importId";
            $stmt = $this->dbh->query($query);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $importTbl = CreateMarkupFromDB::generateHTML_Table($rows, Upload::TBL_NAME);
        }else{
            $importTbl = "";
        }
        return $this->generateSummaryMkup() . $importTbl;
    }

    public function generateSummaryMkup(){
        return HTMLContainer::generateMarkup("h2", "Summary") . HTMLContainer::generateMarkup("div",
             $this->getStayMkup() . $this->getPeopleMkup() . $this->getHospitalMkup() . $this->getDoctorMkup(). $this->getRoomMkup() . $this->getDiagMkup() . $this->getEthnicityMkup() . $this->getGenderMkup() . $this->getPatientRelationMkup()
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
        $hospitalInfo = $this->getHospitalInfo("");

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

    private function getPeopleInfo(){
        try {
            //$query = "select 'Guests' as `Member Type`, count(distinct(concat(GuestLast,GuestFirst))) as `numRecords` from " . Upload::TBL_NAME . " i UNION select 'Patients' as `Member Type`, count(distinct(PatientId)) as `numRecords` from " . Upload::TBL_NAME . " i;";
            $query = "select 'Patients' as `Member Type`, count(*) as `numRecords` from " . Upload::TBL_NAME . " i;";
            $stmt = $this->dbh->query($query);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }catch(\PDOException $e){
            return [];
        }
    }

    private function getPeopleMkup(){
        $mkup = HTMLContainer::generateMarkup("div",
            HTMLContainer::generateMarkup("h3", "People") .
            CreateMarkupFromDB::generateHTML_Table($this->getPeopleInfo(), "people")
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
        $roomInfo = $this->getRoomInfo("");
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

    public function getDiagInfo(string $fieldName = ""){
        try{
            $query = "select d.`Code` as idDiagnosis, ifnull(d.`Description`, '') as `HHK Diagnosis`, i.`$fieldName` from `" . Upload::TBL_NAME . "` i left join `gen_lookups` d on i.`$fieldName` = d.`Description` and d.`Table_Name` = 'Diagnosis' where i.`$fieldName` != '' group by i.`$fieldName` order by d.Description, i.`$fieldName`;";
            $stmt = $this->dbh->query($query);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }catch(\Exception $e){
            return false;
        }

    }

    private function getDiagMkup(){
        $diagInfo = $this->getGenLookupInfo("Diagnosis", "");
        if(is_array($diagInfo)){
            $mkup = HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("h3", "Diagnosis" . HTMLContainer::generateMarkup("button", "Create Missing Diagnoses", array("data-entity"=>"Diags", "class"=>"makeMissing ui-button ui-corner-all ml-2"))) .
                CreateMarkupFromDB::generateHTML_Table($diagInfo, "diag")
                , array("class"=>"ui-widget ui-widget-content hhk-widget-content ui-corner-all mr-2"));
        }else{
            $mkup = "";
        }
        return $mkup;
    }

    public function getEthnicityInfo(){
        try{
            $query = "select d.`Code` as idEthnicity, ifnull(d.`Description`, '') as `HHK Ethnicity`, i.`Ethnicity` from `" . Upload::TBL_NAME . "` i left join `gen_lookups` d on i.`Ethnicity` = d.`Description` and d.`Table_Name` = 'Ethnicity' where i.Ethnicity != '' group by i.`Ethnicity` order by d.Description, i.Ethnicity;";
            $stmt = $this->dbh->query($query);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }catch(\Exception $e){
            return false;
        }

    }

    private function getEthnicityMkup(){
        $ethnicityInfo = $this->getGenLookupInfo("Ethnicity", "");
        if(is_array($ethnicityInfo)){
            $mkup = HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("h3", "Ethnicities" . HTMLContainer::generateMarkup("button", "Create Missing Ethnicities", array("data-entity"=>"Ethnicities", "class"=>"makeMissing ui-button ui-corner-all ml-2"))) .
                CreateMarkupFromDB::generateHTML_Table($ethnicityInfo, "ethnicity")
                , array("class"=>"ui-widget ui-widget-content hhk-widget-content ui-corner-all mr-2"));
        }else{
            $mkup = "";
        }
        return $mkup;
    }

    public function getGenderInfo(){
        try{
            $query = "select d.`Code` as idGender, ifnull(d.`Description`, '') as `HHK Gender`, i.`PatientGender` from `" . Upload::TBL_NAME . "` i left join `gen_lookups` d on i.`PatientGender` = d.`Description` and d.`Table_Name` = 'Gender' where i.PatientGender != '' group by i.`PatientGender` order by d.Description, i.PatientGender;";
            $stmt = $this->dbh->query($query);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }catch(\Exception $e){
            return false;
        }

    }

    private function getGenderMkup(){
        $genderInfo = $this->getGenLookupInfo("Gender", "");
        if(is_array($genderInfo)){
            $mkup = HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("h3", "Genders" . HTMLContainer::generateMarkup("button", "Create Missing Genders", array("data-entity"=>"Genders", "class"=>"makeMissing ui-button ui-corner-all ml-2"))) .
                CreateMarkupFromDB::generateHTML_Table($genderInfo, "genders")
                , array("class"=>"ui-widget ui-widget-content hhk-widget-content ui-corner-all mr-2"));
        }else{
            $mkup = "";
        }
        return $mkup;
    }

    public function getPatientRelationInfo(){
        try{
            $query = "select d.`Code` as idRelation, ifnull(d.`Description`, '') as `HHK Relation`, i.`PatientRelation` from `" . Upload::TBL_NAME . "` i left join `gen_lookups` d on i.`PatientRelation` = d.`Description` and d.`Table_Name` = 'Patient_Rel_Type' where i.PatientRelation != '' group by i.`PatientRelation` order by d.Description, i.PatientRelation;";
            $stmt = $this->dbh->query($query);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }catch(\Exception $e){
            return false;
        }

    }

    private function getPatientRelationMkup(){
        $diagInfo = $this->getGenLookupInfo("Patient_Rel_Type", "relationship");
        if(is_array($diagInfo)){
            $mkup = HTMLContainer::generateMarkup("div",
                HTMLContainer::generateMarkup("h3", "Patient Relationships" . HTMLContainer::generateMarkup("button", "Create Missing Relations", array("data-entity"=>"relationship", "class"=>"makeMissing ui-button ui-corner-all ml-2"))) .
                CreateMarkupFromDB::generateHTML_Table($diagInfo, "relations")
                , array("class"=>"ui-widget ui-widget-content hhk-widget-content ui-corner-all mr-2"));
        }else{
            $mkup = "";
        }
        return $mkup;
    }
    public function getDoctorInfo(){
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
        $doctorInfo = $this->getDoctorInfo();
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