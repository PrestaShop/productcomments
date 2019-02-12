{if (!$recently_posted && ($logged || $allow_guests))}
  <button class="btn plain-edit-button post-product-comment">
    <i class="material-icons shopping-cart">edit</i>
    {l s='Be the first to write your review' mod='productcomments'} !
  </button>
{else}
  <p class="align_center">
    {l s='No customer reviews for the moment.' mod='productcomments'}
  </p>
{/if}
