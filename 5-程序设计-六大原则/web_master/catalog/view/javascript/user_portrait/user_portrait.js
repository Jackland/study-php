var get_user_portrait_data = function(obj, customer_id) {
  if (sessionStorage.getItem(customer_id)) {
    layer.closeAll();
    $(document).delegate('.user_portrait', 'mouseover', function() {
      $(document).undelegate('.user_portrait', 'mouseout');
      $(document).undelegate('.user_portrait', 'mouseover');
      tips_mouseover(this);
    });
    $(document).delegate('.user_portrait', 'mouseout', function() {
      tips_mouseout(this);
    })
    layer.tips(sessionStorage.getItem(customer_id), obj, { tips: [1, '#EDEFF3'], area: ['320px', 'auto'], time: -1 });
  } else {
    $.ajax({
      // url: '{{ user_portrait_url }}',
      url: 'index.php?route=common/user_portrait/get_user_portrait_data',
      type: 'post',
      data: { 'customer_id': customer_id },
      dataType: 'json',
      success: function(json) {
        layer.closeAll();
        sessionStorage.setItem(customer_id, json.msg);
        layer.tips(json.msg, obj, { tips: [1, '#EDEFF3'], area: ['320px', 'auto'], time: -1 });
      }
    });
  }
}


var xx;
var yy;


//tips   鼠标mouseover 事件
var tips_mouseover = function(obj) {
  var width = $(obj).width();
  var height = $(obj).height();
  var offset = $(obj).offset();
  var top = offset.top;
  var left = offset.left;
  // console.log(width + ',' + height + ',' + top + ',' + left);
  //清除全部样式
  $('.user_portrait').removeClass('user_portrait_over');
  layer.closeAll();
  $(obj).addClass('user_portrait_over');

  layer.open({
    type: 3,
    icon: 1,
    shade: [0.1, '#ccc'],
    // scrollbar: false,
    success: function() {
      $('body').mousemove(function(e) {
        e = e || window.event;
        xx = e.pageX || e.clientX + document.body.scroolLeft;
        yy = e.pageY || e.clientY + document.body.scrollTop;
      })
      customer_id = $(obj).data('user_customer_id');
      get_user_portrait_data(obj, customer_id)
    },
    end: function(e) {
      //判断是否移动位置
      if ((xx > left && xx < left + width) && (yy > top && yy < top + height)) {
        //还在里面
        // console.log('还在里面');
      } else {
        // console.log('不在里面');
        tips_mouseout(obj);
      }
      $(document).delegate('.user_portrait', 'mouseover', function() {
        $(document).undelegate('.user_portrait', 'mouseout');
        $(document).undelegate('.user_portrait', 'mouseover');
        tips_mouseover(this);
      });
      $(document).delegate('.user_portrait', 'mouseout', function() {
        tips_mouseout(this);
      })
    }
  });
}


//tips   鼠标out 事件
var tips_mouseout = function(obj) {
  // event.stopPropagation();
  $(obj).removeClass('user_portrait_over');
  layer.closeAll();
}

$(document).delegate('.user_portrait', 'mouseover', function(e) {
  $(document).undelegate('.user_portrait', 'mouseout');
  $(document).undelegate('.user_portrait', 'mouseover');
  tips_mouseover(this);
});


// ----------------------------------------------------------------------------------------------------------------
// 一下是18273新弹框优化action-userinfo
function bindMouseEnter() {
  $('body').delegate('.action-userinfo', 'mouseenter', function(event) {
    var that = this;
    var popId = $(that).attr('aria-describedby');
    console.log('mouseenter')
    //鼠标悬浮
    if (!popId) {
      userinfoTipsMouseEnter(this);
    }
    $('body').undelegate('.action-userinfo', 'mouseenter');
  });
}
bindMouseEnter();


$('body').delegate('.action-userinfo', 'mouseleave', function(event) {
  console.log('mouseleave')
  $("[data-toggle='popover']").popover('hide');
  bindMouseEnter();
});

//tips   鼠标mouseover 事件
var userinfoTipsMouseEnter = function(obj) {
  var customer_id = $(obj).data('user_customer_id');
  if (sessionStorage.getItem(customer_id)) {
    // 缓存
    $(obj).attr('data-content', sessionStorage.getItem(customer_id));
    $(obj).popover('show');
  } else {
    $.ajax({
      url: 'index.php?route=common/user_portrait/get_user_portrait_data',
      type: 'post',
      data: { 'customer_id': customer_id },
      dataType: 'json',
      success: function(json) {
        sessionStorage.setItem(customer_id, json.msg);
        $(obj).attr('data-content', json.msg)
        $(obj).popover('show');
      },
      err: function() {
      }
    });
  }
}