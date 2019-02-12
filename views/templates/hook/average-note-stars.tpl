{if $nbComments != 0}
  <div class="comments_note">
    <span>{l s='Average grade' mod='productcomments'}&nbsp</span>
    <div class="star_content clearfix">
      {section name="i" start=0 loop=5 step=1}
        {if $averageTotal le $smarty.section.i.index}
          <div class="star"></div>
        {else}
          <div class="star star_on"></div>
        {/if}
      {/section}
    </div>
  </div>
{/if}
