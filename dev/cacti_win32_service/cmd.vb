Imports System.ServiceProcess

Public Class cmd
    Inherits System.ServiceProcess.ServiceBase

#Region " Component Designer generated code "

    Public Sub New()
        MyBase.New()

        ' This call is required by the Component Designer.
        InitializeComponent()

        ' Add any initialization after the InitializeComponent() call

    End Sub

    'UserService overrides dispose to clean up the component list.
    Protected Overloads Overrides Sub Dispose(ByVal disposing As Boolean)
        If disposing Then
            If Not (components Is Nothing) Then
                components.Dispose()
            End If
        End If
        MyBase.Dispose(disposing)
    End Sub

    ' The main entry point for the process
    <MTAThread()> _
    Shared Sub Main()
        Dim ServicesToRun() As System.ServiceProcess.ServiceBase

        ' More than one NT Service may run within the same process. To add
        ' another service to this process, change the following line to
        ' create a second service object. For example,
        '
        '   ServicesToRun = New System.ServiceProcess.ServiceBase () {New Service1, New MySecondUserService}
        '
        ServicesToRun = New System.ServiceProcess.ServiceBase() {New cmd()}

        System.ServiceProcess.ServiceBase.Run(ServicesToRun)
    End Sub

    'Required by the Component Designer
    Private components As System.ComponentModel.IContainer

    ' NOTE: The following procedure is required by the Component Designer
    ' It can be modified using the Component Designer.  
    ' Do not modify it using the code editor.
    <System.Diagnostics.DebuggerStepThrough()> Private Sub InitializeComponent()
        components = New System.ComponentModel.Container()
        Me.ServiceName = "Service1"
    End Sub

#End Region


    Public WithEvents tmrCmd As New Timers.Timer()
    Dim strPath As String

    Protected Overrides Sub OnStart(ByVal args() As String)
        strPath = Environment.CurrentDirectory

        Debug.WriteLine(strPath)

        'trigger timer every 5 minutes
        tmrCmd.Interval = 60 * 5
        tmrCmd.AutoReset = True
        tmrCmd.Enabled = True
        tmrCmd.Start()
    End Sub

    Protected Overrides Sub OnStop()
        ' Add code here to perform any tear-down necessary to stop your service.
    End Sub

    Private Sub tmrCmd_tick(ByVal e As Object, ByVal args As Timers.ElapsedEventArgs) Handles tmrCmd.Elapsed
        Dim fs As IO.File
        Dim f As IO.StreamReader

        Try
            f = fs.OpenText(strPath & "config.txt")
        Catch
            writeEventLog("ERROR: " & Err.Number & " " & Err.Description)
        End Try

        writeEventLog("Started:" & f.ReadLine)

        Try
            Shell(Environment.GetEnvironmentVariable("ComSpec") & " \k " & f.ReadLine, AppWinStyle.Hide, True)
        Catch
            writeEventLog("ERROR: " & Err.Number & " " & Err.Description)
        End Try

        writeEventLog("Finished:" & f.ReadLine)
        f.Close()
    End Sub

    Private Sub writeEventLog(ByVal strInput As String)
        Dim strSource As String = "Cacti Win32 Service"
        If EventLog.SourceExists(strSource) = False Then
            EventLog.CreateEventSource(strSource, "Application")
        End If
        EventLog.WriteEntry(strSource, strInput)
    End Sub

End Class
