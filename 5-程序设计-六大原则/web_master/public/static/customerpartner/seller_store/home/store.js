//挂载Vuex
Vue.use(Vuex)

//创建VueX对象
function module2Instances(data) {
  let instances = [];
  for (let module of data.modules) {
    module['component'] = MODULE_COMPO[module.type]
    module['title'] = MODULE_LIST[module.type]['title'],
    instances.push(module);
  }
  return instances;
}

const store = new Vuex.Store({
  state: {
    // 下拉框选择数据
    options:[],
    unpublished: {},
    published: {},
    unpublishedData: [], //用于模块编辑展示
    publishedData: [], //用于模块编辑展示
    activeTab: '',
    activeData: {}, //当前选中的tab数据
    startFromTpl: false, //是否从模块开始
    showSide: false, //是否显示左侧菜单
    showContent: false, //是否显示右侧内容
  },
  getters: {

  },
  mutations: {
    setOptions(state, options) {
      state.options = options;
    },
    setUnpublished(state, data) {
      state.unpublished = data;
      state.unpublishedData = module2Instances(data);
    },
    setPublished(state, data) {
      state.published = data;
      state.publishedData = module2Instances(data);
    },
    setActiveTab(state, data) {
      state.activeTab = data;
      if(data == 'published') {
        state.activeData = state.publishedData;
      } else {
        state.activeData = state.unpublishedData;
      }
    },
    startFromTpl(state, data) {
      state.startFromTpl = data;
    },
    showSide(state, data) {
      state.showSide = data;
    },
    showContent(state, data) {
      state.showContent = data;
    }
  },
  actions: {
    setOptions({ commit }, options) {
      commit('setOptions', options);
    },

    setUnpublished({ commit }, data) {
      commit('setUnpublished', data);
    },

    setPublished({ commit }, data) {
      commit('setPublished', data);
    },

    setActiveTab({ commit }, data) {
      commit('setActiveTab', data);
    },

    startFromTpl({ commit }, data) {
      commit('startFromTpl', data);
    },
    showSide({ commit }, data) {
      commit('showSide', data);
    }, 
    showContent({ commit }, data) {
      commit('showContent', data);
    } 
  }
})
