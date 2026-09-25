<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_newsroom\Endpoint\ExternalAuthEndpoints;
use Drupal\oe_newsroom\Value\Sentinel\Unauthorized;
use Drupal\Tests\oe_newsroom\Constraint\AssocValuesMatch;
use Drupal\Tests\oe_newsroom\Traits\LocalTestValuesTrait;
use Drupal\Tests\oe_newsroom\Traits\TryAndCatchTrait;
use Drupal\Tests\oe_newsroom\Traits\VcrTrait;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\Constraint\RegularExpression;

/**
 * Tests the ExternalAuthEndpoints class.
 */
class ExternalAuthEndpointsTest extends KernelTestBase {

  use LocalTestValuesTrait;
  use TryAndCatchTrait;
  use VcrTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_newsroom',
    'oe_newsroom_vcr',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig([
      'oe_newsroom',
    ]);

    $this->initializeNewsroomAndVcrWithTestValues();
  }

  /**
   * Tests a successful call to ->tokenEmail().
   */
  public function testTokenEmail(): void {
    $external_auth_endpoints = \Drupal::service(ExternalAuthEndpoints::class);

    $this->startVcr(__METHOD__);

    // A call with a path-less url will be rejected.
    // No API request will be made - as can be seen in the VCR recording.
    $this->tryAndCatch(
      fn () => $external_auth_endpoints->tokenEmail(
        'testuser@example.com',
        'https://example.com',
        'Action button text.',
      ),
      \InvalidArgumentException::class,
      "Expected a url with path, found 'https://example.com'.",
    );

    $this->vcrComment('Request to send an email with an authentication link.');
    // In recording mode, the email will be sent to the email address provided
    // by the developer, allowing them to inspect the shape of the email.
    $external_auth_endpoints->tokenEmail(
      $this->newsroomTestValues->subscriberEmail,
      'https://example.com/external-auth-destination',
      'Action button text.',
    );

    $this->endVcr();

    if (!$this->isRecording()) {
      $this->assertVcrCaptured(
        ['<signature key 0>'],
        ['2283cafb8c33c5d2be3c1db74199513c'],
      );
    }
  }

  /**
   * Tests a successful call to ->tokenNomail().
   */
  public function testTokenNomail(): void {
    $email = 'test@example.com';
    $external_auth_endpoints = \Drupal::service(ExternalAuthEndpoints::class);
    $this->startVcr(__METHOD__);

    $this->vcrComment('Request an authentication token, without sending an email.');
    $this->assertThat(
      $external_auth_endpoints->tokenNomail($email),
      new AssocValuesMatch([
        'token' => new RegularExpression('#^[a-zA-Z0-9]{16}$#'),
        'expiration_date' => new RegularExpression('#\d\d\d\d-\d\d-\d\dT\d\d:\d\d:\d\d\.\d+Z#'),
      ]),
    );

    $this->endVcr();

    if (!$this->isRecording()) {
      $this->assertVcrCaptured(
        ['<signature key 0>'],
        ['2283cafb8c33c5d2be3c1db74199513c'],
      );
    }
  }

  /**
   * Tests a successful call to ->login().
   */
  public function testLoginSuccess(): void {
    $email_original = 'tesTUVw1@example.com';
    $email_variant = 'testUvw1@example.com';
    $this->assertSame(mb_strtolower($email_original), mb_strtolower($email_variant));
    $external_auth_endpoints = \Drupal::service(ExternalAuthEndpoints::class);

    if ($this->isRecording()) {
      $token = $external_auth_endpoints->tokenNomail($email_original)['token'];
    }
    else {
      $token = 'testToken1234567';
    }

    $this->startVcr(__METHOD__);

    $this->vcrComment(<<<'COMMENT'
Verify an authentication token.
The email address stored in Newsroom uses different capitalization.
COMMENT
    );
    $result = $external_auth_endpoints->login($email_variant, $token);
    $this->assertThat(
      $result,
      new AssocValuesMatch([
        'user_email' => $email_original,
        'user_id' => new IsType(IsType::TYPE_INT),
      ]),
    );

    $this->endVcr();

    if (!$this->isRecording()) {
      $this->assertVcrCaptured(
        ['<login token 0>'],
        ['0f017da7f43eef5190070257bdd2b198'],
      );
    }
  }

  /**
   * Tests a failed call to ->login() with an invalid token.
   */
  public function testLoginBadToken(): void {
    $external_auth_endpoints = \Drupal::service(ExternalAuthEndpoints::class);
    $this->startVcr(__METHOD__);

    $this->vcrComment('Attempt to verify an invalid authentication token.');
    $this->assertSame(
      Unauthorized::Instance,
      $external_auth_endpoints->login(
        'test@example.com',
        'randomToken12345',
      ),
    );

    $this->endVcr();

    if (!$this->isRecording()) {
      $this->assertVcrCaptured(
        ['<login token 0>'],
        ['7f4755550f222ed4e28494087eb7a982'],
      );
    }
  }

  /**
   * Tests a failed call to ->login() with an unknown email address.
   */
  public function testLoginUnknownEmail(): void {
    $external_auth_endpoints = \Drupal::service(ExternalAuthEndpoints::class);
    $this->startVcr(__METHOD__);

    $this->vcrComment('Attempt to verify an authentication token for an unknown email address.');
    $this->assertSame(
      Unauthorized::Instance,
      $external_auth_endpoints->login(
        'test123unknown@example.com',
        'irrelevantToken1',
      ),
    );

    $this->endVcr();

    if (!$this->isRecording()) {
      $this->assertVcrCaptured(
        ['<login token 0>'],
        ['94b4907e3d84522241b15e76ae8947a4'],
      );
    }
  }

}
