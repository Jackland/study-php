var img_container = {};
var maxUploadNum  = 5;
$(function () {
    let body = $('body');
    // 鼠标经过显示删除按钮
    body.delegate('.content-img-list .content-img-list-item', 'mouseover', function () {
        $(this).children('a').removeClass('hide');
    });
    // 鼠标离开隐藏删除按钮
    body.delegate('.content-img-list .content-img-list-item', 'mouseleave', function () {
        $(this).children('a').addClass('hide');
    });
    // 单个图片删除
    body.delegate('.content-img-list .content-img-list-item a .fa-trash-o', "click", function () {
        var index = $(this).parent().parent().index();
        var boxId = $(this).parents('.content-img').find('.content-img-list');
        var id = boxId.attr('data-id');
        let uploadObj = $(this).parent().parent().parent().parent().find('.upload');
        var imgListObj = eval($(uploadObj[0]).data("img-list-obj"));//用于指定全局接收图片数据的变量名称，该变量必须在外面声明
        //删除暂存图片的对象
        imgListObj.splice(index,1);
        img_container[id].imgSrc.splice(index, 1);
        img_container[id].imgFile.splice(index, 1);
        img_container[id].imgName.splice(index, 1);
        addNewContent(boxId);
        if (img_container[id].imgSrc.length < maxUploadNum) { //显示上传按钮
            boxId.parents('.content-img').find('.file').show();
        }
    });
    //查看原图
    body.delegate('.content-img-list .content-img-list-item a .fa-search-plus', "click", function () {
        var index = $(this).parent('a').attr('index');
        var id = $(this).parents('.content-img').find('.content-img-list').attr('data-id');
        let imgSrc = img_container[id].imgSrc;
        let modal = $(".img-modal-content .modal-body");
        modal.html("");
        modal.html('<div class="show"><img class="img-responsive" src="' + imgSrc[index] + '" alt=""><div>');
        $('#show_image').modal('show');
    });
    //图片上传
    body.delegate('.upload', 'change', function (e) {
        var url = this.dataset.uploadUrl;//data-upload-url属性
        var imgMaxSize = this.dataset.imgMaxSize;//img-max-size属性
        var imgTypes = eval(this.dataset.imgTypes);//img-types属性
        var imgListObj = eval(this.dataset.imgListObj);//用于指定全局接收图片数据的变量名称，该变量必须在外面声明
        //前端校验
        var imgSize = this.files[0].size;
        if (imgSize > imgMaxSize) { //30M
            $('.upload').val('');
            let maxSizeMb = imgMaxSize/1024/1024;
            return myToastFn(false,"Upload image cannot exceed " + maxSizeMb + "MB",'error');
        }
        var file = this.files[0];
        var typeCheck = false;
        $.each(imgTypes, function (i, type) {
            if (file.type == type) {
                typeCheck = true;
            }
        });
        if (!typeCheck) {
            $('.upload').val('');
            return  myToastFn(false,'Incorrect image upload format','error');
        }

        var fd = new FormData();
        var file = this.files[0];
        fd.append("file", file)

        //图片上传
        $.ajax({
            url: url,
            type: 'POST',
            data: fd,
            async: true,
            cache: false,
            contentType: false,
            processData: false,
            // dataType: 'json',
            success: function (res) {
                if (res.code == 200) {
                    //使用该组件必须声明全局变量
                    imgListObj.push(res.data)
                } else {
                    $('.upload').val('');
                    return myToastFn(false,res.msg,'error');
                }
            }
        });

        var imgBox = $(this).parents('.content-img').find('.content-img-list');
        var id = imgBox.attr('data-id');
        if (!img_container.hasOwnProperty(id)) {
            img_container[id] = {imgName: [], imgSrc: [], imgFile: [],};
        }
        var imgSrcI = getObjectURL(file);
        img_container[id].imgName.push(file.name);
        img_container[id].imgSrc.push(imgSrcI);
        img_container[id].imgFile.push(file);

        if (img_container[id].imgSrc.length >= maxUploadNum) { //显示上传按钮
          $(this).parents('.content-img').find('.file').hide();
        }

        addNewContent(imgBox);
        this.value = null; //上传相同图片
    });
});

//删除
function removeImg(obj, index) {
    imgSrc.splice(index, 1);
    imgFile.splice(index, 1);
    imgName.splice(index, 1);
    var boxId = ".content-img-list";
    addNewContent(boxId);
}

//图片展示
function addNewContent(obj) {
    // console.log(imgSrc)
    let id = $(obj).attr('data-id');
    if (JSON.stringify(img_container[id]) === undefined) {
      return;
    }

    let imgSrc = img_container[id].imgSrc;
    $(obj).html("");
    for (var a = 0; a < imgSrc.length; a++) {
        var oldBox = $(obj).html();
        $(obj).html(
            oldBox
            + '<li class="content-img-list-item"><img src="' + imgSrc[a]
            + '" alt=""><a index="' + a
            + '" class="hide delete-btn"><i class="fa fa-trash-o"></i><i class="fa fa-search-plus""></i> </a></li>'
        );
    }
}

//建立一個可存取到該file的url
function getObjectURL(file) {
    var url = null;
    if (window.createObjectURL != undefined) { // basic
        url = window.createObjectURL(file);
    } else if (window.URL != undefined) { // mozilla(firefox)
        url = window.URL.createObjectURL(file);
    } else if (window.webkitURL != undefined) { // webkit or chrome
        url = window.webkitURL.createObjectURL(file);
    }
    return url;
}
