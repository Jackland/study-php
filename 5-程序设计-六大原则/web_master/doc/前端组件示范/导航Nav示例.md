#### 父级页面
![image](https://note.youdao.com/yws/api/personal/file/WEB07930b45d7258db7e6a60ee4d24aba7a?method=download&shareKey=f10c932ff994493dc0531457ef163d14)
```
{{ this.title('Set Page Title') }}
{{ this.params('breadcrumbs', [
'home',
'current'
]) }}
{# 定义nav导航数组 #}
// 说明：tab1_url变量用于跳转到此tab并且带参数查询， 后端传带查询条件的url，但是初始化tab之后要置回不带参的路由(window.history.pushState({}, 0, 'account/example/list');)
{% set navDatas = [
{'id':'tab1_id','name':'Tab1', 'url': tab1_url },
{'id':'tab2_id','name':'Tab2', 'url': tab2_url, 'show_number':12},
{'id':'tab3_id','name':'Tab3', 'url': 'account/example/list'}]
%}
{{ this.params('title', this.getTitle()) }}
<div class="nav-oris">
  <div class="oris-row">
    <ul class="nav nav-tabs main" role="tablist">
      {% for one in navDatas %}
      <li>
        <a href="#{{one.id}}" role="tab" data-toggle="tab-ajax" data-url="{{ url(one.url) }}">
          {{one.name}}
          {% if one.show_number is defined and one.show_number > 0 %}
          <span class="tab-number">{{ one.show_number }}</span>
          {% endif %}
        </a>
      </li>
      {% endfor %}
    </ul>
  </div>
  {#tab页对应内容#}
  <div class="tab-content m10-b">
    {% for one in navDatas %}
    <div class="tab-pane" id="{{one.id}}"></div>
    {% endfor %}
  </div>
</div>
```