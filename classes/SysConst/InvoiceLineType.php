<?php
namespace HHK\SysConst;

/**
 * InvoiceLineType.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class InvoiceLineType {
    const Recurring = 1;
    const Tax = 2;
    const OneTime = 6;
    const Invoice = 3;
    const Hold = 4;
    const Reimburse = 7;
    const Waive = 5;
}
?>