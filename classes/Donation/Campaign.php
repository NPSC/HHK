<?php
namespace HHK\Donation;

use HHK\SysConst\CampaignType;
use HHK\Tables\Donate\CampaignRS;
use HHK\Tables\EditRS;
/**
 * Campaign.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class Campaign {

    /** Selector value for a campaign that hasn't been saved yet. */
    public const NEW_CODE = 'vNew';

    protected CampaignRS $campRS;


    public function __construct(\PDO $dbh, $campCode = "") {

        $campRS = new CampaignRS();
        $campRS->Campaign_Code->setStoredVal($campCode);
        $rows = EditRS::select($dbh, $campRS, array($campRS->Campaign_Code));

        if (count($rows) > 0) {
            EditRS::loadRow($rows[0], $campRS);
        }

        $this->campRS = $campRS;

    }

    /**
     * The campaign as the plain values the edit form displays.
     *
     * Text columns are stored HTML-encoded, so they are decoded here and
     * re-encoded on output by whoever renders them.
     */
    public function toArray(): array {

        $dec = fn($v) => html_entity_decode((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $amt = fn($v) => ((float) $v > 0 ? number_format((float) $v, 2, '.', '') : '');
        $date = fn($v, $fmt) => ($v ? date($fmt, strtotime($v)) : '');

        $exists = $this->get_idcampaign() > 0;

        return [
            'campCode'    => $exists ? $this->get_campaigncode() : self::NEW_CODE,
            'title'       => $dec($this->get_title()),
            'startDate'   => $date($this->get_startdate(), 'Y-m-d'),
            'endDate'     => $date($this->get_enddate(), 'Y-m-d'),
            'status'      => $exists ? (string) $this->get_status() : 'a',
            'type'        => ($exists && $this->get_type() != '') ? (string) $this->get_type() : CampaignType::Normal,
            'percent'     => ((float) $this->get_percentCut() > 0 ? (string) (float) $this->get_percentCut() : ''),
            'minDonation' => $amt($this->get_mindonation()),
            'maxDonation' => $amt($this->get_maxdonation()),
            'target'      => $amt($this->get_target()),
            'category'    => $dec($this->get_category()),
            'mergeCode'   => $dec($this->get_mergecode()),
            'description' => $dec($this->get_description()),
            'lastUpdated' => $date($this->get_lastupdated(), 'M j, Y g:i a'),
            'updatedBy'   => $exists ? (string) $this->get_updatedby() : '',
        ];
    }

    /**
     * Validate posted campaign form values.
     *
     * @param array $types    Campaign_Type gen_lookups keyed by code
     * @param array $statuses Campaign_Status gen_lookups keyed by code
     *
     * @return array{0: array, 1: array<string,string>} [clean values, errors keyed by field name]
     */
    public static function validate(array $in, array $types, array $statuses): array {

        $errors = [];
        $vals = [];

        $str = fn(string $k) => trim((string) ($in[$k] ?? ''));

        // Text fields are stored HTML-encoded (existing convention), so lengths are checked after encoding.
        $text = function (string $k, string $label, int $max, bool $required) use ($str, &$errors, &$vals) {
            $raw = $str($k);
            $enc = filter_var($raw, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if ($required && $raw === '') {
                $errors[$k] = "$label is required.";
            } else if (mb_strlen($enc) > $max) {
                $errors[$k] = "$label must be $max characters or fewer.";
            }
            $vals[$k] = $enc;
        };

        // Optional dollar amount: blank = 0, otherwise a non-negative number with up to 2 decimals.
        $amount = function (string $k, string $label) use ($str, &$errors, &$vals) {
            $raw = str_replace(['$', ',', ' '], '', $str($k));
            if ($raw === '') {
                $vals[$k] = 0;
            } else if (preg_match('/^\d{1,13}(\.\d{1,2})?$/', $raw) !== 1) {
                $errors[$k] = "$label must be a positive dollar amount, like 25 or 25.00.";
            } else {
                $vals[$k] = (float) $raw;
            }
        };

        $date = function (string $k, string $label) use ($str, &$errors): ?\DateTime {
            $raw = $str($k);
            if ($raw === '') {
                $errors[$k] = "$label is required.";
                return null;
            }
            $d = \DateTime::createFromFormat('!Y-m-d', $raw);
            $warn = \DateTime::getLastErrors();
            if ($d === false || ($warn !== false && ($warn['warning_count'] > 0 || $warn['error_count'] > 0))) {
                $errors[$k] = "$label is not a valid date.";
                return null;
            }
            return $d;
        };

        $text('title', 'Title', 250, true);
        $text('category', 'Category', 15, false);
        $text('mergeCode', 'Mail merge code', 15, false);
        $text('description', 'Description', 250, false);

        $start = $date('startDate', 'Start date');
        $end = $date('endDate', 'End date');
        if ($start && $end && $start > $end) {
            $errors['endDate'] = 'End date must be on or after the start date.';
        }
        $vals['startDate'] = $start ? $start->format('Y-m-d') : null;
        $vals['endDate'] = $end ? $end->format('Y-m-d') : null;

        $status = $str('status');
        if (!isset($statuses[$status])) {
            $errors['status'] = 'Choose a status.';
        }
        $vals['status'] = $status;

        $type = $str('type');
        if (!isset($types[$type])) {
            $errors['type'] = 'Choose a campaign type.';
        }
        $vals['type'] = $type;

        $amount('minDonation', 'Minimum donation');
        $amount('maxDonation', 'Maximum donation');
        $amount('target', 'Goal');

        if (!isset($errors['minDonation']) && !isset($errors['maxDonation'])
                && $vals['maxDonation'] > 0 && $vals['minDonation'] > $vals['maxDonation']) {
            $errors['maxDonation'] = 'Maximum donation must be greater than or equal to the minimum.';
        }

        // Percent only applies to percentage campaigns.
        $vals['percent'] = 0;
        if ($type === CampaignType::Percent) {
            $pct = str_replace(['%', ' '], '', $str('percent'));
            if (preg_match('/^\d{1,3}(\.\d{1,3})?$/', $pct) !== 1 || (float) $pct <= 0 || (float) $pct > 100) {
                $errors['percent'] = 'Enter a percentage greater than 0 and no more than 100, with up to 3 decimal places.';
            } else {
                $vals['percent'] = (float) $pct;
            }
        }

        return [$vals, $errors];
    }

    /**
     * Insert or update a campaign from validated values.
     *
     * @return string the campaign code
     */
    public static function saveCampaign(\PDO $dbh, string $campCode, array $v, string $userName): string {

        $campRS = new CampaignRS();
        $isNew = ($campCode === '' || $campCode === self::NEW_CODE);

        if (!$isNew) {
            $campRS->Campaign_Code->setStoredVal($campCode);
            $rows = EditRS::select($dbh, $campRS, [$campRS->Campaign_Code]);
            if (count($rows) === 0) {
                throw new \RuntimeException('That campaign was not found. It may have been deleted.');
            }
            EditRS::loadRow($rows[0], $campRS);
        }

        $campRS->Title->setNewVal($v['title']);
        $campRS->Start_Date->setNewVal($v['startDate']);
        $campRS->End_Date->setNewVal($v['endDate']);
        $campRS->Status->setNewVal($v['status']);
        $campRS->Campaign_Type->setNewVal($v['type']);
        $campRS->Percent_Cut->setNewVal($v['percent']);
        $campRS->Min_Donation->setNewVal($v['minDonation']);
        $campRS->Max_Donation->setNewVal($v['maxDonation']);
        $campRS->Target->setNewVal($v['target']);
        $campRS->Category->setNewVal($v['category']);
        $campRS->Campaign_Merge_Code->setNewVal($v['mergeCode']);
        $campRS->Description->setNewVal($v['description']);
        $campRS->Updated_By->setNewVal($userName);
        $campRS->Last_Updated->setNewVal(date('Y-m-d H:i:s'));

        if ($isNew) {

            $dbh->prepare("CALL IncrementCounter('codes', @num);")->execute();
            $rptId = (int) $dbh->query("SELECT @num")->fetchColumn();

            if ($rptId === 0) {
                throw new \RuntimeException('The campaign code counter is not set up.');
            }

            $campCode = 'cp' . $rptId;
            $campRS->Campaign_Code->setNewVal($campCode);
            EditRS::insert($dbh, $campRS);

        } else {
            EditRS::update($dbh, $campRS, [$campRS->Campaign_Code]);
        }

        return $campCode;
    }

    public static function CampaignSelOptionMarkup(\PDO $dbh, $campCode, $newOpt, $shoDisabled = true) {
        // Load the selector control with all the campaigns.
        $query = "";

        if ($shoDisabled) {
            $query = "Select Campaign_Code, Title, Status, Start_Date, End_Date from campaign";  // where Status='a' and (case when End_Date is null then now() else End_Date end) >= now()";
        } else {
            // Onlny show active campaigns
            $query = "Select Campaign_Code, Title, Status, Start_Date, End_Date from campaign where Status = 'a'";
        }
        $stmt = $dbh->query($query);
        $timedOut = "";
        $disOut = "";
        $currentOpt = "";
        $futureOpt = "";
        $CampOpt = "";

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row2) {

            $cCode = $row2["Campaign_Code"];
            $cTitle = $row2["Title"];
            $cStatus = trim($row2["Status"]);
            $cStart = strtotime($row2["Start_Date"]);
            $cEnd = strtotime($row2["End_Date"]);

            if ($campCode == $cCode) {
                $optString = "<option value='" . $cCode . "' selected='selected'>" . $cTitle . "</option>";
            } else {
                $optString = "<option value='" . $cCode . "'>" . $cTitle . "</option>";
            }


            if ($cStatus == 'a') {

                if (time() > $cEnd) {
                    $timedOut .= $optString;
                } else if (time() < $cStart) {
                    $futureOpt .= $optString;
                } else {
                    $currentOpt .= $optString;
                }
            } else {
                $disOut .= $optString;
            }
        }

        if ($timedOut != "") {
            $timedOut = "<optgroup label='Timed Out' class='cogTimedOut' >" . $timedOut . "</optgroup>";
        }

        if ($disOut != "") {
            $disOut = "<optgroup label='Disabled' class='cogDisabled' >" . $disOut . "</optgroup>";
        }

        if ($currentOpt != "") {
            $currentOpt = "<optgroup label='Current' class='cogCurrent' >" . $currentOpt . "</optgroup>";
        }

        if ($futureOpt != "") {
            $futureOpt = "<optgroup label='Future' class='cogFuture' >" . $futureOpt . "</optgroup>";
        }

        if ($newOpt) {
            if ($campCode == "") {
                $CampOpt = "<option value='" . self::NEW_CODE . "' selected='selected'>New</option>";
            } else {
                $CampOpt = "<option value='" . self::NEW_CODE . "'>New</option>";
            }
        }

        $CampOpt .= $currentOpt . $futureOpt . $timedOut . $disOut;
        // return just the options
        return $CampOpt;
    }

    public function get_percentCut() {
        return $this->campRS->Percent_Cut->getStoredVal();
    }

    public function set_percentCut(float $v) {
        $this->campRS->Percent_Cut->setNewVal($v);
    }

    public function get_type() {
        return $this->campRS->Campaign_Type->getStoredVal();
    }

    public function set_type($v) {
        $this->campRS->Campaign_Type->setNewVal($v);
    }

    public function get_idcampaign() {
        return $this->campRS->idcampaign->getStoredVal();
    }

    public function get_campaigncode() {
        return $this->campRS->Campaign_Code->getStoredVal();
    }

    public function set_campaigncode($v) {
        $this->campRS->Campaign_Code->setNewVal($v);
    }

    public function set_mergecode($v) {
        $this->campRS->Campaign_Merge_Code->setNewVal($v);
    }

    public function get_mergecode() {
        return $this->campRS->Campaign_Merge_Code->getStoredVal();
    }

    public function get_startdate() {
        return $this->campRS->Start_Date->getStoredVal();
    }

    public function set_startdate($v) {
        $this->campRS->Start_Date->setNewVal($v);
    }

    public function get_enddate() {
        return $this->campRS->End_Date->getStoredVal();
    }

    public function set_enddate($v) {
        $this->campRS->End_Date->setNewVal($v);
    }

    public function get_title() {
        return $this->campRS->Title->getStoredVal();
    }

    public function set_title($v) {
        $this->campRS->Title->setNewVal($v);
    }

    public function get_description() {
        return $this->campRS->Description->getStoredVal();
    }

    public function set_description($v) {
        $this->campRS->Description->setNewVal($v);
    }

    public function get_target() {
        return $this->campRS->Target->getStoredVal();
    }

    public function set_target($v) {
        $this->campRS->Target->setNewVal($v);
    }

    public function get_mindonation() {
        return $this->campRS->Min_Donation->getStoredVal();
    }

    public function get_mindonationString() {
        return number_format($this->get_mindonation(), 2, ".", ",");
    }

    public function set_mindonation($v) {
        $this->campRS->Min_Donation->setNewVal($v);
    }

    public function get_maxdonation() {
        return $this->campRS->Max_Donation->getStoredVal();
    }

    public function get_maxdonationString() {
        return number_format($this->get_maxdonation(), 2, ".", ",");
    }

    public function set_maxdonation($v) {
        $this->campRS->Max_Donation->setNewVal($v);
    }

    public function get_category() {
        return $this->campRS->Category->getStoredVal();
    }

    public function set_category($v) {
        $this->campRS->Category->setNewVal($v);
    }

    public function get_status() {
        return $this->campRS->Status->getStoredVal();
    }

    public function set_status($v) {
        $this->campRS->Status->setNewVal($v);
    }

    public function get_lastupdated() {
        return $this->campRS->Last_Updated->getStoredVal();
    }

    public function set_lastupdated($v) {
        $this->campRS->Last_Updated->setNewVal($v);
    }

    public function get_updatedby() {
        return $this->campRS->Updated_By->getStoredVal();
    }

    public function set_updatedby($v) {
        $this->campRS->Updated_By->setNewVal($v);
    }

    public function isAmountValid(float $amt): bool {

        if ($this->get_maxdonation() == 0 && $this->get_mindonation() == 0) {
            return TRUE;

        } else if ($this->get_maxdonation() == 0 && $amt - $this->get_mindonation() >= 0) {
            return TRUE;

        } else if ($amt - $this->get_mindonation() >= 0 && ($this->get_maxdonation() - $amt) >= 0) {
            return TRUE;
        }
        return FALSE;
    }

}
