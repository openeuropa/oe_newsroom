<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_newsroom\OeNewsroom;
use Drupal\oe_newsroom_newsletter\Api\NewsroomClient;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Subscribe form.
 *
 * Arguments:
 *  - A distribution lists array
 *    ex. array(0 => array('sv_id' => 20, 'name' => 'XY Newsletter'))
 */
class UnsubscribeForm extends NewsletterFormBase {

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container): UnsubscribeForm {
    return new static(
      NewsroomClient::create($container),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('logger.factory'),
    );
  }

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
      }
      else {
        $this->messenger->addError($this->t('There was a problem.'));
      }
    }
    catch (ServerException | BadResponseException $e) {
      $this->messenger->addError($this->t('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.'));
      $this->logger->get('oe_newsroom_newsletter')->error('Exception thrown with code %code while subscribing email %email to the newsletter(s) with ID(s) %sv_ids and universe %universe: %exception', [
        '%code' => $e->getCode(),
        '%email' => $values['email'],
        '%universe' => $this->config(OeNewsroom::CONFIG_NAME)->get('universe'),
        '%sv_ids' => implode(',', $distribution_lists),
        '%exception' => $e->getMessage(),
      ]);
    }
  }

}
