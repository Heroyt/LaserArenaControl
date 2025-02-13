#include <MsgBoxConstants.au3>

#include "config.au3"
#include "functions.au3"
#include "logFunctions.au3"

Func end()
	; Save currently active window handle for later
    Local $activeWin = WinGetHandle("[ACTIVE]")
	; Activate the main window
    Local $win = WinWaitActivate($g_WinName)
    If $win = 0 Then
    	ErrorLog("Cannot activate the main window for load", @ScriptName, @ScriptLineNumber)
    	Return False
    EndIf

	Sleep(200)

	Local $status = GetGameStatus()

	If $status = "PLAYING" Then
		ClickButton($win, $g_StopGameBtnId) ;Click - Stop Game
		ClickButton($win, $g_StopGameBtnId) ;Click - Stop Game
	ElseIf $status = "ARMED" Then
		 ClickButton($win, $g_CancelGameBtnId) ;Click - End Script
		 ClickButton($win, $g_CancelGameBtnId) ;Click - End Script
	EndIf

	; Wait for the game load process to end, otherwise the window will become active again after and the return to the previously active window will not work
	Sleep(1000)

	; Return to the previously active window
    ; Doesn't matter if it fails
	WinActivate($activeWin)

	Return True
EndFunc