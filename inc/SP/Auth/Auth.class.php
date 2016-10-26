<?php

/**
 * sysPass
 *
 * @author    nuxsmin
 * @link      http://syspass.org
 * @copyright 2012-2015 Rubén Domínguez nuxsmin@syspass.org
 *
 * This file is part of sysPass.
 *
 * sysPass is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * sysPass is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with sysPass.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace SP\Auth;

use SP\Config\Config;
use SP\Core\Exceptions\SPException;
use SP\DataModel\UserData;
use SP\DataModel\UserPassData;
use SP\DataModel\UserPassRecoverData;
use SP\Storage\DB;
use SP\Log\Email;
use SP\Html\Html;
use SP\Core\Init;
use SP\Log\Log;
use SP\Mgmt\Users\UserLdap;
use SP\Mgmt\Users\UserMigrate;
use SP\Mgmt\Users\UserPassRecover;
use SP\Mgmt\Users\UserUtil;
use SP\Storage\QueryData;
use SP\Util\Checks;
use SP\Util\Util;

defined('APP_ROOT') || die(_('No es posible acceder directamente a este archivo'));

/**
 * Esta clase es la encargada de realizar la autentificación de usuarios de sysPass.
 */
class Auth
{
    public static $userName;
    public static $userEmail;

    /**
     * Autentificación de usuarios con LDAP.
     *
     * @param string $userLogin con el login del usuario
     * @param string $userPass  con la clave del usuario
     * @return int|bool Número de error o boolean
     */
    public static function authUserLDAP($userLogin, $userPass)
    {
        if (!Checks::ldapIsAvailable() || !Checks::ldapIsEnabled() || !Ldap::checkLDAPParams()) {
            return false;
        }

        $ldapGroupAccess = false;
        $message['action'] = __FUNCTION__;

        // Conectamos al servidor realizamos la conexión con el usuario proxy
        try {
            Ldap::ldapConnect();
            Ldap::ldapBind();
            Ldap::getUserDN($userLogin);
        } catch (\Exception $e) {
            return false;
        }

        $userDN = Ldap::$ldapSearchData[0]['dn'];

        // Realizamos la conexión con el usuario real y obtenemos los atributos
        try {
            Ldap::ldapBind($userDN, $userPass);
            $attribs = Ldap::getLDAPAttr();
        } catch (\Exception $e) {
            return ldap_errno(Ldap::getConn());
        }

        // Comprobamos si la cuenta está bloqueada o expirada
        if (isset($attribs['expire']) && $attribs['expire'] > 0) {
            return 701;
        }

        if (Ldap::getLdapGroup() !== '*') {
            // Comprobamos que el usuario está en el grupo indicado buscando en los atributos del usuario
            if (isset($attribs['group'])) {
                if (is_array($attribs['group'])) {
                    foreach ($attribs['group'] as $group) {
                        if (is_int($group)) {
                            continue;
                        }

                        // Comprobamos que el usuario está en el grupo indicado
                        if (self::checkLDAPGroup($group)) {
                            $ldapGroupAccess = true;
                            break;
                        }
                    }
                } else {
                    $ldapGroupAccess = self::checkLDAPGroup($attribs['group']);
                }
                // Comprobamos que el usuario está en el grupo indicado buscando en los atributos del grupo
            } else {
                $ldapGroupAccess = (Ldap::searchUserInGroup($userDN) || LdapADS::searchADUserInGroup($userLogin));
            }
        } else {
            $ldapGroupAccess = true;
        }

        if ($ldapGroupAccess === false) {
            $log = new Log(__FUNCTION__);
            $log->addDescription(_('Usuario no pertenece al grupo'));
            $log->addDetails(_('Usuario'), $userDN);
            $log->writeLog();

            return 702;
        }

        self::$userName = (isset($attribs['name'])) ? $attribs['name'] : $userLogin;
        self::$userEmail = (isset($attribs['mail'])) ? $attribs['mail'] : '';

        return true;
    }

    /**
     * Comprobar si el grupo de LDAP está habilitado.
     *
     * @param string $group con el nombre del grupo
     * @return bool
     */
    private static function checkLDAPGroup($group)
    {
        $ldapGroup = strtolower(Config::getConfig()->getLdapGroup());
        $groupName = array();

        preg_match('/^cn=([\w\s-]+),.*/i', $group, $groupName);

        if (strtolower($groupName[1]) == $ldapGroup || strtolower($group) == $ldapGroup) {
            return true;
        }

        return false;
    }

    /**
     * Autentificación de usuarios con MySQL.
     *
     * Esta función comprueba la clave del usuario. Si el usuario necesita ser migrado desde phpPMS,
     * se ejecuta el proceso para actualizar la clave.
     *
     * @param string $userLogin con el login del usuario
     * @param string $userPass  con la clave del usuario
     * @return bool
     */
    public static function authUserMySQL($userLogin, $userPass)
    {
        if (UserMigrate::checkUserIsMigrate($userLogin)) {
            try {
                UserMigrate::migrateUser($userLogin, $userPass);
            } catch (SPException $e) {
                $Log = new Log(__FUNCTION__);
                $Log->addDescription($e->getMessage());
                $Log->addDetails(_('Login'), $userLogin);
                $Log->writeLog();

                return false;
            }
        }

        $query = /** @lang SQL */
            'SELECT user_pass, user_hashSalt
            FROM usrData
            WHERE user_login = ? 
            AND user_isMigrate = 0 LIMIT 1';

        $Data = new QueryData();
        $Data->setMapClassName('SP\DataModel\UserPassData');
        $Data->setQuery($query);
        $Data->addParam($userLogin);

        /** @var UserPassData $queryRes */
        $queryRes = DB::getResults($Data);

        return ($queryRes !== false
            && $queryRes->getUserPass() == crypt($userPass, $queryRes->getUserHashSalt()));
    }

    /**
     * Proceso para la recuperación de clave.
     *
     * @param UserData $UserData
     * @return bool
     */
    public static function mailPassRecover(UserData $UserData)
    {
        if (!$UserData->isUserIsDisabled()
            && !$UserData->isUserIsLdap()
            && !UserPassRecover::checkPassRecoverLimit($UserData)
        ) {
            $hash = Util::generate_random_bytes();

            $Log = new Log(_('Cambio de Clave'));

            $Log->addDescriptionHtml(_('Se ha solicitado el cambio de su clave de usuario.'));
            $Log->addDescriptionLine();
            $Log->addDescription(_('Para completar el proceso es necesario que acceda a la siguiente URL:'));
            $Log->addDescriptionLine();
            $Log->addDescription(Html::anchorText(Init::$WEBURI . '/index.php?a=passreset&h=' . $hash . '&t=' . time()));
            $Log->addDescriptionLine();
            $Log->addDescription(_('Si no ha solicitado esta acción, ignore este mensaje.'));

            $UserPassRecoverData = new UserPassRecoverData();
            $UserPassRecoverData->setUserpassrUserId($UserData->getUserId());
            $UserPassRecoverData->setUserpassrHash($hash);

            return (Email::sendEmail($Log, $UserData->getUserEmail(), false) && UserPassRecover::getItem($UserPassRecoverData)->add());
        }

        return false;
    }

    /**
     * Comprobar el token de seguridad
     *
     * @param $actionId int El id de la accion
     * @param $token    string El token de seguridad
     * @return bool
     */
    public static function checkAuthToken($actionId, $token)
    {
        $query = /** @lang SQL */
            'SELECT authtoken_id
            FROM authTokens
            WHERE authtoken_actionId = ?
            AND authtoken_token = ?
            LIMIT 1';

        $Data = new QueryData();
        $Data->setQuery($query);
        $Data->addParam($actionId);
        $Data->addParam($token);

        DB::getQuery($Data);

        return (DB::$lastNumRows === 1);
    }

    /**
     * Comprobar si el usuario es autentificado por el servidor web
     *
     * @param $login string El login del usuario a comprobar
     * @return bool
     */
    public static function checkServerAuthUser($login)
    {
        if (isset($_SERVER['PHP_AUTH_USER']) || isset($_SERVER['REMOTE_USER'])) {
            return self::getServerAuthUser() == $login;
        }
        return true;
    }

    /**
     * Devolver el nombre del usuario autentificado por el servidor web
     *
     * @return string
     */
    public static function getServerAuthUser()
    {
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            return $_SERVER['PHP_AUTH_USER'];
        } elseif (isset($_SERVER['REMOTE_USER'])) {
            return $_SERVER['REMOTE_USER'];
        }
        return '';
    }

    /**
     * Devuelve el typo de autentificación del servidor web
     *
     * @return string
     */
    public static function getServerAuthType()
    {
        return strtoupper($_SERVER['AUTH_TYPE']);
    }
}