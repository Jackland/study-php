;
(function($) {
  'use strict';

  function noop() {}

  function throttle(func, wait, options) {
    var context, args, result;
    var timeout = null;
    // 上次执行时间点
    var previous = 0;
    if (!options) options = {};
    // 延迟执行函数
    var later = function() {
      // 若设定了开始边界不执行选项，上次执行时间始终为0
      previous = options.leading === false ? 0 : new Date().getTime();
      timeout = null;
      result = func.apply(context, args);
      if (!timeout) context = args = null;
    };
    return function() {
      var now = new Date().getTime();
      // 首次执行时，如果设定了开始边界不执行选项，将上次执行时间设定为当前时间。
      if (!previous && options.leading === false) previous = now;
      // 延迟执行时间间隔
      var remaining = wait - (now - previous);
      context = this;
      args = arguments;
      // 延迟时间间隔remaining小于等于0，表示上次执行至此所间隔时间已经超过一个时间窗口
      // remaining大于时间窗口wait，表示客户端系统时间被调整过
      if (remaining <= 0 || remaining > wait) {
        clearTimeout(timeout);
        timeout = null;
        previous = now;
        result = func.apply(context, args);
        if (!timeout) context = args = null;
        //如果延迟执行不存在，且没有设定结尾边界不执行选项
      } else if (!timeout && options.trailing !== false) {
        timeout = setTimeout(later, remaining);
      }
      return result;
    };
  }

  var isSafari = function() {
    var ua = navigator.userAgent.toLowerCase();
    if (ua.indexOf('safari') !== -1) {
      return ua.indexOf('chrome') > -1 ? false : true;
    }
  }();

  var settings = {
    readonly: false,
    minCount: 0,
    minCountErrorMessage: '',
    limitCount: Infinity,
    limitCountErrorMessage: '',
    maxShowCount: Infinity,
    input: '<input type="text" maxLength="20" placeholder="Search...">',
    data: [],
    searchable: false,
    selectAll: false,
    selectAllText: 'Select All',
    deselectAllText: 'Deselect All',
    searchNoData: '<li class="multselect-noresult">No Results.</li>',
    init: noop,
    choice: noop,
    extendProps: [],
    value: null
  };

  var KEY_CODE = {
    up: 38,
    down: 40,
    enter: 13
  };

  var EVENT_SPACE = {
    click: 'click.iui-dropdown',
    focus: 'focus.iui-dropdown',
    keydown: 'keydown.iui-dropdown',
    keyup: 'keyup.iui-dropdown'
  };

  var ALERT_TIMEOUT_PERIOD = 1000;

  // 创建模板
  function createTemplate() {
    var isLabelMode = this.isLabelMode;
    var searchable = this.config.searchable;
    var templateSearch = searchable ? '<span class="multselect-search">' + this.config.input + '</span>' : '';

    return isLabelMode ? `<div class="multselect-display-label ${this.config.readonly?'mul-readonly':''}"><div class="multselect-chose-list">` + templateSearch + '</div></div><div class="multselect-main">{{ul}}</div>' : '<a href="javascript:;" class="multselect-display" tabindex="0"><span class="multselect-chose-list"></span><a href="javascript:;"  class="multselect-clear-all" tabindex="0">\xD7</a></a><div class="multselect-main">' + templateSearch + '{{ul}}</div>';
  }

  // 小于minCount提示的元素
  function minItemsAlert() {
    var _dropdown = this;
    var _config = _dropdown.config;
    var alertMessage = _config.minCountErrorMessage;
    if (!alertMessage) {
      alertMessage = '\u6700\u4f4e\u9009\u62e9' + _config.minCount + '\u4E2A';
    }
    layer.msg(alertMessage);
  }

  // 超出限制提示
  function maxItemAlert() {
    var _dropdown = this;
    var _config = _dropdown.config;
    var alertMessage = _config.limitCountErrorMessage;
    if (!alertMessage) {
      alertMessage = 'Up to ' + _config.limitCount + ' options can be selected';
    }
    layer.msg(alertMessage);
  }

  // select-option 转 ul-li
  function selectToDiv(str) {
    var result = str || '';
    // 移除select标签
    result = result.replace(/<select[^>]*>/gi, '').replace('</select>', '');
    // 移除 optgroup 结束标签
    result = result.replace(/<\/optgroup>/gi, '');
    result = result.replace(/<optgroup[^>]*>/gi, function(matcher) {
      var groupName = /label="(.[^"]*)"(\s|>)/.exec(matcher);
      var groupId = /data\-group\-id="(.[^"]*)"(\s|>)/.exec(matcher);
      return '<li class="multselect-group" data-group-id="' + (groupId ? groupId[1] : '') + '">' + (groupName ? groupName[1] : '') + '</li>';
    });
    result = result.replace(/<option(.*?)<\/option>/gi, function(matcher) {
      // var value = /value="?([\w\u4E00-\u9FA5\uF900-\uFA2D]+)"?/.exec(matcher);
      var value = $(matcher).val();
      var name = />(.*)<\//.exec(matcher);
      // 强制要求html中使用selected/disabled，而不是selected="selected","disabled="disabled"
      var isSelected = matcher.indexOf('selected') > -1 ? true : false;
      var isDisabled = matcher.indexOf('disabled') > -1 ? true : false;
      var extendAttr = ''
      var extendProps = matcher.replace(/data-(\w+)="?(.[^"]+)"?/g, function($1) {
        extendAttr += $1 + ' '
      });
      return '<li ' + (isDisabled ? ' disabled' : ' tabindex="0"') + ' data-value="' + (value || '') + '" class="multselect-option ' + (isSelected ? 'multselect-chose' : '') + '" ' + extendAttr + '>' + (name ? name[1] : '') + '</li>';
    });

    return result;
  }

  // object-data 转 select-option
  function objectToSelect(data) {
    var dropdown = this;
    var map = {};
    var result = '';
    var name = [];
    var selectAmount = 0;
    var extendProps = dropdown.config.extendProps;

    if (!data || !data.length) {
      return false;
    }

    $.each(data, function(index, val) {
      // disable 权重高于 selected
      var hasGroup = val.groupId;
      var isDisabled = val.disabled ? ' disabled' : '';
      var isSelected = val.selected && !isDisabled ? ' selected' : '';
      var extendAttr = ''
      $.each(extendProps, function(index, value) {
        if (val[value]) {
          extendAttr += 'data-' + value + '="' + val[value] + '" '
        }
      })
      var temp = '<option' + isDisabled + isSelected + ' value="' + val.id + '" ' + extendAttr + '>' + val.name + '</option>';
      if (isSelected) {
        let selectItem = `<span class="multselect-selected">${val.name }`;
        if (dropdown.config.readonly) {
          selectItem += '</span>';
        } else {
          selectItem += `<i class="del" data-id="${val.id}"></i></span>`;
        }
        name.push(selectItem);
        selectAmount++;
      }
      // 判断是否有分组
      if (hasGroup) {
        if (map[val.groupId]) {
          map[val.groupId] += temp;
        } else {
          //  &janking& just a separator
          map[val.groupId] = val.groupName + '&janking&' + temp;
        }
      } else {
        map[index] = temp;
      }
    });

    $.each(map, function(index, val) {
      var option = val.split('&janking&');
      // 判断是否有分组
      if (option.length === 2) {
        var groupName = option[0];
        var items = option[1];
        result += '<optgroup label="' + groupName + '" data-group-id="' + index + '">' + items + '</optgroup>';
      } else {
        result += val;
      }
    });

    return [result, name, selectAmount];
  }

  // select-option 转 object-data
  //
  function selectToObject(el) {
    var $select = el;
    var result = [];

    function readOption(key, el) {
      var $option = $(el);
      this.id = $option.prop('value');
      this.name = $option.text();
      this.disabled = $option.prop('disabled');
      this.selected = $option.prop('selected');
    }

    $.each($select.children(), function(key, el) {
      var tmp = {};
      var tmpGroup = {};
      var $el = $(el);
      if (el.nodeName === 'OPTGROUP') {
        tmpGroup.groupId = $el.data('groupId');
        tmpGroup.groupName = $el.attr('label');
        $.each($el.children(), $.proxy(readOption, tmp));
        $.extend(tmp, tmpGroup);
      } else {
        $.each($el, $.proxy(readOption, tmp));
      }
      result.push(tmp);
    });

    return result;
  }

  var action = {
    show: function(event) {
      event.stopPropagation();
      var _dropdown = this;
      $(document).trigger('click.dropdown');
      _dropdown.$el.addClass('active');
    },
    search: throttle(function(event) {
      var _dropdown = this;
      var _config = _dropdown.config;
      var $el = _dropdown.$el;
      var $input = $(event.target);
      var intputValue = $input.val();
      var data = _dropdown.config.data;
      var result = [];
      if (event.keyCode > 36 && event.keyCode < 41) {
        return;
      }
      $.each(data, function(key, value) {
        if ((value.groupName && value.groupName.toLowerCase().indexOf(intputValue.toLowerCase()) > -1) || value.name.toLowerCase().indexOf(intputValue.toLowerCase()) > -1 || '' + value.id === '' + intputValue) {
          result.push(value);
        }
      });
      let elemLi = selectToDiv(objectToSelect.call(_dropdown, result)[0]);
      // 是否有全选按钮，前提是isLabelMode; 插入全选li
      if (elemLi && !intputValue && _dropdown.isLabelMode && _dropdown.config.selectAll) {
        elemLi = `<li tabindex="0" class="multselect-option multselectall">${_dropdown.config.selectAllText}</li>` +
          `<li tabindex="0" class="multselect-option multdeselectall" style="display: none;">${_dropdown.config.deselectAllText}</li>` +
          elemLi;
      }
      $el.find('ul').html(elemLi || _config.searchNoData);
    }, 300),
    control: function(event) {
      var keyCode = event.keyCode;
      var KC = KEY_CODE;
      var index = 0;
      var direct;
      var itemIndex;
      var $items;
      if (keyCode === KC.down || keyCode === KC.up) {
        // 方向
        direct = keyCode === KC.up ? -1 : 1;
        $items = this.$el.find('[tabindex]');
        itemIndex = $items.index($(document.activeElement));
        // 初始
        if (itemIndex === -1) {
          index = direct + 1 ? -1 : 0;
        } else {
          index = itemIndex;
        }
        // 确认位序
        index = index + direct;
        // 最后位循环
        if (index === $items.length) {
          index = 0;
        }
        $items.eq(index).focus();
        event.preventDefault();
      }
    },
    multiChoose: function(event, status) {
      var _dropdown = this;
      var _config = _dropdown.config;
      var $select = _dropdown.$select;
      var $target = $(event.target);
      var value = $target.attr('data-value');
      var hasSelected = $target.hasClass('multselect-chose');
      var selectedName = [];
      var selectedProp;
      // 首先判定是否是全选，全不选
      if ($target.hasClass('multselectall')) {
        // 选中所有
        action.selectAll.call(_dropdown);
        return false;
      }

      if ($target.hasClass('multdeselectall')) {
        action.deselectAll.call(_dropdown);
        $target.hide().siblings('.multselectall').show();
        return false;
      }

      if ($target.hasClass('multselect-display')) {
        return false;
      }
      if (hasSelected) {
        $target.removeClass('multselect-chose');
        _dropdown.selectAmount--;
        // 检查全选按钮
        var $seAll = _dropdown.$el.find('li.multselectall');
        if ($seAll && _dropdown.$el.find('li.multselectall').is(':hidden')) {
          $seAll.show().siblings('.multdeselectall').hide();
        }
      } else {
        if (_dropdown.selectAmount < _config.limitCount) {
          $target.addClass('multselect-chose');
          _dropdown.selectAmount++;
        } else {
          maxItemAlert.call(_dropdown);
          return false;
        }
      }

      _dropdown.name = [];
      var selectedCount = 0;
      $.each(_config.data, function(key, item) {
        if ('' + item.id === '' + value) {
          selectedProp = item;
          item.selected = hasSelected ? false : true;
        }
        if (item.selected) {
          selectedCount++;
          selectedName.push(item.name);
          if (selectedCount <= _config.maxShowCount) {
            _dropdown.name.push('<span class="multselect-selected">' + item.name + '<i class="del" data-id="' + item.id + '"></i></span>');
          }
        }
      });

      // 选中，数量超出maxShowCount条默认显示共选中X条
      if (selectedCount > _config.maxShowCount) {
        _dropdown.name.length = _config.maxShowCount;
        _dropdown.name.push(`<span class="multselect-selected">+${selectedCount - _config.maxShowCount}</span>`);
      }

      $select.find('option[value="' + value + '"]').prop('selected', hasSelected ? false : true);

      if (hasSelected && _dropdown.selectAmount < _config.minCount) {
        minItemsAlert.call(_dropdown);
      }
      if (_dropdown.name.length > 0) {
        _dropdown.$choseList.find('.placeholder').hide();
      } else {
        _dropdown.$choseList.find('.placeholder').show();
      }
      _dropdown.$choseList.find('.multselect-selected').remove();
      _dropdown.$choseList.prepend(_dropdown.name.join(''));
      _dropdown.$el.find('.multselect-display').attr('title', selectedName.join(','));
      _config.choice.call(_dropdown, event, null);
      _dropdown.checkPlaceholder();
    },
    singleChoose: function(event) {
      var _dropdown = this;
      var _config = _dropdown.config;
      var $el = _dropdown.$el;
      var $select = _dropdown.$select;
      var $target = $(event.target);
      var value = $target.attr('data-value');
      var hasSelected = $target.hasClass('multselect-chose');

      if ($target.hasClass('multselect-chose') || $target.hasClass('multselect-display')) {
        return false;
      }

      _dropdown.name = [];


      $el.removeClass('active').find('li').not($target).removeClass('multselect-chose');

      $target.toggleClass('multselect-chose');
      $.each(_config.data, function(key, item) {
        // id 有可能是数字也有可能是字符串，强制全等有弊端 2017-03-20 22:19:21
        item.selected = false;
        if ('' + item.id === '' + value) {
          item.selected = hasSelected ? 0 : 1;
          if (item.selected) {
            _dropdown.name.push('<span class="multselect-selected">' + item.name + '<i class="del" data-id="' + item.id + '"></i></span>');
          }
        }
      });

      $select.find('option[value="' + value + '"]').prop('selected', true);

      _dropdown.name.push('<span class="placeholder">' + _dropdown.placeholder + '</span>');
      _dropdown.$choseList.html(_dropdown.name.join(''));
      _config.choice.call(_dropdown, event);
      _dropdown.checkPlaceholder();
    },
    del: function(event) {
      var _dropdown = this;
      var _config = _dropdown.config;
      var $target = $(event.target);
      var id = $target.data('id');
      $.each(_dropdown.name, function(key, value) {
        if (value.indexOf('data-id="' + id + '"') !== -1) {
          _dropdown.name.splice(key, 1);
          return false;
        }
      });

      $.each(_dropdown.config.data, function(key, item) {
        if ('' + item.id == '' + id) {
          item.selected = false;
          return false;
        }
      });

      _dropdown.selectAmount--;
      _dropdown.$el.find('[data-value="' + id + '"]').removeClass('multselect-chose');
      _dropdown.$el.find('[value="' + id + '"]').prop('selected', false).removeAttr('selected');
      _dropdown.$el.find('.multselect-selected').remove();
      // 重新渲染label
      _dropdown.name = [];
      var selectedCount = 0;
      var selectedName = [];
      $.each(_config.data, function(key, item) {
        if (item.selected) {
          selectedCount++;
          selectedName.push(item.name);
          if (selectedCount <= _config.maxShowCount) {
            _dropdown.name.push('<span class="multselect-selected">' + item.name + '<i class="del" data-id="' + item.id + '"></i></span>');
          }
        }
      });

      // 选中，数量超出maxShowCount条默认显示共选中X条
      if (selectedCount > _config.maxShowCount) {
        _dropdown.name.length = 1;
        _dropdown.name.push(`<span class="multselect-selected">+${selectedCount - 1}</span>`);
      }
      _dropdown.$choseList.prepend(_dropdown.name.join(''));
      // 检查全选按钮
      var $seAll = _dropdown.$el.find('li.multselectall');
      if ($seAll && _dropdown.$el.find('li.multselectall').is(':hidden')) {
        $seAll.show().siblings('.multdeselectall').hide();
      }
      if (_dropdown.name.length > 0) {
        _dropdown.$choseList.find('.placeholder').hide();
      } else {
        _dropdown.$choseList.find('.placeholder').show();
      }
      _config.choice.call(_dropdown, event);
      _dropdown.checkPlaceholder()

      return false;
    },
    clearAll: function(event) {
      var _dropdown = this;
      var _config = _dropdown.config;
      event && event.preventDefault();
      console.log(this)
      this.$choseList.find('.del').each(function(index, el) {
        $(el).trigger('click');
      });

      if (_config.minCount > 0) {
        minItemsAlert.call(_dropdown);
      }

      this.$el.find('.multselect-display').removeAttr('title');
      return false;
    },
    selectAll: function(event) {
      var _dropdown = this;
      var _config = _dropdown.config;
      event && event.preventDefault();

      _dropdown.$choseList.find('.multselect-selected').remove();
      _dropdown.$el.find(`li[tabindex]`).removeClass('multselect-chose');
      _dropdown.name = [];
      _dropdown.selectAmount = 0;
      _config.data.forEach(one => {
        one.selected = false;
      })
      $.each(_config.data, function(key, item) {
        // 最多可选择X个
        if (_dropdown.selectAmount >= _config.limitCount) {
          // 弹出
          maxItemAlert.call(_dropdown);
          return false;
        }
        // maxShowCount个之内选中所有
        if (_dropdown.name.length <= _config.maxShowCount) {
          _dropdown.name.push('<span class="multselect-selected">' + item.name + '<i class="del" data-id="' + item.id + '"></i></span>');
        }
        _dropdown.$el.find(`li[tabindex][data-value=${item.id}]`).addClass('multselect-chose');
        _dropdown.selectAmount++;
        _dropdown.$select.find('option[value="' + item.id + '"]').prop('selected', true);
        item.selected = true;
      });
      // 全选选中，数量超出maxShowCount条默认显示共选中X条
      if (_dropdown.selectAmount > _config.maxShowCount) {
        _dropdown.name.length = _config.maxShowCount;
        _dropdown.name.push(`<span class="multselect-selected">+${_dropdown.selectAmount - _config.maxShowCount}</span>`);
      }
      _dropdown.$choseList.prepend(_dropdown.name.join(''));
      var selectResult = [];
      var selectedName = [];
      _config.data.forEach(one => {
        selectResult.push(one.id);
        selectedName.push(one.name);
      })
      if (_dropdown.name.length > 0) {
        _dropdown.$choseList.find('.placeholder').hide();
      } else {
        _dropdown.$choseList.find('.placeholder').show();
      }
      if (_dropdown.name.length === _config.data.length) {
        _dropdown.$el.find('.multselectall').hide();
        _dropdown.$el.find('.multdeselectall').show();
      }
      _dropdown.$select.val(selectResult);
      _dropdown.$el.find('.multselect-display').attr('title', selectedName.join(','));
      _config.choice.call(_dropdown, event, _config.data);
      _dropdown.checkPlaceholder()
      return false;
    },
    deselectAll: function(event) {
      var _dropdown = this;
      var _config = _dropdown.config;
      event && event.preventDefault();
      _dropdown.$el.find('li[tabindex].multselect-chose').removeClass('multselect-chose');
      _dropdown.name = [];
      _dropdown.selectAmount = 0;
      _dropdown.$choseList.find('.multselect-selected').remove();
      _dropdown.$select.val('');
      _dropdown.$el.find('.multselect-display').attr('title', '');
      $.each(_config.data, function(key, item) {
        item.selected = false;
      });
      _dropdown.$choseList.find('.placeholder').show();
      _config.choice.call(_dropdown, event, _config.data);
      _dropdown.checkPlaceholder();
      return false;
    }
  };

  function Dropdown(options, el) {
    this.$el = $(el).parent();
    this.$select = this.$el.find('select');
    this.placeholder = this.$select.attr('placeholder');
    this.config = options;
    this.name = [];
    this.isSingleSelect = !this.$select.prop('multiple');
    this.selectAmount = 0;
    this.itemLimitAlertTimer = null;
    this.isLabelMode = this.config.multipleMode === undefined; // 默认都是label
    this.init();
  }

  Dropdown.prototype = {
    init: function() {
      var _this = this;
      var _config = _this.config;
      var $el = _this.$el;
      _this.$select.hide();
      //  判断dropdown是否单选，是否token模式
      $el.addClass(_this.isSingleSelect ? 'multselect-single' : _this.isLabelMode ? 'multselect-multiple-label' : 'multselect-multiple');

      if (_config.data.length === 0) {
        _config.data = selectToObject(_this.$select);
      }

      var processResult = objectToSelect.call(_this, _config.data);

      _this.name = processResult[1];
      _this.selectAmount = processResult[2];
      _this.$select.html(processResult[0]);
      _this.renderSelect();
      // disabled权重高于readonly
      _this.changeStatus(_config.disabled ? 'disabled' : _config.readonly ? 'readonly' : false);

      _this.config.init();
      _config.limitCount = Math.min(_config.limitCount, _config.data.length);
      _config.maxShowCount = Math.min(_config.maxShowCount, _config.data.length);
      // 重新写multselect-main的宽度
      $el.find('.multselect-main').css({ 'width': `${$el.width()}px` })
    },
    // 渲染 select 为 dropdown
    renderSelect: function(isUpdate, isCover) {
      var _this = this;
      var $el = _this.$el;
      var $select = _this.$select;
      var elemLi = selectToDiv($select.prop('outerHTML'));
      var template;
      // 是否有全选按钮，前提是isLabelMode; 插入全选li
      if (_this.isLabelMode && _this.config.selectAll) {
        elemLi = `<li tabindex="0" class="multselect-option multselectall">${_this.config.selectAllText}</li>` +
          `<li tabindex="0" class="multselect-option multdeselectall" style="display: none;">${_this.config.deselectAllText}</li>` +
          elemLi;
      }

      if (isUpdate) {
        $el.find('ul')[isCover ? 'html' : 'append'](elemLi);
      } else {
        template = createTemplate.call(_this).replace('{{ul}}', '<ul>' + elemLi + '</ul>');
        $el.append(template).find('ul').removeAttr('style class');
      }

      if (isCover) {
        _this.name = [];
        _this.$el.find('.multselect-selected').remove();
        _this.$select.val('');
      }

      _this.$choseList = $el.find('.multselect-chose-list');
      if (_this.placeholder && _this.name.length === 0) {
        _this.$choseList.html($('<span class="placeholder"></span>').text(_this.placeholder));
      } else {
        _this.$choseList.find('.placeholder').hide();
      }

      _this.$choseList.prepend(_this.name ? _this.name.join('') : []);
    },
    bindEvent: function() {
      var _this = this;
      var $el = _this.$el;
      var openHandle = isSafari ? EVENT_SPACE.click : EVENT_SPACE.focus;

      $el.on(EVENT_SPACE.click, function(event) {
        event.stopPropagation();
      });

      $el.on(EVENT_SPACE.click, '.del', $.proxy(action.del, _this));

      // show
      if (_this.isLabelMode) {
        $el.on(EVENT_SPACE.click, '.multselect-display-label', function() {
          $el.find('input').focus();
        });
        if (_this.config.searchable) {
          $el.on(EVENT_SPACE.focus, 'input', $.proxy(action.show, _this));
        } else {
          $el.on(EVENT_SPACE.click, $.proxy(action.show, _this));
        }
        $el.on(EVENT_SPACE.keydown, 'input', function(event) {
          if (event.keyCode === 8 && this.value === '' && _this.name.length) {
            $el.find('.del').eq(-1).trigger('click');
          }
        });
      } else {
        $el.on(openHandle, '.multselect-display', $.proxy(action.show, _this));
        $el.on(openHandle, '.multselect-clear-all', $.proxy(action.clearAll, _this));
      }

      // 搜索
      $el.on(EVENT_SPACE.keyup, 'input', $.proxy(action.search, _this));

      // 按下enter键设置token
      $el.on(EVENT_SPACE.keyup, function(event) {
        var keyCode = event.keyCode;
        var KC = KEY_CODE;
        if (keyCode === KC.enter) {
          $.proxy(_this.isSingleSelect ? action.singleChoose : action.multiChoose, _this, event)();
        }
      });

      // 按下上下键切换token
      $el.on(EVENT_SPACE.keydown, $.proxy(action.control, _this));

      $el.on(EVENT_SPACE.click, 'li[tabindex]', $.proxy(_this.isSingleSelect ? action.singleChoose : action.multiChoose, _this));
    },
    unbindEvent: function() {
      var _this = this;
      var $el = _this.$el;
      var openHandle = isSafari ? EVENT_SPACE.click : EVENT_SPACE.focus;

      $el.off(EVENT_SPACE.click);
      $el.off(EVENT_SPACE.click, '.del');

      // show
      if (_this.isLabelMode) {
        $el.off(EVENT_SPACE.click, '.multselect-display-label');
        $el.off(EVENT_SPACE.focus, 'input');
        $el.off(EVENT_SPACE.keydown, 'input');
      } else {
        $el.off(openHandle, '.multselect-display');
        $el.off(openHandle, '.multselect-clear-all');
      }
      // 搜索
      $el.off(EVENT_SPACE.keyup, 'input');
      // 按下enter键设置token
      $el.off(EVENT_SPACE.keyup);
      // 按下上下键切换token
      $el.off(EVENT_SPACE.keydown);
      $el.off(EVENT_SPACE.click, '[tabindex]');
    },
    changeStatus: function(status) {
      var _this = this;
      if (status === 'readonly') {
        _this.unbindEvent();
      } else if (status === 'disabled') {
        _this.$select.prop('disabled', true);
        _this.unbindEvent();
      } else {
        _this.$select.prop('disabled', false);
        _this.bindEvent();
      }
    },
    update: function(data, isCover) {
      var _this = this;
      var _config = _this.config;
      var $el = _this.$el;
      var _isCover = isCover || false;

      if (Object.prototype.toString.call(data) !== '[object Array]') {
        return;
      }

      _config.data = _isCover ? data.slice(0) : _config.data.concat(data);

      var processResult = objectToSelect.call(_this, _config.data);

      _this.name = processResult[1];
      _this.selectAmount = processResult[2];
      _this.$select.html(processResult[0]);
      _this.renderSelect(true, _isCover);
    },
    destroy: function() {
      this.unbindEvent();
      this.$el.children().not('select').remove();
      this.$el.removeClass('multselect-single multselect-multiple-label multselect-multiple');
      this.$select.show();
    },
    choose: function(values, status) {
      var valArr = Object.prototype.toString.call(values) === '[object Array]' ? values : [values];
      var _this = this;
      var _status = status !== void 0 ? !!status : true
      $.each(valArr, function(index, value) {
        var $target = _this.$el.find('[data-value="' + value + '"]');
        var targetStatus = $target.hasClass('multselect-chose');
        if (targetStatus !== _status) {
          $target.trigger(EVENT_SPACE.click, status || true)
        }
      });
    },
    reset: function() {
      action.clearAll.call(this)
    },
    checkPlaceholder() {
      // 校验是否要显示placeholder 'Search...'
      let $selected = this.$el.find('span.multselect-selected');
      if ($selected.length > 0) {
        this.$el.find('input').attr('placeholder', '')
      } else {
        this.$el.find('input').attr('placeholder', 'Search...')
      }
    },
  };

  $(document).on('click.dropdown', function() {
    // 下拉框收起，清除search input内容
    $('.multselect-single,.multselect-multiple,.multselect-multiple-label').each((index,one) => {
      $(one).removeClass('active').find('.multselect-search input').val('').trigger(EVENT_SPACE.keyup);
    })
  });

  $.fn.multselect = function(options) {
    this.each(function(index, el) {
      $(el).data('dropdown', new Dropdown($.extend(true, {}, settings, options), el));
    });
    return this;
  }
})(jQuery);