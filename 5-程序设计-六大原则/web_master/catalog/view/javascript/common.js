/**
 * 去尾法
 * @param num 小数位长度
 * @returns {string|Number}
 */
Number.prototype.toFloor = function (num) {
  //return Math.floor(this * Math.pow(10, num)) / Math.pow(10, num);//39.80->39.79

  if (num < 0) {
    return this;
  }
  let nnum = Number.parseFloat(this);
  let str = nnum.toString();
  let arr = str.split(".");
  let strZ = arr[0];//arr[0] 整数部分，arr[1] 小数部分
  let strX = "";  //小数点与小数部分
  if (arr.length > 1) {//有小数
    if (num > 0) {
      if (arr[1].length >= num) {//小数长，保留位短
        strX = arr[1].substr(0, num);
      } else {//小数短，保留位长
        let zeroArr = [];
        for (let i = 0; i < num - arr[1].length; i++) {
          zeroArr.push(0);
        }
        strX = arr[1].toString() + zeroArr.join("");
      }
      strX = "." + strX;
    }
  } else {//无小数
    if (num > 0) {
      let zeroArr = [];
      for (let i = 0; i < num; i++) {
        zeroArr.push(0);
      }
      strX = "." + zeroArr.join("");
    }
  }
  let result = strZ + "" + strX;
  return result;
};
/**
 * 添加千分位
 * @param Number|String num 待转换的数字
 * @returns {string}
 */
function toThousands(num){
  let re=/\d{1,3}(?=(\d{3})+$)/g;
  let n1 = num.toString().replace(/^(\d+)((\.\d+)?)$/,function(s,s1,s2){return s1.replace(re,"$&,")+s2;});
  return n1;
}
String.prototype.isInteger = function (maxlen) {
  let range = '*';
  if (maxlen && (parseInt(maxlen) >= 1)) range = '{0,' + (maxlen - 1) + '}';
  return (new RegExp('((^[1-9][0-9]' + range + ')|^0)$')).test(String(this));
};
String.prototype.isFloat = function (dec) {
  dec = dec || 2;
  return (new RegExp('((^[1-9][0-9]*)|^0)(\.[0-9]{1,' + dec + '})$')).test(String(this));
};

var flagDownloadFile = true;
function download_file(that,product_id, customer_id) {
  $(that).attr('disabled','disabled');
  $.ajax({
    url: "index.php?route=product/product/download",
    method: "post",
    data: {
      product_id: product_id,
      customer_id: customer_id,
    },
    success: function (res) {
      if (res.code == 300) {
        if (flagDownloadFile) {
          $.toast({
            heading: false,
            text: "Download in process, please wait a moment",
            position: 'top-center',
            showHideTransition: 'fade',
            icon: 'warning',
            hideAfter: 3500,
            allowToastClose: true,
            loader: false,
            afterHidden: function () {
              flagDownloadFile = false
            }
          })
        }
        $("#downloadFile").tooltip('destroy');
        var download = window.setTimeout(function () {
          download_file(that,product_id, customer_id)
        }, 3000);
      }
      if (res.code != 300) {
        window.clearTimeout(download);
        $(that).removeAttr('disabled');
        if (res.code == 0) {
          layer.msg('Failed to download the resource package. Please try again.')
        }
        if (res.code == 200) {
          var downloadHref = "index.php?route=product/product/downloadZip&product_id=" + product_id;
          window.location.href = downloadHref;
        }
      }
    }
  })
}

function getURLVar(key) {
  var value = [];

  var query = String(document.location).split('?');

  if (query[1]) {
    var part = query[1].split('&');

    for (i = 0; i < part.length; i++) {
      var data = part[i].split('=');

      if (data[0] && data[1]) {
        value[data[0]] = data[1];
      }
    }

    if (value[key]) {
      return value[key];
    } else {
      return '';
    }
  }
}

$(document).ready(function () {
  // Highlight any found errors
  $('.text-danger').each(function () {
    var element = $(this).parent().parent();

    if (element.hasClass('form-group')) {
      element.addClass('has-error');
    }
  });

  // Currency
  $('#form-currency .currency-select').on('click', function (e) {
    e.preventDefault();

    $('#form-currency input[name=\'code\']').val($(this).attr('name'));

    $('#form-currency').submit();
  });

  // Country
  $('#form-country .country-select').on('click', function (e) {
    e.preventDefault();
    $('#form-country input[name=\'code\']').val($(this).find('span').attr('title'));

    $('#form-country').submit();
  });

  // Language
  $('#form-language .language-select').on('click', function (e) {
    e.preventDefault();

    $('#form-language input[name=\'code\']').val($(this).attr('name'));

    $('#form-language').submit();
  });

  /* Search */
  $('#search').find('button').on('click', function () {
    // 获取是否是seller店铺
    if($('#searchRange').length > 0  && $('#searchRange').find('text').html() == 'In This Store'){
      var url = $('base').attr('href') + 'index.php?route=seller_store/products&id=' + $('#headerSearchSellerId').val();
    }else{
      var url = $('base').attr('href') + 'index.php?route=product/search';
    }
    var value = $('header #search input[name=\'search\']').val().trim();

    if (value.trim()=='') {
      alert('Please input what you want to search for.');
    } else {
      let limit = 100;
      if (value.length > limit) {
        value = $.trim(value.substr(0, limit));
      }
      url += '&search=' + encodeURIComponent(value);
      location = url;
    }
  });

  $('#search input[name=\'search\']').on('keydown', function (e) {
    if (e.keyCode == 13) {
      $('header #search').find('button').trigger('click');
    }
  });

  // Menu
  $('#menu .dropdown-menu').each(function () {
    var menu = $('#menu').offset();
    var dropdown = $(this).parent().offset();

    var i = (dropdown.left + $(this).outerWidth()) - (menu.left + $('#menu').outerWidth());

    if (i > 0) {
      $(this).css('margin-left', '-' + (i + 10) + 'px');
    }
  });

  // Product List
  $(document).on("click", '#list-view', function () {
    var $productList = $('#product_all_list');
    $productList.addClass('product-line-show').removeClass('product-grid-show');
    $('#content_product #product_all_list .product-show').attr('class', 'col-xs-12 p0 line-layout product-show shadow box-bg');
    // 换图片
    $('#list-view img')[0].src = 'image/icons/list-active.png';
    $('#grid-view img')[0].src = 'image/icons/grid.png';
    $productList.find('.col-sub').hide();
    $productList.find('.col-sub').parent().attr('class', 'col-sm-12 no-padding');
    localStorage.setItem('display', 'list');
    $productList.find('.product-line-show div.col-sm-12:last-child').css('display','inherit');
    $productList.find('.product-line-show .col-sub').remove();
    $productList.find('div.col-sm-12.no-padding').removeClass('just-flex');
    // 控制缩略图grid和line展示不同UI
    $productList.find('.action-grid').hide();
    $productList.find('.action-line').show();
  });

  // Product Grid
  $(document).on('click', '#grid-view', function () {
    var $productList = $('#product_all_list');
    $productList.addClass('product-grid-show').removeClass('product-line-show');
    $('#content_product .line-layout').attr('class', 'p0 col-sm-3 grid-layout product-show shadow box-bg');
    // 换图片
    $('#list-view img')[0].src = 'image/icons/list.png';
    $('#grid-view img')[0].src = 'image/icons/grid-active.png';
    // 处理最后一行border-left问题
    $productList.find('.col-sub').show();
    $productList.find('.col-sub').parent().attr('class', 'col-sm-12 no-padding just-flex');
    localStorage.setItem('display', 'grid');
    if ( $productList.find('.product-line-show div.col-sm-12:last-child').children().length < 4){
      $productList.find('.product-line-show.product-grid-show div.col-sm-12:last-child').css('display','flex');
      $productList.find('.product-line-show.product-grid-show div.col-sm-12:last-child .grid-layout:last-child').after(' <div class="col-sub"></div>')
    }
    $productList.find('div.col-sm-12.no-padding').addClass('just-flex');
     // 控制缩略图grid和line展示不同UI
    $productList.find('.action-grid').show();
    $productList.find('.action-line').hide();
  });

  if (localStorage.getItem('display') == 'list') {
    $('#list-view').trigger('click');
    $('#list-view').addClass('active');
    $('#product_all_list').find('div.col-sm-12.no-padding').removeClass('just-flex');
  } else {
    $('#grid-view').trigger('click');
    $('#grid-view').addClass('active');
    $('#product_all_list').find('div.col-sm-12.no-padding').addClass('just-flex');
  }

  // Checkout
  $(document).on('keydown', '#collapse-checkout-option input[name=\'email\'], #collapse-checkout-option input[name=\'password\']', function (e) {
    if (e.keyCode == 13) {
      $('#collapse-checkout-option #button-login').trigger('click');
    }
  });

  // tooltips on hover
  // 说明：不是所有的tooltip需求都需要绑定在body下，所以用类oris-tooltip区分是否绑定在body下
  // 如果是类oris-tooltip，需求自行初始化
  $('[data-toggle=\'tooltip\']:not(.oris-tooltip)').tooltip({container: 'body', trigger: 'hover'});

  // Makes tooltips work on ajax generated content
  $(document).ajaxStop(function () {
    $('[data-toggle=\'tooltip\']:not(.oris-tooltip)').tooltip({container: 'body', trigger: 'hover'});
  });

  // Image Manager
  $(document).on('click', 'a[data-toggle=\'imageForAddAndRemove\']', function (e) {
    var $element = $(this);
    var $popover = $element.data('bs.popover'); // element has bs popover?

    e.preventDefault();

    // destroy all image popovers
    $('a[data-toggle="imageForAddAndRemove"]').popover('destroy');
    var id = this.id;
    var pattern = new RegExp("[0-9]+");
    var imgRow = id.match(pattern);
    // remove flickering (do not re-add popover when clicking for removal)
    if ($popover) {
      return;
    }

    $element.popover({
      html: true,
      placement: 'right',
      trigger: 'manual',
      content: function () {
        return '<button type="button" id="button-image' + imgRow + '" class="btn btn-primary"><i class="fa fa-pencil"></i></button><button type="button" id="button-clear" class="btn btn-danger"><i class="fa fa-trash-o"></i></button>';
      }
    });

    $element.popover('show');
    var addBtn = document.querySelector('#button-image' + imgRow);
    addBtn.addEventListener('click', function () {
      document.querySelector('#imageAdd' + imgRow).value = null;
      document.querySelector('#imageAdd' + imgRow).click();
      $element.popover('destroy');
      return false;
    }, false);


    $('#button-clear').on('click', function () {
      var src = $element.find('img')[0].src;
      $element.find('img').attr('src', $element.find('img').attr('data-placeholder'));
      if ($element.parent().find('input')[0].name == 'brandImage') {
        //品牌
      } else {
        //review 附件
        var filePath = src.split('reviewFiles/')[1];
        $.ajax({
          url: 'index.php?route=account/order/deleteFiles',
          type: 'post',
          data: {'path': filePath},
          error: function (xhr, ajaxOptions, thrownError) {
            alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
          }
        })
      }
      $element.parent().find('input').val('');

      $element.popover('destroy');
    });
  });

  $(document).on("mouseover", ".label-more-on-the-way", function () {
    $(this).next().show();
  });
  $(document).on("mouseleave", ".label-more-on-the-way", function () {
    $(this).next().hide();
  });
});

var block = {
  'blockUI': function () {
    var ele = $("#Modal-Mask-layer");
    if (!ele.length) {
      var html = "";
      html += '<div class="modal fade" id="Modal-Mask-layer" tabindex="-1" data-backdrop="static" role="dialog" aria-hidden="true" style="display: none;">';
      html += '  <div class="loader1" style="margin-top: 20%;margin-left: 50%">';
      html += '  <span></span>';
      html += '  <span></span>';
      html += '  <span></span>';
      html += '  <span></span>';
      html += '  <span></span>';
      html += '  </div>';
      html += '</div>';
      $('body').append(html);
    }
    $("#Modal-Mask-layer").modal('show');
  },
  'unBlockUI': function () {
    $('#Modal-Mask-layer').modal('hide');
  }
};


var waitBlock = {
  'blockUI': function () {
    var ele = $(".loadingcolumn");
    if (!ele.length) {
      var html = "";
      html += '<div  class="loadingcolumn"   style="display: none;width: 100%;>';
      html += '  <div class="" style="width: 100%;text-align: center">';
      html += '  <p style="width: 100%;text-align: center"><i class="layui-icon layui-icon-loading layui-icon layui-anim layui-anim-rotate layui-anim-loop" style="font-size: 40px"></i> <span class="description">Loading...</span></p>';
      html += '  </div>';
      html += '</div>';
      $('.load').append(html);
    }
    $(".loadingcolumn").show()
  },
  'unBlockUI': function () {
    $('.loadingcolumn').hide();
  }
};

// Cart add remove functions
var cart = {
  'add': function (product_id, quantity, deliveryType) {
    $.ajax({
      url: 'index.php?route=checkout/cart/add',
      type: 'post',
      data: 'product_id=' + product_id + '&quantity=' + (typeof (quantity) != 'undefined' ? quantity : 1) + '&freight_radio=' + (typeof (deliveryType) != 'undefined' ? deliveryType : 'no_cwf'),
      dataType: 'json',
      beforeSend: function () {
        $('#cart > button').button('loading');
      },
      complete: function () {
        $('#cart > button').button('reset');
      },
      success: function (json) {
        $('.alert-dismissible, .text-danger').remove();

        if (json['success']) {
          if (json['redirect']) {
            location = json['redirect'];
          }
          $('body').append('<div class="alert alert-success alert-dismissible container  new-alert-title"><i class="fa fa-check-circle"></i> ' + json['success'] + ' <button type="button" class="close" data-dismiss="alert">&times;</button></div>');
          if (json.hasOwnProperty("totalNum")) {
            $("#header-totalNum").html(json["totalNum"]);
          }
          if (json.hasOwnProperty("totalMoney")) {
            $("#header-totalMoney").html(json["totalMoney"]);
          }
          // Need to set timeout otherwise it wont update the total
          setTimeout(function () {
            $('#cart > button').html('<span id="cart-total"><i class="fa fa-shopping-cart"></i> ' + json['total'] + '</span>');
          }, 100);

          // $('html, body').animate({scrollTop: 0}, 'slow');
          window.setTimeout(function () {
            $('[data-dismiss="alert"]').alert('close');
          }, 5000);
          $('#cart > ul').load('index.php?route=common/cart/info ul li');
        } else if (json['error']['transaction_type']) {
          $('body').append('<div class="alert alert-danger alert-dismissible new-alert-title"><i class="fa fa-exclamation-circle"></i> ' + json['error']['transaction_type'] + ' <button type="button" class="close" data-dismiss="alert">&times;</button></div>');
          // $('html, body').animate({scrollTop: 0}, 'slow');
          window.setTimeout(function () {
            $('[data-dismiss="alert"]').alert('close');
          }, 5000);
          $('#cart > ul').load('index.php?route=common/cart/info ul li');
        } else {
          if (json['redirect']) {
            location = json['redirect'];
          }
        }


      },
      error: function (xhr, ajaxOptions, thrownError) {
        if (xhr.status == 302) {
          window.location.reload();
          return;
        }
        alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
      }
    });
  },
  'update': function (key, quantity) {
    $.ajax({
      url: 'index.php?route=checkout/cart/edit',
      type: 'post',
      data: 'key=' + key + '&quantity=' + (typeof (quantity) != 'undefined' ? quantity : 1),
      dataType: 'json',
      beforeSend: function () {
        $('#cart > button').button('loading');
      },
      complete: function () {
        $('#cart > button').button('reset');
      },
      success: function (json) {
        // Need to set timeout otherwise it wont update the total
        if (json.hasOwnProperty("totalNum")) {
          $("#header-totalNum").html(json["totalNum"]);
        }
        if (json.hasOwnProperty("totalMoney")) {
          $("#header-totalMoney").html(json["totalMoney"]);
        }

        setTimeout(function () {
          $('#cart > button').html('<span id="cart-total"><i class="fa fa-shopping-cart"></i> ' + json['total'] + '</span>');
        }, 100);

        if (getURLVar('route') == 'checkout/cart' || getURLVar('route') == 'checkout/checkout') {
          location = 'index.php?route=checkout/cart';
        } else {
          $('#cart > ul').load('index.php?route=common/cart/info ul li');
        }
      },
      error: function (xhr, ajaxOptions, thrownError) {
        alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
      }
    });
  },
  'remove': function (key, delivery_type) {
    $.ajax({
      url: 'index.php?route=checkout/cart/remove',
      type: 'post',
      data: 'key=' + key + '&delivery_type=' + delivery_type,
      dataType: 'json',
      beforeSend: function () {
        $('#cart > button').button('loading');
      },
      complete: function () {
        $('#cart > button').button('reset');
      },
      success: function (json) {
        // Need to set timeout otherwise it wont update the total
        if (json.hasOwnProperty("totalNum")) {
          $("#header-totalNum").html(json["totalNum"]);
        }
        if (json.hasOwnProperty("totalMoney")) {
          $("#header-totalMoney").html(json["totalMoney"]);
        }
        if (json.hasOwnProperty("drop_ship_qty")) {
          $("#drop_ship_qty").html(json["drop_ship_qty"]);
        }
        if (json.hasOwnProperty("home_pick_qty")) {
          $("#home_pick_qty").html(json["home_pick_qty"]);
        }
        if (json.hasOwnProperty("cloud_logistics_qty")) {
          $("#cloud_logistics_qty").html(json["cloud_logistics_qty"]);
        }

        setTimeout(function () {
          $('#cart > button').html('<span id="cart-total"><i class="fa fa-shopping-cart"></i> ' + json['total'] + '</span>');
        }, 100);

        if (getURLVar('route') == 'checkout/cart' || getURLVar('route') == 'checkout/checkout') {
          if (delivery_type == 2) {
            $('#cloud-logistics-list').load('index.php?route=checkout/cart/cart_cloud_logistics');
          } else if (delivery_type == 1) {
            $('#home-pick-list').load('index.php?route=checkout/cart/cart_home_pick');
          } else {
            $('#drop-ship-list').load('index.php?route=checkout/cart/cart_drop_ship');
          }
        } else {
          $('#cart > ul').load('index.php?route=common/cart/info ul li');
        }
      },
      error: function (xhr, ajaxOptions, thrownError) {
        alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
      }
    });
  },
  'change': function (key, from_delivery_type, to_delivery_type) {
    $.ajax({
      url: 'index.php?route=checkout/cart/change',
      type: 'post',
      data: 'key=' + key + '&from_delivery_type=' + from_delivery_type + "&to_delivery_type=" + to_delivery_type,
      dataType: 'json',
      beforeSend: function () {
        $('#cart > button').button('loading');
      },
      complete: function () {
        $('#cart > button').button('reset');
      },
      success: function (json) {
        // Need to set timeout otherwise it wont update the total
        if (json.hasOwnProperty("totalNum")) {
          $("#header-totalNum").html(json["totalNum"]);
        }
        if (json.hasOwnProperty("totalMoney")) {
          $("#header-totalMoney").html(json["totalMoney"]);
        }
        if (json.hasOwnProperty("drop_ship_qty")) {
          $("#drop_ship_qty").html(json["drop_ship_qty"]);
        }
        if (json.hasOwnProperty("home_pick_qty")) {
          $("#home_pick_qty").html(json["home_pick_qty"]);
        }
        if (json.hasOwnProperty("cloud_logistics_qty")) {
          $("#cloud_logistics_qty").html(json["cloud_logistics_qty"]);
        }
        $('#cloud-logistics-list').load('index.php?route=checkout/cart/cart_cloud_logistics');
        $('#drop-ship-list').load('index.php?route=checkout/cart/cart_drop_ship');
        $('#removeMsg').remove();
        if (json.hasOwnProperty("success")) {
          $('#checkout-cart').prepend('<div id="removeMsg" class="alert alert-success alert-dismissible"><i class="fa fa-check-circle"></i> ' + json['success'] +
            '<button type="button" class="close" data-dismiss="alert">&times;</button> </div>');
        }
        if (json.hasOwnProperty("total")) {
          setTimeout(function () {
            $('#cart > button').html('<span id="cart-total"><i class="fa fa-shopping-cart"></i> ' + json['total'] + '</span>');
          }, 100);
        }
        if (json.hasOwnProperty("error")) {
          $('#checkout-cart').prepend('<div id="removeMsg" class="alert alert-danger alert-dismissible"><i class="fa fa-check-circle"></i> ' + json['error'] +
            '<button type="button" class="close" data-dismiss="alert">&times;</button> </div>');
        }
        setTimeout(function () {
          $('[data-dismiss="alert"]').alert('close');
        }, 5000);
      },
      error: function (xhr, ajaxOptions, thrownError) {
        alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
      }
    });
  }
};

var voucher = {
  'add': function () {

  },
  'remove': function (key) {
    $.ajax({
      url: 'index.php?route=checkout/cart/remove',
      type: 'post',
      data: 'key=' + key,
      dataType: 'json',
      beforeSend: function () {
        $('#cart > button').button('loading');
      },
      complete: function () {
        $('#cart > button').button('reset');
      },
      success: function (json) {
        // Need to set timeout otherwise it wont update the total
        setTimeout(function () {
          $('#cart > button').html('<span id="cart-total"><i class="fa fa-shopping-cart"></i> ' + json['total'] + '</span>');
        }, 100);

        if (getURLVar('route') == 'checkout/cart' || getURLVar('route') == 'checkout/checkout') {
          location = 'index.php?route=checkout/cart';
        } else {
          $('#cart > ul').load('index.php?route=common/cart/info ul li');
        }
      },
      error: function (xhr, ajaxOptions, thrownError) {
        alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
      }
    });
  }
};

var wishlist = {
  'add': function (product_id, btn, from_page) {
    $.ajax({
      url: 'index.php?route=account/wishlist/add',
      type: 'post',
      data: 'product_id=' + product_id,
      dataType: 'json',
      success: function (json) {
        if (from_page == 'home') {

          if (json['redirect']) {
            location = json['redirect'];
          }

          if (json['success']) {
            // $('#content').parent().before('<div class="alert alert-success alert-dismissible container"><i class="fa fa-check-circle"></i> ' + json['success'] + ' <button type="button" class="close" data-dismiss="alert">&times;</button></div>');
            if (json['success'].indexOf('Success') != -1) {
              $(btn).removeAttr('onclick');
              $(btn).attr('onclick', 'wishlist.remove(' + product_id + ',this, "' + from_page + '")');
              $(btn).attr('data-original-title', "Remove from Saved Items");
              $(btn).find('i').removeClass('fa-heart-o');
              $(btn).find('i').addClass('fa-heart');
            }
          }

          // $('#wishlist-total span').html(json['total']);
          $('#wishlist-total').attr('title', json['total']);
          if (json.hasOwnProperty("totalNum")) {
            $('#wishlist-total-num').html(json['totalNum']);
          }
          layer.config({
            skin: 'wishlist-class'
          });
          layer.msg('<i class="fa fa-check-circle"></i>' + '&nbsp' + json['success'] + '<button type="button" class="close" data-dismiss="alert">×</button>', {
            // icon: 1,
            offset: '50px',
            area: '600px',
            closeBtn: 2,
            skin: 'wishlist-class',
            time: 3000 //2秒关闭（如果不配置，默认是3秒）
          }, function () {
            //do something
          });
        } else if (from_page == 'product') {
          if (json['redirect']) {
            location = json['redirect'];
          }

          if (json['success']) {
            $('#content').parent().before('<div class="alert alert-success alert-dismissible container"><i class="fa fa-check-circle"></i> ' + json['success'] + ' <button type="button" class="close" data-dismiss="alert">&times;</button></div>');
            if (json['success'].indexOf('Success') != -1) {
              $(btn).removeAttr('onclick');
              $(btn).attr('onclick', 'wishlist.remove(' + product_id + ',this, "' + from_page + '")');
              $(btn).attr('data-original-title', "");
              $(btn).html('<i class="giga fa-heart"></i>&nbsp;Remove from Saved Items');
            }
          }

          // $('#wishlist-total span').html(json['total']);
          $('#wishlist-total').attr('title', json['total']);
          if (json.hasOwnProperty("totalNum")) {
            $('#wishlist-total-num').html(json['totalNum']);
          }

          // $('html, body').animate({scrollTop: 0}, 'slow');
          window.setTimeout(function () {
            $('[data-dismiss="alert"]').alert('close');
          }, 5000);
        } else {
          $('.alert-dismissible').remove();

          if (json['redirect']) {
            location = json['redirect'];
          }

          if (json['success']) {
            $('#content').parent().before('<div class="alert alert-success alert-dismissible container"><i class="fa fa-check-circle"></i> ' + json['success'] + ' <button type="button" class="close" data-dismiss="alert">&times;</button></div>');
            if (json['success'].indexOf('Success') != -1) {
              $(btn).removeAttr('onclick');
              $(btn).attr('onclick', 'wishlist.remove(' + product_id + ',this)');
              $(btn).attr('data-original-title', "Remove from Saved Items");
              $(btn).find('i').removeClass('fa-heart-o');
              $(btn).find('i').addClass('fa-heart');
            }
          }

          // $('#wishlist-total span').html(json['total']);
          $('#wishlist-total').attr('title', json['total']);
          if (json.hasOwnProperty("totalNum")) {
            $('#wishlist-total-num').html(json['totalNum']);
          }

          // $('html, body').animate({scrollTop: 0}, 'slow');
          window.setTimeout(function () {
            $('[data-dismiss="alert"]').alert('close');
          }, 5000);
        }
      },
      error: function (xhr, ajaxOptions, thrownError) {
        if (xhr.status == 302) {
          window.location.reload();
          return ;
        }
        alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
      }
    });
  },
  'unavailable': function (product_id, btn) {
    $('.alert-dismissible').remove();
    $('#content').parent().before('<div class="alert alert-success alert-dismissible container"><i class="fa fa-check-circle"></i> Failed : The product is unavailable.Please contact seller! <button type="button" class="close" data-dismiss="alert">&times;</button></div>');
    $('html, body').animate({scrollTop: 0}, 'slow');

  },
  'remove': function (product_id, btn, from_page) {
    $.ajax({
      url: 'index.php?route=account/wishlist/remove',
      type: 'post',
      data: 'product_id=' + product_id,
      dataType: 'json',
      success: function (json) {
        if (from_page == 'home') {
          if (json['redirect']) {
            location = json['redirect'];
          }

          if (json['success']) {
            // $('#content').parent().before('<div class="alert alert-success alert-dismissible container"><i class="fa fa-check-circle"></i> ' + json['text'] + ' <button type="button" class="close" data-dismiss="alert">&times;</button></div>');
            $(btn).removeAttr('onclick');
            $(btn).attr('onclick', 'wishlist.add(' + product_id + ',this,"' + from_page + '")');
            $(btn).attr('data-original-title', "Add to Saved Items");
            $(btn).find('i').removeClass('fa-heart');
            $(btn).find('i').addClass('fa-heart-o');
          }

          // $('#wishlist-total span').html(json['total']);
          $('#wishlist-total').attr('title', json['total']);
          if (json.hasOwnProperty("totalNum")) {
            $('#wishlist-total-num').html(json['totalNum']);
          }
          layer.config({
            skin: 'wishlist-class'
          });
          layer.msg('<i class="fa fa-check-circle"></i>' + '&nbsp' + json['text'] + '<button type="button" class="close" data-dismiss="alert">×</button>', {
            // icon: 1,
            offset: '50px',
            area: '600px',
            closeBtn: 2,
            skin: 'wishlist-class',
            time: 3000 //2秒关闭（如果不配置，默认是3秒）
          }, function () {
            //do something
          });
        } else if (from_page == 'product') {
          if (json['redirect']) {
            location = json['redirect'];
          }

          if (json['success']) {
            $('#content').parent().before('<div class="alert alert-success alert-dismissible container"><i class="fa fa-check-circle"></i> ' + json['text'] + ' <button type="button" class="close" data-dismiss="alert">&times;</button></div>');
            $(btn).removeAttr('onclick');
            $(btn).attr('onclick', 'wishlist.add(' + product_id + ',this, "' + from_page + '")');
            $(btn).attr('data-original-title', "");
            $(btn).html('<i class="giga fa-heart-o"></i>&nbsp;Add to Saved Items');
          }

          // $('#wishlist-total span').html(json['total']);
          $('#wishlist-total').attr('title', json['total']);
          if (json.hasOwnProperty("totalNum")) {
            $('#wishlist-total-num').html(json['totalNum']);
          }

          // $('html, body').animate({scrollTop: 0}, 'slow');
          window.setTimeout(function () {
            $('[data-dismiss="alert"]').alert('close');
          }, 5000);
        } else {
          $('.alert-dismissible').remove();

          if (json['redirect']) {
            location = json['redirect'];
          }

          if (json['success']) {
            $('#content').parent().before('<div class="alert alert-success alert-dismissible container"><i class="fa fa-check-circle"></i> ' + json['text'] + ' <button type="button" class="close" data-dismiss="alert">&times;</button></div>');
            $(btn).removeAttr('onclick');
            $(btn).attr('onclick', 'wishlist.add(' + product_id + ',this)');
            $(btn).attr('data-original-title', "Add to Saved Items");
            $(btn).find('i').removeClass('fa-heart');
            $(btn).find('i').addClass('fa-heart-o');
          }


          // $('#wishlist-total span').html(json['total']);
          $('#wishlist-total').attr('title', json['total']);
          if (json.hasOwnProperty("totalNum")) {
            $('#wishlist-total-num').html(json['totalNum']);
          }

          // $('html, body').animate({scrollTop: 0}, 'slow');
          window.setTimeout(function () {
            $('[data-dismiss="alert"]').alert('close');
          }, 5000);
        }
      },
      error: function (xhr, ajaxOptions, thrownError) {
        alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
      }
    });
  }
};

var compare = {
  'add': function (product_id) {
    $.ajax({
      url: 'index.php?route=product/compare/add',
      type: 'post',
      data: 'product_id=' + product_id,
      dataType: 'json',
      success: function (json) {
        $('.alert-dismissible').remove();

        if (json['success']) {
          $('#content').parent().before('<div class="alert alert-success alert-dismissible container"><i class="fa fa-check-circle"></i> ' + json['success'] + ' <button type="button" class="close" data-dismiss="alert">&times;</button></div>');

          $('#compare-total').html(json['total']);

          // $('html, body').animate({scrollTop: 0}, 'slow');
          window.setTimeout(function () {
            $('[data-dismiss="alert"]').alert('close');
          }, 5000);
        }
      },
      error: function (xhr, ajaxOptions, thrownError) {
        alert(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
      }
    });
  },
  'remove': function () {

  }
};

/* Agree to Terms */
$(document).delegate('.agree', 'click', function (e) {
  e.preventDefault();

  $('#modal-agree').remove();

  var element = this;

  $.ajax({
    url: $(element).attr('href'),
    type: 'get',
    dataType: 'html',
    success: function (data) {
      html = '<div id="modal-agree" class="modal">';
      html += '  <div class="modal-dialog">';
      html += '  <div class="modal-content">';
      html += '    <div class="modal-header">';
      html += '    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>';
      html += '    <h4 class="modal-title">' + $(element).text() + '</h4>';
      html += '    </div>';
      html += '    <div class="modal-body">' + data + '</div>';
      html += '  </div>';
      html += '  </div>';
      html += '</div>';

      $('body').append(html);

      $('#modal-agree').modal('show');
    }
  });
});

// Autocomplete */
(function ($) {
  $.fn.autocomplete = function (option) {
    return this.each(function () {
      this.timer = null;
      this.items = [];

      $.extend(this, option);

      $(this).attr('autocomplete', 'off');

      // Focus
      $(this).on('focus', function () {
        this.request();
      });

      // Blur
      $(this).on('blur', function () {
        setTimeout(function (object) {
          object.hide();
        }, 200, this);
      });

      // Keydown
      $(this).on('keydown', function (event) {
        switch (event.keyCode) {
          case 27: // escape
            this.hide();
            break;
          default:
            this.request();
            break;
        }
      });

      // Click
      this.click = function (event) {
        event.preventDefault();

        value = $(event.target).parent().attr('data-value');

        if (value && this.items[value]) {
          this.select(this.items[value]);
        }
      };

      // Show
      this.show = function () {
        var pos = $(this).position();

        $(this).siblings('ul.dropdown-menu').css({
          top: pos.top + $(this).outerHeight(),
          left: pos.left
        });

        $(this).siblings('ul.dropdown-menu').show();
      };

      // Hide
      this.hide = function () {
        $(this).siblings('ul.dropdown-menu').hide();
      };

      // Request
      this.request = function () {
        clearTimeout(this.timer);

        this.timer = setTimeout(function (object) {
          object.source($(object).val(), $.proxy(object.response, object));
        }, 200, this);
      };

      // Response
      this.response = function (json) {
        html = '';

        if (json.length) {
          for (i = 0; i < json.length; i++) {
            this.items[json[i]['value']] = json[i];
          }

          for (i = 0; i < json.length; i++) {
            if (!json[i]['category']) {
              html += '<li data-value="' + json[i]['value'] + '"><a href="#">' + json[i]['label'] + '</a></li>';
            }
          }

          // Get all the ones with a categories
          var category = [];

          for (i = 0; i < json.length; i++) {
            if (json[i]['category']) {
              if (!category[json[i]['category']]) {
                category[json[i]['category']] = [];
                category[json[i]['category']]['name'] = json[i]['category'];
                category[json[i]['category']]['item'] = [];
              }

              category[json[i]['category']]['item'].push(json[i]);
            }
          }

          for (i in category) {
            html += '<li class="dropdown-header">' + category[i]['name'] + '</li>';

            for (j = 0; j < category[i]['item'].length; j++) {
              html += '<li data-value="' + category[i]['item'][j]['value'] + '"><a href="#">&nbsp;&nbsp;&nbsp;' + category[i]['item'][j]['label'] + '</a></li>';
            }
          }
        }

        if (html) {
          this.show();
        } else {
          this.hide();
        }

        $(this).siblings('ul.dropdown-menu').html(html);
      };

      $(this).after('<ul class="dropdown-menu"></ul>');
      $(this).siblings('ul.dropdown-menu').delegate('a', 'click', $.proxy(this.click, this));

    });
  }
})(window.jQuery);

if (typeof getSeller == "undefined") {
  function getSeller(seller_id) {
    sessionStorage.removeItem('bigClientDiscountShowed');
    let href = 'index.php?route=customerpartner/profile&id=' + seller_id;
    window.open(href,'_blank');
  }
}

function layerConfirm(msg, option) {
  if (typeof layer !== 'undefined') {
    return new Promise(function (resolve, reject) {
      let defaultOption = {
        btn: ['Yes', 'No'],
        btnAlign: 'c',
        skin: 'yzc_layer',
        title: 'Confirm',
        area: '500px',
        end: function () {
          reject();
        }
      };
      $.extend(defaultOption, option || {});
      layer.confirm(msg || '',
        defaultOption,
        function () {
          resolve();
        });
    })
  } else {
    console.error('layer is required.')
  }
}

function layerMsg(msg, option) {
  let defaultOption = {
    btn: ['ok'],
    btnAlign: 'c',
    skin: 'yzc_layer',
    title: 'Message',
    area: 'auto',
  };
  $.extend(defaultOption, option || {});
  return layerConfirm(msg, defaultOption)
}

function layerConfirmResolve(msg, option) {
  return new Promise(function (resolve, reject) {
    resolve();
  })
}

/**
 * Compare the size of two parameters
 *
 * @param {string|float} left - the left param
 * @param {string|float} right - the right param
 * @param {string} operation - < / <= / > / >= / =
 * @param {int} precision - precision of comparison
 * @returns {boolean}
 */
function comparison(left, right, operation, precision) {
  let _left = Number.parseFloat(parseFloat(left).toFixed(precision));
  let _right = Number.parseFloat(parseFloat(right).toFixed(precision));
  let _result;
  switch (operation) {
    case '>':
      _result = _left > _right;
      break;
    case '>=':
      _result = _left >= _right;
      break;
    case '<':
      _result = _left < _right;
      break;
    case '<=':
      _result = _left <= _right;
      break;
    case '=':
      _result = _left === _right;
      break;
    default:
      _result = undefined;
  }
  return _result;
}

var DateTime = {
  getFormatDay: function (day) {
    //Date()返回当日的日期和时间。
    var days = new Date();
    //getTime()返回 1970 年 1 月 1 日至今的毫秒数。
    var gettimes = days.getTime() + 1000 * 60 * 60 * 24 * day;
    //setTime()以毫秒设置 Date 对象。
    days.setTime(gettimes);
    var year = days.getFullYear();
    var month = days.getMonth() + 1;
    if (month < 10) {
      month = "0" + month;
    }
    var today = days.getDate();
    if (today < 10) {
      today = "0" + today;
    }
    return year + "-" + month + "-" + today;
  }
};

/**
 * 简易的倒计时插件
 * 2个触发事件点:cd.interval:每次执行时候触发，cd.done:定时器结束触发
 * example：
 *  $(this).easyCountDown(maxTime)
 *     .on('cd.interval', function (e, obj) {
 *       let minutes = '0' + obj.m;          let seconds = '0' + obj.s;
 *       $(this).text(minutes.substring(minutes.length - 2) + ':' + seconds.substring(seconds.length - 2));
 *     })
 *     .on('cd.done', function () {
 *       $(this).parentsUntil('.clock-con').remove();
 *     })
 * @param time 总倒计时秒数
 * @param step 步进时间秒 默认为 1秒
 * @returns {*|jQuery.fn.init|jQuery|HTMLElement}
 */
jQuery.fn.easyCountDown = function (time, step) {
  let item = $(this);
  step = parseInt(step || 1);
  let timer = setInterval(function () {
    if (!!time && time > 0) {
      let day = Math.floor(time / 86400),
        hour = Math.floor((time % 86400) / 3600),
        minutes = Math.floor((time % 3600) / 60),
        seconds = Math.floor(time % 60);
      item.trigger('cd.interval', {d: day, h: hour, m: minutes, s: seconds});
      --time;
    } else {
      clearInterval(timer);
      item.trigger('cd.done');
    }
  }, step * 1000);
  return item;
};

function initPopover() {
  // popover
  // 简单使用见: https://v3.bootcss.com/javascript/#popovers
  $('[data-toggle="popover"]').popover();
  // 扩展使用：
  // 需求：弹出内容为html内容，且内容较为复杂，且需要鼠标移上去可以悬停显示
  // 配置：
  // 1.触发节点上增加 data-toggle="popover-html" data-html-el="#html-el-id"
  // 2.新增一个 html 节点,id="html-el-id",内容为复杂的html，外层增加用 display:none 包裹（用于隐藏不可见）
  $('[data-toggle="popover-html"]').each(function () {
    var content = $(this).data('html');
    if (!content) {
      var htmlEl = $(this).data('html-el');
      if (htmlEl) {
        content = $(htmlEl).html();
      }
    }
    if (!content) {
      console.error('必须配置 data-html 或者 data-html-el');
      return;
    }
    $(this).popover({
      html: true,
      animation: false,
      trigger: 'manual',
      content: content,
      template: '<div class="popover"><div class="arrow"></div><div class="popover-title"></div><div class="popover-content"></div></div>'
    }).on('mouseenter', function () {
      var _this = this;
      $(this).popover("show");
      $(this).children(".icon-V10_shouyeyouxiadown").removeClass("icon-V10_shouyeyouxiadown").addClass("icon-V10_shouyeyouxiaTOP");
      $(".popover").on("mouseleave", function () {
        $(_this).children(".icon-V10_shouyeyouxiaTOP").removeClass("icon-V10_shouyeyouxiaTOP").addClass("icon-V10_shouyeyouxiadown");
        $(_this).popover('hide');
      });
    }).on('mouseleave', function () {
      var _this = this;
      setTimeout(function () {
        if (!$(".popover:hover").length) {
          $(_this).children(".icon-V10_shouyeyouxiaTOP").removeClass("icon-V10_shouyeyouxiaTOP").addClass("icon-V10_shouyeyouxiadown");
          $(_this).popover("hide");
        }
      }, 300);
    });
  });
}

/**
 * tab-ajax 的使用
 * 参考 catalog/view/theme/default/template/account/purchase_order_list.twig
 * ！！注意每个 load 的内容之间的 js 方法会相互影响的问题
 */
function initTabAjax() {
  var tabAjaxElStr = '.nav-tabs a[data-toggle="tab-ajax"]',
    tabAjaxEls = $(tabAjaxElStr);
  if (tabAjaxEls.length <= 0) {
    return;
  }
  var currentUrl = window.location.href;
  if (currentUrl.indexOf('#') !== -1) {
    currentUrl = currentUrl.substr(0, currentUrl.indexOf('#'));
  }
  tabAjaxEls.each(function () {
    // 解决右击tab页会导致打开首页链接的问题
    var oldHref = $(this).attr('href');
    if (oldHref.indexOf('#') === 0) {
      $(this).attr('href', currentUrl + oldHref);
      $(this).attr('data-target', oldHref);
    }
  });
  var tabContentLoaded = {};
  function loadAjaxTabContent(id) {
    var tabEl = $('.nav-tabs a[data-toggle="tab-ajax"][data-target="'+ id +'"]'),
      loadUrl = tabEl.attr('data-url'),
      tabLiEl = tabEl.parent('li'),
      alwaysLoad = tabEl.attr('data-always-load') || false;
    // tab 页高亮
    tabLiEl.siblings('li').removeClass('active');
    tabLiEl.addClass('active');
    // 显示隐藏 content
    $('.tab-content .tab-pane').hide();
    $('.tab-content .tab-pane' + id).show();
    // 加载内容
    if (!tabContentLoaded[id] || alwaysLoad) {
      block.blockUI();
      $(id).load(loadUrl, function () {
        tabContentLoaded[id] = true;
        block.unBlockUI();
      })
    }
  }
  // tab 点击事件
  $('body').on('click', '.nav-tabs a[data-toggle="tab-ajax"]', function (e) {
    e.preventDefault();
    loadAjaxTabContent($(this).attr('data-target'));
  });
  // 首次加载页面时自动载入 hash 或者第一个 tab
  loadAjaxTabContent(window.location.hash || tabAjaxEls.first().attr('data-target'));
}

$(function () {
  initPopover();
  initTabAjax();
});

// #32120 seller Affiliation功能，涉及修改处有 所有列表、产品详情页、购物车、支付页面底部推荐产品，故写公共方法
var sellerAffiliationCommonFn={
  actionAffiliation:function(seller_id) {
    var postData = {
      'subject': 'Establish Contact',
      'message': 'The other party requests to establish contact with you, do you agree?',
      'seller_id':seller_id,
    };
    //发送站内信
    $.ajax({
      url: 'index.php?route=customerpartner/profile/establishContact',
      data: postData,
      async: false,
      type: 'POST',
      dataType: 'json',
      success: function(json) {
        if (json['error']) {
          $.toast({
            heading: false,
            text: json['error'],
            position: 'top-center',
            showHideTransition: 'fade',
            icon: 'error',
            hideAfter: 5000,
            allowToastClose: false,
            loader: false
          });
        } else if (json['success']) {
          $.toast({
            heading: false,
            text: json['success'],
            position: 'top-center',
            showHideTransition: 'fade',
            icon: 'success',
            hideAfter: 5000,
            allowToastClose: false,
            loader: false
          });
        }
      },
    })
  }
};