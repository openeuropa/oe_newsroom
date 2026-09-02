<?php

namespace Drupal\oe_newsroom\Value\EmailAction;

use Drupal\oe_newsroom\Helper\ArrayHelper;

/**
 * Indicates that an email with customized values should be sent.
 */
class SendCustomizedEmail {

  /**
   * Constructs a new instance.
   *
   * @param string|null $subject
   *   The custom subject, or NULL to not override.
   * @param string|null $header
   *   The custom header, or NULL to not override.
   * @param string|null $description
   *   The custom description, or NULL to not override.
   * @param string|null $actionDescription
   *   The custom action description, or NULL to not override.
   * @param string|null $actionText
   *   The custom action text, or NULL to not override.
   * @param string|null $footer
   *   The custom footer, or NULL to not override.
   * @param string|null $redirect
   *   The custom redirect url, or NULL to not override.
   */
  public function __construct(
    private readonly ?string $subject = NULL,
    private readonly ?string $header = NULL,
    private readonly ?string $description = NULL,
    private readonly ?string $actionDescription = NULL,
    private readonly ?string $actionText = NULL,
    private readonly ?string $footer = NULL,
    private readonly ?string $redirect = NULL,
  ) {}

  /**
   * Builds an array to use in an API request parameter.
   *
   * @return array
   *   The parameter array.
   */
  public function getCustomizedValues(): array {
    return ArrayHelper::filter([
      'email_custom_subject' => $this->subject,
      'email_custom_header' => $this->header,
      'email_custom_description' => $this->description,
      'email_custom_action_description' => $this->actionDescription,
      'email_custom_action_text' => $this->actionText,
      'email_custom_footer' => $this->footer,
      'email_custom_redirect' => $this->redirect,
    ]);
  }

}
