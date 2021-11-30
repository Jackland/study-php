import mysql
import cv2
import numpy as np
import os, time
import signal
from urllib.request import urlopen
from urllib import request
from BackgroundColorDetector import BackgroundColorDetector

picFlag = 0.08
#picFlag = 0.12

"""
 将文档按字符排序从少到多或从多到少

"""
def get_data(start, limit):
    mc = db_select()
    sql = 


    sql = sql % (start, limit)
    data = mc.select(sql)
    return data


# 将识别的数据入库
def addcheckImgSigle(data):
    start = time.time()
    # print('Run task %s (%s)...' % (i, os.getpid()))
    for a, b in enumerate(data):
        print('进行到--%s.' % a)
        state = check(b[0])
        if state == 0:
            # 准备sql
            sql = (""
                   "(%s);")
            lastId = add_data(sql,(b[0]))
            print('last instert id-'+str(lastId))
    end = time.time()
    print('Task runs %0.2f seconds.' % (end - start))


# 将识别的数据入库
def addcheckImg(i,data):
    try:
        start = time.time()
        # 进程的pid
        print('Run task %s (%s)...' % (i, os.getpid()))

        for a, b in enumerate(data):
            # 检查图片是否存在
            if a % 6 == i:
                print('进行到--%s.' % a)
                write_log('process_log_%s.txt' % i,'进行到--%s.' % a)

                """
                如果图片在 404  直接跳过
                """
                try:
                    state = check(b)
                except Exception as ee:
                    continue
                ##2020-9-25新增跑纯色背景图片
                # if state == 1:
                #     image = url_to_image(b)
                #     BgColorDetector = BackgroundColorDetector(image)
                #     state = BgColorDetector.detect()
                ##2020-9-25新增跑纯色背景图片

                if state == 0:
                    #如何扫描出来 已经存在原图和地址
                    exits = checkImgExits(b)
                    if exits == '':
                        # 准备sql
                        sql = ''
                        lastId = add_data(sql, (b[2], b[0]))
                        print('last instert id-' + str(lastId))
                        write_log('python_ins.txt', '自增id--%s.' % str(lastId))
                    # 如果已经存在 并且已经删除过 重新标记状态
                    elif exits[2] == 1:
                        if b[0]!=exits[1]:
                            sqle =""
                            sqle = sqle % (0,b[0],exits[0])
                            edit_data(sqle)
                            write_log('exits_update_url.txt', '[x]--projectId(%s);self_image_id(%s)' % (b[1], b[2]))
                        else:
                            print('[x]--exits status eq 1 ；continue;num --(%s)' % (a))
                            write_log('exits_yes_del.txt', '[x]--projectId(%s);self_image_id(%s)' % (b[1], b[2]))
                    # 如果已经存在且未处理 直接调过
                    elif exits[2] == 0 :
                        print('[x]--exits status eq 0 ；continue;num --(%s)' % (a))
                        write_log('exits_no_del.txt', '[x]--projectId(%s);self_image_id(%s)' % (b[1],b[2]))
        end = time.time()
        print('Task %s runs %0.2f seconds.' % (i, (end - start)))
    except Exception as e:
        write_log('python_error.txt',str(e))

"""
判断是否存在当前图片
如果已经存在干扰图不添加
如果判断该图片地址发生变化修改改图片地址

"""
def checkImgExits(val):
    sql = ""
    sql = sql % (val[2])
    mc = db_select()
    data = mc.select(sql)

    if data:
        return data[0]
    else :
        return ''

# 开始检查图片
def check(val):
    #2020-9-25注释
    # rp=request.Request(val[0])
    # if val[1]:
    #     refer = get_refer(val[1])
    #     if refer:
    #         rp.add_header('Referer', refer)
    #
    # resp = request.urlopen(rp)
    # image = np.asarray(bytearray(resp.read()), dtype="uint8")
    # image = cv2.imdecode(image, cv2.IMREAD_COLOR)
    image = url_to_image(val)
    mean, std = cv2.meanStdDev(image)
    stdArr = np.array([std[::-1] / 255])
    for b in stdArr:
        r = np.round(b[0][0], 5)
        g = np.round(b[1][0], 5)
        b = np.round(b[2][0], 5)
        print(r, g, b)
        if r == g and g == b:
            return  0
        elif r <= picFlag and g <= picFlag and b <= picFlag:
            return 0
        else:
            return 1

"""
获取项目组配置的refer
"""
def get_refer(project_id):
    tup = ()
    exits = tup.__contains__(project_id)
    if exits:
        info = {}
        return info[project_id]
    return '123'

"""

数据库操作or 日志操作===

"""


# 写入错误日志
def write_log(name = 'python_error.txt',str='msg'):
    path = 'python_log/'
    isExists = os.path.exists(path)
    if not isExists:
        os.makedirs(path)
    name= 'python_log/'+name
    fhLog = open(name, "a",encoding='utf8')
    date = time.strftime("%Y-%m-%d %H:%M:%S", time.localtime())
    fhLog.write(date + ' === ' + str+'\n')
    fhLog.close()

# 写入数据
def add_data(sql,data):
    mc =db_select()
    last = mc.exec_data(sql,data)
    return last

# 执行sql
def edit_data(sql):
    mc = db_select()
    last = mc.exec(sql)
    return last

# 选择数据库
def db_select():
    # 线上
    try:
        mc = mysql.MysqlConnect()

        return mc
    except Exception as e:
        write_log('python_error.txt',str(e))


def url_to_image(val):
    # download the image, convert it to a NumPy array, and then read
	# it into OpenCV format
    rp = request.Request(val[0])
    if val[1]:
        refer = get_refer(val[1])
        if refer:
            rp.add_header('Referer', refer)

    resp = request.urlopen(rp)
    image = np.asarray(bytearray(resp.read()), dtype="uint8")
    image = cv2.imdecode(image, cv2.IMREAD_COLOR)
    return image

