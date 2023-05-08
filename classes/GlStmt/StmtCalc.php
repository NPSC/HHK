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


	/**
	 * @return float
	 */
	public function getPaymentFromPast() {
		return $this->paymentFromPast;
	}

	/**
	 * @return float
	 */
	public function getPaymentToPast() {
		return $this->paymentToPast;
	}

		/**
	 * @return float
	 */
	public function getPastPaymentsToNow() {
		return $this->pastPaymentsToNow;
	}

/**
	 * @return float
	 */
	public function getPaymentToNow() {
		return $this->paymentToNow;
	}

	/**
	 * @return float
	 */
	public function getPaymentToFuture() {
		return $this->paymentToFuture;
	}

	/**
	 * @return float
	 */
	public function getUnallocatedPayments() {
		return $this->unallocatedPayments;
	}

	/**
	 * @return float
	 */
	public function getUnpaidCharges() {
		return $this->unpaidCharges;
	}

	/**
	 * @return float
	 */
	public function getFullIntervalCharge() {
		return $this->fullIntervalCharge;
	}

	/**
	 * @return float
	 */
	public function getSubsidyCharge() {
		return $this->subsidyCharge;
	}

	/**
	 * @return float
	 */
	public function getWaiveAmt() {
		return $this->waiveAmt;
	}

	/**
	 * @return float
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

	public function getIncome() {
		return $this->getPaymentToNow() + $this->getPastPaymentsToNow() + $this->getUnpaidCharges() + abs($this->getDiscount())
			+ abs($this->getWaiveAmt()) + $this->getSubsidyCharge();

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