var vatContainer = '';

var vatTooltip = {
  fillContent: function(dom, is_show_vat, vat) {
    let content = `
    <div class="vat-icon__container">
      <div class="vat-tip">This Buyer is eligible for VAT-free purchase.</div>
    `
    if(is_show_vat) {
      content += `
        <div class="vat-number">
          <div class="vat-number__text-container">
            <div class="vat-number__label">
              <span class="vat-number__label-text">
                View the Buyer's VAT number 
              </span>
              <i class="icon-down giga icon-V10_shouyeyouxiadown"></i>
              <i class="icon-up giga icon-V10_shouyeyouxiaTOP"></i>
            </div>
            <div class="vat-number__text">
              ${vat}
            </div>
          </div>
        <div>
      </div>
      `
    }
  
    $(dom).popover({
      placement: function (context, source) {
        var position = $(source).parent().parent().position();
        var placementVal = is_show_vat ? 355 : 110;
        if (position.top < placementVal){
            return "bottom";
        }
        if (position.top >= placementVal){
          return "top";
        }
        return "top";
      },
      //container: 'body',
      content: function() {
        return content;
      }
    });
    $(dom).popover("show");
  }
}

// 外部点击 dismiss popover
$(document).click(function() {
  $('.vat-icon-container .vat-icon').popover("hide");
});

$(document).ready(function() {
  $(document).on('mouseenter', '.vat-icon-container', function(event) {
    event.stopPropagation();
  })
  
  $(document).on('click', '.vat-number__text', function(event) {
    event.stopPropagation();
  })
  
  $(document).on('click', '.vat-icon__container', function(event) {
    event.stopPropagation();
  })
  
  // 绑定点击图标事件
  $(document).on('click', '.vat-icon-container .vat-icon', function(event) {
    let is_show_vat = $(this).data('is_show_vat');
    let vat = $(this).data('vat');
    vatTooltip.fillContent(this, is_show_vat, vat);
    event.stopPropagation();
  })
  
  $(document).on('click', '.vat-icon__container .vat-number__text-container', function(event) {
    let isVisible = $(this).find('.vat-number__text').css('visibility') == 'hidden';
    $(this).find('.vat-number__text').css('visibility', isVisible ? 'visible' : 'hidden');
    if (isVisible) {
      $(this).find('.icon-down').hide();
      $(this).find('.icon-up').show();
    } else {
      $(this).find('.icon-down').show();
      $(this).find('.icon-up').hide();
    }
    event.stopPropagation();
  })
})

