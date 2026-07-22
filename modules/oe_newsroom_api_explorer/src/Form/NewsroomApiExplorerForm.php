<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_api_explorer\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\oe_newsroom\Value\NotificationFrequency;
use Drupal\oe_newsroom_api_explorer\ApiExplorerMethodRegistry;
use Drupal\oe_newsroom_api_explorer\Helper\ReflectionHelper;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use phpDocumentor\Reflection\DocBlock\Tag;
use phpDocumentor\Reflection\DocBlock\Tags\Param;
use phpDocumentor\Reflection\DocBlockFactory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * A form to test API endpoints.
 */
class NewsroomApiExplorerForm implements FormInterface, ContainerInjectionInterface {

  use AutowireTrait;
  use DependencySerializationTrait;
  use StringTranslationTrait;

  public function __construct(
    protected HandlerStack $handlerStack,
    protected ModuleHandlerInterface $moduleHandler,
    protected TimeInterface $time,
    protected MessengerInterface $messenger,
    protected ApiExplorerMethodRegistry $methodRegistry,
    TranslationInterface $translation,
  ) {
    $this->setStringTranslation($translation);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'oe_newsroom_explorer_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $endpoint_options = $this->methodRegistry->getSelectOptions();
    $form['endpoint'] = [
      '#type' => 'select',
      '#title' => $this->t('Endpoint'),
      '#options' => $endpoint_options,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::' . [$this, 'endpointChangeAjax'][1],
      ],
    ];
    $form['endpoint_subform'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'endpoint-subform'],
    ];
    $endpoint_name = $form_state->getValue('endpoint') ?: NULL;
    if ($endpoint_name !== NULL) {
      $form['endpoint_subform'] += $this->buildEndpointArgumentsSubform($endpoint_name);
    }
    $form['messages'] = [
      '#prefix' => '<div id="ajax-messages">',
      '#suffix' => '</div>',
    ];
    if ($form_state->getValues()) {
      $form['messages']['messages'] = [
        '#type' => 'status_messages',
      ];
    }
    $form['endpoint_info'] = [
      '#weight' => 200,
      '#type' => 'container',
      '#attributes' => ['id' => 'endpoint-info'],
    ];
    if ($endpoint_name !== NULL) {
      $form['endpoint_info'] += $this->buildEndpointInfo($endpoint_name);
    }
    return $form;
  }

  /**
   * Ajax callback to update the subform when the endpoint changes.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response with ajax commands.
   */
  public function endpointChangeAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#endpoint-subform', $form['endpoint_subform']));
    $response->addCommand(new ReplaceCommand('#endpoint-info', $form['endpoint_info']));
    return $response;
  }

  /**
   * Ajax callback to update form parts on submit.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Response with ajax commands.
   */
  public function submitAjax(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#ajax-messages', $form['messages']));
    $response->addCommand(new ReplaceCommand('#endpoint-subform', $form['endpoint_subform']));
    return $response;
  }

  /**
   * Builds a subform for a given endpoint.
   *
   * @param string $endpoint_name
   *   The endpoint name.
   *
   * @return array
   *   Form elements array.
   */
  protected function buildEndpointArgumentsSubform(string $endpoint_name): array {
    $method_closure = $this->methodRegistry->getMethodAsClosure($endpoint_name);
    assert($method_closure !== NULL);
    $method = ReflectionHelper::getReflectionMethodFromClosure($method_closure);
    assert($method !== NULL);
    $subform = [];
    /** @var array<string, \phpDocumentor\Reflection\DocBlock\Tags\Param> $param_tags */
    $param_tags = [];
    // Use phpDocumentor if available.
    if (class_exists(DocBlockFactory::class)) {
      $doc_comment = ReflectionHelper::findOriginalMethodDocComment($method);
      if ($doc_comment !== NULL) {
        $phpdoc = DocBlockFactory::createInstance()->create($doc_comment);
        foreach ($phpdoc->getTagsByName('param') as $tag) {
          assert($tag instanceof Param);
          $param_tags[$tag->getVariableName()] = $tag;
        }
      }
    }
    $subform['arguments'] = [
      '#tree' => TRUE,
      '#type' => 'fieldset',
      '#title' => $this->t('Arguments for %endpoint', [
        '%endpoint' => $method->name . '()',
      ]),
    ];
    $unsupported = FALSE;
    foreach ($method->getParameters() as $parameter) {
      $subform['arguments'][$parameter->getName()] = $this->buildArgumentWidget(
        $parameter,
        $param_tags[$parameter->name] ?? NULL,
        $unsupported,
      );
    }
    $subform['consent'] = [
      '#type' => 'checkbox',
      '#required' => TRUE,
      '#default_value' => FALSE,
      '#title' => $this->t('I understand that submitting this form might cause emails being sent to innocent recipients.'),
    ];
    $subform['actions'] = [
      '#type' => 'actions',
      '#weight' => NULL,
    ];
    $subform['actions']['submit'] = [
      '#type' => 'submit',
      '#ajax' => [
        'callback' => '::' . [$this, 'submitAjax'][1],
      ],
      '#value' => $this->t('Submit'),
      '#disabled' => $unsupported,
    ];
    return $subform;
  }

  /**
   * Builds render elements with information about the endpoint.
   *
   * @param string $endpoint_name
   *   The endpoint name.
   *
   * @return array
   *   Form elements array.
   */
  protected function buildEndpointInfo(string $endpoint_name): array {
    $method_closure = $this->methodRegistry->getMethodAsClosure($endpoint_name);
    assert($method_closure !== NULL);
    $method = ReflectionHelper::getReflectionMethodFromClosure($method_closure);
    assert($method !== NULL);
    $subform = [];
    $doc_comment = ReflectionHelper::findOriginalMethodDocComment($method);
    $doc_description = $doc_comment;

    // Use phpDocumentor if available.
    if (class_exists(DocBlockFactory::class) && $doc_comment) {
      $phpdoc = DocBlockFactory::createInstance()->create($doc_comment);
      $doc_description = implode("\n", array_filter([
        $phpdoc->getSummary() . "\n",
        $phpdoc->getDescription() . "\n",
        ...array_map(
          function (Tag $tag) {
            return $tag->render();
          },
          array_filter($phpdoc->getTags(), function (Tag $tag) {
            return $tag->getName() !== 'param';
          }),
        ),
      ], fn (string $part) => trim((string) $part, "\n ") !== ''));
    }
    $subform['method'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Endpoint method'),
      'value' => $this->buildCodeElement($endpoint_name . '()'),
    ];
    if ($doc_description) {
      $subform['doc'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Method documentation'),
        'doc' => $this->buildCodeElement($doc_description),
      ];
    }
    return $subform;
  }

  /**
   * Builds a widget to set/choose an argument value.
   *
   * @param \ReflectionParameter $parameter
   *   Parameter that should receive the value.
   * @param \phpDocumentor\Reflection\DocBlock\Tags\Param|null $param_tag
   *   Parameter phpdoc tag, if available.
   *   If the phpdocumentor package is not present, this will be NULL.
   * @param bool $unsupported
   *   This will be set to TRUE, if the parameter type is unsupported.
   *
   * @return array
   *   Form element array.
   */
  protected function buildArgumentWidget(\ReflectionParameter $parameter, ?Param $param_tag, bool &$unsupported): array {
    $reflection_type = $parameter->getType();
    // Currently, all relevant parameters have simple named types.
    $type_name = $reflection_type instanceof \ReflectionNamedType
      ? $reflection_type->getName()
      : NULL;
    try {
      // If $type_name is NULL, it will trigger the `UnhandledMatchError` that
      // is handled in the catch branch below.
      $element = match ($type_name) {
        'int' => [
          '#type' => 'number',
          '#step' => 1,
        ],
        'string' => [
          '#type' => 'textfield',
        ],
        'bool' => [
          '#type' => 'checkbox',
        ],
        'array' => [
          '#type' => 'textfield',
          '#description' => $this->t('Use json or separate by comma'),
        ],
        NodeInterface::class => [
          '#type' => 'entity_autocomplete',
          '#target_type' => 'node',
        ],
        NotificationFrequency::class => [
          '#type' => 'select',
          '#options' => (function (): array {
            $options = [];
            foreach (NotificationFrequency::cases() as $case) {
              $options[$case->value] = $case->value;
            }
            return $options;
          })(),
          ...($parameter->allowsNull()) ? [
            '#empty_option' => $this->t('- None -'),
            '#empty_value' => '',
          ] : [],
        ],
      };
    }
    catch (\UnhandledMatchError) {
      $unsupported = TRUE;
      $element = [
        '#disabled' => TRUE,
        '#type' => 'textfield',
        '#element_validate' => ['::' . [$this, 'validateUnsupportedParameter'][1]],
        '#validation_error' => $type_name !== NULL
          ? $this->t('The parameter @name has an unsupported type @type.', [
            '@name' => '$' . $parameter->name,
            '@type' => $type_name,
          ])
          : $this->t('The parameter @name has a no type information.', [
            '@name' => '$' . $parameter->name,
          ]),
      ];
    }
    $element['#title'] = $parameter->name;
    $element['#required'] = !$parameter->isOptional() && $type_name !== 'bool';
    $description_parts = [];
    if ($param_tag !== NULL) {
      $description_parts[] = Html::escape($param_tag->getDescription()->render());
    }
    if (!empty($element['#description'])) {
      $description_parts[] = $element['#description'];
    }
    if ($type_name !== NULL) {
      $description_parts[] = $this->t('Type: %type', [
        '%type' => $type_name,
      ]);
    }
    if ($param_tag !== NULL) {
      $doc_type_name = $param_tag->getType()->__toString();
      if ($doc_type_name !== $type_name && $doc_type_name !== '\\' . $type_name) {
        $description_parts[] = $this->t('Doc type: %type', [
          '%type' => $doc_type_name,
        ]);
      }
    }
    $description_placeholders = array_map(
      fn (int $index) => '@' . $index,
      array_keys($description_parts),
    );
    $element['#description'] = new FormattableMarkup(
      implode("<br>\n", $description_placeholders),
      array_combine($description_placeholders, $description_parts),
    );
    return $element;
  }

  /**
   * Validates an element that represents an unsupported parameter.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateUnsupportedParameter(array &$element, FormStateInterface $form_state): void {
    $form_state->setError($element, $element['#validation_error']);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $endpoint_name = $form_state->getValue('endpoint') ?: NULL;
    $submitted_arguments = $form_state->getValue('arguments') ?? [];

    $report = [];
    try {
      $this->invokeEndpoint($endpoint_name, $submitted_arguments, $report);
      $this->messenger->addStatus($this->t(
        // Add a time to make subsequent submissions distinguishable.
        '<h3>Submission successful (%time).</h3><br>@report',
        [
          '%time' => date('H:i:s', $this->time->getRequestTime()),
          '@report' => $this->renderReport($report),
        ],
      ));
    }
    catch (\Throwable $exception) {
      $exception_summary = [];
      $exception_report = [];
      for ($i = 0, $e = $exception; $e !== NULL; $e = $e->getPrevious(), ++$i) {
        $exception_report[]["exception.$i"] = [
          'file' => $e->getFile() . ':' . $e->getLine(),
          'class' => get_class($e),
          'code' => $e->getCode(),
          'message' => $e->getMessage(),
          'trace' => $e->getTraceAsString(),
        ];
        $exception_summary[] = get_class($e) . ":\n" . $e->getMessage();
      }
      $report = [
        ['exception' => $exception_summary],
        ...$exception_report,
        ...$report,
      ];
      $this->messenger->addError($this->t(
        '<h3>Submission failed (%time).</h3><br><pre>@report</pre>',
        [
          '@report' => $this->renderReport($report),
          '%time' => date('H:i:s', $this->time->getRequestTime()),
        ],
      ));
    }
  }

  /**
   * Invokes the endpoint and populates a report array.
   *
   * @param string $endpoint_name
   *   The endpoint name, with class name and method name separated by '::'.
   * @param array $submitted_arguments
   *   Argument values from form elements.
   * @param array $report
   *   A report array, to be updated by reference.
   *   All the values here must be exportable to yaml.
   *
   * @throws \Throwable
   *   If any step fails.
   */
  protected function invokeEndpoint(string $endpoint_name, array $submitted_arguments, array &$report): void {
    $report[]['endpoint_name'] = $endpoint_name;
    $method_closure = $this->methodRegistry->getMethodAsClosure($endpoint_name);
    assert($method_closure !== NULL);
    $reflection_function = new \ReflectionFunction($method_closure);
    assert($reflection_function !== NULL);
    try {
      $arguments = [];
      foreach ($reflection_function->getParameters() as $parameter) {
        $arguments[$parameter->name] = $this->getArgumentValue(
          $submitted_arguments[$parameter->name] ?? NULL,
          $parameter,
        );
      }
      $report[]['arguments'] = $arguments;
    }
    catch (\Throwable $e) {
      $report[]['raw_arguments'] = $submitted_arguments;
      throw $e;
    }
    // Insert a http client middleware to record requests and responses.
    $reporting_middleware_handler = $this->createReportingMiddleware($report);
    if ($this->moduleHandler->moduleExists('http_request_mock')) {
      $this->handlerStack->before('http_request_mock.client_middleware', $reporting_middleware_handler);
    }
    else {
      $this->handlerStack->push($reporting_middleware_handler);
    }
    try {
      $report = [
        ['return' => $method_closure(...$arguments)],
        ...$report,
      ];
    }
    finally {
      $this->handlerStack->remove($reporting_middleware_handler);
    }
  }

  /**
   * Gets an argument value from a form value.
   *
   * @param mixed $value
   *   Form value.
   * @param \ReflectionParameter $parameter
   *   Parameter that the argument value will be passed to.
   *
   * @return mixed
   *   The argument value.
   *
   * @throws \Exception
   *   Value cannot be converted.
   */
  protected function getArgumentValue(mixed $value, \ReflectionParameter $parameter): mixed {
    if ($value === NULL) {
      if (!$parameter->allowsNull()) {
        throw new \Exception(sprintf("Illegal value NULL for parameter %s", $parameter->name));
      }
      else {
        return NULL;
      }
    }
    if ($value === '' && $parameter->allowsNull()) {
      return NULL;
    }
    $illegal_value = new \stdClass();
    $argument = match (ltrim($parameter->getType()->__toString(), '?')) {
      'int' => match (TRUE) {
        is_int($value) => $value,
        !is_string($value) => $illegal_value,
        (string) (int) $value === $value => (int) $value,
        // Bad string not shaped like an integer.
        default => $illegal_value,
      },
      'bool' => (bool) $value,
      'array' => match (TRUE) {
        !is_string($value) => $illegal_value,
        trim($value) === '' => [],
        str_starts_with($value, '{') || str_starts_with($value, '[') => json_decode($value, TRUE, flags: JSON_THROW_ON_ERROR),
        default => preg_split('#, *#', trim($value)),
      },
      'string' => (string) $value,
      NotificationFrequency::class => NotificationFrequency::from($value),
      NodeInterface::class => Node::load(match (TRUE) {
        is_int($value) => $value,
        (string) (int) $value === $value => (int) $value,
      }),
      default => throw new \Exception(sprintf('Unsupported type %s for parameter %s', $parameter->getType()->__toString(), $parameter->name)),
    };
    if ($argument === $illegal_value) {
      throw new \Exception(sprintf("Illegal value %s for parameter %s", var_export($value, TRUE), $parameter->name));
    }
    return $argument;
  }

  /**
   * Creates a middleware callback for the http client.
   *
   * @param array $report
   *   A report array to be populated with request and response.
   *
   * @return \Closure
   *   The middleware callback.
   */
  protected function createReportingMiddleware(array &$report): \Closure {
    return function (callable $decorated_handler) use (&$report) {
      return function (
        RequestInterface $request,
        array $options = [],
      ) use ($decorated_handler, &$report) {
        $url = $request->getUri()->__toString();
        $url_parts = explode('?', $url, 2);
        parse_str($url_parts[1] ?? '', $query);
        $report[]['request'] = [
          'method' => $request->getMethod(),
          'url' => $url_parts[0],
          ...($query !== []) ? ['query' => $query] : [],
          // Unlike ->getContents(), ->__toString() is idempotent.
          ...$this->buildBodyReport($request->getBody()->__toString()),
          'body' => $request->getBody()->__toString(),
          'headers' => $request->getHeaders(),
        ];
        $promise = $decorated_handler($request, $options);
        assert($promise instanceof PromiseInterface);
        return $promise->then(
          function (ResponseInterface $response) use (&$report): ResponseInterface {
            $report[]['response'] = [
              'status' => $response->getStatusCode() . ' ' . $response->getReasonPhrase(),
              // Unlike ->getContents(), ->__toString() is idempotent.
              ...$this->buildBodyReport($response->getBody()->__toString()),
              'headers' => $response->getHeaders(),
            ];
            return $response;
          },
          function ($reason) use (&$report) {
            $report[]['failure reason'] = $reason;
            return Create::rejectionFor($reason);
          }
        );
      };
    };
  }

  /**
   * Creates a report about a response or request body which should be json.
   *
   * @param string $body
   *   The body text, which should be json.
   *
   * @return array
   *   Report.
   */
  protected function buildBodyReport(string $body): array {
    try {
      $data = json_decode($body, TRUE, flags: JSON_THROW_ON_ERROR);
      return [
        'data' => $data,
        'body' => $body,
      ];
    }
    catch (\JsonException) {
      return [
        'body' => $body,
      ];
    }
  }

  /**
   * Builds a render element to show a code snippet.
   *
   * @param string $code
   *   Code snippet.
   *
   * @return array
   *   Render element.
   */
  protected function buildCodeElement(string $code): array {
    return [
      '#markup' => new FormattableMarkup('<pre>@code</pre>', [
        '@code' => $code,
      ]),
    ];
  }

  /**
   * Renders a report array.
   *
   * @param array $report
   *   The report array.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The rendered report.
   */
  protected function renderReport(array $report): MarkupInterface {
    $text_parts = [];
    $replacements = [];
    $i = 0;
    foreach ($report as $report_section) {
      foreach ($report_section as $key => $value) {
        $title_key = '@title_' . $i;
        $value_key = '@value_' . $i;
        $text_parts[] = "<h4>$title_key</h4><pre>$value_key</pre>";
        $replacements[$title_key] = '[' . $key . ']';
        $replacements[$value_key] = Yaml::encode($this->formatForYaml($value));
        ++$i;
      }
    }
    return new FormattableMarkup(implode("\n", $text_parts), $replacements);
  }

  /**
   * Formats a value for export in a yaml report.
   *
   * @param mixed $input
   *   The input value.
   *
   * @return mixed
   *   The value suitable for yaml export.
   */
  protected function formatForYaml(mixed $input): mixed {
    if (is_array($input)) {
      return array_map($this->formatForYaml(...), $input);
    }
    if (is_object($input)) {
      if ($input instanceof \UnitEnum) {
        return new TaggedValue('enum', get_class($input) . '::' . $input->name);
      }
      if ($input instanceof EntityInterface) {
        return new TaggedValue('entity', get_class($input) . ' ' . ($input->id() ?? '#new'));
      }
      if ($input instanceof TaggedValue) {
        return new TaggedValue('TaggedValue.' . $input->getTag(), $input->getValue());
      }
      return new TaggedValue('object', get_class($input));
    }
    if (is_resource($input)) {
      return new TaggedValue('resource', get_resource_type($input));
    }
    return $input;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // No validation needed.
  }

}
