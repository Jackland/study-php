// oris相关新组件交互
var orisComponents = {
  // Input框的清除按钮事件绑定
  clearInputIcon() {
    // input change事件绑定
    $('.action-clear').on('change', 'input', function() {
      orisComponents.triggerInputClear(this);
    })
    $('.action-clear').on('click', '.oris-clear', function(event) {
      // 清楚input内容
      $(this).siblings('input').val('');
      orisComponents.hideClearIcon(this);
      // 如果是日期选择范围，有日期选择范围的联动，则默认清空选择Anytime class: action-clear-date-range
      // data-action-range标记有清楚range的clear
      var isClearRange = $(this).attr('data-action-range');
      var $select = $(this).parents('form').find('.action-clear-date-range select');
      if (isClearRange=='1' && $select && $select.length ==1 ) {
        $select.selectpicker('val', 'anytime');
      }
    })
  },
  initClearInputIcon() {
    // 根据input是否有内容初始化icon
    $('.action-clear input').each((index, one) => {
      orisComponents.triggerInputClear(one);
    })
  },
  // 隐藏 clearicon
  hideClearIcon(that) {
    $(that).parent('.action-clear').find('.oris-clear').removeClass('oris-clear-show').addClass('oris-clear-hide');
  },
  triggerInputClear(that) {
    var $clear = $(that).parent('.action-clear');
    if ($(that).val()) {
      $clear.find('.oris-clear').removeClass('oris-clear-hide').addClass('oris-clear-show');
    } else {
      // 解除绑定
      $clear.find('.oris-clear').removeClass('oris-clear-show').addClass('oris-clear-hide');
    }
  },
}
$(function () {
  orisComponents.initClearInputIcon();
  orisComponents.clearInputIcon();
});