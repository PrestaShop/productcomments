{**
 * 2007-2019 PrestaShop and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
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
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 *}

<div class="product-comment-list-item">
  <div class="comment_author">
    <span>{l s='Grade' mod='productcomments'}&nbsp</span>
    {include file='module:productcomments/views/templates/hook/grade-stars.tpl' grade=5}
    <div class="comment_author_infos">
      <strong>@CUSTOMER_NAME@</strong><br/>
      <em>@COMMENT_DATE@</em>
    </div>
  </div>
  <div class="comment_details">
    <h4 class="title_block">@COMMENT_TITLE@</h4>
    <p>@COMMENT_COMMENT@</p>
    <ul>
      <li>
        {l s='%0 out of %1 people found this review useful.' sprintf=['@COMMENT_USEFUL_ADVICES@','@COMMENT_TOTAL_ADVICES@'] mod='productcomments'}
      </li>
      <li>
        {l s='Was this comment useful to you?' mod='productcomments'}
        <button class="usefulness_btn" data-is-usefull="1" data-id-product-comment="@COMMENT_ID@">
          {l s='yes' mod='productcomments'}
        </button>
        <button class="usefulness_btn" data-is-usefull="0" data-id-product-comment="@COMMENT_ID@">
          {l s='no' mod='productcomments'}
        </button>
      </li>
      <li>
        <span class="report_btn" data-id-product-comment="@COMMENT_ID@">
          {l s='Report abuse' mod='productcomments'}
        </span>
      </li>
    </ul>
  </div>
</div>
