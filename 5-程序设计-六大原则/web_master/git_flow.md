
### 0x00 分支说明

**严格按照一个功能(需求)对应一个分支**

**推送之前, 先拉取**

当前拥有三个(类)分支
#####  1. `master`分支 
- 名字：`master`
- 说明：
  - 主分支
  - 上线分支，代码始终和线上保持一致，上线时也是基于此分支更新。
  - 为所有新功能分支的父分支，每当有新功能开发时，基于此分支创建新分支。
  - 该分支禁止删除，禁止直接推送
##### 2. `test` 分支
 - 名字：`test/35`或者`test/17`
 - 说明：
   - 测试分支
   - `test/35`为第一道测试分支
   - `test/17`为第二道测试分支
   - 推送到测试分支上，对应的测试环境会自动拉取最新代码(基于Jenkins, 自动化部署)
   - 该分支禁止删除
##### 3. `feature` 分支
 - 名字：`feature/N-***`  `N-***`为需求号
 - 说明：
   - 功能分支 (开发分支)  
   - 用于开发新需求
   - 新分支是基于`master`分支创建的
   - 一般情况，需求上线之后，可以自行删除该分支

### 0x01 开发

##### 1.单人开发一个需求(功能) 需求号为 N-001

 - 基于`master`分支创建一个新分支`feature/N-001`
```
git checkout master
git pull origin master
git checkout -b feature/N-001
```
 - 本地开发 提交
```
git add file
```
上面是添加指定文件，下面是本地所有修改/添加的文件
```
git add .
```
提交到本地仓库，注意一定要写说明
```
git commit -m "DEVELOP: N-001 需求标题[--提交说明]"
```
推送到远程仓库
```
git push origin feature/N-001:feature/N-001
```

##### 2.多人开发一个需求(功能) 需求号为 N-002
和1的差别就是 需要有个人 先在本地创建一个分支，然后推送到远程仓库，然后其他人拉取这个分支，进行开发。

###### 先创建分支(一般为组长),并推送到远程仓库
```
git checkout master
git pull origin master
git checkout -b feature/N-002
git push origin feature/N-002:feature/N-002
```
###### 其他人开发

 - 先拉取,并切换到`feature/N-002`分支
```
git pull
git checkout feature/N-002
```
 - 本地开发 提交
```
git add file
```
上面是添加指定文件，下面是本地所有修改/添加的文件
```
git add .
```
提交到本地仓库，注意一定要写说明
```
git commit -m "feature: N-001 需求标题[--提交说明]"
```
推送到远程仓库
```
git push origin feature/N-001:feature/N-001
```

### 0x02 测试

1. 提交到测试分支的时候，先提交到第一道测试`test/35`,测试没问题后，再提交到`test/17`。
2. 合并的时候注意保证合并分支和被合并分支都是最新的。
3. 均是从功能分支合并到测试分支。
4. 合并具体操作：
 - 先切换到功能分支，保证该分支最新(单人开发可以省略)
```
git checkout feature/N-001
git pull origin feature/N-001
```
   - 切换到`test/35`分支，进行合并（如果有冲突就解决冲突）
```
git checkout test/35
git pull origin test/35
git merge feature/N-001
git commit -m "Merge: 合并说明"(如果有冲突需要解决冲突后手动提交)
git pull origin test/35(养成好习惯，推送前先拉取一次)
git push origin test/35:test/35
```

提交到第二道测试`test/17`,类似操作。

### 0x03 上线

1. ***该操作只能组长来操作***
2. 确认测试通过后，才能上线。
3. 具体操作：

 - 先切换到功能分支，保证该分支最新
```
git checkout feature/N-001
git pull origin feature/N-001
```
 - 再切换到`master`分支，创建一个用于线上测试`master-1212-***`分支，并把功能分支合并到这个分支上; 如果有多个需求一起上线，则一起合并到这个分支上。
```
git checkout master
git pull origin master
git checkout -b master-1212-001
git merge feature/N-001
git commit -m "Merge: 合并说明"(如果有冲突需要解决冲突后手动提交)
git pull origin (养成好习惯，推送前先拉取一次)
git push origin master-1212-001:master-1212-001
```
 - 然后到线上测试环境拉取该分支,并进行测试
```
git pull
git checkout master-20191212
```
 - 测试通过后, 把该分支合并到`master`分支
```
git checkout master-20191212
git pull origin master-20191212
git checkout master
git pull origin master
git merge master-20191212
git push origin master
```
 - 到线上环境，拉取`master`
```
git checkout master
git pull origin master
```
 - 如果添加或修改组件，需要更新组件
```
cd system
composer install --no-dev
```
 - 如果有增加配置项，则修改`.env.php`
 - 如果有修改SQL，则执行SQL文件。

##### 注意：
1. `master-2019****`为临时分支，上线完成后，即删除。每次上线时，该分支都是基于最新的`master`分支拉取的。
2. 如果线上测试环境，某一需求测试未通过或者延后上线，其他需求仍要上线的，需要针对上线的需求重新执行 **上线** 操作

### 0x04 GIT提交信息规范
根据我们项目实际情况，暂定以下GIT提交信息规范
```
<type>[(<scope>)]: <code> <subject>

[<body>]
```
#### type[必须]
用于说明 commit 的类别，只允许使用下面几种标识：
 - `feature`: 新功能、新需求(feature)
 - `fixbug`: 修复bug
 - `docs`: 修改文档 (documentation)
 - `style`: 修改格式(缩进、换行符、换行)(不影响代码运行)(修改HTML/CSS,不在此列)
 - `refactor`: 重构(不新增功能、也不修改bug)
 - `perf`: 优化
 - `test`: 测试相关修改
 - `chore`: 构建过程&辅助工具/组件的变动
 - `revert`: 撤销上一次修改
 - `merge`: 分支合并

#### scope[可选]
用于说明修改、影响的范围：
 - `all`：表示大范围的修改
 - `module`：模块名，表示修改了某个模块 
  
#### code[必须]
需求号 如果多个 用"+"号连接且"+"两边留有空格
比如:
```
1000001 + 100002
```
#### subject[必须]
对此次提交变动的简要说明，尽量简单。

#### body[可选]
和上面保留一个空格，对`subject`的详细描述


#### DEMO
```
feature(pormotions): 101594 Add banner request

Add a banner type promotion application page.
```
