<?php
namespace HHK\Admin;

use HHK\Common;
use HHK\House\ResourceBuilder;

/**
 * CategoryEditor.php
 *
 * Renames the categories inside a member category group.  Groups are gen_lookups
 * rows in the 'Vol_Category' table whose Code names another gen_lookups table
 * (e.g. 'Vol_Type' = Member Type).  Members are assigned categories in
 * name_volunteer2.  The Vol_ names are legacy; these are general member categories.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class CategoryEditor {

    public const GROUP_TABLE = 'Vol_Category';

    /** gen_lookups.Description is VARCHAR(255). */
    public const MAX_DESC_LENGTH = 255;

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

    /**
     * The categories in a group, with how many members are assigned to each.
     *
     * @return array<string,array{code:string, description:string, members:int}> keyed by code
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
            ];
        }
        return $cats;
    }

    /**
     * Validate a posted save against the group's current categories.
     *
     * Post shape: desc[code] => name.  Error keys are the posted field names, e.g. "desc[g]".
     *
     * @param array $existing result of load()
     * @return array{0: array<string,string>, 1: array<string,string>} [code => name, errors]
     */
    public static function validate(array $existing, array $post): array {

        $errors = [];
        $desc = [];
        $names = [];   // lower-cased, for duplicate checks

        $postDesc = is_array($post['desc'] ?? null) ? $post['desc'] : [];

        foreach ($existing as $code => $cat) {
            $code = (string) $code;
            $name = trim((string) ($postDesc[$code] ?? $cat['description']));

            if ($name === '') {
                $errors["desc[$code]"] = 'Name is required.';
            } else if (mb_strlen(filter_var($name, FILTER_SANITIZE_FULL_SPECIAL_CHARS)) > self::MAX_DESC_LENGTH) {
                // Stored HTML-encoded, so measure the encoded length.
                $errors["desc[$code]"] = 'Name must be ' . self::MAX_DESC_LENGTH . ' characters or fewer.';
            } else if (isset($names[mb_strtolower($name)])) {
                $errors["desc[$code]"] = 'Another category in this group already has this name.';
            }

            $names[mb_strtolower($name)] = true;
            $desc[$code] = $name;
        }

        return [$desc, $errors];
    }

    /**
     * Save validated names.
     *
     * @param array<string,string> $desc first element of validate()
     * @return string summary for the user
     */
    public static function save(\PDO $dbh, string $group, array $desc): string {

        ResourceBuilder::replaceGenLk($dbh, $group, $desc, null, null, null, null, null, null);

        return 'Categories saved.';
    }

    protected static function decode($v): string {
        return html_entity_decode((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
