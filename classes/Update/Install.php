<?php
namespace HHK\Update;
use HHK\Common;
use HHK\Exception\RuntimeException;
use HHK\Purchase\PriceModel\AbstractPriceModel;
use HHK\sec\Crypto;
use HHK\sec\Login;
use HHK\sec\Session;
use HHK\sec\SysConfig;
use HHK\sec\UserClass;
use HHK\sec\WebInit;
use HHK\SysConst\CodeVersion;
use HHK\SysConst\WebSiteCode;
use PDOException;

/**
 * HHK Installer
 */
class Install{

    protected \PDO $dbh;

    protected array $sqlFiles = [
            "Tables" => [
                'path'=>REL_BASE_DIR.'sql'.DS.'CreateAllTables.sql',
                'delimiter'=>';',
                'split'=>';'
            ],
            "Views" => [
                'path'=>REL_BASE_DIR.'sql'.DS.'CreateAllViews.sql',
                'delimiter'=>';',
                'split'=>';'
            ],
            "Stored Procedures" => [
                'path'=>REL_BASE_DIR.'sql'.DS.'CreateAllRoutines.sql',
                'delimiter'=>'$$',
                'split'=>'-- ;'
            ]
        ];

    public function __construct(){
        $this->dbh = $this->initSessionPDO();
    }

    protected function initSessionPDO() {

        // get session instance
    	$ssn = Session::getInstance(CONF_PATH, ciCFG_FILE);

        // Get the site configuration object
        try {
            $config = parse_ini_file(CONF_PATH . ciCFG_FILE, true);
        } catch (\Exception $ex) {
            $ssn->destroy();
            throw new RuntimeException("Configurtion file is missing, path=".ciCFG_FILE, 999, $ex);
        }

        $ssn->sitePepper = (isset($config["site"]["sitePepper"]) ? $config["site"]["sitePepper"]:'');

        Login::dbParmsToSession(CONF_PATH, ciCFG_FILE);
    	return Common::initPDO(TRUE);
    }

    /**
     * Initialize Tables, Views & Stored Procedures
     * @param \PDO $dbh
     * @return array
     */
    public function installDB(){
        $nextStep = $this->getNextStep();
        if($nextStep != 'installDB'){
            return ['success'=>["installDB already completed."]];
        }

        try {

            $patch = new Patch();
            $results = array();

            //open transaction
            if($this->dbh->inTransaction() === FALSE){
                $this->dbh->beginTransaction();
            };
    
            foreach($this->sqlFiles as $type=>$file){
                // Update Tables
                if($patch->updateWithSqlStmts($this->dbh, $file['path'], $type, $file['delimiter'], $file['split'])){
                    $results['success'][] = $type . " Successful";
                }else{
                    foreach ($patch->results as $err) {
                        $results['errors'][] = 'Create Table Error: ' . $err['error'] . ', ' . $err['errno'] . '; Query=' . $err['query'] . '<br/>';
                    }
                    if($this->dbh->inTransaction()){
                        $this->dbh->rollBack();
                    }
                    return $results;
                }
            }

            // Set web_sites table
            $adminDir = str_ireplace('/', '', 'admin');
            $houseDir = str_ireplace('/', '', 'house');
            $volDir = str_ireplace('/', '', 'volunteer');
    
    
            // Admin
            $this->dbh->exec("update web_sites set Relative_Address = '$adminDir/' where Site_Code = '" . WebSiteCode::Admin . "'");
    
            // House
            if ($houseDir != '') {
                $this->dbh->exec("update web_sites set Relative_Address = '$houseDir/' where Site_Code = '" . WebSiteCode::House . "'");
            } else {
                $this->dbh->exec("update web_sites set Relative_Address = '' where Site_Code = '" . WebSiteCode::House . "'");
            }
    
            // Volunteer
            if ($volDir != '') {
                $this->dbh->exec("update web_sites set Relative_Address = '$volDir/' where Site_Code = '" . WebSiteCode::Volunteer . "'");
            } else {
                $this->dbh->exec("update web_sites set Relative_Address = '' where Site_Code = '" . WebSiteCode::Volunteer . "'");
            }
    
    
        } catch (\Exception $hex) {
            if($this->dbh->inTransaction()){
                $this->dbh->rollBack();
            }

            $results['errors'][] = $hex->getMessage();
        }

        if($this->dbh->inTransaction()){
            if(isset($results['errors'])){
                $this->dbh->rollBack();
            }else{
                $this->dbh->commit();
            }
        }

        return $results;
    }

    /**
     * Load InitialData
     * @return array
     */
    public function loadMetadata(string $newPw, $npscuserPw = null){
        $nextStep = $this->getNextStep();
        if($nextStep != 'loadMetadata'){
            return ['success'=>["loadMetadata already completed."]];
        }

        try {
            // Load initialization data
            $filedata = file_get_contents('initialdata.sql');
            $parts = explode('-- ;', $filedata);
    
            if($this->dbh->inTransaction() === false){
                $this->dbh->beginTransaction();
            }

            foreach ($parts as $q) {
    
                $q = trim($q);
    
                if ($q != '') {
                    try {
                        $this->dbh->exec($q);
                    } catch (PDOException $pex) {
                        if($this->dbh->inTransaction()){
                            $this->dbh->rollBack();
                        }
                        return ['errors'=>[$pex->getMessage() . '. ' . PHP_EOL . 'Query: '. $q]];
                    }
                }
            }
    
            $successMsg = "";
            // Update admin password
            if ($newPw != '') {
    
                $uclass = new UserClass();
                if ($uclass->setPassword($this->dbh, -1, $newPw)) {
                    $successMsg = 'Admin';
                    if($npscuserPw && $npscuserPw != '' && $uclass->setPassword($this->dbh, 10, $npscuserPw)){
                        $successMsg .= ' and npscuser';
                    }

                    if($this->dbh->inTransaction()){
                        $this->dbh->commit();
                    }
                    return ['success'=>[$successMsg .= " password set."]];
                } else {
                    if($this->dbh->inTransaction()){
                        $this->dbh->rollBack();
                    }
                    return ['errors'=> ["Installer Error: Could not set Admin Password"]];
                }
            }else{
                return ['errors'=>["Admin Password is required"]];
            }
    
        } catch (\Exception $ex) {
            if($this->dbh->inTransaction()){
                $this->dbh->rollBack();
            }
            return ['errors'=> ["Installer Error: " . $ex->getMessage()]];
        }
    }

    public function loadZipFile(array $zipFile){
        $nextStep = $this->getNextStep();
        if($nextStep != 'loadZipFile'){
            return ['success'=>["loadZipFile already completed."]];
        }

        $result = array();

        try {
            
            SiteConfig::checkUploadFile('zipfile');
            $result = ['success'=>[SiteConfig::loadZipCodeFile($this->dbh, $zipFile['tmp_name'])]];
        
            SiteLog::writeLog($this->dbh, 'Zip', 'Zip Code File Loaded. ' . $result['success'][0], CodeVersion::GIT_Id);
        
        } catch (\Exception $hex) {
            $result = ['errors'=>[$hex->getMessage()]];
            SiteLog::writeLog($this->dbh, 'Zip', 'Zip Code File Failed. ' . $hex->getMessage(), CodeVersion::GIT_Id);
        }
        return $result;
    }

    /**
     * Install Rooms
     * @param int $numRooms
     * @param string $rateCode
     * @param bool $finAssist
     * @return array
     */
    public function installRooms(int $numRooms, string $rateCode, bool $finAssist){
        $nextStep = $this->getNextStep();
        if($nextStep != 'installRooms'){
            return ['success'=>["installRooms already completed."]];
        }

        try{

            $this->loadSysConfig();
            $ssn = Session::getInstance();

            $rPrices = Common::readGenLookupsPDO($this->dbh, 'Price_Model');

            if($this->dbh->inTransaction() === false){
                $this->dbh->beginTransaction();
            }

            if ($numRooms > 0 && $numRooms < 201) {

                // Clear the database
                $this->dbh->exec("Delete from `room` where idRoom > 0;");
                $this->dbh->exec("Delete from `resource`;");
                $this->dbh->exec("Delete from `resource_room`;");
                $this->dbh->exec("Delete from `resource_use`;");
                $this->dbh->exec("Delete from `room_log`;");

                // Install new rooms
                for ($n = 1; $n <= $numRooms; $n++) {

                    $idRoom = $n + 9;
                    $title = $idRoom + 100;

                    // create room record
                    $this->dbh->exec("insert into room "
                            . "(`idRoom`,`idHouse`,`Item_Id`,`Title`,`Type`,`Category`,`Status`,`State`,`Availability`, "
                            . "`Max_Occupants`,`Min_Occupants`,`Rate_Code`,`Key_Deposit_Code`,`Cleaning_Cycle_Code`, `idLocation`) VALUES "
                            . "($idRoom, 0, 1, '$title', 'r', 'dh', 'a', 'a', 'a', 4, 0,'rb', 'k0', 'a', 1);");

                    // create resource record
                    $this->dbh->exec("insert into resource "
                            . "(`idResource`,`idSponsor`,`Title`,`Utilization_Category`,`Type`,`Util_Priority`,`Status`)"
                            . " Values "
                            . "($idRoom, 0, '$title', 'uc1', 'room', '$title', 'a')");

                    // Resource-Room
                    $this->dbh->exec("insert into resource_room "
                            . "(`idResource_room`,`idResource`,`idRoom`) values "
                            . "($idRoom, $idRoom, $idRoom)");
                }

            }

            if ($rateCode != '' && isset($rPrices[$rateCode])) {

                SysConfig::saveKeyValue($this->dbh, WebInit::SYS_CONFIG, 'RoomPriceModel', $rateCode);

                if ($finAssist) {
                    SysConfig::saveKeyValue($this->dbh, webInit::SYS_CONFIG, 'IncomeRated', 'true');
                } else {
                    SysConfig::saveKeyValue($this->dbh, webInit::SYS_CONFIG, 'IncomeRated', 'false');
                }

                SysConfig::getCategory($this->dbh, $ssn, "'h'", webInit::SYS_CONFIG);
                SysConfig::getCategory($this->dbh, $ssn, "'hf'", webInit::SYS_CONFIG);

                $this->dbh->exec("delete from `room_rate`");

                AbstractPriceModel::installRates($this->dbh, $rateCode, $ssn->IncomeRated);

            }

            $siteId = $ssn->sId;
            $houseName = $ssn->siteName;

            if ($siteId > 0) {

                $stmt = $this->dbh->query("Select count(`idName`) from `name` where `idName` = $siteId");
                $row = $stmt->fetchAll(\PDO::FETCH_NUM);


                if (isset($row[0]) && $row[0][0] == 0 && $houseName != '') {
                    $this->dbh->exec("insert into `name` (`idName`, `Company`, `Member_Type`, `Member_Status`, `Record_Company`, `Last_Updated`, `Updated_By`) values ($siteId, '$houseName', 'np', 'a', 1, now(), 'admin')");
                }

            } else {

                $numRcrds = $this->dbh->exec("insert into `name` (`Company`, `Member_Type`, `Member_Status`, `Record_Company`, `Last_Updated`, `Updated_By`) values ('$houseName', 'np', 'a', 1, now(), 'admin')");
                if ($numRcrds != 1) {
                    // problem
                    return ['errors'=>['Insert of house name record failed.']];
                }

                $siteId = $this->dbh->lastInsertId();
                $ssn->sId = $siteId;

                SysConfig::saveKeyValue($this->dbh, 'sys_config', 'sId', $siteId);

            }

            if ($ssn->subsidyId == 0 && $siteId > 0) {
                $ssn->subsidyId = $siteId;

                SysConfig::saveKeyValue($this->dbh, 'sys_config', 'subsidyId', $siteId);

            }
        }catch (\Exception $e) {
            if($this->dbh->inTransaction()){
                $this->dbh->rollBack();
            }
            return ["errors"=>["Install Rooms failed: " . $e->getMessage()]];
        }

        if($this->dbh->inTransaction()){
            $this->dbh->commit();
        }

        return ['success'=>[$numRooms." rooms configured."]];
    }

    /**
     * Test Database Connection
     * @param mixed $post
     * @return array
     */
    public function testDB($post) {

        $dbms = '';
        $dbURL = '';
        $dbUser = '';
        $pw = '';
        $dbName = '';
    
        if (isset($post['dbms'])) {
            $dbms = filter_var($post['dbms'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
        if (isset($post['dburl'])) {
            $dbURL = filter_var($post['dburl'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
        if (isset($post['dbuser'])) {
            $dbUser = filter_var($post['dbuser'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
        if (isset($post['dbPW'])) {
            $pw = Crypto::decryptMessage(filter_var($post['dbPW'], FILTER_UNSAFE_RAW));
        }
        if (isset($post['dbSchema'])) {
            $dbName = filter_var($post['dbSchema'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }
    
    
        try {
    
            $this->dbh->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $serverInfo = $this->dbh->getAttribute(\PDO::ATTR_SERVER_VERSION);
            $driver = $this->dbh->getAttribute(\PDO::ATTR_DRIVER_NAME);
    
        } catch (PDOException $e) {
            return array("error" => $e->getMessage() . "<br/>");
        }
    
        return array('success'=>'Good! Server version ' . $serverInfo . '; ' . $driver);
    }

    protected function loadSysConfig(){
        // get session instance
        $ssn = Session::getInstance();

        SysConfig::getCategory($this->dbh, $ssn, "'f'", WebInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $ssn, "'r'", webInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $ssn, "'d'", webInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $ssn, "'h'", webInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $ssn, "'a'", WebInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $ssn, "'hf'", webInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $ssn, "'ha'", webInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $ssn, "'p'", webInit::SYS_CONFIG);
        SysConfig::getCategory($this->dbh, $ssn, "'g'", webInit::SYS_CONFIG);
    }

    /**
     * Determine the next step of the install process
     * @return string
     */
    public function getNextStep(){

        //Does sys_config table exist?
        $stmt = $this->dbh->query("show tables like 'sys_config';");
        if($stmt->rowCount() === 0){
            return "installDB";
        }

        //Does sys_config have data in it?
        $stmt = $this->dbh->query("select * from sys_config");
        if($stmt->rowCount() === 0){
            return "loadMetadata";
        }

        //Have zip codes been loaded? 
        $stmt = $this->dbh->query("select * from postal_codes");
        if($stmt->rowCount() === 0){
            return "loadZipFile";
        }

        //have rooms been set up?
        $stmt = $this->dbh->query("select * from room");
        if($stmt->rowCount() === 0){
            return "installRooms";
        }

        return "done";
    }

}
?>