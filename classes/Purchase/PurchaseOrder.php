<?php
/**
 * PurchaseOrder.php
 *
 * @category  Purchase
 * @package   Hospitality HouseKeeper
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2014 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/ecrane57/Hospitality-HouseKeeper
 */

/**
 * Description of PurchaseOrder
 *
 * @author Eric
 */
abstract class PurchaseOrder {

    protected $purchaseOrderRs;
    protected $status;
    protected $deleted;
    protected $activeSince;
    protected $activeUntil;
    protected $billingTypeId;
    protected $periodId;
    protected $notes;
    protected $createdBy;


    private $idPo;

    function __construct(\PurchaseOrderRS $purchaseOrderRS) {

        $this->purchaseOrderRs = $purchaseOrderRS;
        $this->idPo = $this->purchaseOrderRs->idPurchase_Order->getStoredVal();

        // set local vars
        $this->setLocalVars();
    }

    protected function setLocalVars() {

        $this->status = $this->purchaseOrderRs->Status_Id->getStoredVal();
        $this->deleted = $this->purchaseOrderRs->Deleted->getStoredVal();
        $this->activeSince = $this->purchaseOrderRs->Active_Since->getStoredVal();
        $this->activeUntil = $this->purchaseOrderRs->Active_Until->getStoredVal();
        $this->billingTypeId = $this->purchaseOrderRs->Billing_Type_Id->getStoredVal();
        $this->periodId = $this->purchaseOrderRs->Period_Id->getStoredVal();
        $this->notes = $this->purchaseOrderRs->Notes->getStoredVal();
        $this->createdBy = $this->purchaseOrderRs->Created_By->getStoredVal();
    }

    /**
     *
     * @param \PDO $dbh
     * @param type $idPurchaseOrder
     * @return \PurchaseOrderRS
     */
    public static function loadRecordSet(\PDO $dbh, $idPurchaseOrder) {

        $idPo = intval($idPurchaseOrder, 10);

        $poRs = new PurchaseOrderRS();

        if ($idPo > 0) {

            $poRs->idPurchase_Order->setNewVal($idPo);
            $rows = EditRS::select($dbh, $poRs, array($poRs->idPurchase_Order));

            if (count($rows) === 1) {
                $poRs = new PurchaseOrderRS();
                EditRS::loadRow($rows[0], $poRs);
            }
        }

        return $poRs;
    }


    public function invoice();

    public static function loadOrderLines(\PDO $dbh, $idPo);

    public function save(\PDO $dbh, $object = NULL, $preSaveCallback = '') {

        $poRs = $this->purchaseOrderRs;

        $poRs->Status_Id->setNewVal($this->status);
        $poRs->Deleted->setNewVal($this->deleted);
        $poRs->Active_Since->setNewVal($this->activeSince);
        $poRs->Active_Until->setNewVal($this->activeUntil);
        $poRs->Billing_Type_Id->setNewVal($this->billingTypeId);
        $poRs->Period_Id->setNewVal($this->periodId);
        $poRs->Notes->setNewVal($this->notes);

        if (is_null($object) === FALSE && $preSaveCallback != '') {

            $object->$preSaveCallback($poRs);
        }

        if ($poRs->idPurchase_Order == 0) {
            // insert new record
            $id = EditRS::insert($dbh, $poRs);

            if ($id > 0) {
                // Insert successful
                $poRs->idPurchase_Order->setNewVal($id);
                EditRS::updateStoredVals($poRs);
                $this->idPo = $this->purchaseOrderRs->idPurchase_Order->getStoredVal();

            } else {
                throw new Hk_Exception_Payment('Purchase Order insert error.  ');
            }

        } else {
            // update
            $cnt = EditRS::update($dbh, $poRs, $poRs->idPurchase_Order);

            if ($cnt > 0) {
                EditRS::updateStoredVals($poRs);
            }
        }

        $this->setLocalVars();
    }


    public function getStatus() {
        return $this->status;
    }

    public function isActive() {

        if ($this->getStatus() == OrderStatusCode::Active) {
            return TRUE;
        }

        return FALSE;
    }

    public function getIdPo() {
        return $this->idPo;
    }

    public function setStatus($orderStatus) {
        $this->status = $orderStatus;
        return $this;
    }

    public function isDeleted() {

        if ($this->deleted == 0) {
            return FALSE;
        }
        return TRUE;
    }

    public function setDeleted($boolDeleted) {

        if ($boolDeleted) {
            $this->deleted = 1;
        } else {
            $this->deleted = 0;
        }
        return $this;
    }

    public function getActiveSince() {
        return $this->activeSince;
    }

    public function getActiveUntil() {
        return $this->activeUntil;
    }

    public function getBillingTypeId() {
        return $this->billingTypeId;
    }

    public function getPeriodId() {
        return $this->periodId;
    }

    public function getNotes() {
        return $this->notes;
    }

    public function setActiveSince($activeSince) {
        $this->activeSince = $activeSince;
        return $this;
    }

    public function setActiveUntil($activeUntil) {
        $this->activeUntil = $activeUntil;
        return $this;
    }

    public function setBillingTypeId($billingTypeId) {
        $this->billingTypeId = $billingTypeId;
        return $this;
    }

    public function setPeriodId($periodId) {
        $this->periodId = $periodId;
        return $this;
    }

    public function setNotes($notes) {
        $this->notes = $notes;
        return $this;
    }


}

