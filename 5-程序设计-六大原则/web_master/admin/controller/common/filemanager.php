<?php

use App\Components\Storage\StorageCloud;
use App\Models\Information\UploadInformation;

class ControllerCommonFileManager extends Controller
{
    const PAGE_LIMIT = 16;//文件夹分页

    public function index()
    {
        $this->load->language('common/filemanager');

        if (isset($this->request->get['filter_name'])) {
            $filter_name = trim($this->request->get['filter_name']);
            if (strstr($filter_name,'%')) {
                $filter_name = str_replace('%','\%',$filter_name);
            }

        } else {
            $filter_name = '';
        }


        // Make sure we have the correct directory
        $pid = $this->request->query->get('pid', 0);
        $folderId = $this->request->query->get('id', 0);
        $parent = $this->request->query->get('parent', false);
        $refresh = $this->request->query->get('refresh', false);
        $directory = $this->request->query->get('directory', false);
        $pageLimit = $this->request->query->get('page_limit',self::PAGE_LIMIT );

        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }

        $data['images'] = array();

        $this->load->model('tool/image');


        /**
         *返回上级查找pid
         */
        $pidS = UploadInformation::where('id', $pid)->value('pid');
        if ($parent == 1) {
            $folderId = $pidS ?? 0;
            $pid = $pidS ?? 0;
        }

        /**
         *刷新停留当前页面
         */
        $pidRS = UploadInformation::where('id', $pid)->value('pid');
        if ($refresh == 1) {
            $folderId = $pid ?? 0;
            $pid = $pidRS ?? 0;
        }


        $directories = UploadInformation::query()
            ->where(function ($q) use ($folderId, $filter_name) {
                if ($filter_name) {
                    $folderId && $q->where('pid', $folderId);
                    $q->where('folder', 'like', "%{$filter_name}%")
                        ->orWhere('file_name', 'like', "%{$filter_name}%");
                } else {
                    $q->where('pid', $folderId);
                }
            })
            ->where('folder', '!=', '')
            ->get(['folder', 'id', 'pid'])
            ->toArray();

        $files = UploadInformation::query()
            ->where(function ($q) use ($folderId, $parent, $filter_name) {
                if ($filter_name) {
                    $folderId && $q->where('pid', $folderId);
                    $q->where('file_name', 'like', "%{$filter_name}%");
                }else{
                    $q->where('pid', $folderId);
                }
            })
            ->where('file_path', '!=', '')
            ->get()
            ->toArray();
        $images = array_merge($directories, $files);
        $image_total = count($images);
        $images = array_splice($images, ($page - 1) * $pageLimit, $pageLimit);

        foreach ($images as $image) {
            if (!isset($image['file_path'])) { //如果是目录
                $data['images'][] = array(
                    'thumb' => '',
                    'id' => $image['id'],
                    'name' => rtrim($image['folder'], '/'),
                    'type' => 'directory',
                    'path' => $image['folder'],
                    'href' => $this->url->to([
                        'common/filemanager',
                        'user_token' => session('user_token'),
                        'pid' => $image['pid'],
                        'id' => $image['id'],
                        'directory' => $image['folder'],
                        'target' => $this->request->get['target'] ?? '',
                        'thumb' => $this->request->get['thumb'] ?? '',
                    ])
                );
            } else {
                $data['images'][] = array(
                    'thumb' => StorageCloud::root()->getImageUrl($image['file_path']),
                    'name' => $image['file_name'],
                    'type' => 'image',
                    'id' => $image['id'],
                    'path' => $image['file_path'],
                    'href' => StorageCloud::root()->getImageUrl($image['file_path']),
                    'directory' => $image['folder'],
                    'target' => $this->request->get['target'] ?? '',
                );
            }
        }

        $data['parent'] = $this->url->to([
            'common/filemanager',
            'user_token' => session('user_token'),
            'id' => $pid,
            'pid' => $folderId,
            'target' => $this->request->get('target', ''),
            'thumb' => $this->request->get('thumb' , ''),
            'parent' => true
        ]);
        $data['refresh'] = $this->url->to([
            'common/filemanager',
            'user_token' => session('user_token'),
            'id' => $pid,
            'pid' => $folderId,
            'target' => $this->request->get('target',''),
            'thumb' => $this->request->get('thumb',''),
            'refresh' => true,
        ]);

        $data['user_token'] = session('user_token');
        $data['pid'] = $pid;
        $data['folder_id'] = $folderId;
        $data['directory'] = $directory;
        $data['thumb'] = $this->request->get('thumb', '');
        $data['target'] = $this->request->get('target', '');
        $data['filter_name'] = $this->request->get('filter_name', '');

        $pagination = new Pagination();
        $pagination->total = $image_total;
        $pagination->page = $page;
        $pagination->limit = $pageLimit;
        $pagination->url = $this->url->link('common/filemanager',
            'user_token=' . session('user_token') . '&id=' . $folderId .
            '&pid=' . $pid . '&filter_name=' . $filter_name.'&target='.$data['target'].'&thumb='.$data['thumb']
            . '&page={page}', true);
        $pagination->renderScript = false;
        $data['pagination'] = $pagination->render();
        $this->response->setOutput($this->load->view('common/filemanager', $data));
    }

    /**
     * description:迁移帮助中心文件到oss 改版
     * author: fuyunnan
     * @param
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     * @throws
     * Date: 2021/6/16
     */
    public function upload()
    {
        $this->load->language('common/filemanager');
        $json = [];
        // Check user has permission
        if (!$this->user->hasPermission('modify', 'common/filemanager')) {
            return $this->jsonFailed($this->language->get('error_permission'), [], 100);
        }
        $directory = $this->request->query->get('directory');
        $pid = $this->request->query->get('pid');

        if (isset($directory) && $directory) {
            $directory = rtrim('/catalog/' . $directory . '/');
        } else {
            $directory = '/catalog/';
        }
        // Make sure we have the correct directory
        $file = $this->request->file('file');
        $add = [];
        for ($i = 0; $i < count($file); $i++) {
            if (!$file[$i] || !$file[$i]->isValid()) {
                $json['error'] = $this->language->get('error_file_upload');
            }
            if (!in_array($file[$i]->getClientMimeType(),
                ['image/jpeg', 'image/pjpeg', 'image/png', 'image/x-png', 'image/gif'])) {
                $json['error'] = $this->language->get('error_filetype');
            }
            // Validate the filename length
            if ((utf8_strlen(($file[$i])->getClientOriginalName()) < 3) || (utf8_strlen($file[$i]->getClientOriginalName()) > 255)) {
                $json['error'] = $this->language->get('error_filename');
            }
            if (isset($json['error']) && $json['error']) {
                $this->response->setOutput(json_encode($json));
            }else{
                $path = StorageCloud::image()->writeFile($file[$i], rtrim($directory, '/'), $file[$i]->getClientOriginalName());
                $add [] = [
                    'file_name' => $file[$i]->getClientOriginalName(),
//                'folder' => $directory,
                    'file_path' => $path,
                    'file_suffix' => $file[$i]->getClientMimeType(),
                    'file_size' => $file[$i]->getSize(),
                    'pid' => $pid,
                ];
            }
        }
        $add && UploadInformation::query()->insert($add);

        if (!$json) {
            $json['success'] = $this->language->get('text_uploaded');
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * description:创建文件夹
     * author: fuyunnan
     * @param
     * @return void
     * @throws
     * Date: 2021/6/18
     */
    public function folder()
    {
        $this->load->language('common/filemanager');
        $json = [];
        if (!$this->user->hasPermission('modify', 'common/filemanager')) {
            $json['error'] = $this->language->get('error_permission');
        }
        $directory = $this->request->post('folder', '');
        $pid = $this->request->get('pid', 0);

        if (request()->isMethod('POST')) {
            if ((utf8_strlen(trim($directory)) < 3) || (utf8_strlen(trim($directory)) > 128)) {
                $json['error'] = $this->language->get('error_folder');
            }
            if (strpos(strip_tags($directory), '/') !== false) {
                $json['error'] = $this->language->get('error_directory');
            }
            if (preg_match('/\/|\~|\!|\@|\#|\\$|\%|\^|\&|\*|\(|\)|\_|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\.|\/|\;|\'|\`|\-|\=|\\\|\|/', $directory)) {
                $json['error'] = $this->language->get('error_folder_characters');
            }


        }
        if (!isset($json['error'])) {
            UploadInformation::query()->updateOrInsert([
                'folder' => trim(strtolower(html_entity_decode($directory) )). '/',
                'pid' => $pid
            ]);
            $json['success'] = $this->language->get('text_directory');
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * description:删除阿里云的图片
     * author: fuyunnan
     * @param
     * @return void
     * @throws
     * Date: 2021/6/21
     */
    public function delete()
    {
        $this->load->language('common/filemanager');
        $paths = $json = $delPid = [];
        // Check user has permission
        if (!$this->user->hasPermission('modify', 'common/filemanager')) {
            $json['error'] = $this->language->get('error_permission');
        }
        if (!isset($this->request->post['path']) || empty($this->request->post['path'])) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (isset($this->request->post['path']) && $this->request->post['path']) {
            $paths = $this->request->post['path'];
        }



        $ids = UploadInformation::query()->whereIn('id', $paths)->get()->toArray();

        // Loop through each path to run validations
        try {
            db()->getConnection()->beginTransaction();
            if ($ids) {
                foreach ($ids as $path) {
                    if ($path['file_path'] == '') {
                        //TODO 暂时禁止删除oss文件夹
//                        StorageCloud::image()->deleteDirectory('catalog/' . $path['folder']);
                        $delPid[] = $path['id'];
                        continue;
                    }
                    //TODO 暂时禁止删除oss文件
//                    storageCloud::root()->delete($path['file_path']);
                }

                UploadInformation::query()->whereIn('id', $paths)->delete();
                $delPid && UploadInformation::query()->whereIn('pid', $delPid)->delete();
            }
            db()->getConnection()->commit();
        } catch (Exception $e) {
            db()->getConnection()->rollBack();
            $json['error'] = $this->language->get('error_delete');
        }
        if (!$json) {
            $json['success'] = $this->language->get('text_delete');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }
}
