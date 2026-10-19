<?php

namespace HHK\Cron;

use HHK\Exception\RuntimeException;
use HHK\Tables\EditRS;

/**
 * PurgeAuditLogJob.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Deletes audit_log rows older than the retention period, keeping a rollup checkpoint for each
 * record that still existed then (see Auditable::purgeActivityBefore()).  No job scheduled = keep forever.
 */
class PurgeAuditLogJob extends AbstractJob implements JobInterface {

    public array $paramTemplate = [
        "retentionMonths" => [
            "label" => "Keep Months",
            "type" => "number",
            "defaultVal" => "36",
            "min" => 1,
            "required" => true
        ],
    ];

    public function tasks(): void {

        $months = intval($this->params["retentionMonths"] ?? 0);

        if ($months < 1) {
            throw new RuntimeException("Keep Months must be at least 1");
        }

        $cutoff = (new \DateTime())->modify("-$months months")->format("Y-m-d H:i:s");

        $count = EditRS::purgeActivityBefore($this->dbh, $cutoff, 5000, $this->dryRun);

        $this->logMsg = ($this->dryRun ? "Would roll up " : "Rolled up ") . $count . " records older than " . $cutoff;
    }
}
