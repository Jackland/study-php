# Action 请求操作

## 后续需求跟进

- 系统代码中不应该直接使用 new Action() 的方式，使用 redirect 替换，或者使用 Throw HttpException 的方式

- 移除系统中对就的 Action 的使用，替换为 Framework\Action\Action