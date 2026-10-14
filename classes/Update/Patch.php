<?php
namespace HHK\Update;

use HHK\HTMLControls\{HTMLTable, HTMLContainer};
use HHK\SysConst\CodeVersion;
use HHK\sec\Session;


/**
 * Patch.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of Patch
 *
 * @author Eric
 */
class Patch {

    public $oldVersion = '';
    public $newVersion = '';

    public array $results;

    public function __construct() {
        $this->results = [];
    }

    public function updateWithSqlStmts(\PDO $dbh, $tfile, $type = '', $delimiter = ';', $splitAt = ';') {

        $this->results = array();

        if ($tfile == '') {
            return $type . ' Filename is missing.  ';
        }

        $tquery = file_get_contents($tfile);

        $tresult = self::multiQueryPDO($dbh, $tquery, $delimiter, $splitAt);

        if (count($tresult) > 0) {

            foreach ($tresult as $err) {
                $this->results[$err['errno']] = $err;
            }

        } else {
            return $type . ' Successful<br/>';
        }
    }

    public static function multiQueryPDO(\PDO $dbh, $query, $delimiter = ";", $splitAt = ';') {

        $msg = [];

        if ($query === FALSE || trim($query) == '') {
            return $msg[] = array('error'=>'Empty query file ', 'errno'=> '', 'query'=> $query );
        }

        // the plain ';' case (every caller except the stored-routines file, which uses its own '-- ;' marker
        // instead, since a routine body is full of real semicolons that must not be split on at all) is split with
        // splitSqlStatements() instead of a bare explode() - see its own docblock for why
        $qParts = $splitAt === ';' ? self::splitSqlStatements($query) : explode($splitAt, $query);
        $q = '';

        try{
            $dbh->beginTransaction();

            foreach ($qParts as $q) {

                $q = trim($q);
                if ($q == '' || $q == $delimiter || $q == 'DELIMITER') {
                    continue;
                }

                $dbh->exec($q);
            }

            if ($dbh->inTransaction()) {
                $dbh->commit();
            }

        }catch(\Exception $e){

            $msg[] = array('error'=>$e->getMessage(), 'errno'=>$e->getCode(), 'query'=>$q);
            
            if($dbh->inTransaction()){
                $dbh->rollBack();
            }

        }

        return $msg;
    }

    /**
     * Split a SQL script into individual statements on ';', skipping any ';' that is inside a line comment ('--' or
     * '#'), a block comment, a quoted string ('...' or "...") or a backtick-quoted identifier (`...`) - so a
     * semicolon used as ordinary text in a comment, or as part of a value, is never mistaken for a statement
     * boundary. A plain explode(';', ...) (this method's only caller for every file except the stored-routines one,
     * which keeps its own '-- ;' marker instead - a routine body is full of real semicolons that must not be split
     * on at all, '-- ;' sidesteps that entirely rather than needing this to understand it) broke exactly this way
     * once a '--' comment happened to contain a ';' of its own: the comment, not actually ending the statement
     * there, still got cut in half, corrupting both the truncated statement and whatever followed.
     *
     * Backslash-escapes the next character inside a '...'/".." string (not inside a `...` identifier, where MySQL
     * has no such escaping); a doubled quote/backtick ('' / "" / ``) escapes itself and stays inside the same string
     * or identifier, per MySQL's own rules.
     *
     * @return string[] one entry per statement, in order, with any comments immediately preceding it still attached
     *                   (exactly as a plain explode(';', ...) would have grouped them) - never empty/whitespace-only
     */
    public static function splitSqlStatements(string $sql): array {
        $statements = [];
        $current = '';
        $len = strlen($sql);
        $state = 'normal'; // normal, single, double, backtick, lineComment, blockComment

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            if ($state === 'lineComment') {
                $current .= $ch;
                if ($ch === "\n") {
                    $state = 'normal';
                }
                continue;
            }

            if ($state === 'blockComment') {
                $current .= $ch;
                if ($ch === '*' && $next === '/') {
                    $current .= $next;
                    $i++;
                    $state = 'normal';
                }
                continue;
            }

            if ($state === 'single' || $state === 'double' || $state === 'backtick') {
                $quoteChar = $state === 'single' ? "'" : ($state === 'double' ? '"' : '`');

                if ($ch === '\\' && $state !== 'backtick') {
                    $current .= $ch . $next;
                    $i++;
                    continue;
                }

                if ($ch === $quoteChar) {
                    if ($next === $quoteChar) {
                        $current .= $ch . $next;
                        $i++;
                        continue;
                    }
                    $current .= $ch;
                    $state = 'normal';
                    continue;
                }

                $current .= $ch;
                continue;
            }

            // normal: '--' only starts a comment when followed by whitespace or end of file, matching MySQL's own rule
            if ($ch === '-' && $next === '-' && ($i + 2 >= $len || ctype_space($sql[$i + 2]))) {
                $current .= $ch . $next;
                $i++;
                $state = 'lineComment';
                continue;
            }
            if ($ch === '#') {
                $current .= $ch;
                $state = 'lineComment';
                continue;
            }
            if ($ch === '/' && $next === '*') {
                $current .= $ch . $next;
                $i++;
                $state = 'blockComment';
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $current .= $ch;
                $state = $ch === "'" ? 'single' : ($ch === '"' ? 'double' : 'backtick');
                continue;
            }
            if ($ch === ';') {
                $statements[] = $current;
                $current = '';
                continue;
            }

            $current .= $ch;
        }

        if (trim($current) !== '') {
            $statements[] = $current;
        }

        return $statements;
    }

    public static function patchTabMu() {

        $uS = Session::getInstance();

        // Database info
        $dbt = new HTMLTable();

        $dbt->addBodyTr(HTMLTable::makeTd('Database:', array('class' => 'tdlabel')) . HTMLTable::makeTd($uS->dbms));
        $dbt->addBodyTr(HTMLTable::makeTd('URL:', array('class' => 'tdlabel')) . HTMLTable::makeTd($uS->databaseURL));
        $dbt->addBodyTr(HTMLTable::makeTd('Schema:', array('class' => 'tdlabel')) . HTMLTable::makeTd($uS->databaseName));
        $dbt->addBodyTr(HTMLTable::makeTd('User:', array('class' => 'tdlabel')) . HTMLTable::makeTd($uS->databaseUName));

        $markup = HTMLContainer::generateMarkup('fieldset',
                HTMLContainer::generateMarkup('legend', 'DB Info', array('style'=>'font-weight:bold;'))
                . $dbt->generateMarkup(), array('style'=>'float:left; margin:5px;', 'class'=>'hhk-panel'));

        // Software info
        $tbl = new HTMLTable();

        $tbl->addBodyTr(
                HTMLTable::makeTd('Build:', array('class' => 'tdlabel'))
                . HTMLTable::makeTd(CodeVersion::BUILD));
        $tbl->addBodyTr(
                HTMLTable::makeTd('Version:', array('class' => 'tdlabel'))
                . HTMLTable::makeTd(CodeVersion::VERSION));
        $tbl->addBodyTr(
                HTMLTable::makeTd('Patch:', array('class' => 'tdlabel'))
                . HTMLTable::makeTd(CodeVersion::PATCH));
        $tbl->addBodyTr(
                HTMLTable::makeTd('Git Id:', array('class' => 'tdlabel'))
                . HTMLTable::makeTd(CodeVersion::GIT_Id));
        $tbl->addBodyTr(
                HTMLTable::makeTd('Release Date:', array('class' => 'tdlabel'))
                . HTMLTable::makeTd(CodeVersion::REL_DATE));

        $markup .= HTMLContainer::generateMarkup('fieldset',
            HTMLContainer::generateMarkup('legend', 'Software Version', array('style'=>'font-weight:bold;'))
            . $tbl->generateMarkup(), array('style'=>'float:left; margin:5px; margin-left:25px;', 'class'=>'hhk-panel'));


        // Contributors
//        $ctbl = new HTMLTable();
//
//        $contributors = array(
//            array("ML", "Eubanks"),
//            array("E", "Crane"),
//            array("K", "Lannan"),
//            array("R", "Chan"),
//            array("B", "VanderMeer"),
//            array("W", "Ireland"),
//            );
//
//        foreach ($contributors as $c) {
//
//            $ctbl->addBodyTr(
//                HTMLTable::makeTd($c[0], array('class' => 'tdlabel'))
//                . HTMLTable::makeTd($c[1]));
//
//        }
//
//        $markup .= HTMLContainer::generateMarkup('fieldset',
//                HTMLContainer::generateMarkup('legend', 'Major Contributors<br/>in order of Appearance', array('style'=>'font-weight:bold;'))
//                . $ctbl->generateMarkup(), array('style'=>'float:left; margin:5px; margin-left:25px;', 'class'=>'hhk-panel'));

        return HTMLContainer::generateMarkup('div', $markup, array());
    }

}

