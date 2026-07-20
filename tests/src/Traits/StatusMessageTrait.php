<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom\Traits;

use Drupal\Component\Serialization\Yaml;

/**
 * Trait to find and assert status messages.
 *
 * The selectors in this trait assume that 'stark' theme is used on the pages
 * where the messages appear.
 *
 * Differences to similar methods in Drupal core tests:
 *   - On failure, the actual messages are displayed.
 *   - Any unexpected message is considered a failure, and will be reported.
 *   - The expected message can contain regular expression sub-patterns.
 *   - Regular expression matches are returned for further assertions.
 */
trait StatusMessageTrait {

  /**
   * Asserts exact status messages.
   *
   * @param array<'status'|'warning'|'error', list<string>> $expected
   *   Expected status messages by type.
   *   This can contain placeholder tokens to be replaced by regular expression
   *   sub-patterns.
   *   This is compared against normalized/formatted actual html extracted from
   *   the page.
   * @param array<string, string> $subpatterns
   *   Regular expression sub-patterns by placeholder token.
   *
   * @return array<'status'|'warning'|'error', array<int, list<string>>>
   *   Regular expression matches per message.
   */
  protected function assertStatusMessages(array $expected, array $subpatterns = []): array {
    $actual = $this->findStatusMessages();
    $map_counts = fn (array $values) => array_map(count(...), $values);
    $this->assertSame($map_counts($expected), $map_counts($actual), "Actual messages:\n" . Yaml::encode($actual));
    $matches_by_message = [];
    foreach ($expected as $type => $expected_for_type) {
      foreach ($expected_for_type as $i => $expected_message) {
        // If the expected message does not contain any regex subpatterns,
        // compare it as-is.
        if (!$subpatterns || strtr($expected_message, $subpatterns) === $expected_message) {
          $this->assertSame($expected_message, $actual[$type][$i], "Message $type:$i");
          continue;
        }
        $pattern = '#^' . strtr(preg_quote($expected_message, '#'), $subpatterns) . '$#';
        if (!preg_match($pattern, $actual[$type][$i], $matches_by_message[$type][$i])) {
          $this->fail("Message $type:$i:\n" . $actual[$type][$i]);
        }
        $this->addToAssertionCount(1);
      }
    }
    return $matches_by_message;
  }

  /**
   * Finds inner html of all status messages, grouped by type.
   *
   * The selectors in this method are fragile, and only work with 'stark' theme.
   * Future versions of 'stark' theme might behave differently.
   *
   * @return array<'status'|'warning'|'error', list<string>>
   *   Lists of status messages inner html, grouped by type.
   *   The html is somewhat normalized/formatted.
   */
  protected function findStatusMessages(): array {
    // Find the container that holds all status messages.
    $container = $this->getSession()->getPage()->find('css', '[data-drupal-messages]');
    if (!$container) {
      // No messages on this page.
      return [];
    }
    // Status messages are grouped by type (status, warning, error).
    $groups = $container->findAll('css', ':scope > [role=contentinfo]');
    if (!$groups) {
      // Unexpected html structure.
      // Perhaps a different theme than stark was used?
      $this->fail($container->getOuterHtml());
    }
    $messages = [];
    foreach ($groups as $group) {
      // The group label indicates the type. It is found in an aria attribute,
      // and in a <h2> tag. The aria attribute is easier to read from.
      $heading_text = $group->getAttribute('aria-label');
      $type = match ($heading_text) {
        'Status message' => 'status',
        'Warning message' => 'warning',
        'Error message' => 'error',
      };
      // In stark theme, a group with type 'error' has an additional wrapper.
      if ($type === 'error') {
        $group = $group->find('css', ':scope > [role=alert]') ?? $this->fail($group->getOuterHtml());
      }
      // Multiple messages in a group are wrapped in ul/li tags.
      $ul = $group->find('css', ':scope > ul');
      if ($ul) {
        $li_elements = $ul->findAll('css', ':scope > li');
        foreach ($li_elements as $li) {
          $messages[$type][] = trim($li->getHtml());
        }
      }
      else {
        // A single message in a group is not wrapped in ul/li.
        $group_heading = $group->find('css', ':scope > h2')
          ?? $this->fail($group->getOuterHtml());
        $message_html = explode($group_heading->getOuterHtml(), $group->getHtml(), 2)[1]
          ?? $this->fail($group->getOuterHtml());
        $messages[$type][] = trim($message_html);
      }
      // Attempt to "normalize" the html of each message, so that assertions
      // become stable and readable.
      $messages[$type] = array_map($this->normalizeStatusMessageHtml(...), $messages[$type]);
    }
    return $messages;
  }

  /**
   * Normalizes and formats the html of one status message.
   *
   * The logic here is "good enough" for status messages that are being tested
   * in this module.
   *
   * @param string $message_html
   *   Raw html of the message, as captured from the page.
   *
   * @return string
   *   Formatted/normalized html.
   */
  protected function normalizeStatusMessageHtml(string $message_html): string {
    $dom = new \DOMDocument();
    $dom->preserveWhiteSpace = FALSE;
    $dom->formatOutput = TRUE;
    $dom->loadHTML("<div>$message_html</div>", LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $parts = [];
    foreach ($dom->firstChild->childNodes as $childNode) {
      $parts[] = trim($dom->saveHTML($childNode));
    }
    return implode("\n", array_filter($parts));
  }

}
