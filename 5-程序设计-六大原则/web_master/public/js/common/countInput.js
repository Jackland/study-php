/**
 * 计数Input通用组件 (JQuery插件)
 * 
 * @author 前端组
 * @description 有加减号只能输入数字的通用组件，首次用于需求 #24701 上门取货流程优化
 * @usage div使用单独的id并初始化，使用示例补充在前端组wiki上
 */

class CountInput {
  constructor(dom, value = 0, max = -1, min = 0, changeCallback = () => { }, plusCallback = () => { }, minusCallback = () => { }, id = "") {
    this.dom = dom;
    this.setValue.bind(this)(value, max, min, changeCallback, plusCallback, minusCallback, id)

    this.inputDisabled = false;
    this.plusDisabled = false;
    this.minusDisabled = false;

    $(dom).on('blur', 'input', this.blurChange.bind(this));

    $(dom).on('keyup', 'input', this.inputEvent.bind(this));
  }

  // 限制输入数字负号外的其他字符
  inputEvent(e) {
    this.value = Number(('' + this.value).replace(/[e\.]/g,'')) || 0;
    $(this.dom).find('input').val(this.value);
    // 回车输入框失焦
    if (e.key === 'Enter' || e.keyCode === 13) {
      $(this.dom).find('input').blur();
      this.blurChange.bind(this);
    }
  }

  // 失焦时限制输入的内容在min和max之间
  blurChange(e) {
    let val = $(this.dom).find('input').val();
    
    if (this.max > 0) {
      this.value = Math.min(val, this.max);
      val = this.value;
      $(this.dom).find('input').val(this.value);
    }
    this.value = Math.max(val, this.min);
    $(this.dom).find('input').val(this.value);
    this.changeCallback(this.value, this.id);
  }

  //设置默认值及回调
  setValue(value = 0, max = -1, min = 0, changeCallback = () => { }, plusCallback = () => { }, minusCallback = () => { }, id = "") {
    this.value = value;
    this.max = max;
    this.min = min;
    this.changeCallback = changeCallback;
    this.plusCallback = plusCallback;
    this.minusCallback = minusCallback;
    this.id = id;
  }

  updateValue(options) {
    for (let key in options) {
      if (options[key] != undefined) {
        this[key] = options[key];
      }
    }
  }

  //填充组件内容
  fillContent() {
    $(this.dom).html(`
      <div class="input-group count-input-container">
        <span class="input-group-addon count-btn count-minus-btn">
          <span class="giga icon-a-V10_jibuqijianhao count-icon"></span>
        </span>
        <input type="number" class="oris-input count-input"></input>
        <span class="input-group-addon count-btn count-plus-btn">
          <span class="giga icon-iconfonticon02 count-icon"></span>
        </span>
      </div>
    `);

    this.update();
  }

  //数值发生变化重新渲染组件
  rerender(options) {
    this.updateValue(options);
    this.update();
  }

  //检查按钮状态(是否需要禁用)
  check() {
    if (this.value >= this.max && this.max >= 0) {
      this.disablePlus();
    } else {
      this.enablePlus();
    }
    if (this.value <= this.min) {
      this.disableMinus();
    } else {
      this.enableMinus();
    }
  }

  plus() {
    if ((this.value < this.max || this.max < 0) && !this.plusDisabled) {
      this.value++;
      this.plusCallback(this.value, this.id);
      this.changeCallback(this.value, this.id);
      this.update();
    }
  }

  minus() {
    if (this.value > this.min && !this.minusDisable) {
      this.value--;
      this.minusCallback(this.value, this.id);
      this.changeCallback(this.value, this.id);
      this.update();
    }
  }

  init() {
    this.fillContent();
    $(this.dom).on('click', '.count-minus-btn', this.minus.bind(this));
    $(this.dom).on('click', '.count-plus-btn', this.plus.bind(this));
    var that = this;
    $(this.dom).on('input blur', '.count-input', function (e) {
      that.value = Number(e.target.value);
      if (e.target.value == '') {
        that.value = 0;
      }
      that.check();
    });
  }

  //刷新
  update() {
    this.check();
    $(this.dom).find('.count-input').val(this.value);
  }

  //按钮禁用部分
  //禁用输入框
  disable() {
    $(this.dom).find('.count-input').attr('disabled', 'disabled');
    this.disablePlus();
    this.disableMinus();
    this.inputDisabled = true;
    this.plusDisabled = true;
    this.minusDisabled = true;
  }

  //禁用加号
  disablePlus() {
    $(this.dom).find('.count-plus-btn').addClass('disabled');
    this.plusDisabled = true;
  }

  //禁用减号
  disableMinus() {
    $(this.dom).find('.count-minus-btn').addClass('disabled');
    this.minusDisabled = true;
  }

  //按钮启用部分
  enable() {
    $(this.dom).find('.count-input').removeAttr('disabled');
    this.enablePlus();
    this.enableMinus();
    this.inputDisabled = false;
    this.plusDisabled = false;
    this.minusDisabled = false;
  }

  //启用加号
  enablePlus() {
    $(this.dom).find('.count-plus-btn').removeClass('disabled');
    this.plusDisabled = false;
  }

  //启用减号
  enableMinus() {
    $(this.dom).find('.count-minus-btn').removeClass('disabled');
    this.minusDisabled = false;
  }
}


// CountInput 二次封装成JQuery插件，方便调用
; (function ($) {

  var methods = {
    init: function (options = {}) {
      return this.each(function () {
        let ciInstance = new CountInput(this, options['value'], options['min'], options['max'],
          options['changeCallback'], options['plusCallback'], options['minusCallback'], options['id']);
        ciInstance.init();
        $(this).data('ciInstance', ciInstance);
      })
    },
    disable: function () {
      return this.each(function () {
        if ($(this).data('ciInstance')) $(this).data('ciInstance').disable();
      })
    },
    disablePlus: function () {
      return this.each(function () {
        if ($(this).data('ciInstance')) $(this).data('ciInstance').disablePlus();
      })
    },
    disableMinus: function () {
      return this.each(function () {
        if ($(this).data('ciInstance')) $(this).data('ciInstance').disableMinus();
      })
    },
    enable: function () {
      return this.each(function () {
        if ($(this).data('ciInstance')) $(this).data('ciInstance').enable();
      })
    },
    enablePlus: function () {
      return this.each(function () {
        if ($(this).data('ciInstance')) $(this).data('ciInstance').enablePlus();
      })
    },
    enableMinus: function () {
      return this.each(function () {
        if ($(this).data('ciInstance')) $(this).data('ciInstance').enableMinus();
      })
    },
    update: function (options = {}) {
      return this.each(function () {
        if ($(this).data('ciInstance')) $(this).data('ciInstance').rerender(options);
      })
    }
  };

  $.fn.countinput = function (method) {

    // Method calling logic
    if (methods[method]) {
      return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
    } else if (typeof method === 'object' || !method) {
      return methods.init.apply(this, arguments);
    } else {
      $.error('Method ' + method + ' does not exist on jQuery.countinput');
    }

  };

})(jQuery);

