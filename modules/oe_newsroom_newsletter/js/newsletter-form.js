/**
 * @file
 * AJAX command used by the newsletter subscribe and unsubscribe forms.
 */

(function ($, Drupal) {
  /**
   * Replaces the marked wrapper of the submitted newsletter form.
   *
   * Targets the outermost ancestor of the submitted form that carries the
   * data-oe-newsroom-newsletter-wrapper attribute, so markup rendered around
   * the form, such as the block title, is replaced together with the form.
   * Falls back to the form element itself when no marked ancestor is present.
   *
   * @param {Drupal.Ajax} ajax
   *   The Drupal Ajax object.
   * @param {object} response
   *   The server response, holding the replacement markup in response.data.
   */
  Drupal.AjaxCommands.prototype.oeNewsroomNewsletterReplaceFormWrapper =
    function (ajax, response) {
      const $ancestors = $(ajax.element).parents(
        '[data-oe-newsroom-newsletter-wrapper]',
      );
      response.selector = $ancestors.length
        ? $ancestors.last()
        : $(ajax.element).closest('form');
      response.method = 'replaceWith';
      this.insert(ajax, response);
    };
})(jQuery, Drupal);
