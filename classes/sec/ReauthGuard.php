<?php
namespace HHK\sec;

use HHK\Exception\AuthException;
use HHK\sec\MFA\Email;

/**
 * ReauthGuard.php
 *
 * Step-up re-authentication for sensitive areas.  Before using one, the user re-proves their
 * identity: local users with their password, SSO users with a two step verification code
 * (authenticator app, emailed code or backup code) set up in User Settings.  SSO users can't
 * re-enter a password, and IdPs such as Google ignore SAML ForceAuthn, so their own two step
 * verification is the second proof.
 *
 * A grant for that purpose is then recorded in their session; it lasts while the user keeps
 * working (each guarded action extends it) and lapses after a quiet period.  Guarded endpoints
 * call check() and refuse the action without a grant.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ReauthGuard
{
    /** Web Account dialog on NameEdit: web status, user name, role, security groups, password resets */
    const PURPOSE_WEB_USER = 'webuser';

    /** Page whose users can open the Web Account dialog */
    const WEB_USER_PAGE = 'NameEdit.php';

    /** Changing your own two step verification, once it is set up.  Otherwise whoever finds an
     *  unattended session could swap in their own authenticator and pass re-authentication with it. */
    const PURPOSE_MFA = 'mfa';

    /** Seconds of inactivity after which a grant lapses, per purpose */
    const GRANT_TTL = [
        self::PURPOSE_WEB_USER => 900,
        self::PURPOSE_MFA => 300,
    ];

    /** Web_User_Actions codes */
    const LOG_VERIFIED = 'RA';
    const LOG_FAILED = 'RAF';

    /**
     * Is the current session allowed to use (and so re-authenticate for) this purpose?
     * @param \PDO $dbh
     * @param string $purpose
     * @return bool
     */
    public static function isAuthorizedFor(\PDO $dbh, string $purpose): bool
    {
        $uS = Session::getInstance();

        return match ($purpose) {
            self::PURPOSE_WEB_USER => SecurityComponent::is_Admin() || self::isPageAuthorized($dbh, self::WEB_USER_PAGE),
            self::PURPOSE_MFA => $uS->logged === true && !empty($uS->username),
            default => false,
        };
    }

    /**
     * Do the user's security groups allow this page?  Reads the page table rather than
     * SecurityComponent::is_Authorized(), whose page list only holds the site last loaded.
     * @param \PDO $dbh
     * @param string $fileName
     * @return bool
     */
    protected static function isPageAuthorized(\PDO $dbh, string $fileName): bool
    {
        $stmt = $dbh->prepare("select ps.`Group_Code` from `page` p join `page_securitygroup` ps on p.`idPage` = ps.`idPage` where p.`File_Name` = :fileName");
        $stmt->execute([':fileName' => $fileName]);

        return SecurityComponent::does_User_Code_Match($stmt->fetchAll(\PDO::FETCH_COLUMN));
    }

    /**
     * What the client needs to know to show the re-authentication dialog.
     *
     * method is 'password' for local users; for SSO users 'otp' (with otpMethods, default first),
     * or 'otp-setup' if they have not set up two step verification yet.
     *
     * @param \PDO $dbh
     * @param string $purpose
     * @throws AuthException
     * @return array{granted: bool, expiresIn: int, method: string, otpMethods: array}
     */
    public static function status(\PDO $dbh, string $purpose): array
    {
        if (!self::isAuthorizedFor($dbh, $purpose)) {
            throw new AuthException('You are not authorized to perform this action.');
        }

        $uS = Session::getInstance();
        $user = UserClass::getUserCredentials($dbh, $uS->username);
        $otpMethods = (empty($user['idIdp']) ? [] : UserClass::getOtpMethods($user));

        return [
            'granted' => self::isGranted($purpose),
            'expiresIn' => self::expiresIn($purpose),
            'method' => (empty($user['idIdp']) ? 'password' : (count($otpMethods) > 0 ? 'otp' : 'otp-setup')),
            'otpMethods' => $otpMethods,
        ];
    }

    /**
     * Re-authenticate: local users with their password, SSO users with a two step verification code.
     * @param \PDO $dbh
     * @param string $purpose
     * @param string $secret The password or code
     * @param string $otpMethod authenticator, email or backup, for SSO users
     * @throws AuthException
     */
    public static function verify(\PDO $dbh, string $purpose, string $secret, string $otpMethod = ''): void
    {
        $uS = Session::getInstance();

        if (!self::isAuthorizedFor($dbh, $purpose)) {
            throw new AuthException('You are not authorized to perform this action.');
        }

        $u = new UserClass();
        $ok = (UserClass::isLocalUser($dbh, $uS, $uS->username)
            ? $u->verifyPassword($dbh, $uS->username, $secret)
            : $u->verifyOtp($dbh, $uS->username, $otpMethod, $secret));

        if (!$ok) {
            UserClass::insertUserLog($dbh, self::LOG_FAILED, $uS->username);
            throw new AuthException($u->logMessage);
        }

        self::grant($purpose);
        UserClass::insertUserLog($dbh, self::LOG_VERIFIED, $uS->username);
    }

    /**
     * Email a two step verification code to an SSO user who set up email codes.
     * @param \PDO $dbh
     * @param string $purpose
     * @throws AuthException
     */
    public static function sendEmailCode(\PDO $dbh, string $purpose): void
    {
        $uS = Session::getInstance();

        if (!self::isAuthorizedFor($dbh, $purpose)) {
            throw new AuthException('You are not authorized to perform this action.');
        }

        $user = UserClass::getUserCredentials($dbh, $uS->username);

        if (empty($user['emailSecret'])) {
            throw new AuthException('Email verification codes are not set up for your account.');
        }

        (new Email($user))->sendCode($dbh);
    }

    /**
     * For guarded endpoints: is there a live grant?  Using it extends it.
     * @param string $purpose
     * @return bool
     */
    public static function check(string $purpose): bool
    {
        if (!self::isGranted($purpose)) {
            return false;
        }

        self::grant($purpose);
        return true;
    }

    /**
     * Response for a guarded endpoint called without a grant.  The client re-authenticates and retries.
     * @param string $purpose
     * @return array
     */
    public static function requiredResponse(string $purpose): array
    {
        return ['error' => 'Please verify your identity and try again.', 'reauth' => $purpose];
    }

    /**
     * @param string $purpose
     */
    public static function grant(string $purpose): void
    {
        $uS = Session::getInstance();
        $grants = is_array($uS->reauthGrants) ? $uS->reauthGrants : [];
        $grants[$purpose] = ['uid' => intval($uS->uid), 'at' => time()];
        $uS->reauthGrants = $grants;
    }

    /**
     * @param string $purpose
     * @return bool
     */
    public static function isGranted(string $purpose): bool
    {
        return self::expiresIn($purpose) > 0;
    }

    /**
     * Seconds left on the grant, 0 if there is none.
     * @param string $purpose
     * @return int
     */
    public static function expiresIn(string $purpose): int
    {
        $uS = Session::getInstance();
        $grant = (is_array($uS->reauthGrants) ? ($uS->reauthGrants[$purpose] ?? null) : null);

        if (!is_array($grant) || $grant['uid'] != $uS->uid || !isset(self::GRANT_TTL[$purpose])) {
            return 0;
        }

        return max(0, self::GRANT_TTL[$purpose] - (time() - $grant['at']));
    }

    /**
     * @param string $purpose
     */
    public static function revoke(string $purpose): void
    {
        $uS = Session::getInstance();
        if (is_array($uS->reauthGrants)) {
            $grants = $uS->reauthGrants;
            unset($grants[$purpose]);
            $uS->reauthGrants = $grants;
        }
    }
}
