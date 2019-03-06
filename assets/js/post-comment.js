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
  $('body').on('click', '.post-product-comment', function (event) {
    event.preventDefault();
    showPostCommentModal();
  });

  const postCommentModal = $('#post-product-comment-modal');
  postCommentModal.on('hidden.bs.modal', function () {
    postCommentModal.modal('hide');
    clearPostCommentForm();
  });

  const commentPostedModal = $('#product-comment-posted-modal');
  commentPostedModal.on('hidden.bs.modal', function () {
    commentPostedModal.modal('hide');
  });

  const commentPostErrorModal = $('#product-comment-post-error');
  commentPostErrorModal.on('hidden.bs.modal', function () {
    commentPostErrorModal.modal('hide');
  });

  function showPostCommentModal() {
    commentPostedModal.modal('hide');
    commentPostErrorModal.modal('hide');
    postCommentModal.modal('show');
  }

  function showCommentPostedModal() {
    postCommentModal.modal('hide');
    commentPostErrorModal.modal('hide');
    clearPostCommentForm();
    commentPostedModal.modal('show');
  }

  function showPostErrorModal(errorMessage) {
    postCommentModal.modal('hide');
    commentPostedModal.modal('hide');
    clearPostCommentForm();
    $('#product-comment-post-error-message').html(errorMessage);
    commentPostErrorModal.modal('show');
  }

  function clearPostCommentForm() {
    $('#post-product-comment-form input[type="text"]').val('');
    $('#post-product-comment-form input[type="text"]').removeClass('valid error');
    $('#post-product-comment-form textarea').val('');
    $('#post-product-comment-form textarea').removeClass('valid error');
    $('#post-product-comment-form .criterion-rating input').val(3).change();
  }

  function initCommentModal() {
    $('#post-product-comment-modal .grade-stars').rating();
    $('body').on('click', '.post-product-comment', function (event) {
      event.preventDefault();
      showPostCommentModal();
    });

    $('#post-product-comment-form').submit(submitCommentForm);
  }

  function submitCommentForm(event) {
    event.preventDefault();
    var formData = $(this).serializeArray();
    if (!validateFormData(formData)) {
      return;
    }
    $.post($(this).attr('action'), $(this).serialize(), function(jsonResponse) {
      var jsonData = false;
      try {
        jsonData = JSON.parse(jsonResponse);
      } catch (e) {
      }
      if (jsonData) {
        if (jsonData.success) {
          clearPostCommentForm();
          showCommentPostedModal();
        } else {
          showPostErrorModal(jsonData.error);
        }
      } else {
        showPostErrorModal('Sorry, your review could not be posted.');
      }
    }).fail(function(result) {
      showPostErrorModal('Sorry, your review could not be posted.');
    });
  }

  function validateFormData(formData) {
    var isValid = true;
    formData.forEach(function(formField) {
      const fieldSelector = '#post-product-comment-form [name="'+formField.name+'"]';
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

  initCommentModal();
});
