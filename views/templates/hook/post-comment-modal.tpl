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
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6 col-sm-6 hidden-xs-down">
            <h2 class="title">{l s='Write your review' mod='productcomments'}</h2>
            {if isset($product) && $product}
              <div class="product clearfix">
                <img src="{$cover_image}" height="{$medium_size.height}" width="{$medium_size.width}" alt="{$product->name|escape:html:'UTF-8'}" />
                <div class="product_desc">
                  <p class="product_name"><strong>{$product->name}</strong></p>
                  {$product->description_short}
                </div>
              </div>
            {/if}
          </div>
          <div class="col-md-6 col-sm-6">
            <h1 class="h1">{$product.name}</h1>
            {block name='product_description_short'}
              <div id="product-description-short" itemprop="description">{$product.description_short nofilter}</div>
            {/block}
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
