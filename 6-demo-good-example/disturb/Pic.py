import multiprocessing
import style
import os
import signal
if __name__ == '__main__':
    # 多进程 6核
    p = multiprocessing.Pool(multiprocessing.cpu_count())  #
    print('start shell...')
    data = style.get_data(0, 300000)
    if data:
        for i in range(1):
            try:
                p.apply_async(style.addcheckImg, args=(i, data))
                print('Waiting for all subprocesses done...')
            except Exception as e:
                print(e.__cause__)
                # 关闭进程和子进程
                os.killpg(os.getpgid(os.getpid()), signal.SIGKILL)
    p.close()
    p.join()
    print('All subprocesses done.')
    exit()

