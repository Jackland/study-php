var getUlList = document.getElementsByClassName('nav-content-ul')[0].children;
var navSize = 6; //每页放多少个
$('.left-arrow,.right-arrow').addClass('none');
if (getUlList.length <= navSize) {
  $(".item-nav").each(function (i) {
    $(this).attr('id', 'nav_' + (i + 1));
    $(this)[0].style.width = 100 / getUlList.length + '%';
  });
} else {
  //设置每一个宽度
  $(".item-nav").each(function (i) {
    $(this).attr('id', 'nav_' + (i + 1));
    $(this)[0].style.width = 100 / navSize + '%';
  });
  totalWidth = (100 / navSize) * getUlList.length + '%';
  $('.nav-content-ul')[0].style.width = totalWidth;
  $('.right-arrow').removeClass('none')
}
$('.item-nav').click(function () {
  var index, category_id;
  $('.left-arrow,.right-arrow').addClass('none');
  index = ($(this).attr("id")).split('_')[1];
  if (Number(index) > 1 && getUlList.length>navSize ) {
    if(Number(index)>getUlList.length - (navSize-2)){
      pindex = getUlList.length - (navSize-2) - 2
    }else{
      pindex = index - 2
    }
    $('.nav-content-ul').css('margin-left', (-parseFloat($('.item-nav')[0].style.width)) * pindex + '%')
  }
  if (Number(index) > 2 && getUlList.length>navSize) {
    $('.left-arrow').removeClass('none');
  }
  if (Number(index) < getUlList.length - (navSize-2) && getUlList.length>navSize) {
    $('.right-arrow').removeClass('none');
  }
  $('.item-nav').removeClass('triangle-black');
  $('.triangle').removeClass('triangle-block');
  $(this).addClass('triangle-black');
  $(this).find('.triangle').addClass('triangle-block');
  category_id = $(this).attr('data');
  var isFirstLoad=$('.nav-content-ul').attr('is-first-load');
  //tab 切换查询参数
  var postMsg={
    page:1,
    category_id:category_id,
    limit:20
  };
  $('#min_price').val('');
  $('#max_price').val('');
  $('#min_quantity').val('');
  $('#max_quantity').val();
  flagBtn=true; //btn默认状态
  $('.sort-item.active').removeClass('active');
  $('.sort-item').find('.active').removeClass('active');
  $('.search-condition-right').find(".sort-item").eq(0).addClass('active');
  $('.search-condition-right').find(".sort-item").eq(0).find('.sort-down').addClass('active');
  if(isFirstLoad == 'yes'){
    initClassFn.loadSynData(1, category_id, '#search-content-up-down-thumbnails',true,postMsg); //首次加载
   }else{
      debounce(initClassFn.loadSynData,300).bind(this,1, category_id, '#search-content-up-down-thumbnails',true,postMsg)();
    }   
})
//左箭头
$('.left-arrow').click(function () {//点击左箭头左平移navSize -1 个
  var marginLeft = $('.nav-content-ul')[0].style.marginLeft || 0;
  var index = (-parseFloat(marginLeft)) / parseFloat($('.item-nav')[0].style.width) // 当前显示的第一个的下标
  if (index > 0) {
    var pIndex = Number(index) - (navSize-1);
    if (pIndex <= 0) {
      $(this).addClass("none");
      pIndex = 0
    }
    $('.nav-content-ul').css('margin-left', -(parseFloat($('.item-nav')[0].style.width) * pIndex) + '%');
    $('.right-arrow').removeClass("none")
  }
})
//右箭头
$('.right-arrow').click(function () { //点击右箭头右平移navSize -1个
  var marginLeft = $('.nav-content-ul')[0].style.marginLeft || 0;
  var index = (-parseFloat(marginLeft)) / parseFloat($('.item-nav')[0].style.width)//当前显示的第一个的下标
  if (index < getUlList.length - (navSize-1)) {
    var pIndex = (navSize-1) + Number(index);
    if (pIndex > getUlList.length - navSize) {
      pIndex = getUlList.length - navSize;
      $(this).addClass("none")
    }
    $('.nav-content-ul').css('margin-left', -(parseFloat($('.item-nav')[0].style.width) * pIndex) + '%');
    $('.left-arrow').removeClass("none")
  }
})
//防抖节流
let timer = null
function debounce(fn, delay) {
  return function () {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
      fn.call(this, ...arguments)
    }, delay)
  }
}

window.onresize = function () {
  $(window).scrollTop($(window).scrollTop() - 1)
};


