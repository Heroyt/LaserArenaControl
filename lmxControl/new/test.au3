#include <MsgBoxConstants.au3>
#include <GuiListView.au3>

$win = WinGetHandle("LaserMaxx EVO-5 Console")
$hListView = ControlGetHandle($win, "", "[CLASS:ListView20WndClass; INSTANCE:5]")

MsgBox($MB_SYSTEMMODAL, "Info", ControlListView($win, "", $hListView, "GetSubItemCount"))

$i = ControlListView($win, "", $hListView, "FindItem", "1-TEAM-DEATHMACH",1)
MsgBox($MB_SYSTEMMODAL, "Info", $i)

MsgBox($MB_SYSTEMMODAL, "Info", ControlListView($win, "", $hListView, "GetText", 1, 1))