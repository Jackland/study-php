// add product
let vm_4396 = new Vue({
  el: '#app_form',
  data: function () {
    let _this = this;
    // 校验mpn重复
    let ValidateMpn = function (rule, value, callback) {
      if (_this.formModel.product_id) return; // 修改产品时不允许修改mpn 也就不需要校验mpn
      _this.axios.get('index.php?route=account/customerpartner/addproduct/checkMpnNoComboFlag&mpn=' + value)
        .then(function (res) {
          if (res['status'] !== 200) {
            console.error(res);
            return;
          }
          let data = res['data'] || {};
          if (data['success'] && data['success'] === true) {
            return callback(new Error('Failed！MPN cannot repeat.'));
          }
        })
        .catch(function (e) {
          console.error(e);
        })
    };
    // 校验产品名称
    let validateProductName = function (rule, value, callback) {
      let item = value[1];
      if (!item.name) {
        return callback(new Error('Product Name can not be left blank.'));
      } else if (item.name.length < 1 || item.name.length > 200) {
        return callback(new Error('Product Name must be greater than 1 and less than 200 characters.'));
      }
    };
    // 校验产品图片
    let validateProductImage = function (rule, value, callback) {
      _this.refreshUploadInput2();
      // 商品可以单独售卖时候 必须选择主图
      if (_this.formModel.allowedBuy === 1 && !_this.formModel.image) {
        return callback(new Error('Main image is needed!'))
      }
      // sort order不能相同
      // 后续需求 暂时不校验sort order
      // let sortOrderArr = value.map(function (item) {
      //   return item['sort_order'];
      // });
      // let uniqueSortOrderArr = sortOrderArr.filter(function (value, index, self) {
      //   return self.indexOf(value) === index;
      // });
      // if (sortOrderArr.length !== uniqueSortOrderArr.length) {
      //   return callback(new Error('Display order numbers must be different from each other.'));
      // }

    };
    // 校验combo品不能为空
    let validatorCombo = function (rule, value, callback) {
      // 只有在product type选择combo item时才校验combo品是否为空
      if (_this.formModel.product_type !== 2) return;
      let combos = _this.formModel.combo;
      if (combos.length === 0) {
        return callback(new Error('Sub-items can not be left empty.'));
      }
      if (combos.length === 1 && (parseInt(combos[0].quantity) <= 1)) {
        return callback(new Error('Sub-item quantity must greater than 1.'));
      }
    };

    return {
      options: [],
      bar_active: 0,     // 步骤条标志
      stepStatus: [
        {status: 'process', title: 'General Information'},
        {status: 'wait', title: 'Product Specifications'},
        {status: 'wait', title: 'Material Package'}
      ],
      formModel: {
        product_id: window.PRODUCT_ID || null,                           // 商品id 这个参数必须初始化,多数api请求依赖
        partFlag: null,
        comboFlag: null,
        model: null,
        // 基本属性
        status1: 1,                                                      // 商品上下架状态  status
        product_group_ids: '',                                           // group ids
        product_category: [],                                            // 商品类别
        allowedBuy: 1,                                                   // 商品是否允许购买
        mpn: null,                                                       // MPN
        sku: null,                                                       // item code
        manufacturer_id: null,                                           // 商品品牌
        product_description: {                                           // 商品描述 原先为product_description[1][name]
          1: {
            name: null,
            description: null,
            meta_title: null,
            meta_description: null,
            meta_keyword: null,
            tag: null,
            returns_and_notice: null                                     // Returns & Notice
          }
        },
        returns_and_notice: {                                             // 创建富文本
          1: {}
        },
        product_image: [],                                               // 商品图片 [ {image:'',sort_order:''},... ]
        image: null,                                                     // 商品主图片
                                                                         // ----------------产品属性------------------------------
        product_type: 1,                                                 // 产品类型 [1:General item 2:Combo item 3:Part item]
        length: null,                                                    // 长度
        width: null,                                                     // 宽度
        height: null,                                                    // 高度
        weight: null,                                                    // 质量
        color: null,                                                     // 颜色 color
        product_color: [],                                               // 关联产品
        combo: [],                                                       // combo子产品
                                                                         // [{mpn:'',quantity:'',length:'',width:'',height:'',weight:'',...}]
                                                                         // ---------------------素材包----------------------------
        material_images: [],
        material_manuals: [],
        material_video: [],
        // 兼容之前写法
        product_link_tab: 1,
        product_attribute_tab: 1,
        product_option_tab: 1,
        product_discount_tab: 1,
        product_special_tab: 1,
        // 表征表单来源
        fromNew: 1
      },
      formRules: {
        mpn: [
          {required: true, message: 'MPN can not be left blank.'},
          {min: 3, max: 255, message: 'MPN must be greater than 3 and less than 255 characters.'},
          {validator: ValidateMpn, trigger: 'blur'}
        ],
        product_description: [{validator: validateProductName}],
        returns_and_notice: [{validator: validateProductName}],
        product_image: [{validator: validateProductImage}],
        combo: [{validator: validatorCombo}]
      },
      // product group
      product_group_value: null,
      product_group: [],
      product_group_list: [],
      // end product group
      // categories
      product_category: [],    // [{label:'',value:''},...]
      categoryListDialogVisible: false,
      categoryList: [],
      categoryTreeProps: {
        children: 'son',
        label: 'name'
      },
      // brand start
      brandQueryTimeOut: null,
      brandSelectName: null,
      // brand end
      // product description start
      descriptionSummerNote: null,    // editor实例
      // product description end
      // Returns & Notice start
      returnsAndNoticeSummerNote: null,    // editor实例
      // Returns & Notice end
      // product images
      fileList: [],
      // end product images
      // product type
      product_type_list: [
        {value: 1, title: 'General item'},
        {value: 2, title: 'Combo item'},
        {value: 3, title: 'Replacement part'}
      ],
      // end product type
      // color
      colorName: null,
      product_color_list: [],
      temp_product_color_list: [],
      colorQueryTimeOut: null,
      associatedProductsDialogVisible: false, // add associated products 按钮点击
      associatedProductsDialogInputValue: null,
      associatedProductsDialogInputTimeout: null,
      associateProductTableData: [],
      associateProductCurrentPage: 1,
      associateProductCurrentPageSize: 5,
      associateProductTotal: 0,
      // end color
      // combo
      temp_sub_item_list: [],
      addSubItemDialogVisible: false,
      addSubItemDialogInputValue: null,
      addSubItemDialogInputTimeout: null,
      addSubItemTableData: [],
      comboQuantityValueMap: [],
      addSubItemCurrentPage: 1,
      addSubItemCurrentPageSize: 5,
      addSubItemTotal: 0,
      // end combo
      // material images
      material_images: [], // 初始化material images赋值
      // end material images
      // material manuals
      material_manuals: [],
      // end material manuals
      // material video
      material_video: [],
      // end material video
      // axios 实例
      axios: null,
      // loading 实例  防止用户多次点击
      loading: null
    };
  },
  computed: {
    // 监测对象内部变化
    formMpn: function () {
      return this.formModel.mpn;
    },
    productDescription: function () {
      return this.formModel.product_description[1]['description'];
    },
    returnsAndNotice: function () {
      return this.formModel.product_description[1]['returns_and_notice'];
    },
    formProductType: function () {
      return this.formModel.product_type;
    },
    isShowProductGroup: function () {
      return parseInt(this.formModel.status1) === 1 && parseInt(this.formModel.allowedBuy) === 1;
    }
  },
  watch: {
    isShowProductGroup: {
      immediate: true,
      handler: function (res) {
        if (!res) {
          this.product_group = [];
          this.product_group_list = this.product_group_list.map(function (item) {
            if (!item.hasOwnProperty('checked')) item.checked = false;
            item.checked = false;
            return item;
          })
        }
      }
    },
    product_group: {
      immediate: true,
      handler: function (groups) {
        let _this = this;
        let groupName = groups.map(function (item) {
          return item.name;
        });
        _this.product_group_value = groupName.length > 0 ? groupName.join(',') : null;
        let groupIds = groups.map(function (item) {
          return item.id;
        });
        _this.formModel.product_group_ids = groupIds.join(',');
      }
    },
    product_category: {
      immediate: true,
      handler: function (items) {
        let _this = this;
        _this.formModel.product_category = [];
        items.map(function (item) {
          _this.formModel.product_category.push(item['value']);
        })
      }
    },
    formMpn: {
      immediate: true,
      handler: function (formMpn) {
        // 处于修改商品时候 sku mpn不需要联动
        if (this.formModel.product_id > 0) return;
        this.formModel.sku = formMpn;
      }
    },
    formProductType: {
      immediate: true,
      handler: function (productType) {
        switch (productType) {
          case 2: {
            this.formModel.partFlag = null;
            this.formModel.comboFlag = 'on';
            break;
          }
          case 3: {
            this.formModel.partFlag = 'on';
            this.formModel.comboFlag = null;
            break;
          }
          default: {
            this.formModel.partFlag = null;
            this.formModel.comboFlag = null;
            break;
          }
        }
      }
    },
    fileList: {
      deep: true,
      handler: function (fileList) {
        let _this = this;
        _this.formModel.image = null;
        _this.formModel.product_image = [];
        fileList.map(function (item) {
          let file_url = item['orig_url'].replace(/^image\//g, '');
          if (item['isMainImage']) _this.formModel.image = file_url;
          _this.formModel.product_image.push({
            image: file_url,
            sort_order: parseInt(item['sort_order'] || 0)
          });
        })
      }
    },
    colorName: function (val) {
      if (val === undefined || !val) {
        this.formModel.color = 0;
      }
    },
    brandSelectName: function (val) {
      if (val === undefined || !val) {
        this.formModel.manufacturer_id = 0;
      }
    },
    associatedProductsDialogInputValue: function (val) {
      this.getAssociatedProducts(val);
    },
    product_color_list: {
      immediate: true,
      handler: function (list) {
        this.formModel.product_color = list.map(function (item) {
          return item['product_id'];
        })
      }
    },
    addSubItemDialogInputValue: function (val) {
      this.getComboProducts(val);
    },
    material_images: {
      immediate: true,
      handler: function (images) {
        let _this = this;
        _this.formModel.material_images = [];
        images.map(function (item) {
          let info = {
            url: item['orig_url'].replace(/^(productPackage|image)\//g, ''),
            name: item['name'],
            file_id: 0,
            m_id: 0
          };
          if (item.hasOwnProperty('file_id')) info.file_id = item.file_id;
          if (item.hasOwnProperty('m_id')) info.m_id = item.m_id;
          _this.formModel.material_images.push(info);
        })
      }
    },
    material_manuals: {
      immediate: true,
      handler: function (items) {
        let _this = this;
        _this.formModel.material_manuals = [];
        items.map(function (item) {
          let info = {
            url: item['orig_url'].replace(/^(productPackage|image)\//g, ''),
            name: item['name'],
            file_id: 0,
            m_id: 0
          };
          if (item.hasOwnProperty('file_id')) info.file_id = item.file_id;
          if (item.hasOwnProperty('m_id')) info.m_id = item.m_id;
          _this.formModel.material_manuals.push(info);
        })
      }
    },
    material_video: {
      immediate: true,
      handler: function (items) {
        let _this = this;
        _this.formModel.material_video = [];
        items.map(function (item) {
          let info = {
            url: item['orig_url'].replace(/^(productPackage|image)\//g, ''),
            name: item['name'],
            file_id: 0,
            m_id: 0
          };
          if (item.hasOwnProperty('file_id')) info.file_id = item.file_id;
          if (item.hasOwnProperty('m_id')) info.m_id = item.m_id;
          _this.formModel.material_video.push(info);
        })
      }
    }
  },
  mounted: function () {
    let _this = this;
    this.axios = axios.create({});
    // api请求
    axios.all([_this.getProductGroupList(), _this.getProductInfo()])
      .then(axios.spread(function (res1, res2) {
        setTimeout(function () {
          // 初始化summerNote
          _this.initSummerNote();
        }, 0);
        _this.product_group_list = res1['data'];
        // product info
        let productInfo = res2['data'];
        if (
          !_this.formModel.product_id
          || (Array.isArray(productInfo) && productInfo.length === 0)
        ) {
          return;
        }
        _this.formModel.product_description[1] =
          {
            name: productInfo['name'],
            description: productInfo['description'],
            meta_description: productInfo['meta_description'],
            meta_keyword: productInfo['meta_keyword'],
            meta_title: productInfo['meta_title'],
            tag: productInfo['tag'],
            returns_and_notice: productInfo['returns_and_notice']
          };
        _this.formModel.status1 = parseInt(productInfo['status']);
        // product group
        _this.product_group_list.map(function (item) {
          if (
            productInfo['group_ids']
            && productInfo['group_ids'].indexOf(item['id']) !== -1
          ) {
            item.checked = true;
            _this.product_group.push(item);
            return item;
          }
        });
        // product category
        _this.product_category = productInfo['product_category'] || [];
        // buyer flag
        _this.formModel.allowedBuy = parseInt(productInfo['buyer_flag']);
        // mpn
        _this.formModel.mpn = productInfo['mpn'];
        // sku
        _this.formModel.sku = productInfo['sku'];
        // manufacture id
        _this.brandSelectName = productInfo['manufacturer'];
        _this.formModel.manufacturer_id = productInfo['manufacturer_id'];
        // image product image
        let productImage = [];
        let images = productInfo['product_image'];
        let isInImages = false;
        images = images.map(function (item) {
          if (productInfo['image'] === item['orig_url']) {
            isInImages = true;
            item.isMainImage = true;
          }
          return item;
        });
        // 首先确认一点 主图是不是在product images中；
        if (!isInImages && productInfo['image'] && productInfo['image'] !== '') {
          productImage.push({
            thumb: productInfo['image_show_url'],
            url: productInfo['image_show_url'],
            orig_url: productInfo['image'],
            isMainImage: true
          });
          _this.formModel.image = productInfo['image']
        }
        _this.fileList = productImage.concat(images);
        // product type 判定
        let partFlag = parseInt(productInfo['partFlag']);
        let comboFlag = parseInt(productInfo['comboFlag']);
        if (partFlag === 1) {
          _this.formModel.product_type = 3;
        } else if (comboFlag === 1) {
          _this.formModel.product_type = 2;
        } else {
          _this.formModel.product_type = 1;
        }
        // length width height weight
        _this.formModel.length = productInfo['length'];
        _this.formModel.width = productInfo['width'];
        _this.formModel.height = productInfo['height'];
        _this.formModel.weight = productInfo['weight'];
        // color
        _this.colorName = productInfo['colorName'];
        _this.formModel.color = productInfo['color'];
        _this.product_color_list = productInfo['associate_products'] || [];
        // combo
        _this.formModel.combo = productInfo['combo_products'] || [];
        // material
        _this.material_images = productInfo['material_images'] || [];
        _this.material_manuals = productInfo['material_manuals'] || [];
        _this.material_video = productInfo['material_video'] || [];
      }));
  },
  methods: {
    // step bar click 回调函数
    handlerClickOnStepBar: function (index) {
      let _this = this;
      _this.bar_active = index;
      _this.stepStatus = _this.stepStatus.map(function (item, key) {
        if (key === index) {
          item['status'] = 'process';
        } else {
          if (item.status === 'process') item.status = 'wait';
        }
        return item;
      })
    },
    // next step 回调函数
    handleNextStepClick: function () {
      let _this = this;
      if (_this.bar_active === 2) {
        // 此时应该执行submit
        _this.submitForm();
        return;
      }
      _this.validateFieldsByBarActive(_this.bar_active);
      _this.stepStatus[++_this.bar_active]['status'] = 'process';
    },
    // previous step 回调函数
    handlePreviousStepClick: function () {
      let _this = this;
      _this.stepStatus[_this.bar_active--]['status'] = 'wait';
      _this.stepStatus[_this.bar_active]['status'] = 'process';
    },
    // 分区校验各个字段
    validateFieldsByBarActive: function (bar_active) {
      let app_form = this.$refs['app_form'];
      let errorMsg = [];
      switch (bar_active) {
        case 0: {
          app_form.validateField([
            'product_category',
            'mpn',
            'product_description',
            'product_image'
          ], function (msg) {
            msg && msg.length > 0 && errorMsg.push(msg);
          });
          break;
        }
        case 1: {
          if (this.formModel.product_type === 2) {
            app_form.validateField(['combo'], function (msg) {
              msg && msg.length > 0 && errorMsg.push(msg);
            });
          }
          break;
        }
        default:
          break;
      }
      this.stepStatus[bar_active]['status'] = errorMsg.length > 0 ? 'error' : 'success';
      return errorMsg;
    },
    // 实际的表单提交方法
    submitForm: function () {
      let _this = this;
      let errorMsg = [].concat(
        _this.validateFieldsByBarActive(0),
        _this.validateFieldsByBarActive(1),
        _this.validateFieldsByBarActive(2)
      );
      if (errorMsg.length > 0) {
        _this.error('Please correct error input first!');
        return;
      }
      if (!_this.formModel.color && _this.formModel.product_color.length > 0) {
        _this.error('You can not add associated products without select color attribute!');
        return;
      }
      if (_this.loading !== null) return;
      _this.loading = this.$loading({
        lock: true,
        text: 'Loading',
        spinner: 'el-icon-loading',
        background: 'rgba(0, 0, 0, 0.7)'
      });
      //检测是否新增删除关联商品
      let check_url = 'index.php?route=pro/product/check_relation_product' + '&product_id=' + _this.formModel.product_id;
      _this.axios.post(check_url, _this.formModel)
        .then(function (res) {
          let data = res['data'];
          const rCode = parseInt(data.hasOwnProperty('code') ? data['code'] : 0);
          if (rCode === 1) { // 成功
            alert(data['msg']);
          }
        })
        .catch(function (e) {
          console.log(e);
        });
      let url = 'index.php?route=pro/product/storeProduct';
      if (_this.formModel.product_id) url += '&product_id=' + _this.formModel.product_id;
      _this.axios.post(url, _this.formModel)
        .then(function (res) {
          let data = res['data'];
          _this.loading.close();
          const rCode = parseInt(data.hasOwnProperty('code') ? data['code'] : 0);
          if (rCode === 0) { // 成功
            _this.$notify.success({
              title: 'Info',
              message: data['msg']
            });
          } else {          // 失败
            _this.$notify.error({
              title: 'Info',
              message: data['msg']
            });
            _this.loading = null;
            return;
          }
          _this.formModel.product_id > 0
            ? _this.editProductAfter()        // 编辑商品回调函数
            : _this.addProductAfter();        // 添加商品回调函数
        })
        .catch(function (e) {
          _this.loading.close();
          _this.loading = null;
          console.log(e);
        })
    },
    addProductAfter: function () {
      let _this = this;
      // 商品为下架 或者 不能为单独售卖状态
      if (_this.formModel.status1 === 0 || _this.formModel.allowedBuy === 0) {
        window.location.href = 'index.php?route=account/customerpartner/productlist';
        return;
      }
      _this.$confirm('This item has been added successfully.Continue to set the product price?', 'Notice', {
        confirmButtonText: 'Confirm',
        cancelButtonText: 'Cancel',
        type: 'warning',
        center: true
      })
        .then(function () {
          window.location.href =
            'index.php?route=account/customerpartner/product_manage&filter_mpn=' + _this.formModel.mpn;
        })
        .catch(function () {
          window.location.href = 'index.php?route=account/customerpartner/productlist';
        })
    },
    editProductAfter: function () {
      let _this = this;
      // 编辑产品之后允许重复提交表单
      _this.loading = null;
    },
    // region product group
    handlerProductGroupClick: function (item) {
      let _this = this;
      // 所有东西都与product_group属性关联 务必留意
      if (item.hasOwnProperty('checked') && item.checked === true) {
        // 由checked => unchecked
        _this.product_group = _this.product_group.filter(function (oldItem) {
          return oldItem.id !== item.id;
        });
      } else {
        _this.product_group.push(item);
      }
      _this.product_group_list = _this.product_group_list.map(function (val) {
        if (val.id === item.id) {
          if (!val.hasOwnProperty('checked')) val.checked = false;
          val.checked = !!!val.checked;
        }
        return val;
      })
    },
    // endregion product group
    // region select categories 点击事件回调函数
    handleSelectCategoriesClick: async function () {
      let _this = this;
      _this.categoryListDialogVisible = true;
      if (_this.categoryList.length === 0) {
        _this.categoryList = await this.getSelectCategories();
      }
      let checkKeys = _this.product_category.map(function (item) {
        return item.value;
      });
      _this.$nextTick(function () {
        this.$refs['selectCategoryTree'].setCheckedKeys(checkKeys);
      });
    },
    selectCategoryChange: function (node, ele) {
    },
    handleSelectCategoryConfirm: function () {
      let categoryTreeRef = this.$refs['selectCategoryTree'];
      let checkedKeys = categoryTreeRef.getCheckedKeys();
      if (checkedKeys.length === 0) {
        this.error('Please tick at least one category.');
        return;
      }
      let product_category = [];
      for (let key of checkedKeys) {
        let node = categoryTreeRef.getNode(key);
        let nodeNameArr = [];
        while (node.parent !== null) {
          nodeNameArr.push(node.data.name);
          node = node.parent;
        }
        product_category.push({value: key, label: nodeNameArr.reverse().join(' >> ')});
      }
      this.product_category = product_category;
      this.categoryListDialogVisible = false;
    },
    handleCategoryMinusClick: function (index) {
      this.product_category.splice(index, 1)
    },
    // endregion select categories
    // brand start
    brandQuerySearch: function (query, cb) {
      let _this = this;
      clearTimeout(this.brandQueryTimeOut);
      _this.brandQueryTimeOut = setTimeout(function () {
        let r_url = 'index.php?route=customerpartner/autocomplete/manufacturer';
        if (query) r_url += '&filter_name=' + query;
        _this.axios
          .get(r_url)
          .then(function (res) {
            cb(res.data);
          })
      }, 50);
    },
    brandCategorySelect: function (item) {
      this.formModel.manufacturer_id = item.manufacturer_id;
    },
    // brand end
    // product description start
    onProductDescriptionConfirm: function (files) {
      let _this = this;
      let ele = _this.descriptionSummerNote;
      let baseList = document.getElementsByTagName('base');
      let baseHref = '';
      if (baseList.length > 0) baseHref = baseList[0].href;
      files.map(function (item) {
        if (item.type === 'image') {
          ele.summernote('focus');
          ele.summernote(
            'insertImage',
            item['url'].toLowerCase().indexOf('http') === 0
              ? item['url']
              : baseHref + item['url']
          );
        }
      });
      _this.$refs['upload_input_product_description'].closeDialog();
    },
    // product description end
    // Returns & Notice start
    onReturnsAndNoticeConfirm: function (files) {
      let _this = this;
      let ele = _this.returnsAndNoticeSummerNote;
      let baseList = document.getElementsByTagName('base');
      let baseHref = '';
      if (baseList.length > 0) baseHref = baseList[0].href;
      files.map(function (item) {
        if (item.type === 'image') {
          ele.summernote('focus');
          ele.summernote(
            'insertImage',
            item['url'].toLowerCase().indexOf('http') === 0
              ? item['url']
              : baseHref + item['url']
          );
        }
      });
      _this.$refs['upload_input_returns_and_notice'].closeDialog();
    },
    // Returns & Notice end
    // region product image
    handlerOnChangeProductImage: function () {
      let _this = this;
      let tempList = _this.$refs['upload_input_2'].getFileList();
      let fileList = [];
      let errorFormat = [];
      for (let i in tempList) {
        let item = tempList[i];
        if (item.type.indexOf('image') !== 0) {
          errorFormat.push(item.name);
        } else {
          fileList.push(item);
        }
      }
      errorFormat.length > 0 && this.$notify.error({
        title: 'Error',
        dangerouslyUseHTMLString: true,
        message: "File format error: <br>" + errorFormat.join('<br>')
      });


      let fromIndex = -1; // 用于自动写入sort_order
      let maxSortOrder = 0;
      fileList = fileList.map(function (item, key) {
        // 保证新的图片修改后不会覆盖原有的内容
        if (item.hasOwnProperty('sort_order')) {
          fromIndex = key;
          maxSortOrder = Math.max(parseInt(maxSortOrder), parseInt(item['sort_order']));
          return item;
        }
        if (!item.hasOwnProperty('isMainImage')) item.isMainImage = false;
        item.sort_order = null;
        // 如果刚开始没有主图
        // 则默认所选择的第一张图为主图
        if (!_this.formModel.image) {
          item.isMainImage = true;
          _this.formModel.image = item['orig_url'];
        }
        return item;
      });
      _this.fileList = fileList.map(function (item, key) {
        if (key > parseInt(fromIndex)) {
          maxSortOrder += 1;
          item['sort_order'] = maxSortOrder;
        }
        return item;
      })
    },
    handlerProductImageCheckboxChange: function (file) {
      let _this = this;
      let fileList = _this.$refs['upload_input_2'].getFileList();
      // checkbox  true => false
      if (file['isMainImage'] === false) {
        file['isMainImage'] = true;
        this.error('The image has already been selected as Main image.');
        return;
      }
      _this.fileList = fileList.map(function (item) {
        // 检测是否为同一个文件
        if (item['orig_url'] !== file['orig_url']) {
          if (item['isMainImage'] === true) {
            item['isMainImage'] = false;
            item['sort_order'] = 99;
          }
        } else {
          item['isMainImage'] = true;
          // 选择为主图的同时 sort order 变为1
          item['sort_order'] = 1;
        }
        return item;
      });
    },
    sortFileList: function (fileList) {
      let sort_exist_file = [];
      let null_sort_file = [];
      fileList.forEach(function (item) {
        if (item['sort_order'] === null || item['sort_order'] === 0) {
          null_sort_file.push(item);
        } else {
          sort_exist_file.push(item);
        }
      });
      sort_exist_file = sort_exist_file.sort(function (item1, item2) {
        return item1['sort_order'] - item2['sort_order'];
      });
      return sort_exist_file.concat(null_sort_file);
    },
    beforeProductImageDelete: function (file) {
      let _this = this;
      if (file.isMainImage === true) {
        return _this.$confirm('Are you sure you want to delete this image?', 'Notice', {
          confirmButtonText: 'Confirm',
          cancelButtonText: 'Cancel',
          type: 'warning',
          center: true
        })
      }
      return true;
    },
    refreshUploadInput2: function () {
      this.$refs['upload_input_2'].refreshFileList();
    },
    checkFileIsNotEmpty: function (file) {
      if (!file.hasOwnProperty('is_blank')) return true;
      return file['is_blank'] === 0;
    },
    // endregion product image
    // region color
    colorQuerySearch: function (query, cb) {
      let _this = this;
      clearTimeout(this.colorQueryTimeOut);
      _this.colorQueryTimeOut = setTimeout(function () {
        let httpUrl = ' index.php?route=account/customerpartner/productoptions/autocomplete';
        if (query) httpUrl += '&filter_name=' + query;
        _this.axios
          .get(httpUrl)
          .then(function (res) {
            if (res.data) {
              _this.options = res.data
            } else {
              _this.options = []
            }
            // cb(res.data);
          })
      }, 50);
    },
    colorSelect: function (color) {
      this.options.forEach(v => {
        if (v.color == color) {
          this.formModel.color = v.id
        }
      })
      // this.formModel.color = item['id'];
    },
    // endregion
    // region color and associated products
    getAssociatedProducts: function (val) {
      let _this = this;
      _this.associateProductTableData = [];
      clearTimeout(_this.associatedProductsDialogInputTimeout);
      _this.associatedProductsDialogInputTimeout = setTimeout(function () {
        let httpUrl = 'index.php?route=pro/product/getAssociatedProducts';
        let data = {
          filter_search: val || '',
          product_id: [],
          page: _this.associateProductCurrentPage,
          page_size: _this.associateProductCurrentPageSize
        };
        if (_this.formModel.product_id) {
          data.product_id.push(_this.formModel.product_id);
        }
        _this.axios.post(httpUrl, data)
          .then(function (res) {
            _this.associateProductTableData = res['data']['data'] || [];
            _this.associateProductTotal = parseInt(res['data']['total']);
            _this.associateProductCurrentPage = parseInt(res['data']['page']);
          }).catch(function (e) {
          console.error(e);
        })
      }, 500);
    },
    handlerAssociateProductsClick: function () {
      let _this = this;
      // 刚开始没有input，手动触发
      if (!_this.associatedProductsDialogInputValue && _this.associateProductTableData.length === 0) {
        _this.getAssociatedProducts();
      }
      _this.associatedProductsDialogVisible = true;
    },
    handleAssociateProductSelectionChange: function (select) {
      // 临时存储选择项
      this.temp_product_color_list = select;
    },
    handlerAssociatedProductsSubmitClick: function () {
      let _this = this;
      if (_this.temp_product_color_list.length === 0) {
        _this.error('Please tick at least one product.');
        return;
      }
      _this.associatedProductsDialogVisible = false;
      let temp_product_color_list = _this.temp_product_color_list.filter(function (item) {
        let flag = true;
        _this.product_color_list.map(function (val) {
          if (parseInt(val['product_id']) === parseInt(item['product_id'])) {
            flag = false;
          }
        });
        return flag;
      });
      _this.product_color_list = _this.product_color_list.concat(temp_product_color_list);
    },
    handlerProductColorDelete: function (index) {
      this.product_color_list.splice(index, 1)
    },
    // endregion
    // region combo
    getComboProducts: function (val) {
      let _this = this;
      _this.addSubItemTableData = [];
      clearTimeout(_this.addSubItemDialogInputTimeout);
      _this.addSubItemDialogInputTimeout = setTimeout(function () {
        let httpUrl = 'index.php?route=pro/product/getComboProducts';
        let data = {
          filter_search: val || '',
          product_id: [],
          page: _this.addSubItemCurrentPage,
          page_size: _this.addSubItemCurrentPageSize
        };
        if (_this.formModel.product_id) {
          data.product_id.push(_this.formModel.product_id);
        }
        _this.axios.post(httpUrl, data)
          .then(function (res) {
            _this.addSubItemTableData = res['data']['data'] || [];
            _this.addSubItemTotal = parseInt(res['data']['total']);
            _this.addSubItemCurrentPage = parseInt(res['data']['page']);
          }).catch(function (e) {
          console.error(e);
        })
      }, 500);
    },
    handlerAddSubItemsClick: function () {
      if (!this.addSubItemDialogInputValue && this.addSubItemTableData.length === 0) {
        this.getComboProducts();
      }
      this.addSubItemDialogVisible = true;
    },
    handlerAddItemSubmitClick: function () {
      let _this = this;
      if (_this.temp_sub_item_list.length === 0) {
        _this.error('Please tick at least one product.');
        return;
      }
      _this.addSubItemDialogVisible = false;
      let temp_sub_item_list = _this.temp_sub_item_list.filter(function (item) {
        let flag = true;
        _this.formModel.combo.map(function (val) {
          if (parseInt(val['product_id']) === parseInt(item['product_id'])) {
            flag = false;
          }
        });
        return flag;
      });
      temp_sub_item_list.map(function (item) {
        // 默认数量为1 且最小只能为1
        item['quantity'] = 1;
        // 实现浅拷贝
        _this.formModel.combo.push(JSON.parse(JSON.stringify(item)));
      });
    },
    // endregion combo
    // region material images
    handlerOnConfirmMaterialImage: function () {
      let _this = this;
      let tempList = _this.$refs['upload_input_3'].getFileList();
      let fileList = [];
      let errorFormat = [];
      for (let i in tempList) {
        let item = tempList[i];
        if (item.type.indexOf('image') !== 0) {
          errorFormat.push(item.name);
        } else {
          fileList.push(item);
        }
      }
      errorFormat.length > 0 && this.$notify.error({
        title: 'Error',
        dangerouslyUseHTMLString: true,
        message: "File format error: <br>" + errorFormat.join('<br>')
      });
      _this.material_images = fileList;
    },
    // endregion material images
    // material manuals
    handlerOnConfirmMaterialManual: function () {
      let _this = this;
      _this.material_manuals = _this.$refs['upload_input_4'].getFileList();
    },
    // end material manuals
    handlerOnConfirmMaterialVideo: function () {
      let _this = this;
      _this.material_video = _this.$refs['upload_input_5'].getFileList();
    },
    handlerBeforeConfirmMaterialVideo: function (list) {
      let newList = list.filter(function (item) {
        return (item.name.indexOf('.txt') !== -1 || item.name.indexOf('.text') !== -1)
      });
      if (newList.length !== list.length) {
        this.error('Material Video must be in TXT format!');
      }
      return newList;
    },
    // end material manuals
    // region api请求
    getSelectCategories: async function () {
      let ret = await this.axios.post('index.php?route=pro/product/getCates');
      if (!ret || !(ret.status === 200)) {
        console.error(ret);
      }
      return ret.data;
    },
    // 获取用户可选product group
    getProductGroupList: function () {
      return this.axios.post('index.php?route=pro/product/getCustomerGroup', {product_id: this.formModel.product_id});
    },
    // 获取商品信息
    getProductInfo: function () {
      return this.axios.post('index.php?route=pro/product/getProductInfo', {product_id: this.formModel.product_id});
    },
    // endregion

    // 初始化Summer note
    initSummerNote: function () {
      let _this = this;
      let ele = this.descriptionSummerNote = $('.app_form_product_description');
      summernote.init(ele, {
        callbacks: {
          onChange: function () {
            _this.formModel.product_description[1]['description']
              = _this.descriptionSummerNote.summernote('code');
          }
        },
        buttons: {
          image: function () {
            let ui = $.summernote.ui;
            let button = ui.button({
              contents: '<i class="note-icon-picture" />',
              tooltip: $.summernote.lang[$.summernote.options.lang].image.image,
              click: function () {
                _this.$refs['upload_input_product_description'].openDialog();
              }
            });
            return button.render();
          }
        }
      });
      ele.summernote('code', this.productDescription);

      let elereturns = this.returnsAndNoticeSummerNote = $('.app_form_returns_and_notice');
      summernote.init(elereturns, {
        callbacks: {
          onChange: function () {
            _this.formModel.product_description[1]['returns_and_notice']
              = _this.returnsAndNoticeSummerNote.summernote('code');
          }
        },
        buttons: {
          image: function () {
            let ui = $.summernote.ui;
            let button = ui.button({
              contents: '<i class="note-icon-picture" />',
              tooltip: $.summernote.lang[$.summernote.options.lang].image.image,
              click: function () {
                _this.$refs['upload_input_returns_and_notice'].openDialog();
              }
            });
            return button.render();
          }
        }
      });
      elereturns.summernote('code', this.returnsAndNotice);
    }
    ,
    // 提示信息
    error(message, title, duration) {
      message = message || '';
      title = title || 'Error';
      if (duration === undefined) {
        duration = 4500;
      }
      this.$notify.error({
        title: title,
        message: message,
        duration: duration
      })
    }
  }
});
window.vm_4396 = vm_4396;