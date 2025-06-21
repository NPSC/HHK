<?php
namespace HHK\Tables\Fields;
/**
 * DbFieldSanitizer.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

interface DbFieldSanitizerInterface {
    /**
     * Summary of sanitize
     * @param mixed $v
     * @return mixed
     */
    public function sanitize($v);
    /**
     * Summary of getDbType
     * @return mixed
     */
    public function getDbType();
}
