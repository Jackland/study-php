// 统一整理前端金额计算方法
// Start: #1452 RMA金额计算
// Date: 2021-01-18
var mathematical = {
  // 用于计算两数相减 arg1-arg2，保留小数点首先取myFixedNum，其次去两数的最大小数
  accSub(arg1, arg2, myFixedNum) {
    var r1, r2, m, n;
    try {
      r1 = arg1.toString().split(".")[1].length;
    } catch (e) {
      r1 = 0;
    }
    try {
      r2 = arg2.toString().split(".")[1].length;
    } catch (e) {
      r2 = 0;
    }
    m = Math.pow(10, Math.max(r1, r2)); //last modify by deeka //动态控制精度长度
    n = (r1 >= r2) ? r1 : r2;
    return ((arg1 * m - arg2 * m) / m).toFixed(myFixedNum ? myFixedNum : n);
  },
  // 用于计算两数相加 arg1+arg2，保留小数点首先取myFixedNum，其次去两数的最大小数
  accAdd(arg1, arg2, myFixedNum) {
    var r1, r2, m, n;
    try {
      r1 = arg1.toString().split(".")[1].length;
    } catch (e) {
      r1 = 0;
    }
    try {
      r2 = arg2.toString().split(".")[1].length;
    } catch (e) {
      r2 = 0;
    }
    m = Math.pow(10, Math.max(r1, r2)); //last modify by deeka //动态控制精度长度
    n = (r1 >= r2) ? r1 : r2;
    return ((arg1 * m + arg2 * m) / m).toFixed(myFixedNum ? myFixedNum : n);
  },
  // 用于计算两数相乘
  accMul(arg1, arg2) {
    var m = 0,
      s1 = arg1.toString(),
      s2 = arg2.toString();
    try {
      m += s1.split(".")[1].length;
    } catch (e) {
      m += 0;
    }
    try {
      m += s2.split(".")[1].length;
    } catch (e) {
      m += 0;
    }
    return Number(s1.replace(".", "")) * Number(s2.replace(".", "")) / Math.pow(10, m);
  },
  // 保留decimal位小数（不四舍五入）
  formatDecimal(num, decimal) {
    if (!num) {
      num = 0;
    }
    decimal = parseFloat(decimal) || 0;
    num = num.toString();
    let index = num.indexOf('.');
    if (index !== -1) {
      num = num.substring(0, decimal + index + 1);
    } else {
      num = num.substring(0);
    }
    return parseFloat(num).toFixed(decimal);
  },
  // 四舍五入
  MathToFixed(num, decimal) {
    var f = parseFloat(num);
    decimal = parseFloat(decimal) || 0;
    if (isNaN(f)) {
      return '0';
    }
    var powMe = Math.pow(10, decimal);
    f = Math.round(mathematical.accMul(num, powMe)) / powMe; // n 幂
    var s = f.toString();
    var rs = s.indexOf('.');
    //判定如果是整数，增加小数点再补0
    if (rs < 0 && decimal > 0) {
      rs = s.length;
      s += '.';
    }
    while (s.length <= rs + decimal) {
      s += '0';
    }
    return s;
  },
  // 保留decimal位小数，向上取小数位
  // 一般用于价格取值
  MathToPrice(num, decimal) {
    var f = parseFloat(num);
    decimal = parseFloat(decimal) || 0;
    if (isNaN(f)) {
      return '0';
    }
    var powMe = Math.pow(10, decimal);
    f = Math.round(Math.ceil(mathematical.accMul(num, powMe))) / powMe; // n 幂
    return f;
  },
  //金额格式（千分位，保留小数）
  number_format(number, decimals, dec_point = '.', thousands_sep = ','){
    number = (number + '').replace(/[^0-9+-Ee.]/g, '');
    var n = !isFinite(+number) ? 0 : +number,
      prec = !isFinite(+decimals) ? 2 : Math.abs(decimals),
      sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
      dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
      s = '',
      toFixedFix = function(n, prec) {
        var k = Math.pow(10, prec);
        return '' + Math.round(n * k) / k;
      };

    s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
    var re = /(-?\d+)(\d{3})/;
    while(re.test(s[0])) {
      s[0] = s[0].replace(re, "$1" + sep + "$2");
    }

    if((s[1] || '').length < prec) {
      s[1] = s[1] || '';
      s[1] += new Array(prec - s[1].length + 1).join('0');
    }
    return s.join(dec);
  },

  //格式化金额格式
  formatPrice(currency, price) {
    let currencyConfig = {
      'GBP': {
        'currency_id': 1,
        'symbol_left': '£',
        'symbol_right': '',
        'decimal_place': '2',
      },
      'USD': {
        'currency_id': 2,
        'symbol_left': '$',
        'symbol_right': '',
        'decimal_place': '2',
      },
      'EUR': {
        'currency_id': 3,
        'symbol_left': '',
        'symbol_right': '€',
        'decimal_place': '2',
      },
      'JPY': {
        'currency_id': 4,
        'symbol_left': '￥',
        'symbol_right': '',
        'decimal_place': '0',
      },
      'UUU': {
        'currency_id': 5,
        'symbol_left': '',
        'symbol_right': '',
        'decimal_place': '2',
      },
    };

    return currencyConfig[currency].symbol_left +
      price.toFixed(currencyConfig[currency].decimal_place) +
      currencyConfig[currency].symbol_right;
  }
}
