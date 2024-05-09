<?php

namespace Drupal\uceap_tfa\Plugin\TfaLogin;

use Drupal\Core\Config\Config;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\tfa\Plugin\TfaBasePlugin;
use Drupal\tfa\Plugin\TfaLoginInterface;
use Drupal\tfa\Plugin\TfaValidationInterface;
use Drupal\user\UserDataInterface;

/**
 * UCEAP TFA trusted browser validation class.
 *
 * @TfaLogin(
 *   id = "uceap_tfa_trusted_browser",
 *   label = @Translation("UCEAP TFA Trusted Browser"),
 *   description = @Translation("UCEAP TFA Trusted Browser Plugin"),
 *   setupPluginId = "uceap_tfa_trusted_browser_setup",
 * )
 */
class TfaTrustedBrowser extends TfaBasePlugin implements TfaLoginInterface, TfaValidationInterface {
  use StringTranslationTrait;

  const COOKIE_NAME = 'uceap-tfa-trusted-browser';

  /**
   * The instructions markup.
   *
   * @var string
   */
  protected $instructionsMarkup;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, UserDataInterface $user_data, EncryptionProfileManagerInterface $encryption_profile_manager, EncryptServiceInterface $encrypt_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $user_data, $encryption_profile_manager, $encrypt_service);
    $this->userData = $user_data;
    $plugin_settings = \Drupal::config('tfa.settings')->get('login_plugin_settings');
    $settings = $plugin_settings['uceap_tfa_trusted_browser'] ?? [];
    $settings = array_replace([
      'instructions_markup' => 'Please contact the helpdesk if you need assistance.',
    ], $settings);
    $this->instructionsMarkup = $settings['instructions_markup'];
  }

  /**
   * {@inheritdoc}
   */
  public function loginAllowed() {
    if (isset($_COOKIE[static::COOKIE_NAME]) && $this->trustedBrowser($_COOKIE[static::COOKIE_NAME]) !== FALSE) {
      $this->setUsed($_COOKIE[static::COOKIE_NAME]);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array $form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array $form, FormStateInterface &$form_state) {
    $this->setTrusted($this->generateBrowserId());
  }

  /**
   * The configuration form for this validation plugin.
   *
   * @param \Drupal\Core\Config\Config $config
   *   Config object for tfa settings.
   * @param array $state
   *   Form state array determines if this form should be shown.
   *
   * @return array
   *   Form array specific for this validation plugin.
   */
  public function buildConfigurationForm(Config $config, array $state = []) {
    return [
      'info' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => 'Browsers will be remembered for 18 hours.',
      ],
      'instructions_markup' => [
        '#type' => 'textarea',
        '#title' => $this->t('Instructions Markup'),
        '#description' => $this->t('HTML markup for instructions (e.g. a button to popup an overlay)'),
        '#value' => $this->instructionsMarkup,
      ],
    ];
  }

  /**
   * Finalize the browser setup.
   *
   * @throws \Exception
   */
  public function finalize() {
    $this->setTrusted($this->generateBrowserId());
  }

  /**
   * Generate a random value to identify the browser.
   *
   * @return string
   *   Base64 encoded browser id.
   *
   * @throws \Exception
   */
  protected function generateBrowserId() {
    $id = base64_encode(random_bytes(32));
    return strtr($id, ['+' => '-', '/' => '_', '=' => '']);
  }

  /**
   * Store browser value and issue cookie for user.
   *
   * @param string $id
   *   Trusted browser id.
   */
  protected function setTrusted($id) {
    // Currently broken.
    // Store id for account.
    $records = $this->getUserData('tfa', 'uceap_tfa_trusted_browser', $this->configuration['uid'], $this->userData) ?: [];
    $request_time = \Drupal::time()->getRequestTime();

    $name = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : 'unknown';

    $records[$id] = [
      'created' => $request_time,
      'ip' => \Drupal::request()->getClientIp(),
      'name' => $name,
    ];
    $this->setUserData('tfa', ['uceap_tfa_trusted_browser' => $records], $this->configuration['uid'], $this->userData);

    // Issue cookie with ID.
    $cookie_secure = ini_get('session.cookie_secure');
    // 18 hours, per UC Account and Authentication Management Standard 5.1.2 (Last Updated: 12/8/2022)
    $expiration = $request_time + 18 * 60 * 60;
    $domain = ini_get('session.cookie_domain');
    setcookie(static::COOKIE_NAME, $id, $expiration, base_path(), $domain, !empty($cookie_secure), TRUE);

    // @todo use services defined in module instead this procedural way.
    \Drupal::logger('uceap_tfa')->info('Set trusted browser for user UID @uid, browser @name', [
      '@name' => empty($name) ? $this->getAgent() : $name,
      '@uid' => $this->uid,
    ]);
  }

  /**
   * Updated browser last used time.
   *
   * @param int $id
   *   Internal browser ID to update.
   */
  protected function setUsed($id) {
    $result = $this->getUserData('tfa', 'uceap_tfa_trusted_browser', $this->uid, $this->userData);
    $result[$id]['last_used'] = \Drupal::time()->getRequestTime();
    $data = [
      'uceap_tfa_trusted_browser' => $result,
    ];
    $this->setUserData('tfa', $data, $this->uid, $this->userData);
  }

  /**
   * Check if browser id matches user's saved browser.
   *
   * @param string $id
   *   The browser ID.
   *
   * @return bool
   *   TRUE if ID exists otherwise FALSE.
   */
  protected function trustedBrowser($id) {
    // Check if $id has been saved for this user.
    $result = $this->getUserData('tfa', 'uceap_tfa_trusted_browser', $this->uid, $this->userData);
    if (isset($result[$id])) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Delete users trusted browser.
   *
   * @param string $id
   *   (optional) Id of the browser to be purged.
   *
   * @return bool
   *   TRUE is id found and purged otherwise FALSE.
   */
  protected function deleteTrusted($id = '') {
    $result = $this->getUserData('tfa', 'uceap_tfa_trusted_browser', $this->uid, $this->userData);
    if ($id) {
      if (isset($result[$id])) {
        unset($result[$id]);
        $data = [
          'uceap_tfa_trusted_browser' => $result,
        ];
        $this->setUserData('tfa', $data, $this->uid, $this->userData);
        return TRUE;
      }
    }
    else {
      $this->deleteUserData('tfa', 'uceap_tfa_trusted_browser', $this->uid, $this->userData);
      return TRUE;
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function ready() {
    return TRUE;
  }

}
