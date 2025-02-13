#include <GuiListView.au3>

#include "config.au3"
#include "functions.au3"
#include "logFunctions.au3"

; This function should load configuration from 0000.game file, select the correct game mode and load the game
Func load($mode)
	; Save currently active window handle for later
	Local $activeWin = WinGetHandle("[ACTIVE]")

	; Activate the main window
	Local $win = WinWaitActivate($g_WinName)
	If $win = 0 Then
		ErrorLog("Cannot activate the main window for load", @ScriptName, @ScriptLineNumber)
		Return False
	EndIf

	;Load 0000.game
	If ClickButton($win, $g_LoadGroupBtnId) = False Then ; Should open a new dialog window
		ErrorLog("Load game click failed", @ScriptName, @ScriptLineNumber)
		Return False
	EndIf
	Local $win2 = WinWait($g_LoadGroupWinName, "", 20)
	If $win2 = 0 Then
		;MsgBox($MB_OK, "Error", "Waiting for Load game file window timed out")
		ErrorLog("Load game file window time out", @ScriptName, @ScriptLineNumber)
		Return False
	Endif
	If WinWaitActivate($win2) = 0 Then
		ErrorLog("Failed to activate the load window", @ScriptName, @ScriptLineNumber)
		Return False
	EndIf
	Sleep(200)

	ControlSetText($win2, "", "[CLASS:Edit; INSTANCE:1]", "C:\LaserMaxx\shared\games\0000.game")
	Sleep(200)
	; Click "Load" button, which should load the game config
	If ClickButton($win2, "[CLASS:Button; INSTANCE:1]") = False Then
		ErrorLog("Load game config click failed", @ScriptName, @ScriptLineNumber)
		Return False
	EndIf
	If WinWaitClose($win2, "", 10) = 0 Then
		;MsgBox($MB_OK, "Error", "Waiting for Load game file window timed out")
		ErrorLog("Load window close timeout", @ScriptName, @ScriptLineNumber)
		Return False
	Endif

	; Wait for all loading to finish
	Sleep(500)

	;Set mode
	$win = WinWaitActivate($g_WinName)
	If $win = 0 Then
		ErrorLog("Failed to reactivate the main window", @ScriptName, @ScriptLineNumber)
    	Return False
    EndIf
	Local $hListView = ControlGetHandle($win, "", $g_ModeSelectBox)
	If $hListView = 0 Then
		;MsgBox($MB_OK, "Error", "Cannot find game mode select")
		ErrorLog("Cannot find game mode select", @ScriptName, @ScriptLineNumber)
		Return False
	EndIf
	Local $iItemCnt = ControlListView($win, "", $hListView, "GetItemCount")

	Local $modeFound = False
	; Find a correct game mode in the ListView and select it
	For $i = 0 To $iItemCnt - 1
		Local $c = 0
		Local $sText = _GUICtrlListView_GetItemTextString($hListView, $i)
		If $sText = $mode Then
			ControlListView($win, "", $hListView, "Select", $i, $c)
			$modeFound = True
			ExitLoop
		EndIf
	Next

	If Not $modeFound Then
		;MsgBox($MB_OK, "Error", "Could not find mode: " & $mode)
		ErrorLog("Game mode cannot be found - " & $mode, @ScriptName, @ScriptLineNumber)
		Return False
	EndIf

	Sleep(200)

	;Load Game
	Local $status = GetGameStatus()
	Switch $status
		Case "STANDBY"
			;Click - Run script
			ClickButton($win, $g_LoadGameBtnId)
		Case "ARMED"
			;Click - Setup new game
			ClickButton($win, $g_LoadGameBtnId)
	EndSwitch

	; Wait for the game load process to end, otherwise the window will become active again after and the return to the previously active window will not work
	Sleep(6000)

	; Return to the previously active window
	; Doesn't matter if it fails
	WinActivate($activeWin)
	return True
EndFunc