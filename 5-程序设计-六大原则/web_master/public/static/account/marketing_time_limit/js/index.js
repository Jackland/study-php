$(function () {
  btnClickClass.changeTabTypeFn();
})
var btnClickClass = {
  changeTabTypeFn: function () {
    $(".activity-navigation-ul").children(":first").addClass('navigation-active');
    $('.activity-navigation-ul li').click(function () {
      $(window).scrollTop(0); //滚动初始化
      $('.activity-navigation-ul li').each(function (i) {
        $(this).removeClass('navigation-active');
      })
      $(this).addClass('navigation-active');
      var tabType = $(this).attr('data-type');
      $('.include-box-show').each(function (i) {
        $(this).removeClass('navigation-block"');
        if (i + 1 == tabType) {
          $(this).removeClass('navigation-none');
          $(this).addClass('navigation-block')
        } else {
          $(this).removeClass('navigation-block');
          $(this).addClass('navigation-none');
        }
      })
    })
  }
}