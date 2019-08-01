<?php
/**
 * Invoice.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */



/**
 * Description of Invoice
 *
 * @author Eric
 */
class Invoice {

    protected $invRs;
    protected $invoiceNum;
    protected $idInvoice;
    protected $amountToPay;
    protected $delegatedInvoiceNumber;
    protected $delegatedStatus;


    function __construct(\PDO $dbh, $invoiceNumber = '') {

        $this->invRs = new InvoiceRs();
        $this->idInvoice = 0;
        $this->amountToPay = 0;
        $this->invoiceNum = '';
        $this->delegatedStatus ='';
        $this->delegatedInvoiceNumber = '';

        if ($invoiceNumber != '') {

            $this->invoiceNum = $invoiceNumber;

            $stmt = $dbh->query("select i.*, ifnull(di.Invoice_Number, '') as Delegated_Invoice_Number, ifnull(di.Status, '') as Delegated_Invoice_Status
                from invoice i left join invoice di on i.Delegated_Invoice_Id = di.idInvoice
                where i.Invoice_Number = '$invoiceNumber'");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($rows) == 1) {
                $this->loadFromRow($rows[0]);
            } else {
                throw new Hk_Exception_Runtime('Invoice number not found: ' . $invoiceNumber);
            }

        }
    }

    public static function load1stPartyUnpaidInvoices(\PDO $dbh, $orderNumber, $returnId = 0) {

        $orderNum = str_replace("'", '', $orderNumber);

        if ($orderNum == '') {
            return array();
        }


        $stmt = $dbh->query("SELECT
    i.idInvoice, i.`Invoice_Number`, i.`Balance`, i.`Amount`
FROM
    `invoice` i
        LEFT JOIN
    name_volunteer2 nv ON i.Sold_To_Id = nv.idName
        AND nv.Vol_Category = 'Vol_Type'
        AND nv.Vol_Code = 'ba'
WHERE
    i.Order_Number = '$orderNum' AND i.Status = '" . InvoiceStatus::Unpaid . "'
        AND i.Deleted = 0
        AND nv.idName IS NULL;");

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);

    }

    public static function loadUnpaidInvoices(\PDO $dbh, $orderNumber) {

        $invRs = new InvoiceRs();
        $invRs->Order_Number->setStoredVal($orderNumber);
        $invRs->Status->setStoredVal(InvoiceStatus::Unpaid);
        $invRs->Deleted->setStoredVal(0);

        $rows = EditRS::select($dbh, $invRs, array($invRs->Order_Number, $invRs->Status, $invRs->Deleted), 'and', array($invRs->Invoice_Date));

        return $rows;

    }

    public static function getIdFromInvNum(\PDO $dbh, $invNum) {

        $idInvoice = 0;

        if ($invNum < 1) {
            return $idInvoice;
        }

        $invRs = new InvoiceRs();
        $invRs->Invoice_Number->setStoredVal($invNum);
        $rows = EditRS::select($dbh, $invRs, array($invRs->Invoice_Number));

        if (count($rows) == 1) {
            EditRS::loadRow($rows[0], $invRs);
            $idInvoice = $invRs->idInvoice->getStoredVal();
        }

        return $idInvoice;

    }

    public function loadInvoice(\PDO $dbh, $idInvoice = 0, $idPayment = 0) {

        $this->invoiceNum = '';

        if ($idInvoice > 0) {

            $stmt = $dbh->query("select i.*, ifnull(di.Invoice_Number, '') as Delegated_Invoice_Number, ifnull(di.Status, '') as Delegated_Invoice_Status
 from invoice i left join invoice di on i.Delegated_Invoice_Id = di.idInvoice
 where i.idInvoice = '$idInvoice'");

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } else if ($idPayment > 0) {

            $stmt = $dbh->query("Select i.*, ifnull(di.Invoice_Number, '') as Delegated_Invoice_Number, ifnull(di.Status, '') as Delegated_Invoice_Status "
                    . "from payment_invoice pi join invoice i on pi.Invoice_Id = i.idInvoice "
                    . "left join invoice di on i.Delegated_Invoice_Id = di.idInvoice "
                    . "where pi.Payment_Id = $idPayment");
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        }

        if (count($rows) == 1) {
            $this->loadFromRow($rows[0]);
        } else {
            throw new Hk_Exception_Runtime('Invoice Id not found: ' . $idInvoice);
        }

    }

    protected function loadFromRow($row) {

        $this->invRs = new InvoiceRs();

        EditRS::loadRow($row, $this->invRs);
        $this->invoiceNum = $this->invRs->Invoice_Number->getStoredVal();
        $this->idInvoice = $this->invRs->idInvoice->getStoredVal();
        $this->delegatedStatus = $row['Delegated_Invoice_Status'];
        $this->delegatedInvoiceNumber = $row['Delegated_Invoice_Number'];

    }

    public static function getLineCount(\PDO $dbh, $idInvoice) {

        $count = 0;
        $id = intval($idInvoice, 10);

        if ($id > 0) {
            $stmt = $dbh->query("select count(*) from invoice_line where Deleted = 0 and Invoice_Id = $id");
            $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

            if (count($rows) > 0) {
                $count = $rows[0][0];
            }
        }

        return $count;
    }

    public function getLines(\PDO $dbh) {

        $lines = array();

        $ilRs = new InvoiceLineRS();
        $ilRs->Invoice_Id->setstoredVal($this->idInvoice);

        $rows = EditRS::select($dbh, $ilRs, array($ilRs->Invoice_Id));

        foreach ($rows as $r) {

            $ilRs = new InvoiceLineRS();
            EditRS::loadRow($r, $ilRs);

            // Falls through if line is marked as deleted.
            if ($ilRs->Deleted->getStoredVal() != 1) {
                $iLine = InvoiceLine::invoiceLineFactory($ilRs->Type_Id->getStoredVal());
                $iLine->loadRecord($ilRs);
                $lines[] = $iLine;
            }
        }

        return $lines;
    }

    public function addLine(\PDO $dbh, InvoiceLine $invLine, $user) {

        if ($this->isDeleted()) {
            throw new Hk_Exception_Runtime('Cannot add a line to a deleted Invoice.  ');
        }

        $invLine->setInvoiceId($this->getIdInvoice());
        $invLine->save($dbh);
        $this->updateInvoiceAmount($dbh, $user);
    }

    public function addTaxLines(\PDO $dbh, $username) {

        if ($this->getStatus() != InvoiceStatus::Unpaid || $this->isDeleted()) {
            return;
        }

        // Get any tax items
        $taxstmt = $dbh->query("Select idItem, Description, Gl_Code, Percentage from item i join item_type_map itm on itm.Item_Id = i.idItem and itm.Type_Id = 2");

        if ($taxstmt->rowCount() == 0) {
            // No tax items are defined by the house.
            return;
        }

        $taxItems = array();

        foreach ($taxstmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
            $t['Total'] = 0;
            $taxItems[$t['idItem']] = $t;

        }

        // Delete any existing tax lines
        $lines = $this->deleteTaxLines($this->getLines($dbh));

        if (count($lines) == 0) {
            return;
        }

        // item-item mapping table
        $iistmt = $dbh->query("Select * from item_item");
        $taxItemMap = $iistmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($lines as $l) {

            // Look for tax item connection
            foreach ($taxItemMap as $m) {

                if ($m['idItem'] == $l->getItemId() && isset($taxItems[$m['Item_Id']])) {

                    $taxItems[$m['Item_Id']]['Total'] += $l->getAmount();
                }
            }

        }

        // Add tax amount lines
        foreach ($taxItems as $t) {

            if ($t['Total'] != 0 && $t['Percentage'] != 0) {

                $quant = $t['Percentage'] / 100;

                $taxInvoiceLine = new TaxInvoiceLine();
                $taxInvoiceLine->createNewLine(new Item($dbh, $t['idItem'], $t['Total']), $quant, '');
                $this->addLine($dbh, $taxInvoiceLine, $username);
            }
        }

    }

    protected function deleteTaxLines($lines) {

        $filteredLines = array();

        foreach ($lines as $l) {

            if ($l->getTypeId() != 2) {
                $filteredLines[] = $l;
            }
        }

        return $filteredLines;
    }

    public function deleteLine(\PDO $dbh, $idInvoiceLine, $username) {

        $lines = $this->getLines($dbh);
        $result = FALSE;

        foreach ($lines as $line) {

            if ($line->getLineId() == $idInvoiceLine) {

                $line->setDeleted();
                $ct = $line->updateLine($dbh);

                if ($ct > 0) {
                    $this->updateInvoiceAmount($dbh, $username);
                    $this->updateInvoiceStatus($dbh, $username);

                    // Delete any zero amount payments for this Invoice.
                    $stmt = $dbh->query("select p.idPayment, pi.idPayment_Invoice from payment_invoice pi join payment p on pi.Payment_Id = p.idPayment and p.Amount = 0
where pi.Invoice_Id = " . $this->getIdInvoice());

                    $rows = $stmt->fetchAll(\PDO::FETCH_NUM);
                    if (count($rows) > 0) {
                        $idPayment = intval($rows[0][0]);
                        $idPayInv = intval($rows[0][1]);

                        if ($idPayment > 0) {
                            $dbh->exec("delete from payment where idPayment = $idPayment");
                            $dbh->exec("delete from payment_invoice where idPayment_Invoice = $idPayInv");
                        }
                    }

                    $result = TRUE;
                }

                break;
            }
        }

        return $result;
    }

    public function createMarkup(\PDO $dbh) {


        $uS = Session::getInstance();
        $config = new Config_Lite(ciCFG_FILE);

        $invoiceTerm = $config->getString('financial', 'InvoiceTerm', '30');
        $hospital = '';
        $roomTitle = '';
        $idGuest = 0;
        $idPatient = 0;
        $idAssoc = 0;
        $idHosp = 0;
        $patientName = '';


        // Find Hospital and Room
        $pstmt = $dbh->query("select
    hs.idHospital, hs.idAssociation, re.Title, v.idPrimaryGuest, hs.idPatient, n.Name_Full
from
    visit v
	left join
    resource re on v.idResource = re.idResource
        left join
    hospital_stay hs on v.idHospital_stay = hs.idHospital_stay
        left join
    name n on n.idName = hs.idPatient
where
    v.idVisit = " . $this->getOrderNumber());

        $rows = $pstmt->fetchAll(\PDO::FETCH_ASSOC);

        if(count($rows) > 0){
            $idAssoc = $rows[0]['idAssociation'];
            $idGuest = $rows[0]['idPrimaryGuest'];
            $idHosp = $rows[0]['idHospital'];
            $idPatient = $rows[0]['idPatient'];
            $roomTitle = $rows[0]['Title'];
            $patientName = $rows[0]['Name_Full'];
        }

        // Hospital
        if ($idAssoc > 0 && isset($uS->guestLookups[GL_TableNames::Hospital][$idAssoc]) && $uS->guestLookups[GL_TableNames::Hospital][$idAssoc][1] != '(None)') {
            $hospital .= $uS->guestLookups[GL_TableNames::Hospital][$idAssoc][1] . ' / ';
        }
        if ($idHosp > 0 && isset($uS->guestLookups[GL_TableNames::Hospital][$idHosp])) {
            $hospital .= $uS->guestLookups[GL_TableNames::Hospital][$idHosp][1];
        }

        // Items
        $tbl = new HTMLTable();

        $tbl->addHeaderTr(HTMLTable::makeTh('Room').HTMLTable::makeTh('Item').HTMLTable::makeTh('Amount'));

        foreach ($this->getLines($dbh) as $line) {
            $tbl->addBodyTr(
                    HTMLTable::makeTd($roomTitle)
                    . HTMLTable::makeTd($line->getDescription())
                    . HTMLTable::makeTd(number_format($line->getAmount(), 2), array('class'=>'tdlabel')));
        }

        // totals
        $tbl->addBodyTr(HTMLTable::makeTd("Total:", array('class'=>'tdlabel hhk-tdTotals', 'colspan'=>'2')) . HTMLTable::makeTd('$'.number_format($this->getAmount(), 2), array('class'=>'hhk-tdTotals tdlabel')));
        $tbl->addBodyTr(HTMLTable::makeTd("Previous Payments:", array('class'=>'tdlabel', 'colspan'=>'2')) . HTMLTable::makeTd(number_format(($this->getAmount() - $this->getBalance()), 2), array('class'=>'hhk-tdTotals tdlabel')));

        if ($this->getDelegatedStatus() == InvoiceStatus::Paid) {
            $tbl->addBodyTr(HTMLTable::makeTd("Balance Due:", array('class'=>'tdlabel', 'colspan'=>'2')) . HTMLTable::makeTd('$0.00', array('class'=>'hhk-tdTotals tdlabel')));
        } else {
            $tbl->addBodyTr(HTMLTable::makeTd("Balance Due:", array('class'=>'tdlabel', 'colspan'=>'2')) . HTMLTable::makeTd('$'.number_format($this->getBalance(), 2), array('class'=>'hhk-tdTotals tdlabel')));
        }

        // House Icon and address
        $rec = Receipt::getHouseIconMarkup();
        $rec .= HTMLContainer::generateMarkup('div', Receipt::getAddressTable($dbh, $uS->sId), array('style'=>'float:left;margin-bottom:10px;margin-left:20px;'));

        // Invoice dates
        $invDate = new DateTime($this->getDate());
        $invDateString = $invDate->format('M jS, Y');
        $invDate->add(new DateInterval('P' . $invoiceTerm . 'D'));

        $invTbl = new HTMLTable();

        if ($this->isDeleted()) {
            $rec .= HTMLContainer::generateMarkup('h2', 'INVOICE - DELETED', array('style'=>'clear:both;margin-bottom:10px;color:darkred;'));
        } else if ($this->getStatus() == InvoiceStatus::Carried) {
            $rec .= HTMLContainer::generateMarkup('h2', 'INVOICE - (Delegated To Invoice #' . $this->getDelegatedInvoiceNumber() .')', array('style'=>'clear:both;margin-bottom:10px;color:blue;'));
        } else {
            $rec .= HTMLContainer::generateMarkup('h2', 'INVOICE', array('style'=>'clear:both;margin-bottom:10px;'));
        }

        $invTbl->addBodyTr(
                HTMLTable::makeTd('INVOICE #:', array('class'=>'tdlabel'))
                .HTMLTable::makeTd($this->getInvoiceNumber())
                );

        $invTbl->addBodyTr(
                HTMLTable::makeTd('DATE:', array('class'=>'tdlabel'))
                .HTMLTable::makeTd($invDateString)
                );

        $invTbl->addBodyTr(HTMLTable::makeTd('TERMS NET:', array('class'=>'tdlabel')) . HTMLTable::makeTd($invoiceTerm));
        $invTbl->addBodyTr(HTMLTable::makeTd('DUE DATE:', array('class'=>'tdlabel')) . HTMLTable::makeTd($invDate->format('M jS, Y')));

        if ($this->getDelegatedStatus() == InvoiceStatus::Paid) {
            $invTbl->addBodyTr(HTMLTable::makeTd('BALANCE DUE:', array('class'=>'tdlabel')) . HTMLTable::makeTd('$0.00'));
        } else {
            $invTbl->addBodyTr(HTMLTable::makeTd('BALANCE DUE:', array('class'=>'tdlabel')) . HTMLTable::makeTd('$' . number_format($this->getBalance(), 2)));
        }

        $rec .= $invTbl->generateMarkup(array('class'=>'hhk-tdbox-noborder', 'style'=>'float:left;'));

        $billTbl = new HTMLTable();
        $billTbl->addBodyTr(HTMLTable::makeTd('Bill To'));
        $billTbl->addBodyTr(HTMLTable::makeTd($this->getBillToAddress($dbh, $this->getSoldToId())->generateMarkup()));
        $rec .= $billTbl->generateMarkup(array('style'=>'float:right; margin-right:40px;'));

        $rec .= HTMLContainer::generateMarkup('div','', array('style'=>'clear:both;'));

        // Patient and guest
        if ($idPatient != $idGuest && $patientName != '') {
            $rec .= HTMLContainer::generateMarkup('h4', 'Patient:  ' . $patientName, array('style'=>'margin-top:10px;'));
        }

        $rec .= HTMLContainer::generateMarkup('h4', 'Guest', array('style'=>'margin-top:10px;'));
        $rec .= $this->getGuestAddress($dbh, $idGuest);

        $rec .= HTMLContainer::generateMarkup('h4', 'Hospital:  ' . $hospital, array('style'=>'margin-bottom:10px;margin-top:10px;'));

        $rec .= HTMLContainer::generateMarkup('div', $tbl->generateMarkup(), array('class'=>'hhk-tdbox'));

        if ($this->getInvoiceNotes() != '') {
            $rec .= HTMLContainer::generateMarkup('p', $this->getInvoiceNotes(), array('style'=>'margin-top:1em;'));
        }

        if ($this->isDeleted()) {
            $rec = HTMLContainer::generateMarkup('div', $rec, array('style'=>'background-color:pink;'));
        }

        return $rec;

    }

    public static function getGuestAddress(\PDO $dbh, $idName) {
        $mkup = '';

        if ($idName > 0) {

            $stmt = $dbh->query("select
    ifnull(a.Address_1,'') as Address_1,
    ifnull(a.Address_2,'') as Address_2,
    ifnull(a.City,'') as City,
    ifnull(a.State_Province,'') as State,
    ifnull(a.Postal_Code,'') as Zip,
    ifnull(p.Phone_Num,'') as Phone_Num,
    ifnull(e.Email,'') as Email,
	n.Name_Full
from
	name n left join
    name_address a ON n.idName = a.idName  and a.Purpose = n.Preferred_Mail_Address
        left join
    name_phone p ON n.idName = p.idName and n.Preferred_Phone = p.Phone_Code
        left join
    name_email e ON n.idName = e.idName and n.Preferred_Email = e.Purpose
where
    n.idName = $idName");

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($rows) == 1) {


                $street = $rows[0]['Address_1'];
                $city = '';

                if ($street != '') {

                    if ($rows[0]['Address_2'] != '') {
                        $street .= ', ' . $rows[0]['Address_2'];
                    }

                    $city = $rows[0]['City'] . ', ' . $rows[0]['State'] . '  ' . $rows[0]['Zip'];

                }

                $adrTbl = new HTMLTable();

                $adrTbl->addBodyTr(HTMLTable::makeTd($rows[0]['Name_Full']));
                $adrTbl->addBodyTr(HTMLTable::makeTd($street));
                $adrTbl->addBodyTr(HTMLTable::makeTd($city));

                if ($rows[0]['Phone_Num'] != '') {
                    $adrTbl->addBodyTr(HTMLTable::makeTd('Phone: ' . $rows[0]['Phone_Num']));
                }
                if ($rows[0]['Email'] != '') {
                    $adrTbl->addBodyTr(HTMLTable::makeTd('Email: ' . $rows[0]['Email']));
                }


                $mkup = $adrTbl->generateMarkup();
            }
        }

        return $mkup;

    }

    public static function getBillToAddress(\PDO $dbh, $idName) {

        $adrTbl = new HTMLTable();

        if ($idName > 0) {

            $stmt = $dbh->query("select
    ifnull(n.Company,'') as Company,
    ifnull(a.Address_1,'') as Address_1,
    ifnull(a.Address_2,'') as Address_2,
    ifnull(a.City,'') as City,
    ifnull(a.State_Province,'') as State,
    ifnull(a.Postal_Code,'') as Zip,
    ifnull(ab.Address_1,'') as Billing_1,
    ifnull(ab.Address_2,'') as Billing_2,
    ifnull(ab.City,'') as Billing_City,
    ifnull(ab.State_Province,'') as Billing_State,
    ifnull(ab.Postal_Code,'') as Billing_Zip,
    ifnull(ac.Address_1,'') as Company_1,
    ifnull(ac.Address_2,'') as Company_2,
    ifnull(ac.City,'') as Company_City,
    ifnull(ac.State_Province,'') as Company_State,
    ifnull(ac.Postal_Code,'') as Company_Zip,
    ifnull(p.Phone_Num,'') as Phone_Num,
    ifnull(e.Email,'') as Email,
	n1.Name_Full,
	n1.Company as Company_Name
from
	name n1 left join
    name n on n1.Company_Id = n.idName
        left join
    name_address ab ON n.idName = ab.idName  and ab.Purpose = 'b'
        left join
    name_address a ON n.idName = a.idName  and a.Purpose = n.Preferred_Mail_Address
        left join
    name_address ac ON n1.idName = ac.idName  and ac.Purpose = n1.Preferred_Mail_Address
        left join
    name_phone p ON n1.idName = p.idName and n1.Preferred_Phone = p.Phone_Code
        left join
    name_email e ON n1.idName = e.idName and n1.Preferred_Email = e.Purpose
where
    n1.idName = $idName");

            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (count($rows) == 1) {

                if ($rows[0]['Billing_1'] != '') {
                    // Use billing address
                    $street = $rows[0]['Billing_1'];

                    if ($rows[0]['Billing_2'] != '') {
                        $street .= ', ' . $rows[0]['Billing_2'];
                    }

                    $city = $rows[0]['Billing_City'];
                    $state = $rows[0]['Billing_State'];
                    $zip = $rows[0]['Billing_Zip'];
                    $careOf = $rows[0]['Name_Full'];
                    $company = $rows[0]['Company'];

                } else if ($rows[0]['Address_1'] != '') {

                    $street = $rows[0]['Address_1'];

                    if ($rows[0]['Address_2'] != '') {
                        $street .= ', ' . $rows[0]['Address_2'];
                    }

                    $city = $rows[0]['City'];
                    $state = $rows[0]['State'];
                    $zip = $rows[0]['Zip'];
                    $careOf = $rows[0]['Name_Full'];
                    $company = $rows[0]['Company'];

                } else {

                    $street = $rows[0]['Company_1'];

                    if ($rows[0]['Company_2'] != '') {
                        $street .= ', ' . $rows[0]['Company_2'];
                    }

                    $city = $rows[0]['Company_City'];
                    $state = $rows[0]['Company_State'];
                    $zip = $rows[0]['Company_Zip'];
                    $careOf = '';
                    $company = $rows[0]['Company_Name'];

                    if ($company == '') {
                        $company = $rows[0]['Name_Full'];
                    }
                }



                $adrTbl->addBodyTr(HTMLTable::makeTd($company));

                if ($careOf != ''){
                    $adrTbl->addBodyTr(HTMLTable::makeTd('c/o: ' . $careOf));
                }

                $adrTbl->addBodyTr(HTMLTable::makeTd($street));
                $adrTbl->addBodyTr(HTMLTable::makeTd($city . ', ' . $state . ' ' . $zip));
                if ($rows[0]['Phone_Num'] != '') {
                    $adrTbl->addBodyTr(HTMLTable::makeTd('Phone: ' . $rows[0]['Phone_Num']));
                }
                if ($rows[0]['Email'] != '') {
                    $adrTbl->addBodyTr(HTMLTable::makeTd('Email: ' . $rows[0]['Email']));
                }
            }
        }

        return $adrTbl;
    }

    public function delegateTo(\PDO $dbh, Invoice $delegatedInvoice, $user) {

        // only delegate to invoices with the same order number
        if ($this->getOrderNumber() != $delegatedInvoice->getOrderNumber()) {
            return FALSE;
        }

        // set this invoice as delegated
        if ($this->getStatus() == InvoiceStatus::Unpaid) {

            // update delegated invoice
            $carriedAmt = $delegatedInvoice->invRs->Carried_Amount->getStoredVal();
            $delegatedInvoice->invRs->Carried_Amount->setNewVal($carriedAmt + $this->getBalance());

            $invItem = new Item($dbh, ItemId::InvoiceDue, $this->getBalance());
            $invLine = new InvoiceInvoiceLine();
            $invLine->createNewLine($invItem, 1, $this->getInvoiceNumber());

            $delegatedInvoice->addLine($dbh, $invLine, $user);

            // Update this invoice to Carried.
            $this->invRs->Delegated_Invoice_Id->setNewVal($delegatedInvoice->idInvoice);
            $this->invRs->Status->setNewVal(InvoiceStatus::Carried);
            $this->invRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $this->invRs->Updated_By->setNewVal($user);

            EditRS::update($dbh, $this->invRs, array($this->invRs->Invoice_Number));
            EditRS::updateStoredVals($this->invRs);

            return TRUE;
        } else {
            return FALSE;
        }

    }

    /** Sums invoice line amounts and updates invoice amount total and balance
     *
     * @param \PDO $dbh
     * @param string $user
     */
    protected function updateInvoiceAmount(\PDO $dbh, $user) {

        $stmt = $dbh->query("Select sum(Amount) from invoice_line where Deleted = 0 and Invoice_Id = " . $this->idInvoice);
        $rows = $stmt->fetchAll();


        if (count($rows) == 1) {

            $newAmount = $rows[0][0];
            $oldAmount = $this->invRs->Amount->getStoredVal();
            $oldBalance = $this->invRs->Balance->getStoredVal();

            $difAmount = $newAmount - $oldAmount;
            $newBalance = $oldBalance + $difAmount;

            $this->invRs->Amount->setNewVal($newAmount);
            $this->invRs->Balance->setNewVal($newBalance);
            $this->invRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $this->invRs->Updated_By->setNewVal($user);

            EditRS::update($dbh, $this->invRs, array($this->invRs->Invoice_Number));

            EditRS::updateStoredVals($this->invRs);

        }

    }

    public function updateInvoiceStatus(\PDO $dbh, $username) {

            if ($this->invRs->Amount->getStoredVal() != 0 && $this->invRs->Balance->getStoredVal() == 0) {
                $this->invRs->Status->setNewVal(InvoiceStatus::Paid);
            } else {
                $this->invRs->Status->setNewVal(InvoiceStatus::Unpaid);
            }

            $this->invRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $this->invRs->Updated_By->setNewVal($username);

            EditRS::update($dbh, $this->invRs, array($this->invRs->Invoice_Number));

            EditRS::updateStoredVals($this->invRs);
    }

    public function newInvoice(\PDO $dbh, $amount, $soldToId, $idGroup, $orderNumber, $suborderNumber, $notes, $invoiceDate, $username, $description = '') {

        $invRs = new InvoiceRs();
        $invRs->Amount->setNewVal($amount);
        $invRs->Balance->setNewVal($amount);
        $invRs->Invoice_Number->setNewVal(self::createNewInvoiceNumber($dbh));
        $invRs->Sold_To_Id->setNewVal($soldToId);
        $invRs->idGroup->setNewVal($idGroup);
        $invRs->Order_Number->setNewVal($orderNumber);
        $invRs->Suborder_Number->setNewVal($suborderNumber);
        $invRs->Notes->setNewVal($notes);
        $invRs->Invoice_Date->setNewVal($invoiceDate);
        $invRs->Status->setNewVal(InvoiceStatus::Unpaid);
        $invRs->Description->setNewVal($description);

        $invRs->Updated_By->setNewVal($username);
        $invRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

        $this->idInvoice = EditRS::insert($dbh, $invRs);
        $invRs->idInvoice->setNewVal($this->idInvoice);

        EditRS::updateStoredVals($invRs);

        $this->invRs = $invRs;
        $this->invoiceNum = $invRs->Invoice_Number->getStoredVal();
        $this->delegatedStatus ='';
        $this->delegatedInvoiceNumber = '';

        return $this->idInvoice;
    }

    public function setBillDate(\PDO $dbh, $billDT, $user, $notes) {

        if (is_null($billDT) === FALSE) {
            $this->invRs->BillDate->setNewVal($billDT->format('Y-m-d'));
            $this->invRs->BillStatus->setNewVal(BillStatus::Billed);
        }

        if ($notes != '') {
            $this->invRs->Notes->setNewVal($notes);
        }

        if ($this->idInvoice > 0) {

            $this->invRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $this->invRs->Updated_By->setNewVal($user);

            EditRS::update($dbh, $this->invRs, array($this->invRs->Invoice_Number));

            EditRS::updateStoredVals($this->invRs);

            return TRUE;
        }

        return FALSE;
    }

    public function setAmountToPay($amt) {
        $this->amountToPay = $amt;
        return $this;
    }

    public function getAmountToPay() {
        return $this->amountToPay;
    }

    // Call on payment only.
    public function updateInvoiceBalance(\PDO $dbh, $paymentAmount, $user) {
        // positive payment amounts reduce a  balance.
        // Neg payments increase a  bal.

        if ($this->idInvoice > 0) {

            $balAmt = $this->invRs->Balance->getStoredVal();
            $newBal = $balAmt - $paymentAmount;

            $this->invRs->Balance->setNewVal($newBal);

            $attempts = $this->invRs->Payment_Attempts->getStoredVal();
            $this->invRs->Payment_Attempts->setNewVal(++$attempts);

            if ($newBal == 0) {
                $this->invRs->Status->setNewVal(InvoiceStatus::Paid);
            } else {
                $this->invRs->Status->setNewVal(InvoiceStatus::Unpaid);
            }

            $this->invRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
            $this->invRs->Updated_By->setNewVal($user);

            EditRS::update($dbh, $this->invRs, array($this->invRs->idInvoice));
            EditRS::updateStoredVals($this->invRs);

        } else {
            throw new Hk_Exception_Payment('Cannot make payments on a blank invoice record.  ');
        }
    }


    protected function unwindCarriedInv(\PDO $dbh, $id, &$invIds) {

        $stmt = $dbh->query("select idInvoice from invoice where Delegated_Invoice_Id = " . $id);
        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        foreach ($rows as $r) {

            $invIds[] = $r[0];
            $this->unwindCarriedInv($dbh, $r[0], $invIds);
        }

    }


    protected function deleteCarriedInvoice(\PDO $dbh, $user) {

        if ($this->invRs->Carried_Amount->getStoredVal() == 0) {
            throw new Hk_Exception_Payment('This invoice has no carried amount. ');
        }

        // Get all the carried invoices
        $invIds = array();
        $this->unwindCarriedInv($dbh, $this->idInvoice, $invIds);

        $whAssoc = '';
        foreach ($invIds as $a) {

           if ($a != 0) {

               if ($whAssoc == '') {
                   $whAssoc .= $a;
               } else {
                   $whAssoc .= ",". $a;
               }
           }
        }

        $query = "select count(p.idPayment)
From payment p join payment_invoice pi on p.idPayment = pi.Payment_Id and p.Status_Code = 's' and p.Is_Refund = 0
where pi.Invoice_Id in ($whAssoc)";

        $stmn = $dbh->query($query);
        $rows = $stmn->fetchAll(PDO::FETCH_NUM);

        if (count($rows) > 0 && $rows[0][0] > 0) {
            throw new Hk_Exception_Payment('Unpaid or partially paid invoices cannot be deleted. Remove the payments first.');
        }

        $bolDeld = TRUE;
        foreach ($invIds as $id) {

            if ($this->_deleteInvoice($dbh, $id, $user) === FALSE) {
                $bolDeld = FALSE;
            }
        }

        // Dekete delegated invoice
        if ($bolDeld) {
            return $this->deleteMe($dbh, $user);
        }

        return FALSE;
    }

    public function deleteInvoice(\PDO $dbh, $user) {

        //
        if ($this->invRs->Carried_Amount->getStoredVal() != 0) {
            return $this->deleteCarriedInvoice($dbh, $user);
        }

        switch ($this->getStatus()) {

            case InvoiceStatus::Paid:

                if ($this->getAmount() == 0) {
                    // Delete any 0-amount CASH payment records...
                    $dbh->exec("CALL `delete_Invoice_payments`(" . $this->idInvoice . ", " . PaymentMethod::Cash . ");");
                }

                if ($this->countPayments($dbh) == 0) {
                    return $this->deleteMe($dbh, $user);
                }

                break;

            case InvoiceStatus::Unpaid:

                if ($this->getAmount() != 0 && $this->getBalance() != $this->getAmount()) {
                    throw new Hk_Exception_Payment('Partially paid invoices cannot be deleted. Remove the payments first.');
                }

                $lines = $this->getLines($dbh);
                foreach ($lines as $l) {
                    if ($l->getItemId() == ItemId::LodgingMOA && $this->is3rdParty($dbh, $this->getSoldToId()) && $l->getAmount() > 0) {
                        throw new Hk_Exception_Payment('Cannot delete.  This is a 3rd party invoice for MOA (Money on Account), and the amount may already have been returned to the Guest. ');
                    }
                }

                return $this->deleteMe($dbh, $user);

        }

        throw new Hk_Exception_Payment('Only unpaid invoices can be deleted. ');

    }

    protected function deleteMe(\PDO $dbh, $user) {

        $result = $this->_deleteInvoice($dbh, $this->idInvoice, $user);

        if ($result) {
            $this->loadInvoice($dbh, $this->idInvoice);
        }

        return $result;
    }

    private function _deleteInvoice(\PDO $dbh, $id, $user) {

        if ($id > 0) {

            $dbh->exec("update invoice set Deleted = 1, Last_Updated = now(), Updated_By = '$user' where idInvoice = $id");
            $dbh->exec("update invoice_line set Deleted = 1 where Invoice_Id = $id");

            return TRUE;
        }

        return FALSE;
    }

    protected function is3rdParty(\PDO $dbh, $idName) {

        $stmt = $dbh->query("Select count(*) from name_volunteer2 where idName = $idName and Vol_Category = 'Vol_Type' and Vol_Code = '" .VolMemberType::ReferralAgent. "' and Vol_Status = '" .VolStatus::Active. "'");
        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        if (count($rows) > 0 && $rows[0][0] == 0) {
            return TRUE;
        }

        return FALSE;
    }

    protected function countPayments(\PDO $dbh) {

        $cnt = 0;
        $stmt = $dbh->query("select count(*) from payment_invoice pi where pi.Invoice_Id = " . $this->idInvoice);
        $rows = $stmt->fetchAll(\PDO::FETCH_NUM);

        if (count($rows) > 0) {
            $cnt = $rows[0][0];
        }

        return $cnt;
    }

    private function createNewInvoiceNumber(PDO $dbh) {
        return incCounter($dbh, 'invoice');
    }

    public function getIdInvoice() {
        return $this->invRs->idInvoice->getStoredVal();
    }

    public function getInvoiceNumber() {
        return $this->invRs->Invoice_Number->getStoredVal();
    }

    public function getAmount() {
        return $this->invRs->Amount->getStoredVal();
    }

    public function getBalance() {
        return $this->invRs->Balance->getStoredVal();
    }

    public function getDate() {
        return $this->invRs->Invoice_Date->getStoredVal();
    }

    public function getNotes() {
        return $this->invRs->Notes->getStoredVal();
    }

    public function getStatus() {
        return $this->invRs->Status->getStoredVal();
    }

    public function getPayAttemtps() {
        return $this->invRs->Payment_Attempts->getStoredVal();
    }

    public function getSoldToId() {
        return $this->invRs->Sold_To_Id->getStoredVal();
    }

    public function setSoldToId($id) {
        $this->invRs->Sold_To_Id->setNewVal($id);
        return $this;
    }

    public function getIdGroup() {
        return $this->invRs->idGroup->getStoredVal();
    }

    public function getOrderNumber() {
        return $this->invRs->Order_Number->getStoredVal();
    }

    public function getSuborderNumber() {
        return $this->invRs->Suborder_Number->getStoredVal();
    }

    public function getDelegatedInvoiceNumber() {
        return $this->delegatedInvoiceNumber;
    }

    public function getDelegatedStatus() {
        return $this->delegatedStatus;
    }

    public function getInvoiceNotes() {
        return $this->invRs->Notes->getStoredVal();
    }

    public function getBillDate() {
        return $this->invRs->BillDate->getStoredVal();
    }

    public function getDescription() {
        return $this->invRs->Description->getStoredVal();
    }

    public function setDescription($desc) {
        $this->invRs->Description->setNewVal($desc);
        return $this;
    }

    public function setDelegatedInvoiceNumber($delegatedInvoiceNumber) {
        $this->delegatedInvoiceNumber = $delegatedInvoiceNumber;
        return $this;
    }

    public function setDelegatedStatus($delegatedStatus) {
        $this->delegatedStatus = $delegatedStatus;
        return $this;
    }

    public function getInvRecordSet() {
        return $this->invRs;
    }

    public function isDeleted() {
        if ($this->invRs->Deleted->getStoredVal() == 0) {
            return FALSE;
        } else {
            return TRUE;
        }
    }

}
