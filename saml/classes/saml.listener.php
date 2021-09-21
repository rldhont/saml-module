<?php
/**
 * @author  Laurent Jouanneau
 * @copyright  2021 3liz
 * @licence  http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public Licence, see LICENCE file
 */

class samlListener extends jEventListener{

    /**
     * @param jEvent $event
     */
    function onJauthLoginFormExtraBefore ($event)
    {
        $tpl = new jTpl();
        $url = '';
        if (jApp::coord()->request) {
            $url = jApp::coord()->request->getParam('auth_url_return');
        }
        $tpl->assign('auth_url_return', $url);
        $event->add($tpl->fetch('saml~loginform'));

    }
}
