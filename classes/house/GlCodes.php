<?php



class GlCodes {

	// General GL codes
	const ALL_GROSS_SALES = '200-1007582-500014';
	const CASH_CHECK = '200-0000000-140007';
	const CREDIT_CARD = '200-0000000-100010';
	const FOUNDATION_DON = '200-0000000-180100';
	const COUNTY_LIABILITY = '200-0000000-210134';

	protected $fileId;
	protected $journalCat;
	protected $startDate;
	protected $endDate;
	protected $glParm;
	protected $records;
	protected $lines;
	protected $errors;
	protected $paymentDate;
	protected $glLineMapper;

	
	public function __construct(\PDO $dbh, $month, $year, $glParm) {

		$this->fileId = $year . $month . '01';

		$this->startDate = new \DateTimeImmutable(intval($year) . '-' . intval($month) . '-01');

		// End date is the beginning of the next month.
		$this->endDate = $this->startDate->add(new DateInterval('P1M'));

		$this->glParm = $glParm;

		$this->errors = array();

		$this->loadDbRecords($dbh);

		$this->lines = array();

		$this->glLineMapper = new GlTemplateRecord();
	}

	public function mapRecords() {

		if (count($this->records) < 1) {
			$this->recordError('No Records to Map. ');
		}

		// Filter payment records.
		foreach ($this->records as $r) {

			// Any payments?
			if (count($r['p']) < 1) {
				$this->recordError('No payment for Invoice ' . $r['i']['iNumber']);
				continue;
			}

			$payments = array();

			foreach ($r['p'] as $p) {

				if ($p['pStatus'] == PaymentStatusCode::Reverse || $p['pStatus'] == PaymentStatusCode::VoidSale || $p['pStatus'] == PaymentStatusCode::Declined) {
					continue;
				}

				$payments[$p['idPayment']] = $p;

			}

			// any payments left?
			if (count($payments) == 0) {
				continue;
			}

			// We can only process one payment(?)
			if (count($payments) !== 1) {
				$this->recordError('To many payments('.count($payments) . ') for Invoice ' . $r['i']['iNumber']);
				continue;
			}

			// Got one - go with it.
			$this->mapPayment($r, array_pop($payments));

		}

		return $this;
	}

	protected function mapPayment($r, $p) {

		$invLines = array();
		$hasWaive = FALSE;

		if ($p['pTimestamp'] != '') {
			$this->paymentDate = new DateTime($p['pTimestamp']);
		} else {
			$this->recordError("Missing Payment Timestamp. Payment Id = ". $p['idPayment']);
			return;
		}

		// Copy invoice lines and Look for waived payments.
		foreach ($r['l'] as $l) {

			$invLines[] = $l;

			if ($l['il_Item_Id'] == ItemId::Waive) {
				$hasWaive = TRUE;
			}
		}

		// Special handling for waived payments.
		if ($hasWaive) {
			$invLines = $this->mapWaivePayments($invLines);
		}

		// payment type sets glCode.
		if ($p['ba_Gl_Debit'] != '') {
			// process 3rd party payment
			
			$glCode = $p['ba_Gl_Debit'];
			
			if ($glCode == self::COUNTY_LIABILITY) {
				// Special handling for county payments
				$invLines = $this->mapCountyPayments($invLines, $p);
			}

		} else if ($p['pMethod'] == PaymentMethod::Charge) {
			
			$glCode = self::CREDIT_CARD;
			
		} else {
			
			$glCode = self::CASH_CHECK;
			
		}


		// Payment Status
		if ($p['pStatus'] == PaymentStatusCode::Retrn) {
			//Return earlier sale
			
			// if original payment is in the same report as this return, then ignore.
			if ($this->paymentDate >= $this->startDate && $this->paymentDate < $this->endDate) {
				return;
			}

			if ($p['pUpdated'] != '') {
				$pUpDate = new DateTime($p['pUpdated']);
			} else {
				$this->recordError("Missing Payment Updated Date on RETURN. Payment Id = ". $p['idPayment']);
				return;
			}

			$this->lines[] = $this->glLineMapper->makeLine($this->fileId, $glCode, (0 - abs($p['pAmount'])), 0, $pUpDate, $this->glParm->getJournalCat());

			foreach($invLines as $l) {

				if ($l['Item_Gl_Code'] == '') {
					continue;
				}

				// map gl code
				$this->lines[] = $this->glLineMapper->makeLine($this->fileId, $l['Item_Gl_Code'], 0, (0 - abs($l['il_Amount'])), $pUpDate, $this->glParm->getJournalCat());
			}

		} else if ($p['pStatus'] == PaymentStatusCode::Paid && $p['Is_Refund'] == 0) {
			// Status = Sale
			
			$this->lines[] = $this->glLineMapper->makeLine($this->fileId, $glCode, $p['pAmount'], 0, $this->paymentDate, $this->glParm->getJournalCat());

			foreach($invLines as $l) {

				if ($l['Item_Gl_Code'] == '') {
					continue;
				}

				$this->lines[] = $this->glLineMapper->makeLine($this->fileId, $l['Item_Gl_Code'], 0, $l['il_Amount'], $this->paymentDate, $this->glParm->getJournalCat());
			}

		} else if ($p['pStatus'] == PaymentStatusCode::Paid && $p['Is_Refund'] > 0){
			// Status = refund amount
			
			$this->lines[] = $this->glLineMapper->makeLine($this->fileId, $glCode, (0 - abs($p['pAmount'])), 0, $this->paymentDate, $this->glParm->getJournalCat());

			foreach($invLines as $l) {

				if ($l['Item_Gl_Code'] == '') {
					continue;
				}

				// map gl code
				$this->lines[] = $this->glLineMapper->makeLine($this->fileId, $l['Item_Gl_Code'], 0, (0 - abs($l['il_Amount'])), $this->paymentDate, $this->glParm->getJournalCat());
			}

		} else {
			$this->recordError("Unanticipated Payment Status: ". $p['pStatus'] . '  Payment Id = '.$p['idPayment']);
		}
	}
	
	protected function mapCountyPayments(array $invLines, array $p) {
		
		$lodgingCharge = 0;
		$taxCharge = 0;

		foreach ($invLines as $l) {

			if ($l['il_Item_Id'] == ItemId::Lodging) {
				$lodgingCharge += $l['il_Amount'];
			}
			
			if ($l['il_Type_Id'] == InvoiceLineType::Tax) {
				$taxCharge += $l['il_Amount'];
			}
		}
		
		
		
		
	}

	protected function makeWaivePayments(array $invLines) {

		// add up waiveable items
		$waiveAmt = 0;
		$waiveGlCode = '';
		$remainingItems = array();

		foreach ($invLines as $l) {

			if ($l['il_Item_Id'] == ItemId::Waive) {
				$waiveAmt += abs($l['il_Amount']);
				$waiveGlCode = $l['Item_Gl_Code'];
			}
		}

		if ($waiveAmt > 0) {

			// credit the waive gl code
			$this->lines[] = $this->glLineMapper->makeLine($this->fileId, $waiveGlCode, 0, $waiveAmt, $this->paymentDate, $this->glParm->getJournalCat());

			// debit the foundation donation
			$this->lines[] = $this->glLineMapper->makeLine($this->fileId, self::FOUNDATION_DON, $waiveAmt, 0, $this->paymentDate, $this->glParm->getJournalCat());

		}

		// reduce the waiveable items
		foreach ($invLines as $l) {

			// Don't return the waiving item
			if ($l['il_Item_Id'] == ItemId::Waive) {
				continue;
			}

			// Adjust the amounts after the waive.
			if ($l['il_Item_Id'] == ItemId::Lodging || $l['il_Item_Id'] == ItemId::AddnlCharge) {

				if ($l['il_Amount'] >= $waiveAmt) {
					$l['il_Amount'] -= $waiveAmt;
					$waiveAmt = 0;
				} else {
					$waiveAmt -= $l['il_Amount'];
					$l['il_Amount'] = 0;
				}
			}

			$remainingItems[] = $l;
		}

		return $remainingItems;
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
				
		$query = "call gl_report('" . $this->startDate->format('Y-m-d') . "','" . $this->endDate->format('Y-m-d') . "')";
		
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

    				$payments[$idPayment] = array(
    						'idPayment'=>$p['idPayment'],
    						'pAmount'=>$p['pAmount'],
    						'pMethod'=>$p['pMethod'],
    						'pStatus'=>$p['pStatus'],
    						'pUpdated'=>($p['pUpdated'] == '' ? '' : $p['pUpdated']),
    						'pTimestamp'=>$p['pTimestamp'],
    						'Is_Refund'=>$p['Is_Refund'],
    						'idPayor'=>$p['idPayor'],
    						'ba_Gl_Debit'=>$p['ba_Gl_Debit'],
    						'ba_Gl_Credit'=>$p['ba_Gl_Credit'],
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
    						'il_Type_Id'=>$p['il_Type_Id'],
    						'Item_Gl_Code'=>$p['Item_Gl_Code'],
    				);
    				
    				if ($p['Delegated_Id'] > 0) {
    					$delegatedInvoiceLines[$p['Delegated_Id']][$idInvoiceLine] = $line;
    				} else if ($p['il_Item_Id'] != ItemId::InvoiceDue) {
    					$invoiceLines[$idInvoiceLine] = $line;
    				}
    			}
    		}
    	}
    	
    	unset($rows);

    	if ($idInvoice > 0) {
    		// close last invoice
    		$invoices[$idInvoice] = array('i'=>$invoice, 'p'=>$payments, 'l'=>$invoiceLines);
    	}
    	
    	// Add the delegated items to their carried-by invoice.
    	foreach ($delegatedInvoiceLines as $k => $l) {
    		
    		foreach ($l as $line) {
    			
    			$invoices[$k]['l'][$line['il_Id']] = $line;
    		}
    	}

    	$this->records =  $invoices;
	}
	

	public function transferRecords() {

		$data = '';
		
		if (count($this->lines) == 0) {
			$this->recordError("No records to Transfer. ");
			return FALSE;
		}
		
		foreach ($this->lines as $l) {
			$data .= implode(',', $l) . "/n";
		}
		
		try
		{
			$sftp = new SFTPConnection($this->glParm->getHost(), $this->glParm->getPort());
			$sftp->login($this->glParm->getUsername(), $this->glParm->getClearPassword());
			$bytesWritten = $sftp->uploadFile($data, $this->glParm->getRemoteFilePath() . 'ggh' . $this->fileId);
			
		}
		catch (Exception $e)
		{
			$this->recordError($e->getMessage() . "\n");
			return FALSE;
		}
		
		return $bytesWritten;
	}
	
	protected function recordError($error) {
		$this->errors[] = $error;
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
	
	public function getTotalCredit() {
		return $this->glLineMapper->getTotalCredit();
	}
	public function getTotalDebit() {
		return $this->glLineMapper->getTotalDebit();
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
				
				if (strtolower($g[0]) == 'password' && $desc != '' && $desc != $g[1]) {
					$desc = encryptMessage($desc);
				} else {
					$desc = addslashes($desc);
				}
				
				$dbh->exec("update `gen_lookups` set `Description` = '$desc' where `Table_Name` = '" .$this->tableName . "' and `Code` = '" . $g[0] . "'");
				
			}
		}
		
		foreach ($post as $k => $v) {
			
			if (stristr($k, 'bagld')) {
				
				$parts = explode('_', $k);
				
				if (isset($parts[1]) && $parts[1] > 0) {
					
					$id = intval($parts[1]);
					$gl = filter_var($v, FILTER_SANITIZE_STRING);
								
					$dbh->exec("Update name_demog set Gl_Code_Debit = '$gl' where idName = $id");
				}
			}
			
			if (stristr($k, 'baglc')) {
				
				$parts = explode('_', $k);
				
				if (isset($parts[1]) && $parts[1] > 0) {
					
					$id = intval($parts[1]);
					$gl = filter_var($v, FILTER_SANITIZE_STRING);
					
					$dbh->exec("Update name_demog set Gl_Code_Credit = '$gl' where idName = $id");
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
		
		$stmt = $dbh->query("SELECT n.idName, n.Name_First, n.Name_Last, n.Company, nd.Gl_Code_Debit, nd.Gl_Code_Credit " .
				" FROM name n join name_volunteer2 nv on n.idName = nv.idName and nv.Vol_Category = 'Vol_Type'  and nv.Vol_Code = '" . VolMemberType::BillingAgent . "' " .
				" JOIN name_demog nd on n.idName = nd.idName  ".
				" where n.Member_Status='a' and n.Record_Member = 1 order by n.Company");

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
					. HTMLTable::makeTd(HTMLInput::generateMarkup($r['Gl_Code_Debit'], array('name'=>$prefix.'d_'.$r['idName'], 'size'=>'25')))
					. HTMLTable::makeTd(HTMLInput::generateMarkup($r['Gl_Code_Credit'], array('name'=>$prefix.'c_'.$r['idName'], 'size'=>'25')))
					);
		}
		
		$glTbl->addHeaderTr(HTMLTable::makeTh('Billing Agent') . HTMLTable::makeTh('GL Debit') . HTMLTable::makeTh('GL Credit'));
		
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

	protected $totalDebit;
	protected $totalCredit;
	protected $fieldArray;

	public function __construct() {

		$this->totalCredit = 0;
		$this->totalDebit = 0;
	}

	public function makeLine($fileId, $glCode, $debitAmount, $creditAmount, $purchaseDate, $journalCategory) {

		$this->fieldArray = $this->setStaticFields($fileId);

		$this->setCreditAmount($creditAmount);
		$this->setDebitAmount($debitAmount);
		$this->setGlCode($glCode);
		$this->setJournalCategory($journalCategory);
		$this->setPurchaseDate($purchaseDate);

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
		$fa[self::FUTURE_2] = '00000';
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
		$this->fieldArray[self::CREDIT_AMOUNT] = number_format($v, 2, '.', '');
		$this->totalCredit += $v;
	}

	public function setDebitAmount($v) {
		$this->fieldArray[self::DEBIT_AMOUNT] = number_format($v, 2, '.', '');
		$this->totalDebit += $v;
	}

	public function setPurchaseDate($v) {
		$this->fieldArray[self::EFFECTIVE_DATE] = $v->format('Y-m-d');
	}

	public function setJournalCategory($v) {
		$this->fieldArray[self::JOURNAL_CATEGORY] = $v;
	}

	public function getTotalCredit() {
		return $this->totalCredit;
	}

	public function getTotalDebit() {
		return $this->totalDebit;
	}
}