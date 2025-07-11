<?php
namespace HHK\SysConst;

/**
 * NotificationStatus.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class NotificationStatus {
    public const string Sent = 's';
    public const string Bounced = 'b';
    public const string Undelivered = 'u';
    public const string Invalid = 'i';
    public const string Queued = 'q';
}
