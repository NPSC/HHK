<?php

namespace HHK\House\GLCodes;

class GLTemplateRecord {
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
        
        $this->totalCredit = 0.0;
        $this->totalDebit = 0.0;
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
        $fa[self::FUTURE_1] = '0000';
        $fa[self::FUTURE_2] = '00000';
        $fa[self::BATCH_ID] = 'HHK_Oracle_Category_Code_' . $fileId;
        $fa[self::BATCH_NAME] = 'HHKJournal' . $fileId;
        $fa[self::FILE_ID] = $fileId;
        $fa[self::LEDGER_NAME] = 'CentraCare US';
        
        return $fa;
        
    }
    
    protected function setGlCode($v) {
        
        $codes = explode('-', $v);
        
        $this->fieldArray[self::COMPANY_CODE] = '000';
        $this->fieldArray[self::COST_CENTER] = '0000000';
        $this->fieldArray[self::ACCOUNT] = '000000';
        $this->fieldArray[self::PAYOR_ID] = '00';
        $this->fieldArray[self::INTERCOMPANY] = '000';
        	
        if (count($codes) == 3) {
        	$this->fieldArray[self::COMPANY_CODE] = trim($codes[0]);
        	$this->fieldArray[self::COST_CENTER] = trim($codes[1]);
        	$this->fieldArray[self::ACCOUNT] = trim($codes[2]);
        	
        } else if (count($codes) == 5) {
        
	        $this->fieldArray[self::COMPANY_CODE] = trim($codes[0]);
	        $this->fieldArray[self::COST_CENTER] = trim($codes[1]);
	        $this->fieldArray[self::ACCOUNT] = trim($codes[2]);
	        $this->fieldArray[self::PAYOR_ID] = trim($codes[3]);
	        $this->fieldArray[self::INTERCOMPANY] = trim($codes[4]);
        }
    }
    
    protected function setCreditAmount($v) {
        $this->fieldArray[self::CREDIT_AMOUNT] = number_format($v, 2, '.', '');
        $this->totalCredit += $v;
    }
    
    protected function setDebitAmount($v) {
        $this->fieldArray[self::DEBIT_AMOUNT] = number_format($v, 2, '.', '');
        $this->totalDebit += $v;
    }
    
    protected function setPurchaseDate($v) {
        $this->fieldArray[self::EFFECTIVE_DATE] = $v->format('Y/m/d');
    }
    
    protected function setJournalCategory($v) {
        $this->fieldArray[self::JOURNAL_CATEGORY] = $v;
    }
    
    public function getTotalCredit() {
        return $this->totalCredit;
    }
    
    public function getTotalDebit() {
        return $this->totalDebit;
    }
}
?>