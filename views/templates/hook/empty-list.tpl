{if (!$recently_posted && ($logged || $allow_guests))}
  <button class="btn btn-comment btn-comment-big post-product-comment">
    <i class="material-icons shopping-cart">edit</i>
    {l s='Be the first to write your review' mod='productcomments'}
  </button>
{else}
  {l s='No customer reviews for the moment.' mod='productcomments'}
{/if}
