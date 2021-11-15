@echo off
::pause
%1 mshta vbscript:CreateObject("Shell.Application").ShellExecute("cmd.exe","/c %~s0 ::","","runas",1)(window.close)&&exit

::D:/sphinx3/bin/indexer -c D:/sphinx3/bin/sphinx.conf doc_delta_test --rotate
D:/sphinx3/bin/indexer -c D:/sphinx3/bin/sphinx.conf oc_product_description_test --rotate

::D:/sphinx3/bin/indexer -c D:/sphinx3/bin/sphinx.conf  --merge oc_product_description_test doc_delta_test --rotate

del /s D:\project\yzc_task_work_test\*.mdmp