<?php
namespace HHK\House\GLCodes;

use HHK\Common;
use HHK\Crypto;
use HHK\HTMLControls\HTMLTable;
use HHK\HTMLControls\HTMLInput;
use HHK\AuditLog\NameLog;
use HHK\Exception\RuntimeException;
use HHK\SysConst\VolMemberType;
use HHK\HTMLControls\HTMLContainer;
use HHK\Tables\EditRS;
use HHK\Tables\GenLookupsRS;
use HHK\Tables\Name\NameDemogRS;
use HHK\sec\Session;
use HHK\sec\SysConfig;

class GLParameters {

    protected $host;
    protected $username;
    protected $password;
    protected $remoteFilePath;
    protected $Port;
    protected $startDay;
    protected $journalCat;
    protected $ledgerName;
    protected $countyPayment;
    protected $countyLiability;
    protected $foundation;
    protected $grossSales;

    protected string $sftpTitle = '';
    protected $glParms;
    protected $tableName;

    /**
     * The parameters kept in gen_lookups (Table_Name = $tableName), in display order.
     * Code => [Label, Default]
     */
    const PARAMETERS = [
        'JournalCategory' => ['Journal Category', ''],
        'LedgerName' => ['Ledger Name', ''],
        'Foundation' => ['Foundation (Waive) GL Code', ''],
        'GrossSales' => ['Gross Sales GL Code', ''],
        'CountyLiability' => ['County Liability GL Code', ''],
        'CountyPayment' => ['County Payment', '50'],
        'SftpTitle' => ['SFTP Connection Title', ''],
        'Host' => ['SFTP Host', ''],
        'Port' => ['SFTP Port', '22'],
        'Username' => ['SFTP Username', ''],
        'Password' => ['SFTP Password', ''],
        'RemoteFilePath' => ['SFTP Remote File Path', ''],
    ];

    /**
     * Parameters shown in the SFTP settings dialog.
     */
    const SFTP_PARAMETERS = ['SftpTitle', 'Host','Port', 'Username', 'Password', 'RemoteFilePath'];

    public function __construct(\PDO $dbh, $tableName = 'Gl_Code') {

        $this->tableName = filter_var($tableName, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $this->loadParameters($dbh);

    }

    public function loadParameters(\PDO $dbh) {

        $this->glParms = Common::readGenLookupsPDO($dbh, $this->tableName, 'Order');

        $this->sftpTitle = $this->getParm('SftpTitle');
        $this->setHost($this->getParm('Host'));
        $this->setJournalCat($this->getParm('JournalCategory'));
        $this->setLedgerName($this->getParm('LedgerName'));
        // Fiscal month start day is a site setting (sys_config fm_Start_Day), like the fiscal year (fy_diff_Months).
        $this->setStartDay(SysConfig::getKeyValue($dbh, 'sys_config', 'fm_Start_Day', 1));
        $this->setRemoteFilePath($this->getParm('RemoteFilePath'));
        $this->setPort($this->getParm('Port'));
        $this->setUsername($this->getParm('Username'));
        $this->setPassword($this->getParm('Password'));
        $this->setCountyPayment($this->getParm('CountyPayment'));
        $this->setCountyLiability($this->getParm('CountyLiability'));
        $this->setFoundation($this->getParm('Foundation'));
        $this->setGrossSales($this->getParm('GrossSales'));
    }

    /**
     * Stored value of a parameter, or its default when the gen_lookups row is missing.
     */
    protected function getParm(string $code): string {

        if (isset($this->glParms[$code])) {
            return (string)$this->glParms[$code][1];
        }

        return self::PARAMETERS[$code][1] ?? '';
    }

    public function saveParameters(\PDO $dbh, $post, $prefix = 'gl_') {

        $uS = Session::getInstance();
        $order = 0;

        foreach (self::PARAMETERS as $code => $p) {

            $order++;

            if (isset($post[$prefix . $code]) === FALSE) {
                continue;
            }

            $keys = ['Table_Name' => $this->tableName, 'Code' => $code];

            if ($code == 'Password') {
                // Process password: blank keeps the saved one. Not sanitized - it's encrypted, never displayed, and
                // has to reach the SFTP server exactly as typed.
                $password = (is_string($post[$prefix . $code]) ? $post[$prefix . $code] : '');

                if ($password != '') {
                    $desc = Crypto::encryptMessage($password);

                    // keep the (encrypted) password out of the activity log - just note that it changed
                    $auditWas = EditRS::$auditEnabled;
                    EditRS::$auditEnabled = false;
                    EditRS::upsert($dbh, new GenLookupsRS(), $keys, ['Description' => $desc, 'Order' => $order]);
                    EditRS::$auditEnabled = $auditWas;

                    EditRS::logActivity($dbh, $this->tableName . ' password changed', [], 'default', 'gen_lookups');
                }

            } else {
                $desc = filter_var($post[$prefix . $code], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                if ($code == 'RemoteFilePath') {
                    $desc = self::withTrailingSlash((string)$desc);
                }

                EditRS::upsert($dbh, new GenLookupsRS(), $keys, ['Description' => $desc, 'Order' => $order]);
            }
        }

        if (isset($post['bagl']) && is_array($post['bagl'])) {

            foreach ($post['bagl'] as $idName => $baAr) {

                $idName = intval($idName);

                $nameDemogRS = new NameDemogRS();
                $nameDemogRS->idName->setStoredVal($idName);
                $rows = EditRS::select($dbh, $nameDemogRS, array($nameDemogRS->idName));

                if(count($rows) == 1){
                    EditRS::loadRow($rows[0], $nameDemogRS);

                    if (isset($baAr['debit'])) {
                        $gl = filter_var($baAr['debit'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                        $nameDemogRS->Gl_Code_Debit->setNewVal($gl);
                    }

                    if (isset($baAr['credit'])) {
                        $gl = filter_var($baAr['credit'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

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
        }

        $this->loadParameters($dbh);
    }

    /**
     * Parameter, item, pay type and billing agent GL code tables.
     *
     * @param \PDO $dbh
     * @param string $prefix  parameter input name prefix
     * @param bool $saveButton  include a 'btnSaveGlParms' submit button
     * @param string $sftpDialogForm  when set, the SFTP parameters go in a hidden #glSftpDialog div, opened by #btnGlSftp, for the page
     *  to make a dialog. Its inputs belong to this form id, so the dialog can live outside the form (eg. appended to body).
     * @return string
     */
    public function getChooserMarkup(\PDO $dbh, $prefix, bool $saveButton = TRUE, string $sftpDialogForm = '') {

        $sftpDialog = ($sftpDialogForm != '');

        // GL Parms chooser markup
        $glTbl = new HTMLTable();
        $glTbl->addHeaderTr(HTMLTable::makeTh('Parameter') . HTMLTable::makeTh('Value'));
        $sftpTbl = new HTMLTable();

        foreach (self::PARAMETERS as $code => $p) {

            $attrs = array('name'=>$prefix.$code, 'size'=>'23');

            if ($sftpDialog && in_array($code, self::SFTP_PARAMETERS)) {
                $attrs['form'] = $sftpDialogForm;
            }

            if ($code == 'Password') {
                // Never sent back to the browser; blank on save keeps the saved password.
                $attrs['type'] = 'password';
                $attrs['autocomplete'] = 'new-password';
                $attrs['placeholder'] = ($this->getParm($code) == '' ? '' : 'Saved - leave blank to keep');
                $input = HTMLInput::generateMarkup('', $attrs);
            } else {
                $input = HTMLInput::generateMarkup($this->getParm($code), $attrs);
            }

            $tr = HTMLTable::makeTh($p[0], array('class'=>'tdlabel')) . HTMLTable::makeTd($input);

            if ($sftpDialog && in_array($code, self::SFTP_PARAMETERS)) {
                $sftpTbl->addBodyTr($tr);
            } else {
                $glTbl->addBodyTr($tr);
            }
        }

        $sftpMkup = '';
        $sftpBtnMkup = '';

        if ($sftpDialog) {
            $sftpBtnMkup = HTMLContainer::generateMarkup('div',
                HTMLInput::generateMarkup('SFTP Settings', array('type'=>'button', 'id'=>'btnGlSftp', 'class'=>'ui-button ui-corner-all ui-widget'))
                . HTMLContainer::generateMarkup('span', ($this->isSftpConfigured() ? ($this->sftpTitle != '' ? $this->sftpTitle . ' (' . $this->getHost() . ')' : $this->getHost()) : 'Not set up'), array('class'=>'ml-2'))
                , array('class'=>'mt-2'));

            $sftpMkup = HTMLContainer::generateMarkup('div', $sftpTbl->generateMarkup(), array('id'=>'glSftpDialog', 'class'=>'hhk-tdbox', 'style'=>'display:none;'));
        }

        // Items
        $sitems = $dbh->prepare("SELECT  `i`.`idItem`, `itm`.`Type_Id`, `i`.`Description`, `i`.`Gl_Code`, `i`.`Percentage`, `i`.`Last_Order_Id`
    FROM `item` `i` LEFT JOIN `item_type_map` `itm` ON `itm`.`Item_Id` = `i`.`idItem`");
        $sitems->execute();
        $items = $sitems->fetchAll(\PDO::FETCH_ASSOC);

        $itemTbl = new HTMLTable();
        $itemTbl->addHeaderTr(HTMLTable::makeTh('Item') . HTMLTable::makeTh('GL Code'));

        foreach ($items as $d) {

            $des = $d['Description'];

            $itemTbl->addBodyTr(
                HTMLTable::makeTh($des, array('class'=>'tdlabel'))
                . HTMLTable::makeTd($d['Gl_Code'])
                );
        }

        // Pay Types
        $payMethods = array();
        $stmtp = $dbh->prepare("SELECT `idPayment_method`, `Gl_Code` FROM `payment_method`");
        $stmtp->execute();
        while ($t = $stmtp->fetch(\PDO::FETCH_NUM)) {
            $payMethods[$t[0]] = $t[1];
        }
        $payMethods[''] = '';


        $payTypes = Common::readGenLookupsPDO($dbh, 'Pay_Type');
        $payTbl = new HTMLTable();
        $payTbl->addHeaderTr(HTMLTable::makeTh('Pay Type') . HTMLTable::makeTh('GL Code'));

        foreach ($payTypes as $r) {

            $payTbl->addBodyTr(
                HTMLTable::makeTh($r[1], array('class'=>'tdlabel'))
                . HTMLTable::makeTd($payMethods[$r[2]] ?? '')
                );
        }

        // One titled box per section, like the Cloudbeds import tables.
        $mkup = HTMLContainer::generateMarkup('div',
                $this->makeSection('GL Parameters', 'Journal settings used when building the GL file.',$glTbl->generateMarkup() . $sftpBtnMkup)
                . $this->makeSection('Item GL Codes', 'Set in Resource Builder.', $itemTbl->generateMarkup())
                . $this->makeSection('Pay Type GL Codes', 'Set in Resource Builder.', $payTbl->generateMarkup())
                . $this->makeSection('Billing Agent GL Codes', 'Third-party payors: the GL debit replaces the pay type GL code on their payments.', $this->getBaMarkup($dbh))
                , array('class'=>'hhk-flex hhk-flex-wrap', 'style'=>'align-items:flex-start;'));

        // Add save button
        if ($saveButton) {
            $mkup .= HTMLContainer::generateMarkup('div', HTMLInput::generateMarkup('Save', array('name'=>'btnSaveGlParms', 'type'=>'submit')), array('style'=>'text-align:right;'));
        }

        return $mkup . $sftpMkup;

    }

    protected function makeSection(string $title, string $help, string $body): string {

        return HTMLContainer::generateMarkup('div',
            HTMLContainer::generateMarkup('h4', $title)
            . HTMLContainer::generateMarkup('p', $help, array('class'=>'mb-1'))
            . $body,
            array('class'=>'ui-widget ui-widget-content ui-corner-all p-2 mb-3 mr-2'));
    }

    protected function getBaMarkup(\PDO $dbh, $prefix = 'bagl') {

        $stmt = $dbh->prepare("SELECT `n`.`idName`, `n`.`Name_First`, `n`.`Name_Last`, `n`.`Company`, `nd`.`Gl_Code_Debit`, `nd`.`Gl_Code_Credit`, `nd`.`tax_exempt` " .
            " FROM `name` `n` JOIN `name_volunteer2` `nv` ON `n`.`idName` = `nv`.`idName` AND `nv`.`Vol_Category` = 'Vol_Type'  AND `nv`.`Vol_Code` = :volCode " .
            " JOIN `name_demog` `nd` ON `n`.`idName` = `nd`.`idName`  ".
            " WHERE `n`.`Member_Status` = 'a' AND `n`.`Record_Member` = 1 ORDER BY `n`.`Company`");
        $stmt->execute([':volCode' => VolMemberType::BillingAgent]);

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
                . HTMLTable::makeTd(HTMLInput::generateMarkup($r['Gl_Code_Debit'], array('name'=>$prefix.'['.$r['idName'] . '][debit]', 'size'=>'24')))
                . HTMLTable::makeTd(HTMLInput::generateMarkup($r['Gl_Code_Credit'], array('name'=>$prefix.'['.$r['idName'] . '][credit]', 'size'=>'24')))
                );
        }

        $glTbl->addHeaderTr(HTMLTable::makeTh('Billing Agent') . HTMLTable::makeTh('Tax Exempt') . HTMLTable::makeTh('GL Debit') . HTMLTable::makeTh('GL Credit'));

        return $glTbl->generateMarkup();

    }

    public function getParmsArray() {
        return $this->glParms;
    }

    /**
     * SFTP transfer needs at least a host and username.
     */
    /**
     * Name for the SFTP connection, eg. the accounting system it delivers to. Stored HTML-escaped.
     */
    public function getSftpTitle(): string {
        return $this->sftpTitle;
    }

    public function isSftpConfigured(): bool {
        return trim((string)$this->host) != '' && trim((string)$this->username) != '';
    }

    /**
     * County payment splitting only runs when a county liability GL code is set.
     */
    public function isCountyEnabled(): bool {
        return trim((string)$this->countyLiability) != '';
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
        return Crypto::decryptMessage($this->password);
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

    public function getLedgerName() {
        return $this->ledgerName;
    }

    public function setLedgerName($v) {
        $this->ledgerName = $v;
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

    public function getGrossSales() {
        return $this->grossSales;
    }

    public function setGrossSales($v) {
        $this->grossSales = $v;
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
        $this->remoteFilePath = self::withTrailingSlash((string)$remoteFilePath);
    }

    /**
     * The file name is appended to the remote path, so a non-empty path must end with '/'.
     */
    public static function withTrailingSlash(string $path): string {

        $path = trim($path);

        if ($path != '' && !str_ends_with($path, '/')) {
            $path .= '/';
        }

        return $path;
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
