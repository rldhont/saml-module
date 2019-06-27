<?php
/**
 * @author  Laurent Jouanneau
 * @copyright  2019 3Liz
 * @licence  http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public Licence, see LICENCE file
 */
namespace Jelix\Saml;

use OneLogin\Saml2\Settings;
use OneLogin\Saml2\Constants;

class Configuration {

    /**
     * @var array setting for the \OneLogin\Saml2 classes
     */
    protected $settings = array();

    /**
     * Configuration constructor.
     * @param \jRequest $request
     * @param object $iniConfig typically jApp::config()
     * @throws \jException
     */
    public function __construct(\jRequest $request, $iniConfig = null)
    {
        if (!$iniConfig) {
            $iniConfig = \jApp::config();
        }

        $spConfig = $iniConfig->{'saml:sp'};

        $this->settings['strict'] = !$spConfig['saml_debug'];
        $this->settings['debug'] = $spConfig['saml_debug'];
        $this->settings['baseurl'] = $request->getServerURI() . \jApp::urlBasePath();

        $this->settings['sp'] = $this->getSpConfig($iniConfig);
        $this->settings['idp'] = $this->getIdPConfig($iniConfig);
        $this->settings['security'] = $iniConfig->{'saml:security'};
        $this->settings['contactPerson'] = array(
            'technical' => $spConfig['technicalContactPerson'],
            'support'  => $spConfig['supportContactPerson'],
        );
        $this->settings['organization'] = array( 'en-US' => $spConfig['organization']);
        $this->settings['compress'] = array(
            'requests' => $spConfig['compressRequests'],
            'responses' => $spConfig['compressResponses']
        );
    }

    protected function getSpConfig($iniConfig) {
        $spConfig = $iniConfig->{'saml:sp'};

        $spX509certFile = \jApp::configPath('saml/certs/sp.crt');
        $spPrivateKey  = \jApp::configPath('saml/certs/sp.key');

        if (!file_exists($spX509certFile)) {
            throw new \Exception('SAML Error: certificat file of the service provider, var/config/saml/certs/sp.crt, does not exists');
        }

        if (!file_exists($spPrivateKey)) {
            throw new \Exception('SAML Error: private key file of the service provider, var/config/saml/certs/sp.key, does not exists');
        }

        // Service Provider Data that we are deploying
        $serviceProvider =array(
            // Identifier of the SP entity  (must be a URI)
            'entityId' => \jUrl::getFull('saml~endpoint:metadata'),
            // Specifies info about where and how the <AuthnResponse> message MUST be
            // returned to the requester, in this case our SP.
            'assertionConsumerService' => array(
                // URL Location where the <Response> from the IdP will be returned
                'url' => \jUrl::getFull('saml~endpoint:acs'),
                // SAML protocol binding to be used when returning the <Response>
                // message.  Onelogin Toolkit supports for this endpoint the
                // HTTP-Redirect binding only
                'binding' => Constants::BINDING_HTTP_POST,
            ),

            // Specifies info about where and how the <Logout Response> message MUST be
            // returned to the requester, in this case our SP.
            'singleLogoutService' => array(
                // URL Location where the <Response> from the IdP will be returned
                'url' => \jUrl::getFull('saml~endpoint:sls'),
                // SAML protocol binding to be used when returning the <Response>
                // message.  Onelogin Toolkit supports for this endpoint the
                // HTTP-Redirect binding only
                'binding' => Constants::BINDING_HTTP_REDIRECT,
            ),
            // Specifies constraints on the name identifier to be used to
            // represent the requested subject.
            // Take a look on lib/Saml2/Constants.php to see the NameIdFormat supported
            'NameIDFormat' => Constants::NAMEID_UNSPECIFIED,

            'x509cert' => file_get_contents($spX509certFile),
            'privateKey' => file_get_contents($spPrivateKey),
        );

        $spX509certNewFile = \jApp::configPath('saml/certs/sp_new.crt');
        if (file_exists($spX509certNewFile)) {
            $serviceProvider['x509certNew'] = file_get_contents($spX509certNewFile);
        }

        // ---------- requested attributes
        if (isset($spConfig['attrcs_service_name']) &&
            $spConfig['attrcs_service_name'] != '' &&
            isset($iniConfig->{'saml:sp:requestedAttributes'}) &&
            count($iniConfig->{'saml:sp:requestedAttributes'})
        ) {
            $requestedAttributes = array();
            foreach($iniConfig->{'saml:sp:requestedAttributes'} as $attrname =>$properties) {
                $attribute = array( "name" => $attrname);
                foreach(array('isRequired', 'nameFormat', 'friendlyName', 'attributeValue') as $property) {
                    if (isset($properties[$property])) {
                        $attribute[$property] = $properties[$property];
                    }

                }
                $requestedAttributes[] = $attribute;
            }

            $serviceProvider ["attributeConsumingService"] = array(
                "serviceName" => $spConfig['attrcs_service_name'],
                "serviceDescription" => $spConfig['attrcs_service_description'],
                "requestedAttributes" => $requestedAttributes
            );
        }
        return $serviceProvider;
    }

    protected function getIdPConfig($iniConfig) {
        $idpConfig = $iniConfig->{'saml:idp'};

        if ($idpConfig['certs_signing_files'] == '') {
            $idpX509certFile = \jApp::configPath('saml/certs/idp.crt');

            if (!file_exists($idpX509certFile) && $idpConfig['certs_signing_files'] == '') {
                throw new \Exception('SAML Error: certificat file of the identity provider, var/config/saml/certs/idp.crt, does not exists');
            }
            $idpX509cert = file_get_contents($idpX509certFile);
        }
        else {
            $idpX509cert = '';
            $list = preg_split('/ *, */', $idpConfig['certs_signing_files']);
            $certsSigning = array();
            foreach( $list as $file) {
                $path = \jApp::configPath('saml/certs/'.$file);
                if (!file_exists($path)) {
                    throw new \Exception('SAML Error: certificat file of the identity provider, var/config/saml/certs/'.$path.', does not exists');
                }
                $certsSigning[] = file_get_contents($path);
            }
            $list = preg_split('/ *, */', $idpConfig['certs_encryption_files']);
            $certsEncryption = array();
            foreach( $list as $file) {
                $path = \jApp::configPath('saml/certs/'.$file);
                if (!file_exists($path)) {
                    throw new \Exception('SAML Error: certificat file of the identity provider, var/config/saml/certs/'.$path.', does not exists');
                }
                $certsEncryption[] = file_get_contents($path);
            }
        }

        $bindings = array(
            'http-post' => Constants::BINDING_HTTP_POST,
            'http-redirect' => Constants::BINDING_HTTP_REDIRECT,
            'http-artifact' => Constants::BINDING_HTTP_ARTIFACT,
            'soap' => Constants::BINDING_SOAP,
            'deflate' => Constants::BINDING_DEFLATE,
        );
        if (!isset($bindings[$idpConfig['singleSignOnServiceBinding']])) {
            throw new \Exception('SAML Error: bad value for singleSignOnServiceBinding');
        }

        if (!isset($bindings[$idpConfig['singleLogoutServiceBinding']])) {
            throw new \Exception('SAML Error: bad value for singleSignOnServiceBinding');
        }


        // Identity Provider Data that we want connect with our SP
        $identityProvider = array(
            'entityId' => $idpConfig['entityId'],
            'singleSignOnService' => array(
                'url' => $idpConfig['singleSignOnServiceUrl'],
                'binding' => $bindings[$idpConfig['singleSignOnServiceBinding']],
            ),
            'singleLogoutService' => array(
                'url' => $idpConfig['singleLogoutServiceUrl'],
                'binding' => $bindings[$idpConfig['singleLogoutServiceBinding']],
            ),
            // Public x509 certificate of the IdP
            'x509cert' => $idpX509cert,
        );

        if (count($certsSigning)) {
            $identityProvider['x509certMulti'] =  array(
                'signing' => $certsSigning,
                'encryption' => $certsEncryption
            );
        }
        return $identityProvider;
    }

    /**
     * @return Settings
     * @throws \OneLogin\Saml2\Error
     */
    function getSettings() {
        return new Settings($this->settings);
    }

    /**
     * @return array
     */
    function getSettingsArray() {
        return $this->settings;
    }
}