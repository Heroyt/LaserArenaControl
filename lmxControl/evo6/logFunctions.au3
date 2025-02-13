#include-once

; Register the shutdown handler
OnAutoItExitRegister("ShutdownLog")

Global $g_logDir = IniRead("C:\LaserMaxx\shared\lmxControl.ini", "Log", "Dir", "C:\LaserMaxx\shared\logs\")

; Global log file handle to allow debug logging from anywhere
Global $g_logFile = FileOpen($g_logDir & "LMXControlServer.log", $FO_APPEND)

; Log an error message to a file
Func ErrorLog($message, $filename = "", $line = "")
	Local $logFile = $g_logDir & "LMXControl.log"
    Local $dateTime = @YEAR & "/" & @MON & "/" & @MDAY & " " & @HOUR & ":" & @MIN & ":" & @SEC
    If $filename <> "" And $line <> "" Then
    	$message &= " (in " & $filename & " at line " & $line & ")"
    EndIf
	FileWriteLine($logFile, "[" & $dateTime & "] Error: " & $message)
EndFunc

; Log a debug message to the $g_logFile
Func DebugLog($handle, $message, $filename = "", $line = "")
	If $g_logFile = -1 Then
		Return
	EndIf

	Local $dateTime = @YEAR & "/" & @MON & "/" & @MDAY & " " & @HOUR & ":" & @MIN & ":" & @SEC
	If $filename <> "" And $line <> "" Then
		$message &= " (in " & $filename & " at line " & $line & ")"
	EndIf

	$message = "[" & $dateTime & "] Debug: " & $message

	ConsoleWrite(@CRLF & $message)
	FileWriteLine($g_logFile, $message)
EndFunc

; Shutdown function - should close the $g_logFile handle
Func ShutdownLog()
	DebugLog($g_logFile, "Shutting down", @ScriptName, @ScriptLineNumber)
	FileClose($g_logFile)
EndFunc