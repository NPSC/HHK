/**
 * visitState.js
 *
 * Shared checkout-state flag used by visitDialog.js, payments.js,
 * resvManager.js, and invoice.js.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   GPL and MIT
 * @link      https://github.com/NPSC/HHK
 */

let isCheckedOut = false;

export function getIsCheckedOut() {
  return isCheckedOut;
}

export function setIsCheckedOut(value) {
  isCheckedOut = value;
}
