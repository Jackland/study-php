@echo off

::pause
%1 mshta vbscript:CreateObject("Shell.Application").ShellExecute("cmd.exe","/c %~s0 ::","","runas",1)(window.close)&&exit

:Admin
echo Success Get Administrator Privilege
net stop searchd
D:/sphinx3/bin/indexer -c D:/sphinx3/bin/sphinx.conf --all --rotate
net start searchd

