// add product new
let vm_6446 = new Vue({
  el: '#app_form',
  delimiters: ['${', '}'], //更换定界符，和twig冲突，中文化需要使用twig，改vue定界符，成本最小
  data: function () {
    let _this = this;
    // 校验mpn重复
    let ValidateMpn = function (rule, value, callback) {
      value = value.toUpperCase().trim();
      if (value !== _this.formModel.mpn) {
        _this.formModel.mpn = value;
        // 手动触发校验
        setTimeout(function () {
          _this.$refs['app_form'].validateField(['mpn']);
        }, 500);
        return;
      }
      var error = _this.mpnValid(value, _this);
      if (error) {
        return callback(new Error(error));
      }
      // 处于修改商品时候 sku mpn不需要联动
      if (!value) return err;
      _this.axios.get('index.php?route=pro/product/checkMpnValid&mpn=' + value)
        .then(function (res) {
          if (res['status'] !== 200) {
            console.error(res);
            return;
          }
          let data = res['data'] || { "code": 0, "msg": commonError.mpnRepeatErr };
          if (data['code'] === 0) {
            return callback(new Error(data['msg'])); //mpn重复提示
          }
        })
        .catch(function (e) {
          console.error(e);
        })
    };
    let ValidateUpc = function (rule, value, callback) {
      var error = _this.upcValid(value, _this);
      if (error) {
        return callback(new Error(error));
      }
    };
    // 校验产品名称
    let validateProductName = function (rule, value, callback) {
      var error = _this.nameValid(value, _this);
      if (error) {
        return callback(new Error(error));
      }
    };
    // 校验Product size
    let validateProdSize = function (rule, value, callback) {
      var error = _this.productSizeValid(value, _this);
      if (error) {
        return callback(new Error(error));
      }
    };
    // 校验组装尺寸
    let validateProductAssembleFactory = function (type) {
      return function (rule, value, callback) {
        var error = _this.productAssembleSizeValid(_this.formModel.dimensions, _this, type);
        if (error) {
          return callback(new Error(error));
        }
      }
    };
    // 校验combo品不能为空
    let validatorCombo = function (rule, value, callback) {
      var error = _this.comboValid(_this);
      if (error) {
        return callback(new Error(error));
      }
    };

    let validatorLTL = function (rule, value, callback) {
      var error = _this.validatorNumber(value, _this);
      if (error) {
        return callback(new Error(error));
      }
    }
    // 设置产品价格时，需要判断是否触发纯物流判断标准，[（运费-旺季附加费-危险品附加费/（运费-旺季附加费-危险品附加费+货值）>0.4]，
    let validatorPrice = function (rule, value, callback) {
      // 首先得判断是否存在运费
      let price = Number(_this.formModel.price);
      let freight = Number(_this.freightData.freight);
      let dangerFee = Number(_this.freightData.dangerFee);
      let peakSeasonFee = Number(_this.freightData.peakSeasonFee);
      let that = _this;
      var error = _this.priceValid(value, _this);
      if (error) {
        return callback(new Error(error));
      }
      let afterCalculateFreight = freight - dangerFee - peakSeasonFee;
      if (afterCalculateFreight && _this.countryId === 223 && _this.accountType !== 1) {
        if (afterCalculateFreight / (afterCalculateFreight + price) > 0.4) {
          let options = {
            content: _this.showhowCwfContent,
            btn: [commonInfo.yes, commonInfo.no],
            title: commonInfo.confirm,
            area: ['480px', 'auto']
          };
          layerOris.confirmLayer(options, function (index) {
            layer.close(index);
          }, null, function (index) {
            layer.close(index);
            // 清空价格
            that.formModel.price = undefined;
          });
        }
      }
    }

    return {
      editType: window.EDITTYPE || null,
      countryId: window.COUNTRY_ID || null, // 当前国别id
      audit_id: window.AUDIT_ID || 0, // 审核ID: 用于判断是否是审核详情页面
      auditRejectRemark: '',
      isReadOnly: window.IS_READ_ONLY || 0,
      showhowCwfContent: window.SHOWCWFNOTICE ? commonError.lowPriceWithCwfNote : commonError.lowPriceWithoutCwfNote,
      accountType: window.ACCOUNTTYPE || null,
      isOnsiteSeller: (window.ONSITE_SELLER_INFO.is_onsite_seller || 0) == 1,  // 是否是onsite Seller
      isLtlPrice: (window.ONSITE_SELLER_INFO.ltl_quote || 0) == 1,  // 是否维护LTL报价（用于onsite seller）
      onsiteFreightReturnCode: null,  // onsite seller 计算运费时的return_code(用于判断onsite seller是否允许提交审核)
      previewLink: '', //预览链接
      showEmptytext: 'No Data',
      dim: null, // 平台DIM
      limitWeight: null, // 最大计重
      separateEnquiry: false, // 是否单独询价
      isShowLtlWindow: false, // 是否弹出过LTL超大件弹框
      isLtlWindow: null, // 弹出LTL超大件弹框之后的选择标记
      isDraft: '2', //是否是提交草稿
      bar_active: 0, // 步骤条标志
      status: null, //  产品状态：-1：待上架；0：下架；1：上架；（如果是新增前端默认是null）
      notice_type: null, // 编辑返回notice_type，1 保存草稿 、2 代表默认提交审核
      stepStatus: [ // active代表是否激活
        { active: true, title: window.originStepStatus.firstStep },
        { active: false, title: window.originStepStatus.secondStep },
        { active: false, title: window.originStepStatus.thirdStep },
        { active: false, title: window.originStepStatus.forthStep },
        { active: false, title: window.originStepStatus.fifthStep },
        { active: false, title: window.originStepStatus.sixStepCreate },
      ],
      SellablePlatform: ['Amazon', 'Wayfair', 'Walmart', 'Shopify', 'eBay', 'Overstock', 'Home Depot', "Lowe's", 'Wish', 'Newegg', 'AliExpress'], // 平台默认的不可售卖平台选项
      inputPlatform: '', // 用户自定义不可售卖平台输入内容
      isShowInputPlatform: false,// 是否展示平台输入框
      turn: {},
      warranty: {},
      choosedCategList: [], //曾经选择过的类目列表
      allCategoryList: [], // 所有分类类目
      secondCategoryBox: [], // 二级分类类目
      thirdCategoryBox: [], // 三级分类类目
      choosedCateg: { // 选中的分类类目
        one: {},
        second: {},
        third: {}
      },
      isLastCategory: false, //记录是否是最后一级分类
      selectedCateg: null, //选中的曾经选择过的类目
      colorList: [], // 颜色列表
      materialList: [], // 材料列表
      returnWarranty: {
        isAccept: null, // New退返品政策是否接受退货
        days: null,
        percent: null,
        isAcceptDay: null,
        aDays: null,
        aMonth: null,
        checked: false,
        content: []
      },
      freightData: {
        dropShipPackageFee: '',
        freight: '',
        pickUpPackageFee: '',
        dangerFee: '',
        peakSeasonFee: ''
      },
      formModel: {
        product_id: window.PRODUCT_ID || null, // 商品id 这个参数必须初始化,多数api请求依赖
        part_flag: '0',
        combo_flag: '0',
        // 基本属性
        product_group_ids: [], // group ids
        product_category: [], // 商品类别
        buyer_flag: 1, // 商品是否允许购买
        non_sellable_on: [], //不可单独售卖平台
        mpn: null, // MPN
        upc: '', // UPC 
        sku: null, // item code
        name: null, // New product title
        product_size: null, // New product product_size
        // need_install: null, // New 是否需要安装 1：是 0：否 #33309 去除
        original_product: 0, // New 是否为原创产品 1：是 0：否
        price: undefined, // New 价格current price
        price_display: '0', // New 是否展示价格
        description: null, // New 商品描述
        product_image: [], // 商品图片 [ {image:'',sort_order:''},... ]
        image: null, // 商品主图片
        // ----------------产品属性------------------------------
        product_type: null, // 产品类型 [1:General item 2:Combo item 3:Part item]
        length: undefined, // 长度
        width: undefined, // 宽度
        height: undefined, // 高度
        weight: undefined, // 质量
        is_ltl: undefined, // 是否为LTL
        color: null, // 颜色 color
        material: null, // 材质 material
        product_associated: [], // 关联产品
        combo: [], // combo子产品
        // [{mpn:'',quantity:'',length:'',width:'',height:'',weight:'',...}]
        // ---------------------Materials----------------------------
        material_images: [],
        certification_documents: [],
        material_manuals: [],
        material_video: [],
        //原创证明文件
        original_design: [],
        // 兼容之前写法
        product_link_tab: 1,
        product_attribute_tab: 1,
        product_discount_tab: 1,
        product_special_tab: 1,
        // 表征表单来源
        fromNew: 1,

        // #33309 新增
        // 产品尺寸
        dimensions: {
          length: undefined, // 长度
          width: undefined, // 宽度
          height: undefined, // 高度
          weight: undefined, // 质量
        },
        origin_place_code: null, // 原产地
        is_customize: 0, //定制化
        filler: null, // 填充物
      },
      information_custom_map: [], // 产品信息自定义字段
      dimensions_custom_map: [], // 包装信息自定义字段
      // 尺寸 not include checkbox控制
      dimensionsCheck: {
        length: false, // 长度
        width: false, // 宽度
        height: false, // 高度
        weight: false, // 质量
      },
      formRules: {
        mpn: [
          { required: true, message: errEmpty.mpnErrEmpty },
          { validator: ValidateMpn, trigger: 'blur' }
        ],
        upc: [{ validator: ValidateUpc, trigger: 'blur' }],
        name: [{ required: true, message: errEmpty.proTitleErrEmpty }, { validator: validateProductName }],
        product_size: [{ validator: validateProdSize }],
        combo: [{ validator: validatorCombo }],
        length: [{ validator: validatorLTL, trigger: 'blur' }],
        width: [{ validator: validatorLTL, trigger: 'blur' }],
        height: [{ validator: validatorLTL, trigger: 'blur' }],
        weight: [{ validator: validatorLTL, trigger: 'blur' }],
        price: [{ validator: validatorPrice, trigger: 'blur' }],
        color: [{ required: true, message: errEmpty.colorErrEmpty }],
        material: [{ required: true, message: errEmpty.materialErrEmpty }],
        product_type: [{ required: true, message: 'Please select.' }],
        price_display: [{ required: true, message: 'Please select.' }],
        //#33309 新增
        dimensions: {
          length: [{ validator: validateProductAssembleFactory('length'), trigger: 'blur' }],
          width: [{ validator: validateProductAssembleFactory('width'), trigger: 'blur' }],
          height: [{ validator: validateProductAssembleFactory('height'), trigger: 'blur' }],
          weight: [{ validator: validateProductAssembleFactory('weight'), trigger: 'blur' }],
        },
        // filler: [{ required: true, message: 'Please select.' }],
        // origin_place_code: [{ required: true, message: 'Please select.' }],
      },
      // product group
      product_group_list: [],
      // end product group
      // categories
      product_category: [], // [{label:'',value:''},...]
      categoryListDialogVisible: false,
      categoryList: [],
      categoryTreeProps: {
        children: 'son',
        label: 'name'
      },
      // product description start
      descriptionSummerNote: null, // editor实例
      // product description end
      // product images
      fileList: [],
      // end product images
      // product type
      product_type_list: [
        { value: 1, title: window.productTypeList.generalItem },
        { value: 2, title: window.productTypeList.comboItem },
        { value: 3, title: window.productTypeList.replacementItem }
      ],
      // end product type
      // color
      colorName: null,
      product_associated: [],
      temp_product_associated: [],
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
      material_certs: [],
      //原创证明文件
      original_design: [],
      // axios 实例
      axios: null,
      // loading 实例  防止用户多次点击
      loading: null,
      // #33309 新增
      origin_place_list: [], // 原产地列表
      cert_type_list: [], // 产品认真属性列表
      // #49569 新增
      baseInfoTooltip: window.nextStepText.tooltip,
      jpOverSizeIsValid: false,
      firstJpSizeValid: false,
      overConfirmLayer: false, // 是否存在确认弹出框
    };
  },
  computed: {
    // #38548 新增
    // @description 仅能在商品状态为待上架时修改子产品的组成和数量配比；当商品状态不是待上架时，添加子产品按钮、子产品行上的数量输入框和删除按钮禁用
    disableComboEdit: function () {
      return this.status === 0 || this.status === 1;
    },
    formName: function () {
      return this.formModel.name;
    },
    productDescription: function () {
      return this.formModel['description'];
    },
    formProductType: function () {
      return this.formModel.product_type;
    },
    isShowProductGroup: function () {
      return parseInt(this.formModel.buyer_flag) === 1;
    },
    isNextStep: function () {
      return this.validNextStep(true);
    },
    formModelCombo: function () { return this.formModel.combo },
    formLength: function () {
      return this.formModel.length;
    },
    formWidth: function () {
      return this.formModel.width;
    },
    formHeight: function () {
      return this.formModel.height;
    },
    formWeight: function () {
      return this.formModel.weight;
    },
  },
  watch: {
    isShowProductGroup: {
      immediate: true,
      handler: function (res) {
        if (!res) {
          this.formModel.product_group_ids = [];
        }
      }
    },
    formName: {
      immediate: true,
      handler: function (formName) {
        if (!formName) return;
        var resultName = toolString.calcStringLength(formName, 200);
        this.formModel.name = resultName.fullStr;
      }
    },
    inputPlatform: {
      immediate: true,
      handler: function (platform) {
        if (!platform) return;
        var resultName = toolString.calcStringLength(platform, 30);
        this.inputPlatform = resultName.fullStr;
      }
    },
    fileList: {
      deep: true,
      handler: function (fileList) {
        let _this = this;
        let hasMainImage = false;
        _this.formModel.image = null;
        _this.formModel.product_image = [];
        fileList.map(function (item) {
          let file_url = item['orig_url'] ? item['orig_url'].replace(/^image\//g, '') : null;
          if (item['isMainImage']) {
            _this.formModel.image = file_url;
            hasMainImage = true
          }
          _this.formModel.product_image.push({
            image: file_url,
            sort_order: parseInt(item['sort_order'])
          });
        })
        // 如果都没有主图，默认第一个是主图
        if (!hasMainImage && fileList.length > 0) {
          _this.formModel.image = fileList[0]['orig_url'] ? fileList[0]['orig_url'].replace(/^image\//g, '') : null;
          fileList[0]['isMainImage'] = true;
          fileList[0]['sort_order'] = 0;
          _this.formModel.product_image[0]['sort_order'] = 0;
        }
      }
    },
    colorName: function (val) {
      if (val === undefined || !val) {
        this.formModel.color = '';
      }
    },
    associatedProductsDialogInputValue: function (val) {
      this.getAssociatedProducts(val);
    },
    product_associated: {
      immediate: true,
      handler: function(list) {
        this.formModel.product_associated = list.map(function(item) {
          return item['product_id'];
        })
      }
    },
    addSubItemDialogInputValue: function (val) {
      this.getComboProducts(val);
    },
    // #33309 新增 认证文件修改处理
    material_certs: {
      immediate: true,
      handler: function (fileList) {
        let _this = this;
        _this.formModel.certification_documents = [];
        fileList.map(function (item) {
          _this.formModel.certification_documents.push({
            name: item['name'],
            url: item['orig_url'],
            type_id: item['type_id']
          });
        })
      }
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
      deep: true,
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
    },
    //原创证明文件start
    original_design: {
      immediate: true,
      handler: function (images) {
        let _this = this;
        _this.formModel.original_design = [];
        images.map(function (item) {
          let info = {
            url: item['orig_url'].replace(/^(productPackage|image)\//g, ''),
            name: item['name'],
            file_id: 0,
            m_id: 0
          };
          if (item.hasOwnProperty('file_id')) info.file_id = item.file_id;
          if (item.hasOwnProperty('m_id')) info.m_id = item.m_id;
          _this.formModel.original_design.push(info);
        })
      }
    },
    //原创证明文件end
    formModelCombo: {
      deep: true,
      immediate: true,
      handler: function (items) {
        let _this = this;
        if (_this.formModel.product_type == 2) {
          // 校验
          if (_this.comboValid(_this)) {
            _this.clearFreight();
          } else {
            // combo品不处理是否是ltl,但是要校验是否超重, 计算规则是：每个子产品的总重量不超过平台维护
            if (_this.calcLtlMaxWeight(true)) {
              // 超重，停止创建
              _this.overWeightLayer();
              _this.clearFreight();
            } else {
              // 计算运费
              _this.calcFreight();
            }
          }
        }
      }
    },
    formLength: {
      handler: function (newValue, oldValue) {
        // 跳过编辑产品复制校验
        if (newValue == oldValue || (this.formModel.product_id > 0 && oldValue === undefined)) {
          return
        }
        this.ltlValid(this);
      }
    },
    formWidth: {
      handler: function (newValue, oldValue) {
        if (newValue == oldValue || (this.formModel.product_id > 0 && oldValue === undefined)) {
          return
        }
        this.ltlValid(this);
      }
    },
    formHeight: {
      handler: function (newValue, oldValue) {
        if (newValue == oldValue || (this.formModel.product_id > 0 && oldValue === undefined)) {
          return
        }
        this.ltlValid(this);
      }
    },
    formWeight: {
      handler: function (newValue, oldValue) {
        if (newValue == oldValue || (this.formModel.product_id > 0 && oldValue === undefined)) {
          return
        }
        this.ltlValid(this);
      }
    }
  },
  created: function () {
    let _this = this;
    this.axios = axios.create({});
    layer.load();
    // api请求
    axios.all([_this.getProductGroupList(), _this.getProductInfo(), _this.getAllCategory(), _this.getOnceSelectedCategory(),
    _this.getColorAndMaterial(), _this.getReturnWarranty(), _this.getOriginPlace(), _this.getCertTypes()])
      .then(axios.spread(function (res1, res2, res3, res4, res5, res6, res7, res8) {
        layer.closeAll();
        setTimeout(function () {
          // 初始化summerNote
          _this.initSummerNote();
        }, 0);
        _this.product_group_list = res1['data'];
        // 保护, 请求报错
        if (res2['data']['code'] != 200) {
          return layerOris.confirmLayer({ title: commonInfo.confirm, content: res2['data']['msg'], btn: [commonInfo.ok] });
        }
        // product info
        let productInfo = res2['data']['data'];
        _this.allCategoryList = res3['data'];
        if (res4['data']['code'] == 200) {
          _this.choosedCategList = res4['data']['data']['lists'];
        }
        if (res5['data']['code'] == 200) {
          _this.colorList = res5['data']['data']['color_options'];
          _this.materialList = res5['data']['data']['material_options'];
          _this.dim = res5['data']['data']['dim_and_limit']['dim'];
          _this.limitWeight = res5['data']['data']['dim_and_limit']['limit_weight'];
          _this.separateEnquiry = res5['data']['data']['dim_and_limit']['separate_enquiry'];
        }
        if (res6) {
          _this.showReturnWarranty(res6['data']['data'], _this);
        } else if (_this.audit_id > 0) {
          _this.showReturnWarranty(productInfo['return_warranty'], _this);
        }
        // 原产地信息
        if (res7['data']['code'] == 200) {
          _this.origin_place_list = res7['data']['data']['origin_places'];
        }
        // 产品认证信息
        if (res8['data']['code'] == 200) {
          _this.cert_type_list = res8['data']['data']['certification_types'];
        }
        _this.auditRejectRemark = (productInfo && productInfo.hasOwnProperty('auditRejectRemark')) ? productInfo['auditRejectRemark'] : '';
        if (!_this.formModel.product_id || (Array.isArray(productInfo) && productInfo.length === 0)) {
          return;
        }
        // -------以下是编辑初始化数据 Start-----------
        _this.stepStatus = [ // active代表是否激活
          { active: true, title: window.originStepStatus.firstStep },
          { active: true, title: window.originStepStatus.secondStep },
          { active: true, title: window.originStepStatus.thirdStep },
          { active: true, title: window.originStepStatus.forthStep },
          { active: true, title: window.originStepStatus.fifthStep },
          { active: false, title: window.originStepStatus.sixStepEdit },
        ];
        _this.status = productInfo['status']; // 产品状态
        _this.formModel.name = productInfo['name'];
        _this.formModel.description = productInfo['description'];
        _this.formModel.material = _this.checkOptionNotExist(productInfo['material'], _this.materialList, "option_value_id")
          ? productInfo['material'] : '';
        _this.formModel.product_size = productInfo['product_size'];
        _this.formModel.is_ltl = productInfo['is_ltl'];
        _this.formModel.price = productInfo['price'];
        _this.formModel.original_product = productInfo['original_product'];
        _this.formModel.price_display = productInfo['price_display'] + '';
        // 不可售卖平台数据重组
        _this.formModel.non_sellable_on = productInfo['non_sellable_on'] ? productInfo['non_sellable_on'].split(',') : [];
        _this.SellablePlatform = Array.from(new Set(_this.SellablePlatform.concat(_this.formModel.non_sellable_on)));
        if (productInfo['product_group_ids']) {
          _this.formModel.product_group_ids = productInfo['product_group_ids'].split(',');
        }
        // product category start
        _this.product_category = productInfo['product_category'] || [];
        var selectHas = false; // 判断曾经选择过的类目中有没有这个分类
        _this.choosedCategList.forEach(function (item) {
          if (item.category_ids === productInfo['product_category']['category_ids']) {
            selectHas = true;
          }
        })
        if (selectHas) {
          _this.selectedCateg = productInfo['product_category']['category_ids'];
        }
        _this.changedCateg(productInfo['product_category']['category_ids']);
        // product category end
        // buyer flag
        _this.formModel.buyer_flag = parseInt(productInfo['buyer_flag']);
        // mpn
        _this.formModel.mpn = productInfo['mpn'];
        // sku
        _this.formModel.sku = productInfo['sku'];
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
        _this.fileList = images;
        // color
        _this.colorName = _this.checkOptionNotExist(productInfo['color'], _this.colorList, "option_value_id")
          ? productInfo['color'] : '';
        _this.formModel.color = productInfo['color'];
        _this.product_associated = productInfo['product_associated'] || [];

        // material
        _this.material_images = productInfo['material_images'] || [];
        _this.material_manuals = productInfo['material_manuals'] || [];
        _this.material_certs = productInfo['certification_documents'] || [];
        _this.material_video = productInfo['material_video'] || [];
        //原创证明文件
        _this.original_design = productInfo['original_design'] || [];

        // #33309 新增字段
        _this.formModel.filler = _this.checkOptionNotExist(productInfo['filler'], _this.materialList, "option_value_id")
          ? productInfo['filler'] : '';
        _this.formModel.upc = productInfo['upc'] || '';
        _this.formModel.is_customize = productInfo['is_customize'];
        _this.formModel.origin_place_code = _this.checkOptionNotExist(productInfo['origin_place_code'],
          _this.origin_place_list, 'code') ? productInfo['origin_place_code'] : '';

        for (let k in _this.dimensionsCheck) {
          _this.dimensionsCheck[k] = Number(productInfo[`assemble_${k}`]) < 0;
          if (Number(productInfo[`assemble_${k}`]) > 0) {
            _this.formModel.dimensions[k] = productInfo[`assemble_${k}`];
          }
        }

        // #33309 新增，如果选项内容被删除默认值，显示为空


        // 初始化自定义字段
        _this.information_custom_map = productInfo['information_custom_field'] || [];
        _this.dimensions_custom_map = productInfo['dimensions_custom_field'] || [];
        _this.$refs['infoCustomFields'].setDefaultFields(_this.information_custom_map);
        _this.$refs['infoCustomFields'].setDisableBtn(_this.isReadOnly > 0);
        _this.$refs['dimensCustomeFields'].setDefaultFields(_this.dimensions_custom_map);
        _this.$refs['dimensCustomeFields'].setDisableBtn(_this.isReadOnly > 0);

        // -------以下若是复制产品则不需要复制-----------
        if (_this.checkIsCopyProduct(_this)) {
          return;
        }
        // product type 判定
        let part_flag = parseInt(productInfo['part_flag']);
        let combo_flag = parseInt(productInfo['combo_flag']);
        if (part_flag === 1) {
          _this.formModel.product_type = 3;
        } else if (combo_flag === 1) {
          _this.formModel.product_type = 2;
        } else {
          _this.formModel.product_type = 1;
        }
        _this.changeProductType(false);
        // length width height weight
        _this.formModel.length = productInfo['length'];
        _this.formModel.width = productInfo['width'];
        _this.formModel.height = productInfo['height'];
        _this.formModel.weight = productInfo['weight'];
        // combo
        _this.formModel.combo = productInfo['combo'] || [];

        // -------以上是编辑初始化数据 End-----------
        // 计算运费 combo品的计算运费在监听里，这里过滤掉
        if (_this.formModel.product_type != 2) {
          _this.calcFreight();
        }

        // #49459 日本包装尺寸校验新增
        _this.productSizeMaxLimitValid();
      }));
  },
  methods: {
    // #33309 新增，判断选项中的值是否被删除
    checkOptionNotExist(value, options, valName) {
      return options.map(item => item[valName]).includes(value);
    },
    // 判断是否是复制产品链接，如果是，直接调用复制产品按钮
    checkIsCopyProduct: function (_this) {
      if (_this.editType === 'copy') {
        _this.stepStatus[5]['active'] = true;
        // 调用复制按钮
        _this.cloneProduct();
        return true;
      } else {
        return false;
      }
    },
    // step bar click 回调函数
    handlerClickOnStepBar: function (index) {
      let _this = this;
      // 已激活
      if (_this.stepStatus[index]['active']) {
        _this.bar_active = index;
      }
    },
    // next step 回调函数
    handleNextStepClick: function () {
      let _this = this;
      if (_this.bar_active !== 0 && !_this.validNextStep(true)) { return }
      let $err = $('#formMpn').find('div.el-form-item__error');
      if (_this.bar_active == 1 && $err.length > 0) {
        var ct = $($err[0]).offset().top || 0; //获取距离顶部的距离
        window.scrollTo(0, ct - 100);
        return;
      }
      _this.bar_active++;
      _this.stepStatus[_this.bar_active]['active'] = true;
      window.scrollTo(0, 0);
    },
    // previous step 回调函数
    handlePreviousStepClick: function () {
      let _this = this;
      if (_this.bar_active == 0 || _this.bar_active == 5) {
        window.location.href = 'index.php?route=customerpartner/product/lists/index';
      } else {
        _this.bar_active = _this.bar_active - 1;
      }
    },
    // 校验主图和手册素材
    validateMainImageAndManuals: function () {
      this.refreshUploadInput2();
      var error = this.imageValid(this);
      if (error) {
        return error;
      }
    },
    validNextStep: function (isShowErr) {
      isShowErr = isShowErr || false;
      // 校验Next Step按钮是否允许点击 // 只有tab 1、2,3需要
      // isShowErr 是否展示error
      let that = this;
      if (that.bar_active === 1) {
        if (that.mpnValid(that.formModel.mpn, that)) {
          return false;
        }
        if (that.upcValid(that.formModel.upc, that)) {
          return false;
        }
        if (that.nameValid(that.formModel.name, that)) {
          return false;
        }
        if (that.productSizeValid(that.formModel.product_size, that)) {
          return false;
        }
        if (that.comboValid(that)) {
          return false;
        }
        if (that.validatorNumber(that.formModel.length, that) || that.validatorNumber(that.formModel.width, that)
          || that.validatorNumber(that.formModel.height, that) || that.validatorNumber(that.formModel.weight, that)) {
          return false;
        }
        if (that.priceValid(that.formModel.price, that)) {
          return false;
        }
        if (!that.formModel.color || !that.formModel.material
          || !that.formModel.price_display) {
          return false;
        }

        // #33309 新增
        // 自定义字段校验
        if (that.customFieldsValid(that.information_custom_map) || that.customFieldsValid(that.dimensions_custom_map)) {
          return false;
        }

        // 产品安装尺寸校验
        if (that.productAssembleSizeValid(that.formModel.dimensions, that)) {
          return false;
        }
        
        // #49569 jp产品尺寸校验
        if(that.productSizeMaxLimitValid(true)) {
          return false
        }
      } else if (that.bar_active === 2) {
        //原创产品检验
        if (that.originalCertificateValid(that.formModel.original_design, that)) {
          return false;
        }
        // tab2主要是校验退返品
        let err = that.validReturnWarranty(that);
        if (err) {
          isShowErr && layer.msg(err, { time: 5000 });
          return false;
        }
      } else {
        if (that.imageValid(that)) {
          return false;
        }
      }
      return true
    },
    validReturnWarranty: function (that) {
      let err = null
      let regD = new RegExp(/^[1-9][0-9]{0,2}$/); // 1-999正整数
      let regP = new RegExp("^([1-9]|[1-9]\\d|100)$"); // 1-100正整数
      if (that.returnWarranty.isAccept == '1' && (!regD.test(+that.returnWarranty.days))) {
        err = commonError.integerMax999;
        // 标红处理
        $('#rwDays').find('input').addClass('wrong-input');
      } else {
        $('#rwDays').find('input').removeClass('wrong-input');
      }
      if (that.returnWarranty.isAccept == '1' && (!regP.test(+that.returnWarranty.percent))) {
        err = commonError.integerMax100;
        $('#rwPercent').find('input').addClass('wrong-input');
      } else {
        $('#rwPercent').find('input').removeClass('wrong-input');
      }
      if (!regD.test(+that.returnWarranty.aDays)) {
        err = commonError.integerMax999;
        $('#rwAdays').find('input').addClass('wrong-input');
      } else {
        $('#rwAdays').find('input').removeClass('wrong-input');
      }
      if (!regD.test(+that.returnWarranty.aMonth)) {
        err = commonError.integerMax999;
        $('#rwAmonth').find('input').addClass('wrong-input');
      } else {
        $('#rwAmonth').find('input').removeClass('wrong-input');
      }
      return err
    },

    // #33309 认证文件类型必选
    validateCertFiles: function (_this) {
      for (let file of _this.material_certs) {
        if (!file.type_id) {
          return commonError.certFilesErr;
        }
      }
      return;
    },
    //上传图片的格式
    //uploadImagesFormat:function(file){
    //  if(file && file['type'].indexOf('image') === 0){
    //    return true;
    //  }
    //  this.error(file.name+' format error.', 'Error');
    //  return false;
    //},
    // 实际的表单提交方法
    submitForm: function (isDraft) {
      let _this = this;
      let $error = $('body').find('div.el-form-item__error');
      let returnError = _this.validReturnWarranty(_this);
      let certFileError = _this.validateCertFiles(_this);
      if ($error.length > 0 && $($error[0]).is(":visible")) {
        _this.error(commonError.correctFirstErr);
        return;
      }
      if (!_this.isLastCategory) {
        // 不是最后的分类
        _this.bar_active = 0;
        return;
      }
      if (!_this.formModel.color && _this.formModel.product_associated.length > 0) {
        _this.bar_active = 1;
        _this.error(commonError.colorAssoErr);
        return;
      }
      if (returnError) {
        // 退返品有误， 跳转到退返品
        _this.bar_active = 2;
        return;
      }
      if (certFileError) {
        // 退返品有误， 跳转到文档
        _this.bar_active = 4;
        _this.error(certFileError);
        return;
      }
      if (_this.validImageSortOrder(_this)) {
        _this.bar_active = 3;
        return;
      }
      let errorMsg = _this.validateMainImageAndManuals();
      if (errorMsg) {
        _this.bar_active = 3;
        _this.error(errorMsg);
        return;
      }
      // onsite seller 新增操作：提交审核前需验证运费
      if (_this.isOnsiteSeller && isDraft == 2 && !_this.checkOnsiteSellerApproval(_this)) {
        return;
      }
      // onsite seller 编辑操作：统一校验checkFreight
      if (_this.isOnsiteSeller && isDraft == 3) {
        return _this.checkFreightForOnsite(_this);
      }
      _this.submitService(isDraft, _this);
    },
    // #33309 新增 - 数据前置处理
    preDataHandler(obj) {
      // 组装长度不适用值为-1
      let data = Object.assign({}, obj);
      for (let k in data.dimensions) {
        data[`assemble_${k}`] = !this.dimensionsCheck[k] ? data['dimensions'][k] : -1;
      }
      delete data['dimensions'];
      // start 处理自定义字段 ----
      data.dimensions_custom_field = [];
      this.dimensions_custom_map.forEach((item, index) => {
        data.dimensions_custom_field.push({
          ...item,
          sort: index + 1
        });
      })
      data.information_custom_field = [];
      this.information_custom_map.forEach((item, index) => {
        data.information_custom_field.push({
          ...item,
          sort: index + 1
        });
      })
      // end 自定义字段处理 ----
      return data;
    },
    submitService: function (isDraft, _this) {
      if (_this.loading !== null) return;
      _this.loading = this.$loading({
        lock: true,
        text: 'Loading',
        spinner: 'el-icon-loading',
        background: 'rgba(0, 0, 0, 0.7)'
      });

      // 数据前置处理
      let params = _this.preDataHandler(_this.formModel);
      params['product_category'] = _this.handleCategoryParam();
      params['return_warranty'] = _this.handleReturnWarranty(); // 退返品
      params['name'] = params['name'].trim();
      params['upc'] = params['upc'].trim();
      if (_this.formModel.product_group_ids && _this.formModel.product_group_ids.length > 0) {
        params['product_group_ids'] = JSON.parse(JSON.stringify(_this.formModel.product_group_ids.join(',')));
      } else {
        params['product_group_ids'] = '';
      }
      if (!_this.formModel.product_id) {
        params['is_draft'] = isDraft; // 是否保存到草稿箱 1：是 2：否 2就代表直接提交审核
      } else {
        params['is_draft'] = '3'; //固定传3  代表编辑，不做存储
      }
      params['is_ltl'] = _this.formModel.is_ltl ? 1 : 0;
      // 单独售卖时存入不可售卖平台
      if (_this.formModel.buyer_flag) {
        params['non_sellable_on'] = _this.formModel.non_sellable_on.join(',');
      } else {
        params['non_sellable_on'] = '';
      }

      //检测是否新增删除关联商品
      let check_url = 'index.php?route=pro/product/checkRelationDelicacyProduct' + '&product_id=' + _this.formModel.product_id;
      _this.axios.post(check_url, params)
        .then(function (res) {
          let data = res['data'];
          const rCode = parseInt(data.hasOwnProperty('code') ? data['code'] : 0);
          if (rCode === 1) { // 成功
            layerOris.confirmLayer({ title: commonInfo.confirm, content: data['msg'], btn: [commonInfo.ok] });
          }
        })
        .catch(function (e) {
          console.log(e);
        });
      let url = 'index.php?route=pro/product/storeProduct';
      if (_this.formModel.product_id) {
        url += '&product_id=' + _this.formModel.product_id;
      }
      if (_this.audit_id > 0 && _this.isReadOnly == 0) {
        //编辑审核记录
        params['audit_id'] = _this.audit_id;
      }
      _this.axios.post(url, params)
        .then(function (res) {
          let data = res['data'];
          _this.loading.close();
          const rCode = parseInt(data.hasOwnProperty('code') ? data['code'] : 0);
          _this.isDraft = isDraft; // 记录isDraft区分3代表编辑提交
          if (rCode === 1) { // 成功
            _this.$notify.success({
              title: commonInfo.info,
              message: data['msg']
            });
            _this.formModel.product_id > 0 ?
              _this.editProductAfter(data) // 编辑商品回调函数
              :
              _this.addProductAfter(); // 添加商品回调函数
            _this.formModel.product_id = data['info']['product_id'];
            _this.formModel.sku = data['info']['sku'];
            _this.previewLink = '/index.php?route=product/product&product_id=' + data['info']['product_id'] + '&audit_id=' + data['info']['audit_id'];
          } else { // 失败
            _this.$notify.error({
              title: 'Info',
              message: data['msg']
            });
            _this.loading = null;
            return;
          }
        })
        .catch(function (e) {
          _this.loading.close();
          _this.loading = null;
          console.log(e);
        })
    },
    addProductAfter: function () {
      let _this = this;
      _this.loading = null;
      _this.bar_active = 5;
      _this.stepStatus[_this.bar_active]['active'] = true;
    },
    editProductAfter: function (data) {
      let _this = this;
      // 编辑产品之后允许重复提交表单
      _this.loading = null;
      _this.bar_active = 5;
      _this.stepStatus[_this.bar_active]['active'] = true;
      _this.notice_type = data['info']['notice_type'];
    },
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
            item['path'].toLowerCase().indexOf('http') === 0 ?
              item['path'] :
              baseHref + item['path']
          );
        }
      });
    },
    // product description end
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
      let maxSortOrder = -1;
      fileList = fileList.map(function (item, key) {
        // 保证新的图片修改后不会覆盖原有的内容
        if (item.hasOwnProperty('sort_order')) {
          fromIndex = key;
          maxSortOrder = Math.max(parseInt(maxSortOrder), parseInt(item['sort_order']));
          return item;
        }
        item.sort_order = null;
        return item;
      });
      _this.fileList = fileList.map(function (item, key) {
        if (key > parseInt(fromIndex) && !item.sort_order) {
          maxSortOrder++;
          item['sort_order'] = maxSortOrder;
        }
        return item;
      })
      _this.productImgOver();
    },
    productImgOver: function () {
      // 如果产品图片总数大于27张就隐藏上传按钮
      if (this.fileList.length >= 27) {
        $('#productImageUp').find('div.el-upload').hide();
        // 只获取27张
        this.fileList.length = 27;
      } else {
        $('#productImageUp').find('div.el-upload').show();
      }
    },
    handlerProductImageCheckboxChange: function (file) {
      let _this = this;
      let maxOrder = 0;
      let fileList = _this.$refs['upload_input_2'].getFileList();
      // checkbox  true => false
      if (file['isMainImage'] === false) {
        file['isMainImage'] = true;
        this.error(commonError.imageAlreadyManinImage);
        return;
      }
      fileList.forEach(function (img) {
        maxOrder = Math.max(maxOrder, img['sort_order']);
      })
      _this.fileList = fileList.map(function (item) {
        // 检测是否为同一个文件
        if (item['orig_url'] !== file['orig_url']) {
          if (item['isMainImage'] === true) {
            item['isMainImage'] = false;
            item['sort_order'] = maxOrder + 1; // 修改成最后大order+1
          }
        } else {
          item['isMainImage'] = true;
          // 选择为主图的同时 sort order 变为0
          item['sort_order'] = 0;
        }
        return item;
      });
    },
    sortFileList: function (fileList) {
      let sort_exist_file = [];
      let null_sort_file = [];
      fileList.forEach(function (item) {
        if (item['sort_order'] === null) {
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
        return _this.$confirm(commonError.sureToDelImage, 'Notice', {
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
    // region color and associated products
    getAssociatedProducts: function (val) {
      let _this = this;
      _this.associateProductTableData = [];
      _this.showEmptytext = 'Loading...';
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
            if (!_this.associateProductTableData || _this.associateProductTableData.length === 0) {
              _this.showEmptytext = 'No Data';
            }
          }).catch(function (e) {
            console.error(e);
          })
      }, 500);
    },
    handlerAssociateProductsClick: function () {
      let _this = this;
      // 刚开始没有input，手动触发
      _this.associateProductCurrentPage = 1;
      if (!_this.associatedProductsDialogInputValue) {
        _this.getAssociatedProducts();
      }
      _this.associatedProductsDialogVisible = true;
      // #40062 关联产品默认勾选
      _this.$nextTick(() => {
        _this.$refs['associateTable'].clearSelection();
        // let product_ids = _this.product_associated.map(item => {return item.product_id});
        // for(let item of _this.associateProductTableData) {
        //   if(product_ids.includes(item.product_id)) {
        //     _this.$refs['associateTable'].toggleRowSelection(item, true);
        //   }
        // }
      })
    },
    handleAssociateProductSelectionChange: function (select) {
      // 临时存储选择项
      this.temp_product_associated = select;
    },
    handlerAssociatedProductsSubmitClick: function () {
      let _this = this;
      if (_this.temp_product_associated.length === 0) {
        _this.error('Please tick at least one product.');
        return;
      }
      _this.associatedProductsDialogVisible = false;

      // #40062 
      let product_ids = _this.temp_product_associated.map((item) => { return item.product_id }).join(',');
      let exclude_product_ids = this.formModel.product_id;
      _this.getProductAssociates({
        product_ids: product_ids,
        exclude_product_ids: exclude_product_ids
      }).then(res => {
        let temp_product_associated = _this.product_associated.concat(res.data.data);
        
        // #40062 去重操作
        var uniq_product_associated = [];
        temp_product_associated.filter(function(item){
          var i = uniq_product_associated.findIndex(x => (x.product_id == item.product_id));
          if(i <= -1){
            uniq_product_associated.push(item);
          }
          return null;
        });
        _this.product_associated = uniq_product_associated;

      })

      // _this.product_associated = _this.product_associated.concat(temp_product_associated);
    },
    handlerProductColorDelete: function (index) {
      this.product_associated.splice(index, 1)
    },
    // endregion
    // region combo
    getComboProducts: function (val) {
      let _this = this;
      _this.addSubItemTableData = [];
      _this.showEmptytext = 'Loading...';
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
            if (!_this.addSubItemTableData || _this.addSubItemTableData.length === 0) {
              _this.showEmptytext = 'No Data';
            }
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
        _this.$refs['app_form'].validateField('combo');
      });
    },
    handlerDeleteComboItem: function (index) {
      this.formModel.combo.splice(index, 1);
      this.$refs['app_form'].validateField('combo');
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
    //原创证明文件start
    handlerOnConfirmOriginalImage: function () {
      let _this = this;
      let tempList = _this.$refs['upload_input_0'].getFileList();
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
      _this.original_design = fileList;
      _this.productImgLimit();
    },
    productImgLimit: function () {
      // 原创证明文件最多上传9张
      if (this.original_design.length >= 9) {
        $('#originalImagesUp').find('div.el-upload').hide();
        if (this.original_design.length > 9) {
          this.$notify.error({
            title: 'Error',
            dangerouslyUseHTMLString: true,
            message: "File format error: <br>" + commonError.originalCertificateLen
          });
        }
        this.original_design.length = 9;
      } else {
        $('#originalImagesUp').find('div.el-upload').show();
      }
    },
    //原创证明文件end
    // endregion material images
    // material manuals
    handlerOnConfirmMaterialManual: function () {
      let _this = this;
      _this.material_manuals = _this.$refs['upload_input_4'].getFileList();
    },
    handlerOnConfirmMaterialCert: function () {
      let _this = this;
      _this.material_certs = _this.$refs['upload_input_6'].getFileList();
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
        this.error('Files must be in TXT format!');
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
      return this.axios.post('index.php?route=pro/product/getCustomerGroup', { product_id: this.formModel.product_id });
    },
    // 获取商品信息
    getProductInfo: function () {
      if (this.audit_id > 0) {
        if (this.isReadOnly) {
          this.bar_active = 1;
          $('#pageTitle').html('Product Detail');
        } else {
          this.bar_active = 0;
          $('#pageTitle').html('Edit Product Audit');
        }
        return this.axios.get('index.php?route=customerpartner/product/lists/getProductAuditInfo&audit_id=' + this.audit_id + '&product_id=' + this.formModel.product_id);
      } else {
        if (this.formModel.product_id) {
          return this.axios.get('index.php?route=pro/product/getProductInfo&product_id=' + this.formModel.product_id);
        } else {
          return { data: { code: 200 } }
        }
      }
    },
    // 所有类目接口
    getAllCategory: function () {
      return this.axios.get('index.php?route=pro/product/getCates');
    },
    // 曾经选择过的类目接口
    getOnceSelectedCategory: function () {
      return this.axios.get('index.php?route=pro/product/getOnceSelectedCategory');
    },
    // 颜色材料接口
    getColorAndMaterial: function () {
      return this.axios.get('index.php?route=pro/product/getColorAndMaterial');
    },
    // 获取退返品政策
    getReturnWarranty: function () {
      if (!this.audit_id) {
        return this.axios.get('index.php?route=pro/product/getProductReturnWarranty&product_id=' + (this.formModel.product_id || 0));
      } else {
        return;
      }
    },
    // 获取产地信息列表
    getOriginPlace: function () {
      return this.axios.get('/index.php?route=pro/product/originPlaces');
    },
    // 获取产品认证信息
    getCertTypes: function () {
      return this.axios.get('/index.php?route=pro/product/certificationTypes');
    },

    // #40062 新增
    // 请求获取关联产品
    getProductAssociates: function(params) {
      return this.axios('/index.php?route=pro/product/getProductAssociates', {
        params: params
      })
    },
    // endregion

    // 初始化Summer note
    initSummerNote: function () {
      let _this = this;
      let ele = this.descriptionSummerNote = $('.app_form_product_description');
      summernote.init(ele, {
        callbacks: {
          onChange: function () {
            _this.formModel['description'] = _this.descriptionSummerNote.summernote('code');
            if (_this.isReadOnly > 0) {
              $('div.note-editable').attr('contenteditable', false)

            }
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
    },
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
    },
    // 选中分类
    chooseCateg(index, item) {
      if (index === 1) {
        if (this.choosedCateg.one.category_id === item.category_id) {
          return
        }
        this.secondCategoryBox = item.son;
        this.thirdCategoryBox = [];
        this.choosedCateg = {
          one: item,
          second: {},
          third: {}
        }
      } else if (index === 2) {
        if (this.choosedCateg.second.category_id === item.category_id) {
          return
        }
        this.thirdCategoryBox = item.son;
        this.choosedCateg.second = item;
        this.choosedCateg.third = {};
      } else {
        // 三级选中
        this.choosedCateg.third = item;
      }
      this.isLastCategory = (!item.son || item.son.length === 0)
    },
    // 退返品政策添加减少条件
    returnPolicyLine(type, index) {
      if (type > 0) {
        this.returnWarranty.content.push('')
      } else {
        this.returnWarranty.content.splice(index, 1)
      }
    },
    // 选中您曾选择过的类目
    changedCateg(myCateg) {
      if (myCateg) {
        // 清空所有选中
        this.choosedCateg = {
          one: {},
          second: {},
          third: {}
        };
        this.secondCategoryBox = [];
        this.thirdCategoryBox = [];
        var that = this;
        var seArray = myCateg.split(',');
        seArray[0] && that.allCategoryList.forEach(function (item) {
          if (item.category_id == seArray[0]) {
            // 第一项选中
            that.chooseCateg(1, item);
          }
        })
        seArray[1] && that.secondCategoryBox.forEach(function (second) {
          if (second.category_id == seArray[1]) {
            that.chooseCateg(2, second);
          }
        })
        seArray[2] && that.thirdCategoryBox.forEach(function (third) {
          if (third.category_id == seArray[2]) {
            that.chooseCateg(3, third);
          }
        })
      }
    },
    // 校验是否为LTL
    checkLTLProduct() {
      let result = false
      // 最长边>108 inches；实际重量>150 lbs；最长边+周长>165 inches【周长=2*（次长边+最短边）】
      var maxValue = Math.max(this.formModel.length, this.formModel.width, this.formModel.height);
      if (maxValue && maxValue > 108) {
        return true;
      }
      if (this.formModel.weight && this.formModel.weight > 150) {
        return true;
      }
      if (maxValue + 2 * (this.formModel.length + this.formModel.width + this.formModel.height - maxValue) > 165) {
        return true;
      }
      return result;
    },
    isApprochLTL() {
      let result = false;
      // 美国内部不需要判断是否临近LTL
      if (this.countryId === 223 && this.accountType === 1) {
        return result;
      }
      var maxValue = Math.max(this.formModel.length, this.formModel.width, this.formModel.height);
      // 153 inches＜【最长边+周长】≤165 inches；148 lbs＜【实际重量】≤150 lbs；105 inches＜【最长边】≤108 inches
      var maxAndRound = maxValue + 2 * (this.formModel.length + this.formModel.width + this.formModel.height - maxValue);
      if (maxAndRound > 153 && maxAndRound <= 165) {
        result = true;
      }
      if (this.formModel.weight > 148 && this.formModel.weight <= 150) {
        result = true;
      }
      if (maxValue > 105 && maxValue <= 108) {
        result = true;
      }
      return result
    },
    // 校验是否弹框LTL
    showLtlWindow() {
      // onsite seller 如果没有ltl报价，不需要弹框
      if (this.isOnsiteSeller && !this.isLtlPrice) {
        this.isLtlWindow = 0;
        // 计算运费
        this.formModel.is_ltl = 0;
        return this.calcFreight();
      }
      let url = `index.php?route=customerpartner/product/lists/ltlWindow&product_ids=0&length=${this.formModel.length}&width=${this.formModel.width}&height=${this.formModel.height}&weight=${this.formModel.weight}&mpn=${this.formModel.mpn}`;
      let that = this
      // 弹出LTL
      that.isShowLtlWindow = true;
      // 设置为LTL发货
      var optionsLtl = {
        type: 2,
        area: ['1000px', '600px'],
        btn: [commonInfo.submit],
        title: commonInfo.attention,
        content: url
      };
      layerOris.confirmLayer(optionsLtl, function (index, layero) {
        var $submitInput = layer.getChildFrame('body', index).find('input[name=isSubmitLTL]');
        if ($submitInput.length && $submitInput[0].checked) {
          layer.close(index);
          // 标记isShowLtlWindow的选项值
          that.isLtlWindow = 1;
          // 标记为LTL
          that.formModel.is_ltl = 1;
          // 计算运费
          that.calcFreight();
        }
      }, function (index) {
        that.isLtlWindow = 0;
        // 计算运费
        that.formModel.is_ltl = 0;
        that.calcFreight();
      });
    },
    // LTL超大件需要计算体积是否符合条件:产品的计费重量大于超规格尺寸限制，且对应报价为单独询价时
    // 计费重=体积重和实际重量较大值向上取整，体积重=体积/DIM，DIM为LTL报价的DIM
    calcLtlMaxWeight(isCombo) {
      isCombo = isCombo || false;
      let result = false;
      // 非单独询价
      if (!this.separateEnquiry || this.countryId !== 223) {
        return result;
      }
      if (!this.dim || this.dim == 0) {
        return result;
      }
      let voWeight = 0;
      if (!isCombo) {
        voWeight = Math.max((this.formModel.length * this.formModel.width * this.formModel.height) / this.dim, this.formModel.weight);
      } else {
        // 非combo平计算总重量
        if (!this.formModel.combo || this.formModel.combo.length === 0) {
          return false;
        } else {
          let that = this
          this.formModel.combo.forEach(function (item) {
            voWeight += Math.max((item.length * item.width * item.height) / that.dim, item.weight) * item.quantity;
          })
        }
      }
      // console.log('计费重：' + Math.ceil(voWeight) + '  平台重量：' + this.limitWeight)
      if (Math.ceil(voWeight) > this.limitWeight) {
        // 超重
        result = true
      }
      return result;
    },
    // 超重弹框提示
    overWeightLayer() {
      // 审核详情不弹出提示
      if (this.audit_id > 0) {
        return
      }
      var optionsOver = {
        area: ['340px', 'auto'],
        btn: [commonInfo.ok],
        title: commonInfo.attention,
        content: `<span>${commonError.weightExceedsLeft}<span class='tip-color'>${this.limitWeight}${commonInfo.lbs}</span>${commonError.weightExceedsRight}</span>`
      };
      layerOris.confirmLayer(optionsOver, function (index, layero) {
        layer.close(index);
        // 点击ok后，停止创建，返回列表
        window.location.href = 'index.php?route=customerpartner/product/lists/index'
      }, function (index, layero) {
        layer.close(index);
      });
    },
    // 计算运费
    calcFreight() {
      if (this.countryId !== 223) {
        return
      }
      let url = 'index.php?route=pro/freight/index';
      let params = {
        combo_flag: this.formModel.product_type == 2 ? 1 : 0,
        combo: this.formModel.combo,
        length: this.formModel.length,
        width: this.formModel.width,
        height: this.formModel.height,
        weight: this.formModel.weight,
        is_ltl: this.formModel.product_type == 2 ? 0 : (this.formModel.is_ltl || 0),
        product_id: (this.formModel.product_id || 0),
      }
      let that = this
      this.axios.post(url, params)
        .then(function (res) {
          let data = res['data'];
          const rCode = parseInt(data.hasOwnProperty('code') ? data['code'] : 0);
          if (rCode === 200) { // 成功
            that.freightData = data['data'];
            that.formModel.is_ltl = data['data']['ltlFlag'];
            // 如果是onsite seller 需要记录运费返回的return code, 用于判断是否可以提交审核
            if (that.isOnsiteSeller) {
              that.onsiteFreightReturnCode = data['data']['returnCode'];
            }
          } else {
            // 运费计算失败
            layer.msg(data['msg'], { time: 5000 });
            that.freightData = {
              dropShipPackageFee: '',
              freight: '',
              pickUpPackageFee: '',
              dangerFee: '',
              peakSeasonFee: ''
            }
          }
        })
        .catch(function (e) {
          console.log(e);
        });
    },
    // 清空运费和LTL
    clearFreight() {
      this.formModel.is_ltl = undefined;
      this.freightData = {
        dropShipPackageFee: '',
        freight: '',
        pickUpPackageFee: '',
        dangerFee: '',
        peakSeasonFee: ''
      }
    },
    // 退返品模板生成
    handleReturnText() {
      let reConditon = this.checkReturnConditionNull();
      let html = `<div class="return-policy">
                    <div class="policy-title">
                      <i class="giga icon-V10_tuifanpinshuai text-max text-bule"></i>
                      <span class="text-max text-bold"> Return Policy</span>
                      <span class="text-larger ml-1">Marketplace RMA guidelines must be followed for processing all return requests</span>
                    </div>
                    <div class="policy-content">
                      <h4 class="text-bule mt-3"> Unshipped Items Return Policy</h4>
                      <ul>${this.returnWarranty.isAccept == '1' ? `<li><span class="text-warning text-bold"> ${this.returnWarranty.percent} % </span>restocking fee will be charged for unshipped item returns without valid reason within<span class="text-warning text-bold"> ${this.returnWarranty.days} </span>days of the original order being processed. </li>` : `<li>Sellers do not accept return requests for unshipped items. </li>`}
                      </ul>
                      <h4 class="text-bule mt-3">Shipped Items Return Policy</h4>
                      <ul>
                        <li>For quality or logistics issues, the Buyer can request a refund or reshipment within<span class="text-warning text-bold"> ${this.returnWarranty.aDays} </span>days after the end customer has received the item. The reshipment fee will be borne by the Seller.</li>${this.returnWarranty.checked ? `<li>After<span class="text-warning text-bold"> ${this.returnWarranty.aDays} </span>days have passed since the end customer recieved their order, RMA requests will no longer be accepted aside from quality or logistics related issues.</li>` : ''}
                      </ul>
                    </div>
                  </div>
                  <div class="warranty-policy mt-5">
                    <div class="policy-title">
                      <i class="giga icon-fuwu text-max text-bule"></i>
                      <span class="text-max text-bold">Warranty Policy</span>
                      <span class="text-larger ml-1">Marketplace RMA guidelines must be followed during after-sales services </span>
                    </div>
                    <div class="policy-content">
                      <div>
                        Beginning from the date of delivery, purchased products include a
                        <span class="text-warning text-bold"> ${this.returnWarranty.aMonth} </span>
                        month warranty.In the event of manufacturing defects, the Buyer can request a refund, partial reshipment or full reshipment within the warranty period.
                      </div>
                      <h4 class="text-danger mt-2">Please note that the warranty policy does not apply to the following situations: </h4>
                      <ul>
                        <li>Damage resulting from negligence, abuse, normal wear and tear or natural disaster and accidents, including but not limited to: burns, cuts, tears abrasions, scratches, watermarks, indentation or pet damage</li>
                        <li>Damage resulting from unauthorized modifications, except with written approval from Seller</li>
                        <li>Products not purchased through the Giga Cloud Marketplace</li>
                        <li>Products with their own individual warranty policy</li>${reConditon.length > 0 ? '<li>' + reConditon.join('</li><li>') + '</li>' : ''}
                      </ul>
                    </div>
                  </div>`
      return html
    },
    handleReturnWarranty() {
      let result = {
        return_warranty: {
          return: {
            undelivered: {
              days: this.returnWarranty.days,
              rate: this.returnWarranty.percent,
              allow_return: this.returnWarranty.isAccept == '1' ? 1 : 0
            },
            delivered: {
              before_days: this.returnWarranty.aDays,
              after_days: this.returnWarranty.aDays,
              delivered_checked: this.returnWarranty.checked ? 1 : 0
            }
          },
          warranty: {
            month: this.returnWarranty.aMonth,
            conditions: this.checkReturnConditionNull()
          }
        },
        return_warranty_text: this.handleReturnText()
      };
      return result;
    },
    handleCategoryParam() {
      let productCategory = [];
      if (this.choosedCateg['one']['category_id']) {
        productCategory.push(this.choosedCateg['one']['category_id'])
      }
      if (this.choosedCateg['second']['category_id']) {
        productCategory.push(this.choosedCateg['second']['category_id'])
      }
      if (this.choosedCateg['third']['category_id']) {
        productCategory.push(this.choosedCateg['third']['category_id'])
      }
      return productCategory;
    },
    checkReturnConditionNull() {
      let result = []
      this.returnWarranty.content.forEach(function (one) {
        if (one) {
          result.push(one)
        }
      })
      return result;
    },
    // Add sub-items 弹框如果关联产品的长宽高有一项为0就不让选中
    checkProductCanSelect(row, index) {
      return (+row.width != 0 && +row.weight != 0 && +row.height != 0 && +row.length != 0)
    },
    mpnValid: function (value, that) {
      var err = null
      if (that.formModel.product_id) return null; // 修改产品时不允许修改mpn 也就不需要校验mpn
      // 校验MPN输入
      let regex = /^[a-zA-Z0-9][\w\s\-]*$/;
      if (new RegExp(regex).test(value) == false) {
        err = commonError.mpnFormatErr;
      }
      if (!value || value.length < 4 || value.length > 30) {
        err = commonError.mpnFormatCharsErr
      }
      return err;
    },
    // #33309 新增
    upcValid: function (value, that) {
      var err = null
      if (!value || !value.trim()) {
        return err;
      }
      // 校验MPN输入
      let regex = /^[a-zA-Z0-9]*$/;
      if (new RegExp(regex).test(value.trim()) == false) {
        err = commonError.upcFormatErr;
      }
      if (value.trim() && value.length > 30) {
        err = commonError.upcFormatCharsErr
      }
      return err;
    },
    nameValid: function (value, that) {
      var err = null
      if (!value) {
        err = 'Product Title can not be blank.'
      } else {
        var len = toolString.calcStringLength(value, 200).len;
        if (len < 1 || len > 200) {
          err = commonError.productFormatCharsErr;
        }
      }
      return err;
    },
    //原创证明文件
    originalCertificateValid: function (value, that) {
      var err = null;
      var val = that.formModel.original_product;
      if (val == 1 && value.length < 1) {
        err = commonError.originalCertificateErr;
      }
      return err;
    },
    productSizeValid: function (value, that) {
      var err = null
      var len = that.calcStringLength(value);
      if (len < 0 || len > 80) {
        err = commonError.productSizeFormatCharsErr;
      }
      return err;
    },
    imageValid: function (that) {
      // 商品可以单独售卖时候 必须选择主图
      var err = null
      if (that.formModel.buyer_flag === 1 && !that.formModel.image) {
        err = commonError.mainImageNeed;
      }
      return err;
    },
    // #33309 新增 产品安装尺寸校验
    productAssembleSizeValid: function (value, that, keyName = null) {
      var err = null;
      let data = [];
      if (keyName) {
        data = { [keyName]: value[keyName] };
      } else {
        data = value;
      }

      for (let key in data) {
        if (!that.dimensionsCheck[key]) {
          if (data[key] == undefined || (data[key] != undefined && !(data[key] + '').trim())) {
            err = commonError.assembleSizeErr[key];
            return err;
          } else if (isNaN(data[key]) || data[key] <= 0 || data[key] > 999.99) {
            err = commonError.floatMax999with99;
            return err;
          }
        }
      }
      return err;
    },
    // #33309 新增 自定义字段校验
    customFieldsValid: function (value) {
      var err = null;
      if (value.length) {
        for (let item of value) {
          if (!item.value.trim()) {
            err = commonError.customFieldsErr;
            return err;
          }
        }
      }
      return err;
    },
    // #49569 产品可配送尺寸上限校验
    // 包装尺寸blur时，改变状态，控制只有包装尺寸修改时，弹出校验不通过的提示
    productSizeValidBlur: function() {
      if(this.isNextStep) {
        this.productSizeMaxLimitValid();
      }
    },
    // 产品为普通产品 且 为新增状态下 ： 三边和＞260cm 或者 实际重量≥50kg 校验不通过
    productSizeMaxLimitValid: function (fromNextValid) {

      let that = this;
      if(fromNextValid && that.firstJpSizeValid) {
        return false;
      }

      that.firstJpSizeValid = true;
      if (that.status === null && that.audit_id <= 0 && that.countryId === 107) {
        if ((parseFloat(that.formModel.length) + parseFloat(that.formModel.width) + parseFloat(that.formModel.height)) > 260 
          || that.formModel.weight >= 50) {
          that.baseInfoTooltip = window.overSizeInfo.content + "\n" + window.overSizeInfo.description;
          var optionsOver = {
            area: ['340px', 'auto'],
            btn: [commonInfo.ok],
            title: commonInfo.attention,
            content: `<div>${window.overSizeInfo.content}</div> <div>${window.overSizeInfo.description}</div>`
          };
          if(!that.overConfirmLayer) {
            that.overConfirmLayer = true;
            layerOris.confirmLayer(optionsOver, function (index, layero) {
              layer.close(index);
              that.overConfirmLayer = false;
            }, function (index, layero) {
              layer.close(index);
              that.overConfirmLayer = false;
            });
          }
          this.jpOverSizeIsValid = false;
          return true;
        } else {
          that.baseInfoTooltip = window.nextStepText.tooltip;
          this.jpOverSizeIsValid = true;
          return false;
        }
      } else {
        this.jpOverSizeIsValid = true;
        return false;
      }
    },
    comboValid: function (that) {
      var err = null
      // 只有在product type选择combo item时才校验combo品是否为空
      if (that.formModel.product_type !== 2) return err;
      let combos = that.formModel.combo;
      if (combos.length === 0) {
        err = commonError.subItemsEmptyErr;
      }
      if (combos.length === 1 && (parseInt(combos[0].quantity) <= 1)) {
        err = commonError.subItemsNumberErr;
      }
      return err;
    },
    priceValid: function (value, that) {
      var err = null
      // 日本金额不能为小数
      if (that.countryId === 107) {
        if (isNaN(value) || value < 0 || value > 9999999) {
          err = commonError.priceMax9999999;
        }
      } else {
        if (isNaN(value) || value < 0 || value > 9999999.99) {
          err = commonError.priceMax9999999With99;
        }
      }
      return err;
    },
    validatorNumber: function (value, that) {
      var err = null
      if (that.formModel.product_type == 2) { // combo品不校验
        return err
      }
      // 判断数值
      let num = Number(value)
      if (isNaN(num) || num <= 0 || num > 999.99) {
        err = commonError.floatMax999with99;
      }
      return err
    },
    ltlValid: function (that) {
      if (+that.formModel.length && +that.formModel.width && +that.formModel.height && +that.formModel.weight) {
        // 首先判断是否为美国, 否：直接算运费
        if (that.countryId !== 223) {
          that.formModel.is_ltl = undefined;
          return;
        }
        // 内部seller直接算运费
        if (that.accountType === 1) {
          // 如果是超大件立即校验是否超重
          if (that.checkLTLProduct() && that.calcLtlMaxWeight()) {
            // 超重，停止创建
            that.overWeightLayer();
            return that.clearFreight();
          }
          that.formModel.is_ltl = undefined;
          return that.calcFreight();
        }
        // 美国情况下： 首先判断是否为LTL
        if (that.checkLTLProduct()) {
          that.formModel.is_ltl = 1;
          // 如果是超大件立即校验是否超重
          if (that.calcLtlMaxWeight()) {
            // 超重，停止创建
            that.overWeightLayer();
            return that.clearFreight();
          }
          return that.calcFreight();
        }
        // 没有出现过超大件弹框并且触及边缘
        if (!that.isShowLtlWindow && that.isApprochLTL()) {
          return that.showLtlWindow(); // 弹框之后需要计算运费
        }
        // 已经出现过超大件弹框，并且依旧触及边缘 is_ltl 保持不变
        if (that.isShowLtlWindow && that.isApprochLTL()) {
          that.formModel.is_ltl = that.isLtlWindow;
        } else {
          that.formModel.is_ltl = 0;
        }
        // 正常情况下计算运费
        return that.calcFreight();
      } else {
        // 清空
        return that.clearFreight();
      }
    },
    changeText: function (pIndex) {
      //获取输入内容
      var val = this.returnWarranty.content[pIndex];
      val = this.calcStringLength(val.trim(), 400);
      this.$set(this.returnWarranty.content, pIndex, val);
    },
    // 校验字符长度，中文和日本算两个字符
    calcStringLength: function (str, maxLenStr) {
      maxLenStr = maxLenStr || 0;
      var fullStr = '';
      var len = 0;
      var charCode = -1;
      if (!str || !str.length) {
        return maxLenStr ? fullStr : len;
      }
      for (var i = 0; i < str.length; i++) {
        charCode = str.charCodeAt(i);
        if (charCode >= 2048 && charCode <= 40869) {
          len += 2;
        } else {
          len++;
        }
        if (len <= maxLenStr) {
          fullStr += str[i];
        }
      }
      return maxLenStr ? fullStr : len;
    },
    resetPage: function () {
      // 重置创建产品 刷新页面
      window.location.href = 'index.php?route=pro/product';
    },
    // 复制&创建新产品
    cloneProduct: function () {
      // 重置创建产品
      $('#pageTitle').html(PRODUCTINFO.add_product);
      window.history.pushState({}, 0, 'index.php?route=pro/product');
      let $breadli = $('#content').find('ul.breadcrumb li');
      $($breadli[$breadli.length - 1]).html('<a href="index.php?route=pro/product">' + PRODUCTINFO.add_product + '</a>');
      this.isDraft = '2'; //是否是提交草稿
      this.stepStatus = [ // active代表是否激活
        { active: true, title: window.originStepStatus.firstStep },
        { active: false, title: window.originStepStatus.secondStep },
        { active: false, title: window.originStepStatus.thirdStep },
        { active: false, title: window.originStepStatus.forthStep },
        { active: false, title: window.originStepStatus.fifthStep },
        { active: false, title: window.originStepStatus.sixStepCreate },
      ];
      this.bar_active = 0; // 步骤条标志
      this.freightData = {
        dropShipPackageFee: '',
        freight: '',
        pickUpPackageFee: '',
        dangerFee: '',
        peakSeasonFee: ''
      };
      this.formModel.product_id = null;
      this.formModel.mpn = null;
      // 清空产品包装信息
      this.formModel.product_type = null;
      this.formModel.combo = [];
      this.formModel.is_ltl = undefined;
      this.formModel.length = undefined;
      this.formModel.width = undefined;
      this.formModel.height = undefined;
      this.formModel.weight = undefined;
      this.addSubItemDialogVisible = false;
      this.addSubItemDialogInputValue = null;
      this.addSubItemDialogInputTimeout = null;
      this.addSubItemTableData = [];
      this.addSubItemCurrentPage = 1;
      this.addSubItemCurrentPageSize = 5;
      this.addSubItemTotal = 0;
      this.associatedProductsDialogVisible = false;
      this.associatedProductsDialogInputValue = null;
      this.associatedProductsDialogInputTimeout = null;
      this.associateProductTableData = [];
      this.associateProductCurrentPage = 1;
      this.associateProductCurrentPageSize = 5;
      this.associateProductTotal = 0;
      this.status = null; // 默认新增状态
      // material_manuals, material_viedo, material_images 图片的m_id需要置0
      var manuals = this.material_manuals;
      var videos = this.material_video;
      var images = this.material_images;
      var certs = this.material_certs;
      //原创证明文件
      var original = this.original_design;
      for (var i = 0; i < manuals.length; i++) {
        manuals[i].m_id = 0;
      }
      for (var i = 0; i < certs.length; i++) {
        certs[i].m_id = 0;
      }
      for (var i = 0; i < videos.length; i++) {
        videos[i].m_id = 0;
      }
      for (var i = 0; i < images.length; i++) {
        images[i].m_id = 0;
      }
      //原创证明文件
      for (var i = 0; i < original.length; i++) {
        original[i].m_id = 0;
      }
      this.material_manuals = JSON.parse(JSON.stringify(manuals));
      this.material_video = JSON.parse(JSON.stringify(videos));
      this.material_images = JSON.parse(JSON.stringify(images));
      this.material_certs = JSON.parse(JSON.stringify(certs));
      //原创证明文件
      this.original_design = JSON.parse(JSON.stringify(original));
      // 清空校验提示
      let that = this;
      setTimeout(function () {
        that.$refs["app_form"].clearValidate();
      }, 500)
    },
    showReturnWarranty: function (items, that) {
      if (!items) {
        return
      }
      that.turn = items['return'];
      that.warranty = items['warranty'];
      if (that.warranty && !that.isReadOnly && that.warranty.conditions.length < 5) {
        that.warranty.conditions.push(''); // 最后添加一条
      }
      // 退返品
      that.returnWarranty = {
        isAccept: that.turn.undelivered.allow_return + '',
        days: that.turn.undelivered.days,
        percent: that.turn.undelivered.rate,
        isAcceptDay: '1',
        aDays: that.turn.delivered.before_days,
        aMonth: that.warranty.month,
        checked: that.turn.delivered.delivered_checked == 1,
        content: that.warranty.conditions
      }
    },
    changeProductType(isFreight) {
      isFreight = isFreight || false;
      switch (this.formModel.product_type) {
        case 2:
          {
            this.formModel.part_flag = '0';
            this.formModel.combo_flag = '1';
            if (isFreight) {
              if (this.formModel.combo.length > 0) {
                // combo品不处理是否是ltl,但是要校验是否超重, 计算规则是：每个子产品的总重量不超过平台维护
                if (this.calcLtlMaxWeight(true)) {
                  // 超重
                  this.overWeightLayer();
                  this.clearFreight();
                } else {
                  // 计算运费
                  this.calcFreight();
                }
              } else {
                this.clearFreight();
              }
            }
            break;
          }
        case 3:
          {
            this.formModel.part_flag = '1';
            this.formModel.combo_flag = '0';
            isFreight && this.ltlValid(this);
            break;
          }
        default:
          {
            this.formModel.part_flag = '0';
            this.formModel.combo_flag = '0';
            isFreight && this.ltlValid(this);
            break;
          }
      }
    },
    // 提交之前校验图片sort_order不允许为null
    validImageSortOrder(that) {
      let result = false;
      for (let i = 0; i < that.formModel.product_image.length; i++) {
        let order = that.formModel.product_image[i]['sort_order'];
        if (order == null || isNaN(order)) {
          result = true;
          that.error(commonError.displayOrder);
          break;
        }
      }
      return result;
    },
    // 图片sortOrder校验
    changedSortOrder(file) {
      // 主图不用校验
      if (!file.isMainImage) {
        if (parseInt(file.sort_order) <= 0) {
          file.sort_order = null;
          // this.error(commonError.displayOrder);
        }
      }
    },
    blurSortOrder() {
      // 重新根据sort_order排序
      this.fileList.sort(function (item) {
        return parseInt(item.sort_order);
      })
    },
    addMorePlatformClick() {
      if (!this.isShowInputPlatform) {
        this.isShowInputPlatform = true;
        this.inputPlatform = '';
      }
    },
    saveInputPlatform() {
      // 保存不可售卖平台名称
      let value = this.inputPlatform.trim();
      if (value) {
        this.SellablePlatform.forEach(one => {
          if (one.toLowerCase() === value.toLowerCase()) {
            value = one;
          }
        })
        this.SellablePlatform.push(value);
        this.formModel.non_sellable_on.push(value);
        this.checkInputPlatform();
        this.cancelInputPlatform();
      }
    },
    // 判断自定义平台是否重复
    checkInputPlatform() {
      this.SellablePlatform = Array.from(new Set(this.SellablePlatform));
      this.formModel.non_sellable_on = Array.from(new Set(this.formModel.non_sellable_on));
    },
    cancelInputPlatform() {
      this.isShowInputPlatform = false;
      this.inputPlatform = '';
    },
    // 勾选不可售卖平台change事件
    changeSellablePlatform(inputValue, index) {
      // 判断取消选中
      if (this.formModel.non_sellable_on.indexOf(inputValue) === -1 && index > 10) {
        // 取消选中，并且是自定义的字段
        this.SellablePlatform.splice(index, 1);
      }
    },
    // 校验onsite seller 是否可以提交审核
    checkOnsiteSellerApproval(_this) {
      if (_this.onsiteFreightReturnCode == 200) {
        return true;
      }
      if (_this.onsiteFreightReturnCode == 507 && _this.formModel.is_ltl === 1) {
        _this.error(commonError.onsiteErrNoltl);
        return false;
      }
      if (_this.onsiteFreightReturnCode == 507 && !_this.formModel.is_ltl) {
        _this.error(commonError.onsiteErr);
        return false;
      }
      _this.error(commonError.onsiteErrSize);
      return false;
    },
    // onsite seller 编辑提交需要校验运费
    checkFreightForOnsite(_this) {
      let url = '/index.php?route=pro/product/checkFreight';
      let params = {
        product_id: _this.formModel.product_id
      }
      _this.axios.post(url, params).then(res => {
        if (res['data']['code'] == 200) {
          _this.onsiteFreightReturnCode = res['data']['data']['back_code'];
          if (_this.checkOnsiteSellerApproval(_this)) {
            // 提交编辑
            _this.submitService(3, _this);
          }
        } else {
          _this.error(res.msg);
        }
      }).catch(err => {
        console.log(err.toJSON());
      })
    },

    // #33309 新增 
    // 产品尺寸不适用
    changeDimensionCheck(val, type) {
      if (val) {
        this.$refs[`dimension-input-${type}`].resetField();
      }
    },

    // 认证文件type切换触发watch
    certFileTypeChanged() {
      this.material_certs = JSON.parse(JSON.stringify(this.material_certs));
    },

    // 自定义字段更新
    updateDimenCustomFields(map) {
      this.dimensions_custom_map = map;
    },

    updateInfoCustomFields(map) {
      this.information_custom_map = map;
    }
  }
});
window.vm_6446 = vm_6446;