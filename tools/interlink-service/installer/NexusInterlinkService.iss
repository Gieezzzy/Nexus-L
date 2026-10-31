; Nexus-L InterLink Service - installer
; Build:  iscc /DSourceDir="<publish folder>" NexusInterlinkService.iss
#ifndef SourceDir
  #define SourceDir "..\bin\publish"
#endif
#define AppName "Nexus-L InterLink Service"
#define AppVersion "1.1.0"
#define AppCredit "Nexus-L InterLink Sender - Credited by Systems & Process Audit"
#define AppExe "NexusInterlinkService.exe"

[Setup]
AppId={{6E1C0B52-4D3A-4B7E-9C21-5A7F0E2D8B14}
AppName={#AppName}
AppVersion={#AppVersion}
AppPublisher=Systems & Process Audit
AppCopyright={#AppCredit}
VersionInfoCompany=Systems & Process Audit
VersionInfoDescription={#AppName} Setup
VersionInfoVersion={#AppVersion}
DefaultDirName={localappdata}\Programs\NexusInterlinkService
DefaultGroupName={#AppName}
UninstallDisplayName={#AppName}
UninstallDisplayIcon={app}\{#AppExe}
PrivilegesRequired=lowest
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
DisableProgramGroupPage=yes
OutputDir=output
OutputBaseFilename=NexusInterlinkService-Setup-{#AppVersion}
Compression=lzma2/ultra64
SolidCompression=yes
InternalCompressLevel=ultra64
LZMAUseSeparateProcess=yes
WizardStyle=modern
InfoBeforeFile=credits.txt
CloseApplications=force
RestartApplications=no

[Tasks]
Name: "desktopicon"; Description: "Create a desktop shortcut"; GroupDescription: "Shortcuts:"
Name: "autostart"; Description: "Start with Windows (recommended - the service must be running to receive uploads)"; GroupDescription: "Startup:"

[Files]
; settings and session of an earlier installation (appsettings.Local.json, data\) are not part of the package, so an update keeps them
Source: "{#SourceDir}\*"; DestDir: "{app}"; Excludes: "appsettings.Local.json,data\*"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{autoprograms}\{#AppName}"; Filename: "{app}\{#AppExe}"
Name: "{autodesktop}\{#AppName}"; Filename: "{app}\{#AppExe}"; Tasks: desktopicon

[Registry]
Root: HKCU; Subkey: "Software\Microsoft\Windows\CurrentVersion\Run"; ValueType: string; ValueName: "NexusInterlinkService"; ValueData: """{app}\{#AppExe}"""; Tasks: autostart; Flags: uninsdeletevalue

[Run]
Filename: "{app}\{#AppExe}"; Parameters: "--signin"; Description: "Sign in to InterLink now (one time)"; Flags: postinstall nowait skipifsilent unchecked
Filename: "{app}\{#AppExe}"; Description: "Start {#AppName}"; Flags: postinstall nowait skipifsilent

[UninstallDelete]
Type: filesandordirs; Name: "{app}\data"
