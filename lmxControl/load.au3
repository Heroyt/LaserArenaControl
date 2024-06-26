#include <MsgBoxConstants.au3>
#include <GuiListView.au3>

#include "functions.au3"

Func load($mode)
	$activeWin = WinGetHandle("[ACTIVE]")
	$win = WinActivate("LaserMaxx EVO-5 Console")

	;Load 0000.game
   ControlClick($win, "", "[ID:302]")
   $win2 = WinWait("Load game file")
   WinActivate($win2)
   Sleep(200)

   ControlSetText($win2, "", "[CLASS:ToolbarWindow32; INSTANCE:2]", "C:\LaserMaxx\shared\games")
   ControlSetText($win2, "", "[CLASS:Edit; INSTANCE:1]", "0000.game")
   Sleep(200)
   Send("{ENTER}")

	;Set mode
    $win = WinActivate("LaserMaxx EVO-5 Console")

    $hListView = ControlGetHandle($win, "", "[CLASS:ListView20WndClass; INSTANCE:5]")
    $iItemCnt = ControlListView($win, "", $hListView, "GetItemCount")

    For $i = 0 To $iItemCnt - 1
	  $c = 0
	  $sText = _GUICtrlListView_GetItemText($hListView, $i, $c)
	  If $sText = $mode Then
		 ControlListView($win, "", $hListView, "Select", $i, $c)
	  EndIf
    Next

	;Load Game
	$status = GetGameStatus()
	Switch $status
		Case "STANDBY"
			ControlClick($win, "", 361) ;Click - Run script
		Case "ARMED"
			ControlClick($win, "", 368) ;Click - Setup new game
	  EndSwitch
	  Sleep(6000)
	  WinActivate($activeWin)
EndFunc