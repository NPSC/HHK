<?php
/**
 * Registration.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class Registration {

    protected $regRS;
    public $isNew;
    protected $depositBalance;
    protected $lodgingMOA;

    public function __construct(PDO $dbh, $idPsg, $idRegistration = 0) {

        $this->regRS = new RegistrationRs();
        $rows = array();
        $this->isNew = TRUE;
        $this->depositBalance = NULL;
        $this->lodgingMOA = NULL;

        if ($idPsg > 0) {
            $this->regRS->idPsg->setStoredVal($idPsg);
            $rows = EditRS::select($dbh, $this->regRS, array($this->regRS->idPsg));

        } else if ($idRegistration > 0) {
            $this->regRS->idRegistration->setStoredVal($idRegistration);
            $rows = EditRS::select($dbh, $this->regRS, array($this->regRS->idRegistration));
        }

        if (count($rows) > 0) {

            EditRS::loadRow($rows[0], $this->regRS);

            $this->isNew = FALSE;
        }
    }

    public static function loadDepositBalance(PDO $dbh, $idGroup) {

        $depositBalance = 0.0;
        $idg = intval($idGroup, 10);

        if ($idg < 1) {
            return $depositBalance;
        }

        $query = "select
    sum(il.Amount)
from
    invoice_line il
        join
    invoice i ON il.Invoice_Id = i.idInvoice
where
    il.Item_Id in (" . ItemId::DepositRefund . "  , " . ItemId::KeyDeposit . ")
        and i.Deleted = 0
        and il.Deleted = 0
        and i.Status = '" . InvoiceStatus::Paid . "'
        and i.idGroup = " . $idg;
        $stmt = $dbh->query($query);

        $rows = $stmt->fetchAll(PDO::FETCH_NUM);

        if (count($rows) == 1) {
            $depositBalance = floatval($rows[0][0]);
        }

        return $depositBalance;
    }

    public static function loadLodgingBalance(PDO $dbh, $idGroup) {

        $lodgingBalance = 0.0;
        $idg = intval($idGroup, 10);

        if ($idg < 1) {
            return $lodgingBalance;
        }

        $query = "select
    sum(il.Amount)
from
    invoice_line il
        join
    invoice i ON il.Invoice_Id = i.idInvoice
where
    il.Item_Id = ". ItemId::LodgingMOA . "
        and i.Deleted = 0
        and il.Deleted = 0
        and i.Status = '" . InvoiceStatus::Paid . "'
        and i.idGroup = " . $idg;
        $stmt = $dbh->query($query);

        $rows = $stmt->fetchAll(PDO::FETCH_NUM);

        if (count($rows) == 1) {
            $lodgingBalance = floatval($rows[0][0]);
        }

        return $lodgingBalance;
    }

    public static function updatePrefTokenId(\PDO $dbh, $idRegistration, $idToken) {

        $tokenId = intval($idToken);
        $regId = intval($idRegistration);

        if ($tokenId < 1 || $regId < 1) {
            return;
        }

        return $dbh->exec("update registration set Pref_Token_Id = $tokenId where idregistration = $regId and Pref_Token_Id != $tokenId");
    }

    /**
     *
     * @param \PDO $dbh
     * @return type
     */
    public function getDepositBalance(\PDO $dbh) {

        if (is_null($this->depositBalance)) {
            $this->depositBalance = $this->loadDepositBalance($dbh, $this->getIdRegistration());
        }

        return $this->depositBalance;

    }

    public function getLodgingMOA(\PDO $dbh) {

        if (is_null($this->lodgingMOA)) {
            $this->lodgingMOA = $this->loadLodgingBalance($dbh, $this->getIdRegistration());
        }

        return $this->lodgingMOA;

    }

    public function getIdRegistration() {
        return $this->regRS->idRegistration->getStoredVal();
    }

    public function getIdPsg() {
        return $this->regRS->idPsg->getStoredVal();
    }

    public function getEmailReceipt() {
        if ($this->regRS->Email_Receipt->getStoredVal() == 1) {
            return TRUE;
        }
        return FALSE;
    }

    public function getPreferredTokenId() {
        return $this->regRS->Pref_Token_Id->getStoredVal();
    }


    public function getNoVehicle() {
        return $this->regRS->Vehicle->getStoredVal();
    }

    public function setNoVehicle($b) {
        if ($b) {
            $this->regRS->Vehicle->setNewVal('1');
        } else {
            $this->regRS->Vehicle->setNewVal('0');
        }
    }

    public function isNew() {
        if ($this->regRS->idRegistration->getStoredVal() == 0) {
            return TRUE;
        } else {
            return FALSE;
        }
    }

    public function getRegRS() {
        return $this->regRS;
    }

    public function extractVehicleFlag($pData) {

        if (isset($pData["cbNoVehicle"])) {
            if (strtolower($pData["cbNoVehicle"]) == 'on' || $pData["cbNoVehicle"] == '1') {
                $this->regRS->Vehicle->setNewVal('1');
            } else {
                $this->regRS->Vehicle->setNewVal('0');
            }
        } else {
            $this->regRS->Vehicle->setNewVal('0');
        }

    }

    public function extractRegistration(\PDO $dbh, $pData) {

        $regRs = $this->regRS;
        if (isset($pData["cbSig"])) {
            $regRs->Sig_Card->setNewVal(1);
        } else {
            $regRs->Sig_Card->setNewVal(0);
        }
        if (isset($pData["cbPam"])) {
            $regRs->Pamphlet->setNewVal(1);
        } else {
            $regRs->Pamphlet->setNewVal(0);
        }
        if (isset($pData["cbRef"])) {
            $regRs->Referral->setNewVal(1);
        } else {
            $regRs->Referral->setNewVal(0);
        }
        if (isset($pData["cbGid"])) {
            $regRs->Guest_Ident->setNewVal('1');
        } else {
            $regRs->Guest_Ident->setNewVal('');
        }

        if (isset($pData["cbEml"])) {
            $regRs->Email_Receipt->setNewVal('1');
        } else {
            $regRs->Email_Receipt->setNewVal('');
        }

    }

    public function saveRegistrationRs(PDO $dbh, $idPsg, $uname) {

        $msg = "";

        $this->regRS->Last_Updated->setNewVal(date("Y-m-d H:i:s"));
        $this->regRS->Updated_By->setNewVal($uname);
        $this->regRS->Status->setNewVal(VisitStatus::Active);

        if (is_null($this->depositBalance) === FALSE) {
            $this->regRS->Key_Deposit_Bal->setNewVal($this->depositBalance);
        }

        if (is_null($this->lodgingMOA) === FALSE) {
            $this->regRS->Lodging_Balance->setNewVal($this->lodgingMOA);
        }


        if ($this->regRS->idRegistration->getStoredVal() === 0 && $idPsg != 0) {
           // Insert
            $this->regRS->idPsg->setNewVal($idPsg);
            $this->regRS->Date_Registered->setNewVal(date("Y-m-d H:i:s"));
            $idReg = EditRS::insert($dbh, $this->regRS);

            $logText = VisitLog::getInsertText($this->regRS);
            VisitLog::logRegistration($dbh, $idPsg, $idReg, $logText, "insert", $uname);

            $this->regRS->idRegistration->setNewVal($idReg);
            $msg .= 'New Registration.  ';

        } else if ($this->regRS->idRegistration->getStoredVal() != 0) {
            // Update
            $cnt = EditRS::update($dbh, $this->regRS, array($this->regRS->idRegistration));

            $logText = VisitLog::getUpdateText($this->regRS);
            VisitLog::logRegistration($dbh, $idPsg, $this->regRS->idRegistration->getStoredVal(), $logText, "update", $uname);

            if ($cnt > 0) {
                $msg .= 'Registration Updated.  ';
            }
        } else {
            throw new Hk_Exception_Runtime('Registration missing a PSG Id.');
        }

        EditRS::updateStoredVals($this->regRS);
        return $msg;
    }

    public function createRegMarkup(PDO $dbh, $adminKey) {

        $sigChkd = "";
        if ($this->regRS->Sig_Card->getStoredVal() == "1") {
            $sigChkd = "checked='checked'";
        }

        $pamChkd = "";
        if ($this->regRS->Pamphlet->getStoredVal() == "1") {
            $pamChkd = "checked='checked'";
        }

        $refChkd = "";
        if ($this->regRS->Referral->getStoredVal() == "1") {
            $refChkd = "checked='checked'";
        }

        $gidChkd = "";
        if ($this->regRS->Guest_Ident->getStoredVal() == "1") {
            $gidChkd = "checked='checked'";
        }

        $emChkd = "";
        if ($this->regRS->Email_Receipt->getStoredVal() == "1") {
            $emChkd = "checked='checked'";
        }

        // get session instance
        $uS = Session::getInstance();
        $labels = new Config_Lite(LABEL_FILE);
        $keyMkup = '';

        if ($uS->KeyDeposit) {
            $kdBal = $this->getDepositBalance($dbh);

            $keyMkup = "</tr><tr><th class='tdlabel'>" . $labels->getString('resourceBuilder', 'keyDepositLabel', 'Deposit') . "</td><td>$" . number_format($kdBal, 2) . "</td>";
        }

        // Lodging MOA
        $lmoaMkup = "</tr><tr><th class='tdlabel'>" . $labels->getString('statement', 'lodgingMOA', 'MOA') . "</td><td>$" . number_format($this->getLodgingMOA($dbh), 2) . "</td>";



        $rmkup = "<table><tr><th class='tdlabel'>Date</td><td>" . ($this->regRS->Date_Registered->getStoredVal() == '' ? '' : date('M j, Y', strtotime($this->regRS->Date_Registered->getStoredVal()))) . "</td>
</tr><tr><th class='tdlabel'><label for='cbSig'>Signature Card</label></td><td><input type='checkbox' id='cbSig' name='cbSig' class='hhk-regvalue' $sigChkd /></td>
</tr><tr><th class='tdlabel'><label for='cbPam'>Rules</label></td><td><input type='checkbox' id='cbPam' name='cbPam' class='hhk-regvalue' $pamChkd /></td>
</tr><tr><th class='tdlabel'><label for='cbRef'>Referral</label></td><td><input type='checkbox' id='cbRef' name='cbRef' class='hhk-regvalue' $refChkd /></td>
</tr><tr><th class='tdlabel'><label for='cbGid'>ID Card Photocopied</label></td><td><input type='checkbox' id='cbGid' name='cbGid' class='hhk-regvalue' $gidChkd /></td>
</tr><tr><th class='tdlabel'><label for='cbEml'>Email Receipt</label></td><td><input type='checkbox' id='cbEml' name='cbEml' class='hhk-regvalue' $emChkd /></td>
$lmoaMkup
$keyMkup</tr></table>";

        return $rmkup;

    }

}


