#include <GUIConstants.au3>
#include <CommMG.au3>
#include <GuiEdit.au3>
#include <WindowsConstants.au3>

$Main = GUICreate("Com Port Redirector - Com Port Configuration", 400, 150)
GUISetFont(10, 400, "Times New Roman")
$cmboPortsAvailable1 = GUICtrlCreateCombo("", 110, 75, 75, 25)
$lblPort1 = GUICtrlCreateLabel("Connect Port", 30, 78, 80, 25)
$cmboPortsAvailable2 = GUICtrlCreateCombo("", 280, 75, 75, 25)
$lblPort2 = GUICtrlCreateLabel("To Port", 230, 78, 50, 25)
$lblBaud = GUICtrlCreateLabel("Baud Rate for both Ports", 124, 10, 160, 25)
$CmBoBaud = GUICtrlCreateCombo("", 160, 30, 70, 25)
GUICtrlSetData(-1, "50|75|110|150|600|1200|1800|2000|2400|3600|4800|7200|9600|10400|14400|15625|19200|28800|38400|56000|57600|115200|", "4800")
$btnConnect = GUICtrlCreateButton("Connect Com Ports", 130, 120, 140, 25)

GUISetState(@SW_SHOW, $Main)

GUISetOnEvent($GUI_EVENT_CLOSE, "Bye")
$Main1 = GUICreate("Com Port Redirector - Monitor Window", 600, 200)
$lblBaud1 = GUICtrlCreateLabel("", 50, 10, 150, 25)
$lblPort3 = GUICtrlCreateLabel(GUICtrlRead($cmboPortsAvailable1), 10, 10, 35, 25)
$monitor1 = GUICtrlCreateEdit("", 10, 30, 550, 40, BitOR($WS_EX_WINDOWEDGE, $ES_READONLY, $ES_AUTOVSCROLL, $ES_MULTILINE, $WS_VSCROLL))
$lblPort4 = GUICtrlCreateLabel(GUICtrlRead($cmboPortsAvailable2), 10, 90, 35, 25)
$lblBaud2 = GUICtrlCreateLabel("", 50, 90, 150, 25)
$monitor2 = GUICtrlCreateEdit("", 10, 110, 550, 40, BitOR($WS_EX_WINDOWEDGE, $ES_READONLY, $ES_AUTOVSCROLL, $ES_MULTILINE, $WS_VSCROLL))
$btnDisconnect = GUICtrlCreateButton("Disconnect Com Ports", 230, 160, 140, 25)

GetPorts()
SetupComPorts()

GUISetState(@SW_HIDE, $Main)
GUISetState(@SW_SHOW, $Main1)

Events()
_CommClearOutputBuffer()
_CommClearInputBuffer()

While 1
    _CommSwitch(1)
    $instr = _CommGetline(@CR, 1000, 200)
    GUICtrlSetData($monitor1, $instr)
    _CommSwitch(2)
    _CommSendString($instr)
    $instr = _CommGetline(@CR, 1000, 200)
    GUICtrlSetData($monitor2, $instr)
    _CommSwitch(1)
    _CommSendString($instr)
WEnd


Func Events()
    $msg = GUIGetMsg()
        If $msg = $btnDisconnect Then
            Disconnect()
        EndIf
    Opt("GUIOnEventMode", 1)
    GUISetOnEvent($GUI_EVENT_CLOSE, "Bye")
EndFunc;==>Events


Func Bye()
    _Commcloseport()
    Exit
EndFunc;==>Bye

Func GetPorts();Find all com ports
  $portlist = _CommListPorts(0);find the available COM ports and write them into the ports combo
  If @error = 1 Then
    MsgBox(0, 'trouble getting portlist', 'Program will terminate!')
    Exit
  EndIf
  If IsArray($portlist) then
    For $pl = 1 To $portlist[0]
    GUICtrlSetData($cmboPortsAvailable1, $portlist[$pl]);_CommListPorts())
    GUICtrlSetData($cmboPortsAvailable2, $portlist[$pl]);_CommListPorts())
    Next
  Else
    MsgBox(262144,"ERROR", "No COM ports found on this PC.")
  EndIf
  EndFunc;==>GetPorts


Func SetupComPorts();Open com port
    Local $sportSetError
    $baud = GUICtrlRead($CmBoBaud)
    GUICtrlSetData($lblBaud1, $baud & " bps  -  Monitor 1")
    GUICtrlSetData($lblBaud2, $baud & " bps  -  Monitor 2")
    While 1
        Sleep(50)
        $msg = GUIGetMsg()
        If $msg = -3 Then; Exit button on GUI
            Exit
        EndIf
        If $msg = $btnConnect Then
            Sleep(50)
            _CommSwitch(1)
            $setport1 = StringReplace(GUICtrlRead($cmboPortsAvailable1), 'COM', '')
            _CommSetPort($setport1, $sportSetError, $baud, 8, 0, 1, 0)
            GUICtrlSetData($lblPort3, GUICtrlRead($cmboPortsAvailable1))
            _CommSwitch(2)
            $setport2 = StringReplace(GUICtrlRead($cmboPortsAvailable2), 'COM', '')
            _CommSetPort($setport2, $sportSetError, $baud, 8, 0, 1, 0)
            GUICtrlSetData($lblPort4, GUICtrlRead($cmboPortsAvailable2))
            If @error Then MsgBox(0, 'Setport error = ', $sportSetError)
                ExitLoop
        EndIf
    WEnd
EndFunc;==>SetupComPorts

Func Disconnect()
    _CommSwitch(1)
    _CommClosePort()
    _CommSwitch(2)
    _CommClosePort()
EndFunc;==>Disconnect