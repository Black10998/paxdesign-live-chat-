import SwiftUI

struct PushDiagnosticsView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var push: PushService
    @EnvironmentObject private var permissions: PermissionCoordinator
    @ObservedObject private var diagnostics = PushDiagnosticsStore.shared

    var body: some View {
        List {
            Section(L10n.PushDiagSectionLocal) {
                diagRow(L10n.PushDiagPermission, diagnostics.authorizationStatus)
                diagRow(L10n.PushDiagRegistrationRequested, diagnostics.registrationRequested ? L10n.CommonYes : L10n.CommonNo)
                if diagnostics.registrationRequestCount > 0 {
                    diagRow(L10n.PushDiagRegistrationCount, String(diagnostics.registrationRequestCount))
                }
                if let at = diagnostics.lastRegistrationRequestAt {
                    diagRow(L10n.PushDiagRegistrationAt, at.formatted(date: .abbreviated, time: .shortened))
                }
                diagRow(L10n.PushDiagApnsResponded, diagnostics.apnsResponded ? L10n.CommonYes : L10n.CommonNo)
                diagRow(L10n.PushDiagIosStatus, diagnostics.iosRegistrationStatus)
                diagRow(L10n.PushDiagTokenPrefix, diagnostics.deviceTokenPrefix)
                diagRow(L10n.PushDiagTokenSuffix, diagnostics.deviceTokenSuffix)
                if let at = diagnostics.tokenReceivedAt {
                    diagRow(L10n.PushDiagTokenAt, at.formatted(date: .abbreviated, time: .shortened))
                }
                diagRow(L10n.PushDiagEnvironment, diagnostics.apnsEnvironment)
                diagRow(L10n.PushDiagApsEntitlement, diagnostics.apsEntitlement)
                if !PAXAPNsEnvironment.hasPushEntitlement {
                    Text("Push entitlement is missing from this build. Reinstall from TestFlight after a new build is uploaded.")
                        .font(.caption)
                        .foregroundStyle(PAXTheme.danger)
                }
                if push.registrationBlocked {
                    Text(push.registrationBlockedReason ?? "APNs registration paused.")
                        .font(.caption)
                        .foregroundStyle(PAXTheme.danger)
                }
                diagRow(L10n.PushDiagDeviceId, PAXDeviceInfo.deviceId)
                if let error = diagnostics.iosRegistrationError, !error.isEmpty {
                    Text(error)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.danger)
                }
            }

            Section(L10n.PushDiagSectionServer) {
                diagRow(L10n.PushDiagServerUpload, diagnostics.serverUploadAttempted ? L10n.CommonYes : L10n.CommonNo)
                diagRow(L10n.PushDiagServerAccepted, diagnostics.serverAccepted ? L10n.CommonYes : L10n.CommonNo)
                diagRow(L10n.PushDiagServerPushEnabled, diagnostics.serverPushEnabled ? L10n.CommonYes : L10n.CommonNo)
                diagRow(L10n.PushDiagServerStatus, diagnostics.serverRegistrationStatus)
                if let at = diagnostics.serverRegistrationAt {
                    diagRow(L10n.PushDiagServerAt, at.formatted(date: .abbreviated, time: .shortened))
                }
                if let error = diagnostics.lastServerError, !error.isEmpty {
                    Text(error)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.danger)
                }
            }

            if let test = diagnostics.lastTestResult {
                Section(L10n.PushDiagSectionLastTest) {
                    diagRow(L10n.PushDiagTestSent, test.sent ? L10n.CommonYes : L10n.CommonNo)
                    diagRow(L10n.PushDiagTestType, test.pushType)
                    diagRow(L10n.PushDiagTestStatus, test.apnsHTTPStatus > 0 ? String(test.apnsHTTPStatus) : "—")
                    diagRow(L10n.PushDiagEnvironment, test.environment)
                    if !test.appleResponse.isEmpty {
                        diagRow(L10n.PushDiagAppleResponse, test.appleResponse)
                    }
                    if let reason = test.failureReason, !reason.isEmpty {
                        Text(reason)
                            .font(.caption)
                            .foregroundStyle(PAXTheme.danger)
                    }
                    diagRow(L10n.PushDiagTestAt, test.testedAt.formatted(date: .abbreviated, time: .shortened))
                }
            }

            Section {
                Button {
                    Task { await refreshAll() }
                } label: {
                    HStack {
                        Text(L10n.PushDiagRefresh)
                        Spacer()
                        if diagnostics.isRefreshing {
                            ProgressView()
                        }
                    }
                }
                .disabled(diagnostics.isRefreshing)

                Button {
                    Task { await runRepair() }
                } label: {
                    HStack {
                        Text(L10n.PushDiagRepair)
                        Spacer()
                        if diagnostics.isRefreshing {
                            ProgressView()
                        }
                    }
                }
                .disabled(diagnostics.isRefreshing || auth.api == nil)

                Button {
                    Task { await diagnostics.runTestPush(auth: auth) }
                } label: {
                    HStack {
                        Text(L10n.PushDiagSendTest)
                        Spacer()
                        if diagnostics.isRefreshing {
                            ProgressView()
                        }
                    }
                }
                .disabled(diagnostics.isRefreshing || auth.api == nil)

                if permissions.shouldOpenSystemSettingsForNotifications {
                    Button(L10n.SettingsOpenIosSettings) {
                        permissions.openSystemSettings()
                    }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.PushDiagTitle)
        .navigationBarTitleDisplayMode(.inline)
        .task { await diagnostics.refreshLocalOnly(push: push) }
    }

    private func diagRow(_ title: String, _ value: String) -> some View {
        HStack(alignment: .top, spacing: 12) {
            Text(title)
                .font(.subheadline)
            Spacer(minLength: 8)
            Text(value)
                .font(.subheadline.monospaced())
                .foregroundStyle(PAXTheme.textSecondary)
                .multilineTextAlignment(.trailing)
        }
    }

    private func refreshAll() async {
        await diagnostics.refreshWithRegistration(auth: auth, push: push)
        await permissions.refreshStatuses()
        if auth.isLoggedIn {
            await DeviceSessionService.shared.registerWithPush(auth: auth, reason: .userAction)
            await diagnostics.refreshLocalOnly(push: push)
        }
    }

    private func runRepair() async {
        await diagnostics.repairRegistration(auth: auth, push: push)
    }
}
