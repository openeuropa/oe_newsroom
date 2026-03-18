<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_newsroom_management\Form\NodeSubscriptionsForm;
use Drupal\oe_newsroom_management\TokenManager;

/**
 * Handles different request for node notifications.
 */
final class NodeSubscriptions extends ControllerBase {

  public function __construct(
    protected TokenManager $tokenManager,
    FormBuilderInterface $formBuilder,
    AccountInterface $currentUser,
  ) {
    $this->formBuilder = $formBuilder;
    $this->currentUser = $currentUser;
  }

  /**
   * Renders the subscriptions page for authenticated users.
   *
   * @return array
   *   The node subscriptions form render array.
   */
  public function authenticatedManagementPage() {
    if ($this->currentUser->isAnonymous()) {
      $this->messenger()->addWarning($this->t('Your need to request access to manage your subscriptions.'));

      return $this->redirect('oe_newsroom_management.request_access');
    }

    return $this->formBuilder->getForm(NodeSubscriptionsForm::class, $this->currentUser->getEmail());
  }

  /**
   * Renders the subscriptions page for anonymous users.
   *
   * @param string $email
   *   The user e-mail.
   * @param string $token
   *   The token to validate the request.
   *
   * @return mixed
   *   A redirect response if token is not valid or the subscriptions form.
   */
  public function anonymousManagementPage(string $email, string $token) {
    if (!$this->tokenManager->isValid($email, $token)) {
      $this->messenger()->addError($this->t('Your token is either invalid or it has expired. Please request a new token to access your subscriptions.'));

      return $this->redirect('oe_newsroom_management.request_access');
    }

    return $this->formBuilder->getForm(NodeSubscriptionsForm::class, $email);
  }

}
