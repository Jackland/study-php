/**
 * @see App\Views\Components\SevenMoor
 */
var sevenMoor = {
  init: function (userId, nickname, chatType, avatar) {
    // 客户端初始化，必须使用 window.qimoClientId 定义为全局变量 7 moor 才能接收到
    window.qimoClientId = {userId: userId, nickName: nickname};
    this._hideOriginChatBtn();
    this._initCnzzEvent();
    this._initOtherClickTrigger(chatType, avatar);
  },
  // 隐藏原来的样式
  _hideOriginChatBtn() {
    $('head').append($('<style>#chatBtn{display: none !important;}</style>'));
  },
  // CNZZ 点击事件
  _initCnzzEvent() {
    $('body').on('click', '#chatBtn', function() {
      window.CNZZ && window.CNZZ.triggerEvent('页面右侧', 'Ft_Seller');
    });
  },
  // 通过其他点击触发
  _initOtherClickTrigger(chatType, avatar) {
    var triggerEl;
    if (chatType === 1) {
      // 平台客服
      triggerEl = '.onlineChatPlatformTrigger';
      $(triggerEl).show();
    } else if (chatType === 2) {
      // 店铺联系客服图标
      let chatSellerHtml = 
        `<div class="onlineChatSellerTrigger hover1600" data-cnzz-event="页面右侧|Ft_ Online Chat">
          <div class="suspension-btn">
            <div class="message-icon">
              ${avatar?'<div class="store-img"><img alt="" src="' + avatar + '"/></div>' : '<i class="giga icon-yushangjialiaotian-01"></i>'}
              <span class="hide1600">Online Chat</span>
              <span class="action-tooltip-1600">Online Chat</span>
            </div>
          </div>
        </div>`
      triggerEl = '.onlineChatSellerTrigger';
      $('#rightSuspensionFooter').prepend(chatSellerHtml);
    }
    if (triggerEl) {
      $('body').on('click', triggerEl, function () {
        $('#chatBtn').trigger('click');
      });
    }
  }
}
