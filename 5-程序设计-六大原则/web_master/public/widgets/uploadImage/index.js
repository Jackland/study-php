/**
 * @file 上传图片类
 */
class UploadImageClass {
  options = {
    id: '', // 传入 id 时自动 init
    width: '120px',
    height: '120px',
    name: 'files[]', // 上传的 input 的 name
    maxCount: 1, // 最大总数量
    singleAdd: false, // 是否显示单个添加按钮
    accept: 'image/*', // 可接受文件类型,input type='file' 的 accept 值, 'image/jpg,image/png,image/jpeg'
    imageList: [], // 初始化图片, [{url:"https://xxx", path:"uploadMisc/xxx"}]
    endCB: null, // 上传成功回调
    previewCB: null, // 预览图片回调
    previewZIndex: 3000, // 预览的模态框的层级
    uploadUrl: 'index.php?route=common/upload/image', // 上传的 url
    downloadUrl: 'index.php?route=common/upload/download&path={path}', // 下载的地址
    singleMaxSize: 20, // 单文件最大大小，单位M
  }

  /**
   * 构造函数
   * @param options
   */
  constructor(options = {}) {
    this.options = Object.assign(this.options, options)
    if (this.options.id) {
      this.setId(this.options.id)
    }
  };

  setId(id) {
    this.options.id = id
    this.$el = $('#' + this.options.id)
    this.init()
  }

  /**
   * 初始化上传组件
   */
  init() {
    this.initLayout()
    this.initInputFile()
    this.initImageItems();
    this.bindDomEvent();
  };

  initLayout() {
    const html = `<div class="oris-upload-image">
  <div class="action-items-content">
  </div>
  <input type="file" name="__no_used_upload_name">
</div>`
    this.$el.html(html)
  }

  initInputFile() {
    if (this.options.accept) {
      this.$el.find('input[type=file]').attr('accept', this.options.accept)
    }
    if (this.options.maxCount > 1) {
      this.$el.find('input[type=file]').attr('multiple', true)
    }
  }

  // 初始化上传模块Dom
  initImageItems() {
    // 初始化上传的组件
    let itemHtml = '';
    for (let i = 0; i < this.options.maxCount; i++) {
      const styles = [
        `width: ${this.options.width}`,
        `height: ${this.options.height}`,
      ]
      if (this.options.singleAdd && i > 0) {
        styles.push('display:none')
      }
      itemHtml += `<span class="image-items" data-index="${i}" style="${styles.join(';')}">
                    <i class="giga icon-add"></i>
                  </span>`;
    }
    this.$el.find('.action-items-content').html(itemHtml);
    // 填充原数据
    this.fillImage()
    // 初始化预览容器
    if (!this.options.previewCB && $('#orisPreviewImg').length === 0) {
      $('body').append('<div id="orisPreviewImg"></div>');
    }
  };

  bindDomEvent() {
    var that = this;
    this.$el.unbind()
    // 触发选择文件
    this.$el.find('.image-items').on('click', function (e) {
      e.preventDefault();
      if ($(this).find('.icon-add').length > 0) {
        that.$el.find('input').trigger('click');
      }
    });
    // 预览/下载/删除
    this.$el
      .on('click', '.action-preview', function () {
        that.imagePreview($(this).closest('.image-items'));
      })
      .on('click', '.action-download', function () {
        that.imageDownload($(this).closest('.image-items'));
      })
      .on('click', '.action-delete', function () {
        that.imageDelete($(this).closest('.image-items'));
      })
    // 文件选中变化
    this.$el.find('input').change(function () {
      let fileArr = $(this)[0].files;
      let errorTips = '';
      const beuploadImages = [];
      for (var i = 0; i < fileArr.length; i++) {
        // 过滤文件类型 并且只能最多上传count个
        if (that.options.imageList.length + beuploadImages.length >= that.options.maxCount) {
          errorTips += `<div>Upload limited to ${that.options.maxCount} files.</div>`;
          break;
        }
        if (that.checkFilesSize(fileArr[i], that.options.singleMaxSize)) {
          errorTips += `<div>${fileArr[i]['name']} uploaded does not meet the requirement for file size (limited to ${that.options.singleMaxSize}M).</div>`;
          continue;
        }
        // 加入待上传
        beuploadImages.push(fileArr[i]);
      }
      if (errorTips !== '') {
        that.errorToast(errorTips);
      }
      that.uploadingImgService(beuploadImages);
      $(this).val('');
    })
  };

  setImageList(imageList, replace = false) {
    if (!Array.isArray(imageList)) {
      imageList = [imageList]
    }
    imageList = imageList.filter(item => item.url)
    if (imageList.length <= 0) {
      return
    }
    if (replace) {
      this.options.imageList = imageList
    } else {
      this.options.imageList = this.options.imageList.concat(imageList)
    }
    this.fillImage()
  }

  getImageList() {
    return this.options.imageList
  }

  fillImage() {
    this.options.imageList.forEach((item, index) => {
      this.successUpload(item, index)
    })
  }

  openLoading(count) {
    for (let i = 0; i < count; i++) {
      let item = this.$el.find('i.icon-add')[0];
      $(item).parents('.image-items').show()
      $(item).removeClass('icon-add').addClass('icon-oris-loading');
    }
  }

  successUpload(item, index = null) {
    if (index === null) {
      index = this.options.imageList.length
      this.options.imageList.push(item)
    }
    const el = this.$el.find('.image-items').eq(index)
    const previewImgHtml = `<span class="img-content">
                            <img src="${item.url}" alt="image" data-path="${item.path}" style="max-height: ${this.options.height};max-width: ${this.options.width};"/>
                            <span class="img-hover">
                              <span class="action-preview"><i class="giga icon-zoomin"></i></span>
                              <span class="action-download"><i class="giga icon-xiazaixin-01"></i></span>
                              <span class="action-delete"><i class="giga icon-lajitong1"></i></span>
                            </span>
                          </span>`;
    el.addClass('image-items-uploaded')
      .attr('data-index', index)
      .html(previewImgHtml)
    if (el.next().length > 0) {
      el.next().show()
    }
  }

  uploadingImgService(list) {
    if (list.length === 0) {
      return;
    }
    const formData = new FormData();
    list.forEach(item => {
      formData.append(this.options.name, item);
    })

    this.openLoading(list.length);
    const that = this
    $.ajax({
      url: this.options.uploadUrl,
      type: 'post',
      dataType: 'json',
      data: formData,
      cache: false,
      processData: false,
      contentType: false,
      success: function (res) {
        if (res.code !== 200) {
          that.errorToast(res.msg)
          return
        }
        res.data.forEach(item => {
          that.successUpload(item)
        });
        // 回调处理
        if (that.options.endCB) {
          that.options.endCB(res.data);
        }
      },
      error: function () {
        that.errorToast('Network Error');
      },
      complete: function () {
        that.$el.find('i.icon-oris-loading').removeClass('icon-oris-loading').addClass('icon-add');
      }
    });
  };

  imagePreview($item) {
    if ($item.length === 0) {
      return;
    }
    // 获取imgUrl
    let url = $item.find('img').attr('src');
    if (!url) {
      this.errorToast('Invalid Image!')
      return
    }
    if (this.options.previewCB) {
      that.options.previewCB(url)
      return
    }
    // 默认的预览，弹框展示图片
    let previewHtml = `<div class="modal fade" tabindex="-1" role="dialog" style="z-index: ${this.options.previewZIndex}">
                        <div class="modal-dialog modal-lg">
                          <div class="modal-content" style="border-radius: 2px;">
                            <div>
                              <button type="button" class="close" data-dismiss="modal" aria-hidden="true" style="position:absolute;top:16px;right:16px;font-weight:normal;z-index:2;">
                                <i class="giga icon-V10_danchuangguanbi close-icon"></i>
                              </button>
                            </div>
                            <div class="modal-body text-center" style="padding: 40px 20px">
                              <img src="${url}" style="max-width: 100%"/>
                            </div>
                          </div>
                        </div>
                      </div>`
    $('#orisPreviewImg').html(previewHtml);
    $('#orisPreviewImg').find('.modal').modal('show');
  };

  imageDownload($item) {
    if ($item.length === 0) {
      return;
    }
    let path = $item.find('img').attr('data-path');
    if (!path) {
      return;
    }
    window.open(this.options.downloadUrl.replace(/\{path\}/g, encodeURIComponent(path)))
  };

  imageDelete($item) {
    if ($item.length === 0) {
      return;
    }
    let index = $item.attr('data-index');
    this.options.imageList.splice(index, 1);
    // 重置所有为add
    this.$el.find('.image-items').removeClass('image-items-uploaded').html(`<i class="giga icon-add"></i>`);
    // 重新填充已上传
    this.fillImage();
    // singleAdd 模式下隐藏后续的多余的上传
    if (this.options.singleAdd) {
      let needHide = false
      this.$el.find('.image-items').each(function () {
        if ($(this).hasClass('image-items-uploaded')) {
          return;
        }
        if (needHide === false) {
          // 第一个往后的都需要隐藏
          needHide = true
          return;
        }
        if (needHide) {
          $(this).hide();
        }
      })
    }
  };

  // 校验文件是否超大
  checkFilesSize(file, maxSize) {
    var isOverflow = false;
    if (file['size'] / 1024 / 1024 > maxSize) {
      isOverflow = true;
    }
    return isOverflow;
  };

  errorToast(tips) {
    $.toast({
      heading: false,
      text: tips,
      position: 'top-center',
      showHideTransition: 'fade',
      icon: 'error',
      hideAfter: 5000,
      allowToastClose: false,
      loader: false
    });
  }
}