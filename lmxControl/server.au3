#include <MsgBoxConstants.au3>
#include <StringConstants.au3>

#include "TCPServer.au3"
#include "functions.au3"
#include "load.au3"
#include "start.au3"
#include "end.au3"

_TCPServer_OnReceive("received")
_TCPServer_DebugMode(True)
_TCPServer_SetMaxClients(10)

_TCPServer_Start(8081)

Func received($iSocket, $sIP, $sData, $sPar)
	$data = StringRegExp($sData, "([a-zA-Z]*?):(.*)", $STR_REGEXPARRAYFULLMATCH)
	If @error Then Return
	$action = $data[1]
	$parameter = $data[2]
	 For $i = 0 To UBound($data) - 1
		ConsoleWrite(@CRLF & @MIN & ":" & @SEC & " > [" & $i & "] " & $data[$i])
   Next
   $status = GetGameStatus()
   If $status = "DOWNLOAD" Then
   		Switch $action
			Case "retryDownload"
				$win = WinGetHandle("Downloading Scores from Packs")
				ControlClick($win, "", "[CLASS:ThunderRT6CommandButton; INSTANCE:2]")
				_TCPServer_Send($iSocket, "ok")
			Case "cancelDownload"
				$win = WinGetHandle("Downloading Scores from Packs")
				ControlClick($win, "", "[CLASS:ThunderRT6CommandButton; INSTANCE:3]")
				_TCPServer_Send($iSocket, "ok")
			Case Else
				_TCPServer_Send($iSocket, "DOWNLOAD")
		EndSwitch
   Else
		Switch $action
			Case "load"
				load($parameter)
				_TCPServer_Send($iSocket, "ok")
			Case "start"
				start()
				_TCPServer_Send($iSocket, "ok")
			Case "end"
				end()
				_TCPServer_Send($iSocket, "ok")
			Case "status"
				_TCPServer_Send($iSocket, $status)
			Case Else
				_TCPServer_Send($iSocket, "invalid command")
		EndSwitch
	EndIf
		 _TCPServer_Close($iSocket)
EndFunc   ;==>received

While 1
   Sleep(100)
WEnd