import cv2
import mysql
import signal
import os, time
import requests
import urllib.parse
import numpy as np
from urllib.request import urlopen
from urllib import request
from viapi.fileutils import FileUtils
from aliyunsdkcore.client import AcsClient
from aliyunsdkimagerecog.request.v20190930 import RecognizeImageStyleRequest

"""
获取文件路径， 文件名， 后缀名
:param fileUrl:
:return:
"""
def getExtension(fileUrl):
    list = fileUrl.split('!')
    url = list[0]
    arr = url.split(".")
    return arr[len(arr)-1]

#获取图片风格
def getPicStyle(pic_url):
    res = dict()
    r = requests.head(pic_url)
    if r.status_code == requests.codes.ok:
        accessKey = ""
        accessSecret = ""
        file_utils = FileUtils(accessKey, accessSecret)
        ex = getExtension(pic_url)
        oss_url = file_utils.get_oss_url(pic_url, ex, False)
        #创建 AcsClient 实例
        client = AcsClient(accessKey, accessSecret, "cn-shanghai")
        request = RecognizeImageStyleRequest.RecognizeImageStyleRequest()
        request.set_Url(oss_url)
        response = client.do_action_with_exception(request)
        str1 = str(response, encoding="utf-8")
        res = eval(str1)
        res['code'] = 200
    else:
        res['code'] = 400
    return res

# 选择数据库
def db_select():
    # 线上
    try:
        mc = mysql.MysqlConnect()
        return mc
    except Exception as e:
        write_log('python_error.txt',str(e))


"""
 将文档按字符排序从少到多或从多到少
  tudun_project_pic中project_id对应的是tudun_group,class_pid对应的是tudun_project_class
"""
def get_data(start, limit):
    mc = db_select()
    sql = ''
    sql = sql % (start, limit)
    print(sql)
    data = mc.select(sql)
    return data

# 将识别的数据入库
def addcheckImg(data):
    try:
        for b in data:
                style = getPicStyle(b[0])
                print("run " + b[0] + "\n")
                if style['code'] == 200:
                    #如何扫描出来 已经存在原图和地址
                    exits = checkImgExits(b)
                    style_id = []
                    style_dict = {}
                    for sl in style['Data']['Styles']:
                        if sl == 'unstyle':
                            continue
                        id = style_dict[sl]
                        style_id.append(id)
                        sql = ''
                        add_data(sql, (b[2], id))

                    if exits == '':
                        datetime = time.strftime('%Y-%m-%d %H:%M:%S',time.localtime(time.time()))
                        # 准备sql
                        sql = ''
                        lastId = add_data(sql, (b[3], b[0], b[2], ','.join(style_id), datetime, b[1]))
                        print('last instert id-' + str(lastId))
                        write_log('python_ins.txt', '自增id--%s.' % str(lastId))

                else:
                    print("img fail " + b[0] + "\n")
                time.sleep(0.5)

    except Exception as e:
        write_log('python_error.txt',str(e))

"""
判断是否存在当前图片
如果已经存在干扰图不添加
如果判断该图片地址发生变化修改改图片地址

"""
def checkImgExits(val):
    sql = ""
    sql = sql % (val[3])
    mc = db_select()
    data = mc.select(sql)

    if data:
        return data[0]
    else :
        return ''

# 写入数据
def add_data(sql,data):
    mc =db_select()
    last = mc.exec_data(sql,data)
    return last


# 写入错误日志
def write_log(name = 'python_error.txt',str='msg'):
    path = 'pic_style_log/'
    isExists = os.path.exists(path)
    if not isExists:
        os.makedirs(path)
    name= 'pic_style_log/'+name
    fhLog = open(name, "a",encoding='utf8')
    date = time.strftime("%Y-%m-%d %H:%M:%S", time.localtime())
    fhLog.write(date + ' === ' + str+'\n')
    fhLog.close()

def checkStyle(style_list):
    if len(style_list) == 0:
        return True
    elif len(style_list) == 1 and style_list[0] in [16, 6, 8]:
        return True
    elif len(style_list) == 2 and 16 in style_list and 6 in style_list:
        return True
    elif len(style_list) == 2 and 15 in style_list and 6 in style_list:
        return True
    else:
        return False


if __name__ == '__main__':
    page = 1
    limit = 100
    while True:
        print(str(page) + " is start\n")
        start = (page - 1) * limit
        data = get_data(start, limit)
        if not data:
            print("finish")
            break
        else:
            addcheckImg(data)

        page = page + 1


