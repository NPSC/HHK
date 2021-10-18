<?php

namespace HHK\Member\RoleMember;

use HHK\SysConst\VolMemberType;

/**
 * RoleMember.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of RoleMember
 *
 * @author Eric
 */

class DoctorMember extends AbstractRoleMember {
    
    protected function getMyMemberType() {
        return VolMemberType::Doctor;
    }
    
}
?>