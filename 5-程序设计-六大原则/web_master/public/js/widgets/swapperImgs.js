/*
*
* imgArr: 图片数据
* */
$.fn.allPopover =function(imgArr){
  var _this=this
  let small = document.getElementById("left-content-img");
  let big = document.getElementById("hover-big-image");
  //鼠标移动元素变化
  function move() {
    small.addEventListener('mouseover', function () {
      big.style.display = "block";

    })
    small.addEventListener('mouseout', function () {
      big.style.display = "none";
    })
    small.addEventListener('mousemove', function (e) {
      //获得页面中某个元素的左，上，右和下分别相对浏览器视窗的位置
      var object = document.getElementById('imgs');
      var rectObject = object.getBoundingClientRect();
      var rectObjectTop = rectObject.top
      var rectObjectLeft = rectObject.left
      // 计算出鼠标在盒子内的坐标
      // pageX，Y可以获取到鼠标在文档中的位置，
      //鼠标在文档中的位置-元素盒子距离左侧和上侧的距离
      var x = e.pageX - rectObjectLeft;
      var y = e.pageY - rectObjectTop;
      // 使用鼠标在盒子内的坐标减去遮罩层的一半，就是遮罩层的最终位置
      var coverX = x;
      var coverY = y;
      //分别计算出遮罩层X轴与Y轴的最大移动距离
      var coverMaxX = small.offsetWidth;
      var coverMaxY = small.offsetHeight;
      //将遮罩层的移动距离限制在小图片盒子内
      if (coverX <= 0) {
        coverX = 0;
      } else if (coverX >= coverMaxX) {
        coverX = coverMaxX;
      }
      if (coverY <= 0) {
        coverY = 0;
      } else if (coverY >= coverMaxY) {
        coverY = coverMaxY;
      }
      //获取大图
      var bigImg = big.getElementsByTagName("img")[0];
      // 大图片的最大移动距离
      var bigMaxX = bigImg.offsetWidth - big.offsetWidth;
      var bigMaxY = bigImg.offsetHeight - big.offsetHeight;
      // 大图片的移动距离
      var bigX = coverX * bigMaxX / coverMaxX;
      var bigY = coverY * bigMaxY / coverMaxY;
      //大图片一定是往反方向移动，所以设置负值
      bigImg.style.left = -bigX + "px";
      bigImg.style.top = -bigY + "px";
    })
  }
  for (var i = 0; i < imgArr.length; i++) {
    $('.right-img').append(
      `<div data-index="${i}" class="right-img-item"><img src="${imgArr[i].image_path}" /></div>`)

  }
  function btn(){
    var leftBtn = document.getElementById("icon-btn-left");
    var rightBtn = document.getElementById("icon-btn-right");

    if(imgArr.length<=1){
      leftBtn.style.display="none"
      rightBtn.style.display="none"
    }else {
      leftBtn.style.display="block"
      rightBtn.style.display="block"
    }
  }
  var count = 0
  changeSmallImg()

  function changeSmallImg() {
    _this.empty();
    $('#hover-big-image').empty();
    $(".right-img").find(".right-img-item-active").eq(0).removeClass('right-img-item-active');
    $(".right-img").find(".right-img-item").eq(count).addClass('right-img-item-active');
    _this.append(`<img src="${imgArr[count].image_path}" />`)
    $('#hover-big-image').append(`<img src="${imgArr[count].image_path}" />`)
    move()
    btn()
  }
  $(".left-style-right").on('click', function () {
    if (count <= imgArr.length - 2) {
      count = count + 1;
      changeSmallImg()
    } else
    if (count == imgArr.length - 1) {
      count = 0;
      changeSmallImg()
    }
  })

  $(".left-style-left").on('click', function () {
    if (count >= 1) {
      count = count - 1;
      changeSmallImg()
    } else if (count == 0) {
      count = imgArr.length - 1
      changeSmallImg()
    }
  })

  $(".right-img-item").on('click', function (e) {
    count = parseInt(e.currentTarget.dataset.index);
    changeSmallImg()
  })
}