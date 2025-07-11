<?php
namespace HHK\SysConst;

/**
 * InvoiceLineType.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class InvoiceLineType {
    public const int Recurring = 1;
    public const int Tax = 2;
    public const int OneTime = 6;
    public const int Invoice = 3;
    public const int Hold = 4;
    public const int Reimburse = 7;
    public const int Waive = 5;
}
