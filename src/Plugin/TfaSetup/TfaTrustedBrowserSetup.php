<?php

namespace Drupal\uceap_tfa\Plugin\TfaSetup;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\tfa\Plugin\TfaLogin\TfaTrustedBrowser;
use Drupal\tfa\Plugin\TfaSetupInterface;

/**
 * UCEAP TFA Trusted Browser Setup Plugin.
 *
 * @TfaSetup(
 *   id = "uceap_tfa_trusted_browser_setup",
 *   label = @Translation("UCEAP TFA Trusted Browser Setup"),
 *   description = @Translation("UCEAP TFA Trusted Browser Setup Plugin"),
 *   setupMessages = {
 *    "saved" = @Translation("Browser saved."),
 *    "skipped" = @Translation("Browser not saved.")
 *   }
 * )
 */
class TfaTrustedBrowserSetup extends TfaTrustedBrowser implements TfaSetupInterface {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function getSetupForm(array $form, FormStateInterface $form_state) {
    $form['info'] = [
      '#type' => 'markup',
      '#markup' => '<p>' . $this->t("Your browser will be remembered for 18 hours
      before you need to re-enter a login code. If you use a different browser
      during that time you will need to enter a new code.") . '</p>',
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Continue'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSetupForm(array $form, FormStateInterface $form_state) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function submitSetupForm(array $form, FormStateInterface $form_state) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getOverview(array $params) {
    $form = [
      'description' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Browsers will only require a verification code during login once every 18 hours.'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getHelpLinks() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getSetupMessages() {
    return [];
  }

}
