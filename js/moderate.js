/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
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
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

function getCommentForm()
{
	if (document.forms)
		return (document.forms['comment_form']);
	else
		return (document.comment_form);
}

function getCommentDeleteForm()
{
	if (document.forms)
		return (document.forms['delete_comment_form']);
	else
		return (document.delete_comment_form);
}

function acceptComment(id)
{
	var form = getCommentForm();
	if (id)
		form.elements['id_product_comment'].value = id;
	form.elements['action'].value = 'accept';
	form.submit();
}


function deleteComment(id)
{
	var form = getCommentForm();
	if (id)
		form.elements['id_product_comment'].value = id;
	form.elements['action'].value = 'delete';
	form.submit();
}

function delComment(id, confirmation)
{
	var answer = confirm(confirmation);
	if (answer)
	{
		var form = getCommentDeleteForm();
		if (id)
			form.elements['delete_id_product_comment'].value = id;
		form.elements['delete_action'].value = 'delete';
		form.submit();
	}
}

function getCriterionForm()
{
	if (document.forms)
		return (document.forms['criterion_form']);
	else
		return (document.criterion_form);
}

function editCriterion(id)
{
	var form = getCriterionForm();
	form.elements['id_product_comment_criterion'].value = id;
	form.elements['criterion_name'].value = document.getElementById('criterion_name_' + id).value;
	form.elements['criterion_action'].value = 'edit';
	form.submit();
}

function deleteCriterion(id)
{
	var form = getCriterionForm();
	form.elements['id_product_comment_criterion'].value = id;
	form.elements['criterion_action'].value = 'delete';
	form.submit();
}

$( document ).ready(function() {
	$('select#id_product_comment_criterion_type').change(function() {
		// PS 1.6
		$('#categoryBox').closest('div.form-group').hide();
		$('#ids_product').closest('div.form-group').hide();
		// PS 1.5
		$('#categories-treeview').closest('div.margin-form').hide();
		$('#categories-treeview').closest('div.margin-form').prev().hide();
		$('#ids_product').closest('div.margin-form').hide();
		$('#ids_product').closest('div.margin-form').prev().hide();

		if (this.value == 2)
		{
			$('#categoryBox').closest('div.form-group').show();
			// PS 1.5
			$('#categories-treeview').closest('div.margin-form').show();
			$('#categories-treeview').closest('div.margin-form').prev().show();
		}
		else if (this.value == 3)
		{
			$('#ids_product').closest('div.form-group').show();
			// PS 1.5
			$('#ids_product').closest('div.margin-form').show();
			$('#ids_product').closest('div.margin-form').prev().show();
		}
	});

	$('select#id_product_comment_criterion_type').trigger( "change" );
});
