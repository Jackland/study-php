// 用于分类，搜索，店铺页面通用js
$().ready(function() {
  batchDownload();
  batchWish();
  expandClickAround();
  slideInventory();
})

function batchDownload() {
  $('body').on('click', '#batch_download', function() {
    var state = $("#all_products_info").prop("checked");
    var choose_product = $("#choose_product").val();
    if (choose_product == '') {
      var tmplayer = layer.alert('Please select at least one product.', {
        title: 'Message',
        btn: 'OK',
        skin: 'yzc_layer' //样式类名
          ,
        closeBtn: 0
      }, function() {
        layer.close(tmplayer);
      });
    } else {
      var to_url = $('#batch_download').attr('mean');
      if (state == true) {
        to_url = to_url + '&type=1'
      } else {
        to_url = to_url + '&type=0&product_str=' + choose_product
      }
      var that = this
      var tmplayer = layer.confirm('Do you confirm to batch download the information of these products?', {
        title: 'Confirm',
        skin: 'yzc_layer',
        btn: ['Yes', 'No'] //按钮
      }, function() {
        // 埋点事件
        if ($(that).attr('data-mycnzz') && window.CNZZ) {
          var page = $(that).attr('data-mycnzz-page');
          var event = $(that).attr('data-mycnzz-event');
          // 判断是否全选
          event = $('#all_products_info').prop('checked') ? event + '_SelectAll' : event;
          window.CNZZ.triggerEvent(page, event);
        }
        layer.close(tmplayer);
        window.location.href = to_url;
      }, function() {
        layer.close(tmplayer);
      });
    }

  });
}

function batchWish() {
  $('body').on('click', '#batch_wish', function() {
    var state = $("#all_products_info").prop("checked");
    var choose_product = $("#choose_product").val();
    if (choose_product == '') {
      var tmplayer = layer.alert('Please select at least one product.', {
        title: 'Message',
        btn: 'OK',
        skin: 'yzc_layer' //样式类名
          ,
        closeBtn: 0
      }, function() {
        layer.close(tmplayer);
      });
    } else {
      var to_url = $('#batch_wish').attr('mean');
      if (state == true) {
        to_url = to_url + '&type=1'
      } else {
        to_url = to_url + '&type=0&product_str=' + choose_product
      }
      var that = this
      var tmplayer = layer.confirm('Do you confirm to add these products to Saved Items?', {
        title: 'Confirm',
        skin: 'yzc_layer',
        btn: ['Yes', 'No'] //按钮
      }, function() {
        // 埋点事件
        if ($(that).attr('data-mycnzz') && window.CNZZ) {
          var page = $(that).attr('data-mycnzz-page');
          var event = $(that).attr('data-mycnzz-event');
          // 判断是否全选
          event = $('#all_products_info').prop('checked') ? event + '_SelectAll' : event;
          window.CNZZ.triggerEvent(page, event);
        }
        layer.close(tmplayer);
        $.ajax({
          url: to_url,
          type: 'get',
          dataType: 'json',
          success: function(json) {
            if (json) {
              let tip = '';
              if (json['success_num']) {
                tip = json['success_num'] + ' products have been added to my saved items list successfully.'
              }
              if (json['fail_num']) {
                tip += json['fail_num'] + ' products have failed to be added to the list since the relationship with sellers has not been established.';
              }
              $.toast({
                heading: false,
                text: tip,
                position: 'top-center',
                showHideTransition: 'fade',
                icon: 'success',
                hideAfter: 3000,
                allowToastClose: true,
                loader: false,
                afterHidden: function () {
                  window.location.reload();
                }
              })
            }
          }
        });
      }, function() {
        layer.close(tmplayer);
      });
    }
  });
}

function checkSingleProduct(obj, length) {
  var id = $(obj).attr('id');
  var state = $(obj).prop("checked");
  var str = $('#choose_product').val();
  if (str) {
    list = str.split(',');
  } else {
    list = [];
  }
  if (state) {
    //加入
    list.push(id.split('_')[1]);
  } else {
    list.splice(jQuery.inArray(id.split('_')[1], list), 1)
  }
  if (list.length !== parseInt(length)) {
    $("#all_products_info").prop("checked", false);
    if (idExist('boxSelect')) {
      $("#boxSelect").prop("checked", false);
    }
  }
  idExist('boxSelectNum') && $('#boxSelectNum').text(list.length);
  idExist('selectNum') && $('#selectNum').text(list.length);
  $('#choose_product').val(list.join(','));


}

function idExist(id) {
  if ($("#" + id).length > 0) {
    return true;
  } else {
    return false;
  }
}

function checkChoose() {
  var state = $("#all_products_info").prop("checked");
  idExist('boxSelect') && $("#boxSelect").prop("checked", state);
  if (state == true) {
    $("#product_all_list input[type=checkbox]").prop("checked", true);
    // 所有的checkbox值
    var checkboxArr = $("#product_all_list input[type=checkbox]");
    // 获取所有条数
    var allproductsLen = $('#form_products_total').val() || 0;
    var idArr = [];
    $.each(checkboxArr, function(i, item) {
      idArr.push(item.id.split('_')[1])
    })
    if (idArr.length != 0) {
      $('#choose_product').val(idArr.join(','));
    }
    $('.batch-operations #selectNum').text(allproductsLen);
    idExist('boxSelectNum') && $('#boxSelectNum').text(allproductsLen);
  } else {
    $("#product_all_list input[type=checkbox]").prop("checked", false);
    $('#choose_product').val("");
    $('.batch-operations #selectNum').text("0")
    idExist('boxSelectNum') && $('#boxSelectNum').text("0");
  }
}

function slideInventory() {
  // 左侧选择条件Inventory Distribution
  $('body').on('click', '#slideInventory', function() {
    // 展开收起
    $(this).find('i.none').removeClass('none').siblings(':not(.giga.icon-V10-wenhaotishi)').addClass('none');
    $(this).siblings(':not(.giga.icon-V10-wenhaotishi)').toggle();
  })

  // 左侧选择条件Inventory Distribution 内部搜索，不区分大小写
  $('body').on('propertychange input', '#searchInventory', function() {
    var $inventExpand = $(this).parent().siblings('.action-expand');
    var $inventory = $inventExpand.find('.action-inventory');
    var inventCount = 0;  // 记录搜索的个数
    var inputVal = $(this).val().toUpperCase();
    $inventory.each(function(index,one){
      if ($(one).html().toUpperCase().indexOf(inputVal) > -1) {
        // 符合
        inventCount++;
        $(one).parent().show();
      } else {
        $(one).parent().hide();
      }
    })
    if (inventCount === 0) {
      // no results
      $inventExpand.find('.action-noinventory').show();
    } else {
      $inventExpand.find('.action-noinventory').hide();
    }
  })
}

// 扩大点击范围action-click
function expandClickAround() {
  $('body').on('click', 'span.action-click', function(){
    // 点击触发同级input的click事件
    $(this).siblings('input[type=checkbox]').trigger('click');
  }).on('mouseover mouseout', 'span.action-click',function(event){
     if(event.type == "mouseover"){
      //鼠标悬浮
      $(this).css({"cursor":"pointer"});
     }else if(event.type == "mouseout"){
      //鼠标离开
      $(this).css({"cursor":"unset"});
     }
  });
}
