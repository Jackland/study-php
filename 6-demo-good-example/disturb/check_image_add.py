import multiprocessing
import imageTools
import os
import signal
if __name__ == '__main__':
    # 多进程 6核
    p = multiprocessing.Pool(multiprocessing.cpu_count())  #
    print('start shell...')
    data = imageTools.get_data(0,300000)
    if data:
        for i in range(6):
            try:
                p.apply_async(imageTools.addcheckImg, args=(i, data))
                print('Waiting for all subprocesses done...')
            except Exception as e:
                print(e.__cause__)
                # 关闭进程和子进程
                os.killpg(os.getpgid(os.getpid()), signal.SIGKILL)
    p.close()
    p.join()
    print('All subprocesses done.')
    exit()

