<?php

namespace Drupal\openid_connect\Form;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\openid_connect\OpenIDConnectSession;
use Drupal\openid_connect\OpenIDConnectClaims;
use Drupal\openid_connect\Plugin\OpenIDConnectClientManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\SerializableClosure;

/**
 * Provides the OpenID Connect login form.
 *
 * @package Drupal\openid_connect\Form
 */
class OpenIDConnectLoginForm extends FormBase implements ContainerInjectionInterface {

  /**
   * The OpenID Connect session service.
   *
   * @var \Drupal\openid_connect\OpenIDConnectSession
   */
  protected $session;

  /**
   * Drupal\openid_connect\Plugin\OpenIDConnectClientManager definition.
   *
   * @var \Drupal\openid_connect\Plugin\OpenIDConnectClientManager
   */
  protected $pluginManager;

  /**
   * The OpenID Connect claims.
   *
   * @var \Drupal\openid_connect\OpenIDConnectClaims
   */
  protected $claims;

  /**
   * The constructor.
   *
   * @param \Drupal\openid_connect\OpenIDConnectSession $session
   *   The OpenID Connect session service.
   * @param \Drupal\openid_connect\Plugin\OpenIDConnectClientManager $plugin_manager
   *   The plugin manager.
   * @param \Drupal\openid_connect\OpenIDConnectClaims $claims
   *   The OpenID Connect claims.
   */
  public function __construct(
    OpenIDConnectSession $session,
    OpenIDConnectClientManager $plugin_manager,
    OpenIDConnectClaims $claims
  ) {
    $this->session = $session;
    $this->pluginManager = $plugin_manager;
    $this->claims = $claims;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('openid_connect.session'),
      $container->get('plugin.manager.openid_connect_client'),
      $container->get('openid_connect.claims')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'openid_connect_login_form';
  }

  /**
   * {@inheritdoc}
   */

  public function buildForm(array $form, FormStateInterface $form_state) {
    $definitions = $this->pluginManager->getDefinitions();
    foreach ($definitions as $client_id => $client) {
      if (!$this->config('openid_connect.settings.' . $client_id)
        ->get('enabled')) {
        continue;
      }

      $form['tipo_identificacion'] = [
        '#type' => 'select',
        '#title' => t('Tipo de identificación'),
        '#options' => [
          'CC' => t('Cédula de ciudadanía'),
          'EM' => t('Correo electrónico'),
        ],
      ];

      $form['identificacion'] = array(
          '#type' => 'textfield',
          '#title' => t('Identificación'),
      );

      $form['openid_connect_client_' . $client_id . '_login'] = array(
        '#type' => 'submit',
        '#value' => t('Entrar', array('@client_title' => $client['label'])),
        '#name' => $client_id,
        '#nameType' => 'login',
      );
  
      $form['openid_connect_client_' . $client_id . '_register'] = array(
        '#type' => 'submit',
        '#value' => t('Registrar', array('@client_title' => $client['label'])),
        '#name' => $client_id,
        '#nameType' => 'register',
      );

    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */

  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->session->saveDestination();
    $client_name = $form_state->getTriggeringElement()['#name'];
    $type = $form_state->getTriggeringElement()['#nameType'];
    $configuration = $this->config('openid_connect.settings.' . $client_name)
      ->get('settings');
    /** @var \Drupal\openid_connect\Plugin\OpenIDConnectClientInterface $client */
    $client = $this->pluginManager->createInstance(
      $client_name,
      $configuration
    );
    $scopes = $this->claims->getScopes();
    $_SESSION['openid_connect_op'] = 'login';
    $login_hint = $form_state->getValue('tipo_identificacion').','.$form_state->getValue('identificacion');
    $_SESSION['scopes'] = $scopes;
    $_SESSION['login_hint'] = $login_hint;
    $_SESSION['client_name'] = $client_name;
    $_SESSION['configuration'] = $configuration;
    $response = $client->authorize($scopes, $type, $login_hint);
    $form_state->setResponse($response);
  }

}
