<?php
namespace HHK\sec;

use HHK\Exception\AuthException;
use OneLogin\Saml2\Constants;

/**
 * ReauthGuard.php
 *
 * Step-up re-authentication for sensitive areas.  Before using one, the user re-proves their
 * identity: local users with their password, SSO users with a forced (ForceAuthn) sign in at
 * their IdP.  A grant for that purpose is then recorded in their session; it lasts while the
 * user keeps working (each guarded action extends it) and lapses after a quiet period.
 * Guarded endpoints call check() and refuse the action without a grant.
 *
 * SSO detail: the IdP returns the response with a cross-site POST to the ACS.  The session
 * cookie has no SameSite attribute, so browsers that default to Lax leave it off that POST,
 * and starting a session there would replace the user's session cookie and log them out.
 * So the ACS first answers a re-authentication response without any session (relayResponse),
 * re-posting it to itself from our own origin; that same-site POST carries the session
 * cookie, and completeSso() validates it against the pending request stored in the session.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class ReauthGuard
{
    /** Web Account dialog on NameEdit: user name, role, security groups, password resets */
    const PURPOSE_WEB_USER = 'webuser';

    /** Seconds of inactivity after which a grant lapses, per purpose */
    const GRANT_TTL = [
        self::PURPOSE_WEB_USER => 900,
    ];

    /** Seconds we wait for the IdP to answer a re-authentication request */
    const PENDING_TTL = 600;

    /** RelayState sent with the AuthnRequest so the ACS can tell a re-authentication response from a login */
    const RELAY_STATE = 'hhk-reauth';

    /** Field added when the ACS re-posts a re-authentication response to itself */
    const RELAY_FIELD = 'hhkReauthRelay';

    /** BroadcastChannel the SSO result page announces on */
    const CHANNEL = 'hhk-reauth';

    /** Web_User_Actions codes */
    const LOG_VERIFIED = 'RA';
    const LOG_FAILED = 'RAF';

    /**
     * Is the current session allowed to use (and so re-authenticate for) this purpose?
     * @param string $purpose
     * @return bool
     */
    public static function isAuthorizedFor(string $purpose): bool
    {
        return match ($purpose) {
            self::PURPOSE_WEB_USER => SecurityComponent::is_Admin() || SecurityComponent::is_Authorized("ws_gen_Maint"),
            default => false,
        };
    }

    /**
     * Is the current session user an SSO user (and so re-authenticates with their IdP)?
     * @param \PDO $dbh
     * @return bool
     */
    public static function isSsoUser(\PDO $dbh): bool
    {
        $uS = Session::getInstance();
        return !empty($uS->username) && UserClass::isLocalUser($dbh, $uS, $uS->username) === FALSE;
    }

    /**
     * What the client needs to know to show the re-authentication dialog.
     * @param \PDO $dbh
     * @param string $purpose
     * @throws AuthException
     * @return array{granted: bool, expiresIn: int, method: string, idpName: string}
     */
    public static function status(\PDO $dbh, string $purpose): array
    {
        if (!self::isAuthorizedFor($purpose)) {
            throw new AuthException('You are not authorized to perform this action.');
        }

        $uS = Session::getInstance();
        $user = UserClass::getUserCredentials($dbh, $uS->username);
        $isSso = !empty($user['idIdp']);

        return [
            'granted' => self::isGranted($purpose),
            'expiresIn' => self::expiresIn($purpose),
            'method' => ($isSso ? 'sso' : 'password'),
            'idpName' => ($isSso ? ($user['authProvider'] ?? '') : ''),
        ];
    }

    /**
     * Re-authenticate a local user with their password.
     * @param \PDO $dbh
     * @param string $purpose
     * @param string $password
     * @throws AuthException
     */
    public static function verifyPassword(\PDO $dbh, string $purpose, string $password): void
    {
        $uS = Session::getInstance();

        if (!self::isAuthorizedFor($purpose)) {
            throw new AuthException('You are not authorized to perform this action.');
        }

        if (self::isSsoUser($dbh)) {
            throw new AuthException('Your account signs in with an SSO provider.  Verify with your SSO provider instead.');
        }

        $u = new UserClass();
        if (!$u->verifyPassword($dbh, $uS->username, $password)) {
            UserClass::insertUserLog($dbh, self::LOG_FAILED, $uS->username);
            throw new AuthException($u->logMessage);
        }

        self::grant($purpose);
        UserClass::insertUserLog($dbh, self::LOG_VERIFIED, $uS->username);
    }

    /**
     * Begin an SSO re-authentication.
     * @param \PDO $dbh
     * @param string $purpose
     * @throws AuthException
     * @return string The IdP URL to send the browser to
     */
    public static function startSso(\PDO $dbh, string $purpose): string
    {
        $uS = Session::getInstance();

        if (!self::isAuthorizedFor($purpose)) {
            throw new AuthException('You are not authorized to perform this action.');
        }

        $user = UserClass::getUserCredentials($dbh, $uS->username);

        if (empty($user['idIdp'])) {
            throw new AuthException('Your account does not sign in with an SSO provider.');
        }

        // Use the user's own IdP even if it is hidden from the login page
        $saml = new SAML($dbh, $user['idIdp']);
        $request = $saml->startReauth(self::RELAY_STATE);

        $uS->reauthPending = [
            'requestId' => $request['requestId'],
            'idIdp' => intval($user['idIdp']),
            'purpose' => $purpose,
            'uid' => intval($uS->uid),
            'at' => time(),
        ];

        return $request['url'];
    }

    /**
     * Is this request the IdP's cross-site POST of a re-authentication response?  Checked before
     * any session is started.
     * @param string $cmd
     * @return bool
     */
    public static function isRelayRequest(string $cmd): bool
    {
        return $cmd === 'acs'
            && isset($_POST['SAMLResponse'])
            && ($_POST['RelayState'] ?? '') === self::RELAY_STATE
            && !isset($_POST[self::RELAY_FIELD]);
    }

    /**
     * Is this request our own same-site re-post of a re-authentication response?
     * @return bool
     */
    public static function isRelayedResponse(): bool
    {
        return isset($_POST['SAMLResponse'], $_POST[self::RELAY_FIELD]);
    }

    /**
     * Re-post the SAML response to the same URL from our own origin, without starting a session.
     * @return never
     */
    public static function relayResponse(): never
    {
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');

        $fields = [
            'SAMLResponse' => $_POST['SAMLResponse'],
            'RelayState' => self::RELAY_STATE,
            self::RELAY_FIELD => '1',
        ];

        $inputs = '';
        foreach ($fields as $name => $value) {
            $inputs .= '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '">';
        }

        // No action attribute: post back to this same URL so the response Destination still matches
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Verifying...</title></head>'
            . '<body onload="document.forms[0].submit()"><form method="post">' . $inputs
            . '<noscript><button type="submit">Continue</button></noscript></form></body></html>';
        exit();
    }

    /**
     * Validate the relayed SAML response against the pending request and record the grant.
     * @param \PDO $dbh
     * @param int $idpId The idpId the response was posted to
     * @throws AuthException
     * @return string The purpose that was granted
     */
    public static function completeSso(\PDO $dbh, int $idpId): string
    {
        $uS = Session::getInstance();

        // One response per request, pass or fail
        $pending = $uS->reauthPending;
        unset($uS->reauthPending);

        if (!is_array($pending) || time() - $pending['at'] > self::PENDING_TTL) {
            throw new AuthException('No identity verification is in progress, or it has expired.  Please try again.');
        }

        try {
            if ($pending['uid'] != $uS->uid || $pending['idIdp'] != $idpId) {
                throw new AuthException('This verification does not belong to the signed in user.');
            }

            $saml = new SAML($dbh, $pending['idIdp']);
            $result = $saml->processReauthResponse($pending['requestId']);

            if (strcasecmp($result['nameId'], $uS->username) !== 0) {
                throw new AuthException('You verified as a different user (' . $result['nameId'] . ').  Please verify as ' . $uS->username . '.');
            }

            // Some IdPs ignore ForceAuthn and answer from their existing session.  Only accept a
            // sign in that happened after we asked for one.
            if ($result['authnInstant'] === null || $result['authnInstant'] < $pending['at'] - Constants::ALLOWED_CLOCK_DRIFT) {
                throw new AuthException('Your SSO provider did not ask you to sign in again, so your identity could not be verified.');
            }

        } catch (\Exception $ex) {
            UserClass::insertUserLog($dbh, self::LOG_FAILED, $uS->username);
            throw $ex;
        }

        self::grant($pending['purpose']);
        UserClass::insertUserLog($dbh, self::LOG_VERIFIED, $uS->username);

        return $pending['purpose'];
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

    /**
     * Page shown in the SSO verification popup when it finishes.  Tells the page that opened it,
     * then closes on success.
     * @param bool $ok
     * @param string $purpose
     * @param string $message
     * @return never
     */
    public static function renderResult(bool $ok, string $purpose, string $message): never
    {
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');

        $msg = json_encode(['type' => self::CHANNEL, 'ok' => $ok, 'purpose' => $purpose, 'message' => $message], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $channel = json_encode(self::CHANNEL);

        // BroadcastChannel still works when the IdP's Cross-Origin-Opener-Policy has cut window.opener
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Identity Verification</title></head>'
            . '<body style="font-family: sans-serif; padding: 1em;"><p>' . htmlspecialchars($message) . '</p>'
            . '<p>' . ($ok ? 'This window will close.' : 'You can close this window.') . '</p>'
            . '<script>(function(){var msg=' . $msg . ';'
            . 'try{new BroadcastChannel(' . $channel . ').postMessage(msg);}catch(e){}'
            . 'try{if(window.opener){window.opener.postMessage(msg, window.location.origin);}}catch(e){}'
            . ($ok ? 'setTimeout(function(){window.close();}, 1000);' : '')
            . '})();</script></body></html>';
        exit();
    }
}
