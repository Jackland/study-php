$(window).load(function () {
  $(window).scroll(function () {
    //滚过高度
    let scrollTop = $(this).scrollTop();
    //页面高度
    let scrollHeight = $(document).height();
    //浏览器高度
    let windowHeight = $(this).height();
    //尾栏高度 +  一行商品
    let footerHeight = $("footer").height() + ($(".new-store-item").height()+300);
    //滚动条到底时
    if (scrollHeight - scrollTop - windowHeight < footerHeight) {
      let category_id = $('.triangle-black').attr('data');
      loadInfo('#data-load', category_id);
    }
  });
});
$(document).ready(function () {
  let category_id = $('.triangle-black').attr('data');
  loadSynData(1, category_id, '#data-load');
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
    loadSynData(page, category_id, loadHtml);
  }
}

function loadSynData(page, category_id, htmlcon) {
  $(htmlcon).attr('data-loadsuccess', false); //防止冒泡
  if (page === 1) {
    $(htmlcon).empty();
  }
  waitBlock.blockUI();
  let url = $('#url').val();
  $.ajax({
    url: url,
    data: {'category_id': category_id, 'page': page},
    type: 'GET',
    dataType: 'json',
    success: function (data) {
      if(data.code !=200){ //防错判断
        console.error(data.msg);
        waitBlock.unBlockUI();
        return
      }
      if(data.data.storeInfo.data == "" || data.data.storeInfo.data == null){ //异步查询的显示隐藏
        $('#async-box').css('display','none');
        //$('.remove-height').removeClass('min-height332');
        $('#no-records-box').css('display','block');
      }else{
        //固定最小高度
        $('#async-box').css('display','block');
        //$('.remove-height').addClass('min-height332');
        $('#no-records-box').css('display','none');
      }
      let htmls = '';
      htmls = data['data']['storeInfo']['data'];
      $(htmlcon).append(htmls);
      $(htmlcon).attr('data-end', data.data.storeInfo.is_end);   
      $(htmlcon).attr("page", ++page);
      $(htmlcon).attr('data-loadsuccess', true);
    },
    error: function (xhr, ajaxOptions, thrownError) {
      alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
    },
    complete: function (xhr, textStatus) {
      waitBlock.unBlockUI();
    }
  })
}

function download_product_file(product_id, customer_id, materialShow) {
  var $eleForm = $("<form method='post'></form>");
  $eleForm.attr("action", "index.php?route=product/product/download&product_id=" + product_id + "&customer_id=" + customer_id + "&materialShow=" + materialShow);
  $(document.body).append($eleForm);
  $eleForm.submit();
}