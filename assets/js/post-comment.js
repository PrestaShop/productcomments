/**
 * 2007-2019 PrestaShop and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */


jQuery(document).ready(function () {
  const $ = jQuery;
  console.log('init product comments');
  $('body').on('click', '.post-product-comment', function (event) {
    event.preventDefault();
    showProductCommentModal();
  });

  function showProductCommentModal() {
    var productModal = $('#post-product-comment-modal');
    productModal.modal('show');
    productModal.on('hidden.bs.modal', function () {
      productModal.hide();
    });
  }

  function initCommentModal() {
    $('#post-product-comment-modal input.star').rating();
    $('body').on('click', '.post-product-comment', function (event) {
      event.preventDefault();
      showProductCommentModal();
    });

    $('#post-product-comment-form').submit(function(event) {
      event.preventDefault();
      var formData = $(this).serializeArray();
      if (!validateFormData(formData)) {
        return;
      }
      $.post($(this).attr('action'), $(this).serialize(), function(result) {
        console.log('success', result);
      }).fail(function(result) {
        console.log('fail', result);
      });
    });

    function validateFormData(formData) {
      var isValid = true;
      formData.forEach(function(formField) {
        const fieldSelector = '#post-product-comment-form [name="'+formField.name+'"]';
        console.log(fieldSelector, formField.value);
        if (!formField.value) {
          $(fieldSelector).addClass('error');
          $(fieldSelector).removeClass('valid');
          isValid = false;
        } else {
          $(fieldSelector).removeClass('error');
          $(fieldSelector).addClass('valid');
        }
      });

      return isValid;
    }
  }

  initCommentModal();
});
