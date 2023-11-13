<?php

namespace HHK\Member\RoleMember;

use HHK\SysConst\VolMemberType;

/**
 * AgentMember.php
 *
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2017 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Description of AgentMember
 *
 * @author Eric
 */

class AgentMember extends AbstractRoleMember {

    /**
     * Summary of getMyMemberType
     * @return string
     */
    protected function getMyMemberType() {
        return VolMemberType::ReferralAgent;
    }

}
?>