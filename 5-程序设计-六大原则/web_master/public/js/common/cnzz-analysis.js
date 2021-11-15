// CNZZ 自定义事件，事件自动注入方法
// @link https://open.cnzz.com/a/api/trackevent/
// CNZZ 自定义用户属性事件
// @link https://open.cnzz.com/a/api/setcustomvar/
var _czc = _czc || [];
var CNZZ = {
  _options: {
    loginStatus: 0,
    customerId: 0,
    eventConsoleLog: false,
  },
  init(options = {}) {
    $.extend(this._options, options);
    this._initEventsHandler();
    this._initCustomerHandler();
  },
  _initEventsHandler() {
    $('body').on('click', '[data-cnzz-event]', function() {
      var _this = $(this),
        eventInfo = _this.attr('data-cnzz-event'),
        eventArr = eventInfo.split('|');
      if (eventArr.length < 2) {
        console.error('data-cnzz-event 的值至少需要两个');
        return true;
      }
      window.CNZZ.triggerEvent(eventArr[0], eventArr[1], eventArr[2]);
    });
  },
  _initCustomerHandler() {
    _czc.push(['_setCustomVar', '登录状态', this._options.loginStatus, 2]);
    _czc.push(['_setCustomVar', '用户ID', this._options.customerId, 2]);
  },
  triggerEvent(page, key, value = '', score = 0) {
    _czc.push(['_trackEvent', page, key, value, score]);
    if (!!this._options.eventConsoleLog) {
      console.log(['_trackEvent', page, key, value, score]);
    }
  },
}
window.CNZZ = CNZZ;
