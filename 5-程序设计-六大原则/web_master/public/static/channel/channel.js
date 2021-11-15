var navHeightTop;
var current_Category_id;
$(window).load(function () {
  var data_id=$('.nav-content-ul').attr('data');
  if(data_id !=0 && data_id !=null && data_id !=''){
    var isCeiling = sessionStorage.getItem('isCeiling') || 'false';
    if('scrollRestoration' in history && isCeiling =='false'){
      history.scrollRestoration = 'manual';
      //$(window).scrollTop($('.heightCoop')[0].clientHeight+60);
      $(window).scrollTop(($(".nav-bar").offset().top)-250);
    }else{
      history.scrollRestoration = 'auto';
    }
  }else{
    history.scrollRestoration = 'auto';
  }
  $(window).scroll(function () {
    
    if ($(this).scrollTop() >= $(".nav-bar").offset().top) {
      //吸顶
      $('.nav-bar-content').addClass('ceiling-nav-bar');
      $('.search-content').css('margin-top', '95px');
      if($('.load').length>=1){
        $('.load').parent().css('position','static');
        $('.load').css('position','fixed');
        $('.load').css('margin','auto');
        $('.load').css('left','50%');
        $('.load').css('right','50%');
        $('.load').css('top','8%');
      }
      sessionStorage.setItem('isCeiling','true'); //存吸顶状态
    } else {
      $('.nav-bar-content').removeClass('ceiling-nav-bar');
      // $('.search-content').css('margin-top', '10px');
      $('.search-content').css('margin-top', '0px'); //UI考虑到间距问题
      if($('.load').length>=1){
        $('.load').parent().css('position','relative');
        $('.load').css('position','absolute');
        $('.load').css('margin','auto');
        $('.load').css('left','50%');
        $('.load').css('right','50%');
        $('.load').css('top','0');
      }
      sessionStorage.setItem('isCeiling','false');
    }
    //滚过高度
    let scrollTop = $(this).scrollTop();
    //页面高度
    let scrollHeight = $(document).height();
    //浏览器高度
    let windowHeight = $(this).height();
    //尾栏高度 +  一行商品
    let footerHeight = $("footer").height() + ($(".search-content .item").height()+300);
    //滚动条到底时
    if (scrollHeight - scrollTop - windowHeight < footerHeight) {
      let category_id = $('.triangle-black').attr('data');
      loadInfo('#data-load', category_id);
    }
  });
});
$(document).ready(function () {
  let category_id = $('.triangle-black').attr('data');
 // loadSynData(1, category_id, '#data-load');
   $(".item-nav").each(function () {
      if(category_id == $(this).attr('data')){
        $('.nav-content-ul').attr('is-product-activity','no');
        $('.nav-content-ul').attr('is-first-load','yes');
        $(this).click();
        return false;
      }
      //console.log($(this).find('.triangle-black'));
   })   
})

/*加载分页数据*/
function loadInfo(loadHtml, categoryId) {
  if ($(loadHtml).attr("data-loadsuccess") == 'true') {
    var t = $(loadHtml);
    /*当前页码*/
    var page = t.attr("page");
    /**最后一页不再添加数据*/
    if ($(loadHtml).attr('data-end') == '1') {
      return;
    }
    //当前选中分类
    let category_id = $('.triangle-black').attr('data');
    //触发ajax事件，加载dom
    loadSynData(page, category_id, loadHtml,false);
  }
}

function loadSynData(page, category_id, htmlcon,isTrigger) {
  var searchHeight = $('.search-content ').height();
  if(current_Category_id == category_id && isTrigger){ //与当前选中分类比较，如选中当前则回到分类导航顶部
    if($(window).scrollTop() >= $(".nav-bar").offset().top){ //吸顶之后  回到吸顶位置
      $(window).scrollTop(Math.ceil($(".nav-bar").offset().top));
    }else{ //不吸顶的情况 
      if($(".nav-bar").offset().top-$(this).scrollTop()<150){
        $(window).scrollTop($(window).scrollTop()-150);
      }
    }
    return
  }
  current_Category_id=category_id; //判断是否重复点击当前nav
  $(htmlcon).attr('data-loadsuccess', false);
  if (page === 1) {
    //$(htmlcon).empty();
    if($(window).scrollTop() >= $(".nav-bar").offset().top){ //吸顶之后的位置
      $(htmlcon).html("<div style='width:100%;height:"+searchHeight+"px'> <div class='load' style ='position: fixed;margin:auto;left: 50%;right: 50%;top:8%'></div></div>");
    }else{ 
      //$(htmlcon).html("<div style='width:100%;transform: scale(1, 1);height:"+searchHeight+"px'> <div class='load' style ='position: fixed;margin:auto;left: 50%;right: 50%;'></div></div>");
      $(htmlcon).html("<div style='width:100%;position:relative;height:"+searchHeight+"px'> <div class='load' style ='position: absolute;margin:auto;left: 50%;right: 50%;top:0'></div></div>");
    }
    waitBlock.blockUI();
  }
  let url = $('#url').val();
  $.ajax({
    url: url,
    data: {'category_id': category_id, 'page': page},
    type: 'GET',
    dataType: 'json',
    success: function (data) {
      if(data.code != 200){ //防错信息
        console.error(data.msg);
        waitBlock.unBlockUI();
        return;
      }
      let htmls = '';
      htmls = data['data']['searchInfo']['data'];
      waitBlock.unBlockUI();
      if(current_Category_id == data.data.category_id){
        if (page === 1) {
          $(htmlcon).html(htmls);
        }else{
          $(htmlcon).append(htmls);
        } 
      }else{
        return;
      }  
      $(htmlcon).attr('data-end', data.data.searchInfo.is_end);
      if($(window).scrollTop() >= $(".nav-bar").offset().top && page  ===1){ //判断是否吸顶(只有切换tab 的时候回到最初吸顶位置，其他保留历史查询位置)
        $(window).scrollTop(Math.ceil($(".nav-bar").offset().top));
      }
      $(htmlcon).attr("page", ++page);
      $(htmlcon).attr('data-loadsuccess', true);
      $('.nav-content-ul').attr('is-first-load','no');
    },
    error: function (xhr, ajaxOptions, thrownError) {
      alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
    },
    complete: function (xhr, textStatus) {
      //waitBlock.unBlockUI();
    }
  })
}

function download_product_file(product_id, customer_id, materialShow) {
  var $eleForm = $("<form method='post'></form>");
  $eleForm.attr("action", "index.php?route=product/product/download&product_id=" + product_id + "&customer_id=" + customer_id + "&materialShow=" + materialShow);
  $(document.body).append($eleForm);
  $eleForm.submit();
}