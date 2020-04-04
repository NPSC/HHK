<?php

namespace classes\house;
use Hk_Exception_Payment;
use SFTPConnection;

class GlCodes {
	
	const JOURNAL_TEST_CAT = 'Test Category1';
	const JOURNAL_PRODUCTION_CAT = '';

	// General GL codes
	const ALL_GROSS_SALES = '200-1007582-500014';
	const CASH_CHECK = '200-0000000-140007';
	const CREDIT_CARD = '200-0000000-100010';
	
	protected $fileId;
	protected $journalCat;
	protected $startDate;
	protected $records;
	
	public function __construct(\PDO $dbh, $month, $year, $journalCategory) {
		
		$this->fileId = $year . $month . '01';
		
		$this->startDate = new \DateTimeImmutable(intval($year) . '-' . intval($month) . '-01');

		$this->journalCat = $journalCategory;
		
		$this->records = $this->getDbRecords($dbh);
		
	}
	
	public function mapRecords() {
		
		if (count($this->records) < 1) {
			throw new Hk_Exception_Payment('No Records');
		}
		
		foreach ($this->records as $r) {
			
			
		}
	}
	
	protected function getDbRecords(\PDO $dbh) {
		
		$idInvoice = 0;
		$idPayment = 0;
		$idInvoiceLine = 0;
		
		$invoices = array();
		$invoice = array();
		$payments = array();
		$invoiceLines = array();
		
		$endDate = $this->startDate->add(new \DateInterval('P1M'));
		
		$query = "
   SELECT
        ifnull(`i`.`idInvoice` ,0) AS `idInvoice`,
        `i`.`Amount` AS `Invoice_Amount`,
        `i`.`Status` AS `Invoice_Status`,
        `i`.`Carried_Amount` AS `Carried_Amount`,
        `i`.`Balance` AS `Invoice_Balance`,
        `i`.`Delegated_Invoice_Id` AS `Delegated_Invoice_Id`,
        `i`.`Deleted` AS `Deleted`,
        ifnull(`il`.`idInvoice_Line`, '') as `il_Id`,
        ifnull(`il`.`Amount`, 0) as `il_Amount`,
		ifnull(`il`.`Item_Id`, 0) as `il_Item_Id`,
        IFNULL(`p`.`idPayment`, 0) AS `idPayment`,
        IFNULL(`p`.`Amount`, 0) AS `Payment_Amount`,
        IFNULL(`p`.`idPayment_Method`, 0) AS `idPayment_Method`,
        IFNULL(`p`.`Status_Code`, 0) AS `Payment_Status`,
        IFNULL(`p`.`Last_Updated`, '') AS `Payment_Last_Updated`,
        IFNULL(`p`.`Is_Refund`, 0) AS `Is_Refund`,
        IFNULL(`p`.`idPayor`, 0) AS `Payment_idPayor`,
        IFNULL(`p`.`Timestamp`, '') as `Payment_Timestamp`,
		IFNULL(`it`.`Gl_Code`, '') as `Item_Gl_Code`,
        IFNULL(`nv`.`Vol_Status`, '') AS `Bill_Agent`,
		IFNULL(`nd`.`Gl_Code`, '') as `Bill_Agent_Gl_Code`
    FROM
        `payment` `p`
        JOIN `payment_invoice` `pi` ON `p`.`idPayment` = `pi`.`Payment_Id`
        JOIN `invoice` `i` ON `pi`.`Invoice_Id` = `i`.`idInvoice`
        JOIN `invoice_line` `il` on `i`.`idInvoice` = `il`.`Invoice_Id` and `il`.`Deleted` < 1
        LEFT JOIN `name_volunteer2` `nv` ON `p`.`idPayor` = `nv`.`idName`
            AND (`nv`.`Vol_Category` = 'Vol_Type')
            AND (`nv`.`Vol_Code` = 'ba')
		LEFT JOIN name_demog nd on p.idPayor = nd.idName
		LEFT JOIN item it on it.idItem = il.Item_Id
	where (DATE(`p`.`Timestamp`) >= DATE('" . $this->startDate->format('Y-m-d') . "') && DATE(`p`.`Timestamp`) < DATE('" . $endDate->format('Y-m-d') . "'))
		OR (DATE(`p`.`Last_Updated`) >= DATE('" . $this->startDate->format('Y-m-d') . "') && DATE(`p`.`Last_Updated`) < DATE('" . $endDate->format('Y-m-d') . "'))
    ORDER BY i.idInvoice, il.idInvoice_Line, p.idPayment;";
		
    	$stmt = $dbh->query($query);
    	
    	while ($p = $stmt->fetch(\PDO::FETCH_ASSOC)) {
    		
    		if ($p['idInvoice'] != $idInvoice) {
    			// Next Invoice

    			if ($idInvoice > 0) {
    				// close last invoice
    				$invoices[$idInvoice] = array('i'=>$invoice, 'p'=>$payments, 'l'=>$invoiceLines);
    			}

    			$idInvoice = $p['idInvoice'];

    			// new invoice
    			$invoice = array(
    					'idInvoice'=>$p['idInvoice'],
    					'Invoice_Amount'=>$p['Invoice_Amount'],
    					'Bill_Agent'=>$p['Bill_Agent'],
    					'Invoice_Status'=>$p['Invoice_Status'],
    					'Carried_Amount'=>$p['Carried_Amount'],
    					'Delegated_Invoice_Id'=>$p['Delegated_Invoice_Id'],
    			);
    			
    			$idPayment = 0;
    			$idInvoiceLine = 0;
    			$payments = array();
    			$invoiceLines = array();
    		}

    		if ($p['idPayment'] != 0) {
    			// Payment exists

    			if ($idPayment != $p['idPayment']) {
    				// Next Payment

    				$idPayment = $p['idPayment'];

    				$payments[$idPayment] = array(
    						'idPayment'=>$p['idPayment'],
    						'Payment_Amount'=>$p['Payment_Amount'],
    						'idPayment_Method'=>$p['idPayment_Method'],
    						'Payment_Status'=>$p['Payment_Status'],
    						'Payment_Last_Updated'=>$p['Payment_Last_Updated'],
    						'Payment_Timestamp'=>$p['Payment_Timestamp'],
    						'Is_Refund'=>$p['Is_Refund'],
    						'Payment_idPayor'=>$p['Payment_idPayor'],
    						'Last_Updated'=>$p['Payment_Last_Updated'],
    						'Bill_Agent_Gl_Code'=>$p['Bill_Agent_Gl_Code'],
    				);
    			}
    		}

    		if ($p['il_Id'] != 0) {
    			// Invoice line exists

    			if ($idInvoiceLine != $p['il_Id']) {
    				// Next Line

    				$idInvoiceLine = $p['il_Id'];

    				$invoiceLines[$idInvoiceLine] = array(
    						`il_Id`=>$p['il_Id'],
    						`il_Amount`=>$p['il_Amount'],
    						`Item_Gl_Code`=>$p['Item_Gl_Code'],
    				);
    			}
    		}
    	}

    	if ($idInvoice > 0) {
    		// close last invoice
    		$invoices[$idInvoice] = array('i'=>$invoice, 'p'=>$payments, 'h'=>$invoiceLines);
    	}

    	return $invoices;
	}
	
	public function createChooserMarkup() {
		
		
	}

	public function transferRecords(\PDO $dbh) {
		
		$creds = readGenLookupsPDO($dbh, 'Gl_Codes');
		
		$data = implode(',', $this->invoices);
		
		try
		{
			$sftp = new SFTPConnection($creds['Host'][1], $creds['Port'][1]);
			$sftp->login($creds['Username'][1], decryptMessage($creds['Password'][1]));
			$sftp->uploadFile($data, $creds['RemoteFilePath'][1] . 'ggh' . $this->fileId);
		}
		catch (Exception $e)
		{
			echo $e->getMessage() . "\n";
		}
		
	}

}


class GlTemplateRecord {
	// CentraCare journal record (Gorecki)
	
	const STATUS = 0;
	const EFFECTIVE_DATE = 2;
	const JOURNAL_SOURCE = 3;
	const JOURNAL_CATEGORY = 4;
	const CURRENCY_CODE = 5;
	const JOURNAL_CREATE_DATE = 6;
	const ACTUAL_FLAG = 7;
	
	// gl code split among these three 
	const COMPANY_CODE = 8;
	const COST_CENTER = 9;
	const ACCOUNT = 10;
	
	const PAYOR_ID = 11;
	const INTERCOMPANY = 12;
	const FUTURE_1 = 13;
	const FUTURE_2 = 14;
	const DEBIT_AMOUNT = 38;
	const CREDIT_AMOUNT = 39;
	const BATCH_ID = 42;
	const BATCH_NAME = 45;
	const FILE_ID = 66;
	const LEDGER_NAME = 91;
	
	protected $fieldArray;
	
	protected $glCode;
	protected $creditAmount;
	protected $debitAmount;
	protected $purchaseDate;
	protected $journalCategory;
	
	public function __construct($fileId, $glCOde, $creditAmount, $debitAmount, $purchaseDate, $journalCategory) {
		
		$this->fieldArray = $this->setStaticFields($fileId);
		
		$this->setCreditAmount($creditAmount);
		$this->setDebitAmount($debitAmount);
		$this->setGlCode($glCOde);
		$this->setJournalCategory($journalCategory);
		$this->setPurchaseDate($purchaseDate);

	}
	
	public function getFieldArray() {
		return $this->fieldArray;
	}
	
	protected function setStaticFields($fileId) {
		
		$fa = array();
		for ($i = 0; $i <= 93; $i++) {
			$fa[$i] = '';
		}
		
		$fa[self::STATUS] = 'NEW';
		$fa[self::JOURNAL_SOURCE] = 'HHK';
		$fa[self::CURRENCY_CODE] = 'USD';
		$fa[self::ACTUAL_FLAG] = 'A';
		$fa[self::PAYOR_ID] = '0';
		$fa[self::INTERCOMPANY] = '0';
		$fa[self::FUTURE_1] = '0';
		$fa[self::FUTURE_2] = '0';
		$fa[self::BATCH_ID] = 'HHK_Oracle_Category_Code_' . $fileId;
		$fa[self::BATCH_NAME] = 'HHKJournal' . $fileId;
		$fa[self::FILE_ID] = $fileId;
		$fa[self::LEDGER_NAME] = 'CentraCare US';
		
		
		return $fa;
		
	}
	
	public function setGlCode($v) {
		
		$codes = explode('-', $v);

		if (count($codes) != 3) {
			throw new Hk_Exception_Payment('Bad GL Code: ' . $v);
		}
		
		$this->fieldArray[self::COMPANY_CODE] = $codes[0];
		$this->fieldArray[self::COST_CENTER] = $codes[1];
		$this->fieldArray[self::ACCOUNT] = $codes[2];
		
	}
	public function setCreditAmount($v) {
		$this->fieldArray[self::CREDIT_AMOUNT] = number_format($v, 2);
		
	}
	public function setDebitAmount($v) {
		$this->fieldArray[self::DEBIT_AMOUNT] = number_format(abs($v), 2);
		
	}
	public function setPurchaseDate($v) {
		$this->fieldArray[self::JOURNAL_CREATE_DATE] = date('m/d/Y', strtotime($v));
		
	}
	public function setJournalCategory($v) {
		$this->fieldArray[self::JOURNAL_CATEGORY] = $v;
		
	}
}