<?php
namespace HHK\House\GLCodes;

use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLInput;
use HHK\AuditLog\NameLog;
use HHK\Exception\RuntimeException;
use HHK\SysConst\VolMemberType;
use HHK\HTMLControls\HTMLContainer;
use HHK\Tables\EditRS;
use HHK\Tables\Name\NameDemogRS;
use HHK\sec\Session;

class GLParameters {
    
    protected $host;
    protected $username;
    protected $password;
    protected $remoteFilePath;
    protected $Port;
    protected $startDay;
    protected $journalCat;
    protected $countyPayment;
    protected $countyLiability;
    protected $foundation;
    
    protected $glParms;
    protected $tableName;
    
    /*  Add this to gen_lookups:
     INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Gl_Code', 'Host', '');
     INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Gl_Code', 'Username', '');
     INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Gl_Code', 'Password', '');
     INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Gl_Code', 'Port', '22');
     INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Gl_Code', 'JournalCategory', '');
     INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Gl_Code', 'RemoteFilePath', '');
     INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Gl_Code', 'StartDay', '01');
     INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Gl_Code', 'CountyPayment', '50');
     INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Gl_Code', 'CountyLiability', '');
     INSERT INTO `gen_lookups` (`Table_Name`, `Code`, `Description`) VALUES ('Gl_Code', 'Foundation', '');
     */
    
    public function __construct(\PDO $dbh, $tableName = 'Gl_Code') {
        
        $this->tableName = filter_var($tableName, FILTER_SANITIZE_STRING);
        $this->loadParameters($dbh);
        
    }
    
    public function loadParameters(\PDO $dbh) {
        
        $this->glParms = readGenLookupsPDO($dbh, $this->tableName, 'Order');
        
        $this->setHost($this->glParms['Host'][1]);
        $this->setJournalCat($this->glParms['JournalCategory'][1]);
        $this->setStartDay($this->glParms['StartDay'][1]);
        $this->setRemoteFilePath($this->glParms['RemoteFilePath'][1]);
        $this->setPort($this->glParms['Port'][1]);
        $this->setUsername($this->glParms['Username'][1]);
        $this->setPassword($this->glParms['Password'][1]);
        $this->setCountyPayment($this->glParms['CountyPayment'][1]);
        $this->setCountyLiability($this->glParms['CountyLiability'][1]);
        $this->setFoundation($this->glParms['Foundation'][1]);
        
    }
    
    public function saveParameters(\PDO $dbh, $post, $prefix = 'gl_') {
        
        $uS = Session::getInstance();
        
        foreach ($this->glParms as $g) {
            
            if (isset($post[$prefix . $g[0]])) {
                
                $desc = filter_var($post[$prefix . $g[0]], FILTER_SANITIZE_STRING);
                
                if (strtolower($g[0]) == 'password' && $desc != '' && $desc != $g[1]) {
                    $desc = encryptMessage($desc);
                } else {
                    $desc = addslashes($desc);
                }
                
                $dbh->exec("update `gen_lookups` set `Description` = '$desc' where `Table_Name` = '" .$this->tableName . "' and `Code` = '" . $g[0] . "'");
                
            }
        }
        
        foreach ($post['bagl'] as $idName => $baAr) {
                
            $idName = intval($idName);
            
            $nameDemogRS = new NameDemogRS();
            $nameDemogRS->idName->setStoredVal($idName);
            $rows = EditRS::select($dbh, $nameDemogRS, array($nameDemogRS->idName));
            
            if(count($rows) == 1){
                EditRS::loadRow($rows[0], $nameDemogRS);

                if (isset($baAr['debit'])) {
                    $gl = filter_var($baAr['debit'], FILTER_SANITIZE_STRING);
                    
                    $nameDemogRS->Gl_Code_Debit->setNewVal($gl);
                }
                    
                if (isset($baAr['credit'])) {
                    $gl = filter_var($baAr['credit'], FILTER_SANITIZE_STRING);
                    
                    $nameDemogRS->Gl_Code_Credit->setNewVal($gl);
                }
                    
                if (isset($baAr['taxExempt'])) {
                    $nameDemogRS->Tax_Exempt->setNewVal(1);
                }else{
                    $nameDemogRS->Tax_Exempt->setNewVal(0);
                }
            
                $affectedRows = EditRS::update($dbh, $nameDemogRS, [$nameDemogRS->idName]);
                
                if ($affectedRows > 0) {
                    NameLog::writeUpdate($dbh, $nameDemogRS, $nameDemogRS->idName->getStoredVal(), $uS->username);
                }
            }
            
        }
        
        $this->loadParameters($dbh);
    }
    
    public function getChooserMarkup(\PDO $dbh, $prefix) {
        
        // GL Parms chooser markup
        $glTbl = new HTMLTable();
        
        foreach ($this->getParmsArray() as $g) {
            
            $glTbl->addBodyTr(
                HTMLTable::makeTh($g[0], array('class'=>'tdlabel'))
                . HTMLTable::makeTd(HTMLInput::generateMarkup($g[1], array('name'=>$prefix.$g[0])))
                );
        }
        
        $glTbl->addHeaderTr(HTMLTable::makeTh('Parameter') . HTMLTable::makeTh('Value'));
        
        $tbl = new HTMLTable();
        $tbl->addBodyTr(
            HTMLTable::makeTd($glTbl->generateMarkup(), array('style'=>'vertical-align:top;'))
            .HTMLTable::makeTd($this->getBaMarkup($dbh), array('style'=>'vertical-align:top;'))
            );
        
        // Add save button
        $tbl->addBodyTr(HTMLTable::makeTd(HTMLInput::generateMarkup('Save', array('name'=>'btnSaveGlParms', 'type'=>'submit')), array('colspan'=>'2', 'style'=>'text-align:right;')));
        
        return $tbl->generateMarkup(array('style'=>'float:left;margin-right:1.5em;'));
        
    }
    
    protected function getBaMarkup(\PDO $dbh, $prefix = 'bagl') {
        
        $stmt = $dbh->query("SELECT n.idName, n.Name_First, n.Name_Last, n.Company, nd.Gl_Code_Debit, nd.Gl_Code_Credit, nd.tax_exempt " .
            " FROM name n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type'  and nv.Vol_Code = '" . VolMemberType::BillingAgent . "' " .
            " JOIN name_demog nd on n.idName = nd.idName  ".
            " where n.Member_Status='a' and n.Record_Member = 1 order by n.Company");
        
        // Billing agent markup
        $glTbl = new HTMLTable();
        
        while ($r = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $entry = '';
            
            if ($r['Name_First'] != '' || $r['Name_Last'] != '') {
                $entry = trim($r['Name_First'] . ' ' . $r['Name_Last']);
            }
            
            if ($entry != '' && $r['Company'] != '') {
                $entry .= '; ' . $r['Company'];
            }
            
            if ($entry == '' && $r['Company'] != '') {
                $entry = $r['Company'];
            }
            
            $taxExemptCbAttrs = array('name'=>$prefix. '['.$r['idName'] . '][taxExempt]', 'type'=>'checkbox');
            if($r['tax_exempt']){
                $taxExemptCbAttrs['checked'] = 'checked';
            }
            
            $glTbl->addBodyTr(
                HTMLTable::makeTh($entry, array('class'=>'tdlabel'))
                . HTMLTable::makeTd(HTMLContainer::generateMarkup('label', "", array('for'=>$prefix. '['.$r['idName'] . '][taxExempt]', 'class'=>'')) . HTMLInput::generateMarkup('', $taxExemptCbAttrs), array('style'=>'text-align: center;'))
                . HTMLTable::makeTd(HTMLInput::generateMarkup($r['Gl_Code_Debit'], array('name'=>$prefix.'['.$r['idName'] . '][debit]', 'size'=>'25')))
                . HTMLTable::makeTd(HTMLInput::generateMarkup($r['Gl_Code_Credit'], array('name'=>$prefix.'['.$r['idName'] . '][credit]', 'size'=>'25')))
                );
        }
        
        $glTbl->addHeaderTr(HTMLTable::makeTh('Billing Agent') . HTMLTable::makeTh('Tax Exempt') . HTMLTable::makeTh('GL Debit') . HTMLTable::makeTh('GL Credit'));
        
        return $glTbl->generateMarkup();
        
    }
    
    public function getParmsArray() {
        return $this->glParms;
    }
    
    /**
     * @return mixed
     */
    public function getHost() {
        return $this->host;
    }
    
    /**
     * @return mixed
     */
    public function getUsername() {
        return $this->username;
    }
    
    /**
     * @return mixed
     */
    public function getPassword() {
        return $this->password;
    }
    
    public function getClearPassword() {
        return decryptMessage($this->password);
    }
    
    /**
     * @return mixed
     */
    public function getRemoteFilePath() {
        return $this->remoteFilePath;
    }
    
    /**
     * @return mixed
     */
    public function getPort() {
        return $this->Port;
    }
    
    /**
     * @return mixed
     */
    public function getStartDay() {
        $iDay = intval($this->startDay, 10);
        $sDay = '';
        
        if ($iDay < 1 || $iDay > 28) {
            throw new RuntimeException('The Start-Day is not viable: ' . $iDay);
        }
        
        // Format with leading 0's
        if ($iDay < 10) {
            $sDay = '0' . $iDay;
        } else {
            $sDay = (string)$iDay;
        }
        return $sDay;
    }
    
    /**
     * @return mixed
     */
    public function getJournalCat() {
        return $this->journalCat;
    }
    
    public function getCountyPayment() {
        return $this->countyPayment;
    }
    
    public function setCountyPayment($v) {
        $this->countyPayment = $v;
    }
    
    public function getCountyLiability() {
    	return $this->countyLiability;
    }
    
    public function setCountyLiability($v) {
    	$this->countyLiability = $v;
    }
    
    public function getFoundation() {
    	return $this->foundation;
    }
    
    public function setFoundation($v) {
    	$this->foundation = $v;
    }
    
    /**
     * @param mixed $host
     */
    public function setHost($host) {
        $this->host = $host;
    }
    
    /**
     * @param mixed $username
     */
    public function setUsername($username) {
        $this->username = $username;
    }
    
    /**
     * @param mixed $password
     */
    public function setPassword($password) {
        $this->password = $password;
    }
    
    /**
     * @param mixed $remoteFilePath
     */
    public function setRemoteFilePath($remoteFilePath) {
        $this->remoteFilePath = $remoteFilePath;
    }
    
    /**
     * @param mixed $Port
     */
    public function setPort($Port) {
        $this->Port = $Port;
    }
    
    /**
     * @param mixed $startDay
     */
    public function setStartDay($startDay) {
        
        if ($startDay > 0 && $startDay < 29) {
            $this->startDay = $startDay;
        } else {
            $this->startDay = "Invalid";
        }
    }
    
    /**
     * @param mixed $journalCat
     */
    public function setJournalCat($journalCat) {
        $this->journalCat = $journalCat;
    }
}
?>