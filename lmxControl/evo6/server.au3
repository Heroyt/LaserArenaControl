#include <StringConstants.au3>
#include <FileConstants.au3>

#include "config.au3"
#include "TCPServer.au3"
#include "functions.au3"
#include "logFunctions.au3"
#include "load.au3"
#include "start.au3"
#include "end.au3"
#include "fileMonitor.au3"

Opt("TrayAutoPause",0)
Opt("TrayIconDebug",1)

_TCPServer_OnReceive("received")
_TCPServer_DebugMode(True)
_TCPServer_SetMaxClients(10)

_TCPServer_Start($g_tcpPort)

Func received($iSocket, $sIP, $sData, $sPar)
	Local $data = StringRegExp($sData, "([a-zA-Z]*?):(.*)", $STR_REGEXPARRAYFULLMATCH)
	If @error Then
		ErrorLog("Cannot parse regex data", @ScriptName, @ScriptLineNumber)
		Return
	EndIf
	Local $action = $data[1]
	$parameter = $data[2]
	For $i = 0 To UBound($data) - 1
		DebugLog($g_logFile, "Received data: " & " > [" & $i & "] " & $data[$i], @ScriptName, @ScriptLineNumber)
	Next
	Local $status = GetGameStatus()
	If $status = "DOWNLOAD" Then
		DebugLog($g_logFile, "Downloading scores", @ScriptName, @ScriptLineNumber)
		Local $win = WinGetHandle($g_WinName)
		Switch $action
			Case "retryDownload"
                ClickButton($win, $g_RedownloadScoresBtnId)
				_TCPServer_Send($iSocket, "ok")
			Case "cancelDownload"
                ClickButton($win, $g_CancelScoresDownloadBtnId)
                ClickButton($win, $g_CancelScoresDownloadBtnId)
				_TCPServer_Send($iSocket, "ok")
			Case Else
				_TCPServer_Send($iSocket, "DOWNLOAD")
		EndSwitch
	Else
		Switch $action
			Case "load"
				DebugLog($g_logFile, "Load game", @ScriptName, @ScriptLineNumber)
				If load($parameter) Then
					_TCPServer_Send($iSocket, "ok")
				Else
					_TCPServer_Send($iSocket, "error")
				EndIf
			Case "start"
				DebugLog($g_logFile, "Start game", @ScriptName, @ScriptLineNumber)
				If start() Then
					_TCPServer_Send($iSocket, "ok")
				Else
                	_TCPServer_Send($iSocket, "error")
                EndIf
			Case "loadStart"
				DebugLog($g_logFile, "Load Start game", @ScriptName, @ScriptLineNumber)
				If load($parameter) Then
					If start() Then
						_TCPServer_Send($iSocket, "ok")
					Else
						_TCPServer_Send($iSocket, "error")
					EndIf
				Else
					_TCPServer_Send($iSocket, "error")
				EndIf
			Case "end"
				DebugLog($g_logFile, "End game", @ScriptName, @ScriptLineNumber)
				If end() Then
					_TCPServer_Send($iSocket, "ok")
				Else
                	_TCPServer_Send($iSocket, "error")
                EndIf
			Case "status"
				DebugLog($g_logFile, "Get status", @ScriptName, @ScriptLineNumber)
				_TCPServer_Send($iSocket, $status)
			Case Else
				_TCPServer_Send($iSocket, "invalid command")
		EndSwitch
	EndIf
	_TCPServer_Close($iSocket)
EndFunc	;==>received

; Spawn another thread for file monitor
;$hFileMonitor = DllCallbackRegister("MonitorChange", "int", "ptr")
;_WinAPI_CreateThread($hFileMonitor)

While 1
	MonitorChange()
	Sleep(100)
WEnd