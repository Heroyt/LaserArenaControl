#include <MsgBoxConstants.au3>

#include "functions.au3"

Func end()
	$activeWin = WinGetHandle("[ACTIVE]")
	$win = WinActivate("LaserMaxx EVO-5 Console")
	Sleep(200)
	$status = GetGameStatus()
	If $status = "PLAYING" Then
		ControlClick($win, "", 366) ;Click - Stop Game
		ControlClick($win, "", 366) ;Click - Stop Game
	ElseIf $status = "ARMED" Then
		 ControlClick($win, "", 360) ;Click - End Script
		 ControlClick($win, "", 360) ;Click - End Script
	EndIf
	Sleep(1000)
	WinActivate($activeWin)
EndFunc