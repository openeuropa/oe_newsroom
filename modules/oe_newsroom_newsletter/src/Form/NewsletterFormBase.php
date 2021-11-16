<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Newsletter Form Base.
 */
abstract class NewsletterFormBase extends FormBase {

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
      $response->addCommand(new ReplaceCommand(NULL, $form));
    }
    else {
      $messages = ['#type' => 'status_messages'];
      $response->addCommand(new ReplaceCommand(NULL, $messages));
    }

    return $response;
  }

}
