<?php
namespace HHK\GlStmt;

/**
 * Object to collect all payments for one invoice.
 *
 */class CombinedPayment {
	
	/**
	 *
	 * @var float
	 */
	protected $payAmount;
	/**
	 *
	 * @var float
	 */
	protected $returnAmount;
	/**
	 *
	 * @var float
	 */
	protected $refundAmount;
	
	protected $numberPayments = 0;
	protected $numberReturns = 0;
	protected $numberRefunds = 0;
	/**
	 *
	 * @var \DateTime
	 */
	protected $paymentDate;
	
	/**
	 *
	 * @var \DateTime
	 */
	protected $updatedDate;
	
	
	/**
	 * @return number
	 */
	public function getNumberPayments() {
		return $this->numberPayments;
	}

	/**
	 * @return number
	 */
	public function getNumberReturns() {
		return $this->numberReturns;
	}

	/**
	 * @return number
	 */
	public function getNumberRefunds() {
		return $this->numberRefunds;
	}

	public function __construct() {
		
		$this->payAmount = 0;
		$this->returnAmount = 0;
		$this->refundAmount = 0;
		
	}
	
	public function returnAmount($a) {
		$this->returnAmount += abs($a);
		$this->numberReturns++;
	}
	
	public function refundAmount($a) {
		$this->refundAmount += abs($a);
		$this->numberRefunds++;
		
	}
	
	public function payAmount($a) {
		$this->payAmount += abs($a);
		$this->numberPayments++;
		
	}
	
	/**
	 * @return float
	 */
	public function getPayAmount() {
		return $this->payAmount;
	}

	/**
	 * @return float
	 */
	public function getReturnAmount() {
		return $this->returnAmount;
	}

	/**
	 * @return float
	 */
	public function getRefundAmount() {
		return $this->refundAmount;
	}


	/**
	 * @return \DateTime
	 */
	public function getPaymentDate() {
		return $this->paymentDate;
	}

	/**
	 * @return \DateTime
	 */
	public function getUpdatedDate() {
		return $this->updatedDate;
	}

	/**
	 * @param \DateTime $paymentDate
	 */
	public function setPaymentDate($paymentDate) {
		$this->paymentDate = $paymentDate;
	}

	/**
	 * @param \DateTime $updatedDate
	 */
	public function setUpdatedDate($updatedDate) {
		$this->updatedDate = $updatedDate;
	}

	
	
}