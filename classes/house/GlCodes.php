<?php



class GlCodes {
	
	// General GL codes
	const ALL_GROSS_SALES = '200-1007582-500014';
	const CASH_CHECK = '200-0000000-140007';
	const CREDIT_CARD = '200-0000000-100010';
	
	protected $fileId;
	protected $journalCat;
	protected $startDate;
	protected $glParm;
	protected $records;
	protected $lines;
	protected $errors;
	protected $titles;

	
	public function __construct(\PDO $dbh, $month, $year, $glParm) {
		
		$this->fileId = $year . $month . '01';
		
		$this->startDate = new \DateTimeImmutable(intval($year) . '-' . intval($month) . '-01');
		
		$this->glParm = $glParm;
		
		$this->errors = '';
		
		$this->loadDbRecords($dbh);
		
		$this->titles =  array_flip(array(
				// 'Gross sales' 	=>	'200-1007582-500014',
				'County Sales'		=>	'200-1007582-500014',
				'Foundation Donations'	=>	'200-1007582-500105',
				
				'Check or Cash'	=>	'200-0000000-140007',
				'Credit Card'	=>	'200-0000000-100010',
				'County Fee Liability'	=>	'200-0000000-210134',
				'Foundation Donation'	=>	'200-0000000-180100',
				
				'Funded by Cardio Surgery'	=>	'200-10029108-860023',
				'Funded by NICU'	=>	'200-1001313-860023',
				'Funded by Neurology'	=>	'200-1007536-860023',
				'Funded by Csurgery'	=>	'200-1002200-860023',
				
				'Sales Tax'	=> '200-0000000-210100',
				'Hospitality Tax'	=>	'200-0000000-210115',
		));
		
		
	}
	
	
	public function mapRecords($useTitles = FALSE) {
		
		if (count($this->records) < 1) {
			$this->errors .= 'No Records to Map. ';
		}
		
		foreach ($this->records as $r) {
			
			// Must be paid
			if ($r['i']['iStatus'] != InvoiceStatus::Paid) {
				continue;
			}
			
			// Just one payment
			if (count($r['p']) !== 1) {
				$this->errors .= "Too many Payments for invoice: " . $r['i']['iNumber'];
				continue;
			}
			
			// Void or reverse
			if ($r['p'][0]['pStatus'] == PaymentStatusCode::Reverse || $r['p'][0]['pStatus'] == PaymentStatusCode::VoidSale) {
				continue;
			}
			
			$this->makePaymentLine($r, $useTitles);
			
		}
		
		return $this;
	}
	
	protected function makePaymentLine($r, $useTitles = FALSE) {
		
		$p = $r['p'][0];
		
		$isReturn = FALSE;
		$pDate = $p['pTimestamp'];
		
		if ($p['pMethod'] == PaymentMethod::Charge) {
			$glCode = self::CREDIT_CARD;
		} else {
			$glCode = self::CASH_CHECK;
		}
		
		// override with 3rd party paying
		if ($p['ba_Gl_Code'] != '') {
			$glCode = $p['ba_Gl_Code'];
		}
		
		if ($useTitles) {
			$glCode = $this->titles[$glCode];
		}
		
		if ($p['pStatus'] == PaymentStatusCode::Retrn || $p['Is_Refund'] > 0) {
			
			$isReturn = TRUE;

			if ($p['pUpdated'] != '') {
				$pDate = $p['pUpdated'];
			}
			
			$line = new GlTemplateRecord($this->fileId, $glCode, 0, $p['pAmount'], $pDate, $this->glParm->getJournalCat());
			$this->lines[] = $line->getFieldArray();
			
		} else if ($p['pStatus'] == PaymentStatusCode::Paid && $p['Is_Refund'] == 0) {
			
			$line = new GlTemplateRecord($this->fileId, $glCode, $p['pAmount'], 0, $p['pTimestamp'], $this->glParm->getJournalCat());
			$this->lines[] = $line->getFieldArray();
			
		} else {
			$this->errors[] = "Unanticipated Payment Status: ". $p['pStatus'];
		}
		
		
		foreach($r['l'] as $l) {
			
//			if ($l['Item_Gl_Code'] == '') {
//				continue;
//			}
			
			if ($useTitles) {
				$glCode = $this->titles[$l['Item_Gl_Code']];
			} else {
				$glCode = $l['Item_Gl_Code'];
			}
			
			// map gl code
			if ($isReturn) {
				
				$line = new GlTemplateRecord($this->fileId, $glCode, 0, $l['il_Amount'], $pDate, $this->glParm->getJournalCat());
				$this->lines[] = $line->getFieldArray();
			} else {
				$line = new GlTemplateRecord($this->fileId, $glCode, $l['il_Amount'], 0, $pDate, $this->glParm->getJournalCat());
				$this->lines[] = $line->getFieldArray();
			}
		}
	}
	
	
	protected function loadDbRecords(\PDO $dbh) {
		
		$idInvoice = 0;
		$idPayment = 0;
		$idInvoiceLine = 0;
		
		$invoices = array();
		$invoice = array();
		$payments = array();
		$invoiceLines = array();
		$delegatedInvoiceLines = array();
		
		$endDate = $this->startDate->add(new \DateInterval('P1M'));
		
		$query = "call gl_report('" . $this->startDate->format('Y-m-d') . "','" . $endDate->format('Y-m-d') . "')";
		
    	$stmt = $dbh->query($query);
    	$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    	$stmt->nextRowset();
    	
    	foreach ($rows as $p) {
    		
    		if ($p['idInvoice'] != $idInvoice) {
    			// Next Invoice

    			if ($idInvoice > 0) {
    				// close last invoice
    				$invoices[$idInvoice] = array('i'=>$invoice, 'p'=>$payments, 'l'=>$invoiceLines);
    			}
    			
    			$idInvoice = $p['idInvoice'];

    			// new invoice
    			$invoice = array(
    					'iNumber'=>$p['iNumber'],
    					'iAmount'=>$p['iAmount'],
    					'iStatus'=>$p['iStatus'],
    					'Delegated_Id'=>$p['Delegated_Id'],
    					'iDeleted'=>$p['iDeleted'],
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

    				$payments[] = array(
    						'idPayment'=>$p['idPayment'],
    						'pAmount'=>$p['pAmount'],
    						'pMethod'=>$p['pMethod'],
    						'pStatus'=>$p['pStatus'],
    						'pUpdated'=>($p['pUpdated'] == '' ? '' : $p['pUpdated']),
    						'pTimestamp'=>$p['pTimestamp'],
    						'Is_Refund'=>$p['Is_Refund'],
    						'idPayor'=>$p['idPayor'],
    						'ba_Gl_Code'=>$p['ba_Gl_Code'],
    				);
    			}
    		}

    		if ($p['il_Id'] != 0) {
    			// Invoice line exists

    			if ($idInvoiceLine != $p['il_Id']) {
    				// Next Line

    				$idInvoiceLine = $p['il_Id'];
    				
    				$line = array(
    						'il_Id'=>$p['il_Id'],
    						'il_Amount'=>$p['il_Amount'],
    						'il_Item_Id'=>$p['il_Item_Id'],
    						'Item_Gl_Code'=>$p['Item_Gl_Code'],
    				);
    				
    				if ($p['Delegated_Id'] > 0) {
    					
    					$delegatedInvoiceLines[$p['Delegated_Id']][] = $line;
    					
    				} else if ($p['il_Item_Id'] != ItemId::InvoiceDue) {
    					$invoiceLines[] = $line;
    				}
    			}
    		}
    	}

    	if ($idInvoice > 0) {
    		// close last invoice
    		$invoices[$idInvoice] = array('i'=>$invoice, 'p'=>$payments, 'l'=>$invoiceLines);
    	}
    	
    	// Add the delegated items to their carried-by invoice.
    	foreach ($delegatedInvoiceLines as $k => $l) {
    		
    		foreach ($l as $line) {
    			
    			$invoices[$k]['l'][] = $line;
    		}
    	}

    	$this->records =  $invoices;
	}
	

	public function transferRecords() {
		
		$creds = $this->glParm;
		$data = '';
		
		if (count($this->lines) == 0) {
			$this->errors .= "No records to Transfer. ";
			return FALSE;
		}
		
		foreach ($this->lines as $l) {
			$data .= implode(',', $l) . "/n";
		}
		
		try
		{
			$sftp = new SFTPConnection($creds->getHost(), $creds->getPort());
			$sftp->login($creds->getUsername(), $creds->getClearPassword());
			$sftp->uploadFile($data, $creds->getRemoteFilePath() . 'ggh' . $this->fileId);
			
			return TRUE;
		}
		catch (Exception $e)
		{
			$this->errors .= $e->getMessage() . "\n";
			return FALSE;
		}
		
	}
	
	
	public function getInvoices() {
		return $this->records;
	}
	
	public function getLines() {
		return $this->lines;
	}
	
	public function getErrors() {
		return $this->errors;
	}
	
}


class GlParameters {
	
	protected $host;
	protected $username;
	protected $password;
	protected $remoteFilePath;
	protected $Port;
	protected $startDay;
	protected $journalCat;
	
	protected $glParms;
	protected $tableName;
	
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
		
	}
	
	public function saveParameters(\PDO $dbh, $post, $prefix = 'gl_') {
		
		foreach ($this->glParms as $g) {
			
			if (isset($post[$prefix . $g[0]])) {
				
				$desc = filter_var($post[$prefix . $g[0]], FILTER_SANITIZE_STRING);
				
				if (strtolower($g[0]) == 'password' && $desc != '') {
					$desc = encryptMessage($desc);
				} else {
					$desc = addslashes($desc);
				}
				
				$dbh->exec("update `gen_lookups` set `Description` = '$desc' where `Table_Name` = '" .$this->tableName . "' and `Code` = '" . $g[0] . "'");
				
			}
		}
		
		foreach ($post as $k => $v) {
			
			if (stristr($k, 'bagl')) {
				
				$parts = explode('_', $k);
				
				if (isset($parts[1]) && $parts[1] > 0) {
					
					$id = intval($parts[1]);
					$gl = filter_var($v, FILTER_SANITIZE_STRING);
								
					$dbh->exec("Update name_demog set Gl_Code = '$gl' where idName = $id");
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
		$glTbl->generateMarkup(array('style'=>'float:left; margin-right:1em;'));
		
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
		
		$stmt = $dbh->query("SELECT n.idName, n.Name_First, n.Name_Last, n.Company, nd.Gl_Code " .
				" FROM name n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type'  and nv.Vol_Code = '" . VolMemberType::BillingAgent . "' " .
				" JOIN name_demog nd on n.idName = nd.idName  ".
				" where n.Member_Status='a' and n.Record_Member = 1 order by n.Name_Last, n.Name_First");

		// Billing agent markup
		$glTbl = new HTMLTable();
		
		while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
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
			
			$glTbl->addBodyTr(
					HTMLTable::makeTh($entry, array('class'=>'tdlabel'))
					. HTMLTable::makeTd(HTMLInput::generateMarkup($r['Gl_Code'], array('name'=>$prefix.'_'.$r['idName'], 'size'=>'25')))
			);
		}
		
		$glTbl->addHeaderTr(HTMLTable::makeTh('Billing Agent') . HTMLTable::makeTh('GL Code'));
		
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
		return $this->startDay;
	}

	/**
	 * @return mixed
	 */
	public function getJournalCat() {
		return $this->journalCat;
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
		$this->startDay = $startDay;
	}

	/**
	 * @param mixed $journalCat
	 */
	public function setJournalCat($journalCat) {
		$this->journalCat = $journalCat;
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
	
	public function __construct($fileId, $glCode, $creditAmount, $debitAmount, $purchaseDate, $journalCategory) {
		
		$this->fieldArray = $this->setStaticFields($fileId);
		
		$this->setCreditAmount($creditAmount);
		$this->setDebitAmount($debitAmount);
		$this->setGlCode($glCode);
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
		$fa[self::JOURNAL_CREATE_DATE] = date('Y/m/d');

		$fa[self::CURRENCY_CODE] = 'USD';
		$fa[self::ACTUAL_FLAG] = 'A';
		$fa[self::PAYOR_ID] = '00';
		$fa[self::INTERCOMPANY] = '000';
		$fa[self::FUTURE_1] = '0000';
		$fa[self::FUTURE_2] = '000000';
		$fa[self::BATCH_ID] = 'HHK_Oracle_Category_Code_' . $fileId;
		$fa[self::BATCH_NAME] = 'HHKJournal' . $fileId;
		$fa[self::FILE_ID] = $fileId;
		$fa[self::LEDGER_NAME] = 'CentraCare US';
		
		
		return $fa;
		
	}
	
	public function setGlCode($v) {
		
		$codes = explode('-', $v);

		if (count($codes) != 3) {
			$codes[0]= $v;
			$codes[1]= '0';
			$codes[2]= '0';
		}
		
		$this->fieldArray[self::COMPANY_CODE] = $codes[0];
		$this->fieldArray[self::COST_CENTER] = $codes[1];
		$this->fieldArray[self::ACCOUNT] = $codes[2];
		
	}
	public function setCreditAmount($v) {
		$this->fieldArray[self::CREDIT_AMOUNT] = number_format(abs($v), 2, '.', '');
		
	}
	public function setDebitAmount($v) {
		$this->fieldArray[self::DEBIT_AMOUNT] = number_format(abs($v), 2, '.', '');
		
	}
	public function setPurchaseDate($v) {
		$this->fieldArray[self::EFFECTIVE_DATE] = date('Y/m/d', strtotime($v));
		
	}
	public function setJournalCategory($v) {
		$this->fieldArray[self::JOURNAL_CATEGORY] = $v;
		
	}
}