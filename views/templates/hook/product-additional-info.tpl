 {*
* 2007-2016 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2016 PrestaShop SA
*  @version  Release: $Revision$
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{if $nbComments != 0 || ($recently_posted == false && ($logged || $allow_guests))}
<div class="product_comments_additional_info">
  {include file='module:productcomments/views/templates/hook/average-note-stars.tpl'}

	<div class="comments_advices">
		{if $nbComments != 0}
		<a class="btn" href="#product-comments-list-header">
      {l s='Read user reviews' mod='productcomments'} ({$nbComments})
    </a>
		{/if}

		{if ($recently_posted == false && ($logged || $allow_guests))}
		<button class="btn btn-comment post-product-comment">
      <i class="material-icons shopping-cart">edit</i>
      {l s='Write your review' mod='productcomments'}
    </button>
		{/if}
	</div>
</div>
{/if}
