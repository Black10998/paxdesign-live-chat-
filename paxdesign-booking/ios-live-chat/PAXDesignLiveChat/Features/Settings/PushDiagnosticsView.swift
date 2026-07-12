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
                diagRow(L10n.PushDiagToken, diagnostics.deviceTokenPrefix)
                diagRow(L10n.PushDiagEnvironment, diagnostics.apnsEnvironment)
                diagRow(L10n.PushDiagDeviceId, PAXDeviceInfo.deviceId)
            }

            Section(L10n.PushDiagSectionServer) {
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
        .task { await refreshAll() }
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
        await diagnostics.refreshLocalState(push: push)
        await permissions.refreshStatuses()
    }
}
