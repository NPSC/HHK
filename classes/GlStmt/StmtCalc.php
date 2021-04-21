<?php
namespace HHK\GlStmt;

class StmtCalc {
	
	protected $paymentFromPast = 0;
	protected $paymentToPast = 0;
	protected $pastPaymentsToNow = 0;
	protected $paymentToNow = 0;
	protected $paymentToFuture = 0;
	protected $unallocatedPayments = 0;
	protected $unpaidCharges = 0;
	protected $fullIntervalCharge = 0;
	protected $subsidyCharge = 0;
	protected $waiveAmt = 0;
	protected $discount = 0;
	protected $overPaidVisitIds = [];
	private $vCounter = 0;
	
	/**
	 * @return number
	 */
	public function getPaymentFromPast() {
		return $this->paymentFromPast;
	}

	/**
	 * @return number
	 */
	public function getPaymentToPast() {
		return $this->paymentToPast;
	}
	
		/**
	 * @return number
	 */
	public function getPastPaymentsToNow() {
		return $this->pastPaymentsToNow;
	}

/**
	 * @return number
	 */
	public function getPaymentToNow() {
		return $this->paymentToNow;
	}

	/**
	 * @return number
	 */
	public function getPaymentToFuture() {
		return $this->paymentToFuture;
	}

	/**
	 * @return number
	 */
	public function getUnallocatedPayments() {
		return $this->unallocatedPayments;
	}

	/**
	 * @return number
	 */
	public function getUnpaidCharges() {
		return $this->unpaidCharges;
	}

	/**
	 * @return number
	 */
	public function getFullIntervalCharge() {
		return $this->fullIntervalCharge;
	}

	/**
	 * @return number
	 */
	public function getSubsidyCharge() {
		return $this->subsidyCharge;
	}

	/**
	 * @return number
	 */
	public function getWaiveAmt() {
		return $this->waiveAmt;
	}

	/**
	 * @return number
	 */
	public function getDiscount() {
		return $this->discount;
	}
	
	/**
	 * @return array
	 */
	public function getOverpaidVisitIds() {
		return $this->overPaidVisitIds;
	}
	
	public function addVisit(VisitIntervalCalculator $visitCalc, $idVisit) {
		
		$this->paymentFromPast += $visitCalc->getPaymentFromPast();
		$this->paymentToFuture += $visitCalc->getPaymentToFuture();
		$this->paymentToNow += $visitCalc->getPaymentToNow();
		$this->paymentToPast += $visitCalc->getPaymentToPast();
		$this->pastPaymentsToNow += $visitCalc->getPastPaymentsToNow();
		
		$this->unallocatedPayments += $visitCalc->getUnallocatedPayments();
		$this->unpaidCharges += $visitCalc->getUnpaidCharges();
		
		$this->fullIntervalCharge += $visitCalc->getFullIntervalCharge();
		$this->subsidyCharge += $visitCalc->getSubsidyCharge();
		
		$this->waiveAmt += $visitCalc->getIntervalWaiveAmt();
		$this->discount += $visitCalc->getIntervalDiscount();
		
		if ($visitCalc->getUnallocatedPayments() > 0) {
			$this->overPaidVisitIds[$idVisit] = 'Amount: $'. number_format($visitCalc->getUnallocatedPayments(), 2);
			
		}
		
	}

	
	
	
}