<?php
/**
* @author     Laurent Jouanneau
* @copyright  2019 Laurent Jouanneau
* @licence  http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public Licence, see LICENCE file
*/


/**
* authentification driver for authentification with a SAML server
*/
class samlAuthDriver extends jAuthDriverBase implements jIAuthDriver2 {

    protected $automaticAccountCreation = false;

    function __construct($params) {
        if (property_exists(jApp::config(), 'saml') && is_array(jApp::config()->saml)) {
            $params = array_merge($params, jApp::config()->saml);
        }
        parent::__construct($params);
        if (!isset($this->_params['profile'])) {
            $this->_params['profile'] = '';
        }
        if (!isset($this->_params['dao'])) {
            throw new Exception("Dao selector is missing into the saml configuration");
        }

        if (isset($this->_params['automaticAccountCreation'])) {
            $this->automaticAccountCreation = $this->_params['automaticAccountCreation'];
        }
    }

    public function saveNewUser($user) {
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        $dao->insert($user);
        return true;
    }

    public function removeUser($login) {
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        if (function_exists('mb_strtolower')) {
            $login = mb_strtolower($login);
        }
        $dao->deleteByLogin($login);
        return true;
    }

    public function updateUser($user) {
        if (!is_object($user)) {
            throw new jException('saml~auth.error.object.user.unknown');
        }

        if ($user->login == '') {
            throw new jException('saml~auth.error.user.login.unset');
        }

        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        $dao->update($user);
        return true;
    }

    public function getUser($login) {
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        if (function_exists('mb_strtolower')) {
            $login = mb_strtolower($login);
        }
        return $dao->getByLogin($login);
    }

    public function createUserObject($login, $password) {
        $user = jDao::createRecord($this->_params['dao'], $this->_params['profile']);
        $user->login = $login;
        // we ignore password since it is managed by the SAML server
        $user->password = '!!saml';
        return $user;
    }

    public function getUserList($pattern) {
        $dao = jDao::get($this->_params['dao'], $this->_params['profile']);
        if ($pattern == '%' || $pattern == '') {
            return $dao->findAll();
        } else {
            return $dao->findByLogin($pattern);
        }
    }

    public function canChangePassword($login) {
        return false;
    }
    public function changePassword($login, $newpassword) {
        // we ignore password since it is managed by the SAML server
        return true;
    }

    public function verifyPassword($login, $password) {
        // we ignore password since it is managed by the SAML server

        $daouser = jDao::get($this->_params['dao'], $this->_params['profile']);
        if (function_exists('mb_strtolower')) {
            $Llogin = mb_strtolower($login);
        }
        else {
            $Llogin = $login;
        }
        $user = $daouser->getByLogin($Llogin);
        if (!$user) {
            if ($this->automaticAccountCreation) {
                $user = $this->createUserObject($Llogin, $password);
                if (jApp::isModuleEnabled('jcommunity')) {
                    $user->status = 1; // STATUS_VALID
                }
                jAuth::saveNewUser($user);
            } else {
                return false;
            }
        }
        return $user;
    }
}
