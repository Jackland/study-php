/**
 * @file 组件类：产品选择弹框
 * 
 * @author 前端
 * @description 用于选择产品的弹框展示
 */
class productSelectModal {
  /**
   * 构造函数
   * @param {Dom} el 需要渲染的弹框 Dom Id
   * @param {Dom} triggerEl 触发弹框显示的Dom Id
   * @param {String} 搜索产品的url，可自定义
   * @param {Objecat} 搜索产品的post data参数条件
   * @param {Number} 共可选择的产品总数
   * @param {String} 超出最大个数提示
   */
  constructor(el, triggerEl, url, params, submitCb, maxCount, maxCountTips) {
    this.$el = $(el);
    this.triggerEl = triggerEl;
    this.url = url || 'customerpartner/marketing_campaign/time_limit_discount/products';
    this.params = params || {};
    this.fun = null;                          //用于防抖的timeout
    this.searchVal = '';                      // 搜索输入值记录
    this.prodsList = [];                      // 产品列表数据
    this.submitCb = submitCb || function() {};// 提交回调函数
    this.maxCount = Number(maxCount || 999);
    this.maxCountTips = maxCountTips || '';
    this.init();
  }

  // 初始化
  init() {
    $(() => {
      this.bindEvent();
    })
  };
  // dom事件绑定
  bindEvent() {
    let that = this;
    // 产品选中弹框show
    $('body').on('click', this.triggerEl, function() {
      // 重新弹框后清空之前的所有数据
      that.refreshData(that);
      that.$el.modal('show');
    });
    // 搜索输入
    that.$el.on('keyup', '.search-input', function() {
      that.searchVal = $(this).val().trim();
      that.search(that);
    });
    // 点击触发checKbox选中状态
    that.$el.on('click', '.product-item-container', function(event) {
      if (!$(event.target).is('input')) {
        let val = $(this).find('input[type="checkbox"]').prop('checked');
        $(this).find('input[type="checkbox"]').prop('checked', !val);
      }
    });
    // 提交按钮
    that.$el.on('click', '.action-submit', function() {
      // 校验选中数量
      let choosedIds = that.$el.data('choosedIds')?that.$el.data('choosedIds').split(','):[]; // 已经选中的产品需要过滤
      let prods = that.getCheckedProds(that); // 选中的产品
      if (that.maxCount && (choosedIds.length + prods.length > that.maxCount) ) {
        // 数量超出
        return layer.msg(that.maxCountTips)
      }
      // 回调函数
      if(that.submitCb) {
        that.submitCb(prods);
        that.$el.modal('hide');
      }
    })
  };
  // 返回选中的产品列表
  getCheckedProds(that) {
    let prods = [];
    that.$el.find('input[type="checkbox"]:checked').each((ind,item) => {
      let index = $(item).attr('data-index');
      that.prodsList[index] && prods.push(that.prodsList[index]);
    });
    return prods;
  };
  // 初始化弹框数据
  refreshData(that) {
    that.searchVal = '';
    that.prodsList = [];
    that.$el.find('.search-input').val(that.searchVal);
    that.renderProdsHtml(that);
  };
  searchProduct(that) {
    let data = JSON.parse(JSON.stringify(that.params));
    data['keywords'] = that.searchVal;
    let loading = layer.load(1, {
      shade: [0.5, '#fff']
    });
    $.ajax({
      url: `index.php?route=${that.url}`,
      type: 'post',
      dataType: 'json',
      data: data,
      success: function(res) {
        if (res['code'] === 200) {
          that.prodsList = res['data']['products'];
        } else {
          that.prodsList = [];
        }
        layer.close(loading);
        that.renderProdsHtml(that);
      },
      error: function() {
        layer.close(loading);
        that.renderProdsHtml(that);
      },
      complete: function() {
        layer.close(loading);
      }
    })
  };
  //防抖
  debounce(fn, wait, that) {
    if (that.fun !== null) {
      clearTimeout(that.fun);
    }
    that.fun = setTimeout(fn, wait);
  };
  //查询操作
  search(that) {
    if (that.searchVal.length > 1) {
      that.debounce(that.searchProduct(that), 500, that);
    }
  };
  // 渲染产品列表
  renderProdsHtml(that) {
    if (that.prodsList.length > 0) {
      let prodHtml = '';
      let choosedIds = this.$el.data('choosedIds'); // 已经选中的产品需要过滤
      that.prodsList.forEach((one,index) => {
        if (choosedIds.indexOf(one['id']+'') > -1){
          return;
        }
        prodHtml +=`<div class="product-item-container">
                      <div class="prodduct-content">
                        <input type="checkbox" class="oris-checkbox-mini" data-index="${index}"/>
                        <div class="product-img-container">
                          <img class="product-img" src="${one.image}">
                        </div>
                        <div class="product-info">
                          <div class="product-info-title" title="${one.name}">${one.name}</div>
                          <div class="product-info-code">${one.sku} / ${one.mpn}<span>${one.tags}</span></div>
                          <div class="product-info-footer">
                            <span class="product-info-footer-price">${mathematical.formatPrice(one.currency, +one.price)}</span>
                            <span class="product-info-footer-qty">Qty Available: ${one.qty}</span>
                          </div>
                        </div>
                      </div>
                    </div>`
      })
      that.$el.find('.action-products').html(prodHtml);
      if (prodHtml) {
        that.$el.find('.no-records').hide();
      } else {
        that.$el.find('.no-records').show();
      }
    } else {
      that.$el.find('.action-products').html('');
      that.$el.find('.no-records').show();
    }
  }
}