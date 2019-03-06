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
  const commentsList = $('#product-comments-list');
  const emptyProductComment = $('#empty-product-comment');
  const commentsListUrl = commentsList.data('list-comments-url');
  const commentPrototype = commentsList.data('comment-item-prototype');

  emptyProductComment.hide();
  $('.grade-stars').rating();

  function paginateComments(page) {
    $.get(commentsListUrl, {page: page}, function(result) {
      const jsonResponse = JSON.parse(result);
      if (jsonResponse.comments && jsonResponse.comments.length > 0) {
        populateComments(jsonResponse.comments);
        $('#product-comments-list-pagination').pagination({
          currentPage: page,
          items: jsonResponse.comments_nb,
          itemsOnPage: jsonResponse.comments_per_page,
          cssStyle: '',
          prevText: '<i class="material-icons">chevron_left</i>',
          nextText: '<i class="material-icons">chevron_right</i>',
          useAnchors: false,
          onPageClick: paginateComments
        });
      } else {
        commentsList.html('');
        emptyProductComment.show();
        commentsList.append(emptyProductComment);
      }
    });
  }

  function populateComments(comments) {
    commentsList.html('');
    comments.forEach(addComment);
  }

  function addComment(comment) {
    var commentTemplate = commentPrototype;
    commentTemplate = commentTemplate.replace(/@COMMENT_ID@/, comment.id_product_comment);
    commentTemplate = commentTemplate.replace(/@PRODUCT_ID@/, comment.id_product);
    commentTemplate = commentTemplate.replace(/@CUSTOMER_NAME@/, comment.customer_name);
    commentTemplate = commentTemplate.replace(/@COMMENT_DATE@/, comment.date_add);
    commentTemplate = commentTemplate.replace(/@COMMENT_TITLE@/, comment.title);
    commentTemplate = commentTemplate.replace(/@COMMENT_COMMENT@/, comment.content);
    commentTemplate = commentTemplate.replace(/@COMMENT_USEFUL_ADVICES@/, comment.usefulness);
    commentTemplate = commentTemplate.replace(/@COMMENT_NOT_USEFUL_ADVICES@/, comment.total_usefulness);
    commentTemplate = commentTemplate.replace(/@COMMENT_TOTAL_ADVICES@/, (comment.total_usefulness - comment.usefulness));

    const $comment = $(commentTemplate);
    $('.grade-stars', $comment).rating({
      value: comment.grade
    });

    commentsList.append($comment);
  }

  paginateComments(1);
});
