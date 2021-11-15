(function (name) {
  if (window[name]) {
    return
  }

  window[name] = {
    name: '',
    value: '',
    init(name, value) {
      this.name = name;
      this.value = value;
      if (this.value) {
        // 给所有 post 表单加入隐藏域信息
        this.injectForm()
        // 给所有 xhr 请求加入头信息
        this.injectXMLHttpRequest()
      }
    },
    injectForm() {
      const forms = document.querySelectorAll('form[method="post"]')
      for (let i = 0; i < forms.length; i++) {
        let form = forms[i]
        if (form.hasAttribute('injected-session-auth')) {
          // 防止同一个表单重复注入多次
          continue;
        }
        if (form.childNodes[0]) {
          form.insertBefore(this.createInput(), form.childNodes[0])
        }
        form.setAttribute('injected-session-auth', 'yes')
      }
    },
    isXMLHttpRequestInjected: false,
    injectXMLHttpRequest() {
      if (this.isXMLHttpRequestInjected) {
        // 不能重复注入，否则会导致无限调用
        return
      }

      const _this = this;
      XMLHttpRequest.prototype._nativeOpen = XMLHttpRequest.prototype.open;
      XMLHttpRequest.prototype._nativeSend = XMLHttpRequest.prototype.send;
      XMLHttpRequest.prototype._needSessionAuth = false;
      XMLHttpRequest.prototype.open = function (method, url, async, user, password) {
        this._needSessionAuth = method === 'POST'
        this._nativeOpen(method, url, async, user, password);
      }

      XMLHttpRequest.prototype.send = function (body) {
        if (this._needSessionAuth) {
          // 放在 header 中而非 body 中是因为 body 的情况比较复杂，存在 json/x-www-form-urlencoded/form-data
          this.setRequestHeader(_this.name, _this.value);
        }

        const _onreadystatechange = this.onreadystatechange;
        this.onreadystatechange = function (ev) {
          if (this.readyState === 4) {
            // response 如果是 302，且包含 redirect 字段，则跳转
            if (this.status === 302 && this.getResponseHeader('Content-Type') === 'application/json') {
              const response = JSON.parse(this.responseText);
              if (response.redirect) {
                alert('The account identity has changed. Please refresh the page and try again later.');
                window.location.href = response.redirect;
              }
            }
          }
          if (_onreadystatechange) {
            _onreadystatechange(ev)
          }
        }

        this._nativeSend(body)
      }

      this.isXMLHttpRequestInjected = true
    },
    createInput() {
      const input = document.createElement('input')
      input.setAttribute('type', 'hidden')
      input.setAttribute('name', this.name)
      input.setAttribute('value', this.value)
      return input
    }
  }
})('sessionAuth')
