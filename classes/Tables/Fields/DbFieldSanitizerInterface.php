<?php
namespace Tables\Fields;
/**
 * DbFieldSanitizer.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

interface DbFieldSanitizerInterface {
    public function sanitize($v);
    public function getDbType();
}
?>