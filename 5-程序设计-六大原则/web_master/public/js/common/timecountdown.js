/**
 * @file 倒计时工具类
 * 
 * @author 前端组
 * @description 用于页面倒计时展示
 */
class TimeCountDown {

  /**
   * 构造函数
   * @param {Dom} el 需要渲染的dom，默认为`id="countdown"`
   * @param {Number} times 
   * @param {String} split
   */
  constructor(times, el='#countdown', split=":") {
    this.times = times;
    this.el = el;
    this.split = split;
  }

  /**
   * 剩余秒数倒计时
   * @param {params} second 
   */
  calcCountdown(times) {
    times = +times;
    var hour = Math.floor((times) / 3600),
        minute = Math.floor((times % 3600) / 60),
        second = Math.floor(times % 60)
    $(this.el).html(
      `<div class="countdown-hour">${("0" + hour).slice(-2)}</div>
      <span class="countdown-split">${this.split}</span>
      <div class="countdown-minute">${("0" + minute).slice(-2)}</div>
      <span class="countdown-split">${this.split}</span>
      <div class="countdown-second">${("0" + second).slice(-2)}</div>
      `
    )
  }

  /**
   * 开始计时方法
   * @param {Function} finish
   */
  start(finish=function(){}) {
    var that = this;
    let symbol = Symbol.for(that.el);
    window.clearInterval(window[symbol]);
    that.calcCountdown.bind(that)(that.times);
    that.times --;
    window[symbol] = setInterval(function () {
      that.calcCountdown.bind(that)(that.times);
      if (that.times <= 0) {
        window.clearInterval(window[symbol]);
        if(finish) {
          finish();
        }
      }
      that.times --;
    }, 1000);
  }
}