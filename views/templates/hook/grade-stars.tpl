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

<div class="grade_stars">
  <div class="star_content clearfix">
    {section name="i" start=0 loop=5 step=1}
      <div class="star"></div>
    {/section}
  </div>
  <div class="star_content clearfix" alt="{$grade}">
    {section name="j" start=0 loop=5 step=1}
      {assign var="star_width" value=max(0, min(1, $grade - $smarty.section.j.index))*20}
      <div class="star_on" style="width: {$star_width}px"></div>
    {/section}
  </div>
</div>
