@api
Feature: Newsroom settings tests.
  In order to have a example of behat test
  As an anonymous
  I need to be able to see the homepage

  Scenario: As a user, I want to access to the Newsletter subscription
  form, so that I can subscribe to the newsletter(s).
    Given I am on "newsletter/subscribe"
