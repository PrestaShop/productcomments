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
<div id="post-product-comment-modal" class="modal fade" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h2>{l s='Write your review' mod='productcomments'}</h2>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-2 col-sm-2">
            {if isset($product) && $product}
              {block name='product_flags'}
                <ul class="product-flags">
                  {foreach from=$product.flags item=flag}
                    <li class="product-flag {$flag.type}">{$flag.label}</li>
                  {/foreach}
                </ul>
              {/block}

              {block name='product_cover'}
                <div class="product-cover">
                  {if $product.cover}
                    <img class="js-qv-product-cover" src="{$product.cover.bySize.medium_default.url}" alt="{$product.cover.legend}" title="{$product.cover.legend}" style="width:100%;" itemprop="image">
                  {else}
                    <img src="{$urls.no_picture_image.bySize.large_default.url}" style="width:100%;">
                  {/if}
                </div>
              {/block}
            {/if}
          </div>
          <div class="col-md-4 col-sm-4">
            <h3>{$product.name}</h3>
            {block name='product_description_short'}
              <div itemprop="description">{$product.description_short nofilter}</div>
            {/block}
          </div>
          <div class="col-md-6 col-sm-6">
            {if $criterions|@count > 0}
              <ul id="criterions_list">
                {foreach from=$criterions item='criterion'}
                  <li>
                    <div class="criterion_rating">
                      <label>{$criterion.name|escape:'html':'UTF-8'}:</label>
                      <div class="star_content">
                        <input class="star" type="radio" name="criterion[{$criterion.id_product_comment_criterion|round}]" value="1" />
                        <input class="star" type="radio" name="criterion[{$criterion.id_product_comment_criterion|round}]" value="2" />
                        <input class="star" type="radio" name="criterion[{$criterion.id_product_comment_criterion|round}]" value="3" />
                        <input class="star" type="radio" name="criterion[{$criterion.id_product_comment_criterion|round}]" value="4" />
                        <input class="star" type="radio" name="criterion[{$criterion.id_product_comment_criterion|round}]" value="5" checked="checked" />
                      </div>
                    </div>
                  </li>
                {/foreach}
              </ul>
            {/if}
          </div>
        </div>

        <div class="row">
          {if $allow_guests == true && !$logged}
            <div class="col-md-8 col-sm-8">
              <label class="form-label" for="comment_title">{l s='Title' mod='productcomments'}<sup class="required">*</sup></label>
            </div>
            <div class="col-md-4 col-sm-4">
              <label class="form-label" for="customer_name">{l s='Your name' mod='productcomments'}<sup class="required">*</sup></label>
            </div>
          {else}
            <div class="col-md-12 col-sm-12">
              <label class="form-label" for="comment_title">{l s='Title' mod='productcomments'}<sup class="required">*</sup></label>
            </div>
          {/if}
        </div>

        <div class="row">
          {if $allow_guests == true && !$logged}
            <div class="col-md-8 col-sm-8">
              <input name="comment_title" type="text" value=""/>
            </div>
            <div class="col-md-4 col-sm-4">
              <input name="customer_name" type="text" value=""/>
            </div>
          {else}
            <div class="col-md-12 col-sm-12">
              <input name="comment_title" type="text" value=""/>
            </div>
          {/if}
        </div>

        <div class="row">
          <div class="col-md-12 col-sm-12">
            <label class="form-label" for="comment_content">{l s='Review' mod='productcomments'}<sup class="required">*</sup></label>
          </div>
        </div>
        <div class="row">
          <div class="col-md-12 col-sm-12">
            <textarea name="comment_content"></textarea>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6 col-sm-6">
            <input id="id_product_comment_send" name="id_product" type="hidden" value='{$id_product_comment_form}' />
            <p class="required"><sup>*</sup> {l s='Required fields' mod='productcomments'}</p>
          </div>
          <div class="col-md-6 col-sm-6 post-comment-buttons">
            <button type="button" class="btn btn-comment-inverse btn-comment-big" data-dismiss="modal" aria-label="{l s='Cancel' mod='productcomments'}">
              {l s='Cancel' mod='productcomments'}
            </button>
            <button type="submit" class="btn btn-comment btn-comment-big">
              {l s='Send' mod='productcomments'}
            </button>
        </div>
        </div>
      </div>
    </div>
  </div>
</div>
