<?php
/**
 * InvoiceLine.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

namespace npsc;

/**
 * Description of InvoiceLine
 *
 * @author Eric
 */
abstract class InvoiceLine {

    protected $amount;
    protected $quantity;
    protected $price;
    protected $itemId;
    protected $description;
    protected $typeId;
    protected $invoiceId;
    protected $invLineRs;
    protected $var;
    protected $carriedFrom;
    protected $useDetail;

    public function __construct() {
        $this->useDetail = TRUE;
        $this->invLineRs = new InvoiceLineRS();
    }


    public function loadRecord(\InvoiceLineRS $invoiceLine) {

        $this->setItemId($invoiceLine->Item_Id->getStoredVal());
        $this->description = $invoiceLine->Description->getStoredVal();
        $this->setPrice($invoiceLine->Price->getStoredVal());
        $this->setAmount($invoiceLine->Amount->getStoredVal());
        $this->setQuantity($invoiceLine->Quantity->getStoredVal());

        $this->invLineRs = $invoiceLine;

        $this->carriedFrom = '';
        $this->useDetail = TRUE;
    }

    public static function invoiceLineFactory($typeId) {

        if ($typeId == InvoiceLineType::Recurring) {

            return new RecurringInvoiceLine();

        } else if ($typeId == InvoiceLineType::Invoice) {

            return new InvoiceInvoiceLine();

        } else if ($typeId == InvoiceLineType::OneTime) {

            return new OneTimeInvoiceLine();

        } else if ($typeId == InvoiceLineType::Hold) {
            return new HoldInvoiceLine();

        } else if ($typeId == InvoiceLineType::Reimburse) {

            return new ReimburseInvoiceLine();
        }

        return NULL;

    }

    public function createNewLine(Item $item, $quantity, $str1 = '', $str2 = '') {

        $this->var = $str1;
        $this->setItemId($item->getIdItem());
        $this->setPrice($item->getUnitPrice());
        $this->setQuantity($quantity);
        $this->setDescription($item->getDescription());

    }

    public function save(\PDO $dbh, $deleted = FALSE) {

        //
        $ilRs1 = new InvoiceLineRS();

        $ilRs1->Invoice_Id->setNewVal($this->getInvoiceId());
        $ilRs1->Item_Id->setNewVal($this->getItemId());
        $ilRs1->Type_Id->setNewVal($this->getTypeId());
        $ilRs1->Description->setNewVal($this->getDescription());
        $ilRs1->Quantity->setNewVal($this->getQuantity());
        $ilRs1->Price->setNewVal($this->getPrice());

        $ilRs1->Amount->setNewVal($this->getQuantity() * $this->getPrice());

        if ($deleted) {
            $ilRs1->Deleted->setNewVal(1);
        }

        $recId = EditRS::insert($dbh, $ilRs1);

        return $recId;

    }

    public function updateLine(\PDO $dbh) {

        $affected = 0;
        $this->invLineRs->Description->setNewVal($this->getDescription());

        if ($this->invLineRs->idInvoice_Line->getStoredVal() > 0) {
            $affected = EditRS::update($dbh, $this->invLineRs, array($this->invLineRs->idInvoice_Line));
        }

        return $affected;
    }

    public function getAmount() {
        return $this->amount;
    }

    public function getQuantity() {
        return $this->quantity;
    }

    public function getPrice() {
        return $this->price;
    }

    public function getItemId() {
        return $this->itemId;
    }

    public function getDescription() {
        return $this->description;
    }

    public function getTypeId() {
        return $this->typeId;
    }

    public function getInvoiceId() {
        return $this->invoiceId;
    }

    public function getCarriedFrom() {
        return $this->carriedFrom;
    }

    public function setCarriedFrom($invoiceNumber) {
        $this->carriedFrom = $invoiceNumber;
        return $this;
    }

    public function setInvoiceId($id) {
        $this->invoiceId = $id;
        return $this;
    }

    public function setAmount($amount) {
        $this->amount = $amount;
        return $this;
    }

    public function setQuantity($quantity) {
        $this->quantity = $quantity;
        return $this;
    }

    public function setPrice($price) {
        $this->price = $price;
        return $this;
    }

    public function setItemId($itemId) {
        $this->itemId = $itemId;
        return $this;
    }

    public function setUseDetail($bool) {

        if ($bool === TRUE) {
            $this->useDetail = TRUE;
        } else {
            $this->useDetail = FALSE;
        }
        return $this;
    }

    public function getUseDetail() {
        return $this->useDetail;
    }

    public function appendDescription($str) {
        $this->var = $str;
    }

    public function setDescription($description) {

        $this->description = $description;

        if ($this->useDetail && $this->var != '') {
            $this->description .= ' ' . $this->var;
        }

        return $this;
    }

    protected function setTypeId($typeId) {
        $this->typeId = $typeId;
        return $this;
    }


}


class RecurringInvoiceLine extends InvoiceLine {

    protected $periodStart;
    protected $periodEnd;

    public function __construct() {
        parent::__construct();
        $this->setTypeId(InvoiceLineType::Recurring);
    }

    public function loadRecord(\InvoiceLineRS $invoiceLine) {
        parent::loadRecord($invoiceLine);

        $this->setPeriodStart($invoiceLine->Period_Start->getStoredVal());
        $this->setPeriodEnd($invoiceLine->Period_End->getStoredVal());

    }

    public function createNewLine(Item $item, $quantity, $startDate = '', $endDate = '') {

        $this->setPeriodEnd($endDate);
        $this->setPeriodStart($startDate);

        parent::createNewLine($item, $quantity, $this->var);
    }

    public function save(\PDO $dbh, $deleted = FALSE) {

        //
        $ilRs1 = new InvoiceLineRS();

        $ilRs1->Invoice_Id->setNewVal($this->getInvoiceId());
        $ilRs1->Item_Id->setNewVal($this->getItemId());
        $ilRs1->Type_Id->setNewVal($this->getTypeId());
        $ilRs1->Description->setNewVal($this->getDescription());
        $ilRs1->Quantity->setNewVal($this->getQuantity());
        $ilRs1->Price->setNewVal($this->getPrice());

        $ilRs1->Amount->setNewVal($this->getQuantity() * $this->getPrice());

        $ilRs1->Period_Start->setNewVal($this->getPeriodStart());
        $ilRs1->Period_End->setNewVal($this->getPeriodEnd());

        if ($deleted) {
            $ilRs1->Deleted->setNewVal(1);
        }

        $recId = EditRS::insert($dbh, $ilRs1);

        return $recId;

    }

    public function updateLine(\PDO $dbh) {

        $this->invLineRs->Period_Start->setNewVal($this->getPeriodStart());
        $this->invLineRs->Period_End->setNewVal($this->getPeriodEnd());
        $this->invLineRs->Description->setNewVal($this->getDescription());

        parent::updateLine($dbh);
    }

    public function setDescription($description) {

        if ($this->useDetail && $this->getPeriodStart() != '' && $this->getPeriodEnd() != '') {
            $this->description = $description .  ':  ' . date('M j, Y', strtotime($this->getPeriodStart())) . ' - ' . date('M j, Y', strtotime($this->getPeriodEnd()));
        } else {
            $this->description = trim($description . ' ' . $this->var);
        }

        return $this;
    }

    public function getPeriodStart() {
        return $this->periodStart;
    }

    public function getPeriodEnd() {
        return $this->periodEnd;
    }

    public function setPeriodStart($strDate) {
        $this->periodStart = $strDate;
        return $this;
    }

    public function setPeriodEnd($strDate) {
        $this->periodEnd = $strDate;
        return $this;
    }

}

class InvoiceInvoiceLine extends InvoiceLine {

    public function __construct() {
        parent::__construct();
        $this->setTypeId(InvoiceLineType::Invoice);
    }

}

class OneTimeInvoiceLine extends InvoiceLine {

    public function __construct() {
        parent::__construct();
        $this->setTypeId(InvoiceLineType::OneTime);
    }

}

class HoldInvoiceLine extends InvoiceLine {

    public function __construct() {
        parent::__construct();
        $this->setTypeId(InvoiceLineType::Hold);
    }

}

class ReimburseInvoiceLine extends InvoiceLine {

    public function __construct() {
        parent::__construct();
        $this->setTypeId(InvoiceLineType::Reimburse);
    }

}
