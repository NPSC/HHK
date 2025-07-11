<?php
namespace HHK\SysConst;

/**
 * TransType.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class TransType {
    public const string Sale = 's';
    public const string Void = 'vs';
    public const string Retrn = 'r';
    public const string VoidReturn = 'vr';
    public const string Reverse = 'rv';
    public const string undoRetrn = 'ur';
    public const string ZeroAuth = 'za';
}
