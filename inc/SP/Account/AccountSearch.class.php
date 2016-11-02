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

namespace SP\Account;

use SP\Config\Config;
use SP\Core\Acl;
use SP\Core\ActionsInterface;
use SP\DataModel\AccountData;
use SP\DataModel\AccountExtData;
use SP\DataModel\AccountSearchData;
use SP\Log\Log;
use SP\Mgmt\Groups\GroupAccountsUtil;
use SP\Mgmt\Groups\GroupUtil;
use SP\Mgmt\Users\User;
use SP\Storage\DB;
use SP\Mgmt\Groups\Group;
use SP\Html\Html;
use SP\Core\Session;
use SP\Mgmt\Users\UserUtil;
use SP\Storage\QueryData;
use SP\Util\Checks;

defined('APP_ROOT') || die(_('No es posible acceder directamente a este archivo'));

/**
 * Class AccountSearch para la gestión de búsquedas de cuentas
 */
class AccountSearch
{
    /**
     * Constantes de ordenación
     */
    const SORT_NAME = 1;
    const SORT_CATEGORY = 2;
    const SORT_LOGIN = 3;
    const SORT_URL = 4;
    const SORT_CUSTOMER = 5;
    const SORT_DIR_ASC = 0;
    const SORT_DIR_DESC = 1;
    /**
     * @var int El número de registros de la última consulta
     */
    public static $queryNumRows;
    /**
     * Colores para resaltar las cuentas
     *
     * @var array
     */
    private static $colors = [
        '2196F3',
        '03A9F4',
        '00BCD4',
        '009688',
        '4CAF50',
        '8BC34A',
        'CDDC39',
        'FFC107',
        '795548',
        '607D8B',
        '9E9E9E',
        'FF5722',
        'F44336',
        'E91E63',
        '9C27B0',
        '673AB7',
        '3F51B5',
    ];

    /**
     * @var bool
     */
    private $globalSearch = false;
    /**
     * @var string
     */
    private $txtSearch = '';
    /**
     * @var int
     */
    private $customerId = 0;
    /**
     * @var int
     */
    private $categoryId = 0;
    /**
     * @var int
     */
    private $sortOrder = 0;
    /**
     * @var int
     */
    private $sortKey = 0;
    /**
     * @var int
     */
    private $limitStart = 0;
    /**
     * @var int
     */
    private $limitCount = 12;
    /**
     * @var bool
     */
    private $sortViews = false;
    /**
     * @var bool
     */
    private $searchFavorites = false;

    /**
     * Constructor
     */
    public function __construct()
    {
        $userResultsPerPage = (Session::getSessionType() === Session::SESSION_INTERACTIVE) ? Session::getUserPreferences()->getResultsPerPage() : 0;

        $this->limitCount = ($userResultsPerPage > 0) ? $userResultsPerPage : Config::getConfig()->getAccountCount();
        $this->sortViews = (Session::getSessionType() === Session::SESSION_INTERACTIVE) ? Session::getUserPreferences()->isSortViews() : false;
    }

    /**
     * @return boolean
     */
    public function isSearchFavorites()
    {
        return $this->searchFavorites;
    }

    /**
     * @param boolean $searchFavorites
     * @return $this
     */
    public function setSearchFavorites($searchFavorites)
    {
        $this->searchFavorites = (bool)$searchFavorites;

        return $this;
    }

    /**
     * @return int
     */
    public function getGlobalSearch()
    {
        return $this->globalSearch;
    }

    /**
     * @param int $globalSearch
     * @return $this
     */
    public function setGlobalSearch($globalSearch)
    {
        $this->globalSearch = $globalSearch;

        return $this;
    }

    /**
     * @return string
     */
    public function getTxtSearch()
    {
        return $this->txtSearch;
    }

    /**
     * @param string $txtSearch
     * @return $this
     */
    public function setTxtSearch($txtSearch)
    {
        $this->txtSearch = (string)$txtSearch;

        return $this;
    }

    /**
     * @return int
     */
    public function getCustomerId()
    {
        return $this->customerId;
    }

    /**
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId($customerId)
    {
        $this->customerId = $customerId;

        return $this;
    }

    /**
     * @return int
     */
    public function getCategoryId()
    {
        return $this->categoryId;
    }

    /**
     * @param int $categoryId
     * @return $this
     */
    public function setCategoryId($categoryId)
    {
        $this->categoryId = $categoryId;

        return $this;
    }

    /**
     * @return int
     */
    public function getSortOrder()
    {
        return $this->sortOrder;
    }

    /**
     * @param int $sortOrder
     * @return $this
     */
    public function setSortOrder($sortOrder)
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    /**
     * @return int
     */
    public function getLimitStart()
    {
        return $this->limitStart;
    }

    /**
     * @param int $limitStart
     * @return $this
     */
    public function setLimitStart($limitStart)
    {
        $this->limitStart = $limitStart;

        return $this;
    }

    /**
     * @return int
     */
    public function getLimitCount()
    {
        return $this->limitCount;
    }

    /**
     * @param int $limitCount
     * @return $this
     */
    public function setLimitCount($limitCount)
    {
        $this->limitCount = $limitCount;

        return $this;
    }

    /**
     * Procesar los resultados de la búsqueda y crear la variable que contiene los datos de cada cuenta
     * a mostrar.
     *
     * @return array
     */
    public function processSearchResults()
    {
        if (!$results = $this->getAccounts()) {
            return [];
        }

        // Variables de configuración
        $maxTextLength = Checks::resultsCardsIsEnabled() ? 40 : 60;

        $accountsData['count'] = self::$queryNumRows;

        foreach ($results as $AccountSearchData) {
            // Establecer los datos de la cuenta
            $Account = new Account($AccountSearchData);
            $AccountSearchData->setUsersId($Account->getUsersAccount());
            $AccountSearchData->setUserGroupsId($Account->getGroupsAccount());
            $AccountSearchData->setTags(AccountTags::getTags($Account->getAccountData()));

            // Obtener la ACL de la cuenta
            $AccountAcl = new AccountAcl();
            $AccountAcl->getAcl($Account, Acl::ACTION_ACC_SEARCH);

            $AccountSearchItems = new AccountsSearchItem($AccountSearchData);
            $AccountSearchItems->setTextMaxLength($maxTextLength);
            $AccountSearchItems->setColor($this->pickAccountColor($AccountSearchData->getAccountCustomerId()));
            $AccountSearchItems->setShowView($AccountAcl->isShowView());
            $AccountSearchItems->setShowViewPass($AccountAcl->isShowViewPass());
            $AccountSearchItems->setShowEdit($AccountAcl->isShowEdit());
            $AccountSearchItems->setShowCopy($AccountAcl->isShowCopy());
            $AccountSearchItems->setShowDelete($AccountAcl->isShowDelete());

            $accountsData[] = $AccountSearchItems;
        }

        return $accountsData;
    }

    /**
     * Obtener las cuentas de una búsqueda.
     *
     * @return AccountSearchData[] Resultado de la consulta
     */
    public function getAccounts()
    {
        $isAdmin = (Session::getUserIsAdminApp() || Session::getUserIsAdminAcc());

        $arrFilterCommon = [];
        $arrFilterSelect = [];
        $arrFilterUser = [];
        $arrayQueryJoin = [];
        $arrQueryWhere = [];
        $queryLimit = '';

        $Data = new QueryData();

        if ($this->txtSearch) {
            // Analizar la cadena de búsqueda por etiquetas especiales
            $stringFilters = $this->analyzeQueryString();

            if (count($stringFilters) > 0) {
                foreach ($stringFilters as $filter) {
                    $arrFilterCommon[] = $filter['query'];

                    foreach ($filter['values'] as $value) {
                        $Data->addParam($value);
                    }
                }
            } else {
                $txtSearch = '%' . $this->txtSearch . '%';

                $arrFilterCommon[] = 'account_name LIKE ?';
                $Data->addParam($txtSearch);

                $arrFilterCommon[] = 'account_login LIKE ?';
                $Data->addParam($txtSearch);

                $arrFilterCommon[] = 'account_url LIKE ?';
                $Data->addParam($txtSearch);

                $arrFilterCommon[] = 'account_notes LIKE ?';
                $Data->addParam($txtSearch);

            }
        }

        if ($this->categoryId !== 0) {
            $arrFilterSelect[] = 'account_categoryId = ?';
            $Data->addParam($this->categoryId);
        }

        if ($this->customerId !== 0) {
            $arrFilterSelect[] = 'account_customerId = ?';
            $Data->addParam($this->customerId);
        }

        if ($this->searchFavorites === true) {
            $arrayQueryJoin[] = 'INNER JOIN accFavorites ON (accfavorite_accountId = account_id AND accfavorite_userId = ?)';
            $Data->addParam(Session::getUserId());

//            $arrFilterSelect[] = 'accfavorite_userId = ?';
//            $Data->addParam(Session::getUserId());
        }

        if (count($arrFilterCommon) > 0) {
            $arrQueryWhere[] = '(' . implode(' OR ', $arrFilterCommon) . ')';
        }

        if (count($arrFilterSelect) > 0) {
            $arrQueryWhere[] = '(' . implode(' AND ', $arrFilterSelect) . ')';
        }

        if (!$isAdmin && !$this->globalSearch) {
            /*            $subQueryGroups = '(SELECT user_groupId FROM usrData WHERE user_id = ? UNION ALL SELECT usertogroup_groupId FROM usrToGroups WHERE usertogroup_userId = ?)';

                        // Buscar el grupo principal de la cuenta en los grupos del usuario
                        $arrFilterUser[] = 'account_userGroupId IN ' . $subQueryGroups;
                        $Data->addParam(Session::getUserId());
                        $Data->addParam(Session::getUserId());

                        // Buscar los grupos secundarios de la cuenta en los grupos del usuario
                        $arrFilterUser[] = 'accgroup_groupId IN ' . $subQueryGroups;
                        $Data->addParam(Session::getUserId());
                        $Data->addParam(Session::getUserId());

                        // Comprobar el usuario principal de la cuenta con el usuario actual
                        $arrFilterUser[] = 'account_userId = ?';
                        $Data->addParam(Session::getUserId());

                        // Comprobar los usuarios secundarios de la cuenta con el usuario actual
                        $arrFilterUser[] = 'accuser_userId = ?';
                        $Data->addParam(Session::getUserId());

                        $arrQueryWhere[] = '(' . implode(' OR ', $arrFilterUser) . ')';*/

            $arrFilterUser[] = 'account_userId = ?';
            $Data->addParam(Session::getUserId());
            $arrFilterUser[] = 'account_userGroupId = ?';
            $Data->addParam(Session::getUserGroupId());
            $arrFilterUser[] = 'account_id IN (SELECT accuser_accountId FROM accUsers WHERE accuser_userId = ?)';
            $Data->addParam(Session::getUserId());
            $arrFilterUser[] = 'account_userGroupId IN (SELECT usertogroup_groupId FROM usrToGroups WHERE usertogroup_userId = ?)';
            $Data->addParam(Session::getUserGroupId());

            $arrQueryWhere[] = '(' . implode(' OR ', $arrFilterUser) . ')';
        }

        if ($this->limitCount > 0) {
            $queryLimit = '?, ?';

            $Data->addParam($this->limitStart);
            $Data->addParam($this->limitCount);
        }

        if (count($arrQueryWhere) === 1) {
            $queryWhere = implode($arrQueryWhere);
        } elseif (count($arrQueryWhere) > 1) {
            $queryWhere = implode(' AND ', $arrQueryWhere);
        } else {
            $queryWhere = '';
        }

        $queryJoin = implode('', $arrayQueryJoin);

        $Data->setSelect('*');
        $Data->setFrom('account_search_v ' . $queryJoin);
        $Data->setWhere($queryWhere);
        $Data->setOrder($this->getOrderString());
        $Data->setLimit($queryLimit);

//        $Data->setQuery($query);
        $Data->setMapClassName('SP\DataModel\AccountSearchData');

        // Obtener el número total de cuentas visibles por el usuario
        DB::setFullRowCount();

        // Obtener los resultados siempre en array de objetos
        DB::setReturnArray();

        Log::writeNewLog(__FUNCTION__, $Data->getQuery(), Log::DEBUG);

        // Consulta de la búsqueda de cuentas
        $queryRes = DB::getResults($Data);

        if ($queryRes === false) {
            return false;
        }

        // Obtenemos el número de registros totales de la consulta sin contar el LIMIT
        self::$queryNumRows = DB::$lastNumRows;

        // Establecer el filtro de búsqueda en la sesión como un objeto
        Session::setSearchFilters($this);

        return $queryRes;
    }

    /**
     * Analizar la cadena de consulta por eqituetas especiales y devolver un array
     * con las columnas y los valores a buscar.
     *
     * @return array|bool
     */
    private function analyzeQueryString()
    {
        preg_match('/(user|group|file|tag):(.*)/i', $this->txtSearch, $filters);

        if (!is_array($filters) || count($filters) === 0) {
            return [];
        }

        $filtersData = [];

        switch ($filters[1]) {
            case 'user':
                $UserData = User::getItem()->getByLogin($filters[2])->getItemData();
                $filtersData[] = [
                    'type' => 'user',
                    'query' => '(account_userId = ? OR accuser_userId ?)',
                    'values' => [$UserData->getUserId(), $UserData->getUserId()]
                ];
                break;
            case 'group':
                $GroupData = GroupUtil::getGroupIdByName($filters[2]);
                $filtersData[] = [
                    'type' => 'group',
                    'query' => '(account_userGroupId = ? OR accgroup_groupId ?)',
                    'values' => [$GroupData->getUsergroupId(), $GroupData->getUsergroupId()]
                ];
                break;
            case 'file':
                $filtersData[] = [
                    'type' => 'group',
                    'query' => 'accfile_name LIKE ?',
                    'values' => [$filters[2]]
                ];
                break;
            case 'tag':
                $filtersData[] =
                    [
                        'type' => 'tag',
                        'query' => 'account_id IN (SELECT acctag_accountId FROM accTags, tags WHERE tag_id = acctag_tagId AND tag_name = ?)',
                        'values' => [$filters[2]]
                    ];
                break;
            default:
                return $filtersData;
        }

        return $filtersData;
    }

    /**
     * Devuelve la cadena de ordenación de la consulta
     *
     * @return string
     */
    private function getOrderString()
    {
        switch ($this->sortKey) {
            case self::SORT_NAME:
                $orderKey[] = 'account_name';
                break;
            case self::SORT_CATEGORY:
                $orderKey[] = 'category_name';
                break;
            case self::SORT_LOGIN:
                $orderKey[] = 'account_login';
                break;
            case self::SORT_URL:
                $orderKey[] = 'account_url';
                break;
            case self::SORT_CUSTOMER:
                $orderKey[] = 'customer_name';
                break;
            default :
                $orderKey[] = 'customer_name';
                $orderKey[] = 'account_name';
                break;
        }

        if ($this->isSortViews() && !$this->getSortKey()) {
            array_unshift($orderKey, 'account_countView DESC');
            $this->setSortOrder(self::SORT_DIR_DESC);
        }

        $orderDir = ($this->sortOrder === self::SORT_DIR_ASC) ? 'ASC' : 'DESC';
        return sprintf('%s %s', implode(',', $orderKey), $orderDir);
    }

    /**
     * @return boolean
     */
    public function isSortViews()
    {
        return $this->sortViews;
    }

    /**
     * @param boolean $sortViews
     * @return $this
     */
    public function setSortViews($sortViews)
    {
        $this->sortViews = $sortViews;

        return $this;
    }

    /**
     * @return int
     */
    public function getSortKey()
    {
        return $this->sortKey;
    }

    /**
     * @param int $sortKey
     * @return $this
     */
    public function setSortKey($sortKey)
    {
        $this->sortKey = $sortKey;

        return $this;
    }

    /**
     * Seleccionar un color para la cuenta
     *
     * @param int $id El id del elemento a asignar
     * @return mixed
     */
    private function pickAccountColor($id)
    {
        $accountColor = Session::getAccountColor();

        if (!isset($accountColor)
            || !is_array($accountColor)
            || !isset($accountColor[$id])
        ) {
            // Se asigna el color de forma aleatoria a cada id
            $color = array_rand(self::$colors);

            $accountColor[$id] = '#' . self::$colors[$color];
            Session::setAccountColor($accountColor);
        }

        return $accountColor[$id];
    }
}