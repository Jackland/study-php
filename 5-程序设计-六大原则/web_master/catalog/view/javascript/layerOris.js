// #1452 优惠券
// 统一样式弹框
// author: pyp
var layerOris = {
  // 默认白底弹框
  commonLayer: function(type, title, subtitle, second) { // type: success、error; second:消失时间s,不给就默认不消失
    type = type || 'success';
    title = title || (type==='success'?'Successful claim':'Claim failed');
    subtitle = subtitle || (type==='success'?'Congratulations! You got this coupon':'Something went wrong. Please try again!');
    var that = this
    var index = layer.open({
      type: 1,
      title: false,
      area: ['400px', '240px'], //宽高
      shadeClose: false,
      skin: 'layer-oris-common',
      content: `<div class="content">
      						<div class="close-btn"></div>
      						<div class="image ${type}"></div>
      						<div class="title">${title}</div>
      						<div class="subtitle">${subtitle}</div>
      						${second?`<div class="botTime">This window will automatically close in <span class="second">${second}</span> seconds.</div>`:''}
      					</div>`,
      success: function(layero, index) {
        // 倒计时
        if (second) {
        	that.countDown(second, layero,index)
        }
      }
    });
  },
  countDown(second, layero, index) {
    if (second <= 0) {
      layer.close(index);
      return;
    } else {
      // 切换秒
      layero.find('.second').html(second);
    }
    var that = this
    setTimeout(function() {
      that.countDown(second - 1,layero, index)
    }, 1000);
  },
  // 确定confirm弹框
  // options 弹框内容options:{content:内容，btn: 按钮 ...}
  // confirmCallback确定回调函数
  confirmLayer: function(options, confirmCallback, cancelCalback, btn2Callback) {
    var type = options['type'] || 1;
    var title = options['title'] || 'Confirm';
    var content = options['content'] || 'Do you confirm to submit?';
    var btn = options['btn'] || ['Confirm', 'Cancel'];
    var btnAlign = options['btnAlign'] || 'c';
    var area = options['area'] || ['360px', 'auto'];
    var skin = options['skin'] || 'yzc_layer';
    var shadeClose = options['shadeClose'] || false;  // 是否点击遮罩关闭
    var closeBtn = options['closeBtn'] || 1;
    // 参数传递
    var params = options['params']
    layer.open({
      type: type,
      title: title,
      closeBtn: closeBtn,
      skin: skin,
      btnAlign: btnAlign,
      shadeClose: shadeClose,
      offset: 'auto',
      area: area,
      content: content,
      btn: btn,
      yes: function (index, layero) {
        if (confirmCallback) {
          confirmCallback(index, layero, params);
        } else {
          layer.close(index);
        }
      },
      btn2: function (index, layero) {
        if (btn2Callback) {
          btn2Callback(index, layero, params);
        } else {
          layer.close(index);
        }
      },
      cancel: function (index, layero) {
        if (cancelCalback) {
          cancelCalback(index, layero, params);
        } else {
          layer.close(index);
        }
      },
    });
  }
}
