// 统一整理前端字符串相关方法
// Date: 2021-02-01
var toolString = {
  // 校验字符长度，中文和日本算两个字符，开始不允许输入空格，并且前后空格不加入计算
  // 入参：str: 输入字符； maxLength: 最大字符长度
  // 出参：len: 实际符合的字符长度；fullStr: 实际符合的字符内容
  calcStringLength(str, maxLength) {
    str = str.replace(/^\s*/g, ''); // 控制开始不允许输入空格
    var len = 0;
    var charCode = -1;
    var fullStr = '';
    var lrNoEmpty = str.trim(); // 前后去空格
    if (!str || !str.length) {
      return { 'len': len, 'fullStr': fullStr };
    }
    for (var i = 0; i < str.length; i++) {
      charCode = lrNoEmpty.charCodeAt(i);
      if (charCode >= 2048 && charCode <= 40869) {
        len += 2;
      } else if (!isNaN(charCode)) {
        len++;
      }
      if (len <= maxLength) {
        fullStr += str[i];
      } else {
        len = maxLength
      }
    }
    return { 'len': len, 'fullStr': fullStr };
  },
  //获取实际输入的长度
  calcStrTrueLen(str) {
    str = str.replace(/^\s*/g, ''); // 控制开始不允许输入空格
    var len = 0;
    var charCode = -1;
    var lrNoEmpty = str.trim(); // 前后去空格
    if (!str || !str.length) {
      return len;
    }
    for (var i = 0; i < str.length; i++) {
      charCode = lrNoEmpty.charCodeAt(i);
      if (charCode >= 2048 && charCode <= 40869) {
        len += 2;
      } else if (!isNaN(charCode)) {
        len++;
      }
    }
    return len;
  },
}