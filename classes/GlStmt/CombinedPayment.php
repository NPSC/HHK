<?php
namespace HHK\GlStmt;

class CombinedPayment {
	
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
	
	
	public function __construct() {
		
		$this->payAmount = 0;
		$this->returnAmount = 0;
		$this->refundAmount = 0;
		
	}
	
	public function returnAmount($a) {
		$this->returnAmount += abs($a);
	}
	
	public function refundAmount($a) {
		$this->refundAmount += abs($a);
		
	}
	
	public function payAmount($a) {
		$this->payAmount += abs($a);
		
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