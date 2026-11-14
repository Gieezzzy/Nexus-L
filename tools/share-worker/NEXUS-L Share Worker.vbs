' Runs the NEXUS-L share worker hidden, and starts it again if it ever stops (it applies the Lab Docs file changes to \192.168.32.130).
' Its output goes to E:\html\NEXUS-L\writable\logs\share_worker.out
Set sh = CreateObject("WScript.Shell")
Do
    sh.Run "cmd /c ""cd /d E:\html\NEXUS-L && C:\xampp\php\php.exe spark share:worker >> writable\logs\share_worker.out 2>&1""", 0, True
    WScript.Sleep 5000
Loop
