<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_newsroom_newsletter\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\oe_newsroom_newsletter\Traits\OeNewsroomNewsletterTrait;
use Drupal\user\Entity\Role;

/**
 * Test the subscription through the newsletter field.
 *
 * @group oe_newsroom_newsletter
 */
class SubscriptionFieldTest extends BrowserTestBase {

  use OeNewsroomNewsletterTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_ui',
    'node',
    'oe_newsroom',
    'oe_newsroom_newsletter',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setApiPrivateKey();
    $this->enableMock();
    $this->configureNewsroom();
    $this->configureNewsletter();
    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), ['manage own newsletter subscription']);
  }

  /**
   * Test the subscription field if a user can subscribe to a newsletter.
   *
   * @group oe_newsroom_newsletter
   */
  public function testSubscriptionField(): void {
    $node_type = $this->drupalCreateContentType();
    $user = $this->createUser([
      'administer node display',
      'administer node form display',
      'create ' . $node_type->id() . ' content',
      'edit any ' . $node_type->id() . ' content',
    ]);
    $this->drupalLogin($user);

    /** @var \Drupal\field\FieldStorageConfigInterface $storage */
    $storage = $this->container->get('entity_type.manager')
      ->getStorage('field_storage_config')
      ->create([
        'type' => 'oe_newsroom_newsletter',
        'field_name' => 'newsletter',
        'entity_type' => 'node',
      ]);
    $storage->save();

    $this->container->get('entity_type.manager')
      ->getStorage('field_config')
      ->create([
        'field_storage' => $storage,
        'bundle' => $node_type->id(),
      ])
      ->save();

    // Configure manage form display fields.
    $edit = [
      'fields[newsletter][region]' => 'content',
    ];
    $this->drupalGet('/admin/structure/types/manage/' . $node_type->id() . '/form-display');
    $this->submitForm($edit, 'Save');
    $this->drupalGet('/admin/structure/types/manage/' . $node_type->id() . '/form-display');
    $this->assertSession()->fieldValueEquals('fields[newsletter][region]', 'content');

    // Configure manage display fields.
    $edit = [
      'fields[newsletter][type]' => 'oe_newsroom_newsletter_subscribe_form',
      'fields[newsletter][region]' => 'content',
    ];
    $this->drupalGet('/admin/structure/types/manage/' . $node_type->id() . '/display');
    $this->submitForm($edit, 'Save');
    $this->drupalGet('/admin/structure/types/manage/' . $node_type->id() . '/display');
    $this->assertSession()->fieldValueEquals('fields[newsletter][region]', 'content');
    $this->assertSession()->fieldValueEquals('fields[newsletter][type]', 'oe_newsroom_newsletter_subscribe_form');

    // Create node without checked 'Enable newsletter subscriptions' (default).
    $node = $this->drupalCreateNode([
      'type' => $node_type->id(),
      'title' => 'Newsletter 1',
    ]);
    $this->drupalLogout();
    // Subscribe form will not be displayed.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->pageTextContains('Newsletter 1');
    $this->assertSession()->pageTextNotContains('This is the introduction text.');
    $this->assertSession()->pageTextNotContains('Your e-mail');
    $this->assertSession()->pageTextNotContains('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->assertSession()->buttonNotExists('Subscribe');

    // Enable newsletter subscriptions.
    $this->drupalLogin($user);
    $this->drupalGet("node/" . $node->id() . "/edit");
    $this->getSession()->getPage()->checkField('Enable newsletter subscriptions');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains($node_type->id() . ' Newsletter 1 has been updated.');
    $this->drupalLogout();

    // Subscribe to the newsletter.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->pageTextContains('Newsletter 1');
    $this->assertSession()->pageTextContains('This is the introduction text.');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');

    // Change a field type to display unsubscribe form.
    $this->drupalLogin($user);
    $edit = [
      'fields[newsletter][type]' => 'oe_newsroom_newsletter_unsubscribe_form',
    ];
    $this->drupalGet('/admin/structure/types/manage/' . $node_type->id() . '/display');
    $this->submitForm($edit, 'Save');
    $this->assertSession()->fieldValueEquals('fields[newsletter][type]', 'oe_newsroom_newsletter_unsubscribe_form');
    $this->drupalLogout();

    // Unsubscribe from the newsletter.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->pageTextContains('Newsletter 1');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->pressButton('Unsubscribe');
    $this->assertSession()->pageTextContains('Successfully unsubscribed!');

    // Disable newsletter subscriptions.
    $this->drupalLogin($user);
    $this->drupalGet("node/" . $node->id() . "/edit");
    $this->getSession()->getPage()->uncheckField('Enable newsletter subscriptions');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains($node_type->id() . ' Newsletter 1 has been updated.');
    $this->drupalLogout();

    // Unsubscribe form will not be displayed.
    $this->drupalGet($node->toUrl());
    $this->assertSession()->pageTextContains('Newsletter 1');
    $this->assertSession()->pageTextNotContains('Your e-mail');
    $this->assertSession()->buttonNotExists('Unsubscribe');
  }

}
