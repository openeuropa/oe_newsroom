<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Utility\Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Helper class to log exceptions in the Newsroom log channel.
 */
class ExceptionLogger {

  public function __construct(
    #[Autowire(service: 'logger.channel.oe_newsroom')]
    protected readonly LoggerInterface $logger,
  ) {}

  /**
   * Logs an exception with additional context.
   *
   * @param \Throwable $exception
   *   The exception.
   * @param string $message
   *   Message with additional context.
   *
   * @see \Drupal\Core\Utility\Error::logException()
   *
   * @todo Review this.
   */
  public function logException(\Throwable $exception, string $message): void {
    $lines = [];
    $last_exception = $exception;
    for ($e = $exception; $e !== NULL; $e = $e->getPrevious()) {
      $lines[] = (string) new FormattableMarkup(strtr(Error::DEFAULT_ERROR_MESSAGE, [' in ' => "<br>\nin "]), Error::decodeException($e));
      $last_exception = $e;
    }
    $message .= "<br>\n<br>\n" . implode("<br>\n<br>\n", $lines);
    // The backtrace from the last exception should be enough.
    $message .= "<br>\n<br>\n<pre>" . Html::escape(Error::formatBacktrace($last_exception->getTrace())) . '</pre>';
    $this->logger->error($message);
  }

}
