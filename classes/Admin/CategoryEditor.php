<?php
namespace HHK\Admin;

use HHK\Common;
use HHK\House\ResourceBldr;
use HHK\SysConst\VolMemberType;
use HHK\TableLog\HouseLog;
use HHK\Tables\EditRS;
use HHK\Tables\GenLookupsRS;
use HHK\Tables\Name\NameVolunteerRS;

/**
 * CategoryEditor.php
 *
 * Edits the categories inside a member category group.  Groups are gen_lookups
 * rows in the 'Vol_Category' table whose Code names another gen_lookups table
 * (e.g. 'Vol_Type' = Member Type).  Members are assigned categories in
 * name_volunteer2.  The Vol_ names are legacy; these are general member categories.
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class CategoryEditor {

    public const GROUP_TABLE = 'Vol_Category';

    /** New category codes are this prefix plus the 'codes' counter. */
    public const CODE_PREFIX = 'v';

    /** name_volunteer2.Vol_Code is VARCHAR(5). */
    public const MAX_CODE_LENGTH = 5;

    /** gen_lookups.Description is VARCHAR(255). */
    public const MAX_DESC_LENGTH = 255;

    /**
     * Codes the application looks up by value (VolMemberType, KeyStats).
     * They can be renamed but not deleted.
     */
    public const PROTECTED_CODES = [
        VolMemberType::VolCategoryCode => [
            VolMemberType::Guest,
            VolMemberType::Patient,
            VolMemberType::Donor,
            VolMemberType::ReferralAgent,
            VolMemberType::Doctor,
            VolMemberType::BillingAgent,
            'Vol',
        ],
    ];

    /**
     * @return array<string,string> group code => name
     */
    public static function groups(\PDO $dbh): array {

        $groups = [];
        foreach (Common::readGenLookupsPDO($dbh, self::GROUP_TABLE, 'Description') as $code => $row) {
            $groups[(string) $code] = self::decode($row['Description']);
        }
        return $groups;
    }

    public static function isProtected(string $group, string $code): bool {
        return in_array($code, self::PROTECTED_CODES[$group] ?? [], true);
    }

    /**
     * The categories in a group, with how many members are assigned to each.
     *
     * @return array<string,array{code:string, description:string, members:int, builtIn:bool}> keyed by code
     */
    public static function load(\PDO $dbh, string $group): array {

        $stmt = $dbh->prepare("SELECT `g`.`Code`, `g`.`Description`, COUNT(`nv`.`idName`) AS `Members`
            FROM `gen_lookups` `g`
            LEFT JOIN `name_volunteer2` `nv` ON `nv`.`Vol_Category` = `g`.`Table_Name` AND `nv`.`Vol_Code` = `g`.`Code`
            WHERE `g`.`Table_Name` = :grp
            GROUP BY `g`.`Code`, `g`.`Description`
            ORDER BY `g`.`Description`;");
        $stmt->execute([':grp' => $group]);

        $cats = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r) {
            $code = (string) $r['Code'];
            $cats[$code] = [
                'code' => $code,
                'description' => self::decode($r['Description']),
                'members' => (int) $r['Members'],
                'builtIn' => self::isProtected($group, $code),
            ];
        }
        return $cats;
    }

    /**
     * Validate a posted save against the group's current categories.
     *
     * Post shape: desc[code] => name, del[code] => 1, repl[code] => code or '', newDesc => name.
     * Error keys are the posted field names, e.g. "desc[g]", "repl[v123]", "newDesc".
     *
     * @param array $existing result of load()
     * @return array{0: array{desc: array<string,string>, del: array<string,bool>, repl: array<string,string>, newDesc: string}, 1: array<string,string>}
     */
    public static function validate(array $existing, array $post): array {

        $errors = [];
        $desc = [];
        $del = [];
        $repl = [];

        $postDesc = is_array($post['desc'] ?? null) ? $post['desc'] : [];
        $postDel = is_array($post['del'] ?? null) ? $post['del'] : [];
        $postRepl = is_array($post['repl'] ?? null) ? $post['repl'] : [];

        // Stored HTML-encoded, so measure the encoded length.
        $tooLong = fn(string $s) => mb_strlen(filter_var($s, FILTER_SANITIZE_FULL_SPECIAL_CHARS)) > self::MAX_DESC_LENGTH;

        // Deletions first, so the replacement checks can see them.
        foreach ($existing as $code => $cat) {
            $code = (string) $code;
            if (!empty($postDel[$code])) {
                if ($cat['builtIn']) {
                    $errors["del[$code]"] = 'This category is built in and cannot be deleted.';
                } else {
                    $del[$code] = true;
                }
            }
        }

        // Names in use after this save, lower-cased, for duplicate checks.
        $names = [];

        foreach ($existing as $code => $cat) {
            $code = (string) $code;

            if (isset($del[$code])) {
                $r = trim((string) ($postRepl[$code] ?? ''));
                if ($r !== '') {
                    if (!isset($existing[$r])) {
                        $errors["repl[$code]"] = 'Choose a category from this group.';
                    } else if ($r === $code) {
                        $errors["repl[$code]"] = 'A category cannot replace itself.';
                    } else if (isset($del[$r])) {
                        $errors["repl[$code]"] = 'The replacement is also being deleted.';
                    }
                }
                $repl[$code] = $r;
                $desc[$code] = $cat['description'];   // replaceGenLk walks the desc keys to find deletions
                continue;
            }

            $name = trim((string) ($postDesc[$code] ?? $cat['description']));

            if ($name === '') {
                $errors["desc[$code]"] = 'Name is required.';
            } else if ($tooLong($name)) {
                $errors["desc[$code]"] = 'Name must be ' . self::MAX_DESC_LENGTH . ' characters or fewer.';
            } else if (isset($names[mb_strtolower($name)])) {
                $errors["desc[$code]"] = 'Another category in this group already has this name.';
            }

            $names[mb_strtolower($name)] = true;
            $desc[$code] = $name;
        }

        $newDesc = trim((string) ($post['newDesc'] ?? ''));
        if ($newDesc !== '') {
            if ($tooLong($newDesc)) {
                $errors['newDesc'] = 'Name must be ' . self::MAX_DESC_LENGTH . ' characters or fewer.';
            } else if (isset($names[mb_strtolower($newDesc)])) {
                $errors['newDesc'] = 'A category in this group already has this name.';
            }
        }

        return [['desc' => $desc, 'del' => $del, 'repl' => $repl, 'newDesc' => $newDesc], $errors];
    }

    /**
     * Apply a validated save in one transaction.
     *
     * @param array $v first element of validate()
     * @return string summary for the user
     */
    public static function save(\PDO $dbh, string $group, array $v, string $userName): string {

        $messages = [];
        $dbh->beginTransaction();

        try {
            if ($v['newDesc'] !== '') {
                self::insertCategory($dbh, $group, $v['newDesc'], $userName);
                $messages[] = 'Category added.';
            }

            // replaceGenLk calls this for each deleted code before removing its gen_lookups row.
            $replace = fn($dbh, $newCode, $oldCode, $tbl) =>
                self::reassignMembers($dbh, (string) $tbl, (string) $oldCode, (string) $newCode, $userName);

            $changed = ResourceBldr::replaceGenLk($dbh, $group, $v['desc'], null, null, null, $v['del'], $replace, $v['repl']);

            $nDel = count($v['del']);
            if ($nDel > 0) {
                $messages[] = $nDel . ($nDel === 1 ? ' category' : ' categories') . ' deleted'
                    . ($changed > 0 ? "; $changed member assignment" . ($changed === 1 ? '' : 's') . ' changed.' : '.');
            }

            $dbh->commit();

        } catch (\Throwable $ex) {
            if ($dbh->inTransaction()) {
                $dbh->rollBack();
            }
            throw $ex;
        }

        return count($messages) > 0 ? implode(' ', $messages) : 'Categories saved.';
    }

    /**
     * Move every member from one category to another, or remove the assignments
     * when $newCode is ''.  A member who already has the new category just loses the old one.
     *
     * @return int number of member assignments changed or removed
     */
    public static function reassignMembers(\PDO $dbh, string $group, string $oldCode, string $newCode, string $userName): int {

        $nvRs = new NameVolunteerRS();
        $nvRs->Vol_Category->setStoredVal($group);
        $nvRs->Vol_Code->setStoredVal($oldCode);
        $rows = EditRS::select($dbh, $nvRs, [$nvRs->Vol_Category, $nvRs->Vol_Code]);

        $count = 0;

        foreach ($rows as $r) {

            $oldRs = new NameVolunteerRS();
            EditRS::loadRow($r, $oldRs);
            $key = [$oldRs->idName, $oldRs->Vol_Category, $oldRs->Vol_Code];

            $hasNew = false;
            if ($newCode !== '') {
                $chk = new NameVolunteerRS();
                $chk->idName->setStoredVal($oldRs->idName->getStoredVal());
                $chk->Vol_Category->setStoredVal($group);
                $chk->Vol_Code->setStoredVal($newCode);
                $hasNew = count(EditRS::select($dbh, $chk, [$chk->idName, $chk->Vol_Category, $chk->Vol_Code])) > 0;
            }

            if ($newCode === '' || $hasNew) {
                EditRS::delete($dbh, $oldRs, $key);
            } else {
                $oldRs->Vol_Code->setNewVal($newCode);
                $oldRs->Updated_By->setNewVal($userName);
                $oldRs->Last_Updated->setNewVal(date('Y-m-d H:i:s'));
                EditRS::update($dbh, $oldRs, $key);
            }
            $count++;
        }

        if ($count > 0) {
            $text = ($newCode === '')
                ? "Removed $count member assignment(s) from category $oldCode."
                : "Moved $count member assignment(s) from category $oldCode to $newCode.";
            HouseLog::logGenLookups($dbh, $group, $oldCode, $text, 'update', $userName);
        }

        return $count;
    }

    protected static function insertCategory(\PDO $dbh, string $group, string $desc, string $userName): string {

        $code = self::CODE_PREFIX . Common::incCounter($dbh, 'codes');

        if (strlen($code) > self::MAX_CODE_LENGTH) {
            throw new \RuntimeException("The new category code '$code' is longer than member records can hold ("
                . self::MAX_CODE_LENGTH . ' characters).');
        }

        $glRs = new GenLookupsRS();
        $glRs->Table_Name->setNewVal($group);
        $glRs->Code->setNewVal($code);
        $glRs->Description->setNewVal(filter_var($desc, FILTER_SANITIZE_FULL_SPECIAL_CHARS));
        $glRs->Substitute->setNewVal('');
        EditRS::insert($dbh, $glRs);

        HouseLog::logGenLookups($dbh, $group, $code, HouseLog::getInsertText($glRs), 'insert', $userName);

        return $code;
    }

    protected static function decode($v): string {
        return html_entity_decode((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
