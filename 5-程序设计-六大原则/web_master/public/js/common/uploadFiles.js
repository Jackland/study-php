// 统一整理前端上传文件
// Date: 2021-04-30
// action-files-range: 点击弹出文件选择框的点击范围dom
// action-files-input: 隐藏的input上传文件入库dom
// action-files-show: 展示选中文件的位置
// action-files-error: 上传文件页面红色错误提示, 例如：Required
var uploadFiles = {
  // $content上传文件父级Dom, 保证该Dom唯一即可;
  // maxLength 上传文件最大数量, 默认10个;
  // maxSize 文件的大小限制, 单位：M , 默认10M;
  // uploadUrl 上传文件Url,如果是null,代表不立即上传文件;
  // isMenuId 如果立即上传文件，是否要带参数menuId(取决于Java那边上传文件逻辑);
  // submitId 是需要禁用的提交按钮，上传文件时需要禁用提交按钮
  initUploadInput: function($content, maxLength, maxSize, uploadUrl, isMenuId, submitId) {
    // 初始化绑定文件数据
    $content.data('fileList', []);
    $content.data('length', maxLength || 10);
    $content.data('size', maxSize || 10);
    $content.data('url', uploadUrl);
    $content.data('isMenuId', isMenuId || false);
    $content.data('menuId', null); // 初始化menuId, 上传文件接口传值用
    $content.data('submitId', submitId);
    $content.on('click', '.action-files-range', function() {
      // 确定点击范围
      $content.find('.action-files-input').trigger('click');
    })
    uploadFiles.changedUploadFiles($content);
    uploadFiles.deleteUploadFile($content);
  },
  changedUploadFiles: function($content) {
    // input 内容变化
    $content.find(".action-files-input").change(function() {
      var fileList = $content.data('fileList');
      var showFilesLen = $content.find('.action-files-show').children('div').length; // 页面展示的文件数量
      var len = $content.data('length');
      var size = $content.data('size');
      var acceptStr = $content.find('.action-files-input').attr('accept');
      var fileArr = $(this)[0].files;
      var errorTips = '';
      for (var i = 0; i < fileArr.length; i++) {
        // 过滤文件类型 并且只能最多上传len个
        if (showFilesLen >= len) {
        	errorTips += `<div>Upload limited to ${len} files.</div>`;
        	break;
        }
        if (acceptStr.indexOf(fileArr[i]['type']) === -1) {
        	errorTips += `<div>${fileArr[i]['name']} uploaded does not meet the requirement for file format.</div>`;
        	continue;
        }
        if (uploadFiles.checkFilesSize(fileArr[i], size)) {
        	errorTips += `<div>${fileArr[i]['name']} uploaded does not meet the requirement for file size (limited to ${size}M).</div>`;
        	continue;
        }
        if (showFilesLen < len && (acceptStr.indexOf(fileArr[i]['type']) > -1)) {
          fileList.push(fileArr[i]);
          $content.find('.action-files-cover').hide();
          $content.find(".action-files-show").append(
            `<div class="action-file">
              <p for="message-text">
                <a title="${fileArr[i]['name']}"><i class="giga icon-attachment"></i>&nbsp;${fileArr[i]['name']}</a>
                <button type="button" class="fileBtn del-btn"><i class="giga icon-lajitong1" aria-hidden="true"></i></button>
                <button type="button" class="fileBtn check-btn"><i class="giga icon-V10_shangchuanwenjianyanzhengtongguo-01" aria-hidden="true"></i></button>
                <button type="button" class="fileBtn loading-btn"><img src="public/image/loading.gif"></button>
              </p>
            </div>`);
        }
        showFilesLen = $content.find('.action-files-show').children('div').length;
      }
      if (errorTips !== '') {
      	uploadFiles.errorToast(errorTips);
      }
      if (showFilesLen <= len) {
        $content.data('fileList', fileList);
        uploadFiles.uploadImmediately($content);
      }
      // 每次change后会清空input的值
      $(this).val('')
    });
  },
  deleteUploadFile: function($content) {
    $content.find(".action-files-show").on('click', '.del-btn', function(e) {
      var fileList = $content.data('fileList');
      e.stopPropagation();
      // 首先得判断是是否是上传成功的files
      let $item = $(this).parent().parent();
      if ($item.hasClass('action-file')) {
        var index = $item.index();
        $item.remove();
        fileList.splice(index, 1);
        $content.data('fileList', fileList);
      } else {
        $item.remove();
      }
      // 每次删除之后清空input内容
      $content.find(".action-files-input").val('');
      if (fileList.length === 0) {
        $content.find('.action-files-cover').show();
      }
    });
  },
  // 校验文件是否超大
  checkFilesSize: function(file, maxSize) {
    var isOverflow = false;
    if (file['size'] / 1024 / 1024 > maxSize) {
      isOverflow = true;
    }
    return isOverflow;
  },
  // 上传文件接口
  // 立即上传文件；上传文件方案：如果isMenuId==true首先上传第一个文件，获取menuId之后，其余文件一次性上传;反之一次性全部上传
  // 页面UI上用户看到的是一起uploading的状态
  uploadImmediately: function($content) {
    var url = $content.data('url');
    var menuId = $content.data('menuId');
    var fileList = $content.data('fileList');
    // 不立即上传文件
    if (!url || !fileList || fileList.length == 0) {
      return;
    }
    // 立即上传文件(OS: Java那边上传逻辑太冗余了，真的没必要这么复杂！！！)
    var isMenuId = $content.data('isMenuId'); // 上传文件是否需要带menuId
    var menuId = $content.data('menuId');
    if (isMenuId) {
      if (menuId) {
        // 存在menuId,则可以一次性上传所有文件
        uploadFiles.uploadAllFiles($content, false, fileList, true, menuId);
      } else {
        // 没有menuId, 首先要先上传第一个文件，获取到接口返回的menuId之后一次性上传剩余所有文件
        uploadFiles.uploadFirstFile($content, url, fileList);
      }
    } else {
      // 一次性上传所有文件
      uploadFiles.uploadAllFiles($content, false, fileList, false, null);
    }
  },
  uploadService: function($content, file, index, isMenuId, menuId) {
    var domFile = $content.find('.action-file')[index];
    $(domFile).addClass('upload-loading');
    uploadFiles.disabledSumbitBtn($content);
    let formData = new FormData();
    let url = $content.data('url');
    formData.append('attach', file);
    if (isMenuId) {
      url = url + `&menuId=${menuId || 0}`;
    }
    $.ajax({
      url: url,
      type: 'post',
      dataType: 'json',
      data: formData,
      cache: false,
      processData: false,
      contentType: false,
      success: function(res) {
        $(domFile).removeClass('upload-loading');
        uploadFiles.disabledSumbitBtn($content);
        if (res.code == 200) {
          uploadFiles.uploadSuccessed($content, index, res.data.list);
        } else {
          uploadFiles.uploadFailed($content, index, res.msg);
        }
      },
      error: function(err) {
        $(domFile).removeClass('upload-loading');
        uploadFiles.disabledSumbitBtn($content);
        uploadFiles.serviceError();
      },
    });
  },
  uploadFirstFile: function($content, url, files) {
  	var domFile = $content.find('.action-file')[0];
  	// 默认把所有都加loading一次性，用户感知不到是分批次上传
    $content.find('.action-file').addClass('upload-loading');
    uploadFiles.disabledSumbitBtn($content);
    let formData = new FormData();
    formData.append('attach', files[0]);
    $.ajax({
      url: url + '&menuId=0',
      type: 'post',
      dataType: 'json',
      data: formData,
      cache: false,
      processData: false,
      contentType: false,
      success: function(res) {
        $(domFile).removeClass('upload-loading');
        uploadFiles.disabledSumbitBtn($content);
        if (res.code == 200) {
          uploadFiles.uploadSuccessed($content, 0, res.data.list);
          // 存储menuId
          $content.data('menuId', res.data.menuId);
          uploadFiles.uploadAllFiles($content, true, files, true, res.data.menuId);
        } else {
          uploadFiles.uploadFailed($content, 0, res.msg);
          // 第一个上传失败之后并没有获取到menuId,所以需要重新获取
          $(domFile).removeClass('action-file');
          files.splice(0,1);
          $content.data('fileList', files);
          if ($content.find('.action-file').length > 0) {
            // 循环
            uploadFiles.uploadFirstFile($content, url, files)
          }
        }
      },
      error: function(err) {
        $(domFile).removeClass('upload-loading');
        uploadFiles.disabledSumbitBtn($content);
        uploadFiles.serviceError();
      },
    });
  },
  uploadAllFiles: function($content, isExceptFirst, files, isMenuId, menuId) {
    $.each(files, function(k, v) {
      // 排除第一个文件
      if (isExceptFirst && k == 0) {
        return;
      }
      // 如果已经上传成功
      var domFile = $content.find('.action-file')[k];
      if ($(domFile).data('subId')) {
        return;
      }
      // 如果已经上传失败
      if ($(domFile).is('.upload-error')) {
        return;
      }
      uploadFiles.uploadService($content, v, k, isMenuId, menuId);
    });
  },
  serviceError: function() {
    // 请求异常
    uploadFiles.errorToast('Upload failed, you may contact the customer service');
  },
  uploadSuccessed: function($content, index, list) {
    // 上传成功之后
    var domFile = $content.find('.action-file')[index];
    $(domFile).data('subId', list[0]['subId']);
    $(domFile).find('button.check-btn').show();
    // 上传成功之后最好能做到取消关联的红色报错action-files-error提示，例如：Reqired红色报错提示
    $content.find('.action-files-error').hide();
  },
  uploadFailed: function($content, index, msg) {
    // 上传失败之后 还要show出某一个失败
    var domFile = $content.find('.action-file')[index];
    $(domFile).addClass('upload-error');
    msg = msg || ($(domFile).find('a').html() + ' error!');
    uploadFiles.errorToast(msg);
  },
  disabledSumbitBtn: function($content) {
  	if (!$content.data('submitId')) {
  		return;
  	}
  	var $btn = $(`#${$content.data('submitId')}`);
  	// check上传文件范围内是否有显示loading来判断是否需要disabled
  	if ($content.find('.loading-btn').is(':visible')) {
  		// 禁用按钮
  		$btn.attr("disabled", true);
      $btn.on('click.disable', false);
  	} else {
  		$btn.attr("disabled", false);
      $btn.off('click.disable');
  	}
  },
  errorToast: function(tips) {
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