<?php

use App\Catalog\Controllers\BaseController;
use App\Catalog\Forms\Test\TestForm;
use App\Catalog\Search\Test\TestSearch;
use App\Catalog\Search\Test\TestWithRequestFormSearch;
use App\Components\FormSubmitRepeatChecker;
use App\Components\Storage\StorageCloud;
use App\Components\Storage\StorageLocal;

class ControllerCommonTest extends BaseController
{
    // 测试 DataProvider 的 Search
    // GET /index.php?route=common/test/testSearch&customer_id=1
    public function testSearch()
    {
        $search = new TestSearch();
        $dataProvider = $search->search($this->request->get());

        return $this->json([
            'count' => $dataProvider->getTotalCount(),
            'list' => $dataProvider->getList(),
        ]);
    }

    // 测试 RequestForm
    // POST /index.php?route=common/test/testRequestForm name=abc&user_name=un
    public function testRequestForm(TestForm $form)
    {
        $data = $form->doSomething();

        return $this->json($data);
    }

    // 测试 search 结合 RequestForm
    // GET /index.php?route=common/test/testSearchWithRequestForm&customer_id=1
    public function testSearchWithRequestForm(TestWithRequestFormSearch $search)
    {
        $dataProvider = $search->search();

        return $this->json([
            'count' => $dataProvider->getTotalCount(),
            'list' => $dataProvider->getList(),
        ]);
    }

    // 测试 storage 上传和下载
    // 上传文件 POST /index.php?route=common/test/testStorageGetSet file=[xx]
    // 获取文件 GET /index.php?route=common/test/testStorageGetSet&name=test/uploadDemo/Qpl8I1huSUi0XlkjDRxIKrDjqM6i31qQJ3n2tzX6.jpeg
    public function testStorageGetSet()
    {
        if ($this->request->isMethod('POST')) {
            // 上传文件
            // 校验文件
            $validator = $this->request->validate([
                // 验证参数必须存在，并且是文件，最小10kb，最大1024kb，文件后缀名碧玺是xls、csv、xlsx
                'file' => 'required|file|min:10|max:10240|extension:png,jpg',
            ]);
            if ($validator->fails()) {
                return $this->jsonFailed($validator->errors()->first());
            }
            // 获取上传的文件
            $file = $this->request->file('file');
            // 上传文件 ps:必须制定上传的目录，这里以'test'为例
            $path = StorageCloud::test()->writeFile($file, 'uploadDemo');
            $data = [
                'path' => $path,//文件全路径
                'url' => StorageCloud::root()->getUrl($path),//文件url路径
                'size' => StorageCloud::root()->fileSize($path),//文件大小 单位（字节）
                'org_name' => $file->getClientOriginalName(),//文件在客户端的名字
                'ext' => $file->getClientOriginalExtension(),//文件扩展名
            ];
            return $this->jsonSuccess($data);
        }
        // 获取文件
        $name = $this->request->get('name', 'default');
        if (!StorageCloud::root()->fileExists($name)) {
            return $this->jsonFailed("{$name} 不存在");
        }
        $data = [
            'path' => StorageCloud::root()->getUrl($name),
            'resize' => StorageCloud::root()->getUrl($name, ['w' => 100]),
        ];
        return $this->jsonSuccess($data);
    }

    // 测试 storage 下载
    // GET /index.php?route=common/test/testStorageDownload
    public function testStorageDownload()
    {
        return StorageLocal::root()->browserDownload('download/import-csv-file-template.csv');
    }

    // 测试表单重复提交
    // 打开表单页面 GET /index.php?route=common/test/testFormSubmitRepeat
    // 提交表单 POST /index.php?route=common/test/testFormSubmitRepeat
    // 表单提交第一次成功，第二次再提交返回重复提交
    public function testFormSubmitRepeat()
    {
        if ($this->request->isMethod('POST')) {
            // 表单提交后校验
            if (FormSubmitRepeatChecker::create()->isSubmitRepeat()) {
                // 重复提交
                return $this->jsonFailed('重复提交');
            }
            // 实际业务
            return $this->jsonSuccess();
        }
        // 展示表单前初始化
        FormSubmitRepeatChecker::create()->initForm();
        return $this->json('页面渲染');
    }
}
