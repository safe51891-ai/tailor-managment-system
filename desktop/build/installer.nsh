!macro customInstall
  DetailPrint "Installing the Microsoft runtime included with Tailor Shop..."
  ExecWait '"$INSTDIR\resources\vc_redist.x64.exe" /install /quiet /norestart' $0
  ${If} $0 != 0
  ${AndIf} $0 != 3010
  ${AndIf} $0 != 1638
    MessageBox MB_ICONSTOP "The Microsoft runtime could not be installed (code $0). Please retry the installer as an administrator."
    Abort
  ${EndIf}
!macroend
