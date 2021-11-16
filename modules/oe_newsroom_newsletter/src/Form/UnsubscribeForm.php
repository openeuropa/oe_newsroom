<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_newsroom\Exception\InvalidApiConfiguration;
use Drupal\oe_newsroom_newsletter\Api\NewsroomClient;
use Drupal\oe_newsroom_newsletter\Api\NewsroomClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Subscribe Form.
 *
 * Arguments:
 *  - A distribution lists array
 *    ex. array(0 => array('sv_id' => 20, 'name' => 'XY Newsletter'))
 */
class UnsubscribeForm extends NewsletterFormBase {

  /**
   * API for newsroom calls.
   *
   * @var \Drupal\oe_newsroom_newsletter\Api\NewsroomClientInterface
   */
  protected $newsroomClient;

  /**
   * {@inheritDoc}
   */
  public function __construct(NewsroomClientInterface $newsroomClient) {
    $this->newsroomClient = $newsroomClient;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      NewsroomClient::create($container)
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
  public function buildForm(array $form, FormStateInterface $form_state, array $distribution_lists = []) {
    $currentUser = $this->currentUser();

    $form['#id'] = Html::getUniqueId($this->getFormId());

    // Start building the form itself.
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your e-mail'),
      '#default_value' => $currentUser->isAnonymous() ? '' : $currentUser->getEmail(),
      '#required' => TRUE,
    ];
    if (count($distribution_lists) > 1) {
      $options = array_column($distribution_lists, 'name', 'sv_id');
      $form['distribution_lists'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Newsletters'),
        '#description' => $this->t('Please select which newsletter list interests you.'),
        '#options' => $options,
        '#required' => TRUE,
      ];
    }
    else {
      $id = $distribution_lists[0]['sv_id'];
      $form['distribution_lists'] = [
        '#type' => 'value',
        '#value' => $id,
      ];
    }
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get form values.
    $values = $form_state->getValues();

    $distribution_lists = is_array($values['distribution_lists']) ? array_keys(array_filter($values['distribution_lists'])) : [$values['distribution_lists']];

    try {
      // Let's call the unsubscription service.
      if ($this->newsroomClient->unsubscribe($values['email'], $distribution_lists)) {
        $this->messenger()->addStatus($this->t('Successfully unsubscribed!'));
      }
      else {
        $this->messenger()->addError($this->t('There was a problem.'));
      }
    }
    catch (InvalidApiConfiguration $e) {
      $this->messenger->addError(t('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.'));
      $this->getLogger('oe_newsroom_newsletter')->error('Exception thrown while unsubscribing with %code code and a %message message in the %file file %line line.\n\rTrace: %trace', [
        '%code' => $e->getCode(),
        '%message' => $e->getMessage(),
        '%file' => $e->getFile(),
        '%line' => $e->getLine(),
        '%trace' => $e->getTraceAsString(),
      ]);
    }
    catch (ServerException $e) {
      $this->messenger->addError(t('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.'));
      $this->getLogger('oe_newsroom_newsletter')->error('Exception thrown while unsubscribing with %code code and a %message message in the %file file %line line.\n\rTrace: %trace', [
        '%code' => $e->getCode(),
        '%message' => $e->getMessage(),
        '%file' => $e->getFile(),
        '%line' => $e->getLine(),
        '%trace' => $e->getTraceAsString(),
      ]);
    }
    catch (BadResponseException $e) {
      $this->messenger->addError(t('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.'));
      $this->getLogger('oe_newsroom_newsletter')->error('Exception thrown while unsubscribing with %code code and a %message message in the %file file %line line.\n\rTrace: %trace', [
        '%code' => $e->getCode(),
        '%message' => $e->getMessage(),
        '%file' => $e->getFile(),
        '%line' => $e->getLine(),
        '%trace' => $e->getTraceAsString(),
      ]);
    }
  }

}
