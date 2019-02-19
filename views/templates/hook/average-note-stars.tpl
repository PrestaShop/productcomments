{if $nbComments != 0}
  <div class="comments_note">
    <span>{l s='Grade' mod='productcomments'}</span>
    {include file='module:productcomments/views/templates/hook/grade-stars.tpl' grade=$average_total}
  </div>
{/if}
