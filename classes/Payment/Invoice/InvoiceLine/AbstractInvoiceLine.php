<?php

namespace HHK\Payment\Invoice\InvoiceLine;

use HHK\Tables\EditRS;
use HHK\Tables\Payment\InvoiceLineRS;
use HHK\Purchase\Item;
use HHK\SysConst\{InvoiceLineType};

/**
 * AbstractInvoiceLine.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */



/**
 * Description of AbstractInvoiceLine
 *
 * @author Eric
 */
abstract class AbstractInvoiceLine {

    protected $lineId;
    protected $amount;
    protected $quantity;
    protected $price;
    protected $itemId;
    protected $description;
    protected $typeId;
    protected $sourceItemId;
    protected $invoiceId;
    protected $invLineRs;
    protected $var;
    protected $carriedFrom;
    protected $useDetail;
    protected $isPercentage;

    public function __construct($useDetail = TRUE) {
        $this->useDetail = $useDetail;
        $this->invLineRs = new InvoiceLineRS();
    }

    public function loadRecord(InvoiceLineRS $invoiceLine) {

        $this->lineId = $invoiceLine->idInvoice_Line->getStoredVal();
        $this->setItemId($invoiceLine->Item_Id->getStoredVal());
        $this->setSourceItemId($invoiceLine->Source_Item_Id->getStoredVal());
        $this->description = $invoiceLine->Description->getStoredVal();
        $this->setPrice($invoiceLine->Price->getStoredVal());
        $this->setAmount($invoiceLine->Amount->getStoredVal());
        $this->setQuantity($invoiceLine->Quantity->getStoredVal());

        $this->invLineRs = $invoiceLine;

        $this->carriedFrom = '';
        $this->useDetail = TRUE;
    }

    public static function invoiceLineFactory($typeId) {

        switch ($typeId) {
            case InvoiceLineType::Recurring:
                return new RecurringInvoiceLine();
                break;
            case InvoiceLineType::Invoice:
                return new InvoiceInvoiceLine();
                break;
            case InvoiceLineType::OneTime;
                return new OneTimeInvoiceLine();
                break;
            case InvoiceLineType::Hold:
                return new HoldInvoiceLine();
                break;
            case InvoiceLineType::Reimburse:
                return new ReimburseInvoiceLine();
                break;
            case InvoiceLineType::Tax:
                return new TaxInvoiceLine();
                break;
            case InvoiceLineType::Waive:
                return new WaiveInvoiceLine();
                break;

            default:
                return NULL;
        }

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
        $ilRs1->Source_Item_Id->setNewVal($this->getSourceItemId());
        $ilRs1->Description->setNewVal($this->getDescription());
        $ilRs1->Quantity->setNewVal($this->getQuantity());
        $ilRs1->Price->setNewVal($this->getPrice());

        $ilRs1->Amount->setNewVal(round($this->getQuantity() * $this->getPrice(), 2));

        if ($deleted) {
            $ilRs1->Deleted->setNewVal(1);
        }

        $recId = EditRS::insert($dbh, $ilRs1);

        return $recId;

    }

    public function updateLine(\PDO $dbh) {

        $affected = 0;
        $this->invLineRs->Description->setNewVal($this->getDescription());

        if ($this->getLineId() > 0) {
            $affected = EditRS::update($dbh, $this->invLineRs, array($this->invLineRs->idInvoice_Line));
        }

        return $affected;
    }

    public function setDeleted() {
        $this->invLineRs->Deleted->setNewVal(1);
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

    public function getLineId() {
        return $this->lineId;
    }

    public function getInvoiceId() {
        return $this->invoiceId;
    }

    public function getCarriedFrom() {
        return $this->carriedFrom;
    }

    public function getSourceItemId() {
        return $this->sourceItemId;
    }

    public function setSourceItemId($sourceItemId) {
        $this->sourceItemId = $sourceItemId;
        return $this;
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

        // var will have invoice notes when useDetail is off.
        if ($this->useDetail) {
            $this->description .= ($this->description != '' ? '; ' . $this->var : $this->var);
        } else {
            $this->description .= ($this->var != '' ? '; ' . $this->var : '');
        }

        return $this;
    }

    protected function setTypeId($typeId) {
        $this->typeId = $typeId;
        return $this;
    }

}
?>