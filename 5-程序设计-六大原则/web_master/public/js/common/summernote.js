var summernote = {
  autoInit: true,
  globalInit() {
    // 设置可用字体
    $.summernote.options.fontNames = [
      'Arial', 'Arial Black', 'Comic Sans MS', 'Courier New',
      'Helvetica Neue', 'Helvetica', 'Impact', 'Lucida Grande',
      'Open Sans', 'Tahoma', 'Times New Roman', 'Verdana',
    ];
    $.summernote.options.fontNamesIgnoreCheck = ['Open Sans'];
    // 修改字体大小
    $.summernote.options.fontSizes = [
      '12', '13', '14', '16', '18', '20', '24', '36', '48'
    ];
    // 修改语言
    $.summernote.lang['en-US'].lists.unordered = 'Bullet point list'
  },
  init(el, options = {}) {
    // 其他配置设置
    var _this = this;
    const config = Object.assign({
      sendUrl: '/index.php?route=common/file/uploadImage',
    }, options.custom);
    delete options.custom

    let insertToolbar = ['link', 'picture', 'video']; // 默认使用 picture 插入图片
    if (options.buttons && options.buttons.image) {
      // 定义了 image 按钮的，覆盖原 picture
      insertToolbar[1] = 'image';
    }

    // 支持通过 options 额外扩展 onChange
    var _customOnChange = null
    if (options.callbacks && options.callbacks.onChange) {
      _customOnChange = options.callbacks.onChange
      delete options.callbacks.onChange
    }
    options = $.extend(true, {
      height: 300,
      toolbar: [
        //['undo', ['undo', 'redo']],
        ['style', ['style']],
        ['font', ['bold', 'underline', 'clear']],
        ['fontname', ['fontname']],
        ['fontsize', ['fontsize']],
        ['color', ['color']],
        ['para', ['ul', 'ol', 'paragraph']],
        ['height', ['height']],
        ['hr', ['hr']],
        ['table', ['table']],
        ['insert', insertToolbar],
        ['view', ['fullscreen', 'codeview', 'help']]
      ],
      callbacks: {
        onImageUpload: function (files) {
          // ctrl+v 粘贴图片和选择图片的处理，默认转为base64，改为上传到后台
          $.each(files, function (k, file) {
            _this._sendFile(el, file, config.sendUrl)
          })
        },
        onPaste: function (event) {
          const clipboardData = event.originalEvent.clipboardData;

          let textWithImage = null
          if (clipboardData && clipboardData.items && clipboardData.items.length) {
            const item = clipboardData.items.length > 1 ? clipboardData.items[1] : clipboardData.items[0];
            if (item.kind === 'file' && item.type.indexOf('image/') !== -1) {
              // 直接粘贴图片时不处理，会直接调用 onImageUpload 上传图片
              event.preventDefault();
            } else if (item.kind === 'string') {
              // 粘贴文本时检查是否带图片，带图片的处理掉
              const text = clipboardData.getData('Text/html')
              if (text.indexOf('src="file:') > -1 || text.indexOf('src="data:image') > -1) {
                textWithImage = text
              }
            }
          }
          if (textWithImage) {
            event.preventDefault();
            setTimeout(function () {
              // 移除本地图片
              textWithImage = textWithImage.replace(/<img(.*?)src="file:\/(.*?)>/g, '');
              // 移除base64图片
              textWithImage = textWithImage.replace(/<img(.*?)src="data:image\/(.*?)>/g, '');
              el.summernote('pasteHTML', textWithImage);
            }, 1);
          }
        },
        onChange: function (contents) {
          // 浏览器对这些tag会display:none，因此移除这些标签的内容，防止存到数据库后展示出来的地方处理数据后显示出来导致录入和展示不一致问题
          var notSupportTag = ['head', 'title', 'script'];
          var changed = false;
          $.each(notSupportTag, function (k, tag) {
            var reg = RegExp(`<${tag}(.*?)>(.*?)<\/${tag}>`, 'gi')
            if (reg.test(contents)) {
              contents = contents.replace(reg, '');
              changed = true;
            }
          })
          if (changed) {
            el.summernote('code', contents);
          }

          if (_customOnChange) {
            _customOnChange(contents)
          }
        }
      },
      buttons: {
        image: function () {
          const ui = $.summernote.ui,
            context = $.summernote,
            options = context.options,
            lang = options.langInfo;
          return ui.button({
            contents: ui.icon(options.icons.picture),
            tooltip: lang.image.image,
            click: context.createInvokeHandler('imageDialog.show')
          }).render();
        }
      }
    }, options);
    el.summernote('destroy'); // 先删除已有的，否则会导致新的配置不生效
    el.summernote(options);
  },
  _sendFile(el, file, sendUrl) {
    var data = new FormData();
    data.append('file', file);
    $.ajax({
      data: data,
      type: 'POST',
      url: sendUrl,
      cache: false,
      contentType: false,
      processData: false,
      success: function (data) {
        if (data.error) {
          alert(data.error);
        }
        if (data.success) {
          el.summernote('editor.insertImage', data.url);
        }
      }
    });
  }
}
summernote.globalInit();
$(function () {
  if (summernote.autoInit) {
    $('[data-toggle=\'summernote\']').each(function () {
      summernote.init($(this));
    })
  }
});
