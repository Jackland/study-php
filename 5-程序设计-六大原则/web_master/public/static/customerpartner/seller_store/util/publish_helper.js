/**
 * @file 门店介绍页面、店铺装修页发布流程
 * 
 * @description 用于用户发布操作时需要的验证弹窗等操作（需前置引用axios）
 */

class PublishHelper {
  static PUBLISH_TYPE = {
    INTRODUCTION: 'introduction',
    HOME: 'home'
  }

  static API = {
    INTRODUCTION_SAVE: "/index.php?route=customerpartner/seller_store/introduction/store",
    HOME_SAVE: "/index.php?route=customerpartner/seller_store/home/allSave",
    HOME_CANCEL: "/index.php?route=customerpartner/seller_store/home/auditCancel", // 取消审核接口
  }

  static axios = axios.create({});

  constructor() {
    this._instance = null;
  }

  /**
   * 帮助类单例
   * @param {String} type 发布类型，店铺介绍或店铺装修
   */
  static instance(type) {
    //检查是否在类型中
    if (!Object.values(PublishHelper.PUBLISH_TYPE).includes(type)) {
      throw new Error('type error!');
    }

    if (!this._instance) {
      this._instance = new PublishHelper();
    }
    this._instance.type = type;
    return this._instance;
  }


  /**
   * 发布操作
   * @param {Object} data 请求参数
   * @param {Function} success 成功回调
   * @param {Function} error 失败回调
   * @param {Function} after_remove 删除模块回调
   */
  async publish(data, success = (() => { }), error = (() => { }), after_remove = (() => { })) {
    if (!this._homePublishHandler(data, success, error, after_remove)) {
      return
    }
    this._publish(data, success, error, after_remove);
  }

  async _publish(data, success, error, after_remove) {
    var loading = layer.load(1, {
      shade: [0.5,'#fff']
    });
    let checkRes = await this._publisherFactory()(data);

    if (checkRes.data.code == 200) {
      layer.close(loading);
      this._publishCheckHandler(checkRes.data.data, data, success, error, after_remove)
    } else {
      layer.confirm(checkRes.data.msg, {title: PUBLISH_TRANSLATIONS.CONFIRM_TITLE, skin: 'oris-layer', btn: [PUBLISH_TRANSLATIONS.YES, PUBLISH_TRANSLATIONS.NO]})
      layer.close(loading);
    }
  }

  /**
   * 
   * 格式化模块名
   * @param {Array} emptyModules 
   */
  _formatEmptyModuleTitle(counter, module, emptyModules) {
    if (counter[module.type]) {
      counter[module.type] += 1;
      for (let i = 0 ; i < emptyModules.length; i++) {
        if(emptyModules[i] == MODULE_LIST[module.type].title) {
          emptyModules[i] = MODULE_LIST[module.type].title + ' 1';
        }
      }
    } else {
      counter[module.type] = 1;
    }
    console.log(counter)
    emptyModules.push(counter[module.type] != 1 ? MODULE_LIST[module.type].title + ' ' + counter[module.type] : MODULE_LIST[module.type].title);
    return emptyModules;
  }

  /**
   * 店铺装修页面发布
   */
  _homePublishHandler(data, success, error, after_remove) {
    // 验证是否存在空白模块
    if (this.type == PublishHelper.PUBLISH_TYPE.HOME && data.type == "publish") {
      if (data.hasOwnProperty('modules')) {
        let emptyModules = [];
        let error_modules = [];

        let counter = {};
        for (let [index,module] of data.modules.entries()) {
          if (module && Object.keys(module.data).length === 0) {
            // type 转换为对应的title
            emptyModules = this._formatEmptyModuleTitle(counter, module, emptyModules);
            error_modules.push(index)
          } else {
            if(counter[module.type]) {
              counter[module.type] += 1;
            } else {
              counter[module.type] = 1;
            }
          }
        }
        let that = this;
        if (emptyModules.length > 0) {
          let msg = emptyModules.join('、') + PUBLISH_TRANSLATIONS.EMPTY_MODULES;
          layer.confirm(msg, {title: PUBLISH_TRANSLATIONS.CONFIRM_TITLE, skin: 'oris-layer', btn: [PUBLISH_TRANSLATIONS.YES, PUBLISH_TRANSLATIONS.NO]}, () => {
            data.modules = data.modules.filter(module => {
              return Object.keys(module.data).length > 0;
            })
            layer.closeAll();
            that._publish(data, success, error);
            after_remove(error_modules);
          })
          return false;
        }
      }
    }
    return true;
  }

  async _publishAfterCheck(data, success, error) {
    var loading = layer.load(1, {
      shade: [0.5,'#fff']
    });
    let res = await this._publisherFactory()(data);
    if (res.data.code == 200) {
      layer.close(loading);
      $.toast({
        heading: false,
        text: PUBLISH_TRANSLATIONS.PUBLISH_SUCCESS,
        position: 'top-center',
        showHideTransition: 'fade',
        icon: 'success',
        hideAfter: 5000,
        allowToastClose: false,
        loader: false
      });
      
      success();
    } else {
      layer.close(loading);
      error(res);
    }
  }

  /**
   * 处理发布返回
   * @param {Object} checkRes 
   */
  _publishCheckHandler(checkRes, data, success, error, after_remove) {
    let { has_wait_audit, is_preview_change, need_confirm, preview_key, error_modules} = checkRes;
    let msg = '';

    // 模块错误提示
    if(data.type == 'publish' && error_modules && error_modules.length) {
      let errorModules = [];
      for (let errorIndex of error_modules) {
        // TODO type 改为对应title
        errorModules.push(data.modules[errorIndex].type);
      }
      let msg = errorModules.join('、') + PUBLISH_TRANSLATIONS.EMPTY_MODULES;
      layer.confirm(msg, {title: PUBLISH_TRANSLATIONS.CONFIRM_TITLE, skin: 'oris-layer', btn: [PUBLISH_TRANSLATIONS.YES, PUBLISH_TRANSLATIONS.NO]}, () => {
        after_remove(error_modules);
        layer.closeAll();
        this._publishAfterCheck(data, success, error);
      })
      return;
    }

    // 带有preview key 不进行二次请求操作 （save操作）
    if (preview_key != "") {
      success(preview_key);
      return;
    }

    // 预览数据有修改
    if (is_preview_change) {
      msg = PUBLISH_TRANSLATIONS.PREVIEW_CHANGED;
      layer.confirm(msg, {title: PUBLISH_TRANSLATIONS.CONFIRM_TITLE, skin: 'oris-layer', btn: [PUBLISH_TRANSLATIONS.YES, PUBLISH_TRANSLATIONS.NO]}, () => {
        window.location.reload();
        layer.closeAll();
      })
      return
    }
    // 确认发布
    if (need_confirm) {
      msg = PUBLISH_TRANSLATIONS.CONFIRM;
      data['confirm_publish'] = 1;
    }
    // 存在待审核
    if (has_wait_audit) {
      msg = PUBLISH_TRANSLATIONS.HAS_WAIT_AUDIT;
      data['overwrite_audit'] = 1;
    }
    //TODO is_preview_change 预览数据有修改操作
    layer.confirm(msg, {title: PUBLISH_TRANSLATIONS.CONFIRM_TITLE, skin: 'oris-layer', btn: [PUBLISH_TRANSLATIONS.YES, PUBLISH_TRANSLATIONS.NO]}, () => {
      layer.closeAll();
      this._publishAfterCheck(data, success, error);
    })
  }

  /**
   * 确认框操作
   * @param {Number} index layer确认框
   */
  _publishCheckSuccess(index) {
    layer.close(index);
  }

  _publishCheckError(index) {
    layer.close(index);
  }

  /**
   * 发布工厂方法，返回对应发布方法
   */
  _publisherFactory() {
    if (this.type == PublishHelper.PUBLISH_TYPE.INTRODUCTION) {
      return this._introPublisher
    } else if (this.type == PublishHelper.PUBLISH_TYPE.HOME) {
      return this._homePublisher
    } else {
      throw new Error();
    }
  }

  /**
   * 店铺介绍发布方法
   * @param {Object} data 
   */
  _introPublisher(data) {
    return PublishHelper.axios.post(PublishHelper.API.INTRODUCTION_SAVE, data)
  }

  /**
   * 门店装修页发布方法
   * @param {Object} data 
   */
  _homePublisher(data) {
    return PublishHelper.axios.post(PublishHelper.API.HOME_SAVE, data)
  }


  /**
   * 取消审核
   */
  cancelAudit(data, success, error) {
    if (this.type != PublishHelper.PUBLISH_TYPE.HOME) {
      throw Error('only support home cancel audit!');
    }
    this._cancel(data, success, error);
  }

  async _cancel(data, success, error, dontSaveDraft=false) {
    var loading = layer.load(1, {
      shade: [0.5,'#fff']
    });
    let checkRes = await this._homeCanceller(data);

    if (checkRes.data.code == 200) {
      layer.close(loading);
      this._cancelCheckHandler(checkRes.data.data, data, success, error, dontSaveDraft)
    } else {
      layer.confirm(checkRes.data.msg, {title: PUBLISH_TRANSLATIONS.CONFIRM_TITLE, skin: 'oris-layer', btn: [PUBLISH_TRANSLATIONS.YES, PUBLISH_TRANSLATIONS.NO]})
      layer.close(loading);
    }
  }

  /**
   * 店铺装修页面取消操作
   * @param {Object} data 
   */
  _homeCanceller(data) {
    return PublishHelper.axios.post(PublishHelper.API.HOME_CANCEL, data)
  }

  /**
   * 取消审核返回内容操作
   * @param {Object} checkRes 
   * @param {Object} data 
   * @param {Function} success 
   * @param {Function} error 
   */
  _cancelCheckHandler(checkRes, data, success_callback, error_callback, dontSaveDraft=false) {
    let { need_confirm, draft_exist, draft_empty, success } = checkRes;
    let that = this;
    if (need_confirm) {
      layer.confirm(PUBLISH_TRANSLATIONS.CANCEL, {title: PUBLISH_TRANSLATIONS.CONFIRM_TITLE, skin: 'oris-layer', btn: [PUBLISH_TRANSLATIONS.YES, PUBLISH_TRANSLATIONS.NO]}, () => {
        layer.closeAll();
        data['confirm_cancel'] = 1;
        that._cancel(data, success_callback, error_callback);
      })
      return;
    }
    if (draft_exist || draft_empty) {
      layer.confirm(draft_exist ? PUBLISH_TRANSLATIONS.HAS_DRAFT : PUBLISH_TRANSLATIONS.DRAFT_EMPTY, {title: PUBLISH_TRANSLATIONS.CONFIRM_TITLE, skin: 'oris-layer', btn: [PUBLISH_TRANSLATIONS.YES, PUBLISH_TRANSLATIONS.NO]}, () => {
        layer.closeAll();
        data['save_draft'] = 1;
        dontSaveDraft = false;
        that._cancel(data, success_callback, error_callback);
      }, () => {
        layer.closeAll();
        data['save_draft'] = 0;
        dontSaveDraft = true; //不保存草稿
        that._cancel(data, success_callback, error_callback, dontSaveDraft);
      })
      return;
    }
    if (success) {
      $.toast({
        heading: false,
        text: PUBLISH_TRANSLATIONS.CANCEL_SUCCESS,
        position: 'top-center',
        showHideTransition: 'fade',
        icon: 'success',
        hideAfter: 5000,
        allowToastClose: false,
        loader: false
      });
      success_callback(dontSaveDraft);
      return;
    } else {
      error_callback();
    }
  }

  /**
   * 审核驳回弹窗提示
   * @param {Object} data 
   * @param {Function} success 
   */
  auditRejectedDialog(data, success=(() => {})) {
    if(data.audit_info) {
      if (data.audit_info.status == 40) {
        let msg = `
<div class="reject-msg-container">
  <i class="error-icon giga icon-Group-1"></i>
  <div class="msg-container">
    <div class="msg-title text">${PUBLISH_TRANSLATIONS.REJECT}</div>
    <div class="msg-content text">
      <div class="text"><strong>${PUBLISH_TRANSLATIONS.REJECT_REASON}:</strong></div>
      ${data.audit_info.refuse_reason}
    </div>
  </div>
</div>`
        layer.confirm(msg ,{title: PUBLISH_TRANSLATIONS.CONFIRM_TITLE, skin: 'oris-layer',btn:['OK']}, (index) => {
          success();
          layer.closeAll();
        })
      }
    }
  }
}

$(function() {
  $('body').on("mouseenter", ".save-draft-dialog .layui-layer-btn1" , function() {
    layer.tips(PUBLISH_TRANSLATIONS.CANCEL_DRAFT, '.save-draft-dialog .layui-layer-btn1', {});
  });
})