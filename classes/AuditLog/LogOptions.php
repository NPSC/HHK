<?php
namespace HHK\AuditLog;

/**
 * LogOptions.php
 *
 * Per-table settings for the activity log written by EditRS (see HHK\Tables\Auditable).
 *
 * Method names mirror spatie/laravel-activitylog's LogOptions so a table's settings carry over
 * to a Laravel model's getActivitylogOptions() unchanged.  redact() is the one HHK addition.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class LogOptions {

    /** Columns that change on nearly every save and mean nothing on their own. */
    const DEFAULT_IGNORE = ['Last_Updated', 'Updated_By', 'Timestamp'];

    /** Columns whose values never go into the log. */
    const DEFAULT_REDACT = ['Enc_PW', 'Enc_Password', 'Password', 'secret', 'totpSecret', 'emailSecret', 'backupSecret', 'Token'];

    public bool $enabled = true;
    public string $logName = 'default';

    /** @var string[] empty = all columns */
    public array $logOnly = [];

    /** @var string[] */
    public array $logExcept = [];

    /** @var string[] */
    public array $ignoreChangedOnly = self::DEFAULT_IGNORE;

    /** @var string[] */
    public array $redact = self::DEFAULT_REDACT;

    public static function defaults(): self {
        return new self();
    }

    public function logAll(): self {
        $this->logOnly = [];
        return $this;
    }

    /** @param string[] $cols */
    public function logOnly(array $cols): self {
        $this->logOnly = $cols;
        return $this;
    }

    /** @param string[] $cols */
    public function logExcept(array $cols): self {
        $this->logExcept = array_merge($this->logExcept, $cols);
        return $this;
    }

    /**
     * An update that only changes these columns writes no log row.
     *
     * @param string[] $cols
     */
    public function dontLogIfAttributesChangedOnly(array $cols): self {
        $this->ignoreChangedOnly = array_merge($this->ignoreChangedOnly, $cols);
        return $this;
    }

    public function useLogName(string $logName): self {
        $this->logName = $logName;
        return $this;
    }

    public function disableLogging(): self {
        $this->enabled = false;
        return $this;
    }

    /**
     * Log these columns as '***'.
     *
     * @param string[] $cols
     */
    public function redact(array $cols): self {
        $this->redact = array_merge($this->redact, $cols);
        return $this;
    }

    /**
     * Is this column written to the log at all?
     */
    public function logsColumn(string $col): bool {
        if (count($this->logOnly) > 0 && !in_array($col, $this->logOnly)) {
            return false;
        }
        return !in_array($col, $this->logExcept);
    }

    public function isRedacted(string $col): bool {
        return in_array($col, $this->redact);
    }

    public function isIgnored(string $col): bool {
        return in_array($col, $this->ignoreChangedOnly);
    }
}
