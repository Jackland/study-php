# CNZZ 友盟 cnzz 统计

## 使用

1. 日常的所有页面的 PV/UV 等无需修改任何代码

2. 对于[事件](https://open.cnzz.com/a/new/trackevent/#report) 的快速添加方法如下：

简单添加：

在某个 Html 标签上增加以下属性：
`data-cnzz-event="页面|事件ID"`：如 `data-cnzz-event="页面头部|Hd_Statusbar_HelpCenter"`

动态事件添加：
 
添加特殊动态事件，使用如下js：
`CNZZ.triggerEvent(page, key, value)`

## 后续需求跟进

- js 重构，使用 asset 管理
- customerVar 的加强
- 考虑 用户ID 的跟踪问题