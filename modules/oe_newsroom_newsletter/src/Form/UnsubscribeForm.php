<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Error;
use Drupal\oe_newsroom\Newsroom;
use Drupal\oe_newsroom_newsletter\Exception\ClientException;

/**
 * Unsubscribe form.
 *
 * @internal This class depends on the client that will be later moved to a
 *   dedicated library. This class will be refactored and this will break any
 *   dependencies on it.
 */
class UnsubscribeForm extends NewsletterFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'oe_newsroom_newsletter_unsubscribe_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $distribution_lists = []): array {
    $form = parent::buildForm($form, $form_state, $distribution_lists);

    $form['#id'] = Html::getUniqueId($this->getFormId());

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Unsubscribe'),
        '#ajax' => [
          'callback' => '::submitFormCallback',
          'wrapper' => $form['#id'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Get form values.
    $values = $form_state->getValues();

    $distribution_lists = is_array($values['distribution_lists']) ? array_keys(array_filter($values['distribution_lists'])) : [$values['distribution_lists']];

    try {
      // Let's call the unsubscription service.
      if ($this->newsroomClient->unsubscribe($values['email'], $distribution_lists)) {
        $this->messenger->addStatus($this->t('Successfully unsubscribed!'));
        return;
      }
    }
    catch (ClientException $e) {
      $this->logger->get('oe_newsroom_newsletter')->error('%type thrown while unsubscribing email %email to the newsletter(s) with ID(s) %sv_ids and universe %universe: @message in %function (line %line of %file).', [
        '%email' => $values['email'],
        '%universe' => $this->config(Newsroom::CONFIG_NAME)->get('universe'),
        '%sv_ids' => implode(',', $distribution_lists),
      ] + Error::decodeException($e));
    }

    $this->messenger->addError($this->t('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.'));
  }

  /**
   * {@inheritdoc}
   */
  protected function getDistributionListsFieldDescription(): TranslatableMarkup {
    return $this->t('Please select the newsletter lists you want to unsubscribe from.');
  }

}
