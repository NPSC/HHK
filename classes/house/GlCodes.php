<?php

namespace classes\house;

class GlCodes {
	
	protected $fileId;
	protected $journalCat;
	
	public function __construct(\PDO $dbh, $month, $year, $journalCategory) {
		
		$this->fileId = $year . $month . '01';

		$this->journalCat = $journalCategory;
		
	}
	
	public function mapGlCodes() {
		
	}
	
}

interface iGlTemplateRecord {
	
	public function setGlCode($v);
	public function setCreditAmount($v);
	public function setDebitAmount($v);
	public function setPurchaseDate($v);
	public function setJournalCategory($v);
	
}

class GlTemplateRecord implements iGlTemplateRecord {
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