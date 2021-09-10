<?php

namespace Drupal\oe_newsroom_newsletter\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_newsroom\Api\NewsroomMessengerInterface;
use Drupal\oe_newsroom_newsletter\OeNewsroomNewsletter;
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
  public function __construct(NewsroomMessengerInterface $newsroomMessenger) {
    $this->newsroomMessenger = $newsroomMessenger;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_newsroom.messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'unsubscribe_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $currentUser = $this->currentUser();
    $config = $this->config(OeNewsroomNewsletter::OE_NEWSLETTER_CONFIG_VAR_NAME);

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
    $form['unsubscribe'] = [
      '#type' => 'submit',
      '#value' => $this->t('Unsubscribe'),
      '#weight' => '0',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get form values.
    $values = $form_state->getValues();

    $distribution_list = is_array($values['distribution_list']) ? array_keys(array_filter($values['distribution_list'])) : [$values['distribution_list']];

    // Let's call the subscription service.
    $status = $this->newsroomMessenger->unsubscribe($values['email'], $distribution_list);
    if ($status === TRUE) {
      $this->messenger()->addStatus($this->t('Successfully unsubscribed!'));
    }
    elseif ($status === FALSE) {
      $this->messenger()->addError($this->t('There was a problem.'));
    }
  }

}
