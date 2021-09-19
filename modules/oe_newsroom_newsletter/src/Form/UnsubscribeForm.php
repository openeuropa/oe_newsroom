<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_newsroom\Exception\InvalidApiConfiguration;
use Drupal\oe_newsroom\NewsroomMessengerFactoryInterface;
use Drupal\oe_newsroom_newsletter\OeNewsroomNewsletter;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Subscribe Form.
 */
class UnsubscribeForm extends FormBase {

  /**
   * API for newsroom calls.
   *
   * @var \Drupal\oe_newsroom\Api\NewsroomMessengerInterface
   */
  protected $newsroomMessenger;

  /**
   * {@inheritDoc}
   */
  public function __construct(NewsroomMessengerFactoryInterface $newsroomMessengerFactory) {
    $this->newsroomMessenger = $newsroomMessengerFactory->get();
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_newsroom.messenger_factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_newsroom_newsletter_unsubscribe_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $currentUser = $this->currentUser();
    $config = $this->config(OeNewsroomNewsletter::CONFIG_NAME);

    // Add wrapper for ajax.
    // @todo I think this will break if somebody puts multiple unsubscription
    // form to the same page... However I can't find right now in the core a
    // proper solution for this issue.
    $form['#prefix'] = '<div id="newsroom-newsletter-unsubscription-form">';
    $form['#suffix'] = '</div>';

    // Start building the form itself.
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your e-mail'),
      '#weight' => '0',
      '#default_value' => $currentUser->isAnonymous() ? '' : $currentUser->getEmail(),
      '#required' => TRUE,
    ];
    $distribution_lists = $config->get('distribution_list');
    $distribution_list_options = [];
    foreach ($distribution_lists as $distribution_list) {
      $distribution_list_options[$distribution_list['sv_id']] = $distribution_list['name'];
    }
    if (count($distribution_list_options) > 1) {
      $form['distribution_list'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Newsletter lists'),
        '#description' => $this->t('Please select which newsletter list interests you.'),
        '#options' => $distribution_list_options,
        '#weight' => '0',
        '#required' => TRUE,
      ];
    }
    else {
      $id = array_keys($distribution_list_options)[0];
      $form['distribution_list'] = [
        '#type' => 'hidden',
        '#value' => $id,
        '#default_value' => $id,
      ];
    }
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Unsubscribe'),
        '#ajax' => [
          'callback' => '::submitFormCallback',
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get form values.
    $values = $form_state->getValues();

    $distribution_list = is_array($values['distribution_list']) ? array_keys(array_filter($values['distribution_list'])) : [$values['distribution_list']];

    try {
      // Let's call the subscription service.
      if ($this->newsroomMessenger->unsubscribe($values['email'], $distribution_list)) {
        $this->messenger()->addStatus($this->t('Successfully unsubscribed!'));
      }
      else {
        $this->messenger()->addError($this->t('There was a problem.'));
      }
    }
    catch (InvalidApiConfiguration $e) {
      $this->messenger()->addError($e->getMessage());
    }
    catch (ServerException $e) {
      $this->logger('oe_newsroom_newsletter')->error('An error occurred with %code code and a %message message in the %file file %line line.\n\rTrace: %trace', [
        '%code' => $e->getCode(),
        '%message' => $e->getMessage(),
        '%file' => $e->getFile(),
        '%line' => $e->getLine(),
        '%trace' => $e->getTraceAsString(),
      ]);
      $this->messenger()->addError($this->t('An error happened in the communication. If this persist, connect with the site owner.'));
    }
    catch (BadResponseException $e) {
      $this->messenger()->addError($e->getMessage());
    }
  }

  /**
   * Ajax callback to update the subscription form after it is submitted.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response object.
   */
  public function submitFormCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    if ($form_state->getErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response->addCommand(new HtmlCommand('#newsroom-newsletter-unsubscription-form', $form));
    }
    else {
      $messages = ['#type' => 'status_messages'];
      $response->addCommand(new HtmlCommand('#newsroom-newsletter-unsubscription-form', $messages));
    }

    return $response;
  }

}
