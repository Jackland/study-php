var getUlList = document.getElementsByClassName('nav-content-ul')[0].children;
var navSize = 6; //每页放多少个
//默认第一个选中
// $(".nav-content-ul").children(":first").addClass('triangle-black');
// $(".nav-content-ul").children(":first").find('.triangle').addClass('triangle-block');
$('.left-arrow,.right-arrow').addClass('none')
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
  $('.left-arrow,.right-arrow').addClass('none')
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
  var isProductsActivity=$('.nav-content-ul').attr('is-product-activity');
  var isFirstLoad=$('.nav-content-ul').attr('is-first-load');
  if(isProductsActivity == 'no'){
    if(isFirstLoad == 'yes'){
      loadSynData(1, category_id, '#data-load',true); //首次加载
    }else{
      debounce(loadSynData,300).bind(this,1, category_id, '#data-load',true)();
    }   
  }else{ //活动页的默认查询接口
    if(isFirstLoad == 'yes'){
      getrows(1,category_id, '#data-load',true);
    }else{   
      debounce(getrows,300).bind(this,1,category_id, '#data-load',true)();
    }
  }
})
// var heightCoop = $('.heightCoop')[0].clientHeight;
// $(window).scroll(function (e) {
//   if ($(this).scrollTop() > heightCoop) {
//     //吸顶
//     $('.nav-bar-content').addClass('ceiling-nav-bar');
//   } else {
//     $('.nav-bar-content').removeClass('ceiling-nav-bar');
//   }
// })
//左箭头
$('.left-arrow').click(function () {//点击左箭头左平移navSize -1 个
  var marginLeft = $('.nav-content-ul')[0].style.marginLeft || 0;
  var index = (-parseFloat(marginLeft)) / parseFloat($('.item-nav')[0].style.width) // 当前显示的第一个的下标
  if (index > 0) {
    var pIndex = Number(index) - (navSize-1);
    if (pIndex <= 0) {
      $(this).addClass("none")
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
      pIndex = getUlList.length - navSize
      $(this).addClass("none")
    }
    $('.nav-content-ul').css('margin-left', -(parseFloat($('.item-nav')[0].style.width) * pIndex) + '%');
    $('.left-arrow').removeClass("none")
  }
})
//防抖节流
let timer = null
function debounce(fn,delay){
   return function(){
    if(timer)clearTimeout(timer)
    timer = setTimeout(()=>{
     fn.call(this,...arguments)
    },delay)
   }
  }
  window.onresize = function(){
    $(window).scrollTop($(window).scrollTop()-1)
  }