var app = angular.module('app', ['ngSanitize']);
app.controller('messageController', function ($scope, $http, $timeout) {
    $scope.loading = true;
    $scope.expandmsg = false;
    $scope.message = '';
    $scope.totalthreads = '';
    $scope.total = '';
    $scope.checkboxes = {};
    $scope.message_from = '';
    $scope.click = "false";
    $scope.files = [];
    $scope.messageData = [];
    $scope.formData = {};
    $scope.total.inbox = 0;
    $scope.total.sent = 0;
    $scope.total.trash = 0;
    $scope.predicate = 'name';
    $scope.reverse = true;
    $scope.active = 'inbox';
    $scope.keyword = '';
    $scope.lastKeyword = '';
    $scope.msgBoxHtml = '<div class="alert alert-{type}">{msg}<button class="close" data-dismiss="alert" type="button">×</button></div>';
    //初始化方法
  $('#searchInput').keydown(function (e) {
    if (e.keyCode == 13) {
      $scope.searchQuery()
    }
  });
    $scope.searchQuery = function () {
      $scope.page = 1;
      $scope.query();
    };

    $scope.initQuery = function (placeholder_name) {
      if($scope.active==placeholder_name){
        //重置keyword
        $scope.keyword='';
      }
        $scope.page = 1;
        $scope.active = placeholder_name;
        $scope.query();
    };
    $scope.query = function (callback=null) {
        $('.alert').remove();
        $scope.lastKeyword = $scope.keyword;
        let url = "index.php?route=account/wk_communication/messages&placeholder_name=" + $scope.active + '&keyword='+$scope.keyword+"&page="+$scope.page;
        $scope.loading = true;
        $scope.selection = false;
        $http.get(url).success(function (response) {
            $scope.messages = response.messages;
            $scope.total = response.total;
            $scope.loading = false;
          //分页
          $('#pagination_results').html(response.pagination_results);
          $('#bos_pagination').bootstrapPaginator({
            /*当前使用的是3版本的bootstrap*/
            bootstrapMajorVersion: 3,
            /*配置的字体大小是小号*/
            size: 'normal',
            /*当前页*/
            currentPage: response.page_num,
            /*一共多少页*/
            totalPages: response.total_pages,
            /*页面上最多显示几个含数字的分页按钮*/
            numberOfPages: 5,
            onPageClicked: function (event, originalEvent, type, page) {
              $scope.page = page;
              $scope.query( )
            }
          });
          callback && callback();
        });
    }
    $scope.order = function (predicate) {
        $scope.reverse = ($scope.predicate === predicate) ? !$scope.reverse : false;
        $scope.predicate = predicate;
    };
    $scope.drafts = function () {
        $scope.messages = [];

    }
    $scope.route = function (id) {
        $scope.count = 2;

        $scope.messageinfodata = '';
        $scope.expandmsg = false;
        $scope.threadsQuery = "";
        $scope.threadsAttachments = "";
        $scope.attachments = "";
        $scope.message_from = '';
        $scope.selection = true;
        $http.get("index.php?route=account/wk_communication/info&message_id=" + id).success(function (data) {
            $scope.message_info = data.query_info;
            $('#to').val(data.query_info.message_from);
            $('#parent').val(data.query_info.message_id);
            $('#message_to_id').val(data.query_info.message_from_id);
            if (!angular.isUndefined(data.attachment)) {
                $scope.attachments = data.attachment;
            }
            if (!angular.isUndefined(data.threads)) {
                $scope.threadsQuery = data.threads.query_info;
                $scope.threadsAttachments = data.threads.attachment;
            }
            var no_reply = data.no_reply;
            if(no_reply === true){
              $('#submit').attr('disabled','disabled').attr('title',data.no_reply_hint);
            }else{
              $('#submit').removeAttr('disabled').removeAttr('title');
            }

          if( $scope.active ==='inbox'){
             countUnread();
          }
        });
        $http.get("index.php?route=account/wk_communication/getMessageinfodata").success(function (data) {
            $scope.messageinfodata = data;
            $scope.loading = false;
        });

    }
    $scope.download = function (attachment_id) {
        document.location = "index.php?route=account/wk_communication/download&attachment_id=" + attachment_id;
    }
    $scope.delete = function () {
        let $selectInputArray = $('[name="selectedMessages"]:checked');
        if ($selectInputArray.size() < 1) {
            $scope.appendMsgBox('danger','No Message Selected');
            return;
        }
        let deleteIds = [];
        $selectInputArray.each((i,e) => deleteIds.push(e.value));
        $http({
            method: "POST",
            url: 'index.php?route=account/wk_communication/delete',
            params: {
                "message_id[]": deleteIds
            }
        }).success(function (response) {
          if( $scope.active ==='inbox'){
            countUnread();
          }
            if (response.success) {
                $scope.query( function () {
                    $scope.appendMsgBox('success',response.success)
                });
            }
        });
    }
    $scope.expand = function () {
        $scope.expandmsg = false;
        $scope.count = $scope.count + 2;
        if ($scope.count >= $scope.threadsQuery.length)
            $scope.expandmsg = true;
    }
    $scope.submitQuery = function () {
        var form = document.forms.namedItem("replyform");
        var fd = new FormData(form);
        $http.post('index.php?route=account/wk_communication/reply', fd, {
            transformRequest: angular.identity,
            headers: {'Content-Type': undefined},
        }).success(function (response) {
            if (response.success) {
                document.forms.namedItem("replyform").reset();
                $('#body').summernote('reset');
                $('.attach-file').css('background-image', 'none');
                $('.attach').remove();
                $('.ex').remove();

                    //发邮件
                    $http.post('index.php?route=account/wk_communication/sendSMTPMail', fd, {
                        transformRequest: angular.identity,
                        headers: {'Content-Type': undefined},
                    }).success(function (response) {
                      console.log('sendMail ',response)
                    });

                //end
                $scope.query(function () {
                    $scope.appendMsgBox('success',response.success)
                });
            } else if (response.error) {
                  $scope.appendMsgBox('danger',response.error,$('#message_form'))
            } else {
                    $scope.appendMsgBox('danger','operation failed',$('#message_form'));
            }
        }).error(function (response) {
                    $scope.appendMsgBox('danger','operation failed',$('#message_form'));
        });
    }
  $scope.returnEstablishResult = function (returnType,messageId,user_name) {
      let layer_area;
    if (returnType == 1) {
      //同意
      var title='Approve the application of '+user_name;
      var comment = 'Welcome to establish relationship with ['+storeName+']. Look forward to future cooperation.'
      $(".set_group").show();
      layer_area = ['480px', '360px'];
    }else {
      var title='Reject the application of '+user_name;
      var comment = 'Sorry this store is not in business so far. It\'s currently not availble to be accessed.'
      $(".set_group").hide();
      layer_area = ['480px', 'auto'];
    }
    $("#returnEstablishComment").val(comment);
    $("#returnEstablishCount").html(comment.length);
    layer.open({
      type: 1,
      title: title,
      closeBtn: 0,
      skin: 'layui-layer-lan',
      shadeClose: false,
      area: layer_area,
      offset: 'auto',
      content: $("#returnEstablishModal"),
      btn:['OK','Cancel'],
      btnAlign: 'center',
      resize:false,
      scrollbar:false,
      yes: function (index, layero) {
        var body = $('#returnEstablishComment').val();
        let buyer_group_id = $("#input_buyer_group").val();
        layer.close(index);
        if (returnType == 1) {
          isRefine = layer.confirm("Do you want to take refined management to the buyer?", {
            btn: ['Yes', 'No,with default settings'],
            title: 'Message',
            skin: 'layui-layer-lan',
          }, function (index, layero) {
            $scope.establishReply(returnType, messageId, 1,body,buyer_group_id);
            layer.close(index);
          }, function (index) {
            $scope.establishReply(returnType, messageId, 0,body,buyer_group_id);
            layer.close(index);
          });
        } else {
          $scope.establishReply(returnType, messageId, 0,body,buyer_group_id);
        }
      }
    });

    var input = document.getElementById("returnEstablishComment");
    var len=input.value.length;
    //将光标定位到文本最后
    setSelectionRange(input,len,len);
  };

  function setSelectionRange(input, selectionStart, selectionEnd) {
    if (input.setSelectionRange) {
      input.focus();
      input.setSelectionRange(selectionStart, selectionEnd);
    } else if (input.createTextRange) {
      var range = input.createTextRange();
      range.collapse(true);
      range.moveEnd('character', selectionEnd);
      range.moveStart('character', selectionStart);
      range.select();
    }
  }

  $scope.establishReply = function (returnType, messageId, isRefine = 0,body,buyer_group_id) {
    var formData = new FormData();
    formData.append('message',body);
    formData.append('buyer_group_id',buyer_group_id);
    $http.post('index.php?route=account/wk_communication/establishReply&returnType=' + returnType + '&message_id=' + messageId + '&isRefine=' + isRefine,
      formData, {
        transformRequest: angular.identity,
        headers: {'Content-Type': undefined},
      }).success(function (response) {
      countUnread();
      if (response.success && response.message_id) {
          //发邮件
          $http.post('index.php?route=account/wk_communication/establishReplySendMail&returnType=' + returnType + '&message_id=' + response.message_id,
            formData,
            {
              transformRequest: angular.identity,
              headers: {'Content-Type': undefined},
            }).success(function (response) {
            if (response) {
              console.log(response);
            }
          });
        if (isRefine == 1 && response.jump_url) {
          window.location.href = response.jump_url;
        }
        $scope.query( function () {
          $scope.appendMsgBox('success', response.success)
        });
      } else if (response.error) {
        $scope.appendMsgBox('danger', response.error, $('#message_form'))
      } else {
        $scope.appendMsgBox('danger', 'operation failed', $('#message_form'));
      }
    }).error(function (response) {
      $scope.appendMsgBox('danger', 'operation failed', $('#message_form'));
    });
  };
    $scope.count = function () {
        $scope.total[$scope.active] = $scope.filtered.length
    };
    $scope.appendMsgBox = function (type,msg,obj) {
        $('.alert').remove();
       let html = $scope.msgBoxHtml.replace('{type}',type)
                .replace('{msg}',msg);
       if(obj){
            $(obj).prepend(html);
       }else{
            $("#message").prepend(html);
       }
    }
    $scope.readAll = function () {
      $http({
        method: "POST",
        url: 'index.php?route=account/wk_communication/readAll',
        params: {
          "keyword": $scope.lastKeyword
        }
      }).success(function (response) {
        countUnread();
        $scope.query();
      });
    }
});


app.filter('toTrusted', function ($sce) {
    return function (value) {
        return $sce.trustAsHtml(value);
    };
});
