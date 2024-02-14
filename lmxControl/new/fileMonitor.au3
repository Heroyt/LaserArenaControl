#include <File.au3>
#include <WinAPIProc.au3>
#include <WinAPIFiles.au3>

#include "logFunctions.au3"

Global $fileMonitorLogFile = FileOpen($g_logDir & "LMXControlFileMonitor.log", $FO_APPEND)

; Register the shutdown handler
OnAutoItExitRegister("CloseMonitorChange")

Global $resultPath = IniRead("C:\LaserMaxx\shared\lmxControl.ini", "Result", "Dir", "C:\LaserMaxx\shared\results")
Global $resultPathSend = IniRead("C:\LaserMaxx\shared\lmxControl.ini", "Result", "DirSend", "lmx/results/")
Global $serverHost = IniRead("C:\LaserMaxx\shared\lmxControl.ini", "TCP", "Host", "http://lac.local")
Global $updatePath = IniRead("C:\LaserMaxx\shared\lmxControl.ini", "TCP", "Update", "/api/results/import")
Global $mountPath = IniRead("C:\LaserMaxx\shared\lmxControl.ini", "TCP", "Update", "/api/mount")

DebugLog($fileMonitorLogFile, "Result path: " & $resultPath, @ScriptName, @ScriptLineNumber)

Global $hWatch[1]
$hWatch[0] = _WinAPI_FindFirstChangeNotification($resultPath, $FILE_NOTIFY_CHANGE_FILE_NAME +$FILE_NOTIFY_CHANGE_ATTRIBUTES + $FILE_NOTIFY_CHANGE_SIZE + $FILE_NOTIFY_CHANGE_LAST_WRITE, True)

If $hWatch[0] = 0 Then
ErrorLog("Watch failed")
EndIf

Local $tObjs = DllStructCreate('ptr')
Local $paObj = DllStructGetPtr($tObjs)
DllStructSetData($tObjs, 1, $hWatch[0])

Local $sending = False
$oMyError = ObjEvent("AutoIt.Error","MyErrFunc")

; Define actions that should trigger after a file change
Func SendChangeNotification()
	If ($sending) Then
		Return
	EndIf
	Local $oHTTP = ObjCreate("WinHttp.WinHttpRequest.5.1")
	$sending = True
	$oHTTP.Open("POST", $serverHost & $updatePath, False)
	If (@error) Then
		ErrorLog("Cannot create POST request", @ScriptName, @ScriptLineNumber)
		$sending = False
		Return
	EndIf
	$oHTTP.SetRequestHeader("Content-Type", "application/x-www-form-urlencoded")
	$oHTTP.Send("dir=" & $resultPathSend)
	If (@error) Then
    	ErrorLog("Cannot send POST request", @ScriptName, @ScriptLineNumber)
		$sending = False
    	Return
    EndIf
	Local $StatusCode = $oHTTP.Status
	Local $Message = $oHTTP.ResponseText
	DebugLog($fileMonitorLogFile, "Server responded with status code: " & $StatusCode & @CRLF & "Body: " & $Message, @ScriptName, @ScriptLineNumber)
	$sending = False
EndFunc

Func MonitorChange()
	;While 1
		Local $dwWait = _WinAPI_WaitForMultipleObjects(1, $paObj, False, 0)
		Select
			Case $dwWait = 0
				; Event occurred, do action
				SendChangeNotification()
				; Re-arm the watch
				_WinAPI_FindNextChangeNotification($hWatch[0])
		EndSelect
	;WEnd
EndFunc

Func CloseMonitorChange()
	FileClose($fileMonitorLogFile)
	_WinAPI_FindCloseChangeNotification($hWatch[0])
EndFunc

Func MyErrFunc()
	ErrorLog("An error has occured! " & $oMyError.description, @ScriptName, @ScriptLineNumber)
    $sending = False
EndFunc