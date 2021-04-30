<?php
namespace HHK\GlStmt;

use HHK\SysConst\RoomRateCategories;

class VisitIntervalCalculator {
	
	
	protected $intervalCharge = 0;
	protected $preIntervalCharge = 0;
	protected $fullIntervalCharge = 0;
	protected $subsidyCharge = 0;
	
	protected $intervalPay = 0;
	protected $preIntervalPay = 0;  // ForwardPay
	protected $intervalWaiveAmt = 0;
	protected $preIntervalWaiveAmt = 0;
	
	protected $preIntervalDiscount = 0;
	protected $intervalDiscount = 0;
	
	protected $paymentFromPast = 0;
	protected $pastPaymentsToNow = 0;
	protected $paymentToPast = 0;
	protected $paymentToNow = 0;
	protected $paymentToFuture = 0;
	protected $unallocatedPayments = 0;
	protected $unpaidCharges = 0;

	
	public function closeInterval($hasFutureNights) {
		
		$overWaive = 0;
		$overDiscount = 0;
		
		// pre-Discounts diminish pre-lodging charge amounts, already paid by house.
		if ($this->preIntervalCharge >= abs($this->preIntervalDiscount)) {
			$this->preIntervalCharge += $this->preIntervalDiscount;
		} else {
			// more discounts than charges.
			$overDiscount = $this->preIntervalDiscount + $this->preIntervalCharge;
			$this->preIntervalCharge = 0;
		}
		
		// Deal with pre-interval waive amounts
		if ($this->preIntervalCharge >= abs($this->preIntervalWaiveAmt)) {
			$this->preIntervalCharge += $this->preIntervalWaiveAmt;  // Reduce Charge to guest
			$this->preIntervalPay += $this->preIntervalWaiveAmt;  // remove "fake" payment
		} else {
			// Waive bleed over to this month
			$overWaive = $this->preIntervalWaiveAmt + $this->preIntervalCharge;  // Waive forwarded to next month
			
			$this->preIntervalPay += $this->preIntervalWaiveAmt;  // remove "fake" payment
			$this->preIntervalCharge = 0;  // Reduce Charge to guest
		}

		
		// The interval charge is reduced by any overage from pre-interval
		$this->intervalCharge += ($overDiscount + $overWaive);


		// Remove discounts from charges
		if ($this->intervalCharge >= abs($this->intervalDiscount)) {
			$this->intervalCharge += ($this->intervalDiscount);
		} else {
			// more discounts than charges.

			$this->intervalDiscount += $this->intervalCharge;
			$this->intervalCharge = 0;
		}

		// Interval Waive amounts
		if ($this->intervalCharge >= abs($this->intervalWaiveAmt)) {
			$this->intervalCharge += $this->intervalWaiveAmt;
			$this->intervalPay += $this->intervalWaiveAmt;
		} else {
			// More waives than charges.

			// Remove waive payments
			$this->intervalPay += $this->intervalWaiveAmt;

			// Waives meant for the past?
			$unpaidPreCharges = $this->preIntervalCharge - $this->preIntervalPay;

			if ($unpaidPreCharges > 0 && $unpaidPreCharges >= abs($this->intervalWaiveAmt)) {
				// All interval waives goes to preinterval.
				$this->preIntervalCharge += $this->intervalWaiveAmt;
				$this->intervalWaiveAmt = 0;

			} else if ($unpaidPreCharges > 0) {
				// interval waive amount split between pre and now interval charges.
				$this->intervalWaiveAmt += $unpaidPreCharges;
				$this->preIntervalCharge -= $unpaidPreCharges;
				$this->intervalCharge += $this->intervalWaiveAmt;
			} else {
				$this->intervalCharge += $this->intervalWaiveAmt;
			}
		}

		// Payments and charges from the past.
		$pfp = 0;
		$pptn = 0;
		$cfp = 0;
		if ($this->preIntervalPay - $this->preIntervalCharge >= 0) {
			// leftover Payments from past (C23)
			$pfp = $this->preIntervalPay - $this->preIntervalCharge;

			if ($pfp > $this->intervalCharge) {
				$pptn = $this->intervalCharge;
			} else {
				$pptn = $pfp;
			}
		} else {
			// leftover charge after previous payments (C22)
			$cfp = $this->preIntervalCharge - $this->preIntervalPay;
		}

		// Payments to the past
		$ptp = 0;
		if ($cfp > 0) {
			if($this->intervalPay >= $cfp) {
				$ptp = $cfp;
			} else {
				$ptp = $this->intervalPay;
			}
		} else if ($cfp == 0 && $this->intervalCharge == 0) {
			// Unallocated payment to the past.
			$ptp -= $pfp;
		}

		// Payments to now
		$ptn = 0;
		if ($cfp <= $this->intervalPay) {
			if ($this->intervalPay - $cfp > $this->intervalCharge) {
				$ptn = $this->intervalCharge - $pptn;
			} else {
				$ptn = $this->intervalPay - $cfp;
			}
		}

		// PrePayments to Future
		$ptf = 0;
		if ($ptp + $ptn < $this->intervalPay && $hasFutureNights) {
			// Payment to ongoing visit
			$ptf = $this->intervalPay - $ptp - $ptn;
		} else if ($ptp + $ptn < $this->intervalPay) {
			// Payments to nowhere.
			$this->unallocatedPayments = $this->intervalPay - $ptp - $ptn;
		}

		// Unpaid charges this month
		if ($this->intervalCharge - $ptn - $pptn > 0) {
			$this->unpaidCharges = $this->intervalCharge - $ptn - $pptn;  // pfp?
		}

		$this->pastPaymentsToNow = $pptn;
		$this->paymentFromPast = $pfp;
		$this->paymentToPast = $ptp;
		$this->paymentToNow = $ptn;
		$this->paymentToFuture = $ptf;
		
		return $this;
	}


	
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
	public function getPastPaymentsToNow() {
		return $this->pastPaymentsToNow;
	}
	
		/**
	 * @return number
	 */
	public function getIntervalCharge() {
		return $this->intervalCharge;
	}

/**
	 * @return number
	 */
	public function getPreIntervalCharge() {
		return $this->preIntervalCharge;
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
	public function getIntervalPay() {
		return $this->intervalPay;
	}

	/**
	 * @return number
	 */
	public function getPreIntervalPay() {
		return $this->preIntervalPay;
	}

	/**
	 * @return number
	 */
	public function getIntervalWaiveAmt() {
		return $this->intervalWaiveAmt;
	}


	/**
	 * @return number
	 */
	public function getIntervalDiscount() {
		return $this->intervalDiscount;
	}

	/**
	 *
	 * @param number $charge
	 * @param number $fullCharge
	 * @param number $adjRatio
	 * @param string $rateCategory
	 */
	public function updateIntervalCharge($charge, $fullCharge, $adjRatio, $rateCategory) {
		
		$this->intervalCharge += $charge;
		
		// Adjust ratio
		if ($adjRatio > 0) {
			$this->fullIntervalCharge += ($fullCharge * $adjRatio);
		} else {
			$this->fullIntervalCharge += $fullCharge;
		}
		
		// Subsidy
		if ($rateCategory != RoomRateCategories::FlatRateCategory) {
			$this->subsidyCharge += $fullCharge - $charge;
		}
	}
	
	public function updatePreIntervalCharge($v) {
		$this->preIntervalCharge += $v;
	}
		
	public function updateIntervalPay($v) {
		$this->intervalPay += $v;
	}
	
	public function updatePreIntervalPay($v) {
		$this->preIntervalPay += $v;
	}
	
	public function updateIntervalWaiveAmt($v) {
		$this->intervalWaiveAmt += $v;
	}
	
	public function updatePreIntervalWaiveAmt($v) {
		$this->preIntervalWaiveAmt += $v;
	}
	
	public function updateIntervalDiscount($v) {
		$this->intervalDiscount += $v;
	}
	
	public function updatePreIntervalDiscount($v) {
		$this->preIntervalDiscount += $v;
	}
	
	
}