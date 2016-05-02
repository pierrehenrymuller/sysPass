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

namespace SP\Controller;

defined('APP_ROOT') || die(_('No es posible acceder directamente a este archivo'));

use SP\Config\Config;
use SP\Config\ConfigDB;
use SP\Core\ActionsInterface;
use SP\Core\Init;
use SP\Core\Language;
use SP\Core\Session;
use SP\Core\SessionUtil;
use SP\Core\Themes;
use SP\Storage\DBUtil;
use SP\Util\Checks;
use SP\Util\Util;

/**
 * Clase encargada de preparar la presentación de las opciones de configuración
 *
 * @package Controller
 */
class ConfigMgmt extends Controller implements ActionsInterface
{
    private $tabIndex = 0;
    private $Config;

    /**
     * Constructor
     *
     * @param $template \SP\Core\Template con instancia de plantilla
     */
    public function __construct(\SP\Core\Template $template = null)
    {
        parent::__construct($template);

        $this->Config = Config::getConfig();

        $this->view->assign('tabs', array());
        $this->view->assign('sk', SessionUtil::getSessionKey(true));
        $this->view->assign('isDemoMode', (Checks::demoIsEnabled() && !Session::getUserIsAdminApp()));
        $this->view->assign('isDisabled', (Checks::demoIsEnabled() && !Session::getUserIsAdminApp()) ? 'DISABLED' : '');
    }

    /**
     * Obtener la pestaña de configuración
     *
     * @return bool
     */
    public function getGeneralTab()
    {
        $this->setAction(self::ACTION_CFG_GENERAL);

        if (!$this->checkAccess()) {
            return;
        }

        $this->view->addTemplate('config');

        $this->view->assign('langsAvailable',Language::getAvailableLanguages());
        $this->view->assign('currentLang', $this->Config->getSiteLang());
        $this->view->assign('themesAvailable', Themes::getThemesAvailable());
        $this->view->assign('currentTheme', $this->Config->getSiteTheme());
        $this->view->assign('chkHttps', ($this->Config->isHttpsEnabled()) ? 'checked="checked"' : '');
        $this->view->assign('chkDebug', ($this->Config->isDebug()) ? 'checked="checked"' : '');
        $this->view->assign('chkMaintenance', ($this->Config->isMaintenance()) ? 'checked="checked"' : '');
        $this->view->assign('chkUpdates', ($this->Config->isCheckUpdates()) ? 'checked="checked"' : '');
        $this->view->assign('chkNotices', ($this->Config->isChecknotices()) ? 'checked="checked"' : '');
        $this->view->assign('sessionTimeout', $this->Config->getSessionTimeout());

        // Events
        $this->view->assign('chkLog', ($this->Config->isLogEnabled()) ? 'checked="checked"' : '');
        $this->view->assign('chkSyslog', ($this->Config->isSyslogEnabled()) ? 'checked="checked"' : '');
        $this->view->assign('chkRemoteSyslog', ($this->Config->isSyslogRemoteEnabled()) ? 'checked="checked"' : '');
        $this->view->assign('remoteSyslogServer', $this->Config->getSyslogServer());
        $this->view->assign('remoteSyslogPort', $this->Config->getSyslogPort());

        // Files
        $this->view->assign('chkFiles', ($this->Config->isFilesEnabled()) ? 'checked="checked"' : '');
        $this->view->assign('filesAllowedExts', implode(',', $this->Config->getFilesAllowedExts()));
        $this->view->assign('filesAllowedSize', $this->Config->getFilesAllowedSize());

        // Accounts
        $this->view->assign('chkGlobalSearch', ($this->Config->isGlobalSearch()) ? 'checked="checked"' : '');
        $this->view->assign('chkResultsAsCards', ($this->Config->isResultsAsCards()) ? 'checked="checked"' : '');
        $this->view->assign('chkAccountPassToImage', ($this->Config->isAccountPassToImage()) ? 'checked="checked"' : '');
        $this->view->assign('chkAccountLink', ($this->Config->isAccountLink()) ? 'checked="checked"' : '');
        $this->view->assign('accountCount', $this->Config->getAccountCount());

        // PublicLinks
        $this->view->assign('chkPubLinks', ($this->Config->isPublinksImageEnabled()) ? 'checked="checked"' : '');
        $this->view->assign('chkPubLinksImage', ($this->Config->isPublinksImageEnabled()) ? 'checked="checked"' : '');
        $this->view->assign('pubLinksMaxTime', $this->Config->getPublinksMaxTime() / 60);
        $this->view->assign('pubLinksMaxViews', $this->Config->getPublinksMaxViews());

        // Proxy
        $this->view->assign('chkProxy', ($this->Config->isProxyEnabled()) ? 'checked="checked"' : '');
        $this->view->assign('proxyServer', $this->Config->getProxyServer());
        $this->view->assign('proxyPort', $this->Config->getProxyPort());
        $this->view->assign('proxyUser', $this->Config->getProxyUser());
        $this->view->assign('proxyPass', $this->Config->getProxyPass());

        $this->view->assign('actionId', $this->getAction(), 'config');
        $this->view->append('tabs', array('title' => _('General')));
        $this->view->assign('tabIndex', $this->getTabIndex(), 'config');
    }

    /**
     * Obtener la pestaña de encriptación
     *
     * @return bool
     */
    public function getEncryptionTab()
    {
        $this->setAction(self::ACTION_CFG_ENCRYPTION);

        if (!$this->checkAccess()) {
            return;
        }

        $this->view->addTemplate('encryption');

        $this->view->assign('lastUpdateMPass', ConfigDB::getValue("lastupdatempass"));
        $this->view->assign('tempMasterPassTime', ConfigDB::getValue("tempmaster_passtime"));
        $this->view->assign('tempMasterMaxTime', ConfigDB::getValue("tempmaster_maxtime"));
        $this->view->assign('tempMasterPass', Session::getTemporaryMasterPass());

        $this->view->append('tabs', array('title' => _('Encriptación')));
        $this->view->assign('tabIndex', $this->getTabIndex(), 'encryption');
    }

    /**
     * Obtener la pestaña de copia de seguridad
     *
     * @return bool
     */
    public function getBackupTab()
    {
        $this->setAction(self::ACTION_CFG_BACKUP);

        if (!$this->checkAccess()) {
            return;
        }

        $this->view->addTemplate('backup');

        $this->view->assign('siteName', Util::getAppInfo('appname'));
        $this->view->assign('backupDir', Init::$SERVERROOT . '/backup');
        $this->view->assign('backupPath', Init::$WEBROOT . '/backup');

        $backupHash =  $this->Config->getBackupHash();
        $exportHash =  $this->Config->getExportHash();

        $this->view->assign('backupFile',
            array('absolute' => $this->view->backupDir . DIRECTORY_SEPARATOR . $this->view->siteName  . '-' . $backupHash . '.tar.gz',
                'relative' => $this->view->backupPath . '/' . $this->view->siteName . '-' . $backupHash . '.tar.gz',
                'filename' => $this->view->siteName . '-' . $backupHash . '.tar.gz')
        );
        $this->view->assign('backupDbFile',
            array('absolute' => $this->view->backupDir . DIRECTORY_SEPARATOR . $this->view->siteName . 'db-' . $backupHash . '.sql',
                'relative' => $this->view->backupPath . '/' . $this->view->siteName . 'db-' . $backupHash . '.sql',
                'filename' => $this->view->siteName . 'db-' . $backupHash . '.sql')
        );
        $this->view->assign('lastBackupTime', (file_exists($this->view->backupFile['absolute'])) ? _('Último backup') . ": " . date("r", filemtime($this->view->backupFile['absolute'])) : _('No se encontraron backups'));

        $this->view->assign('exportFile',
            array('absolute' => $this->view->backupDir . DIRECTORY_SEPARATOR . $this->view->siteName . '-' . $exportHash . '.xml',
                'relative' => $this->view->backupPath . '/' . $this->view->siteName . '-' . $exportHash . '.xml',
                'filename' => $this->view->siteName . '-' . $exportHash . '.xml')
        );
        $this->view->assign('lastExportTime', (file_exists($this->view->exportFile['absolute'])) ? _('Última exportación') . ': ' . date("r", filemtime($this->view->exportFile['absolute'])) : _('No se encontró archivo de exportación'));

        $this->view->append('tabs', array('title' => _('Copia de Seguridad')));
        $this->view->assign('tabIndex', $this->getTabIndex(), 'backup');
    }

    /**
     * Obtener la pestaña de Importación
     *
     * @return bool
     */
    public function getImportTab()
    {
        $this->setAction(self::ACTION_CFG_IMPORT);

        if (!$this->checkAccess()) {
            return;
        }

        $this->view->addTemplate('import');

        $this->view->assign('groups', DBUtil::getValuesForSelect('usrGroups', 'usergroup_id', 'usergroup_name'));
        $this->view->assign('users', DBUtil::getValuesForSelect('usrData', 'user_id', 'user_name'));

        $this->view->append('tabs', array('title' => _('Importar Cuentas')));
        $this->view->assign('tabIndex', $this->getTabIndex(), 'import');
    }

    /**
     * Obtener la pestaña de información
     * @return bool
     */
    public function getInfoTab()
    {
        $this->setAction(self::ACTION_CFG_GENERAL);

        if (!$this->checkAccess()) {
            return;
        }

        $this->view->addTemplate('info');

        $this->view->assign('dbInfo', DBUtil::getDBinfo());
        $this->view->assign('dbName', $this->Config->getDbName() . '@' . $this->Config->getDbHost());
        $this->view->assign('configBackupDate', date("r", ConfigDB::getValue('config_backupdate')));

        $this->view->append('tabs', array('title' => _('Información')));
        $this->view->assign('tabIndex', $this->getTabIndex(), 'info');
    }

    /**
     * Obtener la pestaña de Wiki
     * @return bool
     */
    public function getWikiTab()
    {
        $this->setAction(self::ACTION_CFG_WIKI);

        if (!$this->checkAccess(self::ACTION_CFG_GENERAL)) {
            return;
        }

        $this->view->addTemplate('wiki');

        $this->view->assign('chkWiki', ($this->Config->isWikiEnabled()) ? 'checked="checked"' : '');
        $this->view->assign('wikiSearchUrl', $this->Config->getWikiSearchurl());
        $this->view->assign('wikiPageUrl', $this->Config->getWikiPageurl());
        $this->view->assign('wikiFilter', implode(',', $this->Config->getWikiFilter()));

        $this->view->assign('chkDokuWiki', ($this->Config->isDokuwikiEnabled()) ? 'checked="checked"' : '');
        $this->view->assign('dokuWikiUrl', $this->Config->getDokuwikiUrl());
        $this->view->assign('dokuWikiUrlBase', $this->Config->getDokuwikiUrlBase());
        $this->view->assign('dokuWikiUser', $this->Config->getDokuwikiUser());
        $this->view->assign('dokuWikiPass', $this->Config->getDokuwikiPass());
        $this->view->assign('dokuWikiNamespace', $this->Config->getDokuwikiNamespace());

        $this->view->assign('actionId', $this->getAction(), 'wiki');
        $this->view->append('tabs', array('title' => _('Wiki')));
        $this->view->assign('tabIndex', $this->getTabIndex(), 'wiki');
    }

    /**
     * Obtener la pestaña de LDAP
     * @return bool
     */
    public function getLdapTab()
    {
        $this->setAction(self::ACTION_CFG_LDAP);

        if (!$this->checkAccess(self::ACTION_CFG_GENERAL)) {
            return;
        }

        $this->view->addTemplate('ldap');

        $this->view->assign('chkLdap', ($this->Config->isLdapEnabled()) ? 'checked="checked"' : '');
        $this->view->assign('chkLdapADS', ($this->Config->isLdapAds()) ? 'checked="checked"' : '');
        $this->view->assign('ldapIsAvailable', Checks::ldapIsAvailable());
        $this->view->assign('ldapServer', $this->Config->getLdapServer());
        $this->view->assign('ldapBindUser', $this->Config->getLdapBindUser());
        $this->view->assign('ldapBindPass', $this->Config->getLdapBindPass());
        $this->view->assign('ldapBase', $this->Config->getLdapBase());
        $this->view->assign('ldapGroup', $this->Config->getLdapGroup());
        $this->view->assign('groups', DBUtil::getValuesForSelect('usrGroups', 'usergroup_id', 'usergroup_name'));
        $this->view->assign('profiles', DBUtil::getValuesForSelect('usrProfiles', 'userprofile_id', 'userprofile_name'));
        $this->view->assign('ldapDefaultGroup', $this->Config->getLdapDefaultGroup());
        $this->view->assign('ldapDefaultProfile', $this->Config->getLdapDefaultProfile());

        $this->view->assign('actionId', $this->getAction(), 'ldap');
        $this->view->append('tabs', array('title' => _('LDAP')));
        $this->view->assign('tabIndex', $this->getTabIndex(), 'ldap');
    }

    /**
     * Obtener la pestaña de Correo
     * @return bool
     */
    public function getMailTab()
    {
        $this->setAction(self::ACTION_CFG_MAIL);

        if (!$this->checkAccess(self::ACTION_CFG_GENERAL)) {
            return;
        }

        $this->view->addTemplate('mail');

        $this->view->assign('chkMail', ($this->Config->isMailEnabled()) ? 'checked="checked"' : '');
        $this->view->assign('chkMailRequests', ($this->Config->isMailRequestsEnabled()) ? 'checked="checked"' : '');
        $this->view->assign('chkMailAuth', ($this->Config->isMailAuthenabled()) ? 'checked="checked"' : '');
        $this->view->assign('mailServer', $this->Config->getMailServer());
        $this->view->assign('mailPort', $this->Config->getMailPort());
        $this->view->assign('mailUser', $this->Config->getMailUser());
        $this->view->assign('mailPass', $this->Config->getMailPass());
        $this->view->assign('currentMailSecurity', $this->Config->getMailSecurity());
        $this->view->assign('mailFrom', $this->Config->getMailFrom());
        $this->view->assign('mailSecurity', ['SSL', 'TLS']);

        $this->view->assign('actionId', $this->getAction(), 'mail');
        $this->view->append('tabs', array('title' => _('Correo')));
        $this->view->assign('tabIndex', $this->getTabIndex(), 'mail');
    }

    /**
     * Obtener el índice actual de las pestañas
     *
     * @return int
     */
    private function getTabIndex(){
        $index = $this->tabIndex;
        $this->tabIndex++;

        return $index;
    }
}