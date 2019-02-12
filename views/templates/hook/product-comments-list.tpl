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

<script type="text/javascript">
  var productcomments_controller_url = '{$productcomments_controller_url}';
  var confirm_report_message = '{l s='Are you sure that you want to report this comment?' mod='productcomments' js=1}';
  var secure_key = '{$secure_key}';
  var productcomments_url_rewrite = '{$productcomments_url_rewriting_activated}';
  var productcomment_added = '{l s='Your comment has been added!' mod='productcomments' js=1}';
  var productcomment_added_moderation = '{l s='Your comment has been submitted and will be available once approved by a moderator.' mod='productcomments' js=1}';
  var productcomment_title = '{l s='New comment' mod='productcomments' js=1}';
  var productcomment_ok = '{l s='OK' mod='productcomments' js=1}';
  var moderation_active = {$moderation_active};
</script>

<div class="row" id="product-comments-list-header">
  <div class="col-md-6 comments-nb">
    <i class="material-icons shopping-cart">chat</i>
    {l s='Comments' mod='productcomments'} ({$nbComments})
  </div>
  <div class="col-md-6">
    {include file='module:productcomments/views/templates/hook/average-note-stars.tpl'}
  </div>
</div>

<div class="row" id="product-comments-list">
  {if $nbComments > 0}
    {foreach from=$comments item=comment}
      {if $comment.content}
        <div class="comment clearfix">
          <div class="comment_author">
            <span>{l s='Grade' mod='productcomments'}&nbsp</span>
            <div class="star_content clearfix">
              {section name="i" start=0 loop=5 step=1}
                {if $comment.grade le $smarty.section.i.index}
                  <div class="star"></div>
                {else}
                  <div class="star star_on"></div>
                {/if}
              {/section}
            </div>
            <div class="comment_author_infos">
              <strong>{$comment.customer_name|escape:'html':'UTF-8'}</strong><br/>
              <em>{dateFormat date=$comment.date_add|escape:'html':'UTF-8' full=0}</em>
            </div>
          </div>
          <div class="comment_details">
            <h4 class="title_block">{$comment.title}</h4>
            <p>{$comment.content|escape:'html':'UTF-8'|nl2br}</p>
            <ul>
              {if $comment.total_advice > 0}
                <li>{l s='%1$d out of %2$d people found this review useful.' sprintf=[$comment.total_useful,$comment.total_advice] mod='productcomments'}</li>
              {/if}
              {if $logged}
                {if !$comment.customer_advice}
                  <li>{l s='Was this comment useful to you?' mod='productcomments'}<button class="usefulness_btn" data-is-usefull="1" data-id-product-comment="{$comment.id_product_comment}">{l s='yes' mod='productcomments'}</button><button class="usefulness_btn" data-is-usefull="0" data-id-product-comment="{$comment.id_product_comment}">{l s='no' mod='productcomments'}</button></li>
                {/if}
                {if !$comment.customer_report}
                  <li><span class="report_btn" data-id-product-comment="{$comment.id_product_comment}">{l s='Report abuse' mod='productcomments'}</span></li>
                {/if}
              {/if}
            </ul>
          </div>
        </div>
      {/if}
    {/foreach}
    {if (!$recently_posted && ($logged || $allow_guests))}
      <p class="align_center">
        <a id="new_comment_tab_btn" class="open-comment-form" href="#new_comment_form">{l s='Write your review' mod='productcomments'} !</a>
      </p>
    {/if}
  {else}
    {include file='module:productcomments/views/templates/hook/empty-list.tpl'}
  {/if}
</div>
